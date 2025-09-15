<?php

namespace Modules\ZabbixCmdb\Actions;

use CController,
    CControllerResponseData,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ItemFinder.php';
use Modules\ZabbixCmdb\Lib\LanguageManager;
use Modules\ZabbixCmdb\Lib\ItemFinder;

class Cmdb extends CController {

    public function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        $fields = [
            'search' => 'string',
            'groupid' => 'int32'
        ];

        $ret = $this->validateInput($fields);

        if (!$ret) {
            $this->setResponse(new CControllerResponseData(['error' => _('Invalid input parameters.')]));
        }

        return $ret;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $search = $this->getInput('search', '');
        $groupid = $this->getInput('groupid', 0);

        // 获取主机分组列表 - 移除已弃用的参数
        $hostGroups = [];
        try {
            $hostGroups = API::HostGroup()->get([
                'output' => ['groupid', 'name'],
                'sortfield' => 'name',
                'sortorder' => 'ASC'
                // 移除 real_hosts 参数，因为在新版本中已被弃用
            ]);
        } catch (Exception $e) {
            // 如果权限不足，尝试获取用户可访问的分组
            try {
                $hostGroups = API::HostGroup()->get([
                    'output' => ['groupid', 'name'],
                    'sortfield' => 'name',
                    'sortorder' => 'ASC'
                ]);
            } catch (Exception $e2) {
                // 如果还是失败，使用空数组
                $hostGroups = [];
            }
        }

        // 构建主机查询条件
        $hostParams = [
            'output' => ['hostid', 'host', 'name', 'status'],
            'selectHostGroups' => ['groupid', 'name'],
            'selectInterfaces' => ['interfaceid', 'ip', 'dns', 'type', 'main'],
            'sortfield' => 'host',
            'sortorder' => 'ASC',
            'limit' => 100
        ];

        // 如果选择了特定的主机分组
        if ($groupid > 0) {
            $hostParams['groupids'] = [$groupid];
        }

        // 改进搜索功能 - 支持主机名、显示名称和IP地址的模糊搜索
        if (!empty($search)) {
            // 如果搜索词看起来像IP地址，也搜索接口
            if (filter_var($search, FILTER_VALIDATE_IP) || preg_match('/^\d+\.\d+\.\d+/', $search)) {
                // IP地址搜索 - 需要先获取匹配的接口，然后获取对应的主机
                $interfaces = API::HostInterface()->get([
                    'output' => ['hostid', 'ip', 'dns'],
                    'search' => [
                        'ip' => $search,
                        'dns' => $search
                    ],
                    'searchWildcardsEnabled' => true,
                    'searchByAny' => true
                ]);
                
                if (!empty($interfaces)) {
                    $hostIds = array_unique(array_column($interfaces, 'hostid'));
                    $hostParams['hostids'] = $hostIds;
                }
            } else {
                // 主机名搜索
                $hostParams['search'] = [
                    'host' => $search,
                    'name' => $search
                ];
                $hostParams['searchWildcardsEnabled'] = true;
                $hostParams['searchByAny'] = true;
            }
        }

        // 获取主机列表
        $hosts = API::Host()->get($hostParams);

        // 处理主机数据，获取CPU、内存信息和使用率
        $hostData = [];
        foreach ($hosts as $host) {
            $hostInfo = [
                'hostid' => $host['hostid'],
                'host' => $host['host'],
                'name' => $host['name'],
                'status' => $host['status'],
                'groups' => $host['hostgroups'],
                'interfaces' => $host['interfaces'],
                'cpu_total' => '-',
                'cpu_usage' => '-',
                'memory_total' => '-',
                'memory_usage' => '-',
                'kernel_version' => '-'
            ];

            // 获取CPU总量
            $cpuResult = ItemFinder::findCpuCount($host['hostid']);
            if ($cpuResult && $cpuResult['value'] !== null) {
                $hostInfo['cpu_total'] = $cpuResult['value'];
            }

            // 获取CPU使用率
            $cpuUsageResult = ItemFinder::findCpuUsage($host['hostid']);
            if ($cpuUsageResult && $cpuUsageResult['value'] !== null) {
                $hostInfo['cpu_usage'] = round(floatval($cpuUsageResult['value']), 2) . '%';
            }

            // 获取内存总量
            $memoryResult = ItemFinder::findMemoryTotal($host['hostid']);
            if ($memoryResult && $memoryResult['value'] !== null) {
                $hostInfo['memory_total'] = ItemFinder::formatMemorySize($memoryResult['value']);
            }

            // 获取内存使用率
            $memoryUsageResult = ItemFinder::findMemoryUsage($host['hostid']);
            if ($memoryUsageResult && $memoryUsageResult['value'] !== null) {
                $hostInfo['memory_usage'] = round(floatval($memoryUsageResult['value']), 2) . '%';
            }
            if ($memoryResult && $memoryResult['value'] !== null) {
                $hostInfo['memory_total'] = ItemFinder::formatMemorySize($memoryResult['value']);
            }

            // 获取内核版本
            $kernelResult = ItemFinder::findKernelVersion($host['hostid']);
            if ($kernelResult && $kernelResult['value'] !== null) {
                $hostInfo['kernel_version'] = ItemFinder::extractKernelInfo($kernelResult['value']);
            }

            $hostData[] = $hostInfo;
        }

        $response = new CControllerResponseData([
            'title' => LanguageManager::t('CMDB'),
            'host_groups' => $hostGroups,
            'hosts' => $hostData,
            'search' => $search,
            'selected_groupid' => $groupid
        ]);

        $this->setResponse($response);
    }
}
