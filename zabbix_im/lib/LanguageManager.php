<?php

namespace Modules\ZabbixIm\Lib;

class LanguageManager {

    private const LANG_DEFAULT = 'default';
    private const ZBX_DEFAULT_LANG = 'en_US';

    private static $currentLanguage = null;
    private static $translations = [
        'zh_CN' => [
            'IM Sync Assistant' => 'IM 同步助手',
            'IM Sync' => 'IM 同步',
            'IM Sync Settings' => '同步设置',
            'Add sync setting' => '添加同步设置',
            'Edit sync setting' => '编辑同步设置',
            'Setting name' => '设置名称',
            'Credentials' => '凭据',
            'Actions' => '操作',
            'Enabled' => '已启用',
            'Disabled' => '未启用',
            'Enable' => '启用',
            'Disable' => '停用',
            'Edit' => '编辑',
            'Delete' => '删除',
            'Save' => '保存',
            'Cancel' => '取消',
            'Active sync setting' => '当前启用设置',
            'Sync settings' => '同步设置',
            'Go to IM Sync' => '返回 IM 同步',
            'Go to IM Sync Assistant' => '返回 IM 同步助手',
            'Go to IM Sync Settings' => '前往同步设置',
            'Root department ID' => '根部门 ID',
            'Corp ID' => 'CorpID（企业 ID）',
            'Corp Secret' => 'Corp Secret（应用密钥）',
            'App ID' => 'App ID',
            'App Secret' => 'App Secret（应用密钥）',
            'App Key' => 'App Key（AppKey）',
            'Leave blank to keep unchanged' => '留空表示保持原值不变',
            'Enable this setting (disables others)' => '启用此设置（将停用其他设置）',
            'WeCom/DingTalk default 1, Feishu default 0' => '企业微信/钉钉 默认 1，飞书 默认 0',
            'Only one sync setting can be enabled at a time. The sync page uses the enabled setting.' => '同一时间仅允许启用一个同步设置，IM 同步页面将使用已启用的设置。',
            'No sync settings yet. Click "Add sync setting" to create one.' => '暂无同步设置，请点击「添加同步设置」创建。',
            'Confirm delete this sync setting?' => '确定删除该同步设置吗？',
            'Settings saved' => '设置已保存',
            'Settings deleted' => '设置已删除',
            'Save failed' => '保存失败',
            'Delete failed' => '删除失败',
            'Sync setting not found' => '未找到同步设置',
            'Setting name is required.' => '请填写设置名称。',
            'This sync setting is missing required credentials.' => '该同步设置缺少必填的凭据信息。',
            'The enabled sync setting is missing required credentials. Please edit it in IM Sync Settings.' => '已启用的同步设置缺少必填凭据，请在「同步设置」中编辑。',
            'No enabled sync setting. Please add and enable one in IM Sync Settings.' => '尚未启用任何同步设置，请在「同步设置」中添加并启用。',
            'IM provider is not configured. Please configure it in IM Sync Settings.' => '尚未配置 IM 数据源，请在「同步设置」中进行配置。',
            'Instant Messaging Sync' => 'IM 同步助手',
            'WeCom' => '企业微信',
            'Feishu' => '飞书',
            'DingTalk' => '钉钉',
            'Provider' => '数据源',
            'Group Prefix' => '用户组前缀',
            'User Match Field' => '用户匹配字段',
            'Configuration File' => '配置文件',
            'Not configured' => '未配置',
            'Configured' => '已配置',
            'Yes' => '是',
            'No' => '否',
            'Sync all departments' => '同步全部部门',
            'Preview departments' => '预览部门',
            'Syncing...' => '同步中...',
            'Previewing...' => '预览中...',
            'Sync completed' => '同步完成',
            'Sync failed' => '同步失败',
            'Preview completed' => '预览完成',
            'Preview failed' => '预览失败',
            'No permission.' => '没有权限。',
            'Invalid input parameters.' => '无效的输入参数。',
            'IM provider is not configured. Please edit data/config.json.' => 'IM 数据源未配置，请编辑 data/config.json。',
            'Confirm sync all departments to Zabbix user groups?' => '确认将 IM 部门与用户同步到 Zabbix 用户组吗？',
            'Departments' => '部门数',
            'Groups Created' => '新建用户组',
            'Groups Updated' => '更新用户组',
            'Groups Existing' => '已有用户组',
            'Groups Failed' => '失败用户组',
            'Users Matched' => '匹配用户',
            'Users Synced' => '已同步用户',
            'Unmatched user list' => '未同步用户列表',
            'Users Unmatched' => '未匹配用户',
            'Department' => '部门',
            'User Group' => '用户组',
            'IM Users' => 'IM 用户数',
            'Matched' => '已匹配',
            'Unmatched' => '未匹配',
            'Status' => '状态',
            'Action' => '操作',
            'Created' => '已创建',
            'Updated' => '已更新',
            'Existing' => '已存在',
            'Failed' => '失败',
            'No departments found' => '未找到部门',
            'Sync Result' => '同步结果',
            'Preview Result' => '预览结果',
            'username' => '用户名',
            'email' => '邮箱',
            'alias' => '别名',
            'Use full department path as group name' => '使用部门完整路径作为用户组名',
            'Remove orphan groups on sync' => '同步时删除多余用户组',
            'Close' => '关闭',
            'Details' => '详情',
            'Managed User Groups' => '模块管理用户组',
            'Managed Users' => '模块管理用户',
            'Members' => '成员数',
            'No managed groups yet' => '暂无模块管理的用户组',
            'Auto create users' => '自动创建用户',
            'Users Created' => '新建用户',
            'Users Deleted' => '删除用户',
            'Groups Deleted' => '删除用户组',
            'System and manual users/groups are never deleted.' => '系统自带或手工创建的用户和用户组不会被删除。',
            'Sync all users' => '同步所有用户',
            'Syncing users...' => '同步用户中...',
            'User sync completed' => '用户同步完成',
            'User sync failed' => '用户同步失败',
            'User Sync Result' => '用户同步结果',
            'Confirm sync all users and assign them to user groups?' => '确认同步所有 IM 用户并加入对应用户组吗？',
            'User group not synced yet' => '用户组尚未同步',
            'default_roleid is required for Zabbix 7+ when auto creating users. Please edit data/config.json.' => 'Zabbix 7+ 自动创建用户需要配置 default_roleid，请编辑 data/config.json。',
            'Some departments are not synced yet. Run sync all departments before syncing users.' => '部分部门尚未同步用户组，请先点击「同步全部部门」后再同步用户。',
            'IM raw user data' => 'IM 原始用户数据',
            'IM User ID' => 'IM 用户 ID',
            'Name' => '姓名',
            'Zabbix Username' => 'Zabbix 用户名',
            'Zabbix create debug' => 'Zabbix 创建调试',
            'Error' => '错误',
            'WeCom API raw response' => '企业微信 API 原始返回',
            'IM API raw response' => 'IM API 原始返回',
            'API action' => '接口',
            'HTTP raw body' => 'HTTP 原始响应',
            'Parsed response' => '解析后的响应',
            'Extracted userids' => '提取的 userid 列表',
            'Message' => '信息',
            'Auto creating users requires Super Admin permission. Zabbix 7 user.create is Super Admin only.' => '自动创建用户需要 Super Admin 权限（Zabbix 7 的 user.create 仅 Super Admin 可调用）。请使用 Super Admin 账号执行同步。',
            'Zabbix API call failed' => 'Zabbix API 调用失败',
            'check Zabbix PHP log or Authentication password policy' => '请检查 Zabbix PHP 日志或 Authentication 密码策略',
            'Unknown API error' => '未知 API 错误',
            'skipped' => '已跳过',
            'failed' => '失败',
            'updated' => '已更新',
            'created' => '已创建',
            'renamed' => '已重命名',
            'existing' => '已存在',
            'linked' => '已关联',
            'matched' => '已匹配',
            'unmatched' => '未匹配',
            'preview' => '预览',
            'User sync list' => '用户同步列表',
            'Username' => '用户名',
            'Mobile' => '手机号',
            'Password' => '密码',
            'New user passwords are shown only once. Please save them securely.' => '新建用户密码仅展示一次，请妥善保存。',
            'Show IM raw user data' => '展开 IM 原始用户数据',
            'Hide IM raw user data' => '收起 IM 原始用户数据',
            'Show IM API raw response' => '展开 IM API 原始返回',
            'Hide IM API raw response' => '收起 IM API 原始返回',
            'No synced users' => '暂无同步用户',
            'Email' => '邮箱',
            'Username or password is empty.' => '用户名或密码为空。',
            'API returned unexpected result: %s' => 'API 返回异常结果：%s',
            'Group %s is not managed by module' => '用户组 %s 非本模块管理',
            'Failed to encode config' => '配置序列化失败',
            'Failed to write config file: %s' => '写入配置文件失败：%s',
            'DingTalk root department' => '钉钉根部门',
            'Feishu root department' => '飞书根部门',
            'Department %s' => '部门 %s',
            'HTTP error' => 'HTTP 错误',
            'WeCom department list failed' => '企业微信部门列表获取失败',
            'WeCom user list failed' => '企业微信用户列表获取失败',
            'WeCom gettoken failed' => '企业微信 access_token 获取失败',
            'WeCom corp_id or corp_secret is empty' => '企业微信 corp_id 或 corp_secret 未配置',
            'WeCom access_token is empty' => '企业微信 access_token 为空',
            'Feishu user list failed' => '飞书用户列表获取失败',
            'Feishu department list failed' => '飞书部门列表获取失败',
            'Feishu app_id or app_secret is empty' => '飞书 app_id 或 app_secret 未配置',
            'Feishu tenant_access_token failed' => '飞书 tenant_access_token 获取失败',
            'Feishu tenant_access_token is empty' => '飞书 tenant_access_token 为空',
            'DingTalk user list failed' => '钉钉用户列表获取失败',
            'DingTalk department listsub failed' => '钉钉子部门列表获取失败',
            'DingTalk gettoken failed' => '钉钉 access_token 获取失败',
            'DingTalk app_key or app_secret is empty' => '钉钉 app_key 或 app_secret 未配置',
            'DingTalk access_token is empty' => '钉钉 access_token 为空',
        ],
        'en_US' => [
            'IM Sync Assistant' => 'IM Sync Assistant',
            'IM Sync' => 'IM Sync',
            'IM Sync Settings' => 'Sync Settings',
            'Add sync setting' => 'Add sync setting',
            'Edit sync setting' => 'Edit sync setting',
            'Setting name' => 'Setting name',
            'Credentials' => 'Credentials',
            'Actions' => 'Actions',
            'Enabled' => 'Enabled',
            'Disabled' => 'Disabled',
            'Enable' => 'Enable',
            'Disable' => 'Disable',
            'Edit' => 'Edit',
            'Delete' => 'Delete',
            'Save' => 'Save',
            'Cancel' => 'Cancel',
            'Active sync setting' => 'Active sync setting',
            'Sync settings' => 'Sync settings',
            'Go to IM Sync' => 'Back to IM Sync',
            'Go to IM Sync Assistant' => 'Back to IM Sync Assistant',
            'Go to IM Sync Settings' => 'Go to Sync Settings',
            'Root department ID' => 'Root department ID',
            'Corp ID' => 'Corp ID',
            'Corp Secret' => 'Corp Secret',
            'App ID' => 'App ID',
            'App Secret' => 'App Secret',
            'App Key' => 'App Key',
            'Leave blank to keep unchanged' => 'Leave blank to keep unchanged',
            'Enable this setting (disables others)' => 'Enable this setting (disables others)',
            'WeCom/DingTalk default 1, Feishu default 0' => 'WeCom/DingTalk default 1, Feishu default 0',
            'Only one sync setting can be enabled at a time. The sync page uses the enabled setting.' => 'Only one sync setting can be enabled at a time. The IM Sync page uses the enabled setting.',
            'No sync settings yet. Click "Add sync setting" to create one.' => 'No sync settings yet. Click "Add sync setting" to create one.',
            'Confirm delete this sync setting?' => 'Confirm delete this sync setting?',
            'Settings saved' => 'Settings saved',
            'Settings deleted' => 'Settings deleted',
            'Save failed' => 'Save failed',
            'Delete failed' => 'Delete failed',
            'Sync setting not found' => 'Sync setting not found',
            'Setting name is required.' => 'Setting name is required.',
            'This sync setting is missing required credentials.' => 'This sync setting is missing required credentials.',
            'The enabled sync setting is missing required credentials. Please edit it in IM Sync Settings.' => 'The enabled sync setting is missing required credentials. Please edit it in Sync Settings.',
            'No enabled sync setting. Please add and enable one in IM Sync Settings.' => 'No enabled sync setting. Please add and enable one in Sync Settings.',
            'IM provider is not configured. Please configure it in IM Sync Settings.' => 'IM provider is not configured. Please configure it in Sync Settings.',
            'Instant Messaging Sync' => 'IM Sync Assistant',
            'WeCom' => 'WeCom',
            'Feishu' => 'Feishu',
            'DingTalk' => 'DingTalk',
            'Provider' => 'Provider',
            'Group Prefix' => 'Group Prefix',
            'User Match Field' => 'User Match Field',
            'Configuration File' => 'Configuration File',
            'Not configured' => 'Not configured',
            'Configured' => 'Configured',
            'Yes' => 'Yes',
            'No' => 'No',
            'Sync all departments' => 'Sync all departments',
            'Preview departments' => 'Preview departments',
            'Syncing...' => 'Syncing...',
            'Previewing...' => 'Previewing...',
            'Sync completed' => 'Sync completed',
            'Sync failed' => 'Sync failed',
            'Preview completed' => 'Preview completed',
            'Preview failed' => 'Preview failed',
            'No permission.' => 'No permission.',
            'Invalid input parameters.' => 'Invalid input parameters.',
            'IM provider is not configured. Please edit data/config.json.' => 'IM provider is not configured. Please edit data/config.json.',
            'Confirm sync all departments to Zabbix user groups?' => 'Confirm sync all departments to Zabbix user groups?',
            'Departments' => 'Departments',
            'Groups Created' => 'Groups Created',
            'Groups Updated' => 'Groups Updated',
            'Groups Existing' => 'Groups Existing',
            'Groups Failed' => 'Groups Failed',
            'Users Matched' => 'Users Matched',
            'Users Synced' => 'Users Synced',
            'Unmatched user list' => 'Unmatched user list',
            'Users Unmatched' => 'Users Unmatched',
            'Department' => 'Department',
            'User Group' => 'User Group',
            'IM Users' => 'IM Users',
            'Matched' => 'Matched',
            'Unmatched' => 'Unmatched',
            'Status' => 'Status',
            'Action' => 'Action',
            'Created' => 'Created',
            'Updated' => 'Updated',
            'Existing' => 'Existing',
            'Failed' => 'Failed',
            'No departments found' => 'No departments found',
            'Sync Result' => 'Sync Result',
            'Preview Result' => 'Preview Result',
            'username' => 'username',
            'email' => 'email',
            'alias' => 'alias',
            'Use full department path as group name' => 'Use full department path as group name',
            'Remove orphan groups on sync' => 'Remove orphan groups on sync',
            'Close' => 'Close',
            'Details' => 'Details',
            'Managed User Groups' => 'Managed User Groups',
            'Managed Users' => 'Managed Users',
            'Members' => 'Members',
            'No managed groups yet' => 'No managed groups yet',
            'Auto create users' => 'Auto create users',
            'Users Created' => 'Users Created',
            'Users Deleted' => 'Users Deleted',
            'Groups Deleted' => 'Groups Deleted',
            'System and manual users/groups are never deleted.' => 'System and manual users/groups are never deleted.',
            'Sync all users' => 'Sync all users',
            'Syncing users...' => 'Syncing users...',
            'User sync completed' => 'User sync completed',
            'User sync failed' => 'User sync failed',
            'User Sync Result' => 'User Sync Result',
            'Confirm sync all users and assign them to user groups?' => 'Confirm sync all users and assign them to user groups?',
            'User group not synced yet' => 'User group not synced yet',
            'default_roleid is required for Zabbix 7+ when auto creating users. Please edit data/config.json.' => 'default_roleid is required for Zabbix 7+ when auto creating users. Please edit data/config.json.',
            'Some departments are not synced yet. Run sync all departments before syncing users.' => 'Some departments are not synced yet. Run sync all departments before syncing users.',
            'IM raw user data' => 'IM raw user data',
            'IM User ID' => 'IM User ID',
            'Name' => 'Name',
            'Zabbix Username' => 'Zabbix Username',
            'Zabbix create debug' => 'Zabbix create debug',
            'Error' => 'Error',
            'WeCom API raw response' => 'WeCom API raw response',
            'IM API raw response' => 'IM API raw response',
            'API action' => 'API action',
            'HTTP raw body' => 'HTTP raw body',
            'Parsed response' => 'Parsed response',
            'Extracted userids' => 'Extracted userids',
            'Message' => 'Message',
            'Auto creating users requires Super Admin permission. Zabbix 7 user.create is Super Admin only.' => 'Auto creating users requires Super Admin permission. Zabbix 7 user.create is Super Admin only.',
            'Zabbix API call failed' => 'Zabbix API call failed',
            'check Zabbix PHP log or Authentication password policy' => 'check Zabbix PHP log or Authentication password policy',
            'Unknown API error' => 'Unknown API error',
            'skipped' => 'skipped',
            'failed' => 'failed',
            'updated' => 'updated',
            'created' => 'created',
            'renamed' => 'renamed',
            'existing' => 'existing',
            'linked' => 'linked',
            'matched' => 'matched',
            'unmatched' => 'unmatched',
            'preview' => 'preview',
            'User sync list' => 'User sync list',
            'Username' => 'Username',
            'Mobile' => 'Mobile',
            'Password' => 'Password',
            'New user passwords are shown only once. Please save them securely.' => 'New user passwords are shown only once. Please save them securely.',
            'Show IM raw user data' => 'Show IM raw user data',
            'Hide IM raw user data' => 'Hide IM raw user data',
            'Show IM API raw response' => 'Show IM API raw response',
            'Hide IM API raw response' => 'Hide IM API raw response',
            'No synced users' => 'No synced users',
            'Email' => 'Email',
            'Username or password is empty.' => 'Username or password is empty.',
            'API returned unexpected result: %s' => 'API returned unexpected result: %s',
            'Group %s is not managed by module' => 'Group %s is not managed by module',
            'Failed to encode config' => 'Failed to encode config',
            'Failed to write config file: %s' => 'Failed to write config file: %s',
            'DingTalk root department' => 'DingTalk root department',
            'Feishu root department' => 'Feishu root department',
            'Department %s' => 'Department %s',
            'HTTP error' => 'HTTP error',
            'WeCom department list failed' => 'WeCom department list failed',
            'WeCom user list failed' => 'WeCom user list failed',
            'WeCom gettoken failed' => 'WeCom gettoken failed',
            'WeCom corp_id or corp_secret is empty' => 'WeCom corp_id or corp_secret is empty',
            'WeCom access_token is empty' => 'WeCom access_token is empty',
            'Feishu user list failed' => 'Feishu user list failed',
            'Feishu department list failed' => 'Feishu department list failed',
            'Feishu app_id or app_secret is empty' => 'Feishu app_id or app_secret is empty',
            'Feishu tenant_access_token failed' => 'Feishu tenant_access_token failed',
            'Feishu tenant_access_token is empty' => 'Feishu tenant_access_token is empty',
            'DingTalk user list failed' => 'DingTalk user list failed',
            'DingTalk department listsub failed' => 'DingTalk department listsub failed',
            'DingTalk gettoken failed' => 'DingTalk gettoken failed',
            'DingTalk app_key or app_secret is empty' => 'DingTalk app_key or app_secret is empty',
            'DingTalk access_token is empty' => 'DingTalk access_token is empty',
        ],
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

        try {
            if (isset($GLOBALS['ZBX_LOCALES']) && isset($GLOBALS['ZBX_LOCALES']['selected'])) {
                return $GLOBALS['ZBX_LOCALES']['selected'];
            }
        } catch (\Throwable $e) {
        }

        if (isset($_SESSION['zbx_lang']) && !empty($_SESSION['zbx_lang'])) {
            return $_SESSION['zbx_lang'];
        }
        if (isset($_SESSION['lang']) && !empty($_SESSION['lang'])) {
            return $_SESSION['lang'];
        }

        $apiLang = self::getUserLanguageFromAPI();
        if (!empty($apiLang)) {
            return $apiLang;
        }

        return self::getUserLanguageFromDatabase();
    }

    private static function getUserLanguageFromAPI() {
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

            $apiClass = null;
            if (class_exists('API') || class_exists('\API')) {
                $apiClass = '\API';
            }

            if ($apiClass && method_exists($apiClass, 'User')) {
                $users = $apiClass::User()->get([
                    'output' => ['lang'],
                    'userids' => $userid,
                    'limit' => 1,
                ]);

                if (!empty($users) && isset($users[0]['lang'])) {
                    return $users[0]['lang'];
                }
            }
        } catch (\Throwable $e) {
        }

        return null;
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

            $stmt = $pdo->prepare('SELECT lang FROM users WHERE userid = ? LIMIT 1');
            $stmt->execute([$userid]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result && isset($result['lang'])) {
                return $result['lang'];
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
            'default' => self::LANG_DEFAULT,
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
        return sprintf(self::t($key), ...$args);
    }

    public static function getCurrentLanguage() {
        return self::detectLanguage();
    }

    public static function resetLanguage() {
        self::$currentLanguage = null;
    }

    public static function getLanguageDetectionInfo() {
        return [
            'detected' => self::detectLanguage(),
            'zabbix_user_lang' => self::getUserLanguageFromZabbix(),
            'db_lang' => self::getUserLanguageFromDatabase(),
            'system_lang' => self::getSystemDefaultLanguage(),
            'supported_locales' => array_keys(self::$translations),
        ];
    }

    public static function isChinese() {
        return self::detectLanguage() === 'zh_CN';
    }
}
