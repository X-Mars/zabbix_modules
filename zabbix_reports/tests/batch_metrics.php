<?php

// Regression test for the report-page N+1 query fix.
// Run with: php zabbix_reports/tests/batch_metrics.php

use Modules\ZabbixReports\Lib\HostMetrics;
use Modules\ZabbixReports\Lib\ProblemFinder;

define('ITEM_STATUS_ACTIVE', 0);

class API {
    public static $items = [];
    public static $calls = ['item' => 0, 'history' => 0, 'group' => 0, 'event' => 0];

    public static function Item() { return new FakeItemApi(); }
    public static function History() { return new FakeHistoryApi(); }
    public static function HostGroup() { return new FakeHostGroupApi(); }
    public static function Event() { self::$calls['event']++; return new FakeEmptyApi(); }
}

class FakeItemApi {
    public function get(array $options): array {
        API::$calls['item']++;
        if (!isset($options['filter']['key_'])) {
            return [];
        }
        return array_values(array_filter(API::$items, function(array $item) use ($options): bool {
            return in_array($item['hostid'], $options['hostids'], true)
                && in_array($item['key_'], $options['filter']['key_'], true);
        }));
    }
}

class FakeHistoryApi {
    public function get(array $options): array {
        API::$calls['history']++;
        $rows = [];
        foreach ($options['itemids'] as $itemid) {
            $metricNumber = (int)$itemid % 10;
            $value = $metricNumber === 3
                ? (string)(16 * 1024 * 1024 * 1024)
                : ($metricNumber === 1 ? '4' : (string)(((int)$itemid % 80) + 10));
            $rows[] = ['itemid' => $itemid, 'value' => $value, 'clock' => 200];
        }
        return $rows;
    }
}

class FakeHostGroupApi {
    public function get(array $options): array {
        API::$calls['group']++;
        return [[
            'groupid' => '1',
            'name' => 'Linux servers',
            'hosts' => array_map(function(string $hostid): array {
                return ['hostid' => $hostid];
            }, $options['hostids'])
        ]];
    }
}

class FakeEmptyApi {
    public function get(array $options): array { return []; }
}

function check($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "PASS: {$message}\n";
}

require dirname(__DIR__) . '/lib/HostMetrics.php';
require dirname(__DIR__) . '/lib/ProblemFinder.php';

$hosts = [];
for ($hostNumber = 1; $hostNumber <= 100; $hostNumber++) {
    $hostid = (string)$hostNumber;
    $hosts[] = ['hostid' => $hostid, 'name' => 'host-' . $hostid];
    foreach ([
        ['system.cpu.util', 0],
        ['system.cpu.num', 3],
        ['vm.memory.utilization', 0],
        ['vm.memory.size[total]', 3]
    ] as $metricNumber => $definition) {
        API::$items[] = [
            'itemid' => (string)($hostNumber * 10 + $metricNumber),
            'hostid' => $hostid,
            'name' => $definition[0],
            'key_' => $definition[0],
            'lastvalue' => $metricNumber === 3 ? (string)(16 * 1024 * 1024 * 1024) : '4',
            'lastclock' => '200',
            'value_type' => (string)$definition[1]
        ];
    }
}

$result = HostMetrics::build($hosts, 100, 300);
check(count($result['hosts_by_group']['Linux servers']) === 100, 'all hosts are grouped');
check(count($result['top_cpu_hosts']) === 10, 'CPU top ten is retained');
check($result['hosts_by_group']['Linux servers'][0]['mem_total'] === '16.00', 'memory total formatting is retained');
check(API::$calls === ['item' => 2, 'history' => 2, 'group' => 1, 'event' => 0], '100 hosts use five API calls for resource metrics');

$empty = ProblemFinder::getProblemsInPeriod(100, 300, 500, []);
check($empty['problemCount'] === 0 && API::$calls['event'] === 0, 'empty host selection skips global event queries');

echo "All report batching tests passed.\n";
