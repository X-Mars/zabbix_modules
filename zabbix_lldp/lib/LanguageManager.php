<?php

namespace Modules\ZabbixLldp\Lib;

class LanguageManager {

    private const LANG_DEFAULT = 'default';
    private const ZBX_DEFAULT_LANG = 'en_US';

    private static $currentLanguage = null;
    private static $translations = [
        'zh_CN' => [
            'LLDP Topology' => 'LLDP 拓扑',
            'Select host group' => '选择主机分组',
            'Select host' => '选择主机',
            'All Groups' => '所有分组',
            'All Hosts' => '所有主机',
            'Query' => '查询',
            'Querying...' => '查询中...',
            'Host Group' => '主机分组',
            'Host' => '主机',
            'Local device' => '本地设备',
            'Remote device' => '对端设备',
            'Local port' => '本地端口',
            'Remote port' => '对端端口',
            'Last update' => '更新时间',
            'Links' => '链路',
            'Devices' => '设备',
            'Hosts' => '主机',
            'Stats format' => '%d 主机 / %d 链路 / %d 设备',
            'Click query to load topology' => '请选择条件并点击查询以加载拓扑',
            'No LLDP data found' => '未找到 LLDP 数据',
            'No hosts with LLDP template' => '未找到关联 LLDP 模板的主机',
            'Template not found' => '未找到 LLDP 模板，请检查 data/config.json 中的 template_name',
            'Invalid input parameters.' => '无效的输入参数。',
            'Query failed' => '查询失败',
            'No permission.' => '没有权限。',
            'Local' => '本地',
            'Remote' => '对端',
            'Skipped items' => '跳过项',
            'Zoom in' => '放大',
            'Show %d more devices' => '展开 %d 台设备',
            'Collapse branch' => '收起分支',
            'Hidden device alert' => '隐藏设备有告警',
            'Visible devices: %d' => '已显示 %d 台设备',
            'Zoom out' => '缩小',
            'Details' => '详情',
            'Click a node or link to view details' => '点击节点或链路查看详情',
            'Node type' => '节点类型',
            'Connected links' => '关联链路',
            'Link details' => '链路详情',
            'Link filter' => '链路筛选',
            'Filter links by device or port' => '按设备名或端口筛选链路',
            'Clear filter' => '清空筛选',
            'Matched links' => '命中链路：%d',
            'Host name' => '主机名',
            'Problem count' => '告警数量',
            'No active problem' => '无活动告警',
            'Problem details' => '告警详情',
            'Severity: Not classified' => '严重性: 未分类',
            'Severity: Information' => '严重性: 信息',
            'Severity: Warning' => '严重性: 警告',
            'Severity: Average' => '严重性: 一般严重',
            'Severity: High' => '严重性: 严重',
            'Severity: Disaster' => '严重性: 灾难',
        ],
        'en_US' => [
            'LLDP Topology' => 'LLDP Topology',
            'Select host group' => 'Select host group',
            'Select host' => 'Select host',
            'All Groups' => 'All Groups',
            'All Hosts' => 'All Hosts',
            'Query' => 'Query',
            'Querying...' => 'Querying...',
            'Host Group' => 'Host Group',
            'Host' => 'Host',
            'Local device' => 'Local device',
            'Remote device' => 'Remote device',
            'Local port' => 'Local port',
            'Remote port' => 'Remote port',
            'Last update' => 'Last update',
            'Links' => 'Links',
            'Devices' => 'Devices',
            'Hosts' => 'Hosts',
            'Stats format' => '%d hosts / %d links / %d devices',
            'Click query to load topology' => 'Select filters and click Query to load topology',
            'No LLDP data found' => 'No LLDP data found',
            'No hosts with LLDP template' => 'No hosts linked to the LLDP template',
            'Template not found' => 'LLDP template not found. Check template_name in data/config.json',
            'Invalid input parameters.' => 'Invalid input parameters.',
            'Query failed' => 'Query failed',
            'No permission.' => 'No permission.',
            'Local' => 'Local',
            'Remote' => 'Remote',
            'Skipped items' => 'Skipped items',
            'Zoom in' => 'Zoom in',
            'Zoom out' => 'Zoom out',
            'Details' => 'Details',
            'Click a node or link to view details' => 'Click a node or link to view details',
            'Node type' => 'Node type',
            'Connected links' => 'Connected links',
            'Link details' => 'Link details',
            'Link filter' => 'Link filter',
            'Filter links by device or port' => 'Filter links by device or port',
            'Clear filter' => 'Clear filter',
            'Matched links' => 'Matched links: %d',
            'Host name' => 'Host name',
            'Problem count' => 'Problem count',
            'No active problem' => 'No active problem',
            'Problem details' => 'Problem details',
            'Severity: Not classified' => 'Severity: Not classified',
            'Severity: Information' => 'Severity: Information',
            'Severity: Warning' => 'Severity: Warning',
            'Severity: Average' => 'Severity: Average',
            'Severity: High' => 'Severity: High',
            'Severity: Disaster' => 'Severity: Disaster',
        ],
    ];

    public static function detectLanguage() {
        if (self::$currentLanguage !== null) {
            return self::$currentLanguage;
        }

        $userLang = self::getUserLanguageFromZabbix();
        if (!empty($userLang)) {
            $mapped = self::mapZabbixLangToOurs($userLang);
            if ($mapped === self::LANG_DEFAULT) {
                $sys = self::getSystemDefaultLanguage();
                self::$currentLanguage = self::ensureSupportedOrFallback($sys);
                return self::$currentLanguage;
            }
            self::$currentLanguage = self::ensureSupportedOrFallback($mapped);
            return self::$currentLanguage;
        }

        $sys = self::getSystemDefaultLanguage();
        if (!empty($sys)) {
            self::$currentLanguage = self::ensureSupportedOrFallback($sys);
            return self::$currentLanguage;
        }

        self::$currentLanguage = self::ensureSupportedOrFallback(self::ZBX_DEFAULT_LANG);
        return self::$currentLanguage;
    }

    private static function getUserLanguageFromZabbix() {
        try {
            if (class_exists('CWebUser') || class_exists('\\CWebUser')) {
                if (method_exists('CWebUser', 'get')) {
                    $lang = \CWebUser::get('lang');
                    if (!empty($lang)) {
                        return $lang;
                    }
                }
                if (isset(\CWebUser::$data) && is_array(\CWebUser::$data) && !empty(\CWebUser::$data['lang'])) {
                    return \CWebUser::$data['lang'];
                }
            }
        } catch (\Throwable $e) {
        }

        if (isset($_SESSION['zbx_lang']) && !empty($_SESSION['zbx_lang'])) {
            return $_SESSION['zbx_lang'];
        }
        if (isset($_SESSION['lang']) && !empty($_SESSION['lang'])) {
            return $_SESSION['lang'];
        }

        return null;
    }

    private static function getSystemDefaultLanguage() {
        try {
            if (class_exists('CSettingsHelper') && method_exists('CSettingsHelper', 'get')) {
                $val = \CSettingsHelper::get('default_lang');
                if (!empty($val)) {
                    return $val;
                }
            }
        } catch (\Throwable $e) {
        }

        return self::ZBX_DEFAULT_LANG;
    }

    private static function mapZabbixLangToOurs($zabbixLang) {
        $lowerLang = strtolower(trim((string) $zabbixLang));
        $langMap = [
            'zh_cn' => 'zh_CN', 'zh-cn' => 'zh_CN', 'zh' => 'zh_CN',
            'en_us' => 'en_US', 'en-us' => 'en_US', 'en' => 'en_US',
            'default' => self::LANG_DEFAULT,
        ];

        if (isset($langMap[$lowerLang])) {
            return $langMap[$lowerLang];
        }
        if (strpos($lowerLang, 'zh') === 0) {
            return 'zh_CN';
        }
        if (strpos($lowerLang, 'en') === 0) {
            return 'en_US';
        }

        return self::ZBX_DEFAULT_LANG;
    }

    private static function ensureSupportedOrFallback($lang) {
        $mapped = self::mapZabbixLangToOurs($lang);
        return isset(self::$translations[$mapped]) ? $mapped : self::ZBX_DEFAULT_LANG;
    }

    public static function t($key) {
        $lang = self::detectLanguage();
        if (isset(self::$translations[$lang][$key])) {
            return self::$translations[$lang][$key];
        }
        if ($lang !== 'en_US' && isset(self::$translations['en_US'][$key])) {
            return self::$translations['en_US'][$key];
        }
        return $key;
    }

    public static function tf($key, ...$args) {
        return sprintf(self::t($key), ...$args);
    }
}
