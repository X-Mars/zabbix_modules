<?php

namespace Modules\ZabbixSnmp\Lib;

class LanguageManager {
    private const LANG_DEFAULT = 'default';
    private const ZBX_DEFAULT_LANG = 'en_US';

    private static $currentLanguage = null;

    private static $translations = [
        'zh_CN' => [
            'SNMP MIB Browser' => 'SNMP MIB 浏览器',
            'SNMP Assistant' => 'SNMP 助手',
            'Zabbix Mibs' => 'Zabbix Mibs',
            'Zabbix Walk' => 'Zabbix Walk',
            'SNMP Walk Runner' => 'SNMP Walk 运行器',
            'Run' => '运行',
            'Walk OID' => 'Walk OID',
            'Enter OID to walk, e.g. 1.3.6.1.2.1' => '输入要 Walk 的 OID，例如 1.3.6.1.2.1',
            'SNMP Walk Results' => 'SNMP Walk 结果',
            'Total lines' => '总行数',
            'No walk results' => '没有 walk 结果',
            'SNMP walk command failed to execute' => 'snmpwalk 命令执行失败',
            'SNMP walk returned no data' => 'SNMP walk 未返回数据',
            'SNMP walk failed' => 'SNMP walk 失败',
            'Browse operating system MIB files from common SNMP directories.' => '浏览操作系统常见 SNMP 目录中的 MIB 文件。',
            'MIB Directory' => 'MIB 目录',
            'Select MIB directory' => '选择 MIB 目录',
            'SNMP Connection Test' => 'SNMP 连接测试',
            'Connection Mode' => '连接模式',
            'Host Based' => '基于主机',
            'Manual Input' => '手动输入',
            'Host Group' => '主机分组',
            'Host' => '主机',
            'Address' => '地址',
            'Port' => '端口',
            'Version' => '版本',
            'Community' => '团体字',
            'Security Name' => '安全名称',
            'Security Level' => '安全级别',
            'Auth Protocol' => '认证协议',
            'Auth Passphrase' => '认证口令',
            'Priv Protocol' => '加密协议',
            'Priv Passphrase' => '加密口令',
            'Context Name' => '上下文',
            'Current Host SNMP Profile' => '当前主机 SNMP 信息',
            'No SNMP interface found on this host.' => '当前主机未配置 SNMP 接口。',
            'V3 Extra Info' => 'V3 扩展信息',
            'Files in Current Directory' => '当前目录文件',
            'SNMP Objects' => 'SNMP 模块对象',
            'No SNMP objects parsed from this file.' => '该文件未解析到 SNMP 模块对象。',
            'Name' => '名称',
            'Kind' => '类型',
            'OID' => 'OID',
            'Resolved OID' => '解析 OID',
            'Syntax' => '语法',
            'Access' => '访问权限',
            'Status' => '状态',
            'Description' => '描述',
            'Line' => '行号',
            'Action' => '操作',
            'View Source' => '查看源码',
            'Copy Command' => '复制命令',
            'Copied' => '已复制',
            'Success' => '成功',
            'Failed' => '失败',
            'Test' => '测试',
            'Test Result' => '测试结果',
            'Last Test' => '最近测试',
            'Source Preview' => '源码预览',
            'Showing lines %d to %d.' => '显示第 %d 到 %d 行。',
            'Source snippet is truncated.' => '源码片段已截断。',
            'Loading source...' => '正在加载源码...',
            'No source available' => '暂无可用源码',
            'Close' => '关闭',
            'No directories with MIB files found.' => '未找到包含 MIB 文件的目录。',
            'Search MIB files...' => '搜索 MIB 文件...',
            'Search' => '搜索',
            'Clear' => '清除',
            'Detected Directories' => '已检测目录',
            'Existing Directories' => '存在目录',
            'Total MIB Files' => 'MIB 文件总数',
            'Files in Selected Directory' => '当前目录文件数',
            'Directory Status' => '目录状态',
            'Available' => '可用',
            'Missing' => '缺失',
            'files' => '个文件',
            'No MIB directories found.' => '未找到可用的 MIB 目录。',
            'No MIB files found in this directory.' => '该目录下未找到 MIB 文件。',
            'No MIB files match the current filter.' => '当前筛选条件下未找到 MIB 文件。',
            'MIB File Details' => 'MIB 文件详情',
            'Click a MIB file on the left to view its details.' => '点击左侧任意 MIB 文件查看详情。',
            'The selected MIB file is unavailable.' => '所选 MIB 文件不可用。',
            'SNMP Test Success' => 'SNMP 测试成功',
            'SNMP Test Failed' => 'SNMP 测试失败',
            'OID is empty' => 'OID 为空',
            'OID format is invalid' => 'OID 格式无效',
            'SNMP target address is empty' => 'SNMP 目标地址为空',
            'No SNMP runtime is available (PHP SNMP extension and snmpget command unavailable).' => '没有可用的 SNMP 运行环境（PHP SNMP 扩展和 snmpget 命令均不可用）。',
            'SNMP v2c query failed' => 'SNMP v2c 查询失败',
            'SNMP v1 query failed' => 'SNMP v1 查询失败',
            'SNMP v3 query failed' => 'SNMP v3 查询失败',
            'snmpget command failed to execute' => 'snmpget 命令执行失败',
            'SNMP query failed' => 'SNMP 查询失败',
            'SNMPv3 security name is required' => 'SNMPv3 需要安全名称',
            'File Name' => '文件名',
            'Relative Path' => '相对路径',
            'Full Path' => '完整路径',
            'Directory' => '目录',
            'Size' => '大小',
            'Modified Time' => '修改时间',
            'Line Count' => '总行数',
            'Preview Lines' => '预览行数',
            'Extension' => '扩展名',
            'File Preview' => '文件预览',
            'Preview is limited to the first %d lines.' => '仅展示前 %d 行内容。',
            'Unable to read the selected file.' => '无法读取所选文件。',
            'Common MIB directories scanned from the operating system.' => '已扫描操作系统中的常见 MIB 目录。',
            'Selected File' => '当前文件',
            'Preview truncated' => '预览已截断'
        ],
        'en_US' => [
            'SNMP MIB Browser' => 'SNMP MIB Browser',
            'SNMP Assistant' => 'SNMP Assistant',
            'Zabbix Mibs' => 'Zabbix Mibs',
            'Zabbix Walk' => 'Zabbix Walk',
            'SNMP Walk Runner' => 'SNMP Walk Runner',
            'Run' => 'Run',
            'Walk OID' => 'Walk OID',
            'Enter OID to walk, e.g. 1.3.6.1.2.1' => 'Enter OID to walk, e.g. 1.3.6.1.2.1',
            'SNMP Walk Results' => 'SNMP Walk Results',
            'Total lines' => 'Total lines',
            'No walk results' => 'No walk results',
            'SNMP walk command failed to execute' => 'SNMP walk command failed to execute',
            'SNMP walk returned no data' => 'SNMP walk returned no data',
            'SNMP walk failed' => 'SNMP walk failed',
            'Browse operating system MIB files from common SNMP directories.' => 'Browse operating system MIB files from common SNMP directories.',
            'MIB Directory' => 'MIB Directory',
            'Select MIB directory' => 'Select MIB directory',
            'SNMP Connection Test' => 'SNMP Connection Test',
            'Connection Mode' => 'Connection Mode',
            'Host Based' => 'Host Based',
            'Manual Input' => 'Manual Input',
            'Host Group' => 'Host Group',
            'Host' => 'Host',
            'Address' => 'Address',
            'Port' => 'Port',
            'Version' => 'Version',
            'Community' => 'Community',
            'Security Name' => 'Security Name',
            'Security Level' => 'Security Level',
            'Auth Protocol' => 'Auth Protocol',
            'Auth Passphrase' => 'Auth Passphrase',
            'Priv Protocol' => 'Priv Protocol',
            'Priv Passphrase' => 'Priv Passphrase',
            'Context Name' => 'Context Name',
            'Current Host SNMP Profile' => 'Current Host SNMP Profile',
            'No SNMP interface found on this host.' => 'No SNMP interface found on this host.',
            'V3 Extra Info' => 'V3 Extra Info',
            'Files in Current Directory' => 'Files in Current Directory',
            'SNMP Objects' => 'SNMP Objects',
            'No SNMP objects parsed from this file.' => 'No SNMP objects parsed from this file.',
            'Name' => 'Name',
            'Kind' => 'Kind',
            'OID' => 'OID',
            'Resolved OID' => 'Resolved OID',
            'Syntax' => 'Syntax',
            'Access' => 'Access',
            'Status' => 'Status',
            'Description' => 'Description',
            'Line' => 'Line',
            'Action' => 'Action',
            'View Source' => 'View Source',
            'Copy Command' => 'Copy Command',
            'Copied' => 'Copied',
            'Success' => 'Success',
            'Failed' => 'Failed',
            'Test' => 'Test',
            'Test Result' => 'Test Result',
            'Last Test' => 'Last Test',
            'Source Preview' => 'Source Preview',
            'Showing lines %d to %d.' => 'Showing lines %d to %d.',
            'Source snippet is truncated.' => 'Source snippet is truncated.',
            'Loading source...' => 'Loading source...',
            'No source available' => 'No source available',
            'Close' => 'Close',
            'No directories with MIB files found.' => 'No directories with MIB files found.',
            'Search MIB files...' => 'Search MIB files...',
            'Search' => 'Search',
            'Clear' => 'Clear',
            'Detected Directories' => 'Detected Directories',
            'Existing Directories' => 'Existing Directories',
            'Total MIB Files' => 'Total MIB Files',
            'Files in Selected Directory' => 'Files in Selected Directory',
            'Directory Status' => 'Directory Status',
            'Available' => 'Available',
            'Missing' => 'Missing',
            'files' => 'files',
            'No MIB directories found.' => 'No MIB directories found.',
            'No MIB files found in this directory.' => 'No MIB files found in this directory.',
            'No MIB files match the current filter.' => 'No MIB files match the current filter.',
            'MIB File Details' => 'MIB File Details',
            'Click a MIB file on the left to view its details.' => 'Click a MIB file on the left to view its details.',
            'The selected MIB file is unavailable.' => 'The selected MIB file is unavailable.',
            'SNMP Test Success' => 'SNMP Test Success',
            'SNMP Test Failed' => 'SNMP Test Failed',
            'OID is empty' => 'OID is empty',
            'OID format is invalid' => 'OID format is invalid',
            'SNMP target address is empty' => 'SNMP target address is empty',
            'No SNMP runtime is available (PHP SNMP extension and snmpget command unavailable).' => 'No SNMP runtime is available (PHP SNMP extension and snmpget command unavailable).',
            'SNMP v2c query failed' => 'SNMP v2c query failed',
            'SNMP v1 query failed' => 'SNMP v1 query failed',
            'SNMP v3 query failed' => 'SNMP v3 query failed',
            'snmpget command failed to execute' => 'snmpget command failed to execute',
            'SNMP query failed' => 'SNMP query failed',
            'SNMPv3 security name is required' => 'SNMPv3 security name is required',
            'File Name' => 'File Name',
            'Relative Path' => 'Relative Path',
            'Full Path' => 'Full Path',
            'Directory' => 'Directory',
            'Size' => 'Size',
            'Modified Time' => 'Modified Time',
            'Line Count' => 'Line Count',
            'Preview Lines' => 'Preview Lines',
            'Extension' => 'Extension',
            'File Preview' => 'File Preview',
            'Preview is limited to the first %d lines.' => 'Preview is limited to the first %d lines.',
            'Unable to read the selected file.' => 'Unable to read the selected file.',
            'Common MIB directories scanned from the operating system.' => 'Common MIB directories scanned from the operating system.',
            'Selected File' => 'Selected File',
            'Preview truncated' => 'Preview truncated'
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
                self::$currentLanguage = self::ensureSupportedOrFallback(self::getSystemDefaultLanguage());
                return self::$currentLanguage;
            }

            self::$currentLanguage = self::ensureSupportedOrFallback($mapped);
            return self::$currentLanguage;
        }

        self::$currentLanguage = self::ensureSupportedOrFallback(self::getSystemDefaultLanguage());
        return self::$currentLanguage;
    }

    public static function getCurrentLanguage(): string {
        return self::detectLanguage();
    }

    public static function isChinese(): bool {
        return self::getCurrentLanguage() === 'zh_CN';
    }

    public static function t($key) {
        $language = self::getCurrentLanguage();

        if (isset(self::$translations[$language][$key])) {
            return self::$translations[$language][$key];
        }

        if (isset(self::$translations['en_US'][$key])) {
            return self::$translations['en_US'][$key];
        }

        return $key;
    }

    public static function tf($key, ...$args) {
        return vsprintf(self::t($key), $args);
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

        if (!empty($GLOBALS['USER_DETAILS']['lang'])) {
            return $GLOBALS['USER_DETAILS']['lang'];
        }

        if (!empty($GLOBALS['ZBX_LOCALES']['selected'])) {
            return $GLOBALS['ZBX_LOCALES']['selected'];
        }

        if (!empty($_SESSION['zbx_lang'])) {
            return $_SESSION['zbx_lang'];
        }

        if (!empty($_SESSION['lang'])) {
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

            if (!$userid || !defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
                return null;
            }

            $pdo = new \PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                DB_USER,
                defined('DB_PASSWORD') ? DB_PASSWORD : ''
            );

            $stmt = $pdo->prepare('SELECT lang FROM users WHERE userid = :userid LIMIT 1');
            $stmt->execute(['userid' => $userid]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row && !empty($row['lang'])) {
                return $row['lang'];
            }
        } catch (\Throwable $e) {
        }

        return null;
    }

    private static function getSystemDefaultLanguage() {
        try {
            if (class_exists('CSettingsHelper') || class_exists('\\CSettingsHelper')) {
                $val = \CSettingsHelper::get('default_lang');
                if (!empty($val)) {
                    return $val;
                }
            }
        } catch (\Throwable $e) {
        }

        try {
            if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
                $pdo = new \PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                    DB_USER,
                    defined('DB_PASSWORD') ? DB_PASSWORD : ''
                );

                $stmt = $pdo->prepare("SELECT value_str FROM settings WHERE name='default_lang' LIMIT 1");
                $stmt->execute();
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row && !empty($row['value_str'])) {
                    return $row['value_str'];
                }

                try {
                    $stmt2 = $pdo->query('SELECT default_lang FROM config LIMIT 1');
                    $row2 = $stmt2 ? $stmt2->fetch(\PDO::FETCH_ASSOC) : null;
                    if ($row2 && !empty($row2['default_lang'])) {
                        return $row2['default_lang'];
                    }
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
        }

        return self::ZBX_DEFAULT_LANG;
    }

    private static function mapZabbixLangToOurs(string $lang): string {
        $normalized = str_replace('-', '_', trim($lang));
        if ($normalized === '') {
            return self::ZBX_DEFAULT_LANG;
        }

        if (isset(self::$translations[$normalized])) {
            return $normalized;
        }

        if (stripos($normalized, 'zh') === 0) {
            return 'zh_CN';
        }

        if ($normalized === self::LANG_DEFAULT) {
            return self::LANG_DEFAULT;
        }

        return 'en_US';
    }

    private static function ensureSupportedOrFallback(?string $lang): string {
        if ($lang === null || $lang === '') {
            return self::ZBX_DEFAULT_LANG;
        }

        $mapped = self::mapZabbixLangToOurs($lang);
        if ($mapped === self::LANG_DEFAULT) {
            return self::ZBX_DEFAULT_LANG;
        }

        return isset(self::$translations[$mapped]) ? $mapped : self::ZBX_DEFAULT_LANG;
    }
}