<?php

namespace Modules\ZabbixJumpserver\Actions;

use CController,
    CControllerResponseData,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ConfigManager.php';

use Modules\ZabbixJumpserver\Lib\LanguageManager;
use Modules\ZabbixJumpserver\Lib\ConfigManager;

class Jumpserver extends CController {

    public function init(): void {
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation();
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation();
        }
    }

    protected function checkInput(): bool {
        $fields = [
            'groupid'  => 'string',
            'hostid'   => 'string',
            'severity' => 'string',
            'search'   => 'string',
        ];

        $ret = $this->validateInput($fields);

        if (!$ret) {
            $this->setResponse(new CControllerResponseData(['error' => LanguageManager::t('Invalid input parameters.')]));
        }

        return $ret;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $groupid  = trim((string) $this->getInput('groupid', ''));
        $hostid   = trim((string) $this->getInput('hostid', ''));
        $severity = trim((string) $this->getInput('severity', ''));
        $search   = trim((string) $this->getInput('search', ''));

        $hostGroups = $this->getHostGroups();
        $hosts = $this->getHosts($groupid, $hostid, $severity, $search);

        $response = new CControllerResponseData([
            'title'            => LanguageManager::t('JumpServer'),
            'host_groups'      => $hostGroups,
            'hosts'            => $hosts,
            'selected_groupid' => $groupid,
            'selected_hostid'  => $hostid,
            'selected_severity' => $severity,
            'search'           => $search,
            'severity_options' => $this->getSeverityOptions(),
            'is_configured'    => ConfigManager::isConfigured(),
            'asset_tag'        => ConfigManager::ASSET_TAG,
        ]);

        $response->setTitle(LanguageManager::t('JumpServer'));
        $this->setResponse($response);
    }

    /**
     * 告警严重度选项（值 => 显示名），与 Zabbix 默认严重度一致
     */
    private function getSeverityOptions(): array {
        return [
            '5' => LanguageManager::t('Disaster'),
            '4' => LanguageManager::t('High'),
            '3' => LanguageManager::t('Average'),
            '2' => LanguageManager::t('Warning'),
            '1' => LanguageManager::t('Information'),
            '0' => LanguageManager::t('Not classified'),
            'ok' => LanguageManager::t('OK'),
        ];
    }

    private function getHostGroups(): array {
        try {
            $groups = API::HostGroup()->get([
                'output'    => ['groupid', 'name'],
                'sortfield' => 'name',
                'sortorder' => 'ASC',
            ]);

            return is_array($groups) ? $groups : [];
        } catch (\Throwable $e) {
            error_log('JumpServer: Host group fetch failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * 获取主机列表（按所选分组/主机过滤），附带接口、分组与标记信息
     */
    private function getHosts(string $groupid, string $hostid, string $severity = '', string $search = ''): array {
        $params = [
            'output'           => ['hostid', 'host', 'name', 'status'],
            'selectInterfaces' => ['interfaceid', 'ip', 'dns', 'type', 'main'],
            'selectHostGroups' => ['groupid', 'name'],
            'selectTags'       => ['tag', 'value'],
            'sortfield'        => 'name',
            'sortorder'        => 'ASC',
        ];

        if ($groupid !== '' && $groupid !== '0') {
            $params['groupids'] = [$groupid];
        }
        if ($hostid !== '' && $hostid !== '0') {
            $params['hostids'] = [$hostid];
        }

        try {
            $hosts = API::Host()->get($params);
        } catch (\Throwable $e) {
            error_log('JumpServer: Host fetch failed: ' . $e->getMessage());
            return [];
        }

        if (!is_array($hosts)) {
            return [];
        }

        $assetTag = ConfigManager::ASSET_TAG;
        $result = [];

        $hostIds = [];
        foreach ($hosts as $host) {
            $hostIds[] = (string) $host['hostid'];
        }
        $problemMap = $this->getHostProblems($hostIds);

        $searchLower = $search !== '' ? mb_strtolower($search) : '';

        foreach ($hosts as $host) {
            $mainIp = '';
            $interfaces = $host['interfaces'] ?? [];
            foreach ($interfaces as $iface) {
                if ((int) ($iface['main'] ?? 0) === 1) {
                    $mainIp = ($iface['ip'] ?? '') !== '' ? $iface['ip'] : ($iface['dns'] ?? '');
                    break;
                }
            }
            if ($mainIp === '' && !empty($interfaces)) {
                $first = $interfaces[0];
                $mainIp = ($first['ip'] ?? '') !== '' ? $first['ip'] : ($first['dns'] ?? '');
            }

            $groups = $host['hostgroups'] ?? ($host['groups'] ?? []);
            $groupNames = [];
            foreach ($groups as $g) {
                $groupNames[] = (string) ($g['name'] ?? '');
            }

            $assetId = '';
            foreach (($host['tags'] ?? []) as $tag) {
                if (($tag['tag'] ?? '') === $assetTag) {
                    $assetId = (string) ($tag['value'] ?? '');
                    break;
                }
            }

            $hostIdStr = (string) $host['hostid'];
            $hostName  = (string) ($host['name'] ?? '');
            $hostTech  = (string) ($host['host'] ?? '');
            $problemInfo = $problemMap[$hostIdStr] ?? ['max' => -1, 'counts' => [], 'list' => []];
            $hostSeverity = (int) $problemInfo['max'];

            // 搜索过滤：按 IP / 主机名 / 技术名
            if ($searchLower !== '') {
                $haystack = mb_strtolower($hostName . ' ' . $hostTech . ' ' . $mainIp);
                if (mb_strpos($haystack, $searchLower) === false) {
                    continue;
                }
            }

            // 告警状态过滤：选定严重度时，保留含该严重度告警的主机；ok 表示无告警
            if ($severity !== '') {
                if ($severity === 'ok') {
                    if ($hostSeverity >= 0) {
                        continue;
                    }
                } elseif (empty($problemInfo['counts'][(int) $severity])) {
                    continue;
                }
            }

            $result[] = [
                'hostid'         => $hostIdStr,
                'host'           => $hostTech,
                'name'           => $hostName,
                'status'         => (int) ($host['status'] ?? 0),
                'ip'             => $mainIp,
                'group_names'    => $groupNames,
                'asset_id'       => $assetId,
                'severity'       => $hostSeverity,
                'problem_counts' => $problemInfo['counts'],
                'problems'       => $problemInfo['list'],
            ];
        }

        return $result;
    }

    /**
     * 获取每台主机当前处于问题状态的告警
     * 返回 hostid => [
     *   'max'    => int 最高严重度,
     *   'counts' => [severity => count],
     *   'list'   => [ ['severity'=>int, 'name'=>string, 'time'=>int], ... ]
     * ]
     */
    private function getHostProblems(array $hostIds): array {
        $map = [];
        if (empty($hostIds)) {
            return $map;
        }

        try {
            $triggers = API::Trigger()->get([
                'hostids'           => $hostIds,
                'output'            => ['triggerid', 'description', 'priority', 'lastchange', 'value'],
                'selectHosts'       => ['hostid'],
                'filter'            => ['value' => 1],
                'monitored'         => true,
                'skipDependent'     => true,
                'expandDescription' => true,
                'sortfield'         => 'lastchange',
                'sortorder'         => 'DESC',
            ]);
        } catch (\Throwable $e) {
            error_log('JumpServer: Trigger fetch failed: ' . $e->getMessage());
            return $map;
        }

        if (!is_array($triggers)) {
            return $map;
        }

        foreach ($triggers as $trigger) {
            $priority = (int) ($trigger['priority'] ?? 0);
            $name = (string) ($trigger['description'] ?? '');
            $time = (int) ($trigger['lastchange'] ?? 0);

            foreach (($trigger['hosts'] ?? []) as $h) {
                $hid = (string) ($h['hostid'] ?? '');
                if ($hid === '') {
                    continue;
                }
                if (!isset($map[$hid])) {
                    $map[$hid] = ['max' => -1, 'counts' => [], 'list' => []];
                }
                if ($priority > $map[$hid]['max']) {
                    $map[$hid]['max'] = $priority;
                }
                $map[$hid]['counts'][$priority] = ($map[$hid]['counts'][$priority] ?? 0) + 1;
                $map[$hid]['list'][] = [
                    'severity' => $priority,
                    'name'     => $name,
                    'time'     => $time,
                ];
            }
        }

        return $map;
    }
}
