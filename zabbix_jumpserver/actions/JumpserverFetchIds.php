<?php

namespace Modules\ZabbixJumpserver\Actions;

use CController,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ConfigManager.php';
require_once dirname(__DIR__) . '/lib/JumpserverClient.php';

use Modules\ZabbixJumpserver\Lib\LanguageManager;
use Modules\ZabbixJumpserver\Lib\ConfigManager;
use Modules\ZabbixJumpserver\Lib\JumpserverClient;

/**
 * 从 JumpServer 拉取所有资产，按 IP 匹配 Zabbix 本地主机，
 * 并把匹配到的资产 ID 以标记形式写回对应主机。
 */
class JumpserverFetchIds extends CController {

    public function init(): void {
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation();
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation();
        }
    }

    protected function checkInput(): bool {
        return true;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
    }

    protected function doAction(): void {
        if ($this->getUserType() < USER_TYPE_ZABBIX_ADMIN) {
            $this->respond(false, LanguageManager::t('No permission.'));
            return;
        }

        $config = ConfigManager::load();
        if (!ConfigManager::isConfigured()) {
            $this->respond(false, LanguageManager::t('JumpServer is not configured. Please edit data/config.json.'));
            return;
        }

        $client = new JumpserverClient($config);

        try {
            // JumpServer 资产：按 IP（address）建立映射
            $ipToAsset = [];
            foreach ($client->getHosts() as $asset) {
                $address = trim((string) ($asset['address'] ?? ''));
                $id = (string) ($asset['id'] ?? '');
                if ($address !== '' && $id !== '' && !isset($ipToAsset[$address])) {
                    $ipToAsset[$address] = $id;
                }
            }

            if (empty($ipToAsset)) {
                $this->respond(false, LanguageManager::t('No assets found in JumpServer.'));
                return;
            }

            $hosts = $this->getZabbixHosts();

            $matched = 0;
            $updated = 0;
            $skipped = 0;
            $failed = 0;

            $assetTag = ConfigManager::ASSET_TAG;

            foreach ($hosts as $host) {
                $ip = $this->resolveHostIp($host);
                if ($ip === '' || !isset($ipToAsset[$ip])) {
                    continue;
                }

                $matched++;
                $assetId = $ipToAsset[$ip];

                $currentId = '';
                foreach (($host['tags'] ?? []) as $tag) {
                    if (($tag['tag'] ?? '') === $assetTag) {
                        $currentId = (string) ($tag['value'] ?? '');
                        break;
                    }
                }

                if ($currentId === $assetId) {
                    $skipped++;
                    continue;
                }

                if ($this->writeBackAssetId($host, $assetId)) {
                    $updated++;
                } else {
                    $failed++;
                }
            }

            $this->respond(true, LanguageManager::t('Fetch completed'), [
                'type'    => 'fetch',
                'matched' => $matched,
                'updated' => $updated,
                'skipped' => $skipped,
                'failed'  => $failed,
            ]);
        } catch (\Throwable $e) {
            error_log('JumpServer fetch ids error: ' . $e->getMessage());
            $this->respond(false, LanguageManager::t('Fetch failed') . ': ' . $e->getMessage());
        }
    }

    private function getZabbixHosts(): array {
        try {
            $hosts = API::Host()->get([
                'output'           => ['hostid', 'host', 'name'],
                'selectInterfaces' => ['ip', 'dns', 'type', 'main'],
                'selectTags'       => ['tag', 'value'],
            ]);
        } catch (\Throwable $e) {
            return [];
        }

        return is_array($hosts) ? $hosts : [];
    }

    private function resolveHostIp(array $host): string {
        $interfaces = $host['interfaces'] ?? [];
        foreach ($interfaces as $iface) {
            if ((int) ($iface['main'] ?? 0) === 1) {
                $ip = ($iface['ip'] ?? '') !== '' ? (string) $iface['ip'] : (string) ($iface['dns'] ?? '');
                if ($ip !== '') {
                    return $ip;
                }
            }
        }
        if (!empty($interfaces)) {
            $first = $interfaces[0];
            return ($first['ip'] ?? '') !== '' ? (string) $first['ip'] : (string) ($first['dns'] ?? '');
        }
        return '';
    }

    /**
     * 将资产 ID 以标记形式写回 Zabbix 主机（保留已有其他标记）
     */
    private function writeBackAssetId(array $host, string $assetId): bool {
        $assetTag = ConfigManager::ASSET_TAG;
        $tags = [];
        foreach (($host['tags'] ?? []) as $tag) {
            if (($tag['tag'] ?? '') === $assetTag) {
                continue;
            }
            $tags[] = [
                'tag'   => (string) ($tag['tag'] ?? ''),
                'value' => (string) ($tag['value'] ?? ''),
            ];
        }
        $tags[] = ['tag' => $assetTag, 'value' => $assetId];

        try {
            API::Host()->update([
                'hostid' => (string) $host['hostid'],
                'tags'   => $tags,
            ]);
            return true;
        } catch (\Throwable $e) {
            error_log('JumpServer: write back asset id failed for host ' . $host['hostid'] . ': ' . $e->getMessage());
            return false;
        }
    }

    private function respond(bool $ok, string $message, array $summary = []): void {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'      => $ok,
            'message' => $message,
            'summary' => $summary,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
