<?php

namespace Modules\ZabbixBackupRestore\Lib;

use RuntimeException;

final class References {

	private const TARGETS = [
		'hosts' => ['Host', 'hostid', 'host'],
		'templates' => ['Template', 'templateid', 'host'],
		'host_groups' => ['HostGroup', 'groupid', 'name'],
		'template_groups' => ['TemplateGroup', 'groupid', 'name'],
		'media_types' => ['MediaType', 'mediatypeid', 'name'],
		'modules' => ['Module', 'moduleid', 'id'],
		'mfa' => ['Mfa', 'mfaid', 'name'],
		'user_directories' => ['UserDirectory', 'userdirectoryid', 'name'],
		'triggers' => ['Trigger', 'triggerid', 'description']
	];

	/** @var Api */ private $api;
	/** @var array */ private $indexes = [];

	public function __construct(Api $api) {
		$this->api = $api;
	}

	public function clear(string $type): void {
		unset($this->indexes[$type]);
	}

	public static function key($value): string {
		return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	public static function identity(string $type, array $row) {
		if ($type === 'scripts') {
			return is_string($row['name'] ?? null) && $row['name'] !== ''
				? [(string) ($row['menu_path'] ?? ''), $row['name']]
				: null;
		}

		return $row[(self::TARGETS[$type] ?? Catalog::RECORDS[$type])[2]] ?? null;
	}

	public static function validIdentity($value): bool {
		return (is_string($value) && $value !== '')
			|| (is_array($value) && array_values($value) === $value && $value !== []
				&& count($value) === count(array_filter($value, 'is_string')));
	}

	public function has(string $type, $value): bool {
		if (!isset($this->indexes[$type])) {
			$this->build($type);
		}

		return isset($this->indexes[$type]['by_key'][self::key($value)]);
	}

	/**
	 * Optional ID zero represents a Zabbix sentinel, for example “current host” or “all media types”.
	 * The YAML representation is null so that a numeric-looking object name remains unambiguous.
	 */
	public function convert(string $type, $value, bool $export, bool $optional = false) {
		if ($optional && ((!$export && $value === null)
				|| ($export && ($value === null || $value === '0' || $value === 0)))) {
			return $export ? null : '0';
		}

		if (($export && !(is_int($value) || is_string($value))) || (!$export && !self::validIdentity($value))) {
			throw new RuntimeException(LanguageManager::format('A required reference in %s is invalid.', $type));
		}

		if ($type === 'triggers') {
			return $this->trigger($value, $export);
		}

		if (!isset($this->indexes[$type])) {
			$this->build($type);
		}

		$index = $this->indexes[$type];
		$key = $export ? (string) $value : self::key($value);
		$result = $export ? ($index['by_id'][$key] ?? null) : ($index['by_key'][$key] ?? null);
		if ($result === null || ($export && ($index['by_key'][self::key($result)] ?? null) === null)) {
			$this->missing($type, $value);
		}

		return $result;
	}

	private function build(string $type): void {
		$by_id = [];
		$by_key = [];
		if ($type === 'discovery_checks') {
			$rows = $this->api->call('DRule', 'get', [
				'output' => ['name'], 'selectDChecks' => 'extend'
			]);
			foreach ($rows as $rule) {
				foreach ($rule['dchecks'] as $check) {
					$by_id[(string) $check['dcheckid']] = self::checkIdentity($rule['name'], $check);
				}
			}
		}
		else {
			[$service, $id, $field] = self::TARGETS[$type] ?? Catalog::RECORDS[$type];
			if ($type === 'proxies' && version_compare(ZABBIX_VERSION, '6.4', '<')) {
				$field = 'host';
			}
			$options = ['output' => [$id, $field]];
			if ($type === 'scripts') {
				$options['output'][] = 'menu_path';
			}
			foreach ($this->api->call($service, 'get', $options) as $row) {
				if ($type === 'proxies' && $field === 'host') {
					$row['name'] = $row['host'];
				}
				$identity = self::identity($type, $row);
				if (self::validIdentity($identity)) {
					$by_id[(string) $row[$id]] = $identity;
				}
			}
		}

		foreach ($by_id as $id => $value) {
			$key = self::key($value);
			$by_key[$key] = array_key_exists($key, $by_key) ? null : (string) $id;
		}
		$this->indexes[$type] = ['by_id' => $by_id, 'by_key' => $by_key];
	}

	private function trigger($value, bool $export) {
		$options = ['output' => ['triggerid', 'description', 'expression', 'recovery_expression']];
		if ($export) {
			$options['triggerids'] = [$value];
		}
		elseif (!is_array($value) || count($value) !== 3) {
			$this->missing('triggers', $value);
		}
		else {
			$options['filter'] = ['description' => [$value[0]]];
		}
		$cache_key = $export ? 'id'.$value : 'description'.$value[0];
		if (!array_key_exists($cache_key, $this->indexes['triggers'] ?? [])) {
			$rows = $this->api->call('Trigger', 'get', $options);
			$rows = \CMacrosResolverHelper::resolveTriggerExpressions($rows, [
				'sources' => ['expression', 'recovery_expression']
			]);
			$this->indexes['triggers'][$cache_key] = $rows;
		}
		$matching = [];
		foreach ($this->indexes['triggers'][$cache_key] as $trigger) {
			$identity = [$trigger['description'], $trigger['expression'], $trigger['recovery_expression']];
			if ($export || $value === $identity) {
				$matching[] = $export ? $identity : (string) $trigger['triggerid'];
			}
		}
		if (count($matching) !== 1) {
			$this->missing('triggers', $export ? $value : $value[0]);
		}

		return $matching[0];
	}

	public static function checkIdentity(string $rule, array $check): array {
		$fields = [];
		foreach (explode(' ', Records::CHECK_FIELDS) as $field) {
			if ($field !== 'uniq') {
				$fields[] = (string) ($check[$field] ?? '');
			}
		}
		return [$rule, hash('sha256', self::key($fields))];
	}

	private function missing(string $type, $value): void {
		$labels = Catalog::labels('8.0') + ['triggers' => 'Triggers', 'discovery_checks' => 'Discovery checks',
			'modules' => 'Frontend modules', 'mfa' => 'MFA methods', 'user_directories' => 'User directories'];
		$name = is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
		throw new RuntimeException(LanguageManager::format('Missing or ambiguous reference to %s (%s).',
			LanguageManager::t($labels[$type] ?? $type), mb_substr($name, 0, 160)));
	}
}
