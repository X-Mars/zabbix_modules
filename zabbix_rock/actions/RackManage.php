<?php
/**
 * 机柜管理控制器
 * 管理机房和机柜的配置
 */

namespace Modules\ZabbixRock\Actions;

use CController;
use CControllerResponseData;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/RackConfig.php';

use Modules\ZabbixRock\Lib\LanguageManager;
use Modules\ZabbixRock\Lib\RackConfig;

class RackManage extends CController {
    
    protected function init(): void {
        // 兼容Zabbix 6和7
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation(); // Zabbix 7
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation(); // Zabbix 6
        }
    }
    
    protected function checkInput(): bool {
        return true;
    }
    
    protected function checkPermissions(): bool {
        return $this->checkAccess('ui.monitoring.hosts');
    }
    
    protected function doAction(): void {
        // 获取所有机房
        $rooms = RackConfig::getRooms();
        
        // 获取所有机柜
        $racks = RackConfig::getRacks();
        
        // 为机柜添加机房名称
        $roomNames = [];
        foreach ($rooms as $room) {
            $roomNames[$room['id']] = $room['name'];
        }
        
        foreach ($racks as &$rack) {
            $rack['room_name'] = $roomNames[$rack['room_id']] ?? '';
        }
        
        $data = [
            'rooms' => $rooms,
            'racks' => $racks,
            'lang' => LanguageManager::class
        ];
        
        $response = new CControllerResponseData($data);
        $response->setTitle(LanguageManager::t('rack_manage'));
        
        $this->setResponse($response);
    }
}
