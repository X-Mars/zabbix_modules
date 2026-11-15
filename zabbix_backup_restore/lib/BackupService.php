<?php

namespace Modules\ZabbixBackupRestore\Lib;

use RuntimeException;

final class BackupService {

	private Api $api;
	private References $refs;
	private Records $records;

	public function __construct(Api $api) {
		$this->api = $api;
		$this->refs = new References($api);
		$this->records = new Records($this->refs);
	}

	public function export(array $selection, string $version): array {
		if (!$selection) {
			throw new RuntimeException(LanguageManager::t('Choose at least one configuration type.'));
		}
		foreach ($selection as $type) {
			if (!is_string($type) || !isset(Catalog::labels($version)[$type])) {
				throw new RuntimeException(LanguageManager::t('The selected configuration is invalid.'));
			}
		}

		$backup = [
			'schema_version' => 1,
			'source_version' => $version,
			'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
			'configuration' => [],
			'records' => [],
			'host_encryption' => [],
			'omissions' => ['secret_macros' => 0, 'provisioned_users' => 0]
		];
		$options = [];
		foreach (array_intersect_key(Catalog::NATIVE, array_flip($selection)) as $type => [$api, $id, $option]) {
			$params = ['output' => [$id]];
			if ($type === 'hosts') {
				$params['filter'] = ['flags' => ZBX_FLAG_DISCOVERY_NORMAL];
			}
			$rows = $this->api->call($api, 'get', $params);
			if ($rows) {
				$options[$option] = array_column($rows, $id);
			}
		}

		if ($options) {
			$config = $this->api->call('Configuration', 'export', ['options' => $options, 'format' => 'raw']);
			$backup['configuration'] = $config['zabbix_export'];
			self::maskNativeMacros($backup['configuration'], $backup['omissions']['secret_macros']);
		}
		if (!empty($backup['configuration']['hosts'])) {
			$backup['host_encryption'] = array_values($this->api->call('Host', 'get', [
				'output' => explode(' ', 'host '.Bundle::TLS_FIELDS), 'hostids' => $options['hosts']
			]));
		}

		foreach (array_intersect_key(Catalog::RECORDS, array_flip($selection)) as $type => [$api, , , $fields, $params]) {
			$output = explode(' ', $fields);
			if ($type === 'users') {
				$output[] = 'provisioned';
			}
			elseif ($type === 'proxies' && defined('PROXY_APM_DATA_COLLECTION_DISABLED')) {
				$output[] = 'timeout_telemetry_query';
				$params['selectApm'] = 'extend';
			}
			elseif ($type === 'user_groups' && defined('PROXY_MODE_ALLOW')) {
				$output = array_merge($output, ['proxy_mode', 'proxy_group_mode']);
				$params += ['selectProxies' => ['proxyid'], 'selectProxyGroups' => ['proxy_groupid']];
			}
			elseif ($type === 'regular_expressions' && defined('REGEX_TYPE_CONTAINS_ANY_SUBSTRING')) {
				$output[] = 'description';
			}
			$backup['records'][$type] = [];
			foreach ($this->api->call($api, 'get', ['output' => $output] + $params) as $row) {
				if ($type === 'global_macros' && (int) $row['type'] === ZBX_MACRO_TYPE_SECRET) {
					$backup['omissions']['secret_macros']++;
					unset($row['value']);
				}
				if ($type === 'users' && (int) $row['provisioned'] === 1) {
					$backup['omissions']['provisioned_users']++;
					continue;
				}

				$backup['records'][$type][] = $this->records->normalize($type, $row, true);
			}
		}

		if (!Bundle::counts($backup)) {
			throw new RuntimeException(LanguageManager::t('The selection contains no exportable records.'));
		}

		return $backup;
	}

	/**
	 * Native exports leave secret macro values empty. Count their declarations for the preview.
	 */
	private static function maskNativeMacros(array &$node, int &$count): void {
		if (isset($node['macros']) && is_array($node['macros'])) {
			foreach ($node['macros'] as &$macro) {
				if (($macro['type'] ?? null) === 'SECRET_TEXT') {
					unset($macro['value']);
					$count++;
				}
			}
			unset($macro);
		}
		foreach ($node as $key => &$value) {
			if ($key !== 'macros' && is_array($value)) {
				self::maskNativeMacros($value, $count);
			}
		}
		unset($value);
	}

	public function restore(array $bundle, bool $update, string $version): void {
		global $DB;

		if ($DB['TRANSACTIONS'] != 0) {
			throw new RuntimeException(LanguageManager::t('Could not start the restore transaction.'));
		}
		try {
			if (!\DBstart()) {
				throw new RuntimeException(LanguageManager::t('Could not start the restore transaction.'));
			}
			$records = $bundle['records'];
			$upgrade = (int) $bundle['source_version'] < (int) $version;
			foreach (Catalog::EARLY as $type) {
				$this->restoreRecords($type, $records[$type] ?? [], $update);
				unset($records[$type]);
			}

			$existing_hosts = $update || !$bundle['host_encryption'] ? [] : $this->existingHosts($bundle['host_encryption']);
			$native = $bundle['configuration'];
			$late = array_intersect_key($native, array_flip(Catalog::LATE_NATIVE));
			$this->importNative(array_diff_key($native, $late), $update, $version);
			$this->restoreHostEncryption($bundle['host_encryption'], $existing_hosts);

			foreach (array_keys(Catalog::RECORDS) as $type) {
				$this->restoreRecords($type, $records[$type] ?? [], $update, $upgrade);
			}
			if ($late) {
				$this->importNative(['version' => $native['version']] + $late, $update, $version);
			}

			if (!\DBend(true)) {
				throw new RuntimeException(LanguageManager::t('Could not commit the restore transaction.'));
			}
		}
		catch (\Throwable $exception) {
			if ($DB['TRANSACTIONS'] != 0) {
				\DBend(false);
			}
			throw $exception;
		}
	}

	private function importNative(array $configuration, bool $update, string $version): void {
		if ($update && !empty($configuration['media_types'])) {
			// Native media imports revalidate credentials, including those on untouched, unconfigured defaults.
			$configuration['media_types'] = $this->changedMediaTypes($configuration['media_types']);
			if (!$configuration['media_types']) {
				unset($configuration['media_types']);
			}
		}
		if (count($configuration) < 2) {
			return;
		}

		$merge = ['createMissing' => true, 'updateExisting' => $update];
		$rules = array_fill_keys([
			'host_groups', 'template_groups', 'templates', 'hosts', 'mediaTypes', 'images', 'maps',
			'items', 'triggers', 'discoveryRules', 'graphs', 'httptests', 'templateDashboards', 'valueMaps'
		], $merge);
		$rules['templateLinkage'] = ['createMissing' => true];
		if (substr($version, 0, 3) === '8.0') {
			$rules['dashboards'] = $merge;
		}

		$this->api->call('Configuration', 'import', [
			'format' => 'json',
			'rules' => $rules,
			'source' => json_encode(['zabbix_export' => $configuration], JSON_THROW_ON_ERROR)
		]);
	}

	private function changedMediaTypes(array $media_types): array {
		$matches = $this->api->call('MediaType', 'get', [
			'output' => ['mediatypeid'], 'filter' => ['name' => array_column($media_types, 'name')]
		]);
		if (!$matches) {
			return $media_types;
		}

		$export = $this->api->call('Configuration', 'export', [
			'format' => 'raw', 'options' => ['mediaTypes' => array_column($matches, 'mediatypeid')]
		]);
		$existing = array_column($export['zabbix_export']['media_types'] ?? [], null, 'name');
		return array_values(array_filter($media_types, static function (array $media) use ($existing): bool {
			return ($existing[$media['name'] ?? ''] ?? null) != $media;
		}));
	}

	private function existingHosts(array $rows): array {
		return array_fill_keys(array_column($this->api->call('Host', 'get', [
			'output' => ['host'], 'filter' => ['host' => array_column($rows, 'host')]
		]), 'host'), true);
	}

	private function restoreHostEncryption(array $rows, array $existing): void {
		$updates = [];
		foreach ($rows as $row) {
			if (!isset($existing[$row['host']])) {
				$updates[] = ['hostid' => $this->refs->convert('hosts', $row['host'], false)]
					+ Records::fields($row, Bundle::TLS_FIELDS);
			}
		}

		if ($updates) {
			$this->api->call('Host', 'update', $updates);
		}
	}

	private function restoreRecords(string $type, array $rows, bool $update, bool $upgrade = false): void {
		if (!$rows) {
			return;
		}

		[$api, $id, $identity] = Catalog::RECORDS[$type];
		$options = ['output' => [$id, $identity]];
		if ($type === 'global_macros') {
			$options['globalmacro'] = true;
		}
		elseif ($type === 'scripts') {
			$options['output'][] = 'menu_path';
		}
		elseif ($type === 'roles') {
			$options['output'][] = 'readonly';
		}
		elseif ($type === 'users') {
			$options['output'][] = 'provisioned';
		}
		elseif ($type === 'network_discovery') {
			$options['selectDChecks'] = 'extend';
		}
		elseif ($type === 'actions' && $update) {
			$options['output'] = array_merge([$id], explode(' ', Catalog::RECORDS['actions'][3]));
			$options += Catalog::RECORDS['actions'][4];
		}

		$existing = [];
		foreach ($this->api->call($api, 'get', $options) as $row) {
			$existing[References::key(References::identity($type, $row))] = $row;
		}

		$create = [];
		$change = [];
		$links = [];
		foreach ($rows as $row) {
			$key = References::key(References::identity($type, $row));
			$destination = $existing[$key] ?? null;
			if ($destination !== null && (!$update || $this->protectedObject($type, $destination))) {
				continue;
			}
			if ($type === 'actions' && $destination !== null
					&& $this->records->normalize($type, $destination, true) == $row) {
				continue;
			}

			$data = $this->records->normalize($type, $row, false, $upgrade);
			if ($type === 'services') {
				$links[] = $data;
				unset($data['children']);
			}
			if ($destination === null) {
				if ($type === 'users') {
					$data['passwd'] = 'Zb9!'.bin2hex(random_bytes(30));
				}
				elseif ($type === 'global_macros' && (int) ($data['type'] ?? -1) === ZBX_MACRO_TYPE_SECRET) {
					$data += ['value' => ''];
				}
				$create[] = $data;
			}
			else {
				if ($type === 'network_discovery') {
					$data = $this->records->matchDiscoveryChecks($data, $destination);
				}
				$change[] = [$id => $destination[$id]] + $data;
			}
		}

		$suffix = $type === 'global_macros' ? 'Global' : '';
		if ($create) {
			$this->api->call($api, 'create'.$suffix, $create);
		}
		if ($change) {
			$this->api->call($api, 'update'.$suffix, $change);
		}
		$this->refs->clear($type);
		if ($type === 'network_discovery') {
			$this->refs->clear('discovery_checks');
		}
		if ($links) {
			$this->restoreServiceLinks($links);
		}
	}

	private function restoreServiceLinks(array $rows): void {
		$desired = [];
		foreach ($rows as $row) {
			$id = $this->refs->convert('services', $row['uuid'], false);
			$desired[$id] = $this->records->serviceChildren($row);
		}

		$current = $this->api->call('Service', 'get', [
			'output' => ['serviceid'],
			'serviceids' => array_keys($desired),
			'selectChildren' => ['serviceid']
		]);
		$children = array_column($current, 'children', 'serviceid');
		$additions = [];
		foreach ($desired as $serviceid => $links) {
			$old_ids = array_column($children[$serviceid] ?? [], 'serviceid');
			$new_ids = array_column($links, 'serviceid');
			if (array_diff($old_ids, $new_ids)) {
				$retained = array_intersect($old_ids, $new_ids);
				$this->api->call('Service', 'update', [['serviceid' => $serviceid, 'children' => array_values(
					array_map(static fn($id): array => ['serviceid' => $id], $retained)
				)]]);
			}
			if (array_diff($new_ids, $old_ids)) {
				$additions[] = ['serviceid' => $serviceid, 'children' => $links];
			}
		}

		// Remove obsolete edges before adding new ones. One parent per call also handles edges
		// between two restored services whose existing links Zabbix 7.0 sees only as parent links.
		foreach ($additions as $addition) {
			$this->api->call('Service', 'update', [$addition]);
		}
	}

	private function protectedObject(string $type, array $row): bool {
		return ($type === 'roles' && ((int) $row['readonly'] === 1
				|| (string) $row['roleid'] === (string) \CWebUser::$data['roleid']))
			|| ($type === 'users' && ($row['username'] === ZBX_GUEST_USER || (int) $row['provisioned'] === 1
				|| (string) $row['userid'] === (string) \CWebUser::$data['userid']));
	}
}
