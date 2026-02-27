<?php declare(strict_types = 0);

namespace Modules\ZabbixReports\Actions;

use CController,
    CControllerResponseFatal,
    API;

require_once dirname(__DIR__) . '/lib/ItemFinder.php';
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/PdfGenerator.php';

use Modules\ZabbixReports\Lib\ItemFinder;
use Modules\ZabbixReports\Lib\LanguageManager;
use Modules\ZabbixReports\Lib\PdfGenerator;

class CustomReportExport extends CController {

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
            'format' => 'string'
        ];
        
        return $this->validateInput($fields);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        try {
            $language = LanguageManager::getCurrentLanguage();

            $fromDate = $this->getInput('from_date');
            $toDate = $this->getInput('to_date');
            $format = $this->getInput('format', 'pdf');

            // 验证日期
            $fromTimestamp = strtotime($fromDate . ' 00:00:00');
            $toTimestamp = strtotime($toDate . ' 23:59:59');
            
            if ($fromTimestamp === false || $toTimestamp === false) {
                throw new \Exception(LanguageManager::t('Invalid date format'));
            }

            // 重新生成报表数据
            $reportData = $this->generateReportData($fromTimestamp, $toTimestamp);

            // 只支持PDF导出（参考日报导出）
            $this->exportToPdf($reportData, $fromDate, $toDate);

        } catch (\Exception $e) {
            error_log("Custom Report Export Error: " . $e->getMessage());
            $this->setResponse(new CControllerResponseFatal($e->getMessage()));
        }
    }

    private function generateReportData(int $fromTimestamp, int $toTimestamp): array {
        // 获取告警事件数量统计
        $problemCount = API::Event()->get([
            'countOutput' => true,
            'filter' => ['value' => TRIGGER_VALUE_TRUE],
            'time_from' => $fromTimestamp,
            'time_till' => $toTimestamp
        ]);

        $resolvedCount = API::Event()->get([
            'countOutput' => true,
            'filter' => ['value' => TRIGGER_VALUE_FALSE],
            'time_from' => $fromTimestamp,
            'time_till' => $toTimestamp
        ]);

        // 获取告警事件信息（仅问题事件，使用 selectHosts 避免 N+1 查询）
        $events = API::Event()->get([
            'output' => ['eventid', 'objectid', 'name', 'clock', 'value', 'r_eventid', 'severity'],
            'source' => 0,
            'object' => 0,
            'value' => TRIGGER_VALUE_TRUE,
            'time_from' => $fromTimestamp,
            'time_till' => $toTimestamp,
            'selectHosts' => ['hostid', 'name'],
            'sortfield' => 'clock',
            'sortorder' => 'DESC',
            'limit' => 500
        ]);

        // 批量获取恢复事件
        $recoveryEventIds = [];
        foreach ($events as $event) {
            if (!empty($event['r_eventid']) && $event['r_eventid'] != 0) {
                $recoveryEventIds[] = $event['r_eventid'];
            }
        }
        $recoveryMap = [];
        if (!empty($recoveryEventIds)) {
            $recoveryEvents = API::Event()->get([
                'output' => ['eventid', 'clock'],
                'eventids' => $recoveryEventIds
            ]);
            foreach ($recoveryEvents as $re) {
                $recoveryMap[$re['eventid']] = $re['clock'];
            }
        }

        // 对 selectHosts 返回空的事件，通过 Trigger API 二次解析主机名
        $unknownTriggerIds = [];
        foreach ($events as $event) {
            if (empty($event['hosts'])) {
                $unknownTriggerIds[$event['objectid']] = true;
            }
        }
        $triggerHostMap = [];
        if (!empty($unknownTriggerIds)) {
            $triggers = API::Trigger()->get([
                'output' => ['triggerid'],
                'triggerids' => array_keys($unknownTriggerIds),
                'selectHosts' => ['name'],
            ]);
            foreach ($triggers as $trigger) {
                if (!empty($trigger['hosts'])) {
                    $triggerHostMap[$trigger['triggerid']] = $trigger['hosts'][0]['name'];
                }
            }
        }

        // 构建告警信息（主机已删除时显示为未知主机）
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
                'severity' => (int)($event['severity'] ?? 0)
            ];
            $hostCounts[$hostName] = ($hostCounts[$hostName] ?? 0) + 1;
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

        // 第二部分：按主机群组分组的主机信息
        $hostsByGroup = [];
        $cpuUsage = [];
        $memUsage = [];
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
            
            // 使用ItemFinder查找CPU使用率
            $cpuUtilResult = ItemFinder::findCpuUtilization($host['hostid'], $fromTimestamp, $toTimestamp);
            if ($cpuUtilResult && $cpuUtilResult['value'] !== null) {
                $cpuUsage[$host['name']] = $cpuUtilResult['value'];
                $hostInfo['cpu_usage'] = number_format($cpuUtilResult['value'], 2) . '%';
            }
            
            // 使用ItemFinder查找CPU数量
            $cpuCountResult = ItemFinder::findCpuCount($host['hostid'], $fromTimestamp, $toTimestamp);
            if ($cpuCountResult && $cpuCountResult['value'] !== null) {
                $cpuTotal[$host['name']] = $cpuCountResult['value'];
                $hostInfo['cpu_total'] = $cpuCountResult['value'];
            }
            
            // 使用ItemFinder查找内存使用率
            $memUtilResult = ItemFinder::findMemoryUtilization($host['hostid'], $fromTimestamp, $toTimestamp);
            if ($memUtilResult && $memUtilResult['value'] !== null) {
                $memUsage[$host['name']] = $memUtilResult['value'];
                $hostInfo['mem_usage'] = number_format($memUtilResult['value'], 2) . '%';
            }
            
            // 使用ItemFinder查找内存总量
            $memTotalResult = ItemFinder::findMemorySize($host['hostid'], $fromTimestamp, $toTimestamp);
            if ($memTotalResult && $memTotalResult['value'] !== null) {
                $memTotal[$host['name']] = $memTotalResult['value'];
                $hostInfo['mem_total'] = number_format($memTotalResult['value'] / (1024*1024*1024), 2);
            }
            
            // 添加主机到对应群组
            $hostsByGroup[$groupName][] = $hostInfo;
        }
        arsort($cpuUsage);
        arsort($memUsage);
        $topCpuHosts = array_slice($cpuUsage, 0, 10, true);
        $topMemHosts = array_slice($memUsage, 0, 10, true);

        // 准备数据（参考日报格式）
        return [
            'date' => date('Y-m-d', $fromTimestamp),
            'report_date' => date('Y-m-d', $fromTimestamp),
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
    }

    private function exportToPdf(array $data, string $fromDate, string $toDate): void {
        // 生成PDF（严格参考日报导出方式）
        $pdfGenerator = new PdfGenerator(LanguageManager::t('Custom Report') . ' - ' . $fromDate . ' - ' . $toDate);
        $pdfGenerator->setData($data);
        $pdfContent = $pdfGenerator->generate();

        // 检查是否是HTML格式（当没有PDF库时的fallback）
        if (strpos($pdfContent, '<!DOCTYPE html>') === 0) {
            // 返回HTML文件，用户可以打印为PDF
            header('Content-Type: text/html; charset=UTF-8');
            header('Content-Disposition: attachment; filename="custom_report_' . str_replace('-', '', $fromDate) . '_' . str_replace('-', '', $toDate) . '.html"');
        } else {
            // 返回实际的PDF文件
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="custom_report_' . str_replace('-', '', $fromDate) . '_' . str_replace('-', '', $toDate) . '.pdf"');
        }
        
        echo $pdfContent;
        exit;
    }


}