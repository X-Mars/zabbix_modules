<?php

namespace Modules\ZabbixGraphTrees\Lib;

class LanguageManager {
    /**
     * 与 Zabbix 前端保持一致的标识与默认值
     */
    private const LANG_DEFAULT = 'default';
    private const ZBX_DEFAULT_LANG = 'en_US';
    
    private static $currentLanguage = null;
    private static $translations = [
        'zh_CN' => [
            'Graph Trees' => '图表树',
            'Resource Tree' => '资源树',
            'Host Groups' => '主机分组',
            'Hosts' => '主机',
            'Tags' => '标记',
            'Tag Value' => '标记值',
            'Time Range' => '时间范围',
            'Select Tag' => '选择标记',
            'Select Tag Value' => '选择标记值',
            'Select Time Range' => '选择时间范围',
            'Last 10 Minutes' => '最近10分钟',
            'Last 30 Minutes' => '最近30分钟',
            'Last Hour' => '最近1小时',
            'Last 3 Hours' => '最近3小时',
            'Last 6 Hours' => '最近6小时',
            'Last 12 Hours' => '最近12小时',
            'Last 24 Hours' => '最近24小时',
            'Last 7 Days' => '最近7天',
            'Last 30 Days' => '最近30天',
            'Custom' => '自定义',
            'From' => '从',
            'To' => '到',
            'Apply' => '应用',
            'Refresh' => '刷新',
            'No data available' => '无可用数据',
            'No data' => '暂无数据',
            'No valid data' => '暂无有效数据',
            'Loading...' => '加载中...',
            'Select a host to view graphs' => '选择主机以查看图形',
            'No items found for this host' => '未找到此主机的监控项',
            'Monitoring Graphs' => '监控图形',
            'All Tags' => '所有标记',
            'All Values' => '所有值',
            'Search...' => '搜索...',
            'Expand All' => '展开全部',
            'Collapse All' => '收起全部',
            'Auto Refresh' => '自动刷新',
            'Off' => '关闭',
            'seconds' => '秒',
            'Custom time range selection coming soon' => '自定义时间范围选择即将推出',
            'Failed to load data' => '加载数据失败',
            'Zoom In' => '放大',
            'Close' => '关闭',
            'Items' => '监控项',
            'All Items' => '全部监控项',
            'selected' => '项已选',
            'Select All' => '全选',
            'Deselect All' => '取消全选',
            'Quick Select' => '快速选择',
            'Custom Range' => '自定义范围',
            'Cancel' => '取消'
        ],
        'en_US' => [
            'Graph Trees' => 'Graph Trees',
            'Resource Tree' => 'Resource Tree',
            'Host Groups' => 'Host Groups',
            'Hosts' => 'Hosts',
            'Tags' => 'Tags',
            'Tag Value' => 'Tag Value',
            'Time Range' => 'Time Range',
            'Select Tag' => 'Select Tag',
            'Select Tag Value' => 'Select Tag Value',
            'Select Time Range' => 'Select Time Range',
            'Last 10 Minutes' => 'Last 10 Minutes',
            'Last 30 Minutes' => 'Last 30 Minutes',
            'Last Hour' => 'Last Hour',
            'Last 3 Hours' => 'Last 3 Hours',
            'Last 6 Hours' => 'Last 6 Hours',
            'Last 12 Hours' => 'Last 12 Hours',
            'Last 24 Hours' => 'Last 24 Hours',
            'Last 7 Days' => 'Last 7 Days',
            'Last 30 Days' => 'Last 30 Days',
            'Custom' => 'Custom',
            'From' => 'From',
            'To' => 'To',
            'Apply' => 'Apply',
            'Refresh' => 'Refresh',
            'No data available' => 'No data available',
            'No data' => 'No data',
            'No valid data' => 'No valid data',
            'Loading...' => 'Loading...',
            'Select a host to view graphs' => 'Select a host to view graphs',
            'No items found for this host' => 'No items found for this host',
            'Monitoring Graphs' => 'Monitoring Graphs',
            'All Tags' => 'All Tags',
            'All Values' => 'All Values',
            'Search...' => 'Search...',
            'Expand All' => 'Expand All',
            'Collapse All' => 'Collapse All',
            'Auto Refresh' => 'Auto Refresh',
            'Off' => 'Off',
            'seconds' => 'seconds',
            'Custom time range selection coming soon' => 'Custom time range selection coming soon',
            'Failed to load data' => 'Failed to load data',
            'Zoom In' => 'Zoom In',
            'Close' => 'Close',
            'Items' => 'Items',
            'All Items' => 'All Items',
            'selected' => 'selected',
            'Select All' => 'Select All',
            'Deselect All' => 'Deselect All',
            'Quick Select' => 'Quick Select',
            'Custom Range' => 'Custom Range',
            'Cancel' => 'Cancel'
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
        } catch (\Throwable $e) {
            // 继续其他方法
        }
        
        // 方法2: 尝试从全局变量中获取（安装流程/页面初始化缓存）
        try {
            if (isset($GLOBALS['ZBX_LOCALES']) && isset($GLOBALS['ZBX_LOCALES']['selected'])) {
                return $GLOBALS['ZBX_LOCALES']['selected'];
            }
        } catch (\Throwable $e) {
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
     * 通过API获取用户语言设置
     */
    private static function getUserLanguageByAPI($userid) {
        try {
            $apiClass = null;
            if (class_exists('API')) {
                $apiClass = 'API';
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
     * 获取当前语言代码
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
     * 判断是否为中文
     */
    public static function isChinese() {
        return self::detectLanguage() === 'zh_CN';
    }
}
