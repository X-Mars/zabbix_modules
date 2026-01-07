<?php
/**
 * CMDB 主机分组控制器
 * 
 * 功能：
 * - 显示所有主机分组及其统计信息
 * - 分页支持
 * - 搜索功能
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

class CmdbGroups extends CController {
    
    private const DEFAULT_PAGE = 1;
    private const DEFAULT_PER_PAGE = 25;
    private const ALLOWED_PER_PAGE = [10, 25, 50, 100];

    public function init(): void {
        // 兼容Zabbix 6和7
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation();
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation();
        }
    }

    protected function checkInput(): bool {
        $fields = [
            'sort' => 'string',
            'sortorder' => 'in ASC,DESC',
            'search' => 'string',
            'page' => 'int32',
            'per_page' => 'int32'
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
        $search = trim($this->getInput('search', ''));
        
        // 获取分页参数
        $page = max(1, (int)$this->getInput('page', self::DEFAULT_PAGE));
        $perPage = $this->getValidPerPage((int)$this->getInput('per_page', self::DEFAULT_PER_PAGE));

        // 1. 获取主机分组列表
        $hostGroups = $this->getHostGroups($search);
        
        // 2. 批量获取所有分组的主机ID
        $groupHostIds = $this->getGroupHostIds($hostGroups);
        
        // 3. 获取所有主机ID（去重）
        $allHostIds = [];
        foreach ($groupHostIds as $hostIds) {
            $allHostIds = array_merge($allHostIds, $hostIds);
        }
        $allHostIds = array_unique($allHostIds);
        
        // 4. 批量获取所有主机的CPU和内存数据
        $cpuMemoryData = ItemFinder::batchGetCpuMemoryTotals($allHostIds);
        $allItemsData = ItemFinder::batchFindItems($allHostIds);
        
        // 5. 计算每个分组的统计数据
        $groupData = [];
        foreach ($hostGroups as $group) {
            $groupId = $group['groupid'];
            $hostIds = isset($groupHostIds[$groupId]) ? $groupHostIds[$groupId] : [];
            
            $totalCpu = 0;
            $totalMemory = 0;
            
            // 计算该分组的CPU和内存总量
            foreach ($hostIds as $hostId) {
                if (isset($allItemsData[$hostId])) {
                    $data = $allItemsData[$hostId];
                    if ($data['cpu_total'] !== null) {
                        $totalCpu += intval($data['cpu_total']);
                    }
                    if ($data['memory_total'] !== null) {
                        $totalMemory += floatval($data['memory_total']);
                    }
                }
            }
            
            $groupData[] = [
                'groupid' => $groupId,
                'name' => $group['name'],
                'host_count' => count($hostIds),
                'total_cpu' => $totalCpu,
                'total_memory' => $totalMemory
            ];
        }

        // 6. 排序
        if (!empty($groupData)) {
            usort($groupData, function($a, $b) use ($sort, $sortorder) {
                $valueA = isset($a[$sort]) ? $a[$sort] : 0;
                $valueB = isset($b[$sort]) ? $b[$sort] : 0;

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
        
        // 7. 计算分页
        $totalGroups = count($groupData);
        $totalPages = max(1, ceil($totalGroups / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;
        
        // 8. 获取当前页数据
        $pagedData = array_slice($groupData, $offset, $perPage);
        
        // 9. 计算总计（所有分组）
        $grandTotalCpu = array_sum(array_column($groupData, 'total_cpu'));
        $grandTotalMemory = array_sum(array_column($groupData, 'total_memory'));
        $grandTotalHosts = array_sum(array_column($groupData, 'host_count'));

        $response = new CControllerResponseData([
            'title' => LanguageManager::t('Host Groups'),
            'groups' => $pagedData,
            'sort' => $sort,
            'sortorder' => $sortorder,
            'search' => $search,
            // 分页数据
            'page' => $page,
            'per_page' => $perPage,
            'total_groups' => $totalGroups,
            'total_pages' => $totalPages,
            'allowed_per_page' => self::ALLOWED_PER_PAGE,
            // 统计数据
            'grand_total_cpu' => $grandTotalCpu,
            'grand_total_memory' => $grandTotalMemory,
            'grand_total_hosts' => $grandTotalHosts,
        ]);
        
        $response->setTitle(LanguageManager::t('Host Groups'));
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
     */
    private function getHostGroups(string $search): array {
        try {
            $params = [
                'output' => ['groupid', 'name'],
                'sortfield' => 'name',
                'sortorder' => 'ASC'
            ];
            
            // 如果有搜索条件，使用API搜索
            if (!empty($search)) {
                $params['search'] = ['name' => $search];
                $params['searchWildcardsEnabled'] = true;
            }
            
            $hostGroups = API::HostGroup()->get($params);
            return $hostGroups ?: [];
        } catch (\Exception $e) {
            error_log('CmdbGroups: Failed to get host groups - ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 批量获取每个分组的主机ID
     */
    private function getGroupHostIds(array $hostGroups): array {
        if (empty($hostGroups)) {
            return [];
        }
        
        $result = [];
        $groupIds = array_column($hostGroups, 'groupid');
        
        try {
            // 一次性获取所有分组的主机
            $hosts = API::Host()->get([
                'output' => ['hostid'],
                'groupids' => $groupIds,
                // 版本兼容
                ZabbixVersion::isVersion7() ? 'selectHostGroups' : 'selectGroups' => ['groupid'],
            ]);
            
            // 初始化结果
            foreach ($groupIds as $groupId) {
                $result[$groupId] = [];
            }
            
            // 按分组ID分组主机
            foreach ($hosts as $host) {
                // 兼容不同版本的字段名
                $groups = isset($host['hostgroups']) ? $host['hostgroups'] : (isset($host['groups']) ? $host['groups'] : []);
                
                foreach ($groups as $group) {
                    $gid = $group['groupid'];
                    if (isset($result[$gid])) {
                        $result[$gid][] = $host['hostid'];
                    }
                }
            }
            
        } catch (\Exception $e) {
            error_log('CmdbGroups: Failed to get group host IDs - ' . $e->getMessage());
        }
        
        return $result;
    }
}
