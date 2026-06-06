<?php

namespace Modules\ZabbixSnmp\Actions;

use CController;
use CControllerResponseData;
use API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/MibRepository.php';

use Modules\ZabbixSnmp\Lib\LanguageManager;
use Modules\ZabbixSnmp\Lib\MibRepository;

class SnmpWalk extends CController {

    public function init(): void {
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation();
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation();
        }
    }

    protected function checkInput(): bool {
        return $this->validateInput([
            'groupid' => 'string',
            'hostid' => 'string',
            'walk_oid' => 'string',
            'run' => 'in 0,1'
        ]);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $groupid = trim((string) $this->getInput('groupid', ''));
        $hostid = trim((string) $this->getInput('hostid', ''));
        $walkOid = trim((string) $this->getInput('walk_oid', '1.3.6.1.2.1'));
        $run = ((string) $this->getInput('run', '0') === '1');

        $hostGroups = $this->getHostGroups();
        if ($groupid === '' && !empty($hostGroups)) {
            $groupid = (string) $hostGroups[0]['groupid'];
        }

        $hosts = $this->getHostsByGroup($groupid);
        if ($hostid === '' && !empty($hosts)) {
            $hostid = (string) $hosts[0]['hostid'];
        }

        $hostConnection = $this->getHostSnmpConnection($hostid);

        $walkResult = null;
        if ($run) {
            $walkResult = MibRepository::walkOid($walkOid, $hostConnection);
        }

        $response = new CControllerResponseData([
            'title' => LanguageManager::t('Zabbix Walk'),
            'groupid' => $groupid,
            'hostid' => $hostid,
            'host_groups' => $hostGroups,
            'hosts' => $hosts,
            'host_connection' => $hostConnection,
            'walk_oid' => $walkOid,
            'walk_result' => $walkResult
        ]);

        $response->setTitle(LanguageManager::t('Zabbix Walk'));
        $this->setResponse($response);
    }

    private function getHostGroups(): array {
        try {
            $groups = API::HostGroup()->get([
                'output' => ['groupid', 'name'],
                'with_monitored_hosts' => true,
                'sortfield' => 'name',
                'sortorder' => 'ASC'
            ]);

            return is_array($groups) ? $groups : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function getHostsByGroup(string $groupid): array {
        if ($groupid === '') {
            return [];
        }

        try {
            $hosts = API::Host()->get([
                'output' => ['hostid', 'name', 'host'],
                'groupids' => [$groupid],
                'sortfield' => 'name',
                'sortorder' => 'ASC'
            ]);

            if (!is_array($hosts)) {
                return [];
            }

            $hostids = [];
            foreach ($hosts as $host) {
                if (isset($host['hostid']) && $host['hostid'] !== '') {
                    $hostids[] = (string) $host['hostid'];
                }
            }

            if (empty($hostids)) {
                return [];
            }

            $interfaces = API::HostInterface()->get([
                'output' => ['hostid'],
                'hostids' => $hostids,
                'filter' => ['type' => 2]
            ]);

            if (!is_array($interfaces) || empty($interfaces)) {
                return [];
            }

            $snmpHostids = [];
            foreach ($interfaces as $iface) {
                if (isset($iface['hostid'])) {
                    $snmpHostids[(string) $iface['hostid']] = true;
                }
            }

            return array_values(array_filter($hosts, static function (array $host) use ($snmpHostids): bool {
                return isset($snmpHostids[(string) ($host['hostid'] ?? '')]);
            }));
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function getHostSnmpConnection(string $hostid): array {
        if ($hostid === '') {
            return [];
        }

        try {
            $hosts = API::Host()->get([
                'output' => ['hostid', 'name', 'host'],
                'hostids' => [$hostid],
                'selectInterfaces' => ['interfaceid', 'type', 'main', 'ip', 'dns', 'port', 'details'],
                'limit' => 1
            ]);
        } catch (\Throwable $e) {
            return [];
        }

        if (empty($hosts) || !isset($hosts[0])) {
            return [];
        }

        $host = $hosts[0];
        $interfaces = $host['interfaces'] ?? [];
        $snmpInterface = null;

        foreach ($interfaces as $interface) {
            if ((int) ($interface['type'] ?? -1) === 2 && (int) ($interface['main'] ?? 0) === 1) {
                $snmpInterface = $interface;
                break;
            }
        }

        if ($snmpInterface === null) {
            foreach ($interfaces as $interface) {
                if ((int) ($interface['type'] ?? -1) === 2) {
                    $snmpInterface = $interface;
                    break;
                }
            }
        }

        if ($snmpInterface === null) {
            return [];
        }

        $details = is_array($snmpInterface['details'] ?? null) ? $snmpInterface['details'] : [];

        return [
            'host_name' => $host['name'] ?? ($host['host'] ?? ''),
            'address' => ($snmpInterface['ip'] ?? '') !== '' ? $snmpInterface['ip'] : ($snmpInterface['dns'] ?? ''),
            'port' => (string) ($snmpInterface['port'] ?? '161'),
            'version' => $this->mapVersion((string) ($details['version'] ?? '2')),
            'community' => $this->resolveMacros((string) ($details['community'] ?? ''), $hostid),
            'securityname' => $this->resolveMacros((string) ($details['securityname'] ?? ''), $hostid),
            'securitylevel' => (string) ($details['securitylevel'] ?? 'noAuthNoPriv'),
            'authprotocol' => (string) ($details['authprotocol'] ?? 'SHA'),
            'authpassphrase' => $this->resolveMacros((string) ($details['authpassphrase'] ?? ''), $hostid),
            'privprotocol' => (string) ($details['privprotocol'] ?? 'AES'),
            'privpassphrase' => $this->resolveMacros((string) ($details['privpassphrase'] ?? ''), $hostid),
            'contextname' => $this->resolveMacros((string) ($details['contextname'] ?? ''), $hostid)
        ];
    }

    private function resolveMacros(string $value, string $hostid): string {
        if (!preg_match('/\{\$[^}]+\}/', $value)) {
            return $value;
        }

        try {
            $macroMap = [];

            $globalMacros = API::UserMacro()->get([
                'output' => ['macro', 'value'],
                'globalmacro' => true
            ]);
            if (is_array($globalMacros)) {
                foreach ($globalMacros as $macro) {
                    $macroMap[$macro['macro']] = $macro['value'];
                }
            }

            $hostMacros = API::UserMacro()->get([
                'output' => ['macro', 'value'],
                'hostids' => [$hostid]
            ]);
            if (is_array($hostMacros)) {
                foreach ($hostMacros as $macro) {
                    $macroMap[$macro['macro']] = $macro['value'];
                }
            }

            foreach ($macroMap as $macro => $macroValue) {
                $value = str_replace($macro, $macroValue, $value);
            }
        } catch (\Throwable $e) {
        }

        return $value;
    }

    private function mapVersion(string $version): string {
        $version = trim($version);

        if ($version === '1') {
            return '1';
        }

        if ($version === '3') {
            return '3';
        }

        return '2c';
    }
}
