<?php

namespace Modules\ZabbixCmdb\Lib;

class LanguageManager {
    /**
     * 与 Zabbix 前端保持一致的标识与默认值
     */
    private const LANG_DEFAULT = 'default';
    private const ZBX_DEFAULT_LANG = 'en_US';
    
    private static $currentLanguage = null;
    private static $translations = [
        'zh_CN' => [
            'CMDB' => 'CMDB',
            'Configuration Management Database' => '配置管理数据库',
            'Search hosts...' => '搜索主机...',
            'Search by hostname or IP' => '按主机名或IP搜索',
            'All Groups' => '所有分组',
            'Select host group' => '选择主机分组',
            'Host Name' => '主机名',
            'IP Address' => 'IP地址',
            'Interface Type' => '接口方式',
            'All Interfaces' => '所有接口',
            'Interface Type' => '接口方式',
            'CPU Total' => 'CPU总量',
            'CPU Usage' => 'CPU使用率',
            'Memory Total' => '内存总量',
            'Memory Usage' => '内存使用率',
            'Host Group' => '主机分组',
            'System Name' => '系统名称',
            'Architecture' => '架构',
            'Operating System' => '操作系统',
            'Kernel Version' => '内核版本',
            'Agent' => 'Agent',
            'SNMP' => 'SNMP',
            'IPMI' => 'IPMI',
            'JMX' => 'JMX',
            'No hosts found' => '未找到主机',
            'Loading...' => '加载中...',
            'Search' => '搜索',
            'Clear' => '清除',
            'Total Hosts' => '主机总数',
            'Host Groups' => '主机分组',
            'Host List' => '主机列表',
            'Search host groups...' => '搜索主机分组...',
            'Enter group name' => '输入分组名称',
            'Group Name' => '分组名称',
            'Host Count' => '主机数量',
            'Active Hosts' => '启用主机',
            'Search by group name' => '按分组名称搜索',
            'Search groups...' => '搜索分组...',
            'Status' => '状态',
            'No groups found' => '未找到分组',
            'Empty Group' => '空分组',
            'Active Group' => '活跃分组',
            'Basic Group' => '基础分组',
            'hosts' => '个主机',
            'cores' => '核',
            'Invalid input parameters.' => '无效的输入参数。'
        ],
        'en_US' => [
            'CMDB' => 'CMDB',
            'Configuration Management Database' => 'Configuration Management Database',
            'Search hosts...' => 'Search hosts...',
            'Search by hostname or IP' => 'Search by hostname or IP',
            'All Groups' => 'All Groups',
            'Select host group' => 'Select host group',
            'Host Name' => 'Host Name',
            'IP Address' => 'IP Address',
            'Interface Type' => 'Interface Type',
            'All Interfaces' => 'All Interfaces',
            'Interface Type' => 'Interface Type',
            'CPU Total' => 'CPU Total',
            'CPU Usage' => 'CPU Usage',
            'Memory Total' => 'Memory Total',
            'Memory Usage' => 'Memory Usage',
            'Host Group' => 'Host Group',
            'System Name' => 'System Name',
            'Architecture' => 'Architecture',
            'Operating System' => 'Operating System',
            'Kernel Version' => 'Kernel Version',
            'Agent' => 'Agent',
            'SNMP' => 'SNMP',
            'IPMI' => 'IPMI',
            'JMX' => 'JMX',
            'No hosts found' => 'No hosts found',
            'Loading...' => 'Loading...',
            'Search' => 'Search',
            'Clear' => 'Clear',
            'Total Hosts' => 'Total Hosts',
            'Host Groups' => 'Host Groups',
            'Host List' => 'Host List',
            'Search host groups...' => 'Search host groups...',
            'Enter group name' => 'Enter group name',
            'Group Name' => 'Group Name',
            'Host Count' => 'Host Count',
            'Active Hosts' => 'Active Hosts',
            'Search by group name' => 'Search by group name',
            'Search groups...' => 'Search groups...',
            'Status' => 'Status',
            'No groups found' => 'No groups found',
            'Empty Group' => 'Empty Group',
            'Active Group' => 'Active Group',
            'Basic Group' => 'Basic Group',
            'hosts' => 'hosts',
            'cores' => 'cores',
            'Invalid input parameters.' => 'Invalid input parameters.'
        ]
    ];

    /**
     * 检测当前语言（对齐 Zabbix 源码逻辑）
     * 优先级：
     * 1) 用户语言（users.lang），如果为 'default' 则继承系统默认
     * 2) 系统默认语言（settings.default_lang），读取失败则回退到 ZBX 默认
     * 3) Zabbix 默认语言（en_US）
     */
    public static function detectLanguage() {
        if (self::$currentLanguage !== null) {
            return self::$currentLanguage;
        }
        
        // 1) 用户语言
        $userLang = self::getUserLanguageFromZabbix();
        if (!empty($userLang)) {
            $mapped = self::mapZabbixLangToOurs($userLang);
            // 'default' 表示继承系统默认
            if ($mapped === self::LANG_DEFAULT) {
                $sys = self::getSystemDefaultLanguage();
                self::$currentLanguage = self::ensureSupportedOrFallback($sys);
                return self::$currentLanguage;
            }
            self::$currentLanguage = self::ensureSupportedOrFallback($mapped);
            return self::$currentLanguage;
        }

        // 2) 系统默认语言
        $sys = self::getSystemDefaultLanguage();
        if (!empty($sys)) {
            self::$currentLanguage = self::ensureSupportedOrFallback($sys);
            return self::$currentLanguage;
        }

        // 3) Zabbix 默认语言
        self::$currentLanguage = self::ensureSupportedOrFallback(self::ZBX_DEFAULT_LANG);
        return self::$currentLanguage;
    }

    /**
     * 尝试从Zabbix系统中获取当前用户的语言设置
     */
    private static function getUserLanguageFromZabbix() {
        // 方法0: 优先使用 Zabbix 官方封装 CWebUser
        try {
            if (class_exists('CWebUser') || class_exists('\\CWebUser')) {
                // 静态 get 方法（较新版本）
                if (method_exists('CWebUser', 'get')) {
                    $lang = \CWebUser::get('lang');
                    if (!empty($lang)) {
                        return $lang;
                    }
                }
                // 旧版本静态数据容器
                if (isset(\CWebUser::$data) && is_array(\CWebUser::$data) && isset(\CWebUser::$data['lang']) && !empty(\CWebUser::$data['lang'])) {
                    return \CWebUser::$data['lang'];
                }
            }
        } catch (\Throwable $e) {
            // 忽略并继续其他方式
        }

        // 方法1: 尝试通过全局变量获取CWebUser信息
        try {
            // 检查$GLOBALS中是否有CWebUser相关信息
            if (isset($GLOBALS['USER_DETAILS']) && isset($GLOBALS['USER_DETAILS']['lang'])) {
                return $GLOBALS['USER_DETAILS']['lang'];
            }
        } catch (Throwable $e) {
            // 继续其他方法
        }
        
        // 方法2: 尝试从全局变量中获取（安装流程/页面初始化缓存）
        try {
            if (isset($GLOBALS['ZBX_LOCALES']) && isset($GLOBALS['ZBX_LOCALES']['selected'])) {
                return $GLOBALS['ZBX_LOCALES']['selected'];
            }
        } catch (Throwable $e) {
            // 继续其他方法
        }
        
        // 方法3: 从Session中获取（Zabbix 前端会在登录后设置）
        if (isset($_SESSION['zbx_lang']) && !empty($_SESSION['zbx_lang'])) {
            return $_SESSION['zbx_lang'];
        }
        if (isset($_SESSION['lang']) && !empty($_SESSION['lang'])) {
            return $_SESSION['lang'];
        }

        // 方法4: 尝试直接访问数据库获取用户语言设置
        return self::getUserLanguageFromDatabase();
    }

    /**
     * 尝试通过API获取用户语言设置
     */
    private static function getUserLanguageFromAPI() {
        try {
            // 获取当前用户ID
            $userid = null;

            // 从Session中获取用户ID
            if (isset($_SESSION['userid'])) {
                $userid = $_SESSION['userid'];
            } elseif (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['userid'])) {
                $userid = $_SESSION['user']['userid'];
            }

            if (!$userid) {
                return null;
            }

            // 尝试使用不同的API类名（兼容不同版本）
            $apiClass = null;
            if (class_exists('API') || class_exists('\API')) {
                $apiClass = '\API';
            } elseif (class_exists('CApiService') || class_exists('\CApiService')) {
                $apiClass = '\CApiService';
            } elseif (class_exists('\Zabbix\Api\ApiService')) {
                $apiClass = '\Zabbix\Api\ApiService';
            } elseif (class_exists('\API')) {
                $apiClass = '\API';
            }

            if ($apiClass && method_exists($apiClass, 'User')) {
                $users = $apiClass::User()->get([
                    'output' => ['lang'],
                    'userids' => $userid,
                    'limit' => 1
                ]);

                if (!empty($users) && isset($users[0]['lang'])) {
                    return $users[0]['lang'];
                }
            }
        } catch (Throwable $e) {
            // API不可用或出错
        }

        return null;
    }

    /**
     * 尝试直接从数据库获取当前用户的语言设置
     */
    private static function getUserLanguageFromDatabase() {
        try {
            // 获取当前用户ID
            $userid = null;

            // 从Session中获取用户ID
            if (isset($_SESSION['userid'])) {
                $userid = $_SESSION['userid'];
            } elseif (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['userid'])) {
                $userid = $_SESSION['user']['userid'];
            }

            if (!$userid) {
                return null;
            }

            // 尝试连接数据库（需要Zabbix的数据库配置）
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
        } catch (Throwable $e) {
            // 数据库连接失败或其他错误
        }

        return null;
    }

    /**
     * 读取系统默认语言（settings.default_lang 或 config.default_lang）
     */
    private static function getSystemDefaultLanguage() {
        try {
            // 方式0：优先使用 Zabbix 官方封装 CSettingsHelper
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

                // 先查 settings 表（Zabbix 6/7 使用）
                $stmt = $pdo->prepare("SELECT value_str FROM settings WHERE name='default_lang' LIMIT 1");
                if ($stmt->execute()) {
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && !empty($row['value_str'])) {
                        return $row['value_str'];
                    }
                }

                // 兼容旧版本：config 表
                $stmt2 = $pdo->query("SHOW TABLES LIKE 'config'");
                $hasConfig = $stmt2 && $stmt2->fetch();
                if ($hasConfig) {
                    $stmt3 = $pdo->query("SELECT default_lang FROM config LIMIT 1");
                    $row2 = $stmt3 ? $stmt3->fetch(\PDO::FETCH_ASSOC) : false;
                    if ($row2 && !empty($row2['default_lang'])) {
                        return $row2['default_lang'];
                    }
                }
            }
        } catch (Throwable $e) {
            // 忽略并回退
        }

        return self::ZBX_DEFAULT_LANG;
    }

    /**
     * 将Zabbix语言代码映射到我们的语言代码
     */
    private static function mapZabbixLangToOurs($zabbixLang) {
        // 先转换为小写进行比较，以处理大小写不一致的情况
        $lowerLang = strtolower(trim($zabbixLang));

        $langMap = [
            // 中文的各种变体
            'zh_cn' => 'zh_CN',
            'zh-cn' => 'zh_CN',
            'zh_tw' => 'zh_CN', // 繁体中文也使用简体中文翻译
            'zh-tw' => 'zh_CN',
            'zh' => 'zh_CN',
            'chinese' => 'zh_CN',
            'china' => 'zh_CN',
            'cn' => 'zh_CN',

            // 英文的各种变体
            'en_us' => 'en_US',
            'en-us' => 'en_US',
            'en_gb' => 'en_US',
            'en-gb' => 'en_US',
            'en' => 'en_US',
            'english' => 'en_US',
            'us' => 'en_US',
            'gb' => 'en_US',

            // 默认
            'default' => self::LANG_DEFAULT
        ];

        // 如果找到直接映射，返回结果
        if (isset($langMap[$lowerLang])) {
            return $langMap[$lowerLang];
        }

        // 如果没有找到，尝试部分匹配
        if (strpos($lowerLang, 'zh') === 0 || strpos($lowerLang, 'cn') !== false || strpos($lowerLang, 'chinese') !== false) {
            return 'zh_CN';
        }

        if (strpos($lowerLang, 'en') === 0 || strpos($lowerLang, 'english') !== false) {
            return 'en_US';
        }

        // 默认使用英语
        return self::ZBX_DEFAULT_LANG;
    }

    /**
     * 检查并回退到受支持语言
     */
    private static function ensureSupportedOrFallback($lang) {
        $mapped = self::mapZabbixLangToOurs($lang);
        if (self::isSupportedLocale($mapped)) {
            return $mapped;
        }
        // 受支持语言有限，仅 zh_CN / en_US
        return self::ZBX_DEFAULT_LANG;
    }

    /**
     * 仅将我们已提供翻译的语言视为可用
     */
    private static function isSupportedLocale($lang) {
        return in_array($lang, array_keys(self::$translations), true);
    }

    /**
     * 获取翻译文本
     */
    public static function t($key) {
        $lang = self::detectLanguage();

        if (isset(self::$translations[$lang][$key])) {
            return self::$translations[$lang][$key];
        }

        // 如果当前语言没有翻译，尝试使用英语
        if ($lang !== 'en_US' && isset(self::$translations['en_US'][$key])) {
            return self::$translations['en_US'][$key];
        }

        // 如果都没有，返回原键值
        return $key;
    }

    /**
     * 获取带参数的翻译文本
     */
    public static function tf($key, ...$args) {
        $translation = self::t($key);
        return sprintf($translation, ...$args);
    }

    /**
     * 获取当前语言
     */
    public static function getCurrentLanguage() {
        return self::detectLanguage();
    }

    /**
     * 重置语言缓存（主要用于测试）
     */
    public static function resetLanguage() {
        self::$currentLanguage = null;
    }

    /**
     * 获取语言检测信息（用于调试）
     */
    public static function getLanguageDetectionInfo() {
        return [
            'detected' => self::detectLanguage(),
            'zabbix_user_lang' => self::getUserLanguageFromZabbix(),
            'db_lang' => self::getUserLanguageFromDatabase(),
            'system_lang' => self::getSystemDefaultLanguage(),
            'supported_locales' => array_keys(self::$translations)
        ];
    }

    /**
     * 检查当前是否为中文语言
     */
    public static function isChinese() {
        return self::detectLanguage() === 'zh_CN';
    }

    /**
     * 格式化日期时间（根据语言）
     */
    public static function formatDateTime($timestamp, $format = null) {
        if ($format === null) {
            $format = self::isChinese() ? 'Y年m月d日 H:i:s' : 'Y-m-d H:i:s';
        }

        return date($format, $timestamp);
    }

    /**
     * 格式化日期（根据语言）
     */
    public static function formatDate($timestamp, $format = null) {
        if ($format === null) {
            $format = self::isChinese() ? 'Y年m月d日' : 'Y-m-d';
        }

        return date($format, $timestamp);
    }

    /**
     * 格式化周期（根据语言）
     */
    public static function formatPeriod($type, $dateString) {
        // 将日期字符串转换为时间戳
        if (is_string($dateString)) {
            $timestamp = strtotime($dateString);
        } else {
            $timestamp = $dateString;
        }

        if ($timestamp === false) {
            return $dateString; // 如果转换失败，返回原字符串
        }

        if (self::isChinese()) {
            switch ($type) {
                case 'day':
                case 'daily':
                    return date('Y年m月d日', $timestamp);
                case 'week':
                case 'weekly':
                    $year = date('Y', $timestamp);
                    $week = date('W', $timestamp);
                    return $year . '年第' . $week . '周';
                case 'month':
                case 'monthly':
                    return date('Y年m月', $timestamp);
                default:
                    return date('Y-m-d', $timestamp);
            }
        } else {
            switch ($type) {
                case 'day':
                case 'daily':
                    return date('Y-m-d', $timestamp);
                case 'week':
                case 'weekly':
                    return date('Y', $timestamp) . ' Week ' . date('W', $timestamp);
                case 'month':
                case 'monthly':
                    return date('Y-m', $timestamp);
                default:
                    return date('Y-m-d', $timestamp);
            }
        }
    }
}
