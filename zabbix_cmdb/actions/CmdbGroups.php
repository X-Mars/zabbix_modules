<?php

namespace Modules\ZabbixCmdb\Actions;

use CController,
    CControllerResponseData,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ItemFinder.php';
use Modules\ZabbixCmdb\Lib\LanguageManager;
use Modules\ZabbixCmdb\Lib\ItemFinder;

class CmdbGroups extends CController {

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
            'sort' => 'string',
            'sortorder' => 'in ASC,DESC',
            'search' => 'string'
        ];

        $ret = $this->validateInput($fields);

        if (!$ret) {
            $this->setResponse(new CControllerResponseData(['error' => LanguageManager::t('Invalid input parameters.')]));
        }

        return $ret;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $sort = $this->getInput('sort', 'host_count');
        $sortorder = $this->getInput('sortorder', 'DESC');
        $search = $this->getInput('search', '');

        // 获取主机分组列表
        $hostGroups = API::HostGroup()->get([
            'output' => ['groupid', 'name'],
            'sortfield' => 'name',
            'sortorder' => 'ASC'
        ]);

        // 如果有搜索条件，筛选分组
        if (!empty($search)) {
            $hostGroups = array_filter($hostGroups, function($group) use ($search) {
                return stripos($group['name'], $search) !== false;
            });
        }

        // 为每个分组获取详细信息
        $groupData = [];
        foreach ($hostGroups as $group) {
            // 获取分组中的主机
            $hosts = API::Host()->get([
                'output' => ['hostid', 'host', 'name', 'status'],
                'groupids' => [$group['groupid']],
                'selectInterfaces' => ['interfaceid', 'ip', 'dns', 'type', 'main', 'available', 'error']
            ]);

            $hostCount = count($hosts);
            $totalCpu = 0;
            $totalMemory = 0;

            // 计算每个主机的CPU和内存总量
            foreach ($hosts as $host) {
                // 获取CPU数量
                $cpuResult = ItemFinder::findCpuCount($host['hostid']);
                if ($cpuResult && $cpuResult['value'] !== null) {
                    $totalCpu += intval($cpuResult['value']);
                }

                // 获取内存总量
                $memoryResult = ItemFinder::findMemoryTotal($host['hostid']);
                if ($memoryResult && $memoryResult['value'] !== null) {
                    $totalMemory += intval($memoryResult['value']);
                }
            }

            $groupData[] = [
                'groupid' => $group['groupid'],
                'name' => $group['name'],
                'host_count' => $hostCount,
                'total_cpu' => $totalCpu,
                'total_memory' => $totalMemory
            ];
        }

        // 根据排序参数对数据进行排序
        if (!empty($groupData)) {
            usort($groupData, function($a, $b) use ($sort, $sortorder) {
                $valueA = $a[$sort] ?? 0;
                $valueB = $b[$sort] ?? 0;

                // 对于数值字段，确保正确比较
                if (in_array($sort, ['host_count', 'total_cpu', 'total_memory'])) {
                    $valueA = (int)$valueA;
                    $valueB = (int)$valueB;
                } else {
                    $valueA = (string)$valueA;
                    $valueB = (string)$valueB;
                }

                if ($sortorder === 'DESC') {
                    return $valueB <=> $valueA;
                } else {
                    return $valueA <=> $valueB;
                }
            });
        }

        $response = new CControllerResponseData([
            'title' => LanguageManager::t('Host Groups'),
            'groups' => $groupData,
            'sort' => $sort,
            'sortorder' => $sortorder,
            'search' => $search
        ]);
        
        // 显式设置响应标题（Zabbix 6.0 需要）
        $response->setTitle(LanguageManager::t('Host Groups'));

        $this->setResponse($response);
    }
}