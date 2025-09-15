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

        // 获取主机分组列表
        $hostGroups = API::HostGroup()->get([
            'output' => ['groupid', 'name'],
            'sortfield' => 'name',
            'sortorder' => 'ASC'
        ]);

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

        // 如果有搜索条件
        if (!empty($search)) {
            $hostParams['search'] = [
                'host' => $search,
                'name' => $search
            ];
            $hostParams['searchWildcardsEnabled'] = true;
            $hostParams['searchByAny'] = true; // 允许在host或name中任一匹配
        }

        // 获取主机列表
        $hosts = API::Host()->get($hostParams);

        // 处理主机数据，获取CPU和内存信息
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
                'memory_total' => '-',
                'kernel_version' => '-'
            ];

            // 获取CPU总量
            $cpuResult = ItemFinder::findCpuCount($host['hostid']);
            if ($cpuResult && $cpuResult['value'] !== null) {
                $hostInfo['cpu_total'] = $cpuResult['value'];
            }

            // 获取内存总量
            $memoryResult = ItemFinder::findMemoryTotal($host['hostid']);
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
