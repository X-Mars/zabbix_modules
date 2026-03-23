<?php
declare(strict_types = 0);

namespace Modules\ZabbixReports\Actions;

use CController,
    CControllerResponseData,
    API;

require_once dirname(__DIR__) . '/lib/ItemFinder.php';
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ProblemFinder.php';
use Modules\ZabbixReports\Lib\ItemFinder;
use Modules\ZabbixReports\Lib\LanguageManager;
use Modules\ZabbixReports\Lib\ProblemFinder;

class CustomReport extends CController {

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
            'from_date' => 'string',
            'to_date' => 'string',
            'generate' => 'string',
            'groupid' => 'string'
        ];
        
        return $this->validateInput($fields);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        // 设置默认日期：开始日期为7天前，结束日期为今天
        $defaultFromDate = date('Y-m-d', strtotime('-7 days'));
        $defaultToDate = date('Y-m-d');
        
        // 获取用户输入的日期，如果为空则使用默认值
        $fromDate = $this->getInput('from_date', $defaultFromDate);
        $toDate = $this->getInput('to_date', $defaultToDate);
        $generate = !empty($this->getInput('generate', ''));
        $groupId = $this->getInput('groupid', '');

        // 获取所有主机组（用于过滤下拉框）
        $allGroups = API::HostGroup()->get([
            'output' => ['groupid', 'name'],
            'sortfield' => 'name',
            'with_monitored_hosts' => true
        ]);

        $data = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'generate' => $generate,
            'language' => LanguageManager::getCurrentLanguage(),
            'is_chinese' => LanguageManager::isChinese(),
            'all_groups' => $allGroups,
            'filter_groupid' => $groupId,
            'filter_group_name' => $this->getGroupName($groupId, $allGroups)
        ];

        // 如果用户点击了生成报表
        if ($generate && !empty($fromDate) && !empty($toDate)) {
            // 验证日期格式并转换为时间戳
            $fromTimestamp = strtotime($fromDate . ' 00:00:00');
            $toTimestamp = strtotime($toDate . ' 23:59:59');
            
            if ($fromTimestamp === false || $toTimestamp === false) {
                $data['error'] = LanguageManager::t('Invalid date format');
            } elseif ($fromTimestamp > $toTimestamp) {
                $data['error'] = LanguageManager::t('The start date cannot be later than the end date.');
            } elseif (($toTimestamp - $fromTimestamp) > (90 * 24 * 60 * 60)) {
                $data['error'] = LanguageManager::t('The selected date range cannot exceed 90 days.');
            } else {
                // 生成真正的报表数据
                $reportData = $this->generateReportData($fromTimestamp, $toTimestamp, $groupId);
                $data = array_merge($data, $reportData);
                $data['report_generated'] = true;
                $data['period_text'] = $fromDate . ' - ' . $toDate;
            }
        }

        // 添加标题到数据中
        $data['title'] = LanguageManager::t('Custom Report');
        
        $response = new CControllerResponseData($data);
        
        // 显式设置响应标题（Zabbix 6.0 需要）
        $response->setTitle(LanguageManager::t('Custom Report'));
        
        $this->setResponse($response);
    }

    private function generateReportData(int $fromTimestamp, int $toTimestamp, string $groupId = ''): array {
        try {
            // 获取所有主机（带分组过滤）
            $hostParams = [
                'output' => ['hostid', 'name'],
                'filter' => ['status' => HOST_STATUS_MONITORED]
            ];
            if ($groupId !== '') {
                $hostParams['groupids'] = [$groupId];
            }
            $hosts = API::Host()->get($hostParams);

            $filteredHostIds = array_column($hosts, 'hostid');

            // 使用 ProblemFinder 获取与报表周期有交集的所有告警
            $problemResult = ProblemFinder::getProblemsInPeriod($fromTimestamp, $toTimestamp, 500, $filteredHostIds);
            $problemCount = $problemResult['problemCount'];
            $resolvedCount = $problemResult['resolvedCount'];
            $problemEvents = $problemResult['problemEvents'];
            $recoveryMap = $problemResult['recoveryMap'];
            $triggerHostMap = $problemResult['triggerHostMap'];
            $triggerStatusMap = $problemResult['triggerStatusMap'] ?? [];

            // 构建告警信息
            $alertResult = ProblemFinder::buildAlertInfo(
                $problemEvents, $recoveryMap, $triggerHostMap,
                LanguageManager::t('Unknown Host'), $triggerStatusMap
            );
            $alertInfo = $alertResult['alertInfo'];
            $hostCounts = $alertResult['hostCounts'];
            $topProblemHosts = $alertResult['topHosts'];

            // 获取主机组映射（修复selectGroups废弃问题）
            $hostGroups = [];
            if (!empty($filteredHostIds)) {
                $hostGroupMap = API::HostGroup()->get([
                    'output' => ['groupid', 'name'],
                    'hostids' => $filteredHostIds
                ]);
                
                // 建立主机到组的映射
                foreach ($hostGroupMap as $group) {
                    $groupHosts = API::Host()->get([
                        'output' => ['hostid'],
                        'groupids' => $group['groupid'],
                        'hostids' => $filteredHostIds
                    ]);
                    
                    foreach ($groupHosts as $gh) {
                        if (!isset($hostGroups[$gh['hostid']])) {
                            $hostGroups[$gh['hostid']] = [];
                        }
                        $hostGroups[$gh['hostid']][] = $group;
                    }
                }
            }

            $cpuUsage = [];
            $memUsage = [];
            foreach ($hosts as $host) {
                // 使用ItemFinder获取CPU使用率
                $cpuUtilResult = ItemFinder::findCpuUtilization($host['hostid'], $fromTimestamp, $toTimestamp);
                if ($cpuUtilResult && $cpuUtilResult['value'] !== null) {
                    $cpuUsage[$host['name']] = $cpuUtilResult['value'];
                }

                // 使用ItemFinder获取内存使用率
                $memUtilResult = ItemFinder::findMemoryUtilization($host['hostid'], $fromTimestamp, $toTimestamp);
                if ($memUtilResult && $memUtilResult['value'] !== null) {
                    $memUsage[$host['name']] = $memUtilResult['value'];
                }
            }
            arsort($cpuUsage);
            arsort($memUsage);
            $topCpuHosts = array_slice($cpuUsage, 0, 10, true);
            $topMemHosts = array_slice($memUsage, 0, 10, true);

            // 第二部分：按主机群组分组的主机信息
            $hostsByGroup = [];
            $cpuTotal = [];
            $memTotal = [];
            
            // 重新遍历主机，获取完整信息
            foreach ($hosts as $host) {
                // 分组主机
                $groups = isset($hostGroups[$host['hostid']]) ? $hostGroups[$host['hostid']] : [];
                $hostGroupName = !empty($groups) ? $groups[0]['name'] : LanguageManager::t('No Group');
                if (!isset($hostsByGroup[$hostGroupName])) {
                    $hostsByGroup[$hostGroupName] = [];
                }
                
                // 初始化主机信息
                $hostInfo = [
                    'name' => $host['name'],
                    'cpu_usage' => 'N/A',
                    'cpu_total' => 'N/A',
                    'mem_usage' => 'N/A',
                    'mem_total' => 'N/A'
                ];
                
                // 如果已经有CPU使用率数据，使用它
                if (isset($cpuUsage[$host['name']])) {
                    $hostInfo['cpu_usage'] = number_format($cpuUsage[$host['name']], 2) . '%';
                }
                
                // 如果已经有内存使用率数据，使用它
                if (isset($memUsage[$host['name']])) {
                    $hostInfo['mem_usage'] = number_format($memUsage[$host['name']], 2) . '%';
                }

                // 使用ItemFinder获取CPU数量
                $cpuCountResult = ItemFinder::findCpuCount($host['hostid'], $fromTimestamp, $toTimestamp);
                if ($cpuCountResult && $cpuCountResult['value'] !== null) {
                    $cpuTotal[$host['name']] = $cpuCountResult['value'];
                    $hostInfo['cpu_total'] = $cpuCountResult['value'];
                }

                // 使用ItemFinder获取内存总量
                $memSizeResult = ItemFinder::findMemorySize($host['hostid'], $fromTimestamp, $toTimestamp);
                if ($memSizeResult && $memSizeResult['value'] !== null) {
                    $memTotal[$host['name']] = $memSizeResult['value'];
                    $hostInfo['mem_total'] = number_format($memSizeResult['value'] / (1024*1024*1024), 2);
                }
                
                $hostsByGroup[$hostGroupName][] = $hostInfo;
            }

            return [
                'problem_count' => $problemCount,
                'resolved_count' => $resolvedCount,
                'alert_info' => $alertInfo,
                'hosts_by_group' => $hostsByGroup,
                'top_problem_hosts' => $topProblemHosts,
                'top_cpu_hosts' => $topCpuHosts,
                'top_mem_hosts' => $topMemHosts,
                'cpu_total' => $cpuTotal,
                'mem_total' => $memTotal
            ];
            
        } catch (Exception $e) {
            return [
                'error' => 'Error generating report: ' . $e->getMessage(),
                'problem_count' => 0,
                'resolved_count' => 0,
                'alert_info' => [],
                'hosts_by_group' => [],
                'top_problem_hosts' => [],
                'top_cpu_hosts' => [],
                'top_mem_hosts' => [],
                'cpu_total' => [],
                'mem_total' => []
            ];
        }
    }

    private function getGroupName(string $groupId, array $allGroups): string {
        if ($groupId === '') return '';
        foreach ($allGroups as $group) {
            if ((string)$group['groupid'] === (string)$groupId) {
                return $group['name'];
            }
        }
        return '';
    }
}