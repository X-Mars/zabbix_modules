<?php

namespace Modules\ZabbixLldp\Actions;

use CController;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/TemplateHostResolver.php';
require_once dirname(__DIR__) . '/lib/LldpTopologyBuilder.php';

use Modules\ZabbixLldp\Lib\LanguageManager;
use Modules\ZabbixLldp\Lib\TemplateHostResolver;
use Modules\ZabbixLldp\Lib\LldpTopologyBuilder;

class LldpData extends CController {

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
        if ($this->getUserType() < USER_TYPE_ZABBIX_USER) {
            $this->respond(false, LanguageManager::t('No permission.'));
            return;
        }

        $groupid = trim((string) $this->getInput('groupid', ''));
        $hostid  = trim((string) $this->getInput('hostid', ''));

        if (TemplateHostResolver::getTemplateId() === null) {
            $this->respond(false, LanguageManager::t('Template not found'));
            return;
        }

        $hostMap = TemplateHostResolver::getHostMap($groupid, $hostid);
        if (empty($hostMap)) {
            $this->respond(false, LanguageManager::t('No hosts with LLDP template'));
            return;
        }

        $topology = LldpTopologyBuilder::build($hostMap);
        if (empty($topology['edges'])) {
            $this->respond(false, LanguageManager::t('No LLDP data found'), $topology);
            return;
        }

        $this->respond(true, '', $topology);
    }

    private function respond(bool $ok, string $message, array $data = []): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'      => $ok,
            'message' => $message,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
