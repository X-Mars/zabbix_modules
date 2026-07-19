<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** Language helper for Zabbix Clonehosts module.
** Auto-detects Zabbix system language and provides bilingual (zh_CN / en_GB) translations.
** No gettext dependency — pure PHP array-based translation.
*/

namespace Modules\ZabbixClonehosts;

class LangHelper {

	/** @var string|null Cached current language */
	private static $lang = null;

	/** @var array Translation table: key => [en_GB => English, zh_CN => Chinese] */
	private static $translations = [
		// ===== Menu =====
		'menu.clonehosts' => [
			'en_GB' => 'Host Batch Import',
			'zh_CN' => '主机批量导入'
		],

		// ===== Step 1: Source Host Selection =====
		'step1.title' => [
			'en_GB' => 'Step 1: Select Source Host',
			'zh_CN' => '第一步：选择克隆源主机'
		],
		'step1.description' => [
			'en_GB' => 'Select an existing monitored host. Its configuration (groups, templates, tags, macros, interfaces, TLS settings, etc.) will serve as the baseline template for all cloned hosts. Fields not specified in the import data will inherit values from this host.',
			'zh_CN' => '选择一个已有的监控主机。其配置（群组、模板、标签、宏、接口、TLS设置等）将作为所有克隆主机的基准模板。导入数据中未指定的字段将继承该主机的配置值。'
		],
		'step1.select_placeholder' => [
			'en_GB' => '— Select Source Host —',
			'zh_CN' => '— 选择源主机 —'
		],
		'step1.load_btn' => [
			'en_GB' => 'Load Source Host Info',
			'zh_CN' => '加载源主机信息'
		],
		'step1.loading' => [
			'en_GB' => 'Loading...',
			'zh_CN' => '加载中...'
		],
		'step1.load_failed' => [
			'en_GB' => 'Failed to load source host info',
			'zh_CN' => '加载源主机信息失败'
		],
		'step1.load_failed_network' => [
			'en_GB' => 'Failed to load source host info, please check network connection.',
			'zh_CN' => '加载源主机信息失败，请检查网络连接。'
		],
		'step1.source_host_label' => [
			'en_GB' => 'Source Host: ',
			'zh_CN' => '源主机：'
		],
		'step1.inherited_note' => [
			'en_GB' => 'Fields not specified in the import data will inherit the above configuration.',
			'zh_CN' => '导入数据中未指定的字段将继承以上配置。'
		],

		// ===== Host Filter =====
		'filter.host_group' => [
			'en_GB' => 'Host Group',
			'zh_CN' => '主机群组'
		],
		'filter.all_groups' => [
			'en_GB' => '— All Groups —',
			'zh_CN' => '— 全部群组 —'
		],
		'filter.host_name' => [
			'en_GB' => 'Host Name',
			'zh_CN' => '主机名称'
		],
		'filter.name_placeholder' => [
			'en_GB' => 'Search by host name...',
			'zh_CN' => '搜索主机名称...'
		],
		'filter.count' => [
			'en_GB' => 'Showing {total} of {all} hosts',
			'zh_CN' => '显示 {total}/{all} 台主机'
		],
		'filter.no_match' => [
			'en_GB' => 'No matching hosts found',
			'zh_CN' => '未找到匹配的主机'
		],

		// ===== Unified Search Box =====
		'search.placeholder' => [
			'en_GB' => 'Search hosts or host groups...',
			'zh_CN' => '搜索主机或主机群组...'
		],
		'search.hint' => [
			'en_GB' => 'Type to search. Click a host or host group in the dropdown to filter the list below.',
			'zh_CN' => '输入关键字搜索。点击下拉框中的主机或主机群组，即可筛选下方列表。'
		],
		'search.category.host' => [
			'en_GB' => 'Hosts',
			'zh_CN' => '主机'
		],
		'search.category.group' => [
			'en_GB' => 'Host Groups',
			'zh_CN' => '主机群组'
		],
		'search.no_match' => [
			'en_GB' => 'No matching data',
			'zh_CN' => '无匹配数据'
		],
		'search.remove' => [
			'en_GB' => 'Remove',
			'zh_CN' => '移除'
		],

		// ===== Multiselect Control =====
		'multiselect.search_placeholder' => [
			'en_GB' => 'Type to search...',
			'zh_CN' => '输入关键字搜索...'
		],
		'multiselect.select_btn' => [
			'en_GB' => 'Select',
			'zh_CN' => '选择'
		],
		'multiselect.popup_title_group' => [
			'en_GB' => 'Select Host Groups',
			'zh_CN' => '选择主机群组'
		],
		'multiselect.popup_title_host' => [
			'en_GB' => 'Select Hosts',
			'zh_CN' => '选择主机'
		],
		'multiselect.popup_search_placeholder' => [
			'en_GB' => 'Search...',
			'zh_CN' => '搜索...'
		],
		'multiselect.popup_name_header' => [
			'en_GB' => 'Name',
			'zh_CN' => '名称'
		],
		'multiselect.popup_count' => [
			'en_GB' => '{count} selected',
			'zh_CN' => '已选 {count} 项'
		],
		'multiselect.popup_cancel' => [
			'en_GB' => 'Cancel',
			'zh_CN' => '取消'
		],
		'multiselect.popup_apply' => [
			'en_GB' => 'Apply',
			'zh_CN' => '应用'
		],

		// ===== Step 2: Data Input =====
		'step2.title' => [
			'en_GB' => 'Step 2: Enter Host Data',
			'zh_CN' => '第二步：录入主机数据'
		],
		'step2.description' => [
			'en_GB' => 'Upload a CSV file or enter host data directly in the table below. Host name and interface IP are required; other fields are optional and will inherit from the source host when left blank.',
			'zh_CN' => '上传CSV文件或在下方表格中直接录入主机数据。主机名称和接口IP为必填项，其他字段为选填，留空时将继承源主机的配置。'
		],
		'step2.tab_file' => [
			'en_GB' => 'File Upload (CSV)',
			'zh_CN' => '文件上传 (CSV)'
		],
		'step2.tab_table' => [
			'en_GB' => 'Online Table Entry',
			'zh_CN' => '在线表格录入'
		],

		// ===== CSV Upload =====
		'csv.parse_btn' => [
			'en_GB' => 'Parse CSV File',
			'zh_CN' => '解析CSV文件'
		],
		'csv.download_template' => [
			'en_GB' => 'Download CSV Template',
			'zh_CN' => '下载CSV模板'
		],
		'csv.encoding_hint' => [
			'en_GB' => 'Tip: If Chinese characters in the CSV appear garbled, try switching encoding to GBK and re-parse. The template file uses UTF-8 encoding and can be opened directly in Excel.',
			'zh_CN' => '提示：若CSV文件中文显示乱码，请尝试切换编码为 GBK 后重新解析。模板文件使用 UTF-8 编码，可直接在 Excel 中打开编辑。'
		],
		'csv.no_data' => [
			'en_GB' => 'No valid data rows found in CSV file.',
			'zh_CN' => 'CSV文件中未找到有效数据行。'
		],
		'csv.parsed_rows' => [
			'en_GB' => 'Parsed: ',
			'zh_CN' => '解析：'
		],
		'csv.valid_rows' => [
			'en_GB' => 'Valid: ',
			'zh_CN' => '有效：'
		],
		'csv.invalid_rows' => [
			'en_GB' => 'Invalid (missing required fields): ',
			'zh_CN' => '无效（缺少必填字段）：'
		],
		'csv.more_rows' => [
			'en_GB' => '... ',
			'zh_CN' => '... 还有 '
		],
		'csv.more_rows_suffix' => [
			'en_GB' => ' more rows',
			'zh_CN' => ' 行'
		],

		// ===== CSV Headers =====
		'csv.header.host' => [
			'en_GB' => 'Host Name(*)',
			'zh_CN' => '主机名称(*)'
		],
		'csv.header.visible_name' => [
			'en_GB' => 'Visible Name',
			'zh_CN' => '可见的名称'
		],
		'csv.header.ip' => [
			'en_GB' => 'Interface IP(*)',
			'zh_CN' => '接口IP(*)'
		],
		'csv.header.port' => [
			'en_GB' => 'Port',
			'zh_CN' => '端口'
		],
		'csv.header.groups' => [
			'en_GB' => 'Host Groups',
			'zh_CN' => '主机群组'
		],
		'csv.header.templates' => [
			'en_GB' => 'Templates',
			'zh_CN' => '模板'
		],
		'csv.header.tags' => [
			'en_GB' => 'Tags',
			'zh_CN' => '标签'
		],
		'csv.header.macros' => [
			'en_GB' => 'Macros',
			'zh_CN' => '宏'
		],
		'csv.header.description' => [
			'en_GB' => 'Description',
			'zh_CN' => '描述'
		],

		// ===== Table Toolbar =====
		'table.add_row' => [
			'en_GB' => 'Add Row',
			'zh_CN' => '添加行'
		],
		'table.remove_row' => [
			'en_GB' => 'Remove Selected Rows',
			'zh_CN' => '删除选中行'
		],
		'table.clear_all' => [
			'en_GB' => 'Clear All',
			'zh_CN' => '清空全部'
		],
		'table.row_count' => [
			'en_GB' => 'Total rows: ',
			'zh_CN' => '总行数：'
		],
		'table.clear_confirm' => [
			'en_GB' => 'Are you sure you want to clear all rows?',
			'zh_CN' => '确定要清空所有行吗？'
		],
		'table.placeholder.required' => [
			'en_GB' => 'Required',
			'zh_CN' => '必填'
		],
		'table.placeholder.inherited' => [
			'en_GB' => '(inherited)',
			'zh_CN' => '(继承)'
		],

		// ===== Column Headers =====
		'col.host_name' => [
			'en_GB' => 'Host Name',
			'zh_CN' => '主机名称'
		],
		'col.visible_name' => [
			'en_GB' => 'Visible Name',
			'zh_CN' => '可见的名称'
		],
		'col.interface_ip' => [
			'en_GB' => 'Interface IP',
			'zh_CN' => '接口IP'
		],
		'col.port' => [
			'en_GB' => 'Port',
			'zh_CN' => '端口'
		],
		'col.host_groups' => [
			'en_GB' => 'Host Groups',
			'zh_CN' => '主机群组'
		],
		'col.templates' => [
			'en_GB' => 'Templates',
			'zh_CN' => '模板'
		],
		'col.tags' => [
			'en_GB' => 'Tags',
			'zh_CN' => '标签'
		],
		'col.macros' => [
			'en_GB' => 'Macros',
			'zh_CN' => '宏'
		],
		'col.description' => [
			'en_GB' => 'Description',
			'zh_CN' => '描述'
		],
		'col.status' => [
			'en_GB' => 'Status',
			'zh_CN' => '状态'
		],
		'col.result' => [
			'en_GB' => 'Result',
			'zh_CN' => '结果'
		],
		'col.host_id' => [
			'en_GB' => 'Host ID',
			'zh_CN' => '主机ID'
		],
		'col.error' => [
			'en_GB' => 'Error',
			'zh_CN' => '错误'
		],

		// ===== Preview =====
		'preview.btn' => [
			'en_GB' => 'Preview Import',
			'zh_CN' => '预览导入'
		],
		'preview.hint_no_data' => [
			'en_GB' => 'Please fill in at least one valid row of host data to continue.',
			'zh_CN' => '请至少填写一行有效的主机数据以继续。'
		],
		'preview.hint_no_source' => [
			'en_GB' => 'Please select a source host first.',
			'zh_CN' => '请先选择源主机。'
		],
		'preview.hint_ready' => [
			'en_GB' => 'Ready to preview import.',
			'zh_CN' => '可以预览导入。'
		],
		'preview.alert_no_data' => [
			'en_GB' => 'Please enter at least one valid host (host name and IP are required).',
			'zh_CN' => '请至少输入一台有效的主机（主机名和IP为必填项）。'
		],

		// ===== Preview Page =====
		'preview.source_config' => [
			'en_GB' => 'Source Host Configuration (will be inherited)',
			'zh_CN' => '源主机配置（将被继承）'
		],
		'preview.interfaces' => [
			'en_GB' => 'Interfaces',
			'zh_CN' => '接口'
		],
		'preview.none' => [
			'en_GB' => 'None',
			'zh_CN' => '无'
		],
		'preview.total' => [
			'en_GB' => 'Total: ',
			'zh_CN' => '总计：'
		],
		'preview.conflicts' => [
			'en_GB' => 'Conflicts: ',
			'zh_CN' => '冲突：'
		],
		'preview.importable' => [
			'en_GB' => 'Importable: ',
			'zh_CN' => '可导入：'
		],
		'preview.pending_count' => [
			'en_GB' => 'Hosts to import: ',
			'zh_CN' => '待导入主机数：'
		],
		'preview.no_conflicts' => [
			'en_GB' => 'No conflicts detected',
			'zh_CN' => '未检测到冲突'
		],
		'preview.title' => [
			'en_GB' => 'Host Import Preview',
			'zh_CN' => '主机导入预览'
		],
		'preview.inherited' => [
			'en_GB' => '(inherited)',
			'zh_CN' => '(继承)'
		],
		'preview.ready' => [
			'en_GB' => 'Ready',
			'zh_CN' => '就绪'
		],
		'preview.back' => [
			'en_GB' => 'Back',
			'zh_CN' => '返回'
		],
		'preview.start_import' => [
			'en_GB' => 'Start Import',
			'zh_CN' => '开始导入'
		],
		'preview.resolve_conflicts' => [
			'en_GB' => 'Conflicting hosts will be skipped. You can uncheck ready hosts to exclude them too.',
			'zh_CN' => '冲突主机将被跳过，您也可以取消勾选就绪主机以排除。'
		],
		'preview.back_edit' => [
			'en_GB' => 'Back to Edit',
			'zh_CN' => '返回编辑'
		],
		'preview.import_selected' => [
			'en_GB' => 'Import Selected ({count})',
			'zh_CN' => '导入所选 ({count})'
		],
		'preview.no_selected' => [
			'en_GB' => 'Please select at least one ready host to import.',
			'zh_CN' => '请至少勾选一台就绪主机进行导入。'
		],
		'preview.skipped' => [
			'en_GB' => 'Skipped',
			'zh_CN' => '已跳过'
		],
		'preview.col_select' => [
			'en_GB' => 'Import',
			'zh_CN' => '导入'
		],

		// ===== Field Status Legend =====
		'legend.exists' => [
			'en_GB' => 'Exists → Link directly',
			'zh_CN' => '已存在 → 直接关联'
		],
		'legend.new' => [
			'en_GB' => 'Will be created',
			'zh_CN' => '将新建'
		],
		'legend.not_found' => [
			'en_GB' => 'Not found',
			'zh_CN' => '未找到'
		],
		'legend.inherited' => [
			'en_GB' => 'Inherited from source host',
			'zh_CN' => '继承自源主机'
		],
		'legend.exists_tooltip' => [
			'en_GB' => 'Already exists, will be linked directly',
			'zh_CN' => '已存在，将直接关联'
		],
		'legend.new_tooltip_group' => [
			'en_GB' => 'This host group will be created',
			'zh_CN' => '将新建此主机群组'
		],
		'legend.not_found_tooltip_template' => [
			'en_GB' => 'Template not found, please verify the template name',
			'zh_CN' => '模板未找到，请确认模板名称是否正确'
		],

		// ===== Import Progress =====
		'import.progress_title' => [
			'en_GB' => 'Import Progress',
			'zh_CN' => '导入进度'
		],
		'import.importing' => [
			'en_GB' => 'Importing...',
			'zh_CN' => '导入中...'
		],
		'import.complete' => [
			'en_GB' => 'Import Complete',
			'zh_CN' => '导入完成'
		],
		'import.results_title' => [
			'en_GB' => 'Import Results',
			'zh_CN' => '导入结果'
		],
		'import.success' => [
			'en_GB' => 'Success: ',
			'zh_CN' => '成功：'
		],
		'import.failed' => [
			'en_GB' => 'Failed: ',
			'zh_CN' => '失败：'
		],
		'import.download_report' => [
			'en_GB' => 'Download Report',
			'zh_CN' => '下载报告'
		],
		'import.result_success' => [
			'en_GB' => 'Success',
			'zh_CN' => '成功'
		],
		'import.result_failed' => [
			'en_GB' => 'Failed',
			'zh_CN' => '失败'
		],
		'import.ajax_error' => [
			'en_GB' => 'AJAX error: ',
			'zh_CN' => 'AJAX错误：'
		],
		'import.unknown_error' => [
			'en_GB' => 'Unknown',
			'zh_CN' => '未知'
		],

		// ===== Report CSV Headers =====
		'report.col_row' => [
			'en_GB' => '#',
			'zh_CN' => '#'
		],
		'report.col_result' => [
			'en_GB' => 'Result',
			'zh_CN' => '结果'
		],
		'report.col_host' => [
			'en_GB' => 'Host Name',
			'zh_CN' => '主机名称'
		],
		'report.col_ip' => [
			'en_GB' => 'Interface IP',
			'zh_CN' => '接口IP'
		],
		'report.col_hostid' => [
			'en_GB' => 'Host ID',
			'zh_CN' => '主机ID'
		],
		'report.col_error' => [
			'en_GB' => 'Error',
			'zh_CN' => '错误'
		],

		// ===== Error Messages (from controllers) =====
		'err.host_required' => [
			'en_GB' => 'Host name is required',
			'zh_CN' => '主机名称为必填项'
		],
		'err.ip_required' => [
			'en_GB' => 'Interface IP is required',
			'zh_CN' => '接口IP为必填项'
		],
		'err.host_exists' => [
			'en_GB' => 'This host already exists',
			'zh_CN' => '该主机已存在'
		],
		'err.template_exists' => [
			'en_GB' => 'A template with the same name already exists',
			'zh_CN' => '同名模板已存在'
		],
		'err.source_not_found' => [
			'en_GB' => 'Source host not found',
			'zh_CN' => '源主机未找到'
		],
		'err.no_groups' => [
			'en_GB' => 'No host groups available (not specified and cannot inherit)',
			'zh_CN' => '没有可用的主机群组（未指定且无法继承）'
		],
		'err.unknown_api_response' => [
			'en_GB' => 'Unknown API response',
			'zh_CN' => '未知的API响应'
		],
		'err.batch_duplicate' => [
			'en_GB' => 'Duplicate host name within batch',
			'zh_CN' => '批次内主机名称重复'
		],
		'err.host_exists_in_zabbix' => [
			'en_GB' => 'This host name already exists in Zabbix',
			'zh_CN' => '该主机名称在Zabbix中已存在'
		],

		// ===== Interface Type Names =====
		'iface.Agent' => [
			'en_GB' => 'Agent',
			'zh_CN' => 'Agent'
		],
		'iface.SNMP' => [
			'en_GB' => 'SNMP',
			'zh_CN' => 'SNMP'
		],
		'iface.IPMI' => [
			'en_GB' => 'IPMI',
			'zh_CN' => 'IPMI'
		],
		'iface.JMX' => [
			'en_GB' => 'JMX',
			'zh_CN' => 'JMX'
		],
		'iface.Type' => [
			'en_GB' => 'Type ',
			'zh_CN' => 'Type '
		],
	];

	/**
	 * Detect the current user's language from Zabbix.
	 * Priority: CWebUser::$data['lang'] → API user.get → default en_GB
	 *
	 * @return string Language code (e.g., 'zh_CN', 'en_GB')
	 */
	public static function getLang(): string {
		if (self::$lang !== null) {
			return self::$lang;
		}

		// Method 1: Try CWebUser (available in Zabbix frontend context)
		if (class_exists('CWebUser', false) && isset(\CWebUser::$data['lang'])) {
			self::$lang = \CWebUser::$data['lang'];
			return self::$lang;
		}

		// Method 2: Try to get from the session/API
		try {
			$user = \API::User()->get([
				'output' => ['lang'],
				'userids' => [\CWebUser::$data['userid'] ?? 0]
			]);
			if (!empty($user) && !empty($user[0]['lang'])) {
				self::$lang = $user[0]['lang'];
				return self::$lang;
			}
		} catch (\Exception $e) {
			// Fall through to default
		}

		// Default: English
		self::$lang = 'en_GB';
		return self::$lang;
	}

	/**
	 * Check if the current language is Chinese.
	 *
	 * @return bool
	 */
	public static function isChinese(): bool {
		return self::getLang() === 'zh_CN' || strpos(self::getLang(), 'zh') === 0;
	}

	/**
	 * Translate a key to the current language, with optional parameter substitution.
	 * Supports {placeholder} style parameters in the translation string.
	 *
	 * @param string $key Translation key
	 * @param array $params Optional ['{placeholder}' => 'value'] pairs for substitution
	 * @return string Translated string (falls back to English, then to the key itself)
	 */
	public static function t(string $key, array $params = []): string {
		$lang = self::getLang();

		if (isset(self::$translations[$key][$lang])) {
			$str = self::$translations[$key][$lang];
		} elseif (isset(self::$translations[$key]['en_GB'])) {
			$str = self::$translations[$key]['en_GB'];
		} else {
			$str = $key;
		}

		if (!empty($params)) {
			$str = strtr($str, $params);
		}

		return $str;
	}

	/**
	 * Get all translations for the current language as a JSON-serializable array.
	 * Used to pass translations to JavaScript.
	 *
	 * @return array Key => translated string
	 */
	public static function getAllForJs(): array {
		$lang = self::getLang();
		$result = [];

		foreach (self::$translations as $key => $langs) {
			if (isset($langs[$lang])) {
				$result[$key] = $langs[$lang];
			} elseif (isset($langs['en_GB'])) {
				$result[$key] = $langs['en_GB'];
			}
		}

		return $result;
	}
}
