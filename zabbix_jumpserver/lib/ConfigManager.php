<?php

namespace Modules\ZabbixJumpserver\Lib;

/**
 * 读取模块 data/config.json 中保存的 JumpServer 连接凭据
 */
class ConfigManager {

    /** 写回 Zabbix 主机的资产 ID 标记名 */
    public const ASSET_TAG = 'jumpserver_asset_id';

    private static $config = null;

    public static function getConfigPath(): string {
        return dirname(__DIR__) . '/data/config.json';
    }

    public static function load(): array {
        if (self::$config !== null) {
            return self::$config;
        }

        $defaults = [
            'jumpserver_url' => '',
            'access_key_id' => '',
            'access_key_secret' => '',
            'org_id' => '00000000-0000-0000-0000-000000000002',
            'connect_url_template' => '{base_url}/luna/connect?asset={asset_id}',
            'account_template_id' => '',
            'verify_ssl' => false,
        ];

        $path = self::getConfigPath();
        if (!is_file($path) || !is_readable($path)) {
            self::$config = $defaults;
            return self::$config;
        }

        $raw = file_get_contents($path);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            self::$config = $defaults;
            return self::$config;
        }

        self::$config = array_merge($defaults, $decoded);
        return self::$config;
    }

    public static function isConfigured(): bool {
        $config = self::load();
        return trim((string) $config['jumpserver_url']) !== ''
            && trim((string) $config['access_key_id']) !== ''
            && trim((string) $config['access_key_secret']) !== '';
    }

    public static function getBaseUrl(): string {
        $config = self::load();
        return rtrim(trim((string) $config['jumpserver_url']), '/');
    }

    /**
     * 获取账号模板 ID（留空表示不自动关联账号）
     */
    public static function getAccountTemplateId(): string {
        $config = self::load();
        return trim((string) ($config['account_template_id'] ?? ''));
    }

    /**
     * 根据模板和资产 ID 生成 JumpServer 连接地址
     */
    public static function buildConnectUrl(string $assetId): string {
        $config = self::load();
        $template = (string) ($config['connect_url_template'] ?? '{base_url}/luna/connect?asset={asset_id}');
        return strtr($template, [
            '{base_url}' => self::getBaseUrl(),
            '{asset_id}' => $assetId,
        ]);
    }
}
