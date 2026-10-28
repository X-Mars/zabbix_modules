<?php

namespace Modules\ZabbixReports\Lib;

use API;

require_once __DIR__ . '/ItemFinder.php';
require_once __DIR__ . '/LanguageManager.php';

/**
 * Builds the host/resource section shared by all interactive reports.
 */
class HostMetrics {

    public static function build(array $hosts, int $from, int $till): array {
        if (empty($hosts)) {
            return self::emptyResult();
        }

        $hostids = array_values(array_unique(array_map('strval', array_column($hosts, 'hostid'))));
        $hostSet = array_fill_keys($hostids, true);
        $hostGroups = [];

        // selectHosts turns the former group-by-group N+1 loop into one request.
        $groups = API::HostGroup()->get([
            'output' => ['groupid', 'name'],
            'hostids' => $hostids,
            'selectHosts' => ['hostid'],
            'sortfield' => 'name'
        ]);
        if (is_array($groups)) {
            foreach ($groups as $group) {
                foreach (($group['hosts'] ?? []) as $groupHost) {
                    $hostid = (string)$groupHost['hostid'];
                    if (isset($hostSet[$hostid])) {
                        $hostGroups[$hostid][] = $group;
                    }
                }
            }
        }

        $metrics = ItemFinder::findMetricsForHosts($hostids, $from, $till);
        $hostsByGroup = [];
        $cpuUsage = [];
        $memUsage = [];
        $cpuTotal = [];
        $memTotal = [];

        foreach ($hosts as $host) {
            $hostid = (string)$host['hostid'];
            $hostName = $host['name'];
            $groupName = !empty($hostGroups[$hostid])
                ? $hostGroups[$hostid][0]['name']
                : LanguageManager::t('No Group');
            $hostMetrics = $metrics[$hostid] ?? [];
            $hostInfo = [
                'name' => $hostName,
                'cpu_usage' => 'N/A',
                'cpu_total' => 'N/A',
                'mem_usage' => 'N/A',
                'mem_total' => 'N/A'
            ];

            if (isset($hostMetrics['cpu_usage']['value']) && $hostMetrics['cpu_usage']['value'] !== null) {
                $cpuUsage[$hostName] = (float)$hostMetrics['cpu_usage']['value'];
                $hostInfo['cpu_usage'] = number_format($cpuUsage[$hostName], 2) . '%';
            }
            if (isset($hostMetrics['mem_usage']['value']) && $hostMetrics['mem_usage']['value'] !== null) {
                $memUsage[$hostName] = (float)$hostMetrics['mem_usage']['value'];
                $hostInfo['mem_usage'] = number_format($memUsage[$hostName], 2) . '%';
            }
            if (isset($hostMetrics['cpu_total']['value']) && $hostMetrics['cpu_total']['value'] !== null) {
                $cpuTotal[$hostName] = $hostMetrics['cpu_total']['value'];
                $hostInfo['cpu_total'] = $hostMetrics['cpu_total']['value'];
            }
            if (isset($hostMetrics['mem_total']['value']) && $hostMetrics['mem_total']['value'] !== null) {
                $memTotal[$hostName] = $hostMetrics['mem_total']['value'];
                $hostInfo['mem_total'] = number_format((float)$hostMetrics['mem_total']['value'] / (1024 * 1024 * 1024), 2);
            }

            $hostsByGroup[$groupName][] = $hostInfo;
        }

        arsort($cpuUsage);
        arsort($memUsage);

        return [
            'hosts_by_group' => $hostsByGroup,
            'top_cpu_hosts' => array_slice($cpuUsage, 0, 10, true),
            'top_mem_hosts' => array_slice($memUsage, 0, 10, true),
            'cpu_total' => $cpuTotal,
            'mem_total' => $memTotal
        ];
    }

    private static function emptyResult(): array {
        return [
            'hosts_by_group' => [],
            'top_cpu_hosts' => [],
            'top_mem_hosts' => [],
            'cpu_total' => [],
            'mem_total' => []
        ];
    }
}
