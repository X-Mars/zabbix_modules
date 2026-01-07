<?php
/**
 * 获取可分配主机列表控制器
 */

namespace Modules\ZabbixRock\Actions;

use CController;
use CControllerResponseData;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/HostRackManager.php';

use Modules\ZabbixRock\Lib\LanguageManager;
use Modules\ZabbixRock\Lib\HostRackManager;

class HostsGet extends CController {
    
    protected function init(): void {
        // 兼容Zabbix 6和7
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation(); // Zabbix 7
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation(); // Zabbix 6
        }
    }
    
    protected function checkInput(): bool {
        $fields = [
            'groupid' => 'string',
            'search' => 'string'
        ];
        
        $ret = $this->validateInput($fields);
        
        return $ret;
    }
    
    protected function checkPermissions(): bool {
        return $this->checkAccess('ui.monitoring.hosts');
    }
    
    protected function doAction(): void {
        $groupId = $this->getInput('groupid', '');
        $search = $this->getInput('search', '');
        
        $hosts = HostRackManager::getAvailableHosts(
            $groupId ?: null,
            $search ?: null
        );
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'hosts' => $hosts
        ]);
        exit;
    }
}
