<?php

namespace Modules\ZabbixSnmp\Actions;

use CController;
use CControllerResponseData;
use API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/MibRepository.php';

use Modules\ZabbixSnmp\Lib\LanguageManager;
use Modules\ZabbixSnmp\Lib\MibRepository;

class Snmp extends CController {

    public function init(): void {
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation();
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation();
        }
    }

    protected function checkInput(): bool {
        return $this->validateInput([
            'directory' => 'string',
            'file' => 'string',
            'search' => 'string',
            'source' => 'in 0,1',
            'symbol' => 'string',
            'conn_mode' => 'in host,manual',
            'groupid' => 'string',
            'hostid' => 'string',
            'manual_address' => 'string',
            'manual_port' => 'string',
            'manual_version' => 'in 1,2c,3',
            'manual_community' => 'string',
            'manual_securityname' => 'string',
            'manual_securitylevel' => 'in noAuthNoPriv,authNoPriv,authPriv',
            'manual_authprotocol' => 'in MD5,SHA,SHA-224,SHA-256,SHA-384,SHA-512',
            'manual_authpassphrase' => 'string',
            'manual_privprotocol' => 'in DES,AES',
            'manual_privpassphrase' => 'string',
            'manual_contextname' => 'string',
            'test' => 'in 0,1',
            'test_oid' => 'string',
            'test_symbol' => 'string'
        ]);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $requestedDirectory = trim((string) $this->getInput('directory', ''));
        $search = trim((string) $this->getInput('search', ''));
        $requestedFile = trim((string) $this->getInput('file', ''));
        $showSource = ((string) $this->getInput('source', '0') === '1');
        $symbol = trim((string) $this->getInput('symbol', ''));

        $connMode = 'host';
        $groupid = trim((string) $this->getInput('groupid', ''));
        $hostid = trim((string) $this->getInput('hostid', ''));
        $isTest = ((string) $this->getInput('test', '0') === '1');
        $testOid = trim((string) $this->getInput('test_oid', ''));
        $testSymbol = trim((string) $this->getInput('test_symbol', ''));

        $directories = MibRepository::getDirectories($search);
        $selectedDirectory = MibRepository::resolveSelectedDirectory($requestedDirectory, $directories);
        $files = MibRepository::getFilesInDirectory($selectedDirectory, $directories);
        $selectedFile = MibRepository::getFileDetails($requestedFile, $files, $showSource, $symbol);

        if ($selectedFile === null && !empty($files)) {
            $selectedFile = MibRepository::getFileDetails($files[0]['path'], $files, $showSource, $symbol);
        }

        $hostGroups = $this->getHostGroups();
        if ($groupid === '' && !empty($hostGroups)) {
            $groupid = (string) $hostGroups[0]['groupid'];
        }

        $hosts = $this->getHostsByGroup($groupid);
        if ($hostid === '' && !empty($hosts)) {
            $hostid = (string) $hosts[0]['hostid'];
        }

        $hostConnection = $this->getHostSnmpConnection($hostid);
        $manualConnection = [
            'address' => trim((string) $this->getInput('manual_address', '')),
            'port' => trim((string) $this->getInput('manual_port', '161')),
            'version' => trim((string) $this->getInput('manual_version', '2c')),
            'community' => trim((string) $this->getInput('manual_community', 'public')),
            'securityname' => trim((string) $this->getInput('manual_securityname', '')),
            'securitylevel' => trim((string) $this->getInput('manual_securitylevel', 'noAuthNoPriv')),
            'authprotocol' => trim((string) $this->getInput('manual_authprotocol', 'SHA')),
            'authpassphrase' => trim((string) $this->getInput('manual_authpassphrase', '')),
            'privprotocol' => trim((string) $this->getInput('manual_privprotocol', 'AES')),
            'privpassphrase' => trim((string) $this->getInput('manual_privpassphrase', '')),
            'contextname' => trim((string) $this->getInput('manual_contextname', ''))
        ];

        $activeConnection = $connMode === 'manual' ? $manualConnection : $hostConnection;

        $testResult = null;
        if ($isTest && $testOid !== '') {
            $resolvedTestOid = $testOid;
            if ($selectedFile !== null) {
                $resolved = MibRepository::resolveTestOid($testOid, $testSymbol, $selectedFile['snmp_objects'] ?? []);
                if ($resolved !== null) {
                    $resolvedTestOid = $resolved;
                }
            }

            $testResult = MibRepository::testOid($resolvedTestOid, is_array($activeConnection) ? $activeConnection : []);
            $testResult['symbol'] = $testSymbol;
            $testResult['oid'] = $testOid;
            $testResult['resolved_oid'] = $resolvedTestOid;
        }

        $response = new CControllerResponseData([
            'title' => LanguageManager::t('Zabbix Mibs'),
            'selected_directory' => $selectedDirectory,
            'search' => $search,
            'directories' => $directories,
            'files' => $files,
            'stats' => MibRepository::getStats($directories, $files),
            'selected_file' => $selectedFile,
            'selected_file_path' => $selectedFile['path'] ?? '',
            'selected_missing' => ($requestedFile !== '' && $selectedFile === null),
            'show_source' => $showSource,
            'symbol' => $symbol,
            'conn_mode' => $connMode,
            'groupid' => $groupid,
            'hostid' => $hostid,
            'host_groups' => $hostGroups,
            'hosts' => $hosts,
            'host_connection' => $hostConnection,
            'manual_connection' => $manualConnection,
            'test_result' => $testResult,
            'language' => LanguageManager::getCurrentLanguage(),
            'is_chinese' => LanguageManager::isChinese()
        ]);

        $response->setTitle(LanguageManager::t('Zabbix Mibs'));
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
        $version = $this->mapVersion((string) ($details['version'] ?? '2'));

        $community     = $this->resolveMacros((string) ($details['community'] ?? ''), $hostid);
        $securityname  = $this->resolveMacros((string) ($details['securityname'] ?? ''), $hostid);
        $authpassphrase = $this->resolveMacros((string) ($details['authpassphrase'] ?? ''), $hostid);
        $privpassphrase = $this->resolveMacros((string) ($details['privpassphrase'] ?? ''), $hostid);
        $contextname   = $this->resolveMacros((string) ($details['contextname'] ?? ''), $hostid);

        return [
            'host_name' => $host['name'] ?? ($host['host'] ?? ''),
            'address' => ($snmpInterface['ip'] ?? '') !== '' ? $snmpInterface['ip'] : ($snmpInterface['dns'] ?? ''),
            'port' => (string) ($snmpInterface['port'] ?? '161'),
            'version' => $version,
            'community' => $community,
            'securityname' => $securityname,
            'securitylevel' => (string) ($details['securitylevel'] ?? 'noAuthNoPriv'),
            'authprotocol' => (string) ($details['authprotocol'] ?? 'SHA'),
            'authpassphrase' => $authpassphrase,
            'privprotocol' => (string) ($details['privprotocol'] ?? 'AES'),
            'privpassphrase' => $privpassphrase,
            'contextname' => $contextname
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
                foreach ($globalMacros as $m) {
                    $macroMap[$m['macro']] = $m['value'];
                }
            }

            $hostMacros = API::UserMacro()->get([
                'output' => ['macro', 'value'],
                'hostids' => [$hostid]
            ]);
            if (is_array($hostMacros)) {
                foreach ($hostMacros as $m) {
                    $macroMap[$m['macro']] = $m['value'];
                }
            }

            foreach ($macroMap as $macro => $macroValue) {
                $value = str_replace($macro, $macroValue, $value);
            }
        } catch (\Throwable $e) {
            // Return original value if resolution fails
        }

        return $value;
    }

    private function mapVersion(string $version): string {
        if ($version === '1' || $version === '2' || strtolower($version) === 'v2c' || $version === '2c') {
            return $version === '1' ? '1' : '2c';
        }

        if ($version === '3' || strtolower($version) === 'v3') {
            return '3';
        }

        return '2c';
    }
}