<?php

namespace Modules\ZabbixReports\Actions;

use CController,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
use Modules\ZabbixReports\Lib\LanguageManager;

class WeeklyReportSend extends CController {

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

        $events = API::Event()->get([
            'output' => ['eventid', 'objectid'],
            'filter' => ['value' => TRIGGER_VALUE_TRUE],
            'time_from' => $from,
            'time_till' => $till
        ]);

        $hostCounts = [];
        if (!empty($events)) {
            $triggerIds = array_unique(array_column($events, 'objectid'));
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
            
            foreach ($events as $event) {
                $host = isset($triggerHosts[$event['objectid']]) ? $triggerHosts[$event['objectid']] : null;
                if (!$host) {
                    $hostName = LanguageManager::t('Unknown Host');
                } else {
                    $hostName = $host['name'];
                }
                $hostCounts[$hostName] = ($hostCounts[$hostName] ?? 0) + 1;
            }
        }
        arsort($hostCounts);
        $topHosts = array_slice($hostCounts, 0, 10, true);

        $hosts = API::Host()->get([
            'output' => ['hostid', 'name'],
            'filter' => ['status' => HOST_STATUS_MONITORED]
        ]);

        $cpuUsage = [];
        $memUsage = [];
        foreach ($hosts as $host) {
            $cpuItems = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $host['hostid'],
                'search' => ['name' => 'CPU utilization'],
                'filter' => ['status' => ITEM_STATUS_ACTIVE]
            ]);
            if ($cpuItems) {
                $history = API::History()->get([
                    'output' => ['value'],
                    'itemids' => $cpuItems[0]['itemid'],
                    'time_from' => $from,
                    'time_till' => $till,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ]);
                if ($history) {
                    $cpuUsage[$host['name']] = $history[0]['value'];
                }
            }

            $memItems = API::Item()->get([
                'output' => ['itemid'],
                'hostids' => $host['hostid'],
                'search' => ['name' => 'Memory utilization'],
                'filter' => ['status' => ITEM_STATUS_ACTIVE]
            ]);
            if ($memItems) {
                $history = API::History()->get([
                    'output' => ['value'],
                    'itemids' => $memItems[0]['itemid'],
                    'time_from' => $from,
                    'time_till' => $till,
                    'sortfield' => 'clock',
                    'sortorder' => 'DESC',
                    'limit' => 1
                ]);
                if ($history) {
                    $memUsage[$host['name']] = $history[0]['value'];
                }
            }
        }
        arsort($cpuUsage);
        arsort($memUsage);
        $topCpuHosts = array_slice($cpuUsage, 0, 10, true);
        $topMemHosts = array_slice($memUsage, 0, 10, true);

        $to = 'admin@example.com';
        $subject = LanguageManager::t('Zabbix Weekly Report') . ' - ' . date('Y-m-d', $weekStart) . ' to ' . date('Y-m-d', $weekEnd);
        $message = "
        <h1>" . LanguageManager::t('Zabbix Weekly Report') . "</h1>
        <p>Problem Count: $problemCount</p>
        <p>Resolved Count: $resolvedCount</p>
        <p>Top Problem Hosts: " . implode(', ', array_keys($topHosts)) . "</p>
        <p>Top CPU Usage Hosts: " . implode(', ', array_keys($topCpuHosts)) . "</p>
        <p>Top Memory Usage Hosts: " . implode(', ', array_keys($topMemHosts)) . "</p>
        ";
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= 'From: zabbix@example.com' . "\r\n";

        mail($to, $subject, $message, $headers);

        $data = ['message' => 'Report sent successfully'];
        $response = new CControllerResponseData($data);
        $this->setResponse($response);
    }
}
