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
 * Preview page controller for Zabbix Clonehosts module.
 *
 * Receives host data from the main form, loads the source host's
 * configuration for inheritance, checks for duplicate host names
 * and existing hosts, and displays a preview before import.
 */
class ClonehostsPreview extends CController {

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
			'host_data' => 'required|string'
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
		$host_data_json = $this->getInput('host_data');
		$host_data = json_decode($host_data_json, true);

		if (!is_array($host_data)) {
			$this->setResponse(new CControllerResponseFatal());
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

		$source_host = $source_host ? CompatHelper::normalizeHost($source_host[0]) : [];

		// Check for duplicate host names within the batch.
		$batch_names = [];
		$duplicate_names = [];
		foreach ($host_data as $item) {
			$host_name = trim($item['host'] ?? '');
			if ($host_name === '') {
				continue;
			}
			if (in_array($host_name, $batch_names)) {
				$duplicate_names[] = $host_name;
			}
			$batch_names[] = $host_name;
		}

		// Check for existing hosts in Zabbix with the same names.
		$existing_hosts = [];
		if (!empty($batch_names)) {
			$existing = API::Host()->get([
				'output' => ['hostid', 'host', 'name'],
				'filter' => ['host' => array_unique($batch_names)]
			]);
			foreach ($existing as $eh) {
				$existing_hosts[$eh['host']] = $eh;
			}
		}

		// Also check templates with the same names (host name must be unique across hosts and templates).
		$existing_templates = [];
		if (!empty($batch_names)) {
			$existing_tpl = API::Template()->get([
				'output' => ['templateid', 'host'],
				'filter' => ['host' => array_unique($batch_names)]
			]);
			foreach ($existing_tpl as $et) {
				$existing_templates[$et['host']] = $et;
			}
		}

		// Build conflict info for each host entry.
		$conflicts = [];
		foreach ($host_data as $idx => $item) {
			$host_name = trim($item['host'] ?? '');
			$ip = trim($item['ip'] ?? '');
			$issues = [];

			if ($host_name === '') {
				$issues[] = LangHelper::t('err.host_required');
			}
			if ($ip === '') {
				$issues[] = LangHelper::t('err.ip_required');
			}
			if (in_array($host_name, $duplicate_names)) {
				$issues[] = LangHelper::t('err.batch_duplicate');
			}
			if (isset($existing_hosts[$host_name])) {
				$issues[] = LangHelper::t('err.host_exists_in_zabbix');
			}
			if (isset($existing_templates[$host_name])) {
				$issues[] = LangHelper::t('err.template_exists');
			}

			$conflicts[$idx] = $issues;
		}

		// Prepare host groups and templates lookup maps for name resolution.
		$all_groups = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'preservekeys' => false
		]);
		$group_map = [];
		foreach ($all_groups as $g) {
			$group_map[$g['name']] = $g['groupid'];
		}

		$all_templates = API::Template()->get([
			'output' => ['templateid', 'host', 'name'],
			'preservekeys' => false
		]);
		$template_map = [];
		foreach ($all_templates as $t) {
			$template_map[$t['host']] = $t['templateid'];
			$template_map[$t['name']] = $t['templateid'];
		}

		// Build field status for each row: detect which groups/templates exist vs need creation.
		$field_status = [];
		foreach ($host_data as $idx => $item) {
			$fstatus = [
				'groups' => [],
				'templates' => [],
				'groups_inherited' => true,
				'templates_inherited' => true,
				'tags_inherited' => true,
				'macros_inherited' => true
			];

			// Groups
			$groups_input = trim($item['groups'] ?? '');
			if ($groups_input !== '') {
				$fstatus['groups_inherited'] = false;
				$group_names = array_filter(array_map('trim', explode(';', $groups_input)));
				foreach ($group_names as $gn) {
					$fstatus['groups'][] = [
						'name' => $gn,
						'status' => isset($group_map[$gn]) ? 'exists' : 'new'
					];
				}
			}

			// Templates
			$templates_input = trim($item['templates'] ?? '');
			if ($templates_input !== '') {
				$fstatus['templates_inherited'] = false;
				$template_names = array_filter(array_map('trim', explode(';', $templates_input)));
				foreach ($template_names as $tn) {
					// Try both host and name lookup
					$found = isset($template_map[$tn]);
					$fstatus['templates'][] = [
						'name' => $tn,
						'status' => $found ? 'exists' : 'not_found'
					];
				}
			}

			// Tags
			$fstatus['tags_inherited'] = (trim($item['tags'] ?? '') === '');

			// Macros
			$fstatus['macros_inherited'] = (trim($item['macros'] ?? '') === '');

			$field_status[$idx] = $fstatus;
		}

		$data = [
			'source_host' => $source_host,
			'host_data' => $host_data,
			'conflicts' => $conflicts,
			'duplicate_names' => array_unique($duplicate_names),
			'existing_hosts' => $existing_hosts,
			'existing_templates' => $existing_templates,
			'group_map' => $group_map,
			'template_map' => $template_map,
			'field_status' => $field_status,
			'total_count' => count($host_data),
			'conflict_count' => count(array_filter($conflicts, function ($c) {
				return !empty($c);
			}))
		];

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
