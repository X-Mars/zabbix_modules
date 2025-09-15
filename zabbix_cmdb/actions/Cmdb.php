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

        // 获取主机分组列表 - 基于Zabbix 7.0 API文档的最佳实践
        $hostGroups = [];
        
        // 尝试多种策略获取主机分组，确保兼容性
        $strategies = [
            // 策略1：获取包含主机的分组（推荐，性能更好）
            function() {
                return API::HostGroup()->get([
                    'output' => ['groupid', 'name'],
                    'with_hosts' => true,
                    'sortfield' => 'name',
                    'sortorder' => 'ASC'
                ]);
            },
            
            // 策略2：标准获取所有分组
            function() {
                return API::HostGroup()->get([
                    'output' => ['groupid', 'name'],
                    'sortfield' => 'name',
                    'sortorder' => 'ASC'
                ]);
            },
            
            // 策略3：使用extend输出（某些版本可能需要）
            function() {
                $groups = API::HostGroup()->get([
                    'output' => 'extend',
                    'sortfield' => 'name',
                    'sortorder' => 'ASC'
                ]);
                return array_map(function($group) {
                    return [
                        'groupid' => $group['groupid'],
                        'name' => $group['name']
                    ];
                }, $groups);
            },
            
            // 策略4：通过主机反向获取分组（最后的兼容性选项）
            function() {
                $hosts = API::Host()->get([
                    'output' => ['hostid'],
                    'selectGroups' => ['groupid', 'name'],
                    'limit' => 1000
                ]);
                
                $groupsMap = [];
                foreach ($hosts as $host) {
                    if (isset($host['groups'])) {
                        foreach ($host['groups'] as $group) {
                            $groupsMap[$group['groupid']] = [
                                'groupid' => $group['groupid'],
                                'name' => $group['name']
                            ];
                        }
                    }
                }
                
                $groups = array_values($groupsMap);
                usort($groups, function($a, $b) {
                    return strcasecmp($a['name'], $b['name']);
                });
                
                return $groups;
            }
        ];
        
        // 依次尝试每种策略
        foreach ($strategies as $index => $strategy) {
            try {
                $hostGroups = $strategy();
                if (!empty($hostGroups)) {
                    error_log("CMDB: Successfully got host groups using strategy " . ($index + 1) . " (" . count($hostGroups) . " groups)");
                    break;
                }
            } catch (Exception $e) {
                error_log("CMDB: Strategy " . ($index + 1) . " failed: " . $e->getMessage());
                continue;
            }
        }
        
        // 如果所有策略都失败了，记录错误但不中断执行
        if (empty($hostGroups)) {
            error_log("CMDB: All host group retrieval strategies failed");
        }

        // 获取主机列表 - 根据Zabbix 7.0 API文档优化
        if (!empty($search)) {
            // 搜索策略：支持主机名、显示名称和IP地址的模糊搜索
            $allFoundHosts = [];
            
            // 1. 搜索主机名和显示名称
            try {
                $nameSearchParams = [
                    'output' => ['hostid', 'host', 'name', 'status'],
                    'selectGroups' => ['groupid', 'name'],  // 使用selectGroups而不是selectHostGroups
                    'selectInterfaces' => ['interfaceid', 'ip', 'dns', 'type', 'main'],
                    'search' => [
                        'host' => $search,
                        'name' => $search
                    ],
                    'searchWildcardsEnabled' => true,
                    'searchByAny' => true,
                    'sortfield' => 'host',
                    'sortorder' => 'ASC',
                    'limit' => 100
                ];
                
                if ($groupid > 0) {
                    $nameSearchParams['groupids'] = [$groupid];
                }
                
                $nameHosts = API::Host()->get($nameSearchParams);
                
                foreach ($nameHosts as $host) {
                    $allFoundHosts[$host['hostid']] = $host;
                }
            } catch (Exception $e) {
                error_log("Name search failed: " . $e->getMessage());
            }
            
            // 2. 如果搜索词包含数字，也尝试IP搜索
            if (preg_match('/\d/', $search)) {
                try {
                    // 先搜索接口
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
                        
                        $ipSearchParams = [
                            'output' => ['hostid', 'host', 'name', 'status'],
                            'selectGroups' => ['groupid', 'name'],
                            'selectInterfaces' => ['interfaceid', 'ip', 'dns', 'type', 'main'],
                            'hostids' => $hostIds,
                            'sortfield' => 'host',
                            'sortorder' => 'ASC'
                        ];
                        
                        if ($groupid > 0) {
                            $ipSearchParams['groupids'] = [$groupid];
                        }
                        
                        $ipHosts = API::Host()->get($ipSearchParams);
                        
                        foreach ($ipHosts as $host) {
                            $allFoundHosts[$host['hostid']] = $host;
                        }
                    }
                } catch (Exception $e) {
                    error_log("IP search failed: " . $e->getMessage());
                }
            }
            
            $hosts = array_values($allFoundHosts);
        } else {
            // 没有搜索条件时，获取所有主机
            $hostParams = [
                'output' => ['hostid', 'host', 'name', 'status'],
                'selectGroups' => ['groupid', 'name'],  // 使用selectGroups
                'selectInterfaces' => ['interfaceid', 'ip', 'dns', 'type', 'main'],
                'sortfield' => 'host',
                'sortorder' => 'ASC',
                'limit' => 100
            ];
            
            if ($groupid > 0) {
                $hostParams['groupids'] = [$groupid];
            }
            
            try {
                $hosts = API::Host()->get($hostParams);
            } catch (Exception $e) {
                error_log("Host fetch failed: " . $e->getMessage());
                $hosts = [];
            }
        }

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
