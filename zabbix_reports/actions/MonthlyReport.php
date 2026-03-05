<?php

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

class MonthlyReport extends CController {

    public function init(): void {
        // 兼容Zabbix 6和7
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation(); // Zabbix 7
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation(); // Zabbix 6
        }
    }

    protected function checkInput(): bool {
        return true;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $monthStart = mktime(0, 0, 0, date('m'), 1, date('Y'));
        $monthEnd = mktime(23, 59, 59, date('m') + 1, 0, date('Y'));
        $from = $monthStart;
        $till = $monthEnd;

        // 使用 ProblemFinder 获取与报表周期有交集的所有告警
        $problemResult = ProblemFinder::getProblemsInPeriod($from, $till);
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
        $topHosts = $alertResult['topHosts'];

        // 获取所有主机
        $hosts = API::Host()->get([
            'output' => ['hostid', 'name'],
            'filter' => ['status' => HOST_STATUS_MONITORED]
        ]);

        // 获取主机组映射
        $hostGroups = [];
        if (!empty($hosts)) {
            $hostids = array_column($hosts, 'hostid');
            $hostGroupMap = API::HostGroup()->get([
                'output' => ['groupid', 'name'],
                'hostids' => $hostids
            ]);
            
            // 建立主机到组的映射
            foreach ($hostGroupMap as $group) {
                $groupHosts = API::Host()->get([
                    'output' => ['hostid'],
                    'groupids' => $group['groupid'],
                    'filter' => ['status' => HOST_STATUS_MONITORED]
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
            $cpuUtilResult = ItemFinder::findCpuUtilization($host['hostid'], $from, $till);
            if ($cpuUtilResult && $cpuUtilResult['value'] !== null) {
                $cpuUsage[$host['name']] = $cpuUtilResult['value'];
            }

            // 使用ItemFinder获取内存使用率
            $memUtilResult = ItemFinder::findMemoryUtilization($host['hostid'], $from, $till);
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
            $groupName = !empty($groups) ? $groups[0]['name'] : 'No Group';
            if (!isset($hostsByGroup[$groupName])) {
                $hostsByGroup[$groupName] = [];
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
            $cpuCountResult = ItemFinder::findCpuCount($host['hostid'], $from, $till);
            if ($cpuCountResult && $cpuCountResult['value'] !== null) {
                $cpuTotal[$host['name']] = $cpuCountResult['value'];
                $hostInfo['cpu_total'] = $cpuCountResult['value'];
            }

            // 使用ItemFinder获取内存总量
            $memSizeResult = ItemFinder::findMemorySize($host['hostid'], $from, $till);
            if ($memSizeResult && $memSizeResult['value'] !== null) {
                $memTotal[$host['name']] = $memSizeResult['value'];
                $hostInfo['mem_total'] = number_format($memSizeResult['value'] / (1024*1024*1024), 2);
            }
            
            $hostsByGroup[$groupName][] = $hostInfo;
        }

        $response = new CControllerResponseData([
            'title' => LanguageManager::t('Monthly Report'),
            'report_period' => LanguageManager::formatPeriod('monthly', $monthStart),
            'report_period_raw' => date('Y-m', $monthStart),
            'problem_count' => $problemCount,
            'resolved_count' => $resolvedCount,
            'alert_info' => $alertInfo,
            'hosts_by_group' => $hostsByGroup,
            'top_problem_hosts' => $topHosts,
            'top_cpu_hosts' => $topCpuHosts,
            'top_mem_hosts' => $topMemHosts,
            'cpu_total' => $cpuTotal,
            'mem_total' => $memTotal,
            'language' => LanguageManager::getCurrentLanguage(),
            'is_chinese' => LanguageManager::isChinese()
        ]);
        
        // 显式设置响应标题（Zabbix 6.0 需要）
        $response->setTitle(LanguageManager::t('Monthly Report'));

        $this->setResponse($response);
    }
}
