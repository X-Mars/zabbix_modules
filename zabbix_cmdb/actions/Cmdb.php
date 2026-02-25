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

    /** 分页常量 */
    const PAGE_SIZE_DEFAULT = 50;
    const PAGE_SIZE_MIN     = 10;
    const PAGE_SIZE_MAX     = 100;

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
            'search'         => 'string',
            'groupid'        => 'int32',
            'sort'           => 'string',
            'sortorder'      => 'in ASC,DESC',
            'interface_type' => 'int32',
            'page'           => 'int32',
            'page_size'      => 'int32',
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
        $search         = $this->getInput('search', '');
        $groupid        = $this->getInput('groupid', 0);
        $interface_type = $this->getInput('interface_type', 0);
        $page           = max(1, $this->getInput('page', 1));
        $pageSize       = min(self::PAGE_SIZE_MAX, max(self::PAGE_SIZE_MIN, $this->getInput('page_size', self::PAGE_SIZE_DEFAULT)));
        $sort           = $this->getInput('sort', 'name');
        $sortorder      = $this->getInput('sortorder', 'ASC');

        // ── 1) 获取主机分组列表（下拉菜单） ──
        $hostGroups = $this->getHostGroups();

        // ── 2) 轻量查询：获取所有匹配的主机 ID ──
        //    仅返回 hostid + 必要的接口类型信息，不包含监控项数据
        $allHostIds = $this->getFilteredHostIds($search, $groupid, $interface_type);

        // ── 2.5) 计算全局统计（CPU/内存总量 + 活跃主机数，跨所有过滤后主机） ──
        $globalStats = $this->computeGlobalStats($allHostIds);

        // ── 3) 计算分页参数 ──
        $totalCount = count($allHostIds);
        $totalPages = max(1, (int)ceil($totalCount / $pageSize));
        $page       = min($page, $totalPages);
        $offset     = ($page - 1) * $pageSize;

        // ── 4) 取当前页的主机 ID 切片 ──
        $pageHostIds = array_slice($allHostIds, $offset, $pageSize);

        // ── 5) 获取当前页主机的完整信息（单次 API 调用） ──
        $hosts = [];
        if (!empty($pageHostIds)) {
            $hosts = $this->getHostDetails($pageHostIds);
        }

        // ── 6) 批量获取当前页主机的所有监控项 ──
        //    替代原来的 N×8 次逐主机查询
        $itemsData = [];
        if (!empty($pageHostIds)) {
            $itemsData = ItemFinder::batchGetHostItems($pageHostIds);
        }

        // ── 7) 组装主机数据 ──
        $hostData = [];

        foreach ($hosts as $host) {
            $hostid = $host['hostid'];

            $hostInfo = [
                'hostid'             => $hostid,
                'host'               => $host['host'],
                'name'               => $host['name'],
                'status'             => $host['status'],
                'maintenance_status' => $host['maintenance_status'] ?? 0,
                'maintenance_type'   => $host['maintenance_type'] ?? 0,
                'groups'             => $host['hostgroups'] ?? ($host['groups'] ?? []),
                'interfaces'         => $host['interfaces'] ?? [],
                'cpu_total'          => '-',
                'cpu_usage'          => '-',
                'memory_total'       => '-',
                'memory_usage'       => '-',
                'kernel_version'     => '-',
                'system_name'        => '-',
                'operating_system'   => '-',
                'os_architecture'    => '-',
            ];

            // 可用性状态（基于已获取的接口信息，无需额外 API 调用）
            $hostInfo['availability'] = ItemFinder::getHostAvailabilityStatus(
                $hostid,
                $host['interfaces'] ?? []
            );

            // 从批量数据中提取监控项值
            if (isset($itemsData[$hostid])) {
                $items = $itemsData[$hostid];

                // CPU 数量
                if ($items['cpu_count'] !== null && $items['cpu_count']['value'] !== '') {
                    $hostInfo['cpu_total'] = $items['cpu_count']['value'];
                }

                // CPU 使用率
                if ($items['cpu_usage'] !== null && $items['cpu_usage']['value'] !== '') {
                    $val = floatval($items['cpu_usage']['value']);
                    // 处理 idle 类 key：使用率 = 100 - idle%
                    if ($items['cpu_usage']['key'] === 'system.cpu.util[,idle]') {
                        $val = 100 - $val;
                    }
                    $hostInfo['cpu_usage'] = round($val, 2) . '%';
                }

                // 内存总量
                if ($items['memory_total'] !== null && $items['memory_total']['value'] !== '') {
                    $hostInfo['memory_total'] = ItemFinder::formatMemorySize($items['memory_total']['value']);
                }

                // 内存使用率
                if ($items['memory_usage'] !== null && $items['memory_usage']['value'] !== '') {
                    $val = floatval($items['memory_usage']['value']);
                    // 处理 pavailable key：使用率 = 100 - available%
                    if ($items['memory_usage']['key'] === 'vm.memory.size[pavailable]') {
                        $val = 100 - $val;
                    }
                    $hostInfo['memory_usage'] = round($val, 2) . '%';
                }

                // 内核版本
                if ($items['kernel_version'] !== null && $items['kernel_version']['value'] !== '') {
                    $hostInfo['kernel_version'] = ItemFinder::extractKernelInfo($items['kernel_version']['value']);
                }

                // 系统名称
                if ($items['system_name'] !== null && $items['system_name']['value'] !== '') {
                    $hostInfo['system_name'] = $items['system_name']['value'];
                }

                // 操作系统
                if ($items['operating_system'] !== null && $items['operating_system']['value'] !== '') {
                    $hostInfo['operating_system'] = $items['operating_system']['value'];
                }

                // 架构
                if ($items['os_architecture'] !== null && $items['os_architecture']['value'] !== '') {
                    $hostInfo['os_architecture'] = $items['os_architecture']['value'];
                }
            }

            $hostData[] = $hostInfo;
        }

        // ── 8) 页内排序 ──
        //    主机名排序由 API sortfield 保证全局一致性，
        //    监控项字段仅在当前页内排序（API 不支持按监控项值排序）
        if (!empty($hostData)) {
            $itemSortFields = [
                'cpu_total', 'cpu_usage', 'memory_total', 'memory_usage',
                'kernel_version', 'system_name', 'operating_system',
                'os_architecture', 'ip',
            ];

            // 仅当排序字段为监控项字段时才在 PHP 端排序
            // 主机名等字段已由 API 保证排序
            if (in_array($sort, $itemSortFields)) {
                usort($hostData, function ($a, $b) use ($sort, $sortorder, $itemsData) {
                    $valueA = $a[$sort] ?? '';
                    $valueB = $b[$sort] ?? '';

                    if (in_array($sort, ['cpu_total', 'cpu_usage', 'memory_total', 'memory_usage'])) {
                        if ($sort === 'cpu_usage' || $sort === 'memory_usage') {
                            $valueA = $valueA !== '-' ? floatval(str_replace('%', '', $valueA)) : -1;
                            $valueB = $valueB !== '-' ? floatval(str_replace('%', '', $valueB)) : -1;
                        } elseif ($sort === 'memory_total') {
                            // 使用原始字节值排序，而非格式化后的字符串
                            $valueA = isset($itemsData[$a['hostid']]['memory_total'])
                                ? floatval($itemsData[$a['hostid']]['memory_total']['value'])
                                : -1;
                            $valueB = isset($itemsData[$b['hostid']]['memory_total'])
                                ? floatval($itemsData[$b['hostid']]['memory_total']['value'])
                                : -1;
                        } else {
                            $valueA = is_numeric($valueA) ? floatval($valueA) : -1;
                            $valueB = is_numeric($valueB) ? floatval($valueB) : -1;
                        }
                    } else {
                        $valueA = ($valueA === '-') ? '' : (string)$valueA;
                        $valueB = ($valueB === '-') ? '' : (string)$valueB;
                    }

                    return $sortorder === 'DESC'
                        ? ($valueB <=> $valueA)
                        : ($valueA <=> $valueB);
                });
            }
        }

        // ── 9) 构建响应 ──
        $response = new CControllerResponseData([
            'title'            => LanguageManager::t('Host List'),
            'host_groups'      => $hostGroups,
            'hosts'            => $hostData,
            'search'           => $search,
            'selected_groupid' => $groupid,
            'interface_type'   => $interface_type,
            'sort'             => $sort,
            'sortorder'        => $sortorder,
            'total_cpu'        => $globalStats['total_cpu'],
            'total_memory'     => $globalStats['total_memory'],
            'active_hosts'     => $globalStats['active_hosts'],
            // 分页信息
            'page'             => $page,
            'page_size'        => $pageSize,
            'total_count'      => $totalCount,
            'total_pages'      => $totalPages,
        ]);

        $response->setTitle(LanguageManager::t('Host List'));
        $this->setResponse($response);
    }

    // ─────────────────────────────────────────────
    //  辅助方法
    // ─────────────────────────────────────────────

    /**
     * 获取主机分组列表（用于搜索表单下拉菜单）
     */
    private function getHostGroups(): array {
        $hostGroups = [];

        $strategies = [
            // 策略 1：仅获取包含主机的分组（推荐，性能最优）
            function () {
                return API::HostGroup()->get([
                    'output'     => ['groupid', 'name'],
                    'with_hosts' => true,
                    'sortfield'  => 'name',
                    'sortorder'  => 'ASC',
                ]);
            },
            // 策略 2：获取全部分组
            function () {
                return API::HostGroup()->get([
                    'output'    => ['groupid', 'name'],
                    'sortfield' => 'name',
                    'sortorder' => 'ASC',
                ]);
            },
            // 策略 3：extend 输出（某些版本兼容)
            function () {
                $groups = API::HostGroup()->get([
                    'output'    => 'extend',
                    'sortfield' => 'name',
                    'sortorder' => 'ASC',
                ]);
                return array_map(function ($g) {
                    return ['groupid' => $g['groupid'], 'name' => $g['name']];
                }, $groups);
            },
            // 策略 4：通过主机反向获取分组（最后的兼容性选项）
            function () {
                $hosts = API::Host()->get([
                    'output'           => ['hostid'],
                    'selectHostGroups' => ['groupid', 'name'],
                    'limit'            => 1000,
                ]);
                $map = [];
                foreach ($hosts as $host) {
                    $groups = $host['hostgroups'] ?? ($host['groups'] ?? []);
                    foreach ($groups as $group) {
                        $map[$group['groupid']] = [
                            'groupid' => $group['groupid'],
                            'name'    => $group['name'],
                        ];
                    }
                }
                $groups = array_values($map);
                usort($groups, function ($a, $b) {
                    return strcasecmp($a['name'], $b['name']);
                });
                return $groups;
            },
        ];

        foreach ($strategies as $index => $strategy) {
            try {
                $hostGroups = $strategy();
                if (!empty($hostGroups)) {
                    break;
                }
            } catch (\Exception $e) {
                error_log('CMDB: Host group strategy ' . ($index + 1) . ' failed: ' . $e->getMessage());
                continue;
            }
        }

        if (empty($hostGroups)) {
            error_log('CMDB: All host group retrieval strategies failed');
        }

        return $hostGroups;
    }

    /**
     * 获取经过筛选的主机 ID 列表（轻量查询）
     *
     * 只返回 hostid 数组，按主机名排序。
     * 搜索支持：主机名、显示名称、IP 地址。
     * 支持按分组 / 接口类型过滤。
     */
    private function getFilteredHostIds(string $search, int $groupid, int $interface_type): array {
        $allHostIds = []; // hostid => host_lite_data

        // 基础查询参数：仅返回 hostid，按名称排序
        $baseParams = [
            'output'    => ['hostid'],
            'sortfield' => 'name',
            'sortorder' => 'ASC',
        ];

        if ($groupid > 0) {
            $baseParams['groupids'] = [$groupid];
        }

        // 如果需要按接口类型过滤，附带接口类型字段
        if ($interface_type > 0) {
            $baseParams['selectInterfaces'] = ['type'];
        }

        if (!empty($search)) {
            // ── 搜索主机名 / 显示名称 ──
            try {
                $nameParams = array_merge($baseParams, [
                    'search'                 => [
                        'host' => '*' . $search . '*',
                        'name' => '*' . $search . '*',
                    ],
                    'searchWildcardsEnabled' => true,
                    'searchByAny'            => true,
                ]);
                $nameHosts = API::Host()->get($nameParams);
                foreach ($nameHosts as $host) {
                    $allHostIds[$host['hostid']] = $host;
                }
            } catch (\Exception $e) {
                error_log('CMDB: Name search failed: ' . $e->getMessage());
            }

            // ── 如果搜索词包含数字，同时搜 IP 地址 ──
            if (preg_match('/\d/', $search)) {
                try {
                    $interfaces = API::HostInterface()->get([
                        'output'                 => ['hostid'],
                        'search'                 => [
                            'ip'  => '*' . $search . '*',
                            'dns' => '*' . $search . '*',
                        ],
                        'searchWildcardsEnabled' => true,
                        'searchByAny'            => true,
                    ]);

                    if (!empty($interfaces)) {
                        $ipHostIds = array_unique(array_column($interfaces, 'hostid'));
                        // 排除已通过名称搜索到的主机
                        $ipHostIds = array_diff($ipHostIds, array_keys($allHostIds));

                        if (!empty($ipHostIds)) {
                            $ipParams = array_merge($baseParams, [
                                'hostids' => array_values($ipHostIds),
                            ]);
                            $ipHosts = API::Host()->get($ipParams);
                            foreach ($ipHosts as $host) {
                                $allHostIds[$host['hostid']] = $host;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    error_log('CMDB: IP search failed: ' . $e->getMessage());
                }
            }
        } else {
            // ── 无搜索条件：获取所有主机 ──
            try {
                $hosts = API::Host()->get($baseParams);
                foreach ($hosts as $host) {
                    $allHostIds[$host['hostid']] = $host;
                }
            } catch (\Exception $e) {
                error_log('CMDB: Host fetch failed: ' . $e->getMessage());
            }
        }

        // ── 按接口类型过滤 ──
        if ($interface_type > 0) {
            $filtered = [];
            foreach ($allHostIds as $hostid => $host) {
                if (!empty($host['interfaces'])) {
                    foreach ($host['interfaces'] as $iface) {
                        if ($iface['type'] == $interface_type) {
                            $filtered[$hostid] = $host;
                            break;
                        }
                    }
                }
            }
            $allHostIds = $filtered;
        }

        return array_keys($allHostIds);
    }

    /**
     * 计算全局统计数据（跨所有过滤后主机）
     *
     * 仅增加 2 次 API 调用：
     * - 1 次 item.get 获取所有主机的 CPU 数量 + 内存总量
     * - 1 次 host.get 获取 status/maintenance/interfaces 判断活跃主机
     *
     * @param array $allHostIds 所有过滤后的主机 ID
     * @return array ['total_cpu' => int, 'total_memory' => int, 'active_hosts' => int]
     */
    private function computeGlobalStats(array $allHostIds): array {
        $stats = [
            'total_cpu'    => 0,
            'total_memory' => 0,
            'active_hosts' => 0,
        ];

        if (empty($allHostIds)) {
            return $stats;
        }

        // ── CPU 和内存汇总：单次 item.get ──
        try {
            $items = API::Item()->get([
                'output'  => ['hostid', 'key_', 'lastvalue'],
                'hostids' => $allHostIds,
                'filter'  => [
                    'key_'   => ['system.cpu.num', 'vm.memory.size[total]'],
                    'status' => ITEM_STATUS_ACTIVE,
                ],
            ]);

            // 每台主机只取一个 cpu 和一个 memory 值（去重）
            $cpuByHost = [];
            $memByHost = [];
            foreach ($items as $item) {
                $hid = $item['hostid'];
                $val = $item['lastvalue'] ?? '';
                if ($val === '') {
                    continue;
                }
                if ($item['key_'] === 'system.cpu.num' && !isset($cpuByHost[$hid])) {
                    $cpuByHost[$hid] = intval($val);
                } elseif ($item['key_'] === 'vm.memory.size[total]' && !isset($memByHost[$hid])) {
                    $memByHost[$hid] = intval($val);
                }
            }

            $stats['total_cpu']    = array_sum($cpuByHost);
            $stats['total_memory'] = array_sum($memByHost);
        } catch (\Exception $e) {
            error_log('CMDB: Global CPU/Memory stats failed: ' . $e->getMessage());
        }

        // ── 活跃主机统计：单次 host.get ──
        // 活跃 = 启用（status=0）+ 非维护 + 主接口 available=1
        try {
            $hosts = API::Host()->get([
                'output'           => ['hostid', 'status', 'maintenance_status'],
                'selectInterfaces' => ['type', 'main', 'available'],
                'hostids'          => $allHostIds,
            ]);

            foreach ($hosts as $host) {
                // 排除禁用主机
                if ($host['status'] == 1) {
                    continue;
                }
                // 排除维护中的主机
                if (isset($host['maintenance_status']) && $host['maintenance_status'] == 1) {
                    continue;
                }
                // 检查接口可用性（与 getHostAvailabilityStatus 逻辑一致）
                if (!empty($host['interfaces'])) {
                    // 优先查找主接口
                    $mainIface = null;
                    foreach ($host['interfaces'] as $iface) {
                        if ($iface['main'] == 1) {
                            $mainIface = $iface;
                            break;
                        }
                    }
                    if (!$mainIface) {
                        $mainIface = $host['interfaces'][0];
                    }
                    if (isset($mainIface['available']) && $mainIface['available'] == 1) {
                        $stats['active_hosts']++;
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('CMDB: Global active hosts count failed: ' . $e->getMessage());
        }

        return $stats;
    }

    /**
     * 获取指定主机 ID 的完整详细信息
     *
     * 只为当前分页的主机调用，通常 10~100 台。
     */
    private function getHostDetails(array $hostIds): array {
        if (empty($hostIds)) {
            return [];
        }

        try {
            return API::Host()->get([
                'output'           => [
                    'hostid', 'host', 'name', 'status',
                    'maintenance_status', 'maintenance_type', 'maintenanceid',
                ],
                'selectHostGroups' => ['groupid', 'name'],
                'selectInterfaces' => [
                    'interfaceid', 'ip', 'dns', 'type', 'main', 'available', 'error',
                ],
                'hostids'          => $hostIds,
                'preservekeys'     => false,
            ]);
        } catch (\Exception $e) {
            error_log('CMDB: Host details fetch failed: ' . $e->getMessage());
            return [];
        }
    }
}
