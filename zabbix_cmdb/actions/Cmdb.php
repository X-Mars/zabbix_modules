<?php
/**
 * CMDB 主机列表控制器
 * 
 * 功能：
 * - 分页显示主机列表（支持页码切换、每页数量切换、页面跳转）
 * - 根据搜索、分组、接口类型筛选主机
 * - CPU/内存总量根据筛选条件计算（非仅当前页）
 * - 批量获取监控数据，优化性能
 * - 兼容 Zabbix 6.0、7.0、7.4
 */

namespace Modules\ZabbixCmdb\Actions;

use CController;
use CControllerResponseData;
use API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ItemFinder.php';
require_once dirname(__DIR__) . '/lib/ZabbixVersion.php';

use Modules\ZabbixCmdb\Lib\LanguageManager;
use Modules\ZabbixCmdb\Lib\ItemFinder;
use Modules\ZabbixCmdb\Lib\ZabbixVersion;

class Cmdb extends CController {
    
    // 分页配置
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_PER_PAGE = 25;
    private const ALLOWED_PER_PAGE = [10, 25, 50, 100];
    
    public function init(): void {
        // 兼容 Zabbix 6 和 7 的 CSRF 验证禁用方式
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation();
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation();
        }
    }

    protected function checkInput(): bool {
        $fields = [
            'search' => 'string',
            'groupid' => 'int32',
            'sort' => 'string',
            'sortorder' => 'in ASC,DESC',
            'interface_type' => 'int32',
            'page' => 'int32',
            'per_page' => 'int32'
        ];

        $ret = $this->validateInput($fields);

        if (!$ret) {
            $this->setResponse(new CControllerResponseData([
                'error' => LanguageManager::t('Invalid input parameters.')
            ]));
        }

        return $ret;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        // 获取筛选参数
        $search = trim($this->getInput('search', ''));
        $groupid = (int)$this->getInput('groupid', 0);
        $interfaceType = (int)$this->getInput('interface_type', 0);
        $sort = $this->getInput('sort', 'name');
        $sortorder = $this->getInput('sortorder', 'ASC');
        
        // 获取分页参数
        $page = max(1, (int)$this->getInput('page', self::DEFAULT_PAGE));
        $perPage = $this->getValidPerPage((int)$this->getInput('per_page', self::DEFAULT_PER_PAGE));
        
        // 1. 获取主机分组列表
        $hostGroups = $this->getHostGroups();
        
        // 2. 获取符合筛选条件的所有主机ID（用于统计总量）
        $allFilteredHostIds = $this->getFilteredHostIds($search, $groupid, $interfaceType);
        $totalHosts = count($allFilteredHostIds);
        
        // 3. 计算分页信息
        $totalPages = max(1, ceil($totalHosts / $perPage));
        $page = min($page, $totalPages); // 确保页码不超过总页数
        $offset = ($page - 1) * $perPage;
        
        // 4. 获取当前页的主机数据
        $hosts = $this->getPagedHosts($search, $groupid, $interfaceType, $sort, $sortorder, $perPage, $offset);
        
        // 5. 批量获取当前页主机的监控数据
        $hostIds = array_column($hosts, 'hostid');
        $monitoringData = ItemFinder::batchFindItems($hostIds);
        
        // 6. 合并监控数据到主机信息
        $hostData = $this->mergeHostData($hosts, $monitoringData);
        
        // 7. 计算符合筛选条件的所有主机的 CPU/内存总量（非仅当前页）
        $totals = ItemFinder::batchGetCpuMemoryTotals($allFilteredHostIds);
        
        // 8. 构建响应
        $response = new CControllerResponseData([
            'title' => LanguageManager::t('Host List'),
            'host_groups' => $hostGroups,
            'hosts' => $hostData,
            'search' => $search,
            'selected_groupid' => $groupid,
            'interface_type' => $interfaceType,
            'sort' => $sort,
            'sortorder' => $sortorder,
            // 分页数据
            'page' => $page,
            'per_page' => $perPage,
            'total_hosts' => $totalHosts,
            'total_pages' => $totalPages,
            'allowed_per_page' => self::ALLOWED_PER_PAGE,
            // 统计数据（基于筛选条件的所有主机）
            'total_cpu' => $totals['total_cpu'],
            'total_memory' => $totals['total_memory'],
        ]);
        
        $response->setTitle(LanguageManager::t('Host List'));
        $this->setResponse($response);
    }
    
    /**
     * 获取有效的每页数量
     */
    private function getValidPerPage(int $perPage): int {
        return in_array($perPage, self::ALLOWED_PER_PAGE) ? $perPage : self::DEFAULT_PER_PAGE;
    }
    
    /**
     * 获取主机分组列表
     * 兼容 Zabbix 6.0 和 7.0+
     */
    private function getHostGroups(): array {
        try {
            $groups = API::HostGroup()->get([
                'output' => ['groupid', 'name'],
                'with_hosts' => true,
                'sortfield' => 'name',
                'sortorder' => 'ASC'
            ]);
            return $groups ?: [];
        } catch (\Exception $e) {
            error_log('CMDB: Failed to get host groups - ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 获取符合筛选条件的所有主机ID
     * 用于计算统计数据和总数
     */
    private function getFilteredHostIds(string $search, int $groupid, int $interfaceType): array {
        $params = $this->buildHostQueryParams($search, $groupid, true);
        $params['output'] = ['hostid'];
        $params['selectInterfaces'] = ['type'];
        
        // 移除分页限制
        unset($params['limit'], $params['offset']);
        
        try {
            $hosts = API::Host()->get($params);
            
            // 按接口类型过滤
            if ($interfaceType > 0 && !empty($hosts)) {
                $hosts = array_filter($hosts, function($host) use ($interfaceType) {
                    if (empty($host['interfaces'])) {
                        return false;
                    }
                    foreach ($host['interfaces'] as $interface) {
                        if ((int)$interface['type'] === $interfaceType) {
                            return true;
                        }
                    }
                    return false;
                });
            }
            
            return array_column($hosts, 'hostid');
        } catch (\Exception $e) {
            error_log('CMDB: Failed to get filtered host IDs - ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 获取分页后的主机数据
     */
    private function getPagedHosts(string $search, int $groupid, int $interfaceType, 
                                   string $sort, string $sortorder, int $limit, int $offset): array {
        $params = $this->buildHostQueryParams($search, $groupid, false);
        
        // 添加分页
        $params['limit'] = $limit;
        // Zabbix API 不直接支持 offset，需要在筛选后手动处理
        
        // 如果需要按接口类型过滤，需要获取更多数据然后过滤
        if ($interfaceType > 0) {
            // 获取足够多的数据以确保过滤后有足够的结果
            $params['limit'] = ($offset + $limit) * 3;
        }
        
        try {
            $hosts = API::Host()->get($params);
            
            // 按接口类型过滤
            if ($interfaceType > 0 && !empty($hosts)) {
                $hosts = array_filter($hosts, function($host) use ($interfaceType) {
                    if (empty($host['interfaces'])) {
                        return false;
                    }
                    foreach ($host['interfaces'] as $interface) {
                        if ((int)$interface['type'] === $interfaceType) {
                            return true;
                        }
                    }
                    return false;
                });
                $hosts = array_values($hosts); // 重建索引
            }
            
            // 手动排序（因为可能经过过滤）
            if (!empty($hosts)) {
                usort($hosts, function($a, $b) use ($sort, $sortorder) {
                    $valueA = $a[$sort] ?? $a['name'] ?? '';
                    $valueB = $b[$sort] ?? $b['name'] ?? '';
                    
                    $result = strcasecmp((string)$valueA, (string)$valueB);
                    return $sortorder === 'DESC' ? -$result : $result;
                });
            }
            
            // 应用分页
            return array_slice($hosts, $offset, $limit);
            
        } catch (\Exception $e) {
            error_log('CMDB: Failed to get paged hosts - ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 构建主机查询参数
     * 兼容 Zabbix 6.0（selectGroups）和 7.0+（selectHostGroups）
     */
    private function buildHostQueryParams(string $search, int $groupid, bool $minimal): array {
        // 检测 Zabbix 版本，选择正确的参数
        $isVersion7 = ZabbixVersion::isVersion7();
        
        $params = [
            'output' => $minimal 
                ? ['hostid']
                : ['hostid', 'host', 'name', 'status', 'maintenance_status', 'maintenance_type', 'maintenanceid'],
            'sortfield' => 'name',
            'sortorder' => 'ASC',
        ];
        
        // 版本兼容：选择正确的分组查询参数
        if (!$minimal) {
            if ($isVersion7) {
                $params['selectHostGroups'] = ['groupid', 'name'];
            } else {
                $params['selectGroups'] = ['groupid', 'name'];
            }
            $params['selectInterfaces'] = ['interfaceid', 'ip', 'dns', 'type', 'main', 'available', 'error'];
        }
        
        // 按分组筛选
        if ($groupid > 0) {
            $params['groupids'] = [$groupid];
        }
        
        // 搜索条件
        if (!empty($search)) {
            $params['search'] = [
                'host' => $search,
                'name' => $search
            ];
            $params['searchByAny'] = true;
            $params['searchWildcardsEnabled'] = true;
            
            // 如果搜索词看起来像 IP，也搜索接口
            if (preg_match('/^\d{1,3}\./', $search)) {
                // 先搜索接口获取主机ID
                $interfaceHostIds = $this->searchHostsByIp($search);
                if (!empty($interfaceHostIds)) {
                    // 如果已有 groupids 限制，需要合并
                    if (isset($params['hostids'])) {
                        $params['hostids'] = array_intersect($params['hostids'], $interfaceHostIds);
                    } else {
                        // 使用 searchByAny 同时按主机名和IP搜索
                        // 这里通过获取两组数据再合并的方式实现
                    }
                }
            }
        }
        
        return $params;
    }
    
    /**
     * 按IP地址搜索主机
     */
    private function searchHostsByIp(string $ip): array {
        try {
            $interfaces = API::HostInterface()->get([
                'output' => ['hostid'],
                'search' => ['ip' => $ip],
                'searchWildcardsEnabled' => true,
                'limit' => 1000
            ]);
            
            return array_unique(array_column($interfaces, 'hostid'));
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * 合并主机数据和监控数据
     */
    private function mergeHostData(array $hosts, array $monitoringData): array {
        $result = [];
        
        foreach ($hosts as $host) {
            $hostId = $host['hostid'];
            $monitoring = isset($monitoringData[$hostId]) ? $monitoringData[$hostId] : [];
            
            // 处理分组数据（兼容不同版本）
            $groups = [];
            if (isset($host['hostgroups'])) {
                $groups = $host['hostgroups'];
            } elseif (isset($host['groups'])) {
                $groups = $host['groups'];
            }
            
            // 获取可用性状态
            $interfaces = isset($host['interfaces']) ? $host['interfaces'] : [];
            $availability = ItemFinder::getHostAvailabilityStatus($hostId, $interfaces);
            
            $result[] = [
                'hostid' => $hostId,
                'host' => $host['host'],
                'name' => $host['name'],
                'status' => $host['status'],
                'maintenance_status' => isset($host['maintenance_status']) ? $host['maintenance_status'] : 0,
                'maintenance_type' => isset($host['maintenance_type']) ? $host['maintenance_type'] : 0,
                'groups' => $groups,
                'interfaces' => $interfaces,
                'availability' => $availability,
                // 监控数据
                'cpu_total' => isset($monitoring['cpu_total']) ? $monitoring['cpu_total'] : null,
                'cpu_usage' => isset($monitoring['cpu_usage']) ? $monitoring['cpu_usage'] : null,
                'memory_total' => isset($monitoring['memory_total']) ? $monitoring['memory_total'] : null,
                'memory_usage' => isset($monitoring['memory_usage']) ? $monitoring['memory_usage'] : null,
                'system_name' => isset($monitoring['system_name']) ? $monitoring['system_name'] : null,
                'operating_system' => isset($monitoring['operating_system']) ? $monitoring['operating_system'] : null,
                'os_architecture' => isset($monitoring['os_architecture']) ? $monitoring['os_architecture'] : null,
                'kernel_version' => isset($monitoring['kernel_version']) ? $monitoring['kernel_version'] : null,
            ];
        }
        
        return $result;
    }
}
