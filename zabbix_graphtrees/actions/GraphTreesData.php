<?php

namespace Modules\ZabbixGraphTrees\Actions;

use CController,
    CControllerResponseData,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
use Modules\ZabbixGraphTrees\Lib\LanguageManager;

class GraphTreesData extends CController {

    public function init(): void {
        // 兼容Zabbix 6和7
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation(); // Zabbix 7
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation(); // Zabbix 6
        }
    }

    protected function checkInput(): bool {
        $fields = [
            'itemids' => 'string',
            'time_from' => 'int32',
            'time_to' => 'int32'
        ];

        $ret = $this->validateInput($fields);

        return $ret;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $itemidsJson = $this->getInput('itemids', '[]');
        $itemids = json_decode($itemidsJson, true);
        if (!is_array($itemids)) {
            $itemids = [];
        }
        
        $timeFrom = $this->getInput('time_from', time() - 3600);
        $timeTo = $this->getInput('time_to', time());

        $graphData = [];

        if (!empty($itemids)) {
            try {
                // 获取监控项信息
                $items = API::Item()->get([
                    'output' => ['itemid', 'name', 'key_', 'value_type', 'units'],
                    'itemids' => $itemids
                ]);

                foreach ($items as $item) {
                    // 获取历史数据 - 需要指定history类型
                    $historyType = (int)$item['value_type'];
                    
                    $history = API::History()->get([
                        'output' => 'extend',
                        'itemids' => [$item['itemid']],
                        'history' => $historyType,
                        'time_from' => $timeFrom,
                        'time_till' => $timeTo,
                        'sortfield' => 'clock',
                        'sortorder' => 'ASC',
                        'limit' => 1000
                    ]);

                    $dataPoints = [];
                    foreach ($history as $point) {
                        $dataPoints[] = [
                            'clock' => $point['clock'],
                            'value' => $point['value']
                        ];
                    }

                    $graphData[] = [
                        'itemid' => $item['itemid'],
                        'name' => $item['name'],
                        'units' => $item['units'],
                        'data' => $dataPoints
                    ];
                }
            } catch (\Exception $e) {
                error_log("GraphTreesData: Failed to get history data - " . $e->getMessage());
            }
        }

        // 输出JSON数据
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $graphData
        ]);
        exit;
    }
}
