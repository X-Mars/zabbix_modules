<?php

namespace Modules\ZabbixIpam\Lib;

/** Resolves IPv4 addresses to Zabbix hosts through configured host interfaces. */
class HostMatcher {
    private static $cache;

    public static function byIp(): array {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $map = [];
        try {
            $hosts = \API::Host()->get([
                'output' => ['hostid', 'host', 'name', 'status'],
                'selectInterfaces' => ['interfaceid', 'ip', 'dns', 'useip', 'main', 'type'],
                'preservekeys' => false
            ]);

            foreach ($hosts as $host) {
                foreach ($host['interfaces'] ?? [] as $interface) {
                    $ip = trim((string) ($interface['ip'] ?? ''));
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
                        continue;
                    }

                    $host_id = (string) $host['hostid'];
                    $key = $host_id.'@'.$ip;
                    $map[$ip][$key] = [
                        'hostid' => $host_id,
                        'name' => (string) ($host['name'] ?: $host['host']),
                        'host' => (string) $host['host'],
                        'status' => (int) $host['status'],
                        'url' => self::hostUrl($host_id)
                    ];
                }
            }
        }
        catch (\Throwable $exception) {
            error_log('IPAM host matching: '.$exception->getMessage());
        }

        foreach ($map as $ip => $hosts) {
            $map[$ip] = array_values($hosts);
        }

        return self::$cache = $map;
    }

    public static function hostUrl(string $host_id): string {
        return 'zabbix.php?name=&evaltype=0&tags%5B0%5D%5Btag%5D=&tags%5B0%5D%5Boperator%5D=0'
            .'&tags%5B0%5D%5Bvalue%5D=&show_tags=3&tag_name_format=0&tag_priority=&state=-1'
            .'&filter_name=&filter_show_counter=0&filter_custom_time=0&sort=name&sortorder=ASC'
            .'&show_details=0&action=latest.view&hostids%5B%5D='.rawurlencode($host_id);
    }
}
