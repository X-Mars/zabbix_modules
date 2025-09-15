<?php

namespace Modules\ZabbixCmdb\Actions;

use CController,
    CControllerResponseData,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
use Modules\ZabbixCmdb\Lib\LanguageManager;

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
        $hostFilter = [];
        if (!empty($search)) {
            $hostFilter['search'] = [
                'host' => $search,
                'name' => $search
            ];
        }

        if ($groupid > 0) {
            $hostFilter['groupids'] = [$groupid];
        }

        // 获取主机列表
        $hosts = API::Host()->get([
            'output' => ['hostid', 'host', 'name', 'status'],
            'selectHostGroups' => ['groupid', 'name'],
            'selectInterfaces' => ['interfaceid', 'ip', 'dns', 'type', 'main'],
            'selectItems' => ['itemid', 'name', 'key_', 'value_type'],
            'filter' => $hostFilter,
            'sortfield' => 'host',
            'sortorder' => 'ASC',
            'limit' => 100
        ]);

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
            $cpuItems = array_filter($host['items'], function($item) {
                return strpos($item['key_'], 'system.cpu.num') !== false ||
                       strpos($item['key_'], 'proc.num') !== false;
            });

            if (!empty($cpuItems)) {
                $cpuItem = reset($cpuItems);
                $cpuHistory = API::History()->get([
                    'output' => ['value'],
                    'itemids' => [$cpuItem['itemid']],
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ]);

                if (!empty($cpuHistory)) {
                    $hostInfo['cpu_total'] = $cpuHistory[0]['value'];
                }
            }

            // 获取内存总量
            $memoryItems = array_filter($host['items'], function($item) {
                return strpos($item['key_'], 'vm.memory.size[total]') !== false ||
                       strpos($item['key_'], 'memory.total') !== false;
            });

            if (!empty($memoryItems)) {
                $memoryItem = reset($memoryItems);
                $memoryHistory = API::History()->get([
                    'output' => ['value'],
                    'itemids' => [$memoryItem['itemid']],
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ]);

                if (!empty($memoryHistory)) {
                    $memoryValue = $memoryHistory[0]['value'];
                    // 转换为合适的单位显示
                    if ($memoryValue > 1024 * 1024 * 1024) {
                        $hostInfo['memory_total'] = round($memoryValue / (1024 * 1024 * 1024), 2) . ' GB';
                    } elseif ($memoryValue > 1024 * 1024) {
                        $hostInfo['memory_total'] = round($memoryValue / (1024 * 1024), 2) . ' MB';
                    } else {
                        $hostInfo['memory_total'] = round($memoryValue / 1024, 2) . ' KB';
                    }
                }
            }

            // 获取内核版本
            $kernelItems = array_filter($host['items'], function($item) {
                return strpos($item['key_'], 'kernel.version') !== false ||
                       strpos($item['key_'], 'system.uname') !== false ||
                       strpos($item['key_'], 'system.sw.os') !== false;
            });

            if (!empty($kernelItems)) {
                $kernelItem = reset($kernelItems);
                $kernelHistory = API::History()->get([
                    'output' => ['value'],
                    'itemids' => [$kernelItem['itemid']],
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1,
                    'history' => 1  // 字符串类型的历史数据
                ]);

                if (!empty($kernelHistory)) {
                    $hostInfo['kernel_version'] = $kernelHistory[0]['value'];
                }
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
