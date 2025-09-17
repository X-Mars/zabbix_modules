<?php

namespace Modules\ZabbixReports\Actions;

use CController,
    CControllerResponseData,
    CControllerResponseFatal,
    API;

require_once dirname(__DIR__) . '/lib/PdfGenerator.php';
require_once dirname(__DIR__) . '/lib/ItemFinder.php';
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
use Modules\ZabbixReports\Lib\PdfGenerator;
use Modules\ZabbixReports\Lib\ItemFinder;
use Modules\ZabbixReports\Lib\LanguageManager;

class WeeklyReportExport extends CController {

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

        // 初始化CPU和内存使用率数组
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
            
            // 使用ItemFinder获取CPU使用率
            $cpuUtilResult = ItemFinder::findCpuUtilization($host['hostid'], $from, $till);
            if ($cpuUtilResult && $cpuUtilResult['value'] !== null) {
                $hostInfo['cpu_usage'] = number_format($cpuUtilResult['value'], 2) . '%';
            }
            
            // 使用ItemFinder获取CPU数量
            $cpuCountResult = ItemFinder::findCpuCount($host['hostid'], $from, $till);
            if ($cpuCountResult && $cpuCountResult['value'] !== null) {
                $cpuTotal[$host['name']] = $cpuCountResult['value'];
                $hostInfo['cpu_total'] = $cpuCountResult['value'];
            }
            
            // 使用ItemFinder获取内存使用率
            $memUtilResult = ItemFinder::findMemoryUtilization($host['hostid'], $from, $till);
            if ($memUtilResult && $memUtilResult['value'] !== null) {
                $hostInfo['mem_usage'] = number_format($memUtilResult['value'], 2) . '%';
            }
            
            // 使用ItemFinder获取内存总量
            $memTotalResult = ItemFinder::findMemorySize($host['hostid'], $from, $till);
            if ($memTotalResult && $memTotalResult['value'] !== null) {
                $memTotal[$host['name']] = $memTotalResult['value'];
                $hostInfo['mem_total'] = number_format($memTotalResult['value'] / (1024*1024*1024), 2);
            }
            
            // 添加主机到对应群组
            $hostsByGroup[$groupName][] = $hostInfo;
        }

        // 准备PDF数据（新格式）
        $reportData = [
            'date' => date('Y-m-d', $weekStart) . ' to ' . date('Y-m-d', $weekEnd),
            'report_date' => date('Y-m-d', $weekStart) . ' to ' . date('Y-m-d', $weekEnd),
            'problemCount' => $problemCount,
            'resolvedCount' => $resolvedCount,
            'alertInfo' => $alertInfo,
            'hostsByGroup' => $hostsByGroup,
            // 保留兼容性
            'topHosts' => $topHosts,
            'topCpuHosts' => $topCpuHosts,
            'topMemHosts' => $topMemHosts,
            'cpuTotal' => $cpuTotal,
            'memTotal' => $memTotal
        ];

        // 生成PDF
        $pdfGenerator = new PdfGenerator(LanguageManager::t('Zabbix Weekly Report') . ' - ' . date('Y-m-d', $weekStart) . ' to ' . date('Y-m-d', $weekEnd));
        $pdfGenerator->setData($reportData);
        $pdfContent = $pdfGenerator->generate();

        // 检查是否是HTML格式（当没有PDF库时的fallback）
        if (strpos($pdfContent, '<!DOCTYPE html>') === 0) {
            // 返回HTML文件，用户可以打印为PDF
            header('Content-Type: text/html; charset=UTF-8');
            header('Content-Disposition: attachment; filename="weekly_report_' . date('Y-m-d', $weekStart) . '.html"');
        } else {
            // 返回实际的PDF文件
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="weekly_report_' . date('Y-m-d', $weekStart) . '.pdf"');
        }
        
        echo $pdfContent;
        exit;
    }
}
