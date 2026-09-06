<?php
namespace Modules\ZabbixCmdb\Lib;

class ItemConfig {
    const LABELS = [
        'cpu_count' => 'CPU 总量', 'cpu_usage' => 'CPU 使用率',
        'memory_total' => '内存总量', 'memory_usage' => '内存使用率'
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
                throw new \RuntimeException('无法读取 CMDB data/item_rules.json，或 JSON 格式无效。');
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
                throw new \InvalidArgumentException('每个指标必须提供规则列表，最多 100 条。');
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
                    throw new \InvalidArgumentException('规则无效：请填写 1–255 字节的名称或 Key，并选择有效的匹配方式和转换方式。');
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
            throw new \RuntimeException('保存失败：请授予 Web 服务用户 CMDB data 目录写入权限。');
        }
        try {
            if (file_put_contents($tmp, $json, LOCK_EX) !== strlen($json)
                    || !chmod($tmp, 0640) || !rename($tmp, self::path())) {
                throw new \RuntimeException('保存失败：请检查 CMDB data 目录写入权限。');
            }
            self::$cache = $data;
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }
}
