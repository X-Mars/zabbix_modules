<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/

namespace Modules\ZabbixClonehosts\Actions;

use CController,
	CControllerResponseData,
	CControllerResponseFatal,
	API;
use Modules\ZabbixClonehosts\CompatHelper;
use Modules\ZabbixClonehosts\LangHelper;

/**
 * AJAX import endpoint for Host Batch Clone module.
 *
 * Receives a single host's data along with the source host's
 * configuration, merges inherited fields, and creates the host
 * via the Zabbix API. Returns a JSON result.
 */
class ClonehostsImport extends CController {

	public function init(): void {
		if (method_exists($this, 'disableCsrfValidation')) {
			$this->disableCsrfValidation();
		}
		if (method_exists($this, 'disableSIDvalidation')) {
			$this->disableSIDvalidation();
		}
	}

	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'source_hostid' => 'required|string',
			'host_data' => 'required|array'
		]);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
	}

	protected function doAction(): void {
		$source_hostid = $this->getInput('source_hostid');
		$host_input = $this->getInput('host_data');

		$host_name = trim($host_input['host'] ?? '');
		$ip = trim($host_input['ip'] ?? '');
		$visible_name = trim($host_input['visible_name'] ?? '');
		$port = trim($host_input['port'] ?? '');
		$description = trim($host_input['description'] ?? '');
		$groups_input = trim($host_input['groups'] ?? '');
		$templates_input = trim($host_input['templates'] ?? '');
		$tags_input = trim($host_input['tags'] ?? '');
		$macros_input = trim($host_input['macros'] ?? '');

		$result = [
			'host' => $host_name,
			'ip' => $ip,
			'success' => false,
			'error' => '',
			'hostid' => ''
		];

		// Validate required fields.
		if ($host_name === '') {
			$result['error'] = LangHelper::t('err.host_required');
			$this->sendResponse($result);
			return;
		}

		if ($ip === '') {
			$result['error'] = LangHelper::t('err.ip_required');
			$this->sendResponse($result);
			return;
		}

		// Check if host already exists.
		$existing = API::Host()->get([
			'output' => ['hostid'],
			'filter' => ['host' => [$host_name]]
		]);

		if (!empty($existing)) {
			$result['error'] = LangHelper::t('err.host_exists');
			$this->sendResponse($result);
			return;
		}

		// Check if template with same name exists.
		$existing_tpl = API::Template()->get([
			'output' => ['templateid'],
			'filter' => ['host' => [$host_name]]
		]);

		if (!empty($existing_tpl)) {
			$result['error'] = LangHelper::t('err.template_exists');
			$this->sendResponse($result);
			return;
		}

		// Load the source host's full configuration for inheritance.
		// Use CompatHelper for version-correct host group select parameter.
		$host_get_params = [
			'output' => ['hostid', 'host', 'name', 'status', 'description',
				'inventory_mode', 'tls_connect', 'tls_accept', 'tls_issuer',
				'tls_subject', 'ipmi_authtype', 'ipmi_privilege',
				'ipmi_username', 'ipmi_password'
			],
			'hostids' => [$source_hostid],
			'selectInterfaces' => ['type', 'main', 'useip', 'ip', 'dns', 'port', 'details'],
			'selectParentTemplates' => ['templateid', 'host', 'name'],
			'selectTags' => ['tag', 'value'],
			'selectMacros' => ['macro', 'value', 'description', 'type']
		];
		$host_get_params = CompatHelper::buildHostGetParams($host_get_params);

		$source_host = API::Host()->get($host_get_params);

		if (empty($source_host)) {
			$result['error'] = LangHelper::t('err.source_not_found');
			$this->sendResponse($result);
			return;
		}

		$source_host = CompatHelper::normalizeHost($source_host[0]);

		// --- Build the host creation payload, inheriting from source host ---

		// 1. Interfaces: use source host's interface as template, override IP and port.
		$interfaces = [];
		if (!empty($source_host['interfaces'])) {
			foreach ($source_host['interfaces'] as $src_iface) {
				$iface = [
					'type' => (int) $src_iface['type'],
					'main' => (int) $src_iface['main'],
					'useip' => (int) $src_iface['useip'],
					'ip' => $ip,
					'dns' => $src_iface['useip'] ? ($src_iface['dns'] ?? '') : $ip,
					'port' => $port !== '' ? $port : $src_iface['port']
				];
				if (isset($src_iface['details']) && !empty($src_iface['details'])) {
					$iface['details'] = $src_iface['details'];
				}
				$interfaces[] = $iface;
			}
		} else {
			// Default: Zabbix agent interface.
			$interfaces[] = [
				'type' => 1,
				'main' => 1,
				'useip' => 1,
				'ip' => $ip,
				'dns' => '',
				'port' => $port !== '' ? $port : '10050'
			];
		}

		// 2. Host groups: use specified groups or inherit from source host.
		$groups = [];
		if ($groups_input !== '') {
			$group_names = array_filter(array_map('trim', explode(';', $groups_input)));
			if (!empty($group_names)) {
				// Look up existing groups.
				$db_groups = API::HostGroup()->get([
					'output' => ['groupid', 'name'],
					'filter' => ['name' => $group_names]
				]);
				$found_group_names = array_column($db_groups, 'name');

				foreach ($db_groups as $dg) {
					$groups[] = ['groupid' => $dg['groupid']];
				}

				// Auto-create any groups that don't exist yet.
				$missing_groups = array_diff($group_names, $found_group_names);
				foreach ($missing_groups as $mg_name) {
					try {
						$created = API::HostGroup()->create(['name' => $mg_name]);
						if (isset($created['groupids'][0])) {
							$groups[] = ['groupid' => $created['groupids'][0]];
						}
					} catch (\Exception $e) {
						// If group creation fails, skip it silently.
						// The group will simply not be added.
					}
				}
			}
		}
		if (empty($groups) && !empty($source_host['hostgroups'])) {
			foreach ($source_host['hostgroups'] as $sg) {
				$groups[] = ['groupid' => $sg['groupid']];
			}
		}

		if (empty($groups)) {
			$result['error'] = LangHelper::t('err.no_groups');
			$this->sendResponse($result);
			return;
		}

		// 3. Templates: use specified templates or inherit from source host.
		$templates = [];
		if ($templates_input !== '') {
			$template_names = array_filter(array_map('trim', explode(';', $templates_input)));
			if (!empty($template_names)) {
				$db_templates = API::Template()->get([
					'output' => ['templateid', 'host', 'name'],
					'filter' => ['host' => $template_names]
				]);
				// Also try matching by visible name.
				$found_hosts = array_column($db_templates, 'host');
				$missing = array_diff($template_names, $found_hosts);
				if (!empty($missing)) {
					$db_templates_by_name = API::Template()->get([
						'output' => ['templateid', 'host', 'name'],
						'filter' => ['name' => array_values($missing)]
					]);
					$db_templates = array_merge($db_templates, $db_templates_by_name);
				}
				foreach ($db_templates as $dt) {
					$templates[] = ['templateid' => $dt['templateid']];
				}
			}
		}
		if (empty($templates) && !empty($source_host['parentTemplates'])) {
			foreach ($source_host['parentTemplates'] as $st) {
				$templates[] = ['templateid' => $st['templateid']];
			}
		}

		// 4. Tags: use specified tags or inherit from source host.
		$tags = [];
		if ($tags_input !== '') {
			$tag_pairs = array_filter(array_map('trim', explode(';', $tags_input)));
			foreach ($tag_pairs as $pair) {
				$parts = explode('=', $pair, 2);
				if (count($parts) === 2) {
					$tags[] = [
						'tag' => trim($parts[0]),
						'value' => trim($parts[1])
					];
				} elseif (count($parts) === 1 && trim($parts[0]) !== '') {
					$tags[] = [
						'tag' => trim($parts[0]),
						'value' => ''
					];
				}
			}
		}
		if (empty($tags) && !empty($source_host['tags'])) {
			foreach ($source_host['tags'] as $st) {
				$tags[] = [
				'tag' => $st['tag'],
				'value' => $st['value'] ?? ''
			];
			}
		}

		// 5. Macros: use specified macros or inherit from source host.
		$macros = [];
		if ($macros_input !== '') {
			$macro_pairs = array_filter(array_map('trim', explode(';', $macros_input)));
			foreach ($macro_pairs as $pair) {
				$parts = explode('=', $pair, 2);
				if (count($parts) === 2) {
					$macros[] = [
						'macro' => trim($parts[0]),
						'value' => trim($parts[1]),
						'type' => 0
					];
				}
			}
		}
		if (empty($macros) && !empty($source_host['macros'])) {
			foreach ($source_host['macros'] as $sm) {
				$macros[] = [
				'macro' => $sm['macro'],
				'value' => $sm['value'] ?? '',
				'type' => (int) ($sm['type'] ?? 0)
			];
				if (isset($sm['description'])) {
					$macros[count($macros) - 1]['description'] = $sm['description'];
				}
			}
		}

		// 6. Build the final host creation array.
		$create_host = [
			'host' => $host_name,
			'name' => $visible_name !== '' ? $visible_name : $host_name,
			'status' => (int) $source_host['status'],
			'interfaces' => $interfaces,
			'groups' => $groups,
			'templates' => $templates,
			'tags' => $tags,
			'macros' => $macros,
			'inventory_mode' => (int) $source_host['inventory_mode'],
			'description' => $description !== '' ? $description : ($source_host['description'] ?? ''),
			'tls_connect' => (int) $source_host['tls_connect'],
			'tls_accept' => (int) $source_host['tls_accept']
		];

		if (!empty($source_host['tls_issuer'])) {
			$create_host['tls_issuer'] = $source_host['tls_issuer'];
		}
		if (!empty($source_host['tls_subject'])) {
			$create_host['tls_subject'] = $source_host['tls_subject'];
		}

		// 7. Create the host via API.
		try {
			$created = API::Host()->create($create_host);

			if (isset($created['hostids'][0])) {
				$result['success'] = true;
				$result['hostid'] = $created['hostids'][0];
			} else {
				$result['error'] = LangHelper::t('err.unknown_api_response');
			}
		} catch (\Exception $e) {
			$result['error'] = $e->getMessage();
		}

		$this->sendResponse($result);
	}

	/**
	 * Set JSON response via CControllerResponseData.
	 * The view (clonehosts.import.php) outputs the data as JSON.
	 */
	private function sendResponse(array $data): void {
		$this->setResponse(new CControllerResponseData($data));
	}
}
