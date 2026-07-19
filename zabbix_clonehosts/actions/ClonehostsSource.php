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
 * AJAX endpoint to load source host configuration.
 *
 * Returns the full configuration of the selected source host
 * (interfaces, groups, templates, tags, macros, etc.) as JSON,
 * so the main page can display what will be inherited.
 */
class ClonehostsSource extends CController {

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
			'source_hostid' => 'required|string'
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

		// Use CompatHelper for version-correct host group select parameter.
		$host_get_params = [
			'output' => ['hostid', 'host', 'name', 'status', 'description',
				'inventory_mode', 'tls_connect', 'tls_accept'
			],
			'hostids' => [$source_hostid],
			'selectInterfaces' => ['type', 'main', 'useip', 'ip', 'dns', 'port'],
			'selectParentTemplates' => ['templateid', 'host', 'name'],
			'selectTags' => ['tag', 'value'],
			'selectMacros' => ['macro', 'value', 'description', 'type']
		];
		$host_get_params = CompatHelper::buildHostGetParams($host_get_params);

		$source_host = API::Host()->get($host_get_params);

		$source_host = $source_host ? CompatHelper::normalizeHost($source_host[0]) : null;

		$data = [
			'success' => !empty($source_host),
			'source_host' => $source_host,
			'error' => empty($source_host) ? LangHelper::t('err.source_not_found') : ''
		];

		$response = new CControllerResponseData($data);
		$this->setResponse($response);
	}
}
