<?php

namespace Modules\ZabbixLldp\Actions;

use CController,
    CControllerResponseData;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/TemplateHostResolver.php';
require_once dirname(__DIR__) . '/lib/ConfigManager.php';

use Modules\ZabbixLldp\Lib\LanguageManager;
use Modules\ZabbixLldp\Lib\TemplateHostResolver;
use Modules\ZabbixLldp\Lib\ConfigManager;

class Lldp extends CController {

    public function init(): void {
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation();
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation();
        }
    }

    protected function checkInput(): bool {
        return $this->validateInput([
            'groupid' => 'string',
            'hostid'  => 'string',
        ]);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $groupid = trim((string) $this->getInput('groupid', ''));
        $hostid  = trim((string) $this->getInput('hostid', ''));

        $templateId = TemplateHostResolver::getTemplateId();
        $hostGroups = TemplateHostResolver::getHostGroups();
        $hosts = TemplateHostResolver::getHosts($groupid !== '' ? $groupid : '0');

        $response = new CControllerResponseData([
            'title'            => LanguageManager::t('LLDP Topology'),
            'host_groups'      => $hostGroups,
            'hosts'            => $hosts,
            'selected_groupid' => $groupid,
            'selected_hostid'  => $hostid,
            'template_found'   => $templateId !== null,
            'template_name'    => ConfigManager::getTemplateName(),
        ]);

        $response->setTitle(LanguageManager::t('LLDP Topology'));
        $this->setResponse($response);
    }
}
