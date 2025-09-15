<?php declare(strict_types = 0);

namespace Modules\ZabbixCmdb;

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
        $hostParams = [
            'output' => ['hostid', 'host', 'name', 'status'],
            'selectGroups' => ['groupid', 'name'],
            'selectInterfaces' => ['interfaceid', 'ip', 'dns', 'type', 'main'],
            'selectItems' => ['itemid', 'name', 'key_', 'value_type', 'lastvalue', 'units'],
            'sortfield' => 'host',
            'sortorder' => 'ASC',
            'limit' => 100
        ];

        // 搜索条件
        if (!empty($search)) {
            $hostParams['search'] = [
                'host' => $search,
                'name' => $search
            ];
        }

        // 分组筛选
        if ($groupid > 0) {
            $hostParams['groupids'] = [$groupid];
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
                'groups' => $host['groups'],
                'interfaces' => $host['interfaces'],
                'cpu_total' => '-',
                'memory_total' => '-'
            ];

            // 查找CPU相关监控项
            foreach ($host['items'] as $item) {
                // CPU总数
                if (stripos($item['key_'], 'system.cpu.num') !== false) {
                    $hostInfo['cpu_total'] = !empty($item['lastvalue']) ? $item['lastvalue'] : '-';
                    break;
                }
            }

            // 查找内存相关监控项
            foreach ($host['items'] as $item) {
                // 内存总量
                if (stripos($item['key_'], 'vm.memory.size[total]') !== false || 
                    stripos($item['key_'], 'system.memory.total') !== false) {
                    if (!empty($item['lastvalue'])) {
                        $memoryValue = $item['lastvalue'];
                        // 转换为合适的单位显示
                        if ($memoryValue > 1024 * 1024 * 1024) {
                            $hostInfo['memory_total'] = round($memoryValue / (1024 * 1024 * 1024), 2) . ' GB';
                        } elseif ($memoryValue > 1024 * 1024) {
                            $hostInfo['memory_total'] = round($memoryValue / (1024 * 1024), 2) . ' MB';
                        } else {
                            $hostInfo['memory_total'] = round($memoryValue / 1024, 2) . ' KB';
                        }
                    }
                    break;
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
