<?php

namespace Modules\ZabbixBackupRestore\Lib;

final class LanguageManager {

	private const CHINESE = [
		'Backup and restore' => '备份与还原',
		'Export backup' => '导出备份',
		'Restore backup' => '还原备份',
		'Configuration to export' => '选择要导出的配置',
		'Select all' => '全选',
		'Download YAML' => '下载 YAML',
		'Preparing backup…' => '正在生成备份…',
		'PHP ran out of memory while processing the backup.' => '处理备份时 PHP 内存不足。',
		'Monitoring' => '监控配置',
		'Access and notifications' => '访问权限与通知',
		'Services and shared configuration' => '服务与共享配置',
		'Hosts' => '主机',
		'Templates' => '模板',
		'Triggers' => '触发器',
		'Graphs' => '图形',
		'Discovery checks' => '发现检查',
		'Frontend modules' => '前端模块',
		'MFA methods' => '多因素认证方式',
		'User directories' => '用户目录',
		'Host groups' => '主机组',
		'Template groups' => '模板组',
		'Network discovery rules' => '网络发现规则',
		'Maintenance periods' => '维护计划',
		'Proxies' => '代理',
		'Proxy groups' => '代理组',
		'Maps' => '网络拓扑图',
		'Images' => '图像',
		'Dashboards' => '仪表板',
		'Users' => '用户',
		'User groups' => '用户组',
		'User roles' => '用户角色',
		'Media types' => '媒体类型',
		'Actions' => '动作',
		'Scripts' => '脚本',
		'Services and dependencies' => '服务及依赖关系',
		'Service-level agreements' => '服务级别协议',
		'Global macros' => '全局宏',
		'Global regular expressions' => '全局正则表达式',
		'Event correlations' => '事件关联',
		'Includes items, triggers, low-level discovery and user macros.' => '包含监控项、触发器、低级别发现规则及用户宏。',
		'Include related configuration or make sure it already exists on the destination.' => '请一并导出依赖的配置，或确保这些配置已存在于目标实例。',
		'Backup files may contain credentials and commands. Store them securely and restore only trusted files.' => '备份文件可能包含凭据和可执行命令。请妥善保存，仅还原可信来源的文件。',
		'Choose a YAML file' => '选择 YAML 文件',
		'or drag it here' => '或拖到此处',
		'Choose a .yaml or .yml backup file (up to %s MiB).' => '请选择 .yaml 或 .yml 备份文件，当前 PHP 上传上限为 %s MiB。',
		'Update existing configuration' => '更新目标实例中的同名或同 UUID 配置',
		'Preview restore' => '预览还原',
		'Restore configuration' => '执行还原',
		'Preview ready' => '预览已生成',
		'Source version' => '来源版本',
		'Destination version' => '目标版本',
		'Configuration' => '配置类型',
		'Records in file' => '文件内记录数',
		'API validation runs during restore. A failed operation rolls back the entire batch.' => '执行还原时进行 API 校验。任一步骤失败，整批配置将回滚。',
		'Restoring configuration…' => '正在还原配置…',
		'Checking backup…' => '正在检查备份…',
		'Restore complete.' => '配置还原完成。',
		'The request failed. Check the file size and sign-in status, then try again.' => '请求失败。请检查文件大小及登录状态后重试。',
		'The server could not complete the request (HTTP %s).' => '服务器未能完成请求（HTTP %s）。',
		'Choose at least one configuration type.' => '请至少选择一种配置类型。',
		'The selected configuration is invalid.' => '所选配置类型无效。',
		'The selection contains no exportable records.' => '所选范围内没有可导出的配置。',
		'Upload a valid YAML backup file.' => '请上传有效的 YAML 备份文件。',
		'The backup exceeds the upload size limit.' => '备份超过允许的上传大小。',
		'The YAML backup exceeds the 500 MiB file limit.' => 'YAML 备份超过 500 MiB 文件上限。',
		'Could not open a temporary backup file.' => '无法打开临时备份文件。',
		'Could not write the temporary backup file.' => '无法写入临时备份文件。',
		'The YAML backup is malformed or uses unsupported syntax.' => 'YAML 备份格式错误或使用了不支持的语法。',
		'This file is not a supported module backup.' => '此文件不是受支持的模块备份。',
		'This module supports Zabbix 7.0 and 8.0.' => '此模块支持 Zabbix 7.0 和 8.0。',
		'This backup requires a newer Zabbix version.' => '此备份要求更高版本的 Zabbix。',
		'Preview this file and restore option again before restoring.' => '执行还原前，请重新预览此文件及还原选项。',
		'Missing or ambiguous reference to %s (%s).' => '%s 引用不存在或不唯一（%s）。',
		'A required reference in %s is invalid.' => '%s 中的必需引用无效。',
		'Duplicate record in %s (%s).' => '%s 中存在重复记录（%s）。',
		'Invalid record in %s.' => '%s 中存在无效记录。',
		'Could not start the restore transaction.' => '无法启动还原事务。',
		'Could not commit the restore transaction.' => '无法提交还原事务。',
		'Zabbix API call %s failed.' => 'Zabbix API 调用 %s 失败。',
		'An unexpected error occurred.' => '发生意外错误。',
		'%d secret macro values must be configured on the destination.' => '有 %d 个机密宏值需要在目标实例配置。',
		'%d externally provisioned users must be provisioned on the destination.' => '有 %d 个外部预配用户需要在目标实例重新预配。',
		'New local accounts require a password reset on the destination.' => '新建本地用户后，需要在目标实例重置密码。'
	];

	public static function t(string $message): string {
		$lang = \CWebUser::$data['lang'] ?? 'default';
		if ($lang === 'default') {
			$lang = \CSettingsHelper::getPublic(\CSettingsHelper::DEFAULT_LANG);
		}

		return stripos((string) $lang, 'zh') === 0 ? (self::CHINESE[$message] ?? $message) : $message;
	}

	public static function format(string $message, ...$arguments): string {
		return sprintf(self::t($message), ...$arguments);
	}
}
