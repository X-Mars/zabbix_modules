<?php

namespace Modules\ZabbixLldp\Lib;

use API;

require_once __DIR__ . '/ConfigManager.php';

/**
 * 从 lldp.remote.info[*] 监控项构建拓扑 nodes / edges
 */
class LldpTopologyBuilder {

    private const KEY_PATTERN = '/^lldp\.remote\.info\[(.+)\]$/';
    private const VALUE_PATTERN = '/^(.+)\((.+)\)$/s';

    /**
     * @param array<string, array{hostid:string, name:string, host:string}> $hostMap
     */
    public static function build(array $hostMap): array {
        $hostIds = array_keys($hostMap);
        if (empty($hostIds)) {
            return self::emptyResult();
        }

        $prefix = ConfigManager::getItemKeyPrefix();
        $items = self::fetchItems($hostIds, $prefix);
        $alertMap = self::fetchHostAlertSummary($hostIds);
        $hostLookup = self::buildHostLookup($hostMap);

        $nodes = [];
        $edges = [];
        $skipped = 0;
        $edgeIndex = 0;

        foreach ($items as $item) {
            $hostid = (string) ($item['hostid'] ?? '');
            if ($hostid === '' || !isset($hostMap[$hostid])) {
                $skipped++;
                continue;
            }

            $key = (string) ($item['key_'] ?? '');
            if (!preg_match(self::KEY_PATTERN, $key, $keyMatch)) {
                $skipped++;
                continue;
            }

            $localPort = trim($keyMatch[1]);
            $lastvalue = trim((string) ($item['lastvalue'] ?? ''));
            if ($localPort === '' || $lastvalue === '' || $lastvalue === '-') {
                $skipped++;
                continue;
            }

            if (!preg_match(self::VALUE_PATTERN, $lastvalue, $valMatch)) {
                $skipped++;
                continue;
            }

            $remoteDevice = trim($valMatch[1]);
            $remotePort = trim($valMatch[2]);
            if ($remoteDevice === '') {
                $skipped++;
                continue;
            }

            $localMeta = $hostMap[$hostid];
            $localLabel = $localMeta['name'] !== '' ? $localMeta['name'] : $localMeta['host'];
            $localNodeId = 'host:' . $hostid;
            $matchedHostid = $hostLookup[self::normalizeName($remoteDevice)] ?? null;
            $remoteNodeId = $matchedHostid !== null ? 'host:' . $matchedHostid : 'dev:' . $remoteDevice;

            if (!isset($nodes[$localNodeId]) || $nodes[$localNodeId]['kind'] !== 'local') {
                $hostAlert = $alertMap[$hostid] ?? [
                    'severity' => -1,
                    'problem_count' => 0,
                    'problems' => [],
                ];

                $nodes[$localNodeId] = [
                    'id'     => $localNodeId,
                    'label'  => $localLabel,
                    'kind'   => 'local',
                    'hostid' => $hostid,
                    'host'   => $localMeta['host'],
                    'severity' => (int) $hostAlert['severity'],
                    'problemCount' => (int) $hostAlert['problem_count'],
                    'problems' => $hostAlert['problems'],
                    'image'  => null,
                ];
            }

            if (!isset($nodes[$remoteNodeId])) {
                $matchedMeta = ($matchedHostid !== null && isset($hostMap[$matchedHostid])) ? $hostMap[$matchedHostid] : null;
                $matchedAlert = ($matchedHostid !== null && isset($alertMap[$matchedHostid])) ? $alertMap[$matchedHostid] : [
                    'severity' => -1,
                    'problem_count' => 0,
                    'problems' => [],
                ];

                $nodes[$remoteNodeId] = [
                    'id'     => $remoteNodeId,
                    'label'  => $remoteDevice,
                    'kind'   => 'remote',
                    'hostid' => $matchedHostid,
                    'host'   => $matchedMeta['host'] ?? null,
                    'severity' => (int) ($matchedAlert['severity'] ?? -1),
                    'problemCount' => (int) ($matchedAlert['problem_count'] ?? 0),
                    'problems' => $matchedAlert['problems'] ?? [],
                    'image'  => null,
                ];
            }

            $edgeIndex++;
            $edges[] = [
                'id'           => 'e' . $edgeIndex,
                'source'       => $localNodeId,
                'target'       => $remoteNodeId,
                'localDevice'  => $localLabel,
                'localPort'    => $localPort,
                'remoteDevice' => $remoteDevice,
                'remotePort'   => $remotePort,
                'lastclock'    => (int) ($item['lastclock'] ?? 0),
            ];
        }

        $nodeList = array_values($nodes);
        return [
            'nodes'  => $nodeList,
            'preferredRoot' => self::getPreferredRoot(),
            'edges'  => $edges,
            'stats'  => [
                'hosts'    => count(array_filter($nodeList, static function ($node) {
                    return $node['kind'] === 'local';
                })),
                'links'    => count($edges),
                'devices'  => count($nodeList),
                'skipped'  => $skipped,
            ],
        ];
    }

    private static function getPreferredRoot(): ?string {
        $config = ConfigManager::load();
        $id = $config['root_hostid'] ?? '';
        return (is_string($id) || is_int($id)) && ctype_digit((string) $id) ? 'host:' . $id : null;
    }

    private static function fetchItems(array $hostIds, string $prefix): array {
        try {
            $params = [
                'output'  => ['itemid', 'hostid', 'name', 'key_', 'lastvalue', 'lastclock'],
                'hostids' => $hostIds,
                'search'  => ['key_' => $prefix],
                'filter'  => ['status' => defined('ITEM_STATUS_ACTIVE') ? ITEM_STATUS_ACTIVE : 0],
            ];

            $items = API::Item()->get($params);
            return is_array($items) ? $items : [];
        } catch (\Throwable $e) {
            error_log('LLDP: item fetch failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * @param array<string, array{hostid:string, name:string, host:string}> $hostMap
     * @return array<string, string|null>
     */
    private static function buildHostLookup(array $hostMap): array {
        $lookup = [];
        foreach ($hostMap as $hostid => $meta) {
            foreach (['name', 'host'] as $field) {
                $key = self::normalizeName((string) ($meta[$field] ?? ''));
                if ($key === '') {
                    continue;
                }
                if (array_key_exists($key, $lookup) && $lookup[$key] !== (string) $hostid) {
                    $lookup[$key] = null;
                } else {
                    $lookup[$key] = (string) $hostid;
                }
            }
        }
        return $lookup;
    }

    private static function normalizeName(string $value): string {
        return strtolower(trim($value));
    }

    /**
     * @param array<int, string> $hostIds
     * @return array<string, array{severity:int, problem_count:int, problems:array<int, array{name:string, severity:int, clock:int}>}>
     */
    private static function fetchHostAlertSummary(array $hostIds): array {
        $result = [];
        foreach ($hostIds as $hostid) {
            $result[(string) $hostid] = [
                'severity' => -1,
                'problem_count' => 0,
                'problems' => [],
            ];
        }

        try {
            $problems = API::Problem()->get([
                'output' => ['eventid', 'name', 'severity', 'clock'],
                'hostids' => $hostIds,
                'selectHosts' => ['hostid'],
                'sortfield' => ['severity', 'clock'],
                'sortorder' => 'DESC',
                'suppressed' => false,
                'recent' => true,
                'limit' => 1000,
            ]);
        } catch (\Throwable $e) {
            error_log('LLDP: problem fetch failed: ' . $e->getMessage());
            return $result;
        }

        if (!is_array($problems)) {
            return $result;
        }

        foreach ($problems as $problem) {
            $sev = (int) ($problem['severity'] ?? -1);
            $clock = (int) ($problem['clock'] ?? 0);
            $name = (string) ($problem['name'] ?? '');
            $hosts = $problem['hosts'] ?? [];
            if (!is_array($hosts)) {
                continue;
            }

            foreach ($hosts as $host) {
                $hid = (string) ($host['hostid'] ?? '');
                if ($hid === '' || !isset($result[$hid])) {
                    continue;
                }

                $result[$hid]['problem_count']++;
                if ($sev > $result[$hid]['severity']) {
                    $result[$hid]['severity'] = $sev;
                }

                if (count($result[$hid]['problems']) < 5) {
                    $result[$hid]['problems'][] = [
                        'name' => $name,
                        'severity' => $sev,
                        'clock' => $clock,
                    ];
                }
            }
        }

        return $result;
    }

    private static function emptyResult(): array {
        return [
            'nodes' => [],
            'edges' => [],
            'stats' => ['hosts' => 0, 'links' => 0, 'devices' => 0, 'skipped' => 0],
        ];
    }
}
