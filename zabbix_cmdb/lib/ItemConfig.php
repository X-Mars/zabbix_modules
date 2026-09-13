<?php
namespace Modules\ZabbixCmdb\Lib;

require_once __DIR__.'/LanguageManager.php';

class ItemConfig {
    const LABELS = [
        'cpu_count' => 'CPU Total', 'cpu_usage' => 'CPU Usage',
        'memory_total' => 'Memory Total', 'memory_usage' => 'Memory Usage'
    ];
    private static $cache;

    public static function path(): string {
        return dirname(__DIR__).'/data/item_rules.json';
    }

    public static function load(): array {
        if (self::$cache === null) {
            $raw = @file_get_contents(self::path());
            $data = $raw === false ? null : json_decode($raw, true);
            if (!is_array($data)) {
                throw new \RuntimeException(LanguageManager::t('Cannot read CMDB data/item_rules.json, or the JSON is invalid.'));
            }
            self::validate($data, array_keys(self::LABELS));
            self::validate($data, array_keys($data));
            self::$cache = $data;
        }
        return self::$cache;
    }

    public static function validate(array $data, array $categories): void {
        foreach ($categories as $category) {
            if (!isset($data[$category]) || !is_array($data[$category]) || count($data[$category]) > 100) {
                throw new \InvalidArgumentException(LanguageManager::t('Each metric must have a rule list with at most 100 rules.'));
            }
            foreach ($data[$category] as $rule) {
                if (!is_array($rule)
                        || !in_array($rule['field'] ?? '', ['name', 'key_'], true)
                        || !in_array($rule['match'] ?? '', ['exact', 'fuzzy'], true)
                        || !is_string($rule['pattern'] ?? null)
                        || trim($rule['pattern']) === '' || strlen($rule['pattern']) > 255
                        || !in_array($rule['transform'] ?? '', ['none', 'subtract_from_100'], true)
                        || (!in_array($category, ['cpu_usage', 'memory_usage'], true)
                            && $rule['transform'] !== 'none')) {
                    throw new \InvalidArgumentException(LanguageManager::t('Invalid rule: enter an item name or key of 1–255 bytes and select valid matching and conversion options.'));
                }
            }
        }
    }

    public static function save(array $changes): void {
        self::validate($changes, array_keys(self::LABELS));
        $data = self::load();
        foreach (self::LABELS as $category => $label) {
            $data[$category] = array_values($changes[$category]);
        }
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
        $tmp = @tempnam(dirname(self::path()), '.item_rules_');
        if ($tmp === false) {
            throw new \RuntimeException(LanguageManager::t('Cannot save: grant the web service user write access to the CMDB data directory.'));
        }
        try {
            if (file_put_contents($tmp, $json, LOCK_EX) !== strlen($json)
                    || !chmod($tmp, 0640) || !rename($tmp, self::path())) {
                throw new \RuntimeException(LanguageManager::t('Cannot save: check write permissions on the CMDB data directory.'));
            }
            self::$cache = $data;
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }
}
