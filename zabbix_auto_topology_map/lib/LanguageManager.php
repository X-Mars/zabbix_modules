<?php declare(strict_types = 1);

namespace Modules\Topomap\Lib;

/**
 * 模块内文案的中英文支持。
 *
 * 键就是中文原文（本模块的源文案是中文），英文环境下查 en_US 字典，
 * 查不到就返回原文，绝不会因为漏译而报错。
 *
 * 语言判定顺序与 Zabbix 前端一致：
 *   1) 当前用户的语言（CWebUser::get('lang')）；
 *   2) 用户选的是「默认（default）」或取不到时，用系统默认语言（settings.default_lang）；
 *   3) 都取不到时回退 en_US。
 * 只支持 zh_CN / en_US，其它语言一律按 en_US 处理（与 zabbix_cmdb 模块一致）。
 */
class LanguageManager {

	private const ZBX_DEFAULT_LANG = 'en_US';

	/** 当前请求内缓存的语言，null 表示还没判定过。 */
	private static ?string $currentLanguage = null;

	/** 英文词典：键 = 中文原文，值 = 英文。 */
	private const EN = [
		"默认" => "Default",
		"底部" => "Bottom",
		"左侧" => "Left",
		"右侧" => "Right",
		"顶部" => "Top",
		"包含" => "Contains",
		"等于" => "Equals",
		"不包含" => "Does not contain",
		"不等于" => "Does not equal",
		"存在" => "Exists",
		"不存在" => "Does not exist",
		"实线" => "Solid line",
		"粗线" => "Thick line",
		"点线" => "Dotted line",
		"虚线" => "Dashed line",
		"自动曲线" => "Auto curve",
		"自动直角" => "Auto orthogonal",
		"手动折点" => "Manual waypoints",
		"自动（源端优先）" => "Auto (source first)",
		"仅源端" => "Source only",
		"仅本端" => "Target only",
		"两端都关联" => "Both ends",
		"不关联" => "None",
		"矩形" => "Rectangle",
		"椭圆" => "Ellipse",
		"线" => "Line",
		"无" => "None",
		"扫描超时（" => "Scan timed out (",
		" 秒仍无返回），已跳过这批设备" => "s without response); skipped this batch",
		"接口返回 HTTP " => "API returned HTTP ",
		"：" => ": ",
		"接口返回空响应（HTTP " => "API returned an empty response (HTTP ",
		"）" => ")",
		"接口返回的不是 JSON：" => "API returned non-JSON data: ",
		"接口返回失败" => "API request failed",
		"菜单接口返回的不是 JSON" => "The menu API returned non-JSON data",
		"菜单数据为空" => "Menu data is empty",
		"无法打开主机菜单" => "Cannot open the host menu",
		"请求失败" => "Request failed",
		"主机" => "Host",
		"加载中…" => "Loading…",
		"共 " => "Total ",
		" 个拓扑图" => " maps",
		"当前用户没有创建 / 编辑拓扑图的权限，仅供查看" => "Your role cannot create or edit maps; read-only view",
		"加载失败：" => "Loading failed: ",
		" 已选" => " selected",
		"显示 " => "Showing ",
		"，共找到 " => ", found ",
		"没有匹配的拓扑图。" => "No matching maps.",
		"暂无拓扑图。点击右上角「创建拓扑图」新建一个。" => "No maps yet. Click \"Create map\" in the upper right corner to add one.",
		"元素 " => "Elements ",
		" 个" => "",
		"属性" => "Properties",
		"编辑" => "Edit",
		"删除 " => "Delete ",
		" 张拓扑图？" => " maps?",
		"删除后无法恢复。这些图与「监测 → 拓扑图」共用同一份数据，原生列表里也会一起消失。" => "This cannot be undone. These maps share data with Monitoring → Maps and will also disappear from the native list.",
		"删除" => "Delete",
		"已删除 " => "Deleted ",
		" 张拓扑图" => " maps",
		"删除失败：" => "Deletion failed: ",
		"已导出 " => "Exported ",
		"导出失败：" => "Export failed: ",
		"没有权限" => "No permission",
		"当前用户没有创建 / 编辑拓扑图的权限（需要角色里的「创建和编辑拓扑图」）。" => "Your role cannot create or edit maps (the role needs \"Create and edit maps\").",
		"创建拓扑图" => "Create map",
		"名称" => "Name",
		"输入拓扑图名称" => "Enter a map name",
		"画布尺寸" => "Canvas size",
		"自动" => "Auto",
		"创建后画布会随元素自动扩展，保存时按内容收紧到合适大小" => "The canvas expands with its elements and shrinks to fit the content when saving",
		"名称不能为空" => "The name cannot be empty",
		"创建中…" => "Creating…",
		"已创建「" => "Created \"",
		"」" => "\"",
		"创建失败：" => "Creation failed: ",
		"应用" => "Apply",
		"重设" => "Reset",
		"自动发现拓扑" => "Discover topology",
		"取消" => "Cancel",
		"开始发现" => "Start discovery",
		"当前拓扑图里没有主机元素，无法重新发现" => "The current map has no host elements; cannot rediscover",
		"重新发现并更新拓扑图" => "Rediscover and update map",
		"以当前图里的 " => "Using the ",
		" 台主机为起点重新扫描；新增设备/链路会并入当前图，" => " hosts in the current map as seeds; new devices/links are merged in and ",
		"已配置的带宽/流量监控项保留。" => " existing bandwidth/traffic item settings are kept.",
		"从交换机读取 LLDP / CDP 邻居表，自动生成拓扑图。起点设备需要配置 SNMP 接口（推荐 v2c）。" => "Reads the LLDP / CDP neighbor tables from switches and builds the map. Seed devices need an SNMP interface (v2c recommended).",
		"搜索主机名称…" => "Search host name…",
		"全选有 SNMP 的" => "Select all with SNMP",
		"已选 0 台" => "0 selected",
		"发现深度（1-10 层）" => "Discovery depth (1-10 levels)",
		"包含未纳管设备" => "Include unmanaged devices",
		"布局" => "Layout",
		"全部重排成树形" => "Rebuild as tree",
		"保留现有坐标" => "Keep current positions",
		"未再发现的自动连线" => "Auto links no longer discovered",
		"保留" => "Keep",
		"移除" => "Remove",
		"自动关联触发器关键词（一行一个）" => "Trigger keywords for auto-linking (one per line)",
		"处于关闭状态" => "link down",
		"一行一个，例如：\n处于关闭状态\nlink down" => "One per line, e.g.:\nlink down",
		"拓扑图名称" => "Map name",
		"加载主机失败：" => "Failed to load hosts: ",
		"没有匹配的主机（试试清空搜索词）" => "No matching hosts (try clearing the search)",
		"无 SNMP" => "No SNMP",
		"已选 " => "Selected ",
		" 台" => "",
		"自动发现 " => "Auto discovery ",
		"请先勾选至少一台起点设备。" => "Select at least one seed device first.",
		"开始发现：" => "Discovery started: ",
		" 台起点，深度 " => " seeds, depth ",
		"，协议 " => ", protocol ",
		"发现中…" => "Discovering…",
		"扫描 " => "Scanning ",
		"、" => ", ",
		" …" => " …",
		"扫描失败：" => "Scan failed: ",
		"读取失败" => "read failed",
		"：没有 LLDP / CDP / EDP 邻居（设备可能没开邻居发现协议）" => ": no LLDP / CDP / EDP neighbors (neighbor discovery may be disabled on the device)",
		"未命名设备" => "Unnamed device",
		"发现完成：" => "Discovery complete: ",
		" 台设备、" => " devices, ",
		" 条链路" => " links",
		"其中 " => "Of which ",
		" 台已在 Zabbix 里，" => " are in Zabbix and ",
		" 台为未纳管设备。" => " are unmanaged devices.",
		"更新拓扑图" => "Update map",
		"生成拓扑图" => "Generate map",
		"生成中…" => "Generating…",
		"、关联 " => ", linked ",
		" 个触发器" => " triggers",
		"已生成「" => "Generated \"",
		"」：" => "\": ",
		"生成失败：" => "Generation failed: ",
		"更新中…" => "Updating…",
		"，移除 " => ", removed ",
		" 条旧连线" => " old links",
		"重新发现完成：新增 " => "Rediscovery complete: added ",
		" 条链路，保留 " => " links, kept ",
		" 条链路配置" => " link settings",
		"已更新拓扑图：新增 " => "Map updated: added ",
		"更新失败：" => "Update failed: ",
		"打开失败：" => "Open failed: ",
		"这张拓扑图还没有元素" => "This map has no elements yet",
		"放弃未保存的修改？" => "Discard unsaved changes?",
		"当前拓扑图有未保存的修改，返回列表会丢失这些修改。" => "The current map has unsaved changes; returning to the list will discard them.",
		"放弃修改" => "Discard changes",
		"预览模式" => "Preview mode",
		"有未保存的修改" => "Unsaved changes",
		"点击「地图元素: 添加」开始布置拓扑图" => "Click \"Map elements: Add\" to start building the map",
		"带宽" => "Bandwidth",
		"上行流量" => "Inbound traffic",
		"下行流量" => "Outbound traffic",
		"折点模式：点击空白处添加折点，拖动圆点调整，双击圆点删除，Esc 完成" => "Waypoint mode: click empty space to add a point, drag dots to adjust, double-click a dot to delete, Esc to finish",
		"无法编辑该元素" => "Cannot edit this element",
		"该元素类型（主机组 / 触发器 / 嵌套图）由 Zabbix 原生拓扑图编辑器维护，在自动拓扑图里只能预览和移动位置。" => "This element type (host group / trigger / nested map) is maintained by the native Zabbix map editor; here it can only be previewed and moved.",
		"未知主机" => "Unknown host",
		"图片" => "Image",
		"已停用" => "Disabled",
		"在维护中" => "In maintenance",
		"正常" => "OK",
		" 个问题" => " problems",
		"%s 个问题" => "%s problems",
		"%s 个未确认问题" => "%s unacknowledged problems",
		"问题" => "problem",
		"未确认" => "Unacknowledged",
		"当前问题 " => "Current problems: ",
		"问题标签：" => "Problem tags: ",
		"自动 · " => "Auto · ",
		"画布尺寸不固定：最小铺满可视区域，往右/下拖元素时会自动扩展，" => "The canvas size is not fixed: at least the viewport, it expands when elements are dragged right/down, ",
		"拖到边缘时视图会自动跟着平移；保存时收缩到内容大小。这里显示的是当前实际尺寸。" => "and the view pans when you drag to the edge; it shrinks to the content size on save. This shows the current actual size.",
		"新的组件" => "New element",
		"移除模式：点击画布上要移除的" => "Remove mode: click the ",
		"地图元素" => "map element",
		"形" => "shape",
		"链接" => "link",
		"（Esc 取消）" => " to remove (Esc to cancel)",
		"链接模式：依次点击两个元素建立链接（Esc 取消）" => "Link mode: click two elements in order to create a link (Esc to cancel)",
		"链接模式：已选中「" => "Link mode: selected \"",
		"元素" => "element",
		"」，再点击另一个元素" => "\", now click another element",
		"拓扑图属性" => "Map properties",
		"自动（当前 " => "Auto (current ",
		" px）" => " px)",
		"往右 / 往下拖元素时画布会跟着变大，保存时按内容收紧，不会留下大片空白" => "The canvas grows when you drag elements right/down and shrinks to fit on save, leaving no large empty area",
		"已保存「" => "Saved \"",
		"」的属性" => "\" properties",
		"保存失败：" => "Save failed: ",
		"删除拓扑图？" => "Delete map?",
		"将永久删除「" => "This will permanently delete \"",
		"」及其所有元素、链接、形。此操作不可撤销。" => "\" and all its elements, links and shapes. This action cannot be undone.",
		"已删除「" => "Deleted \"",
		"关闭" => "Close",
		"类型" => "Type",
		"标签" => "Label",
		"可换行；选择主机后会自动填「主机名 + IP」" => "Line breaks are allowed; selecting a host fills in \"host name + IP\"",
		"标签位置" => "Label position",
		"在此输入搜索" => "Type to search",
		"选择" => "Select",
		"（" => "(",
		"问题标签" => "Problem tags",
		"与/或 (默认)" => "And/Or (default)",
		"或" => "Or",
		"添加" => "Add",
		"自动选择图标" => "Auto-select icon",
		"图标" => "Icon",
		"维护" => "Maintenance",
		"停用" => "Disabled",
		"请选择主机" => "Select a host",
		"标记" => "Tag",
		"值" => "Value",
		"（无可用图标）" => "(no icons available)",
		"拓扑图形状" => "Map shape",
		"文本" => "Text",
		"字体" => "Font",
		"字体大小" => "Font size",
		"颜色" => "Color",
		"居中" => "Center",
		"左" => "Left",
		"右" => "Right",
		"上" => "Top",
		"下" => "Bottom",
		"横向对齐" => "Horizontal align",
		"纵向对齐" => "Vertical align",
		"背景" => "Background",
		"边框" => "Border",
		"宽(px)" => "Width (px)",
		"坐标" => "Coordinates",
		"大小" => "Size",
		"高度(px)" => "Height (px)",
		"点" => "Point",
		"这条链路共 " => "This link has ",
		" 条成员连线（链路聚合），修改会同时应用到全部成员。" => " member connections (link aggregation); changes apply to all members.",
		"线型" => "Line type",
		"走线" => "Routing",
		"在画布上点选折点" => "Pick waypoints on canvas",
		"自动直角按横/竖折线走；选「手动折点」后点按钮，" => "Auto orthogonal routes along horizontal/vertical segments; when \"manual waypoints\" is selected, click the button, ",
		"在画布空白处点击加点、拖动圆点调整、双击圆点删除，Esc 完成。" => "then click empty canvas to add points, drag dots to adjust, double-click a dot to delete, Esc to finish.",
		"触发器来源" => "Trigger source",
		"源端端口：" => "Source port: ",
		"未知" => "unknown",
		"　本端端口：" => "  Target port: ",
		"（源端 = 连线起点，本端 = 连线终点）" => " (source = link start, target = link end)",
		"选择…" => "Select…",
		"清空" => "Clear",
		"监控项只从这条链路两端的主机里选；链路聚合的多个成员口可以全选，显示时求和。" => "Items are chosen only from the hosts at both ends of this link; for aggregated links you can select multiple member ports and the values are summed.",
		"触发器" => "Trigger",
		"未关联触发器" => "No triggers linked",
		"自动关联只匹配端口状态类关键词（link down / 处于关闭状态 / 接口关闭…）" => "Auto-linking matches only port-status keywords (link down / disabled / …) ",
		"并按连线上的端口名定位；手工建的连线在这里直接选，保存后不会被自动匹配覆盖。" => "and locates them by the port name on the link; manually created links can be selected here and will not be overwritten by auto-matching.",
		"确定" => "OK",
		"没有匹配的主机" => "No matching hosts",
		"（已删除或无权访问）" => " (deleted or no access)",
		"未选择" => "Not selected",
		" 个监控项" => " items",
		"Zabbix 代理" => "Zabbix agent",
		"Zabbix 代理（主动式）" => "Zabbix agent (active)",
		"简单检查" => "Simple check",
		"SNMP 代理" => "SNMP agent",
		"Zabbix 采集器" => "Zabbix trapper",
		"Zabbix 内部" => "Zabbix internal",
		"外部检查" => "External check",
		"数据库监控" => "Database monitor",
		"聚合" => "Aggregate",
		"HTTP 代理" => "HTTP agent",
		"IPMI 代理" => "IPMI agent",
		"SSH 代理" => "SSH agent",
		"TELNET 代理" => "TELNET agent",
		"计算" => "Calculated",
		"JMX 代理" => "JMX agent",
		"依赖项" => "Dependent item",
		"SNMP 采集器" => "SNMP trap",
		"脚本" => "Script",
		"浮点数" => "Numeric (float)",
		"字符" => "Character",
		"日志" => "Log",
		"数字（无正负）" => "Numeric (unsigned)",
		"未设置过滤器" => "No filter set",
		"监控项" => "Item",
		" 监控项" => " items",
		"使用过滤器显示结果" => "Use the filter to show results",
		"加载失败" => "Loading failed",
		"类型 " => "Type ",
		"信息类型 " => "Type of information ",
		"已启用" => "Enabled",
		"未分类" => "Not classified",
		"信息" => "Information",
		"警告" => "Warning",
		"一般严重" => "Average",
		"严重" => "High",
		"灾难" => "Disaster",
		"先用「主机」或「主机群组」选一下，再挑触发器" => "Select a host or host group first, then pick triggers",
		"没有匹配的触发器" => "No matching triggers",
		"换个关键字试试" => "Try another keyword",
		"级别 " => "Severity ",
		"保存中…" => "Saving…",
		"已保存 " => "Saved ",
		"保存失败" => "Save failed",
		"退出编辑模式会丢失本次未保存的修改。" => "Exiting edit mode will discard unsaved changes.",
		"链路中断" => "链路中断",
		"接口关闭" => "接口关闭",
		"接口状态变化" => "接口状态变化",
		"端口关闭" => "端口关闭",
		"未知的操作" => "Unknown operation",
		"结果编码为 JSON 失败：" => "Failed to encode the result as JSON: ",
		"拓扑图不存在，或你没有查看权限" => "Map does not exist or you do not have permission to view it",
		"已存在名为「%1\$s」的拓扑图，请换一个名称" => "A map named \"%1\$s\" already exists; choose another name",
		"创建「%1\$s」失败：Zabbix 没有返回拓扑图 ID" => "Failed to create \"%1\$s\": Zabbix did not return a map ID",
		"缺少有效的拓扑图 ID" => "Missing valid map ID",
		"导出内容编码失败" => "Failed to encode the exported content",
		"保存失败：提交的数据不是合法的 JSON" => "Save failed: the submitted data is not valid JSON",
		"没有指定要查询监控项的主机" => "No host specified for the item query",
		"自动发现：没有指定要扫描的主机" => "Discovery: no hosts specified to scan",
		"自动发现：指定的主机不存在或没有权限" => "Discovery: the specified hosts do not exist or you have no permission",
		"主机不存在或没有权限" => "Host does not exist or you have no permission",
		"本批扫描超出时间预算，已跳过（请稍后重试）" => "This batch exceeded the time budget and was skipped (please retry later)",
		"没有 SNMP 接口" => "No SNMP interface",
		"SNMP 社区串未定义（检查 {\$SNMP_COMMUNITY} 宏）" => "SNMP community is not defined (check the {\$SNMP_COMMUNITY} macro)",
		"/server|服务|printer|打印|camera|摄像/i" => "/server|服务|printer|打印|camera|摄像/i",
		"自动发现：没有可生成的内容" => "Discovery: nothing to generate",
		"自动发现" => "Auto discovery",
		"自动发现：没有可生成的设备" => "Discovery: no devices to generate",
		"自动发现：Zabbix 没有返回拓扑图 ID" => "Discovery: Zabbix did not return a map ID",
		"重新发现：没有可更新的内容" => "Rediscovery: nothing to update",
		"海康" => "海康",
		"摄像" => "摄像",
		"打印" => "打印",
		"拓扑图名称不能为空" => "The map name cannot be empty",
		"拓扑图名称不能超过 128 个字符" => "The map name cannot exceed 128 characters",
		"PHP 缺少 snmp 扩展" => "PHP snmp extension is missing",
		"SNMP 没有响应（超时或设备限流），请稍后重试" => "SNMP did not respond (timeout or rate limiting); please retry later",
		"自动拓扑图" => "Auto topology map",
		"内容控制" => "Content controls",
		"条件检索" => "Filter",
		"动作" => "Actions",
		"分页栏" => "Pagination",
		"导出" => "Export",
		"返回列表" => "Back to list",
		"缩小" => "Zoom out",
		"恢复 100%" => "Reset to 100%",
		"放大" => "Zoom in",
		"缩放到适合窗口" => "Fit to window",
		"适应窗口" => "Fit",
		"重新扫描并就地更新当前拓扑图" => "Rescan and update the current map in place",
		"重新发现" => "Rediscover",
		"编辑拓扑图" => "Edit map",
		"保存" => "Save",
		"退出编辑" => "Exit editing",
		"地图元素:" => "Map elements:",
		"元素属性" => "Element properties",
		"链接属性" => "Link properties",
		"形:" => "Shapes:",
		"链接:" => "Links:",
		"拖动元素可移动位置；单击元素/形/链接可编辑属性" => "Drag elements to move them; click an element, shape or link to edit its properties",
		"0 已选" => "0 selected",
		"过滤" => "Filter",
		"键值" => "Key",
		"信息类型" => "Type of information",
		"状态" => "Status",
		"主机群组" => "Host group",
		"严重级别" => "Severity",
		"选择主机" => "Select host",
	];

	/**
	 * 当前语言：zh_CN 或 en_US。
	 */
	public static function detectLanguage(): string {
		if (self::$currentLanguage !== null) {
			return self::$currentLanguage;
		}

		$lang = self::userLanguage();

		if ($lang === '' || strtolower($lang) === 'default') {
			$lang = self::systemLanguage();
		}

		$lang = strtolower(trim($lang));

		// 繁体中文也按简体中文处理，其它未支持的语言一律回退英文。
		self::$currentLanguage = (strpos($lang, 'zh') === 0) ? 'zh_CN' : self::ZBX_DEFAULT_LANG;

		return self::$currentLanguage;
	}

	/**
	 * 当前用户配置的语言；取不到返回空串。
	 */
	private static function userLanguage(): string {
		try {
			if (class_exists('CWebUser') && method_exists('CWebUser', 'get')) {
				$lang = \CWebUser::get('lang');

				if (is_string($lang) && $lang !== '') {
					return $lang;
				}
			}

			if (isset(\CWebUser::$data['lang']) && is_string(\CWebUser::$data['lang'])) {
				return \CWebUser::$data['lang'];
			}
		}
		catch (\Throwable $e) {
			// 取不到就交给下一级
		}

		return '';
	}

	/**
	 * 系统默认语言；取不到返回空串。
	 */
	private static function systemLanguage(): string {
		try {
			if (class_exists('CSettingsHelper') && method_exists('CSettingsHelper', 'get')) {
				$lang = \CSettingsHelper::get('default_lang');

				if (is_string($lang) && $lang !== '') {
					return $lang;
				}
			}
		}
		catch (\Throwable $e) {
			// 忽略
		}

		return '';
	}

	/**
	 * 取翻译：中文环境直接返回原文。
	 */
	public static function t(string $key): string {
		if (self::detectLanguage() === 'zh_CN') {
			return $key;
		}

		return self::EN[$key] ?? $key;
	}

	/**
	 * 带参数的翻译，占位符用 sprintf 语法（%s / %d）。
	 */
	public static function tf(string $key, ...$args): string {
		return sprintf(self::t($key), ...$args);
	}

	/**
	 * 供前端使用的词典：英文环境返回「中文 => 英文」，中文环境返回空数组。
	 * 前端 T() 在查不到时同样回退原文。
	 */
	public static function jsMap(): array {
		return self::detectLanguage() === 'zh_CN' ? [] : self::EN;
	}

	public static function isChinese(): bool {
		return self::detectLanguage() === 'zh_CN';
	}

	/**
	 * 重置缓存（测试用）。
	 */
	public static function resetLanguage(): void {
		self::$currentLanguage = null;
	}
}
