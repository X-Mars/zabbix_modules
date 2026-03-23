<?php

namespace Modules\ZabbixReports\Actions;

use CController,
    CControllerResponseData,
    CControllerResponseFatal,
    API;

require_once dirname(__DIR__) . '/lib/PdfGenerator.php';
require_once dirname(__DIR__) . '/lib/ItemFinder.php';
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ProblemFinder.php';
use Modules\ZabbixReports\Lib\PdfGenerator;
use Modules\ZabbixReports\Lib\ItemFinder;
use Modules\ZabbixReports\Lib\LanguageManager;
use Modules\ZabbixReports\Lib\ProblemFinder;

class DailyReportExport extends CController {

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
            'format' => 'string',
            'groupid' => 'string',
            'year' => 'string',
            'month' => 'string',
            'day' => 'string'
        ];
        return $this->validateInput($fields);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $groupId = $this->getInput('groupid', '');
        $groupName = '';
        if ($groupId !== '') {
            $groups = API::HostGroup()->get([
                'output' => ['name'],
                'groupids' => [$groupId],
                'limit' => 1
            ]);
            if (!empty($groups)) {
                $groupName = $groups[0]['name'];
            }
        }

        $currentYear = (int)date('Y');
        $year = (int)$this->getInput('year', $currentYear);
        $month = (int)$this->getInput('month', date('m'));
        $day = (int)$this->getInput('day', date('d') - 1);
        if ($year < $currentYear - 1 || $year > $currentYear) {
            $year = $currentYear;
        }
        if ($month < 1 || $month > 12) {
            $month = (int)date('m');
        }
        $maxDay = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
        if ($day < 1 || $day > $maxDay) {
            $day = min((int)date('d') - 1, $maxDay);
            if ($day < 1) $day = 1;
        }

        $yesterday = mktime(0, 0, 0, $month, $day, $year);
        $today = mktime(0, 0, 0, $month, $day + 1, $year);
        $from = $yesterday;
        $till = $today;

        // 使用 ProblemFinder 获取与报表周期有交集的所有告警
        // 先获取主机（支持分组过滤），用于告警筛选
        $hostOptions = [
            'output' => ['hostid', 'name'],
            'filter' => ['status' => HOST_STATUS_MONITORED]
        ];
        if ($groupId !== '') {
            $hostOptions['groupids'] = [$groupId];
        }
        $filteredHosts = API::Host()->get($hostOptions);
        $filteredHostIds = array_column($filteredHosts, 'hostid');

        $problemResult = ProblemFinder::getProblemsInPeriod($from, $till, 500, $filteredHostIds);
        $problemCount = $problemResult['problemCount'];
        $resolvedCount = $problemResult['resolvedCount'];
        $events = $problemResult['problemEvents'];
        $recoveryMap = $problemResult['recoveryMap'];
        $triggerHostMap = $problemResult['triggerHostMap'];
        $triggerStatusMap = $problemResult['triggerStatusMap'] ?? [];

        // 构建告警信息
        $alertInfo = [];
        $hostCounts = [];
        foreach ($events as $event) {
            if (!empty($event['hosts'][0]['name'])) {
                $hostName = $event['hosts'][0]['name'];
            } elseif (isset($triggerHostMap[$event['objectid']])) {
                $hostName = $triggerHostMap[$event['objectid']];
            } else {
                $hostName = LanguageManager::t('Unknown Host');
            }
            $alertTime = date('Y-m-d H:i:s', $event['clock']);
            $recoveryTime = null;
            if (!empty($event['r_eventid']) && isset($recoveryMap[$event['r_eventid']])) {
                $recoveryTime = date('Y-m-d H:i:s', $recoveryMap[$event['r_eventid']]);
            }
            $alertInfo[] = [
                'host' => $hostName,
                'alert' => $event['name'],
                'time' => $alertTime,
                'recovery_time' => $recoveryTime,
                'severity' => (int)($event['severity'] ?? 0),
                'trigger_disabled' => isset($triggerStatusMap[$event['objectid']]) && ($triggerStatusMap[$event['objectid']] === 'disabled' || $triggerStatusMap[$event['objectid']] === 'deleted')
            ];
            $hostCounts[$hostName] = ($hostCounts[$hostName] ?? 0) + 1;
        }
        arsort($hostCounts);
        $topHosts = array_slice($hostCounts, 0, 10, true);

        // 获取所有主机（使用已过滤的主机列表）
        $hosts = $filteredHosts;

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

        // 第二部分：按主机群组分组的主机信息
        $hostsByGroup = [];
        $cpuUsage = [];
        $memUsage = [];
        $cpuTotal = [];
        $memTotal = [];
        
        foreach ($hosts as $host) {
            // 分组主机
            $groups = isset($hostGroups[$host['hostid']]) ? $hostGroups[$host['hostid']] : [];
            $hostGroupName = !empty($groups) ? $groups[0]['name'] : 'No Group';
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
            
            // 使用ItemFinder查找CPU使用率
            $cpuUtilResult = ItemFinder::findCpuUtilization($host['hostid'], $from, $till);
            if ($cpuUtilResult && $cpuUtilResult['value'] !== null) {
                $cpuUsage[$host['name']] = $cpuUtilResult['value'];
                $hostInfo['cpu_usage'] = number_format($cpuUtilResult['value'], 2) . '%';
            }
            
            // 使用ItemFinder查找CPU数量
            $cpuCountResult = ItemFinder::findCpuCount($host['hostid'], $from, $till);
            if ($cpuCountResult && $cpuCountResult['value'] !== null) {
                $cpuTotal[$host['name']] = $cpuCountResult['value'];
                $hostInfo['cpu_total'] = $cpuCountResult['value'];
            }
            
            // 使用ItemFinder查找内存使用率
            $memUtilResult = ItemFinder::findMemoryUtilization($host['hostid'], $from, $till);
            if ($memUtilResult && $memUtilResult['value'] !== null) {
                $memUsage[$host['name']] = $memUtilResult['value'];
                $hostInfo['mem_usage'] = number_format($memUtilResult['value'], 2) . '%';
            }
            
            // 使用ItemFinder查找内存总量
            $memTotalResult = ItemFinder::findMemorySize($host['hostid'], $from, $till);
            if ($memTotalResult && $memTotalResult['value'] !== null) {
                $memTotal[$host['name']] = $memTotalResult['value'];
                $hostInfo['mem_total'] = number_format($memTotalResult['value'] / (1024*1024*1024), 2);
            }
            
            // 添加主机到对应群组
            $hostsByGroup[$hostGroupName][] = $hostInfo;
        }
        arsort($cpuUsage);
        arsort($memUsage);
        $topCpuHosts = array_slice($cpuUsage, 0, 10, true);
        $topMemHosts = array_slice($memUsage, 0, 10, true);

        // 准备PDF数据（新格式）
        $reportData = [
            'date' => date('Y-m-d', $yesterday),
            'report_date' => date('Y-m-d', $yesterday),
            'problemCount' => $problemCount,
            'resolvedCount' => $resolvedCount,
            'alertInfo' => $alertInfo,
            'hostsByGroup' => $hostsByGroup,
            // 保留旧格式兼容性
            'topHosts' => $topHosts,
            'topCpuHosts' => $topCpuHosts,
            'topMemHosts' => $topMemHosts,
            'cpuTotal' => $cpuTotal,
            'memTotal' => $memTotal
        ];

        // 生成PDF
        $pdfTitle = LanguageManager::t('Zabbix Daily Report') . ' - ' . date('Y-m-d', $yesterday);
        if ($groupName !== '') {
            $pdfTitle .= ' [' . $groupName . ']';
        }
        $pdfGenerator = new PdfGenerator($pdfTitle);
        $pdfGenerator->setData($reportData);
        $pdfContent = $pdfGenerator->generate();

        // 检查是否是HTML格式（当没有PDF库时的fallback）
        if (strpos($pdfContent, '<!DOCTYPE html>') === 0) {
            // 返回HTML文件，用户可以打印为PDF
            header('Content-Type: text/html; charset=UTF-8');
            header('Content-Disposition: attachment; filename="daily_report_' . date('Y-m-d', $yesterday) . '.html"');
        } else {
            // 返回实际的PDF文件
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="daily_report_' . date('Y-m-d', $yesterday) . '.pdf"');
        }
        
        echo $pdfContent;
        exit;
    }
}
