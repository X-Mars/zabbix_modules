<?php

namespace Modules\ZabbixLldp\Lib;

class ConfigManager {

    private static $config = null;

    public static function getConfigPath(): string {
        return dirname(__DIR__) . '/data/config.json';
    }

    public static function load(): array {
        if (self::$config !== null) {
            return self::$config;
        }

        $defaults = [
            'template_name' => 'Lldp Discovery',
            'item_key_prefix' => 'lldp.remote.info',
            'discovery_key' => 'lldp_discovery',
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

    public static function getTemplateName(): string {
        $config = self::load();
        return trim((string) ($config['template_name'] ?? 'Lldp Discovery'));
    }

    public static function getItemKeyPrefix(): string {
        $config = self::load();
        return trim((string) ($config['item_key_prefix'] ?? 'lldp.remote.info'));
    }
}
