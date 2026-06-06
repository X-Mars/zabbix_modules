<?php

namespace Modules\ZabbixSnmp\Actions;

use CController;
use API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/MibRepository.php';

use Modules\ZabbixSnmp\Lib\LanguageManager;
use Modules\ZabbixSnmp\Lib\MibRepository;

class SnmpTemplateCreate extends CController {

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
            'name' => 'string',
            'group' => 'string',
            'items' => 'string'
        ]);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
    }

    protected function doAction(): void {
        $name = trim((string) $this->getInput('name', ''));
        $group = trim((string) $this->getInput('group', ''));
        $itemsRaw = (string) $this->getInput('items', '');

        if ($name === '') {
            $this->respond(false, LanguageManager::t('Please enter a template name.'));
            return;
        }

        if (!$this->isValidTemplateName($name)) {
            $this->respond(false, LanguageManager::t('Invalid template name.'));
            return;
        }

        if ($group === '') {
            $this->respond(false, LanguageManager::t('Please enter a template group.'));
            return;
        }

        $items = json_decode($itemsRaw, true);
        if (!is_array($items) || empty($items)) {
            $this->respond(false, LanguageManager::t('No items selected.'));
            return;
        }

        $itemsToCreate = $this->buildItems($items);
        if (empty($itemsToCreate)) {
            $this->respond(false, LanguageManager::t('No items selected.'));
            return;
        }

        if ($this->templateExists($name)) {
            $this->respond(false, LanguageManager::t('Failed to create template.') . ' (' . $name . ')');
            return;
        }

        $groupid = $this->resolveTemplateGroupId($group);
        if ($groupid === '') {
            $this->respond(false, LanguageManager::t('Failed to create template.'));
            return;
        }

        try {
            $created = API::Template()->create([
                'host' => $name,
                'groups' => [['groupid' => $groupid]]
            ]);
        } catch (\Throwable $e) {
            $this->respond(false, LanguageManager::t('Failed to create template.') . ' ' . $e->getMessage());
            return;
        }

        $templateid = (string) ($created['templateids'][0] ?? '');
        if ($templateid === '') {
            $this->respond(false, LanguageManager::t('Failed to create template.'));
            return;
        }

        foreach ($itemsToCreate as &$item) {
            $item['hostid'] = $templateid;
        }
        unset($item);

        try {
            $result = API::Item()->create($itemsToCreate);
        } catch (\Throwable $e) {
            $this->deleteTemplate($templateid);
            $this->respond(false, LanguageManager::t('Failed to create template.') . ' ' . $e->getMessage());
            return;
        }

        if (empty($result['itemids'])) {
            $this->deleteTemplate($templateid);
            $this->respond(false, LanguageManager::t('Failed to create template.'));
            return;
        }

        $count = count($result['itemids']);
        $this->respond(true, LanguageManager::t('Template created.') . ' ' . $name . ' (' . $count . ')');
    }

    private function buildItems(array $items): array {
        $built = [];
        $usedKeys = [];

        foreach ($items as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $oid = ltrim(trim((string) ($entry['oid'] ?? '')), '.');
            if ($oid === '' || $oid === '-') {
                continue;
            }

            $numericOid = MibRepository::translateOidToNumeric($oid);
            if ($numericOid !== null) {
                $oid = $numericOid;
            }

            $name = trim((string) ($entry['name'] ?? ''));
            if ($name === '') {
                $name = 'SNMP ' . $oid;
            }

            $value = (string) ($entry['value'] ?? '');
            $dataType = trim((string) ($entry['data_type'] ?? ''));

            $key = $this->buildUniqueKey($oid, $usedKeys);
            if ($key === '') {
                continue;
            }
            $usedKeys[$key] = true;

            $built[] = [
                'name' => $name,
                'type' => self::ITEM_TYPE_SNMP_AGENT,
                'key_' => $key,
                'snmp_oid' => $oid,
                'value_type' => MibRepository::mapSnmpTypeToZabbixValueType($dataType, $value),
                'delay' => '1m'
            ];
        }

        return $built;
    }

    private function buildUniqueKey(string $oid, array $usedKeys): string {
        $base = 'snmp.get[' . $oid . ']';
        if (!isset($usedKeys[$base])) {
            return $base;
        }

        for ($i = 2; $i <= 1000; $i++) {
            $candidate = 'snmp.get[' . $oid . ',' . $i . ']';
            if (!isset($usedKeys[$candidate])) {
                return $candidate;
            }
        }

        return '';
    }

    private function isValidTemplateName(string $name): bool {
        return (bool) preg_match('/^[a-zA-Z0-9._-]+$/', $name);
    }

    private function templateExists(string $name): bool {
        try {
            $existing = API::Template()->get([
                'output' => ['templateid'],
                'filter' => ['host' => [$name]],
                'limit' => 1
            ]);

            return !empty($existing);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function resolveTemplateGroupId(string $name): string {
        try {
            $existing = API::TemplateGroup()->get([
                'output' => ['groupid'],
                'filter' => ['name' => [$name]],
                'limit' => 1
            ]);
            if (!empty($existing[0]['groupid'])) {
                return (string) $existing[0]['groupid'];
            }

            $created = API::TemplateGroup()->create(['name' => $name]);
            if (!empty($created['groupids'][0])) {
                return (string) $created['groupids'][0];
            }
        } catch (\Throwable $e) {
            // Older Zabbix without TemplateGroup API: fall back to host groups.
        }

        try {
            $existing = API::HostGroup()->get([
                'output' => ['groupid'],
                'filter' => ['name' => [$name]],
                'limit' => 1
            ]);
            if (!empty($existing[0]['groupid'])) {
                return (string) $existing[0]['groupid'];
            }

            $created = API::HostGroup()->create(['name' => $name]);
            if (!empty($created['groupids'][0])) {
                return (string) $created['groupids'][0];
            }
        } catch (\Throwable $e) {
        }

        return '';
    }

    private function deleteTemplate(string $templateid): void {
        try {
            API::Template()->delete([$templateid]);
        } catch (\Throwable $e) {
        }
    }

    private function respond(bool $ok, string $message): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
