<?php

namespace Modules\ZabbixReports\Actions;

use CController,
    CControllerResponseData,
    API;

require_once dirname(__DIR__) . '/lib/ItemFinder.php';
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
use Modules\ZabbixReports\Lib\ItemFinder;
use Modules\ZabbixReports\Lib\LanguageManager;

class WeeklyReport extends CController {

    public function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        return true;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        // 计算本周的开始和结束时间
        $weekStart = strtotime('last monday', strtotime('tomorrow'));
        $weekEnd = strtotime('next sunday', $weekStart);
        $from = mktime(0, 0, 0, date('m', $weekStart), date('d', $weekStart), date('Y', $weekStart));
        $till = mktime(23, 59, 59, date('m', $weekEnd), date('d', $weekEnd), date('Y', $weekEnd));

        $problemCount = API::Event()->get([
            'countOutput' => true,
            'filter' => ['value' => TRIGGER_VALUE_TRUE],
            'time_from' => $from,
            'time_till' => $till
        ]);

        $resolvedCount = API::Event()->get([
            'countOutput' => true,
            'filter' => ['value' => TRIGGER_VALUE_FALSE],
            'time_from' => $from,
            'time_till' => $till
        ]);

        // 获取告警事件信息（包括问题和恢复事件）
        $events = API::Event()->get([
            'output' => ['eventid', 'objectid', 'name', 'clock', 'value', 'r_eventid'],
            'filter' => ['value' => [TRIGGER_VALUE_TRUE, TRIGGER_VALUE_FALSE]],
            'time_from' => $from,
            'time_till' => $till,
            'sortfield' => 'clock',
            'sortorder' => 'DESC',
            'limit' => 200
        ]);

        // 第一部分：告警信息
        $alertInfo = [];
        $hostCounts = [];
        
        if (!empty($events)) {
            // 构建事件映射：eventid -> event
            $eventMap = [];
            foreach ($events as $event) {
                $eventMap[$event['eventid']] = $event;
            }
            
            // 分离问题事件和恢复事件
            $problemEvents = array_filter($events, function($event) {
                return $event['value'] == TRIGGER_VALUE_TRUE;
            });
            
            $triggerIds = array_unique(array_column($problemEvents, 'objectid'));
            
            // 获取触发器信息
            $triggers = API::Trigger()->get([
                'output' => ['triggerid', 'description'],
                'triggerids' => $triggerIds
            ]);
            $triggerMap = [];
            foreach ($triggers as $trigger) {
                $triggerMap[$trigger['triggerid']] = $trigger;
            }
            
            // 获取触发器对应的主机
            $triggerHosts = [];
            foreach ($triggerIds as $triggerId) {
                $hosts = API::Host()->get([
                    'output' => ['hostid', 'name'],
                    'triggerids' => $triggerId,
                    'limit' => 1
                ]);
                if (!empty($hosts)) {
                    $triggerHosts[$triggerId] = $hosts[0];
                }
            }
            
            // 构建告警信息
            foreach ($problemEvents as $event) {
                $trigger = isset($triggerMap[$event['objectid']]) ? $triggerMap[$event['objectid']] : null;
                $host = isset($triggerHosts[$event['objectid']]) ? $triggerHosts[$event['objectid']] : null;
                
                $hostName = $host ? $host['name'] : 'Unknown Host';
                $triggerName = $trigger ? $trigger['description'] : $event['name'];
                $alertTime = date('Y-m-d H:i:s', $event['clock']);
                
                // 查找恢复时间
                $recoveryTime = null;
                if (!empty($event['r_eventid']) && isset($eventMap[$event['r_eventid']])) {
                    $recoveryEvent = $eventMap[$event['r_eventid']];
                    $recoveryTime = date('Y-m-d H:i:s', $recoveryEvent['clock']);
                }
                
                $alertInfo[] = [
                    'host' => $hostName,
                    'alert' => $triggerName,
                    'time' => $alertTime,
                    'recovery_time' => $recoveryTime
                ];
                
                $hostCounts[$hostName] = ($hostCounts[$hostName] ?? 0) + 1;
            }
        }
        arsort($hostCounts);
        $topHosts = array_slice($hostCounts, 0, 10, true);

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
            'report_period' => LanguageManager::formatPeriod('weekly', $weekStart),
            'report_period_raw' => date('Y-W', $weekStart),
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

        $this->setResponse($response);
    }
}
