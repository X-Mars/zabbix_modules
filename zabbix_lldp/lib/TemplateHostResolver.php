<?php

namespace Modules\ZabbixLldp\Lib;

use API;

require_once __DIR__ . '/ConfigManager.php';
require_once __DIR__ . '/ZabbixVersion.php';

/**
 * 按 LLDP 模板过滤主机与主机分组
 */
class TemplateHostResolver {

    private static $templateId = null;
    private static $hostsCache = null;

    public static function getTemplateId(): ?string {
        if (self::$templateId !== null) {
            return self::$templateId !== '' ? self::$templateId : null;
        }

        $name = ConfigManager::getTemplateName();
        if ($name === '') {
            self::$templateId = '';
            return null;
        }

        try {
            $templates = API::Template()->get([
                'output' => ['templateid', 'name', 'host'],
                'filter' => ['name' => [$name]],
                'limit'  => 1,
            ]);

            if (empty($templates)) {
                $templates = API::Template()->get([
                    'output' => ['templateid', 'name', 'host'],
                    'filter' => ['host' => [$name]],
                    'limit'  => 1,
                ]);
            }

            if (!empty($templates[0]['templateid'])) {
                self::$templateId = (string) $templates[0]['templateid'];
                return self::$templateId;
            }
        } catch (\Throwable $e) {
            error_log('LLDP: template lookup failed: ' . $e->getMessage());
        }

        self::$templateId = '';
        return null;
    }

    /**
     * @return array<int, array{hostid:string, host:string, name:string, groupids:string[]}>
     */
    public static function getTemplateHosts(): array {
        if (self::$hostsCache !== null) {
            return self::$hostsCache;
        }

        self::$hostsCache = [];
        $templateId = self::getTemplateId();
        if ($templateId === null) {
            return self::$hostsCache;
        }

        $groupsParam = ZabbixVersion::getSelectGroupsParam();
        $params = [
            'output'    => ['hostid', 'host', 'name', 'status'],
            'templateids' => [$templateId],
            $groupsParam => ['groupid', 'name'],
            'sortfield' => 'name',
            'sortorder' => 'ASC',
        ];

        if (defined('HOST_STATUS_MONITORED')) {
            $params['filter'] = ['status' => HOST_STATUS_MONITORED];
        }

        try {
            $hosts = API::Host()->get($params);
        } catch (\Throwable $e) {
            error_log('LLDP: host fetch failed: ' . $e->getMessage());
            return self::$hostsCache;
        }

        if (!is_array($hosts)) {
            return self::$hostsCache;
        }

        foreach ($hosts as $host) {
            $hostid = (string) ($host['hostid'] ?? '');
            if ($hostid === '') {
                continue;
            }

            $groups = $host['hostgroups'] ?? ($host['groups'] ?? []);
            $groupIds = [];
            foreach ($groups as $g) {
                $gid = (string) ($g['groupid'] ?? '');
                if ($gid !== '') {
                    $groupIds[] = $gid;
                }
            }

            self::$hostsCache[] = [
                'hostid'   => $hostid,
                'host'     => (string) ($host['host'] ?? ''),
                'name'     => (string) ($host['name'] ?? ''),
                'groupids' => $groupIds,
            ];
        }

        return self::$hostsCache;
    }

    /**
     * @return array<int, array{groupid:string, name:string}>
     */
    public static function getHostGroups(): array {
        $hosts = self::getTemplateHosts();
        $map = [];

        foreach ($hosts as $host) {
            foreach ($host['groupids'] as $gid) {
                if (!isset($map[$gid])) {
                    $map[$gid] = ['groupid' => $gid, 'name' => ''];
                }
            }
        }

        if (empty($map)) {
            return [];
        }

        try {
            $groups = API::HostGroup()->get([
                'output'    => ['groupid', 'name'],
                'groupids'  => array_keys($map),
                'sortfield' => 'name',
                'sortorder' => 'ASC',
            ]);
        } catch (\Throwable $e) {
            return [];
        }

        $result = [];
        foreach ((is_array($groups) ? $groups : []) as $g) {
            $result[] = [
                'groupid' => (string) ($g['groupid'] ?? ''),
                'name'    => (string) ($g['name'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{hostid:string, name:string, host:string}>
     */
    public static function getHosts(string $groupid = '0'): array {
        $hosts = self::getTemplateHosts();
        $result = [];

        foreach ($hosts as $host) {
            if ($groupid !== '' && $groupid !== '0') {
                if (!in_array($groupid, $host['groupids'], true)) {
                    continue;
                }
            }
            $result[] = [
                'hostid' => $host['hostid'],
                'name'   => $host['name'] !== '' ? $host['name'] : $host['host'],
                'host'   => $host['host'],
            ];
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    public static function resolveHostIds(string $groupid, string $hostid): array {
        if ($hostid !== '' && $hostid !== '0') {
            return [$hostid];
        }

        $ids = [];
        foreach (self::getHosts($groupid) as $host) {
            $ids[] = $host['hostid'];
        }

        return $ids;
    }

    /**
     * @return array<string, array{hostid:string, name:string, host:string}>
     */
    public static function getHostMap(string $groupid, string $hostid): array {
        $map = [];
        if ($hostid !== '' && $hostid !== '0') {
            foreach (self::getTemplateHosts() as $host) {
                if ($host['hostid'] === $hostid) {
                    $map[$hostid] = [
                        'hostid' => $host['hostid'],
                        'name'   => $host['name'] !== '' ? $host['name'] : $host['host'],
                        'host'   => $host['host'],
                    ];
                    break;
                }
            }
            return $map;
        }

        foreach (self::getHosts($groupid) as $host) {
            $map[$host['hostid']] = $host;
        }

        return $map;
    }
}
