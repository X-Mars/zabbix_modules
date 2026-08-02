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
	API;
use Modules\ZabbixClonehosts\CompatHelper;
use Modules\ZabbixClonehosts\LangHelper;

/**
 * Main page controller for Host Batch Clone module.
 *
 * Displays the batch clone form with source host selection,
 * CSV upload, and online table entry.
 */
class Clonehosts extends CController {

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
			'source_hostid' => 'string',
			'host_data' => 'string',
			'return_from_preview' => 'in 1'
		]);
		return true;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
	}

	protected function doAction(): void {
		// Get all monitored hosts for the source host dropdown.
		// Include host group membership so the frontend can filter by group.
		$host_get_params = [
			'output' => ['hostid', 'host', 'name'],
			'filter' => ['status' => HOST_STATUS_MONITORED],
			'sortfield' => 'name',
			'preservekeys' => true
		];
		$host_get_params = CompatHelper::buildHostGetParams($host_get_params);

		$hosts = API::Host()->get($host_get_params);

		// Normalize host data (Zabbix 6.0 uses 'groups', 6.4+ uses 'hostgroups').
		// Build a hostid → [groupid, groupid, ...] mapping for frontend filtering.
		$host_group_map = [];
		$group_result_key = CompatHelper::getHostGroupResultKey();
		foreach ($hosts as $hostid => $host) {
			$host = CompatHelper::normalizeHost($host);
			$host_group_map[$hostid] = [];
			if (isset($host['hostgroups'])) {
				foreach ($host['hostgroups'] as $group) {
					$host_group_map[$hostid][] = $group['groupid'];
				}
			}
			$hosts[$hostid] = $host;
		}

		// Get all host groups for reference.
		$host_groups = API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'sortfield' => 'name',
			'preservekeys' => true
		]);

		// Get all templates for reference.
		// Zabbix 6.0: API::Template() exists but may have slightly different behavior.
		$templates = API::Template()->get([
			'output' => ['templateid', 'host', 'name'],
			'sortfield' => 'name',
			'preservekeys' => true
		]);

		$data = [
			'hosts' => $hosts,
			'host_groups' => $host_groups,
			'host_group_map' => $host_group_map,
			'templates' => $templates,
			'lang' => LangHelper::getAllForJs()
		];

		// Handle return-from-preview: carry back source host + table data so the
		// online table can be restored instead of being cleared.
		$return_source_hostid = $this->getInput('source_hostid', '');
		$return_host_data_raw = $this->getInput('host_data', '');
		$return_from_preview = $this->getInput('return_from_preview', '');

		if ($return_from_preview === '1') {
			$return_host_data = json_decode($return_host_data_raw, true);
			if (is_array($return_host_data)) {
				$data['return_source_hostid'] = $return_source_hostid;
				$data['return_host_data'] = $return_host_data;
			}
		}

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
