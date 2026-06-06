<?php

namespace Modules\ZabbixSnmp\Actions;

use CController;
use API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/MibRepository.php';

use Modules\ZabbixSnmp\Lib\LanguageManager;
use Modules\ZabbixSnmp\Lib\MibRepository;

class SnmpItemCreate extends CController {

    private const ITEM_TYPE_SNMP_AGENT = 20;

    public function init(): void {
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation();
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation();
        }
    }

    protected function checkInput(): bool {
        return $this->validateInput([
            'hostid' => 'string',
            'oid' => 'string',
            'name' => 'string',
            'value' => 'string',
            'data_type' => 'string'
        ]);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
    }

    protected function doAction(): void {
        $hostid = trim((string) $this->getInput('hostid', ''));
        $oid = ltrim(trim((string) $this->getInput('oid', '')), '.');
        $name = trim((string) $this->getInput('name', ''));
        $value = (string) $this->getInput('value', '');
        $dataType = trim((string) $this->getInput('data_type', ''));

        if ($hostid === '') {
            $this->respond(false, LanguageManager::t('Please select a host first.'));
            return;
        }

        if ($oid === '' || $oid === '-') {
            $this->respond(false, LanguageManager::t('OID is empty'));
            return;
        }

        $numericOid = MibRepository::translateOidToNumeric($oid);
        if ($numericOid !== null) {
            $oid = $numericOid;
        }

        $interfaceid = $this->getSnmpInterfaceId($hostid);
        if ($interfaceid === '') {
            $this->respond(false, LanguageManager::t('No SNMP interface found on this host.'));
            return;
        }

        if ($name === '') {
            $name = 'SNMP ' . $oid;
        }

        $key = $this->buildUniqueKey($hostid, $oid);
        if ($key === '') {
            $this->respond(false, LanguageManager::t('Failed to create monitoring item.'));
            return;
        }

        $item = [
            'hostid' => $hostid,
            'name' => $name,
            'type' => self::ITEM_TYPE_SNMP_AGENT,
            'key_' => $key,
            'snmp_oid' => $oid,
            'value_type' => MibRepository::mapSnmpTypeToZabbixValueType($dataType, $value),
            'interfaceid' => $interfaceid,
            'delay' => '1m'
        ];

        try {
            $result = API::Item()->create($item);
        } catch (\Throwable $e) {
            $this->respond(false, LanguageManager::t('Failed to create monitoring item.') . ' ' . $e->getMessage());
            return;
        }

        if (empty($result['itemids'])) {
            $this->respond(false, LanguageManager::t('Failed to create monitoring item.'));
            return;
        }

        $this->respond(true, LanguageManager::t('Monitoring item created.') . ' (' . $key . ')');
    }

    private function getSnmpInterfaceId(string $hostid): string {
        try {
            $hosts = API::Host()->get([
                'output' => ['hostid'],
                'hostids' => [$hostid],
                'selectInterfaces' => ['interfaceid', 'type', 'main'],
                'limit' => 1
            ]);
        } catch (\Throwable $e) {
            return '';
        }

        if (empty($hosts[0]['interfaces'])) {
            return '';
        }

        $interfaces = $hosts[0]['interfaces'];
        foreach ($interfaces as $interface) {
            if ((int) ($interface['type'] ?? -1) === 2 && (int) ($interface['main'] ?? 0) === 1) {
                return (string) ($interface['interfaceid'] ?? '');
            }
        }

        foreach ($interfaces as $interface) {
            if ((int) ($interface['type'] ?? -1) === 2) {
                return (string) ($interface['interfaceid'] ?? '');
            }
        }

        return '';
    }

    private function buildUniqueKey(string $hostid, string $oid): string {
        $base = 'snmp.get[' . $oid . ']';

        $existing = $this->getExistingKeys($hostid);
        if (!isset($existing[$base])) {
            return $base;
        }

        for ($i = 2; $i <= 1000; $i++) {
            $candidate = 'snmp.get[' . $oid . ',' . $i . ']';
            if (!isset($existing[$candidate])) {
                return $candidate;
            }
        }

        return '';
    }

    private function getExistingKeys(string $hostid): array {
        try {
            $items = API::Item()->get([
                'output' => ['key_'],
                'hostids' => [$hostid]
            ]);
        } catch (\Throwable $e) {
            return [];
        }

        $keys = [];
        if (is_array($items)) {
            foreach ($items as $item) {
                if (isset($item['key_'])) {
                    $keys[(string) $item['key_']] = true;
                }
            }
        }

        return $keys;
    }

    private function respond(bool $ok, string $message): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
