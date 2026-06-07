<?php

namespace Modules\ZabbixJumpserver\Lib;

class LanguageManager {

    private const LANG_DEFAULT = 'default';
    private const ZBX_DEFAULT_LANG = 'en_US';

    private static $currentLanguage = null;
    private static $translations = [
        'zh_CN' => [
            'JumpServer' => 'JumpServer',
            'JumpServer Sync' => 'JumpServer 同步',
            'Select host group' => '选择主机分组',
            'Select host' => '选择主机',
            'All Groups' => '所有分组',
            'All Hosts' => '所有主机',
            'Push all host groups' => '推送所有主机组',
            'Push all hosts' => '推送所有主机',
            'Push' => '推送',
            'Connect' => '连接',
            'Not pushed' => '未推送',
            'Pushed' => '已推送',
            'Re-push' => '重新推送',
            'Host Name' => '主机名',
            'IP Address' => 'IP地址',
            'Platform' => '平台',
            'Host Group' => '主机分组',
            'JumpServer Status' => 'JumpServer 状态',
            'Action' => '操作',
            'No hosts found' => '未找到主机',
            'Total Hosts' => '主机总数',
            'Host Groups' => '主机分组',
            'Pushed Hosts' => '已推送主机',
            'Host List' => '主机列表',
            'Invalid input parameters.' => '无效的输入参数。',
            'JumpServer is not configured. Please edit data/config.json.' => 'JumpServer 未配置，请编辑 data/config.json。',
            'Pushing...' => '推送中...',
            'Push completed' => '推送完成',
            'Push failed' => '推送失败',
            'Created' => '创建',
            'Updated' => '更新',
            'Failed' => '失败',
            'Accounts linked' => '账号关联',
            'No permission.' => '没有权限。',
            'Confirm push all host groups to JumpServer?' => '确认推送所有主机组到 JumpServer 吗？',
            'Confirm push all hosts to JumpServer?' => '确认推送所有主机到 JumpServer 吗？',
            'Configuration error' => '配置错误',
            'hosts' => '个主机',
            'groups' => '个分组',
            'Alarm Status' => '告警状态',
            'All States' => '所有状态',
            'Search' => '搜索',
            'Search by IP or host name' => '按 IP 或主机名搜索',
            'OK' => '正常',
            'Not classified' => '未分类',
            'Information' => '信息',
            'Warning' => '警告',
            'Average' => '一般严重',
            'High' => '严重',
            'Disaster' => '灾难',
            'Fetch JumpServer asset IDs' => '获取 JumpServer 资产 ID',
            'Fetch all assets from JumpServer and match by IP to update host tags?' => '从 JumpServer 获取所有资产并按 IP 匹配，更新主机标记吗？',
            'Fetching...' => '获取中...',
            'Fetch completed' => '获取完成',
            'Fetch failed' => '获取失败',
            'No assets found in JumpServer.' => 'JumpServer 中没有资产。',
            'Matched' => '匹配',
            'Skipped' => '跳过',
            'Show alarms' => '展开告警',
            'No active alarms' => '当前无告警',
            'Active alarms' => '当前告警',
            'Severity' => '严重度',
            'Problem' => '告警',
            'Time' => '时间',
        ],
        'en_US' => [
            'JumpServer' => 'JumpServer',
            'JumpServer Sync' => 'JumpServer Sync',
            'Select host group' => 'Select host group',
            'Select host' => 'Select host',
            'All Groups' => 'All Groups',
            'All Hosts' => 'All Hosts',
            'Push all host groups' => 'Push all host groups',
            'Push all hosts' => 'Push all hosts',
            'Push' => 'Push',
            'Connect' => 'Connect',
            'Not pushed' => 'Not pushed',
            'Pushed' => 'Pushed',
            'Re-push' => 'Re-push',
            'Host Name' => 'Host Name',
            'IP Address' => 'IP Address',
            'Platform' => 'Platform',
            'Host Group' => 'Host Group',
            'JumpServer Status' => 'JumpServer Status',
            'Action' => 'Action',
            'No hosts found' => 'No hosts found',
            'Total Hosts' => 'Total Hosts',
            'Host Groups' => 'Host Groups',
            'Pushed Hosts' => 'Pushed Hosts',
            'Host List' => 'Host List',
            'Invalid input parameters.' => 'Invalid input parameters.',
            'JumpServer is not configured. Please edit data/config.json.' => 'JumpServer is not configured. Please edit data/config.json.',
            'Pushing...' => 'Pushing...',
            'Push completed' => 'Push completed',
            'Push failed' => 'Push failed',
            'Created' => 'Created',
            'Updated' => 'Updated',
            'Failed' => 'Failed',
            'Accounts linked' => 'Accounts linked',
            'No permission.' => 'No permission.',
            'Confirm push all host groups to JumpServer?' => 'Confirm push all host groups to JumpServer?',
            'Confirm push all hosts to JumpServer?' => 'Confirm push all hosts to JumpServer?',
            'Configuration error' => 'Configuration error',
            'hosts' => 'hosts',
            'groups' => 'groups',
            'Alarm Status' => 'Alarm Status',
            'All States' => 'All States',
            'Search' => 'Search',
            'Search by IP or host name' => 'Search by IP or host name',
            'OK' => 'OK',
            'Not classified' => 'Not classified',
            'Information' => 'Information',
            'Warning' => 'Warning',
            'Average' => 'Average',
            'High' => 'High',
            'Disaster' => 'Disaster',
            'Fetch JumpServer asset IDs' => 'Fetch JumpServer asset IDs',
            'Fetch all assets from JumpServer and match by IP to update host tags?' => 'Fetch all assets from JumpServer and match by IP to update host tags?',
            'Fetching...' => 'Fetching...',
            'Fetch completed' => 'Fetch completed',
            'Fetch failed' => 'Fetch failed',
            'No assets found in JumpServer.' => 'No assets found in JumpServer.',
            'Matched' => 'Matched',
            'Skipped' => 'Skipped',
            'Show alarms' => 'Show alarms',
            'No active alarms' => 'No active alarms',
            'Active alarms' => 'Active alarms',
            'Severity' => 'Severity',
            'Problem' => 'Problem',
            'Time' => 'Time',
        ]
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
                if (isset(\CWebUser::$data) && is_array(\CWebUser::$data) && isset(\CWebUser::$data['lang']) && !empty(\CWebUser::$data['lang'])) {
                    return \CWebUser::$data['lang'];
                }
            }
        } catch (\Throwable $e) {
        }

        try {
            if (isset($GLOBALS['USER_DETAILS']) && isset($GLOBALS['USER_DETAILS']['lang'])) {
                return $GLOBALS['USER_DETAILS']['lang'];
            }
        } catch (\Throwable $e) {
        }

        if (isset($_SESSION['zbx_lang']) && !empty($_SESSION['zbx_lang'])) {
            return $_SESSION['zbx_lang'];
        }
        if (isset($_SESSION['lang']) && !empty($_SESSION['lang'])) {
            return $_SESSION['lang'];
        }

        return self::getUserLanguageFromDatabase();
    }

    private static function getUserLanguageFromDatabase() {
        try {
            $userid = null;
            if (isset($_SESSION['userid'])) {
                $userid = $_SESSION['userid'];
            } elseif (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['userid'])) {
                $userid = $_SESSION['user']['userid'];
            }

            if (!$userid) {
                return null;
            }

            if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
                $pdo = new \PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                    DB_USER,
                    defined('DB_PASSWORD') ? DB_PASSWORD : ''
                );

                $stmt = $pdo->prepare('SELECT lang FROM users WHERE userid = ? LIMIT 1');
                $stmt->execute([$userid]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($result && isset($result['lang'])) {
                    return $result['lang'];
                }
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    private static function getSystemDefaultLanguage() {
        try {
            if (class_exists('CSettingsHelper') || class_exists('\\CSettingsHelper')) {
                if (method_exists('CSettingsHelper', 'get')) {
                    $val = \CSettingsHelper::get('default_lang');
                    if (!empty($val)) {
                        return $val;
                    }
                }
            }

            if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
                $pdo = new \PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                    DB_USER,
                    defined('DB_PASSWORD') ? DB_PASSWORD : ''
                );

                $stmt = $pdo->prepare("SELECT value_str FROM settings WHERE name='default_lang' LIMIT 1");
                if ($stmt->execute()) {
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && !empty($row['value_str'])) {
                        return $row['value_str'];
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        return self::ZBX_DEFAULT_LANG;
    }

    private static function mapZabbixLangToOurs($zabbixLang) {
        $lowerLang = strtolower(trim($zabbixLang));

        $langMap = [
            'zh_cn' => 'zh_CN',
            'zh-cn' => 'zh_CN',
            'zh_tw' => 'zh_CN',
            'zh-tw' => 'zh_CN',
            'zh' => 'zh_CN',
            'chinese' => 'zh_CN',
            'china' => 'zh_CN',
            'cn' => 'zh_CN',
            'en_us' => 'en_US',
            'en-us' => 'en_US',
            'en_gb' => 'en_US',
            'en-gb' => 'en_US',
            'en' => 'en_US',
            'english' => 'en_US',
            'us' => 'en_US',
            'gb' => 'en_US',
            'default' => self::LANG_DEFAULT
        ];

        if (isset($langMap[$lowerLang])) {
            return $langMap[$lowerLang];
        }

        if (strpos($lowerLang, 'zh') === 0 || strpos($lowerLang, 'cn') !== false || strpos($lowerLang, 'chinese') !== false) {
            return 'zh_CN';
        }

        if (strpos($lowerLang, 'en') === 0 || strpos($lowerLang, 'english') !== false) {
            return 'en_US';
        }

        return self::ZBX_DEFAULT_LANG;
    }

    private static function ensureSupportedOrFallback($lang) {
        $mapped = self::mapZabbixLangToOurs($lang);
        if (self::isSupportedLocale($mapped)) {
            return $mapped;
        }
        return self::ZBX_DEFAULT_LANG;
    }

    private static function isSupportedLocale($lang) {
        return in_array($lang, array_keys(self::$translations), true);
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
        $translation = self::t($key);
        return sprintf($translation, ...$args);
    }

    public static function getCurrentLanguage() {
        return self::detectLanguage();
    }
}
