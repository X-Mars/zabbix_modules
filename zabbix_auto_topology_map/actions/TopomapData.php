<?php declare(strict_types = 1);

namespace Modules\Topomap\Actions;

use API;
use CController;
use CControllerResponseData;
use CProfile;
use CRoleHelper;
use Modules\Topomap\Lib\LanguageManager;

/**
 * 自动拓扑图的数据接口。zabbix.php?action=topomap.data
 *
 * 数据直接落在 Zabbix 原生 sysmaps 里（map.create / map.update / map.delete），
 * 因此本模块创建的图在「监测 → 拓扑图」里同样可见，反之亦然。
 * 所有调用都走当前登录用户的会话，主机/拓扑图权限由 Zabbix 自身裁决。
 */
class TopomapData extends CController {

	private const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;

	/** 地图元素（sysmaps_elements）允许回写的字段，其余一律丢弃。 */
	private const SELEMENT_FIELDS = [
		'selementid', 'elementtype', 'elements', 'iconid_off', 'iconid_on', 'iconid_disabled',
		'iconid_maintenance', 'label', 'label_location', 'x', 'y', 'use_iconmap', 'evaltype', 'tags',
		'urls', 'width', 'height', 'elementsubtype', 'areatype', 'viewtype'
	];

	private const LINK_FIELDS = ['linkid', 'selementid1', 'selementid2', 'color', 'drawtype', 'label'];

	/**
	 * 每链路「指标监控项」配置表。
	 *
	 * sysmaps_links 只有 linkid/sysmapid/selementid1/selementid2/drawtype/color/label 七列，
	 * 没有任何空余列可以放带宽/上行/下行监控项，所以模块自建一张表挂在 linkid 上。
	 * 表在 get/save 路径上懒建（CREATE TABLE IF NOT EXISTS），只在首次请求时执行一次。
	 */
	private const LINK_METRICS_TABLE = 'topomap_link_metrics';

	/**
	 * 每链路「走线 / 折点」配置表。
	 *
	 * routing: 0 自动曲线（默认）/ 1 自动直角 / 2 手动折点；
	 * waypoints: JSON 数组 [{"x":120,"y":80}, ...]，仅 routing=2 时使用。
	 * 和指标表一样挂在 linkid 上，在读写路径懒建。
	 */
	private const LINK_PATHS_TABLE = 'topomap_link_paths';

	/**
	 * 模块级设置表（键值对）：目前只存「自动关联触发器的关键词」，一行一个。
	 * 和指标表一样在读写路径上懒建。
	 */
	private const SETTINGS_TABLE = 'topomap_settings';

	private static $metricsTableReady = false;
	private static $pathsTableReady = false;
	private static $settingsTableReady = false;

	/** 本请求内缓存的关键词（null = 还没读过） */
	private static $triggerKeywords = null;

	/** 自动发现连线的基础色（正常状态）。触发器进入问题态时用下面那个色。 */
	private const DISCOVERY_LINK_COLOR = '00CC00';

	/** 自动发现的连线绑定的端口状态类触发器，问题时的连线色。 */
	private const DISCOVERY_TRIGGER_COLOR = 'DD0000';

	/**
	 * 主机元素的默认标签：第一行主机名、第二行连接地址。
	 * 存宏原文，渲染时展开，主机改名 / 接口变化后自动跟上最新值。
	 */
	private const DEFAULT_HOST_LABEL = "{HOST.NAME}\n{HOST.CONN}";

	/**
	 * 判定「端口状态类」触发器的关键词（与 aiops 项目 lldpDiscoveryService 保持一致）：
	 * 描述里命中其中之一，且描述里出现该端口名，才认为这条触发器属于这条链路。
	 */
	private const LINK_TRIGGER_KEYWORDS = [
		'link down', 'link down on', 'is down', 'has changed status',
		'处于关闭状态', '链路中断', '接口关闭', '接口状态变化', '端口关闭'
	];

	/**
	 * 「触发器来源端」口径（每链路可配置，存在模块自建表的 trigger_source 列）：
	 *   AUTO   自动：源端优先，源端匹配不到才回退本端（历史行为）
	 *   SOURCE 仅源端：只按源端端口匹配，匹配不到就不关联
	 *   TARGET 仅本端：只按本端端口匹配
	 *   BOTH   两端：两端命中的触发器都关联
	 *   NONE   不关联：忽略这条线
	 */
	public const TRIGGER_SOURCE_AUTO = 0;
	public const TRIGGER_SOURCE_SOURCE = 1;
	public const TRIGGER_SOURCE_TARGET = 2;
	public const TRIGGER_SOURCE_BOTH = 3;
	public const TRIGGER_SOURCE_NONE = 4;

	private const SHAPE_FIELDS = [
		'sysmap_shapeid', 'type', 'x', 'y', 'width', 'height', 'text', 'font', 'font_size', 'font_color',
		'text_halign', 'text_valign', 'border_type', 'border_width', 'border_color', 'background_color', 'zindex'
	];

	public function init(): void {
		foreach (['disableCsrfValidation', 'disableSIDValidation', 'disableSIDvalidation'] as $method) {
			if (method_exists($this, $method)) {
				$this->$method();
			}
		}
	}

	protected function checkInput(): bool {
		// getInput() 只读 validateInput() 填充过的白名单，这里必须逐个声明；
		// 不标 required，缺参由 getInput 的默认值兜底。
		$this->validateInput([
			'mode' => 'string',
			'sysmapid' => 'string',
			'sysmapids' => 'string',
			'name' => 'string',
			'width' => 'string',
			'height' => 'string',
			'payload' => 'string',
			'search' => 'string',
			// 自动发现：要扫描的主机（逗号分隔）、协议、单机 SNMP 超时（毫秒）
			'hostids' => 'string',
			'protocols' => 'string',
			'timeout' => 'string',
			// 监控项选择器里的「主机群组」过滤器（逗号分隔）
			'groupids' => 'string',
			// 链接属性「触发器来源端」重匹配：两端主机 + 端口 + 口径
			'hostid1' => 'string',
			'hostid2' => 'string',
			'port1' => 'string',
			'port2' => 'string',
			'trigger_source' => 'string',
			// 触发器选择器按 triggerid 反查已绑定触发器的名字
			'triggerids' => 'string',
			// 自动关联触发器的关键词（一行一个，存在模块设置表里）
			'trigger_keywords' => 'string'
		]);

		return true;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		try {
			switch ($this->getInput('mode', 'list')) {
				case 'list':
					$result = $this->modeList();
					break;

				case 'get':
					$result = $this->modeGet();
					break;

				case 'create':
					$result = $this->modeCreate();
					break;

				case 'rename':
					$result = $this->modeRename();
					break;

				case 'delete':
					$result = $this->modeDelete();
					break;

				case 'export':
					$result = $this->modeExport();
					break;

				case 'save':
					$result = $this->modeSave();
					break;

				case 'icons':
					$result = $this->modeIcons();
					break;

				case 'hosts':
					$result = $this->modeHosts();
					break;

				case 'hostgroups':
					$result = $this->modeHostgroups();
					break;

				case 'items':
					$result = $this->modeItems();
					break;

				case 'linkTriggers':
					$result = $this->modeLinkTriggers();
					break;

				case 'hostTriggers':
					$result = $this->modeHostTriggers();
					break;

				case 'settings':
					$result = $this->modeSettings();
					break;

				case 'saveSettings':
					$result = $this->modeSaveSettings();
					break;

				case 'discover':
					$result = $this->modeDiscover();
					break;

				case 'discoverSave':
					$result = $this->modeDiscoverSave();
					break;

				case 'rediscover':
					$result = $this->modeRediscover();
					break;

				case 'remember':
					$result = $this->modeRemember();
					break;

				default:
					throw new \Exception(LanguageManager::t('未知的操作'));
			}
		}
		catch (\Throwable $e) {
			$result = ['success' => false, 'error' => $e->getMessage()];
		}

		// layout.json 直接输出 main_block 这个字符串，不做 json_encode。
		$json = json_encode($result, self::JSON_FLAGS);

		if ($json === false) {
			$json = json_encode(['success' => false, 'error' => LanguageManager::t('结果编码为 JSON 失败：').json_last_error_msg()]);
		}

		$this->setResponse(new CControllerResponseData([
			'main_block' => $json
		]));
	}

	// ------------------------------------------------------------------
	// 权限
	// ------------------------------------------------------------------

	private function canEdit(): bool {
		try {
			if (class_exists('CRoleHelper') && defined('CRoleHelper::ACTIONS_EDIT_MAPS')) {
				return $this->checkAccess(CRoleHelper::ACTIONS_EDIT_MAPS);
			}
		}
		catch (\Throwable $e) {
			// 落到下面的用户类型兜底
		}

		return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
	}

	// ------------------------------------------------------------------
	// 列表 / 详情
	// ------------------------------------------------------------------

	private function modeList(): array {
		$maps = API::Map()->get([
			'output' => ['sysmapid', 'name', 'width', 'height'],
			'sortfield' => 'name',
			'sortorder' => 'ASC',
			'selectSelements' => ['selementid']
		]);

		$rows = [];

		foreach ($maps as $map) {
			$rows[] = [
				'sysmapid' => (string) $map['sysmapid'],
				'name' => $map['name'],
				'elements' => count($map['selements']),
				'width' => (int) $map['width'],
				'height' => (int) $map['height']
			];
		}

		return ['success' => true, 'maps' => $rows, 'can_edit' => $this->canEdit()];
	}

	private function modeGet(): array {
		$sysmapid = $this->requireSysmapid();

		$maps = API::Map()->get([
			'sysmapids' => [$sysmapid],
			// label_* 决定元素标签显示什么（0=自定义标签 1=IP 2=名称 3=状态 4=无 5=自定义宏模板），
			// show_unack / expandproblem 决定「有问题」时写几行、写什么。原生地图就是按这些字段渲染的。
			'output' => ['sysmapid', 'name', 'width', 'height', 'label_type', 'label_location',
				'label_format', 'label_type_host', 'label_type_hostgroup', 'label_type_trigger',
				'label_type_map', 'label_type_image',
				'label_string_host', 'label_string_hostgroup', 'label_string_trigger',
				'label_string_map', 'label_string_image',
				'show_unack', 'expandproblem', 'iconmapid', 'backgroundid',
				// 元素高亮开关：原生只有开了 highlight 才在图标后面画严重级别色的圆
				'highlight'
			],
			// selementid / sysmap_shapeid 必须显式列出：一旦指定 output，
			// Zabbix 只返回白名单里的字段，缺了它前端就没法区分「已有元素」和「新元素」，
			// 每次保存都会把老元素当成新元素再插一遍。
			'selectSelements' => ['selementid', 'elementtype', 'elements', 'iconid_off', 'iconid_on',
				'iconid_disabled', 'iconid_maintenance', 'label', 'label_location', 'x', 'y', 'use_iconmap',
				'evaltype', 'tags', 'urls', 'elementsubtype', 'areatype', 'width', 'height', 'viewtype'
			],
			// linktriggers 是 selectLinks 的嵌套字段（7.0.28 的 CMap.php 按
			// outputIsRequested('linktriggers', selectLinks) 判断），写死字段列表反而是把
			// linktriggers 当成 sysmaps_links 的列去查、会报未知字段，所以这里必须用 extend。
			// 自动发现会把端口状态类触发器绑在这里，报警时连线显示为 DD0000。
			'selectLinks' => 'extend',
			'selectShapes' => ['sysmap_shapeid', 'type', 'x', 'y', 'width', 'height', 'text', 'font',
				'font_size', 'font_color', 'text_halign', 'text_valign', 'border_type', 'border_width',
				'border_color', 'background_color', 'zindex'
			]
		]);

		if (!$maps) {
			throw new \Exception(LanguageManager::t('拓扑图不存在，或你没有查看权限'));
		}

		$map = $maps[0];
		$map['selements'] = isset($map['selements']) ? array_values($map['selements']) : [];
		$map['links'] = $this->markLinkTriggerProblems(
			isset($map['links']) ? array_values($map['links']) : []
		);
		$map['shapes'] = isset($map['shapes']) ? array_values($map['shapes']) : [];

		$hostids = [];

		foreach ($map['selements'] as $selement) {
			if ((int) $selement['elementtype'] === 0) {
				foreach ($selement['elements'] as $element) {
					if (isset($element['hostid']) && $element['hostid'] !== '') {
						$hostids[$element['hostid']] = $element['hostid'];
					}
				}
			}
		}

		$map['hosts'] = $this->collectHosts(array_values($hostids));

		// 每链路的带宽 / 上行 / 下行监控项配置（模块自建表 topomap_link_metrics）。
		// 再把用到的监控项的最新值一次性取回来，前端在连线中点按「成员口求和」渲染指标小字。
		$metric_map = $this->loadLinkMetrics($sysmapid);
		$path_map = $this->loadLinkPaths($sysmapid);
		$itemids = [];

		foreach ($map['links'] as &$link) {
			$metrics = $metric_map[(string) ($link['linkid'] ?? '')] ?? [
				'bw' => [], 'in' => [], 'out' => [], 'source_port' => '', 'target_port' => '',
				'trigger_source' => self::TRIGGER_SOURCE_AUTO
			];
			$path = $path_map[(string) ($link['linkid'] ?? '')] ?? [
				// 自动发现的连线（带两端端口名）默认「自动直角」，走树形布局的底中 → 顶中分叉；
				// 手工连的线仍默认「自动曲线」。
				'routing' => ($metrics['source_port'] !== '' || $metrics['target_port'] !== '') ? 1 : 0,
				'waypoints' => []
			];

			// 指标小字、两端端口名（画在连线两端）以及「触发器来源端」口径一起带给前端
			$link['metrics'] = ['bw' => $metrics['bw'], 'in' => $metrics['in'], 'out' => $metrics['out']];
			$link['source_port'] = $metrics['source_port'];
			$link['target_port'] = $metrics['target_port'];
			$link['trigger_source'] = $metrics['trigger_source'];
			// 走线模式与手动折点（模块自建表 topomap_link_paths）
			$link['routing'] = $path['routing'];
			$link['waypoints'] = $path['waypoints'];

			foreach (['bw', 'in', 'out'] as $key) {
				foreach ($metrics[$key] as $itemid) {
					$itemids[$itemid] = $itemid;
				}
			}
		}
		unset($link);

		$map['metric_items'] = $this->fetchMetricItems(array_values($itemids));

		return [
			'success' => true,
			'map' => $map,
			'colors' => $this->labelColors(),
			'can_edit' => $this->canEdit()
		];
	}

	/**
	 * 元素状态行用的颜色。和原生一致：正常/问题/已确认各有配置项，
	 * 停用、维护是写死色（见 7.0.28 maps.inc.php getSelementInfo()）。
	 */
	private function labelColors(): array {
		$colors = [
			'ok' => '009900',
			'ok_ack' => '009900',
			'problem' => 'E45959',
			'problem_ack' => 'E97659',
			'disabled' => '960000',
			'maintenance' => 'EE9600'
		];

		if (!class_exists('\CSettingsHelper')) {
			return $colors;
		}

		try {
			$colors['ok'] = ltrim((string) \CSettingsHelper::get(\CSettingsHelper::OK_UNACK_COLOR), '#');
			$colors['ok_ack'] = ltrim((string) \CSettingsHelper::get(\CSettingsHelper::OK_ACK_COLOR), '#');
			$colors['problem'] = ltrim((string) \CSettingsHelper::get(\CSettingsHelper::PROBLEM_UNACK_COLOR), '#');
			$colors['problem_ack'] = ltrim((string) \CSettingsHelper::get(\CSettingsHelper::PROBLEM_ACK_COLOR), '#');
		}
		catch (\Throwable $e) {
			// 取不到全局配置就用 Zabbix 默认色，不该让整张图打不开
		}

		return $colors;
	}

	/**
	 * 给连线上绑定的触发器打「当前是否处于问题态」的标记。
	 *
	 * 原生 sysmap 的链路指示器本来就是「触发器报警时连线变色」，但那是原生地图在渲染时
	 * 自己查的；本模块的画布是自绘的，所以要把问题态一并带回来，前端才能把线画成 DD0000。
	 *
	 * @param array $links selectLinks 拿到的链接（每条可能带 linktriggers）
	 */
	private function markLinkTriggerProblems(array $links): array {
		$triggerids = [];

		foreach ($links as &$link) {
			$link['linktriggers'] = isset($link['linktriggers']) && is_array($link['linktriggers'])
				? array_values($link['linktriggers'])
				: [];

			foreach ($link['linktriggers'] as $lt) {
				if (!empty($lt['triggerid'])) {
					$triggerids[(string) $lt['triggerid']] = (string) $lt['triggerid'];
				}
			}
		}
		unset($link);

		$problems = [];
		$enabled = [];

		if ($triggerids) {
			try {
				// 原生 CMapHelper 只认「启用」的连线触发器（status == TRIGGER_STATUS_ENABLED），
				// 禁用的触发器上残留的未恢复问题不该让连线变色。
				$rows = API::Trigger()->get([
					'output' => ['triggerid'],
					'triggerids' => array_values($triggerids),
					'filter' => ['status' => 0],
					'preservekeys' => true
				]);

				foreach ($rows as $triggerid => $row) {
					$enabled[(string) $triggerid] = true;
				}

				$rows = API::Problem()->get([
					'output' => ['objectid'],
					'objectids' => array_values($triggerids)
				]);

				foreach ($rows as $row) {
					$problems[(string) $row['objectid']] = true;
				}
			}
			catch (\Throwable $e) {
				// 查不到问题就当全部正常，连线保持基础色，不该因此打不开图
			}
		}

		foreach ($links as &$link) {
			foreach ($link['linktriggers'] as &$lt) {
				$lt['problem'] = isset($problems[(string) $lt['triggerid']])
					&& isset($enabled[(string) $lt['triggerid']]);
				$lt['color'] = strtoupper(ltrim((string) ($lt['color'] ?? self::DISCOVERY_TRIGGER_COLOR), '#'));
			}
			unset($lt);
		}
		unset($link);

		return $links;
	}

	/**
	 * 主机名称/状态 + 当前未恢复问题的数量、未确认数量、最高级别与标题。
	 * 预览时据此点亮「问题图标」、打角标，并在标签下方写「正常 / 问题 / 已停用 / 在维护中」——
	 * 这几行就是原生拓扑图 element 标签里的第二段文字。
	 */
	private function collectHosts(array $hostids): array {
		if (!$hostids) {
			return [];
		}

		$result = [];

		$hosts = API::Host()->get([
			'hostids' => $hostids,
			'output' => ['hostid', 'name', 'status', 'maintenance_status'],
			'selectInterfaces' => ['ip', 'dns', 'main', 'type', 'useip'],
			'preservekeys' => true
		]);

		foreach ($hosts as $hostid => $host) {
			$ip = $this->hostIp($host);

			$result[$hostid] = [
				'name' => $host['name'],
				'ip' => $ip,
				// {HOST.CONN} 的连接地址：优先主接口，其次 SNMP 接口，再按 useip 取 IP/DNS
				'conn' => $this->hostConn($host),
				'status' => (int) $host['status'],
				'maintenance' => isset($host['maintenance_status']) ? (int) $host['maintenance_status'] : 0,
				'problems' => 0,
				'unack' => 0,
				'severity' => -1,
				'title' => ''
			];
		}

		try {
			// 和原生拓扑图一致（getSelementsInfo）：先只取「启用 + 受监控 + 当前处于问题态」的触发器，
			// 再拉这些触发器的问题。之前直接按 hostids 拉问题，禁用触发器上残留的未恢复问题也被算进去，
			// 于是原生显示 1 条、模块显示 2 条。
			$triggers = API::Trigger()->get([
				'output' => ['triggerid'],
				'selectHosts' => ['hostid'],
				'hostids' => $hostids,
				'filter' => ['state' => null],
				'monitored' => true,
				'only_true' => true,
				'skipDependent' => true
			]);
		}
		catch (\Throwable $e) {
			// 取不到问题数据不该让整张图打不开
			return $result;
		}

		if (!$triggers) {
			return $result;
		}

		$trigger_hosts = [];

		foreach ($triggers as $trigger) {
			foreach ($trigger['hosts'] as $host) {
				$trigger_hosts[$trigger['triggerid']][] = $host['hostid'];
			}
		}

		try {
			// 不带 recent: true —— 只算未恢复的问题（原生 countSelementProblems 只数 r_clock = 0 的）
			$problems = API::Problem()->get([
				'output' => ['objectid', 'severity', 'name', 'acknowledged'],
				'objectids' => array_keys($trigger_hosts)
			]);
		}
		catch (\Throwable $e) {
			return $result;
		}

		if (!$problems) {
			return $result;
		}

		foreach ($problems as $problem) {
			if (!isset($trigger_hosts[$problem['objectid']])) {
				continue;
			}

			$severity = (int) $problem['severity'];
			$unack = empty($problem['acknowledged']) ? 1 : 0;
			$title = isset($problem['name']) ? (string) $problem['name'] : '';

			foreach ($trigger_hosts[$problem['objectid']] as $hostid) {
				if (!isset($result[$hostid])) {
					continue;
				}

				$result[$hostid]['problems']++;
				$result[$hostid]['unack'] += $unack;

				// 只记最严重那条的标题，用于「显示单个问题」模式
				if ($severity > $result[$hostid]['severity']) {
					$result[$hostid]['severity'] = $severity;
					$result[$hostid]['title'] = $title;
				}
			}
		}

		return $result;
	}

	// ------------------------------------------------------------------
	// 增 / 改 / 删
	// ------------------------------------------------------------------

	private function modeCreate(): array {
		$name = $this->requireName();
		$width = $this->dimension('width') ?? 1000;
		$height = $this->dimension('height') ?? 700;

		// 拓扑图名称在 sysmaps 里是唯一的。7.0.28 的 map.create 遇到重名不抛异常，
		// 而是直接返回 false（实测），不自己先查一遍的话前端只会拿到空的 sysmapid。
		$exists = API::Map()->get([
			'output' => ['sysmapid'],
			'filter' => ['name' => $name]
		]);

		if ($exists) {
			throw new \Exception(LanguageManager::tf('已存在名为「%1$s」的拓扑图，请换一个名称', $name));
		}

		// 前端 API wrapper 在 map.create 校验失败时只返回 false，错误消息另行登记；
		// 记下调用前的消息条数，失败时把 Zabbix 的真实错误一起抛出去。
		$message_offset = $this->apiMessageCount();

		$result = API::Map()->create([[
			'name' => $name,
			'width' => $width,
			'height' => $height,
			// label_type=0 时原生「拓扑图」也直接显示元素上的自定义标签，
			// 两个入口看到的是同一份内容。
			'label_type' => 0,
			'label_location' => 0,
			'grid_show' => 0,
			'grid_align' => 1,
			'grid_size' => 50,
			'highlight' => 1,
			'private' => 0
		]]);

		$sysmapid = '';

		if (is_array($result)) {
			if (!empty($result['sysmapids'][0])) {
				$sysmapid = (string) $result['sysmapids'][0];
			}
			elseif (!empty($result['sysmapid'])) {
				$sysmapid = (string) $result['sysmapid'];
			}
		}

		if ($sysmapid === '') {
			$detail = $this->apiErrorDetail($message_offset);

			if ($detail !== '') {
				throw new \Exception(LanguageManager::tf('创建「%1$s」失败：%2$s', $name, $detail));
			}

			throw new \Exception(LanguageManager::tf('创建「%1$s」失败：Zabbix 没有返回拓扑图 ID', $name));
		}

		return ['success' => true, 'sysmapid' => $sysmapid];
	}

	private function modeRename(): array {
		$sysmapid = $this->requireSysmapid();
		$name = $this->requireName();

		// 改名同样受「名称唯一」约束，先查一遍给出能看懂的提示
		$exists = API::Map()->get([
			'output' => ['sysmapid'],
			'filter' => ['name' => $name]
		]);

		foreach ($exists as $row) {
			if ((string) $row['sysmapid'] !== $sysmapid) {
				throw new \Exception(LanguageManager::tf('已存在名为「%1$s」的拓扑图，请换一个名称', $name));
			}
		}

		$update = [
			'sysmapid' => $sysmapid,
			'name' => $name
		];

		// 宽 / 高可选：不传就保持原值，避免老客户端把它悄悄改回 1000×700
		$width = $this->dimension('width');
		$height = $this->dimension('height');

		if ($width !== null) {
			$update['width'] = $width;
		}

		if ($height !== null) {
			$update['height'] = $height;
		}

		API::Map()->update([$update]);

		return ['success' => true];
	}

	/**
	 * 删除一张（sysmapid）或一批（sysmapids，逗号分隔）拓扑图。
	 */
	private function modeDelete(): array {
		$raw = trim((string) $this->getInput('sysmapids', ''));
		$ids = [];

		if ($raw === '') {
			$ids[] = $this->requireSysmapid();
		}
		else {
			foreach (explode(',', $raw) as $id) {
				$id = trim($id);

				if ($id !== '' && ctype_digit($id) && $id !== '0') {
					$ids[] = $id;
				}
			}
		}

		if (!$ids) {
			throw new \Exception(LanguageManager::t('缺少有效的拓扑图 ID'));
		}

		API::Map()->delete($ids);

		// 图删了，模块自建表里挂在它下面的指标配置也要跟着清掉，否则会成为孤儿行
		$this->dropLinkMetricsForMaps($ids);
		$this->dropLinkPathsForMaps($ids);

		foreach ($ids as $id) {
			$this->dropMapLayout((string) $id);
		}

		return ['success' => true, 'deleted' => count($ids)];
	}

	/**
	 * 删除若干张图的走线/折点配置（图被删除时清理，避免孤儿行）。
	 *
	 * @param string[] $sysmapids
	 */
	private function dropLinkPathsForMaps(array $sysmapids): void {
		$this->ensurePathsTable();

		$ids = [];

		foreach ($sysmapids as $id) {
			if (ctype_digit((string) $id) && (int) $id > 0) {
				$ids[] = (int) $id;
			}
		}

		if (!$ids) {
			return;
		}

		\DBexecute(
			'DELETE FROM '.self::LINK_PATHS_TABLE.' WHERE sysmapid IN ('.implode(',', $ids).')'
		);
	}

	/**
	 * 删除若干张图的指标配置（图被删除时清理，避免孤儿行）。
	 *
	 * @param string[] $sysmapids
	 */
	private function dropLinkMetricsForMaps(array $sysmapids): void {
		$this->ensureMetricsTable();

		$ids = [];

		foreach ($sysmapids as $id) {
			if (ctype_digit((string) $id) && (int) $id > 0) {
				$ids[] = (int) $id;
			}
		}

		if (!$ids) {
			return;
		}

		\DBexecute(
			'DELETE FROM '.self::LINK_METRICS_TABLE.' WHERE sysmapid IN ('.implode(',', $ids).')'
		);
	}

	/**
	 * 导出拓扑图为 JSON（模块自有格式，可再导入回 sysmaps。
	 * 不传 sysmapids 就导出全部可见的图）。
	 */
	private function modeExport(): array {
		$raw = trim((string) $this->getInput('sysmapids', ''));
		$ids = [];

		foreach (explode(',', $raw) as $id) {
			$id = trim($id);

			if ($id !== '' && ctype_digit($id) && $id !== '0') {
				$ids[] = $id;
			}
		}

		$params = [
			'output' => ['sysmapid', 'name', 'width', 'height', 'label_type', 'label_location',
				'grid_show', 'grid_align', 'grid_size', 'highlight', 'private'
			],
			'selectSelements' => ['selementid', 'elementtype', 'elements', 'iconid_off', 'iconid_on',
				'iconid_disabled', 'iconid_maintenance', 'label', 'label_location', 'x', 'y', 'use_iconmap',
				'evaltype', 'tags', 'urls', 'elementsubtype', 'areatype', 'width', 'height', 'viewtype'
			],
			'selectLinks' => ['linkid', 'selementid1', 'selementid2', 'color', 'drawtype', 'label'],
			'selectShapes' => ['sysmap_shapeid', 'type', 'x', 'y', 'width', 'height', 'text', 'font',
				'font_size', 'font_color', 'text_halign', 'text_valign', 'border_type', 'border_width',
				'border_color', 'background_color', 'zindex'
			],
			'sortfield' => 'name',
			'sortorder' => 'ASC'
		];

		if ($ids) {
			$params['sysmapids'] = $ids;
		}

		$maps = API::Map()->get($params);

		$json = json_encode([
			'zabbix_export' => [
				'version' => '1.0',
				'generator' => 'topomap',
				'date' => date('Y-m-d\TH:i:s\Z'),
				'maps' => $maps
			]
		], self::JSON_FLAGS);

		if ($json === false) {
			throw new \Exception(LanguageManager::t('导出内容编码失败'));
		}

		return [
			'success' => true,
			'count' => count($maps),
			'filename' => 'topomap-'.date('Ymd-His').'.json',
			'json' => $json
		];
	}

	private function modeSave(): array {
		$sysmapid = $this->requireSysmapid();

		$raw = (string) $this->getInput('payload', '');
		$payload = json_decode($raw, true);

		if (!is_array($payload)) {
			throw new \Exception(LanguageManager::t('保存失败：提交的数据不是合法的 JSON'));
		}

		$update = ['sysmapid' => $sysmapid];

		if (array_key_exists('name', $payload) && trim((string) $payload['name']) !== '') {
			$update['name'] = $this->normalizeName((string) $payload['name']);
		}

		// 画布尺寸由前端按内容算好后提交（拖拽时长大、保存时收紧），
		// 这里只做范围钳制，避免出现 0 或超大的值。
		if (array_key_exists('width', $payload)) {
			$update['width'] = max(100, min(10000, (int) $payload['width']));
		}

		if (array_key_exists('height', $payload)) {
			$update['height'] = max(100, min(10000, (int) $payload['height']));
		}

		$update['selements'] = $this->sanitizeSelements(
			isset($payload['selements']) && is_array($payload['selements']) ? $payload['selements'] : []
		);

		$update['links'] = $this->sanitizeLinks(
			isset($payload['links']) && is_array($payload['links']) ? $payload['links'] : [],
			$this->existingLinkIndicatorTypes($sysmapid)
		);

		// 7.0.x 的 CMapElement::checkLinkInput() 里把方法名写成了「updateLink」，
		// 而实际调用传入的是 __FUNCTION__ = 「updateLinks」，于是「更新链接」这条路径
		// 永远走成「新增链接」的校验分支，要求每条 link 必须带 sysmapid。
		// 7.4/8.0 改走 CMap::validateLinkFields() 的字段白名单，白名单里没有 sysmapid，
		// 带上去会报「无效的参数 "/1/links/1": "sysmapid" 参数异常」，所以只给旧版本补。
		if (self::linkSysmapidRequired()) {
			foreach ($update['links'] as &$link) {
				if (isset($link['linkid'])) {
					$link['sysmapid'] = $sysmapid;
				}
			}
			unset($link);
		}

		$update['shapes'] = $this->sanitizeShapes(
			isset($payload['shapes']) && is_array($payload['shapes']) ? $payload['shapes'] : []
		);

		$message_offset = $this->apiMessageCount();
		$update_result = API::Map()->update([$update]);

		if ($update_result === false) {
			$detail = $this->apiErrorDetail($message_offset);

			if ($detail !== '') {
				throw new \Exception(LanguageManager::tf('保存失败：%1$s', $detail));
			}

			throw new \Exception(LanguageManager::t('保存失败：Zabbix 未确认保存结果'));
		}

		// 保存后回读一次链接，把新建链路的真实 linkid 认领出来，再把每链路的
		// 带宽/上行/下行监控项写进模块自建表。sysmaps_links 没有空余列，
		// 指标配置只能挂在 linkid 上的独立表里。
		$this->persistLinkMetrics(
			$sysmapid,
			isset($payload['link_metrics']) && is_array($payload['link_metrics']) ? $payload['link_metrics'] : [],
			$update['links']
		);

		// 每链路的走线模式与手动折点（topomap_link_paths）
		$this->persistLinkPaths(
			$sysmapid,
			isset($payload['link_paths']) && is_array($payload['link_paths']) ? $payload['link_paths'] : [],
			$update['links']
		);

		return ['success' => true];
	}

	/**
	 * 把前端提交的每链路指标配置落库。
	 *
	 * payload.link_metrics 每项形如：
	 *   {linkid: '123'|'', new_seq: 1|0, bw_itemids: [...], in_itemids: [...], out_itemids: [...]}
	 *
	 * 已有链路直接按 linkid 对上；新建链路没有 linkid，用 new_seq 认领：
	 * Zabbix 按 payload.links 数组顺序插入新链接，自增 linkid 依次递增，
	 * 所以回读后「排除已有 linkid、剩下按数值升序」的第 n 条就是 new_seq=n 的那条。
	 *
	 * @param array $raw_metrics payload.link_metrics
	 * @param array $saved_links  刚提交的 $update['links']（用来区分「原有链路」和「新建链路」）
	 */
	private function persistLinkMetrics(string $sysmapid, array $raw_metrics, array $saved_links): void {
		$existing_ids = [];

		foreach ($saved_links as $link) {
			if (isset($link['linkid'])) {
				$existing_ids[(string) $link['linkid']] = true;
			}
		}

		$maps = API::Map()->get([
			'sysmapids' => [$sysmapid],
			'output' => ['sysmapid'],
			'selectLinks' => 'extend'
		]);

		$new_ids = [];

		if ($maps && isset($maps[0]['links']) && is_array($maps[0]['links'])) {
			foreach (array_values($maps[0]['links']) as $db_link) {
				$id = (string) $db_link['linkid'];

				if (!isset($existing_ids[$id])) {
					$new_ids[] = $id;
				}
			}
		}

		sort($new_ids, SORT_NUMERIC);

		$by_link = [];

		foreach ($raw_metrics as $entry) {
			if (!is_array($entry)) {
				continue;
			}

			$linkid = '';

			if (isset($entry['linkid']) && ctype_digit((string) $entry['linkid']) && (int) $entry['linkid'] > 0) {
				$linkid = (string) $entry['linkid'];
			}
			else {
				$seq = isset($entry['new_seq']) ? (int) $entry['new_seq'] : 0;

				if ($seq > 0 && isset($new_ids[$seq - 1])) {
					$linkid = $new_ids[$seq - 1];
				}
			}

			if ($linkid === '') {
				continue;
			}

			$by_link[$linkid] = [
				'bw' => $this->parseItemIds($entry['bw_itemids'] ?? []),
				'in' => $this->parseItemIds($entry['in_itemids'] ?? []),
				'out' => $this->parseItemIds($entry['out_itemids'] ?? []),
				'source_port' => (string) ($entry['source_port'] ?? ''),
				'target_port' => (string) ($entry['target_port'] ?? ''),
				'trigger_source' => $entry['trigger_source'] ?? self::TRIGGER_SOURCE_AUTO
			];
		}

		$this->saveLinkMetrics($sysmapid, $by_link);
	}

	/**
	 * 把前端提交的每链路走线 / 折点配置落库。
	 *
	 * payload.link_paths 每项形如：
	 *   {linkid: '123'|'', new_seq: 1|0, routing: 0|1|2, waypoints: [{x,y}, ...]}
	 *
	 * linkid / new_seq 的认领规则与 persistLinkMetrics() 完全一致：
	 * 新建链路按 payload.links 的出现顺序编号，回读后排除已有 id、按数值升序依次对应。
	 *
	 * @param array $raw_paths   payload.link_paths
	 * @param array $saved_links 刚提交的 $update['links']
	 */
	private function persistLinkPaths(string $sysmapid, array $raw_paths, array $saved_links): void {
		$existing_ids = [];

		foreach ($saved_links as $link) {
			if (isset($link['linkid'])) {
				$existing_ids[(string) $link['linkid']] = true;
			}
		}

		$maps = API::Map()->get([
			'sysmapids' => [$sysmapid],
			'output' => ['sysmapid'],
			'selectLinks' => 'extend'
		]);

		$new_ids = [];

		if ($maps && isset($maps[0]['links']) && is_array($maps[0]['links'])) {
			foreach (array_values($maps[0]['links']) as $db_link) {
				$id = (string) $db_link['linkid'];

				if (!isset($existing_ids[$id])) {
					$new_ids[] = $id;
				}
			}
		}

		sort($new_ids, SORT_NUMERIC);

		$by_link = [];

		foreach ($raw_paths as $entry) {
			if (!is_array($entry)) {
				continue;
			}

			$linkid = '';

			if (isset($entry['linkid']) && ctype_digit((string) $entry['linkid']) && (int) $entry['linkid'] > 0) {
				$linkid = (string) $entry['linkid'];
			}
			else {
				$seq = isset($entry['new_seq']) ? (int) $entry['new_seq'] : 0;

				if ($seq > 0 && isset($new_ids[$seq - 1])) {
					$linkid = $new_ids[$seq - 1];
				}
			}

			if ($linkid === '') {
				continue;
			}

			$by_link[$linkid] = [
				'routing' => max(0, min(2, (int) ($entry['routing'] ?? 0))),
				'waypoints' => $this->normalizeWaypoints($entry['waypoints'] ?? [])
			];
		}

		$this->saveLinkPaths($sysmapid, $by_link);
	}

	/**
	 * 折点归一：只保留 {x,y} 数字对，取整并钳制为非负（sysmaps 坐标同样不能为负）。
	 *
	 * @param mixed $raw
	 * @return array [{x:int,y:int}, ...]
	 */
	private function normalizeWaypoints($raw): array {
		if (!is_array($raw)) {
			return [];
		}

		$points = [];

		foreach ($raw as $point) {
			if (!is_array($point) || !isset($point['x'], $point['y'])) {
				continue;
			}

			if (!is_numeric($point['x']) || !is_numeric($point['y'])) {
				continue;
			}

			$points[] = [
				'x' => max(0, (int) round((float) $point['x'])),
				'y' => max(0, (int) round((float) $point['y']))
			];
		}

		return $points;
	}

	// ------------------------------------------------------------------
	// 图标 / 主机选择
	// ------------------------------------------------------------------

	private function modeIcons(): array {
		// imagetype 1 = IMAGE_TYPE_ICON（拓扑图图标库）
		$images = API::Image()->get([
			'output' => ['imageid', 'name'],
			'filter' => ['imagetype' => 1],
			'sortfield' => 'name',
			'sortorder' => 'ASC'
		]);

		$icons = [];

		foreach ($images as $image) {
			$icons[] = ['imageid' => (string) $image['imageid'], 'name' => $image['name']];
		}

		$preferred = ['Server_(96)', 'Server_(64)', 'Server_(48)', 'Server_(32)', 'Cloud_(96)'];
		$default = $icons ? $icons[0]['imageid'] : '0';

		foreach ($preferred as $want) {
			foreach ($icons as $icon) {
				if (strcasecmp($icon['name'], $want) === 0) {
					$default = $icon['imageid'];
					break 2;
				}
			}
		}

		return ['success' => true, 'icons' => $icons, 'default' => $default];
	}

	/**
	 * 视图记忆：记住 / 忘记「上次打开的那张图」。
	 *
	 * 原生是「打开 map.view 写 web.maps.sysmapid，打开列表页 sysmaps.php 删掉它」，
	 * 菜单项固定指 action=map.view，于是点菜单时能回到上次的视图。
	 * 本模块列表和画布在同一个 action 里，所以「回列表」这次删除只能由前端触发。
	 *
	 * sysmapid 传空（或 0）表示回到列表。
	 */
	private function modeRemember(): array {
		$sysmapid = (int) $this->getInput('sysmapid', 0);

		if ($sysmapid > 0) {
			CProfile::update(TopomapView::PROFILE_KEY, $sysmapid, PROFILE_TYPE_ID);
		}
		else {
			CProfile::delete(TopomapView::PROFILE_KEY);
		}

		return ['success' => true];
	}

	private function modeHosts(): array {
		$search = trim((string) $this->getInput('search', ''));
		$hostids = $this->parseIdList((string) $this->getInput('hostids', ''));
		$groupids = $this->parseIdList((string) $this->getInput('groupids', ''));

		$options = [
			'output' => ['hostid', 'name', 'status'],
			'selectInterfaces' => ['ip', 'type', 'main'],
			'sortfield' => 'name',
			'sortorder' => 'ASC',
			'limit' => 200
		];

		// 监控项选择器里的「主机」弹层：可按已选主机回显 / 按主机群组过滤。
		// 自动发现只传 search，不传这两个参数。
		if ($hostids) {
			$options['hostids'] = $hostids;
			unset($options['limit']);
		}

		if ($groupids) {
			$options['groupids'] = $groupids;
		}

		if ($search !== '') {
			$options['search'] = ['name' => $search, 'host' => $search];
			$options['searchByAny'] = true;
		}

		$hosts = [];

		foreach (API::Host()->get($options) as $host) {
			$hosts[] = [
				'hostid' => (string) $host['hostid'],
				'name' => $host['name'],
				'ip' => $this->hostIp($host),
				'status' => (int) $host['status'],
				// 有 SNMP 接口的才能作为自动发现的起点
				'snmp' => $this->snmpInterface($host) !== null
			];
		}

		return ['success' => true, 'hosts' => $hosts];
	}

	/**
	 * 监控项选择器里「主机群组」弹层的数据源：列出主机群组，可按名称搜。
	 */
	private function modeHostgroups(): array {
		$search = trim((string) $this->getInput('search', ''));

		$options = [
			'output' => ['groupid', 'name'],
			'sortfield' => 'name',
			'sortorder' => 'ASC',
			'limit' => 400
		];

		if ($search !== '') {
			$options['search'] = ['name' => $search];
		}

		$groups = [];

		foreach (API::HostGroup()->get($options) as $group) {
			$groups[] = [
				'groupid' => (string) $group['groupid'],
				'name' => (string) $group['name']
			];
		}

		return ['success' => true, 'hostgroups' => $groups];
	}

	/**
	 * 监控项选择器用的列表：给一批主机，按关键字模糊匹配「数值型」监控项。
	 *
	 * 只列 value_type 0（浮点）/ 3（无符号整数）两类：链路带宽、上下行流量都是数值型，
	 * 而交换机模板上动辄几百条 net.if.alias / descr 文本项（value_type 1）会把列表淹掉。
	 */
	private function modeItems(): array {
		$hostids = $this->parseIdList((string) $this->getInput('hostids', ''));

		if (!$hostids) {
			throw new \Exception(LanguageManager::t('没有指定要查询监控项的主机'));
		}

		$search = trim((string) $this->getInput('search', ''));

		$options = [
			'hostids' => $hostids,
			// 只取数值型（0 浮点 / 3 无符号整数）：过滤放在 API 层而不是取回来再筛，
			// 否则交换机主机上几百条文本项会先把 limit 占满，真正的带宽项根本轮不到。
			'filter' => ['value_type' => [0, 3]],
			'output' => ['itemid', 'name', 'key_', 'units', 'value_type', 'lastvalue', 'lastclock', 'type', 'status'],
			'selectHosts' => ['hostid', 'name'],
			'sortfield' => 'name',
			'sortorder' => 'ASC',
			'limit' => 400
		];

		// 名称和 key 任一命中即可（searchByAny=true 是「或」）
		if ($search !== '') {
			$options['search'] = ['name' => $search, 'key_' => $search];
			$options['searchByAny'] = true;
		}

		$items = [];

		foreach (API::Item()->get($options) as $item) {
			$items[] = [
				'itemid' => (string) $item['itemid'],
				'name' => (string) $item['name'],
				'key_' => (string) $item['key_'],
				'units' => (string) $item['units'],
				'value_type' => (int) $item['value_type'],
				'type' => (int) $item['type'],
				'status' => (int) $item['status'],
				'lastvalue' => $item['lastvalue'] === null ? null : (string) $item['lastvalue'],
				'lastclock' => (string) $item['lastclock'],
				'hostid' => (string) ($item['hosts'][0]['hostid'] ?? ''),
				'hostname' => (string) ($item['hosts'][0]['name'] ?? '')
			];
		}

		return ['success' => true, 'items' => $items];
	}

	/**
	 * 触发器选择器用的列表：给一批主机（连线两端），按关键字列出它们的触发器。
	 *
	 * 手工建的连线（比如新加的一台电脑接到交换机）没有 LLDP 端口信息，
	 * 自动关键词匹配无从下手，这时只能由用户在这里手工挑。
	 */
	private function modeHostTriggers(): array {
		$hostids = $this->parseIdList((string) $this->getInput('hostids', ''));
		$triggerids = $this->parseIdList((string) $this->getInput('triggerids', ''));
		$groupids = $this->parseIdList((string) $this->getInput('groupids', ''));

		if (!$hostids && !$triggerids && !$groupids) {
			return ['success' => true, 'triggers' => []];
		}

		$search = trim((string) $this->getInput('search', ''));

		$options = [
			'output' => ['triggerid', 'description', 'priority', 'status', 'value'],
			'selectHosts' => ['hostid', 'name'],
			'sortfield' => ['priority', 'description'],
			'sortorder' => 'DESC',
			'limit' => 500
		];

		// 按 triggerid 反查（画布上已绑定的 chip 要名字）；否则按主机 / 主机群组列候选
		if ($triggerids) {
			$options['triggerids'] = $triggerids;
			unset($options['limit']);
		}
		else {
			// 主机群组是「过滤器」，语义是「所选主机 ∩ 所选群组」；
			// 只选了群组没选主机时，就是这些群组下所有主机的触发器。
			if ($groupids) {
				$allowed = [];

				foreach (API::Host()->get(['output' => ['hostid'], 'groupids' => $groupids]) as $host) {
					$allowed[(string) $host['hostid']] = (string) $host['hostid'];
				}

				if ($hostids) {
					$hostids = array_values(array_intersect($hostids, $allowed));
				}
				else {
					$hostids = array_values($allowed);
				}

				if (!$hostids) {
					return ['success' => true, 'triggers' => []];
				}
			}

			$options['hostids'] = $hostids;
		}

		if ($search !== '') {
			$options['search'] = ['description' => $search];
		}

		$triggers = [];

		foreach (API::Trigger()->get($options) as $trigger) {
			$host = $trigger['hosts'][0] ?? [];

			$triggers[] = [
				'triggerid' => (string) $trigger['triggerid'],
				'description' => (string) $trigger['description'],
				'priority' => (int) $trigger['priority'],
				'status' => (int) $trigger['status'],
				'value' => (int) $trigger['value'],
				'hostid' => (string) ($host['hostid'] ?? ''),
				'hostname' => (string) ($host['name'] ?? '')
			];
		}

		return ['success' => true, 'triggers' => $triggers];
	}

	/**
	 * 按指定「触发器来源端」重新匹配一条链路的端口状态类触发器。
	 *
	 * 供链接属性弹窗改动「触发器来源」后即时生效（手动建的连线也适用，
	 * 只要两端是 Zabbix 主机且端口名能从连线配置里取到）。
	 */
	private function modeLinkTriggers(): array {
		$src_hostid = trim((string) $this->getInput('hostid1', ''));
		$tgt_hostid = trim((string) $this->getInput('hostid2', ''));
		$src_port = trim((string) $this->getInput('port1', ''));
		$tgt_port = trim((string) $this->getInput('port2', ''));
		$trigger_source = $this->normalizeTriggerSource($this->getInput('trigger_source', self::TRIGGER_SOURCE_AUTO));

		$cache = [];
		$triggerids = $this->matchLinkTriggers($src_hostid, $tgt_hostid, $src_port, $tgt_port, $cache, $trigger_source);

		$triggers = [];

		if ($triggerids) {
			foreach (API::Trigger()->get([
				'triggerids' => $triggerids,
				'output' => ['triggerid', 'description'],
				'selectHosts' => ['hostid']
			]) as $trigger) {
				$triggers[] = [
					'triggerid' => (string) $trigger['triggerid'],
					'description' => (string) $trigger['description'],
					'hostid' => (string) ($trigger['hosts'][0]['hostid'] ?? '')
				];
			}
		}

		return ['success' => true, 'triggers' => $triggers];
	}

	// ------------------------------------------------------------------
	// 每链路指标（带宽 / 上行 / 下行）配置：模块自建表
	// ------------------------------------------------------------------

	/**
	 * 懒建指标表。模块自建表的主体是 zabbix 前端 DB 用户，CREATE TABLE IF NOT EXISTS
	 * 幂等，所以每个请求最多执行一次，静态标记兜住同一请求内的重复调用。
	 */
	private function ensureMetricsTable(): void {
		if (self::$metricsTableReady) {
			return;
		}

		self::$metricsTableReady = true;

		// 三个 ITEMIDS 列存逗号分隔的监控项 ID（聚合成员口就是多条）；
		// source_port / target_port 记两端端口名（画在连线两端、也是触发器匹配的键），
		// trigger_source 记「触发器来源端」口径。
		// linkid 作主键：一条链路一行配置。
		\DBexecute(
			'CREATE TABLE IF NOT EXISTS '.self::LINK_METRICS_TABLE.' ('
			.'linkid bigint NOT NULL,'
			.'sysmapid bigint NOT NULL,'
			.'bw_itemids text NULL,'
			.'in_itemids text NULL,'
			.'out_itemids text NULL,'
			.'source_port varchar(255) NULL,'
			.'target_port varchar(255) NULL,'
			.'trigger_source integer NULL,'
			.'PRIMARY KEY (linkid)'
			.')'
		);

		// 1.0.11 建的表没有后三列，补上（列已存在就跳过）。
		foreach ([
			'source_port' => 'varchar(255) NULL',
			'target_port' => 'varchar(255) NULL',
			'trigger_source' => 'integer NULL'
		] as $column => $definition) {
			if (!$this->metricsColumnExists($column)) {
				\DBexecute('ALTER TABLE '.self::LINK_METRICS_TABLE.' ADD COLUMN '.$column.' '.$definition);
			}
		}
	}

	/** 模块自建表里某个列是否存在（用 information_schema 判断，兼容 MySQL / PostgreSQL）。 */
	private function metricsColumnExists(string $column): bool {
		try {
			$cursor = \DBselect(
				'SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS'
				.' WHERE TABLE_NAME='.\zbx_dbstr(self::LINK_METRICS_TABLE)
				.' AND COLUMN_NAME='.\zbx_dbstr($column)
			);

			if (is_array($cursor)) {
				$row = \DBfetch($cursor);

				return $row && (int) $row['cnt'] > 0;
			}
		}
		catch (\Throwable $e) {
			// 查不到就当作「不存在」，让 ALTER 去兜底；真出问题也只影响这几列
		}

		return false;
	}

	/** 懒建走线 / 折点表（与指标表同一套做法，每个请求最多执行一次）。 */
	private function ensurePathsTable(): void {
		if (self::$pathsTableReady) {
			return;
		}

		self::$pathsTableReady = true;

		\DBexecute(
			'CREATE TABLE IF NOT EXISTS '.self::LINK_PATHS_TABLE.' ('
			.'linkid bigint NOT NULL,'
			.'sysmapid bigint NOT NULL,'
			.'routing integer NULL,'
			.'waypoints text NULL,'
			.'PRIMARY KEY (linkid)'
			.')'
		);
	}

	/**
	 * 读一张图全部链路的走线 / 折点配置。
	 *
	 * @return array linkid => ['routing'=>int, 'waypoints'=>array]
	 */
	private function loadLinkPaths(string $sysmapid): array {
		$this->ensurePathsTable();

		$sysmapid = (int) $sysmapid;
		$cursor = \DBselect(
			'SELECT linkid, routing, waypoints'
			.' FROM '.self::LINK_PATHS_TABLE
			.' WHERE sysmapid='.$sysmapid
		);

		$result = [];

		if (!$cursor) {
			return $result;
		}

		while ($row = \DBfetch($cursor)) {
			$waypoints = json_decode((string) ($row['waypoints'] ?? ''), true);

			$result[(string) $row['linkid']] = [
				'routing' => max(0, min(2, (int) ($row['routing'] ?? 0))),
				'waypoints' => $this->normalizeWaypoints($waypoints)
			];
		}

		return $result;
	}

	/**
	 * 重建一张图的走线 / 折点配置：先删整张图的行，再按当前链路写回。
	 * 「自动曲线」也落库：自动发现的连线默认是自动直角，用户改成自动曲线后要能记住，
	 * 否则每次都按默认值渲染回自动直角。
	 *
	 * @param array $by_link linkid => ['routing'=>int, 'waypoints'=>array]
	 */
	private function saveLinkPaths(string $sysmapid, array $by_link): void {
		$this->ensurePathsTable();

		$sysmapid = (int) $sysmapid;

		\DBexecute('DELETE FROM '.self::LINK_PATHS_TABLE.' WHERE sysmapid='.$sysmapid);

		foreach ($by_link as $linkid => $path) {
			$linkid = (int) $linkid;
			$routing = max(0, min(2, (int) ($path['routing'] ?? 0)));
			$waypoints = $this->normalizeWaypoints($path['waypoints'] ?? []);

			if ($linkid <= 0) {
				continue;
			}

			\DBexecute(
				'INSERT INTO '.self::LINK_PATHS_TABLE
				.' (linkid, sysmapid, routing, waypoints) VALUES ('
				.$linkid.', '.$sysmapid
				.', '.$routing
				.', '.\zbx_dbstr(json_encode($waypoints, self::JSON_FLAGS))
				.')'
			);
		}
	}

	/**
	 * 读一张图全部链路的指标配置。
	 *
	 * @return array linkid => ['bw'=>string[], 'in'=>string[], 'out'=>string[], 'source_port'=>string, 'target_port'=>string, 'trigger_source'=>int]
	 */
	private function loadLinkMetrics(string $sysmapid): array {
		$this->ensureMetricsTable();

		$sysmapid = (int) $sysmapid;
		$cursor = \DBselect(
			'SELECT linkid, bw_itemids, in_itemids, out_itemids, source_port, target_port, trigger_source'
			.' FROM '.self::LINK_METRICS_TABLE
			.' WHERE sysmapid='.$sysmapid
		);

		$result = [];

		if (!$cursor) {
			return $result;
		}

		while ($row = \DBfetch($cursor)) {
			$result[(string) $row['linkid']] = [
				'bw' => $this->parseItemIds((string) ($row['bw_itemids'] ?? '')),
				'in' => $this->parseItemIds((string) ($row['in_itemids'] ?? '')),
				'out' => $this->parseItemIds((string) ($row['out_itemids'] ?? '')),
				'source_port' => (string) ($row['source_port'] ?? ''),
				'target_port' => (string) ($row['target_port'] ?? ''),
				'trigger_source' => (int) ($row['trigger_source'] ?? self::TRIGGER_SOURCE_AUTO)
			];
		}

		return $result;
	}

	/**
	 * 重建一张图的指标配置：先删掉这张图的所有行，再按当前每条链路写回。
	 * 指标、两端端口、触发器来源三样都为空的行不落库（保持表干净）。
	 *
	 * @param array $by_link linkid => ['bw'=>string[], 'in'=>string[], 'out'=>string[], 'source_port'=>string, 'target_port'=>string, 'trigger_source'=>int]
	 */
	private function saveLinkMetrics(string $sysmapid, array $by_link): void {
		$this->ensureMetricsTable();

		$sysmapid = (int) $sysmapid;

		\DBexecute('DELETE FROM '.self::LINK_METRICS_TABLE.' WHERE sysmapid='.$sysmapid);

		foreach ($by_link as $linkid => $metrics) {
			$linkid = (int) $linkid;
			$bw = $this->parseItemIds($metrics['bw'] ?? []);
			$in = $this->parseItemIds($metrics['in'] ?? []);
			$out = $this->parseItemIds($metrics['out'] ?? []);
			$source_port = trim((string) ($metrics['source_port'] ?? ''));
			$target_port = trim((string) ($metrics['target_port'] ?? ''));
			$trigger_source = $this->normalizeTriggerSource($metrics['trigger_source'] ?? self::TRIGGER_SOURCE_AUTO);

			if ($linkid <= 0 || (!$bw && !$in && !$out && $source_port === '' && $target_port === ''
					&& $trigger_source === self::TRIGGER_SOURCE_AUTO)) {
				continue;
			}

			\DBexecute(
				'INSERT INTO '.self::LINK_METRICS_TABLE
				.' (linkid, sysmapid, bw_itemids, in_itemids, out_itemids, source_port, target_port, trigger_source) VALUES ('
				.$linkid.', '.$sysmapid
				.', '.\zbx_dbstr(implode(',', $bw))
				.', '.\zbx_dbstr(implode(',', $in))
				.', '.\zbx_dbstr(implode(',', $out))
				.', '.\zbx_dbstr($source_port)
				.', '.\zbx_dbstr($target_port)
				.', '.$trigger_source
				.')'
			);
		}
	}

	// ------------------------------------------------------------------
	// 模块设置（键值对）
	// ------------------------------------------------------------------

	private function ensureSettingsTable(): void {
		if (self::$settingsTableReady) {
			return;
		}

		self::$settingsTableReady = true;

		\DBexecute(
			'CREATE TABLE IF NOT EXISTS '.self::SETTINGS_TABLE.' ('
			.'name varchar(64) NOT NULL,'
			.'value text NULL,'
			.'PRIMARY KEY (name)'
			.')'
		);
	}

	/**
	 * 读一组关键词设置。空 / 缺失时回退到默认常量。
	 *
	 * @return string[]
	 */
	private function loadTriggerKeywords(): array {
		// 每条链路 × 每条触发器都会问一次关键词，同一次请求里只查一次库
		if (self::$triggerKeywords !== null) {
			return self::$triggerKeywords;
		}

		$this->ensureSettingsTable();

		$cursor = \DBselect(
			'SELECT value FROM '.self::SETTINGS_TABLE.' WHERE name='.\zbx_dbstr('trigger_keywords')
		);
		$row = \DBfetch($cursor);

		if (!$row || trim((string) $row['value']) === '') {
			return self::$triggerKeywords = self::LINK_TRIGGER_KEYWORDS;
		}

		$list = [];

		foreach (preg_split('/\r\n|\r|\n/', (string) $row['value']) as $line) {
			$line = trim($line);

			if ($line !== '') {
				$list[] = mb_strtolower($line);
			}
		}

		return self::$triggerKeywords = ($list ?: self::LINK_TRIGGER_KEYWORDS);
	}

	/**
	 * @param string[] $keywords
	 */
	private function saveTriggerKeywords(array $keywords): void {
		$this->ensureSettingsTable();

		$clean = [];

		foreach ($keywords as $keyword) {
			$keyword = trim((string) $keyword);

			if ($keyword !== '') {
				$clean[$keyword] = $keyword;
			}
		}

		\DBexecute(
			'DELETE FROM '.self::SETTINGS_TABLE.' WHERE name='.\zbx_dbstr('trigger_keywords')
		);

		if ($clean) {
			\DBexecute(
				'INSERT INTO '.self::SETTINGS_TABLE.' (name, value) VALUES ('
				.\zbx_dbstr('trigger_keywords').', '.\zbx_dbstr(implode("\n", array_values($clean))).')'
			);
		}

		self::$triggerKeywords = null;
	}

	/**
	 * 读某张拓扑图记录的树布局（根节点 + 方向）。没记录返回空数组。
	 *
	 * 重新发现必须在同一张图上始终用同一个根、同一个方向，否则每次扫描起点
	 * 一变，根就跟着漂移、画出来的「树」也就不是原来那棵了。所以首次生成时
	 * 把根与方向写进设置表，之后一律以它为准（老图没记录时才做启发式推断）。
	 *
	 * @return array ['root_pkey' => string, 'direction' => string]
	 */
	private function loadMapLayout(string $sysmapid): array {
		$this->ensureSettingsTable();

		$cursor = \DBselect(
			'SELECT value FROM '.self::SETTINGS_TABLE.' WHERE name='.\zbx_dbstr(self::mapLayoutKey($sysmapid))
		);
		$row = \DBfetch($cursor);

		if (!$row) {
			return [];
		}

		$data = json_decode((string) $row['value'], true);

		return is_array($data) ? $data : [];
	}

	/**
	 * 记下这张图的根与方向（sysmapid 变化即新图，不需要迁移旧记录）。
	 */
	private function saveMapLayout(string $sysmapid, string $root_pkey, string $direction): void {
		$direction = $direction === 'horizontal' ? 'horizontal' : 'vertical';

		if ($root_pkey === '') {
			return;
		}

		$this->ensureSettingsTable();

		$value = json_encode(['root_pkey' => $root_pkey, 'direction' => $direction]);

		\DBexecute(
			'DELETE FROM '.self::SETTINGS_TABLE.' WHERE name='.\zbx_dbstr(self::mapLayoutKey($sysmapid))
		);
		\DBexecute(
			'INSERT INTO '.self::SETTINGS_TABLE.' (name, value) VALUES ('
			.\zbx_dbstr(self::mapLayoutKey($sysmapid)).', '.\zbx_dbstr($value).')'
		);
	}

	/**
	 * 图删除时顺手清掉它的布局记录，避免设置表攒下无用行。
	 */
	private function dropMapLayout(string $sysmapid): void {
		$this->ensureSettingsTable();

		\DBexecute(
			'DELETE FROM '.self::SETTINGS_TABLE.' WHERE name='.\zbx_dbstr(self::mapLayoutKey($sysmapid))
		);
	}

	private static function mapLayoutKey(string $sysmapid): string {
		return 'map_layout:'.$sysmapid;
	}

	private function modeSettings(): array {
		return ['success' => true, 'trigger_keywords' => $this->loadTriggerKeywords()];
	}

	/**
	 * 保存设置。关键词用「一行一个」的纯文本传（textarea 直接提交），
	 * 也能传数组（接口调用）。
	 */
	private function modeSaveSettings(): array {
		$raw = $this->getInput('trigger_keywords', '');
		$list = is_array($raw) ? $raw : preg_split('/\r\n|\r|\n/', (string) $raw);

		$this->saveTriggerKeywords($list);

		return ['success' => true, 'trigger_keywords' => $this->loadTriggerKeywords()];
	}

	/**
	 * 自动发现刚建好图时，按创建顺序把每条线的两端端口与「触发器来源端」落库。
	 *
	 * map.create 不返回 linkid，所以回读一次 selectLinks：Zabbix 按 payload.links 数组顺序
	 * 插入，自增 linkid 递增，回读结果（按 linkid 升序）第 n 条就是第 n 条 metadata。
	 *
	 * @param array $link_meta 与创建时 links 数组同序的 ['source_port'=>, 'target_port'=>, 'trigger_source'=>]
	 */
	private function persistDiscoveredLinks(string $sysmapid, array $link_meta): void {
		if (!$link_meta) {
			return;
		}

		$maps = API::Map()->get([
			'sysmapids' => [$sysmapid],
			'output' => ['sysmapid'],
			'selectLinks' => 'extend'
		]);

		$db_links = ($maps && isset($maps[0]['links']) && is_array($maps[0]['links']))
			? array_values($maps[0]['links'])
			: [];

		$by_link = [];

		foreach ($link_meta as $index => $meta) {
			if (!isset($db_links[$index])) {
				break;
			}

			$linkid = (string) ($db_links[$index]['linkid'] ?? '');

			if ($linkid === '' || !ctype_digit($linkid)) {
				continue;
			}

			$by_link[$linkid] = [
				'bw' => [],
				'in' => [],
				'out' => [],
				'source_port' => $meta['source_port'],
				'target_port' => $meta['target_port'],
				'trigger_source' => $meta['trigger_source']
			];
		}

		$this->saveLinkMetrics($sysmapid, $by_link);
	}

	/** 把任意输入收敛成合法的「触发器来源端」取值（0~4），非法值当 AUTO。 */
	private function normalizeTriggerSource($raw): int {
		$value = (int) $raw;

		return ($value >= self::TRIGGER_SOURCE_AUTO && $value <= self::TRIGGER_SOURCE_NONE)
			? $value
			: self::TRIGGER_SOURCE_AUTO;
	}

	/**
	 * 归一化监控项 ID 列表：只保留纯数字，去重，保持顺序。
	 *
	 * @param string[]|string $raw
	 *
	 * @return string[]
	 */
	private function parseItemIds($raw): array {
		if (is_string($raw)) {
			$raw = explode(',', $raw);
		}

		if (!is_array($raw)) {
			return [];
		}

		$out = [];

		foreach ($raw as $itemid) {
			$itemid = trim((string) $itemid);

			if ($itemid !== '' && ctype_digit($itemid) && !isset($out[$itemid])) {
				$out[$itemid] = $itemid;
			}
		}

		return array_values($out);
	}

	/**
	 * 取一批监控项的元信息 + 最新值（连线指标显示用）。
	 *
	 * @param string[] $itemids
	 *
	 * @return array itemid => 元信息（含 lastvalue）
	 */
	private function fetchMetricItems(array $itemids): array {
		if (!$itemids) {
			return [];
		}

		$rows = API::Item()->get([
			'itemids' => $itemids,
			'output' => ['itemid', 'name', 'key_', 'units', 'value_type', 'lastvalue', 'lastclock'],
			'selectHosts' => ['hostid', 'name'],
			'preservekeys' => true
		]);

		$result = [];

		foreach ($rows as $itemid => $item) {
			$result[(string) $itemid] = [
				'itemid' => (string) $item['itemid'],
				'name' => (string) $item['name'],
				'key_' => (string) $item['key_'],
				'units' => (string) $item['units'],
				'value_type' => (int) $item['value_type'],
				'lastvalue' => $item['lastvalue'] === null ? null : (string) $item['lastvalue'],
				'lastclock' => (string) $item['lastclock'],
				'hostname' => (string) ($item['hosts'][0]['name'] ?? '')
			];
		}

		return $result;
	}

	/**
	 * 自动发现第一步：扫描一批主机的 SNMP 邻居表（LLDP / CDP）。
	 *
	 * 前端按 BFS 逐批调用：一次最多 8 台（前端实际按 3 台一批），单台默认超时 1.2s。
	 * 设备不通时单台要重试 2 次（约 10s），所以这里还有一层「整批总时间预算」，
	 * 超预算的主机直接跳过，避免把 nginx 的 fastcgi_read_timeout 顶爆变成 502。
	 * 返回的邻居已经和 Zabbix 主机做过匹配。
	 */
	private function modeDiscover(): array {
		$hostids = $this->parseIdList((string) $this->getInput('hostids', ''));

		if (!$hostids) {
			throw new \Exception(LanguageManager::t('自动发现：没有指定要扫描的主机'));
		}

		$hostids = array_slice($hostids, 0, 8);

		$protocols = $this->parseProtocols((string) $this->getInput('protocols', ''));
		$timeout = max(300, min(5000, (int) $this->getInput('timeout', '800')));

		$hosts = API::Host()->get([
			'hostids' => $hostids,
			'output' => ['hostid', 'name', 'host'],
			'selectInterfaces' => ['ip', 'type', 'main', 'port', 'details'],
			'preservekeys' => true
		]);

		if (!$hosts) {
			throw new \Exception(LanguageManager::t('自动发现：指定的主机不存在或没有权限'));
		}

		$macros = $this->hostMacros($hostids);
		$index = $this->discoveryIndex();

		// 类在模块自己的命名空间下，必须写全限定名：裸类名会被解析成当前命名空间的类。
		$service = new \Modules\Topomap\Services\LldpDiscovery($timeout);

		$result = [];

		// 整批时间预算：设备不通时「重试 2 次 × 两个协议」约 10s，8 台就会超过 60s 的
		// fastcgi_read_timeout（表现是整个发现请求 502）。超预算的直接跳过，
		// 前端会把它记成「未扫到」，用户可以稍后重试。
		$deadline = microtime(true) + 20.0;

		foreach ($hostids as $hostid) {
			if (!isset($hosts[$hostid])) {
				$result[] = [
					'hostid' => (string) $hostid,
					'ok' => false,
					'error' => LanguageManager::t('主机不存在或没有权限'),
					'neighbors' => []
				];

				continue;
			}

			$host = $hosts[$hostid];

			if (microtime(true) > $deadline) {
				$result[] = [
					'hostid' => (string) $hostid,
					'name' => $host['name'],
					'ok' => false,
					'error' => LanguageManager::t('本批扫描超出时间预算，已跳过（请稍后重试）'),
					'neighbors' => []
				];

				continue;
			}

			$iface = $this->snmpInterface($host);

			if (!$iface) {
				$result[] = [
					'hostid' => (string) $hostid,
					'name' => $host['name'],
					'ok' => false,
					'error' => LanguageManager::t('没有 SNMP 接口'),
					'neighbors' => []
				];

				continue;
			}

			$details = isset($iface['details']) && is_array($iface['details']) ? $iface['details'] : [];
			$version = isset($details['version']) ? (int) $details['version'] : 2;

			$community = $this->resolveMacros((string) ($details['community'] ?? 'public'), $macros[$hostid] ?? []);

			if ($version !== 3 && (trim($community) === '' || strpos($community, '{$') !== false)) {
				$result[] = [
					'hostid' => (string) $hostid,
					'name' => $host['name'],
					'ip' => (string) $iface['ip'],
					'ok' => false,
					'error' => LanguageManager::t('SNMP 社区串未定义（检查 {$SNMP_COMMUNITY} 宏）'),
					'neighbors' => []
				];

				continue;
			}

			$scan = $service->scanHost([
				'snmp_ip' => (string) $iface['ip'],
				'snmp_version' => $version,
				'community' => $community,
				'snmp_v3' => [
					'sec_name' => $this->resolveMacros((string) ($details['securityname'] ?? ''), $macros[$hostid] ?? []),
					'sec_level' => (string) ($details['securitylevel'] ?? 'authNoPriv'),
					'auth_protocol' => strtoupper((string) ($details['authprotocol'] ?? 'SHA')),
					'auth_passphrase' => $this->resolveMacros((string) ($details['authpassphrase'] ?? ''), $macros[$hostid] ?? []),
					'priv_protocol' => strtoupper((string) ($details['privprotocol'] ?? 'AES')),
					'priv_passphrase' => $this->resolveMacros((string) ($details['privpassphrase'] ?? ''), $macros[$hostid] ?? [])
				],
				'protocols' => $protocols
			]);

			$neighbors = [];
			$self_ips = [];

			foreach ($host['interfaces'] ?? [] as $host_iface) {
				if (!empty($host_iface['ip'])) {
					$self_ips[] = (string) $host_iface['ip'];
				}
			}

			// 同一台设备上报的邻居里，一个地址出现在多个「不同设备名」的邻居上 = 这不是邻居地址，
			// 而是本机管理地址被回声进了所有端口的 lldpRemManAddr（H3C / 华为常见）。
			// IP 是设备的唯一标识，一个地址不可能同时属于两台名字不同的设备 —— 这种直接判为无效。
			$addr_names = [];

			foreach ($scan['neighbors'] as $neighbor) {
				$ip = trim((string) $neighbor['ip']);

				if ($ip === '' || in_array($ip, $self_ips, true)) {
					continue;
				}

				$name = mb_strtolower(trim((string) $neighbor['sysname']));

				if ($name !== '') {
					$addr_names[$ip][$name] = true;
				}
			}

			$bad_ips = [];

			foreach ($addr_names as $ip => $names) {
				if (count($names) > 1) {
					$bad_ips[$ip] = true;
				}
			}

			foreach ($scan['neighbors'] as $neighbor) {
				$sysname = trim((string) $neighbor['sysname']);
				$chassis = trim((string) ($neighbor['chassis'] ?? ''));

				// 本机地址回声、以及「一个地址挂在多台设备名下」的地址，都不能当邻居地址用
				$neighbor_ip = trim((string) $neighbor['ip']);

				if (in_array($neighbor_ip, $self_ips, true) || isset($bad_ips[$neighbor_ip])) {
					$neighbor_ip = '';
				}

				$neighbor['ip'] = $neighbor_ip;
				$match = $this->matchNeighbor($neighbor, $index, $self_ips);

				$matched = $match['hostid'] !== '';

				// 未纳管的邻居要有个名字：sysName 优先，其次 chassisId（终端 / AP / 打印机
				// 往往只上报 MAC，sysName 为空），最后才是上报的管理 IP。
				// 三个都没有才丢 —— 与 aiops 的 name || chassisId 逻辑一致。
				$display = $sysname !== '' ? $sysname : $chassis;

				if ($display === '') {
					$display = $neighbor_ip;
				}

				if (!$matched && $display === '') {
					continue;
				}

				// 对端端口是 MAC ⇒ 对面是终端网卡（PC / 打印机 / AP），不是交换机端口，
				// 与 aiops 的 is_distribution 判断一致，据此选 Workstation 图标。
				// 注意 Windows 的 sysDescr 会让 guessKind 把它判成 server，名字里没有
				// server / 打印 / 摄像关键词时按 PC 处理（aiops 也是只看名字）。
				$kind = $this->guessKind((string) $neighbor['sysdesc'], $display);

				if (!empty($neighbor['port_mac'])
						&& in_array($kind, ['switch', 'server', 'ap'], true)
						&& !preg_match('/server|服务|printer|打印|camera|摄像/i', $display)) {
					$kind = 'pc';
				}

				$neighbors[] = [
					'protocol' => $neighbor['protocol'],
					'sysname' => $sysname,
					'chassis' => $chassis,
					// 匹配到主机就用「主机在 Zabbix 里的管理地址」，LLDP 上报的地址经常不准
					'ip' => $matched ? (string) $match['ip'] : $neighbor_ip,
					'kind' => $kind,
					'local_port' => $neighbor['local_port'],
					'port_id' => $neighbor['port_id'],
					// 匹配到 Zabbix 主机时带上 hostid，前端据此建「主机元素」而不是图片元素
					'hostid' => $match['hostid'],
					'name' => $matched ? $match['name'] : $display,
					'matched' => $match['how']
				];
			}

			$result[] = [
				'hostid' => (string) $hostid,
				'name' => $host['name'],
				'ip' => (string) $iface['ip'],
				'sysname' => $scan['sysname'],
				'ok' => $scan['ok'],
				'error' => $scan['error'],
				'neighbors' => $neighbors
			];
		}

		return ['success' => true, 'hosts' => $result];
	}

	/**
	 * 把设备 / 链路整理成一棵生成树（链路按无向处理，BFS 定父子）。
	 *
	 * 重新发现时前端给的 payload 边方向会随扫描起点变化（同一对设备这次 A→B、
	 * 下次可能 B→A），照边方向取根会让树的根漂移；这里统一按无向图 + 指定根
	 * 生成一棵树，根找不到时再退化成「入度为 0 / 连接最多」的节点。
	 *
	 * @param array  $order 节点 key，按 payload 顺序（决定邻接与兜底根的稳定顺序）
	 * @param array  $links payload.links
	 * @param string $root_key 指定的根（为空则自动挑）
	 *
	 * @return array ['children' => [key => [key, ...]], 'parent' => [key => key], 'roots' => [key, ...]]
	 */
	private function treeStructure(array $order, array $links, string $root_key = ''): array {
		$rank = [];
		$adj = [];
		$indegree = [];

		foreach ($order as $index => $key) {
			$rank[$key] = $index;
			$adj[$key] = [];
		}

		foreach ($links as $link) {
			if (!is_array($link)) {
				continue;
			}

			$source = (string) ($link['source'] ?? '');
			$target = (string) ($link['target'] ?? '');

			if ($source === '' || $target === '' || $source === $target
					|| !isset($rank[$source]) || !isset($rank[$target])) {
				continue;
			}

			$adj[$source][$target] = true;
			$adj[$target][$source] = true;
			$indegree[$target] = ($indegree[$target] ?? 0) + 1;
		}

		// 邻接表按 payload 顺序展开，保证同样的数据每次得到同样的树
		foreach ($adj as $key => $set) {
			$neighbors = array_keys($set);
			usort($neighbors, function (string $a, string $b) use ($rank): int {
				return $rank[$a] <=> $rank[$b];
			});
			$adj[$key] = $neighbors;
		}

		if ($root_key === '' || !isset($rank[$root_key])) {
			$root_key = $this->pickTreeRoot($order, $adj, $indegree);
		}

		$children = [];
		$parent = [];
		$roots = [];
		$visited = [];

		$walk = function (string $start) use (&$children, &$parent, &$roots, &$visited, $adj): void {
			$visited[$start] = true;
			$roots[] = $start;
			$queue = [$start];

			while ($queue) {
				$key = array_shift($queue);

				foreach ($adj[$key] ?? [] as $neighbor) {
					if (isset($visited[$neighbor])) {
						continue;
					}

					$visited[$neighbor] = true;
					$parent[$neighbor] = $key;
					$children[$key][] = $neighbor;
					$queue[] = $neighbor;
				}
			}
		};

		if ($root_key !== '') {
			$walk($root_key);
		}

		// 没连到主树的独立组件：各自成树，整体排在主树旁边（不丢节点）
		foreach ($order as $key) {
			if (!isset($visited[$key])) {
				$walk($key);
			}
		}

		return ['children' => $children, 'parent' => $parent, 'roots' => $roots];
	}

	/**
	 * 自动挑根：优先「没有入边的源节点」里连接最多的那个，其次全图连接最多的节点。
	 * 用连接数兜底可以避免把一条没有邻居的孤点当成整张图的根。
	 */
	private function pickTreeRoot(array $order, array $adj, array $indegree): string {
		$best_source = '';
		$best_source_degree = -1;
		$best = '';
		$best_degree = -1;

		foreach ($order as $key) {
			$degree = count($adj[$key] ?? []);

			if ($degree > $best_degree) {
				$best = $key;
				$best_degree = $degree;
			}

			if (($indegree[$key] ?? 0) === 0 && $degree > $best_source_degree) {
				$best_source = $key;
				$best_source_degree = $degree;
			}
		}

		return $best_source !== '' ? $best_source : $best;
	}

	/**
	 * 生成树在给定 payload 下的根 key（生成新图时用，保证落库后记下的根与
	 * treeLayout() 实际采用的根一致）。
	 */
	private function treeRootKey(array $nodes, array $links): string {
		$order = [];
		$seen = [];

		foreach ($nodes as $node) {
			if (!is_array($node) || !isset($node['key'])) {
				continue;
			}

			$key = (string) $node['key'];

			if (!isset($seen[$key])) {
				$seen[$key] = true;
				$order[] = $key;
			}
		}

		if (!$order) {
			return '';
		}

		$structure = $this->treeStructure($order, $links, '');

		return (string) ($structure['roots'][0] ?? '');
	}

	/**
	 * 自动发现的树形布局（对齐 aiops 的 tidy tree）：
	 *   - 指定根节点在最上方（direction=horizontal 时在最左侧）；
	 *   - 子节点沿横轴排开，父节点居中于子节点；
	 *   - 主方向按树深度逐层递增（垂直 = y 向下，水平 = x 向右）。
	 *
	 * @param array  $nodes payload.nodes
	 * @param array  $links payload.links
	 * @param string $root_key 指定根节点（重新发现时传老图的根，保证根不漂移）
	 * @param string $direction vertical / horizontal，由老图的方向决定
	 *
	 * @return array key => ['x' => int, 'y' => int]（x/y 是图标左上角，按默认 96 宽居中）
	 */
	private function treeLayout(array $nodes, array $links, string $root_key = '', string $direction = 'vertical'): array {
		$order = [];
		$seen = [];

		foreach ($nodes as $node) {
			if (!is_array($node) || !isset($node['key'])) {
				continue;
			}

			$key = (string) $node['key'];

			if (!isset($seen[$key])) {
				$seen[$key] = true;
				$order[] = $key;
			}
		}

		if (!$order) {
			return [];
		}

		$structure = $this->treeStructure($order, $links, $root_key);
		$horizontal = $direction === 'horizontal';
		// 叶子槽宽：垂直树沿 x 排（96 图标 + 标签 + 间距）；水平树沿 y 排，
		// 图标 96 + 下方名称/状态约 60，给到 170 才不会压住上一台的文字。
		$cross_gap = $horizontal ? 170 : 190;
		// 层间距：垂直是 y 向下、水平是 x 向右，都要给主干/带宽框留出空间。
		$level_gap = 300;
		$start = 80;
		$cursor = $start;
		$pos = [];
		$placed = [];

		$place = function (string $key, int $depth) use (&$place, &$cursor, &$pos, &$placed, $structure,
				$cross_gap, $level_gap, $start, $horizontal) {
			if (isset($placed[$key])) {
				return isset($pos[$key]) ? ($horizontal ? $pos[$key]['y'] : $pos[$key]['x']) + 48 : $cursor;
			}

			$placed[$key] = true;
			$kids = $structure['children'][$key] ?? [];

			if (!$kids) {
				$center = $cursor;
				$cursor += $cross_gap;
			}
			else {
				$centers = [];

				foreach ($kids as $kid) {
					$centers[] = $place($kid, $depth + 1);
				}

				$center = (min($centers) + max($centers)) / 2;
			}

			if ($horizontal) {
				$pos[$key] = ['x' => $start + $depth * $level_gap, 'y' => (int) round($center - 48)];
			}
			else {
				$pos[$key] = ['x' => (int) round($center - 48), 'y' => $start + $depth * $level_gap];
			}

			return $center;
		};

		foreach ($structure['roots'] as $index => $root) {
			if ($index > 0) {
				$cursor += 100;
			}

			$place($root, 0);
		}

		// 兜底：链路没涉及的孤立节点排在同一行最右边
		foreach ($order as $key) {
			if (!isset($pos[$key])) {
				$pos[$key] = $horizontal
					? ['x' => $start, 'y' => $cursor]
					: ['x' => $cursor, 'y' => $start];
				$cursor += $cross_gap;
			}
		}

		return $pos;
	}

	/**
	 * 从老图读出「根节点」和「树的方向」。
	 *
	 * 优先用生成/上次重排时记下的布局元数据（root_pkey + direction），这样同一张
	 * 图无论从哪台设备起扫，重新发现都会回到同一个根、同一个方向。
	 * 老图没有元数据时按坐标推断（见 inferTreeLayout）。
	 *
	 * @param string $sysmapid     当前拓扑图
	 * @param array  $old_selements map.get 回来的旧元素
	 * @param array  $old_links     旧的 links
	 * @param array  $payload_pkeys 本次 payload 里出现的元素匹配键（只在这些元素里挑根）
	 *
	 * @return array ['direction' => vertical|horizontal, 'root_pkey' => string]
	 */
	private function detectTreeLayout(string $sysmapid, array $old_selements, array $old_links,
			array $payload_pkeys): array {
		$stored = $this->loadMapLayout($sysmapid);
		$direction = (string) ($stored['direction'] ?? '');

		if (!in_array($direction, ['vertical', 'horizontal'], true)) {
			$direction = '';
		}

		$root_pkey = (string) ($stored['root_pkey'] ?? '');

		// 记下的根这次没扫到（设备下线 / 改名）⇒ 只剩方向可用，根按坐标重挑
		if ($root_pkey !== '' && $payload_pkeys && !isset($payload_pkeys[$root_pkey])) {
			$root_pkey = '';
		}

		if ($direction !== '' && $root_pkey !== '') {
			return ['direction' => $direction, 'root_pkey' => $root_pkey];
		}

		$inferred = $this->inferTreeLayout($old_selements, $old_links, $payload_pkeys, $direction);

		return [
			'direction' => $direction !== '' ? $direction : $inferred['direction'],
			'root_pkey' => $root_pkey !== '' ? $root_pkey : $inferred['root_pkey']
		];
	}

	/**
	 * 老图没有布局元数据时，用坐标推断根与方向。
	 *
	 * 方向：树是「整齐树」，同一层元素的主轴坐标完全对齐，所以主轴上的坐标簇
	 * 数量远少于交叉轴，簇少的那根轴就是层级方向（y 少 = 自上而下；x 少 = 自左
	 * 向右）。不能只看老链路走向：老图一旦被错误布局污染，横向对角连线会占多数，
	 * 反而把方向带偏。
	 *
	 * 根：取主轴最靠前那一层里、交叉轴最接近整图中心（整棵树根应居中于所有
	 * 后代）的元素，同分取连接数多的；优先有连线的元素，全是孤点时退回最靠前。
	 *
	 * @return array ['direction' => string, 'root_pkey' => string]
	 */
	private function inferTreeLayout(array $old_selements, array $old_links, array $payload_pkeys,
			string $direction = ''): array {
		$points = [];
		$degree = [];
		$sum_dx = 0;
		$sum_dy = 0;

		foreach ($old_selements as $se) {
			$selementid = (string) ($se['selementid'] ?? '');

			if ($selementid !== '') {
				$points[$selementid] = [
					'x' => (int) ($se['x'] ?? 0),
					'y' => (int) ($se['y'] ?? 0)
				];
			}
		}

		foreach ($old_links as $link) {
			$id1 = (string) ($link['selementid1'] ?? '');
			$id2 = (string) ($link['selementid2'] ?? '');

			if ($id1 === '' || $id2 === '' || !isset($points[$id1]) || !isset($points[$id2])) {
				continue;
			}

			$sum_dx += abs($points[$id1]['x'] - $points[$id2]['x']);
			$sum_dy += abs($points[$id1]['y'] - $points[$id2]['y']);
			$degree[$id1] = ($degree[$id1] ?? 0) + 1;
			$degree[$id2] = ($degree[$id2] ?? 0) + 1;
		}

		$candidates = [];

		foreach ($old_selements as $se) {
			$selementid = (string) ($se['selementid'] ?? '');
			$pkey = $this->elementPKey($se);

			if ($selementid === '' || $pkey === '' || !isset($points[$selementid])) {
				continue;
			}

			if ($payload_pkeys && !isset($payload_pkeys[$pkey])) {
				continue;
			}

			$candidates[] = [
				'pkey' => $pkey,
				'x' => $points[$selementid]['x'],
				'y' => $points[$selementid]['y'],
				'degree' => $degree[$selementid] ?? 0
			];
		}

		if (!$candidates) {
			return ['direction' => $direction !== '' ? $direction : 'vertical', 'root_pkey' => ''];
		}

		if ($direction === '') {
			$direction = $this->inferTreeDirection($candidates, $sum_dx, $sum_dy);
		}

		$vertical = $direction !== 'horizontal';
		// 有连线的元素才可能是树根，孤点（没被任何链路引用的旧元素）不参与
		$linked = array_values(array_filter($candidates, function (array $c): bool {
			return $c['degree'] > 0;
		}));

		if (!$linked) {
			$linked = $candidates;
		}

		$min_main = null;
		$cross_values = [];

		foreach ($linked as $c) {
			$main = $vertical ? $c['y'] : $c['x'];

			if ($min_main === null || $main < $min_main) {
				$min_main = $main;
			}
		}

		foreach ($candidates as $c) {
			// 用图标中心（左上角 +48）比较，根居中于整棵树时最接近整图中心
			$cross_values[] = ($vertical ? $c['x'] : $c['y']) + 48;
		}

		sort($cross_values, SORT_NUMERIC);
		$center = $cross_values[(int) floor(count($cross_values) / 2)];

		$best_pkey = '';
		$best_dist = PHP_INT_MAX;
		$best_degree = -1;

		foreach ($linked as $c) {
			$main = $vertical ? $c['y'] : $c['x'];

			// 最靠前的一层：与最小值相差不到一个层间距
			if ($main - $min_main > 150) {
				continue;
			}

			$dist = abs((($vertical ? $c['x'] : $c['y']) + 48) - $center);

			if ($dist < $best_dist || ($dist === $best_dist && $c['degree'] > $best_degree)) {
				$best_pkey = $c['pkey'];
				$best_dist = $dist;
				$best_degree = $c['degree'];
			}
		}

		return ['direction' => $direction, 'root_pkey' => $best_pkey];
	}

	/**
	 * 按坐标簇数量推断树的层级方向。
	 *
	 * 整齐树同一层的元素主轴坐标对齐（簇数 ≈ 层数），交叉轴则一个元素一个位置
	 * （簇数 ≈ 节点数）。簇数差距不明显时退回老链路整体走向，再不行按垂直树处理
	 * （模块自动生成的图默认就是自上而下）。
	 */
	private function inferTreeDirection(array $candidates, int $sum_dx, int $sum_dy): string {
		$xs = [];
		$ys = [];

		foreach ($candidates as $c) {
			$xs[] = $c['x'];
			$ys[] = $c['y'];
		}

		$yclusters = $this->countCoordinateClusters($ys);
		$xclusters = $this->countCoordinateClusters($xs);

		if ($yclusters * 3 <= $xclusters * 2) {
			return 'vertical';
		}

		if ($xclusters * 3 <= $yclusters * 2) {
			return 'horizontal';
		}

		return $sum_dy >= $sum_dx ? 'vertical' : 'horizontal';
	}

	/**
	 * 数坐标聚成多少簇（相邻差值超过一个图标宽就视为新的一层）。
	 */
	private function countCoordinateClusters(array $values): int {
		if (!$values) {
			return 0;
		}

		sort($values, SORT_NUMERIC);

		$clusters = 1;
		$anchor = $values[0];

		foreach ($values as $value) {
			if ($value - $anchor > 100) {
				$clusters++;
				$anchor = $value;
			}
		}

		return $clusters;
	}

	/**
	 * 「保留现有坐标」的新元素增量摆放：
	 *   - 老元素坐标一律不动；
	 *   - 新元素按生成树挂到父节点「下方」（垂直树）/「右侧」（水平树），
	 *     同一父节点下多台新设备沿横轴错开，避开已占位的元素；
	 *   - 连不到已有树的独立新组件，整体排在已有图的下一层，不并进主树。
	 *
	 * @param array  $nodes         payload.nodes
	 * @param array  $structure     treeStructure() 的结果
	 * @param array  $old_selements 旧元素（取坐标占位）
	 * @param string $direction     vertical / horizontal
	 *
	 * @return array key => ['x' => int, 'y' => int]
	 */
	private function keepLayoutPositions(array $nodes, array $structure, array $old_selements, string $direction): array {
		$vertical = $direction !== 'horizontal';
		$level_gap = 300;
		$cross_gap = $vertical ? 190 : 170;

		$old_pos = [];
		$occupied = [];

		foreach ($old_selements as $se) {
			$pkey = $this->elementPKey($se);

			if ($pkey === '' || isset($old_pos[$pkey])) {
				continue;
			}

			$point = ['x' => (int) ($se['x'] ?? 0), 'y' => (int) ($se['y'] ?? 0)];
			$old_pos[$pkey] = $point;
			$occupied[] = $point;
		}

		$pkey_of = [];

		foreach ($nodes as $node) {
			if (is_array($node) && isset($node['key'])) {
				$pkey_of[(string) $node['key']] = $this->nodePKey($node);
			}
		}

		// 老元素在树里的位置：pkey 对上的直接沿用原坐标
		$placed = [];

		foreach ($pkey_of as $key => $pkey) {
			if ($pkey !== '' && isset($old_pos[$pkey])) {
				$placed[$key] = $old_pos[$pkey];
			}
		}

		$max_main = 80;
		$max_cross = 80;

		foreach ($occupied as $point) {
			$max_main = max($max_main, $vertical ? $point['y'] : $point['x']);
			$max_cross = max($max_cross, $vertical ? $point['x'] : $point['y']);
		}

		// 独立新组件（连不到已有图的）统一另起一行排在已有图下方 / 右侧，
		// 不随主方向逐级下沉，否则多棵树会被摆成一条斜线
		$annex_main = $max_main + $level_gap;
		$annex_cross = 40;

		$free = function (int $x, int $y) use (&$occupied): bool {
			foreach ($occupied as $point) {
				if (abs($x - $point['x']) < 150 && abs($y - $point['y']) < 150) {
					return false;
				}
			}

			return true;
		};

		$queue = [];
		$results = [];

		foreach ($structure['roots'] as $root) {
			$queue[] = $root;
		}

		while ($queue) {
			$key = array_shift($queue);

			foreach ($structure['children'][$key] ?? [] as $kid) {
				$queue[] = $kid;
			}

			if (isset($placed[$key])) {
				continue;
			}

			$parent_key = (string) ($structure['parent'][$key] ?? '');
			$point = null;

			if ($parent_key !== '' && isset($placed[$parent_key])) {
				$parent = $placed[$parent_key];
				$parent_cross = ($vertical ? $parent['x'] : $parent['y']) + 48;
				$parent_main = $vertical ? $parent['y'] : $parent['x'];

				// 父节点正下方 / 正右侧开始，向两侧逐个让位：0、-1、+1、-2、+2 …
				// 大扇出（一台交换机下挂几十台新设备）要留足步数，否则找不到空位会掉到图外
				for ($step = 0; $step < 40 && $point === null; $step++) {
					$offset = (int) (ceil($step / 2) * $cross_gap) * ($step % 2 === 0 ? 1 : -1);
					$cross = $parent_cross + $offset - 48;
					$main = $parent_main + $level_gap;
					$x = $vertical ? $cross : $main;
					$y = $vertical ? $main : $cross;

					if ($cross >= 20 && $free($x, $y)) {
						$point = ['x' => $x, 'y' => $y];
					}
				}
			}

			if ($point === null) {
				// 新组件（或父节点不在本次发现里）：沿主方向排到已有图之外
				for ($step = 0; $step < 40; $step++) {
					$x = $vertical ? $annex_cross : $annex_main;
					$y = $vertical ? $annex_main : $annex_cross;

					if ($free($x, $y)) {
						$point = ['x' => $x, 'y' => $y];
						break;
					}

					$annex_cross += $cross_gap;
				}
			}

			if ($point === null) {
				$point = ['x' => $vertical ? 80 : $annex_main, 'y' => $vertical ? $annex_main : 80];
			}

			$placed[$key] = $point;
			$results[$key] = $point;
			$occupied[] = $point;
			$max_main = max($max_main, $vertical ? $point['y'] : $point['x']);
			$max_cross = max($max_cross, $vertical ? $point['x'] : $point['y']);
		}

		return $results;
	}

	/**
	 * 自动发现第二步：把前端算好的节点/链路写成一张新的拓扑图。
	 *
	 * 布局在这里按 aiops 的 tidy tree（整齐树）算：根在最上方，
	 * 子节点横排、父节点居中于子节点之上，逐层向下。
	 */
	private function modeDiscoverSave(): array {
		$raw = (string) $this->getInput('payload', '');
		$payload = json_decode($raw, true);

		if (!is_array($payload) || empty($payload['nodes']) || !is_array($payload['nodes'])) {
			throw new \Exception(LanguageManager::t('自动发现：没有可生成的内容'));
		}

		// 自动关联触发器的关键词：前端「自动发现」页面填的（一行一个）优先，
		// 顺手存起来，之后链接属性里改「触发器来源」重匹配也用同一份。
		if (!empty($payload['keywords']) && is_array($payload['keywords'])) {
			$this->saveTriggerKeywords($payload['keywords']);
		}

		$name = trim((string) $this->getInput('name', ''));

		if ($name === '') {
			$name = LanguageManager::t('自动发现').' '.date('Y-m-d H:i');
		}

		$name = $this->uniqueMapName($this->normalizeName($name));

		$selements = [];
		$host_of_node = [];
		$payload_links = isset($payload['links']) && is_array($payload['links']) ? $payload['links'] : [];
		// 生成时就把树根定下来并记进设置表，之后重新发现一律沿用这个根
		$root_key = $this->treeRootKey($payload['nodes'], $payload_links);
		$positions = $this->treeLayout($payload['nodes'], $payload_links, $root_key);
		$root_pkey = '';

		if ($root_key !== '') {
			foreach ($payload['nodes'] as $node) {
				if (is_array($node) && isset($node['key']) && (string) $node['key'] === $root_key) {
					$root_pkey = $this->nodePKey($node);
					break;
				}
			}
		}

		foreach ($payload['nodes'] as $node) {
			if (!is_array($node) || !isset($node['key'])) {
				continue;
			}

			$key = (string) $node['key'];
			$p = $positions[$key] ?? ['x' => 80, 'y' => 80];
			$x = (int) $p['x'];
			$y = (int) $p['y'];

			$label = (string) ($node['name'] ?? '');

			if (!empty($node['ip'])) {
				$label = $label."\n".(string) $node['ip'];
			}

			$hostid = isset($node['hostid']) ? trim((string) $node['hostid']) : '';

			// 主机元素的默认标签：名字一行、连接地址一行。库里存宏原文，
			// 由渲染端按主机的当前值展开，主机改名后不用重新发现也能跟上。
			if ($hostid !== '' && ctype_digit($hostid) && $hostid !== '0') {
				$label = self::DEFAULT_HOST_LABEL;
			}

			$selementid = (string) (count($selements) + 1);
			// 按设备类型选图标：交换机 Switch_(128)、终端 Workstation_(128)（对齐 aiops）
			$iconid = $this->iconForKind((string) ($node['kind'] ?? ''));

			// 触发匹配要用节点对应的 Zabbix 主机（未纳管的邻居没有 hostid，只能靠对面一端匹配）
			$host_of_node[$key] = ctype_digit($hostid) && $hostid !== '0' ? $hostid : '';

			if ($hostid !== '' && ctype_digit($hostid) && $hostid !== '0') {
				// 已纳管：主机元素，预览里能显示状态行、点开能进主机
				$selement = [
					'selementid' => $selementid,
					'elementtype' => 0,
					'elements' => [['hostid' => $hostid]],
					'label' => $label,
					'label_location' => 0,
					'x' => $x,
					'y' => $y,
					'iconid_off' => $iconid,
					'use_iconmap' => 0,
					'evaltype' => 0
				];
			}
			else {
				// 未纳管（对端设备没建主机）：用图片元素，只显示名字与 IP
				$selement = [
					'selementid' => $selementid,
					'elementtype' => 4,
					'label' => $label,
					'label_location' => 0,
					'x' => $x,
					'y' => $y,
					'iconid_off' => $iconid
				];
			}

			$selements[$key] = $selement;
		}

		if (!$selements) {
			throw new \Exception(LanguageManager::t('自动发现：没有可生成的设备'));
		}

		$links = [];
		$pairs = [];
		// 已建过的「有向设备对」：反向扫到的同一条物理链路整个丢掉（aiops 的做法）。
		// 只靠端口对去重不够：设备被限流时两端的本地端口名可能解析不一致，
		// 端口对就合不掉，会多出一条重复链路，并把对端不相干端口的 down 触发器绑进来。
		$directed = [];
		// 落库后回读认领 linkid 用：按创建顺序记下每条线的两端端口与「触发器来源端」
		$link_meta = [];
		// 每台主机的触发器列表只查一次（同一台交换机会出现在多条链路上）
		$trigger_cache = [];
		$trigger_total = 0;

		foreach ((isset($payload['links']) && is_array($payload['links']) ? $payload['links'] : []) as $link) {
			if (!is_array($link) || !isset($link['source'], $link['target'])) {
				continue;
			}

			$source = (string) $link['source'];
			$target = (string) $link['target'];

			if (!isset($selements[$source], $selements[$target]) || $source === $target) {
				continue;
			}

			// 反向重复（B→A 已经存在 A→B）一律跳过，见 $directed 的注释
			if (isset($directed[$target.'|'.$source])) {
				continue;
			}

			// 同一对设备之间可能有多条链路（堆叠 / 链路聚合的多个成员口），
			// 所以去重键要带上「两端端口」：既保留聚合的多条成员线，
			// 又能把同方向重复上报的线合并成一条。
			// 端口为空时退化成「设备对」，与旧行为一致。
			// 端口名要归一化后再比：核心交换机报的是 Comware 原始数字（已被 LldpDiscovery
			// 还原成 GigabitEthernet1/0/11），对端报的是 Gi1/0/11，归一后都是 ge1011，
			// 重复线才能合并，同时 LAG 的两个成员口各自成线。
			$end_a = $source.'#'.$this->ifPortKey((string) ($link['source_port'] ?? ''));
			$end_b = $target.'#'.$this->ifPortKey((string) ($link['target_port'] ?? ''));
			$pair = $end_a < $end_b ? $end_a.'|'.$end_b : $end_b.'|'.$end_a;

			if (isset($pairs[$pair])) {
				continue;
			}

			$pairs[$pair] = true;
			$directed[$source.'|'.$target] = true;

			$row = [
				'selementid1' => $selements[$source]['selementid'],
				'selementid2' => $selements[$target]['selementid'],
				// 正常态用绿色，触发器报警时由绑定的链路指示器改成红色（原生 sysmap 机制）
				'color' => self::DISCOVERY_LINK_COLOR,
				'drawtype' => 0
			];

			// 每条线可指定「触发器来源端」（源端 / 本端 / 两端 / 不关联），默认源端优先
			$trigger_source = $this->normalizeTriggerSource($link['trigger_source'] ?? self::TRIGGER_SOURCE_AUTO);

			// 自动关联端口状态类触发器：命中后原生地图与模块画布都会在报警时把这条线画红
			$triggerids = $this->matchLinkTriggers(
				$host_of_node[$source] ?? '',
				$host_of_node[$target] ?? '',
				(string) ($link['source_port'] ?? ''),
				(string) ($link['target_port'] ?? ''),
				$trigger_cache,
				$trigger_source
			);

			if ($triggerids) {
				$row['linktriggers'] = [];

				foreach ($triggerids as $triggerid) {
					$row['linktriggers'][] = [
						'triggerid' => $triggerid,
						'drawtype' => 0,
						'color' => self::DISCOVERY_TRIGGER_COLOR
					];
				}

				// Zabbix 8.0 只允许 indicator_type=1 的连线携带 linktriggers，
				// 不写的话整张图会被 map.create 判为参数非法（8.0 之前的版本不写该字段）。
				$indicator_type = self::linkIndicatorType(true);

				if ($indicator_type !== null) {
					$row['indicator_type'] = $indicator_type;
				}

				$trigger_total += count($triggerids);
			}

			$links[] = $row;
			$link_meta[] = [
				'source_port' => (string) ($link['source_port'] ?? ''),
				'target_port' => (string) ($link['target_port'] ?? ''),
				'trigger_source' => $trigger_source
			];
		}

		$width = 80;
		$height = 80;

		foreach ($positions as $point) {
			$width = max($width, $point['x'] + 220);
			$height = max($height, $point['y'] + 180);
		}

		$message_offset = $this->apiMessageCount();

		$result = API::Map()->create([[
			'name' => $name,
			'width' => $width,
			'height' => $height,
			'label_type' => 0,
			'label_location' => 0,
			'grid_show' => 0,
			'grid_align' => 1,
			'grid_size' => 50,
			'highlight' => 1,
			'private' => 0,
			'selements' => array_values($selements),
			'links' => $links
		]]);

		$sysmapid = '';

		if (is_array($result)) {
			if (!empty($result['sysmapids'][0])) {
				$sysmapid = (string) $result['sysmapids'][0];
			}
			elseif (!empty($result['sysmapid'])) {
				$sysmapid = (string) $result['sysmapid'];
			}
		}

		if ($sysmapid === '') {
			$detail = $this->apiErrorDetail($message_offset);

			if ($detail !== '') {
				throw new \Exception(LanguageManager::tf('自动发现：生成拓扑图失败：%1$s', $detail));
			}

			throw new \Exception(LanguageManager::t('自动发现：Zabbix 没有返回拓扑图 ID'));
		}

		// map.create 不返回 linkid：回读一次按创建顺序对应上，把每条线的两端端口与
		// 「触发器来源端」写进模块自建表，前端才能在线上标注端口、按端重新匹配触发器。
		$this->persistDiscoveredLinks($sysmapid, $link_meta);
		$this->saveMapLayout($sysmapid, $root_pkey, 'vertical');

		return [
			'success' => true,
			'sysmapid' => $sysmapid,
			'name' => $name,
			'devices' => count($selements),
			'links' => count($links),
			'triggers' => $trigger_total
		];
	}

	/**
	 * 画布内「重新发现」：把本次重新扫到的节点/链路就地并入已有拓扑图。
	 *
	 * 与 modeDiscoverSave() 的区别：
	 *   - 复用已有元素的 selementid、已有链路的 linkid，从而保住 topomap_link_metrics
	 *     里按 linkid 存的带宽 / 上行 / 下行监控项；
	 *   - 用 map.update 提交合并后的全量 selements / links / shapes（未提交的已有项会被删除）；
	 *   - 未扫到的设备与手工连线默认原样保留；只有 prune_links=1 时才移除未复用的自动连线。
	 */
	private function modeRediscover(): array {
		$sysmapid = $this->requireSysmapid();
		$payload = json_decode((string) $this->getInput('payload', ''), true);

		if (!is_array($payload) || empty($payload['nodes']) || !is_array($payload['nodes'])) {
			throw new \Exception(LanguageManager::t('重新发现：没有可更新的内容'));
		}

		// 关键词与自动发现共用一份设置
		if (!empty($payload['keywords']) && is_array($payload['keywords'])) {
			$this->saveTriggerKeywords($payload['keywords']);
		}

		$maps = API::Map()->get([
			'sysmapids' => [$sysmapid],
			'output' => ['sysmapid', 'name', 'width', 'height'],
			'selectSelements' => ['selementid', 'elementtype', 'elements', 'iconid_off', 'iconid_on',
				'iconid_disabled', 'iconid_maintenance', 'label', 'label_location', 'x', 'y', 'use_iconmap',
				'evaltype', 'tags', 'urls', 'elementsubtype', 'areatype', 'width', 'height', 'viewtype'
			],
			'selectLinks' => 'extend',
			'selectShapes' => ['sysmap_shapeid', 'type', 'x', 'y', 'width', 'height', 'text', 'font',
				'font_size', 'font_color', 'text_halign', 'text_valign', 'border_type', 'border_width',
				'border_color', 'background_color', 'zindex'
			]
		]);

		if (!$maps) {
			throw new \Exception(LanguageManager::t('拓扑图不存在，或你没有查看权限'));
		}

		$map = $maps[0];
		$old_selements = isset($map['selements']) && is_array($map['selements'])
			? array_values($map['selements'])
			: [];
		$old_links = isset($map['links']) && is_array($map['links'])
			? array_values($map['links'])
			: [];
		$old_shapes = isset($map['shapes']) && is_array($map['shapes'])
			? array_values($map['shapes'])
			: [];
		$old_metrics = $this->loadLinkMetrics($sysmapid);
		$old_paths = $this->loadLinkPaths($sysmapid);

		// 老链路按 linkid 升序，保证「同设备对回退匹配」时优先复用编号最小的那条
		usort($old_links, function (array $a, array $b): int {
			return (int) ($a['linkid'] ?? 0) <=> (int) ($b['linkid'] ?? 0);
		});

		$payload_links = isset($payload['links']) && is_array($payload['links']) ? $payload['links'] : [];
		$keep_layout = (string) ($payload['layout'] ?? 'tree') === 'keep';
		$prune_links = !empty($payload['prune_links']);

		// 老图的方向与根节点：重排要沿用原来的根、原来的层级方向，不能因为
		// 本次扫描方向变了就把根换掉、或把自上而下的树改成左右布局。
		$payload_pkeys = [];
		$node_keys = [];
		$key_pkey = [];

		foreach ($payload['nodes'] as $node) {
			if (!is_array($node) || !isset($node['key'])) {
				continue;
			}

			$key = (string) $node['key'];
			$node_keys[] = $key;
			$pkey = $this->nodePKey($node);
			$key_pkey[$key] = $pkey;

			if ($pkey !== '') {
				$payload_pkeys[$pkey] = true;
			}
		}

		$layout_hint = $this->detectTreeLayout((string) $sysmapid, $old_selements, $old_links, $payload_pkeys);
		$direction = (string) ($layout_hint['direction'] ?? 'vertical');
		$root_key = '';

		if ((string) ($layout_hint['root_pkey'] ?? '') !== '') {
			foreach ($payload['nodes'] as $node) {
				if (is_array($node) && isset($node['key'])
						&& $this->nodePKey($node) === $layout_hint['root_pkey']) {
					$root_key = (string) $node['key'];
					break;
				}
			}
		}

		$structure = $this->treeStructure($node_keys, $payload_links, $root_key);
		// 记下实际生效的根（元数据里没有、由 BFS 兜底挑出来的也算）
		$effective_root_key = $root_key !== '' ? $root_key : (string) ($structure['roots'][0] ?? '');
		$root_pkey = (string) ($key_pkey[$effective_root_key] ?? '');
		$positions = $this->treeLayout($payload['nodes'], $payload_links, $effective_root_key, $direction);
		$keep_positions = [];

		if ($keep_layout) {
			$keep_positions = $this->keepLayoutPositions($payload['nodes'], $structure, $old_selements, $direction);
		}
		// 老图升级：旧元素如果还是自动发现给的默认图标，按类型换成 Switch_/Workstation；
		// 用户手动选过其它图标的不动。这里先取一次默认图标做比较基准。
		$default_iconid = $this->defaultIconId();

		// ------------------------------------------------------------
		// 元素：按 h:<hostid> / i:<标签首行> 匹配旧元素，命中则原地更新
		// ------------------------------------------------------------

		$old_by_pkey = [];
		$selement_pkey = [];

		foreach ($old_selements as $se) {
			$pkey = $this->elementPKey($se);
			$selement_pkey[(string) ($se['selementid'] ?? '')] = $pkey;

			if ($pkey !== '' && !isset($old_by_pkey[$pkey])) {
				$old_by_pkey[$pkey] = $se;
			}
		}

		$selements = [];        // selementid => row
		$node_selementid = [];  // payload node key => selementid
		$node_pkey = [];        // payload node key => pkey
		$host_of_node = [];     // payload node key => hostid（未纳管为空）
		$matched = [];          // 已匹配的旧 selementid
		$new_seq = 0;
		$added_devices = 0;

		foreach ($payload['nodes'] as $node) {
			if (!is_array($node) || !isset($node['key'])) {
				continue;
			}

			$key = (string) $node['key'];
			$hostid = trim((string) ($node['hostid'] ?? ''));
			$valid_host = $hostid !== '' && ctype_digit($hostid) && $hostid !== '0';
			$pkey = $this->nodePKey($node);
			$label = (string) ($node['name'] ?? '');

			if (!empty($node['ip'])) {
				$label = $label."\n".(string) $node['ip'];
			}

			// 新加进来的主机元素用两行宏标签（名字 / 连接地址）；未纳管图片仍写死名字与 IP
			if ($valid_host) {
				$label = self::DEFAULT_HOST_LABEL;
			}

			$node_pkey[$key] = $pkey;
			$host_of_node[$key] = $valid_host ? $hostid : '';
			$node_icon = $this->iconForKind((string) ($node['kind'] ?? ''));

			$old = ($pkey !== '' && isset($old_by_pkey[$pkey])) ? $old_by_pkey[$pkey] : null;

			if ($old !== null) {
				unset($old_by_pkey[$pkey]);

				$selementid = (string) ($old['selementid'] ?? '');
				$row = $old;
				$row['elementtype'] = (int) ($old['elementtype'] ?? 0);

				// 老图里还是自动发现给的默认图标（Server_(96)）时，按设备类型换成
				// Switch_(128) / Workstation_(128)，免得上一次生成的图永远全是 Server；
				// 手动改过图标的不覆盖。
				if ($node_icon !== '' && (string) ($row['iconid_off'] ?? '0') === $default_iconid) {
					$row['iconid_off'] = $node_icon;
				}

				// 上次还是未纳管图片、这次对上主机了：就地升级成主机元素，保留图标与标签
				if ($valid_host && $row['elementtype'] === 4) {
					$row['elementtype'] = 0;
					$row['elements'] = [['hostid' => $hostid]];
					$row['label_location'] = 0;
				}

				if (trim((string) ($row['label'] ?? '')) === '') {
					$row['label'] = $label;
				}

				if (!$keep_layout && isset($positions[$key])) {
					$row['x'] = (int) $positions[$key]['x'];
					$row['y'] = (int) $positions[$key]['y'];
				}

				$matched[$selementid] = true;
			}
			else {
				$selementid = 'new'.(++$new_seq);

				if ($valid_host) {
					$row = [
						'selementid' => $selementid,
						'elementtype' => 0,
						'elements' => [['hostid' => $hostid]],
						'label' => $label,
						'label_location' => 0,
						'iconid_off' => $node_icon,
						'use_iconmap' => 0,
						'evaltype' => 0
					];
				}
				else {
					$row = [
						'selementid' => $selementid,
						'elementtype' => 4,
						'label' => $label,
						'label_location' => 0,
						'iconid_off' => $node_icon
					];
				}

				if ($keep_layout) {
					// 老元素原地不动；新设备按生成树挂到父节点下方 / 右侧
					$point = $keep_positions[$key] ?? ['x' => 80, 'y' => 80];
					$row['x'] = (int) $point['x'];
					$row['y'] = (int) $point['y'];
				}
				else {
					$point = $positions[$key] ?? ['x' => 80, 'y' => 80];
					$row['x'] = (int) $point['x'];
					$row['y'] = (int) $point['y'];
				}

				$added_devices++;
			}

			$selements[$selementid] = $row;
			$node_selementid[$key] = $selementid;
		}

		// 只增不减：本次没扫到的已有元素原样提交，避免 map.update 把它们删掉
		foreach ($old_selements as $se) {
			$selementid = (string) ($se['selementid'] ?? '');

			if ($selementid === '' || isset($matched[$selementid]) || isset($selements[$selementid])) {
				continue;
			}

			$selements[$selementid] = $se;
		}

		// ------------------------------------------------------------
		// 链路：先按端口键精确匹配，再按设备对回退（主机换端口的情况）
		// ------------------------------------------------------------

		$old_link_info = [];
		$old_by_port = [];
		$old_by_pair = [];

		foreach ($old_links as $ol) {
			$linkid = (string) ($ol['linkid'] ?? '');

			if ($linkid === '') {
				continue;
			}

			$p1 = $selement_pkey[(string) ($ol['selementid1'] ?? '')] ?? '';
			$p2 = $selement_pkey[(string) ($ol['selementid2'] ?? '')] ?? '';
			$metrics = $old_metrics[$linkid] ?? [
				'bw' => [], 'in' => [], 'out' => [], 'source_port' => '', 'target_port' => '',
				'trigger_source' => self::TRIGGER_SOURCE_AUTO
			];
			$port1 = (string) $metrics['source_port'];
			$port2 = (string) $metrics['target_port'];
			$auto = ($port1 !== '' || $port2 !== '');

			$old_link_info[$linkid] = [
				'auto' => $auto,
				'row' => $ol,
				'p1' => $p1,
				'p2' => $p2
			];

			if ($p1 === '' || $p2 === '') {
				continue;
			}

			$old_by_port[$this->linkMatchKey($p1, $p2, $port1, $port2)][] = $linkid;

			if ($auto) {
				$old_by_pair[$this->linkMatchKey($p1, $p2, '', '')][] = $linkid;
			}
		}

		$links = [];
		$used = [];
		$reuse_meta = [];
		$new_meta = [];
		$added_links = 0;
		$kept_links = 0;
		$removed_links = 0;
		$trigger_cache = [];
		$trigger_total = 0;
		$directed = [];
		$pairs = [];

		foreach ($payload_links as $link) {
			if (!is_array($link) || !isset($link['source'], $link['target'])) {
				continue;
			}

			$source = (string) $link['source'];
			$target = (string) $link['target'];

			if (!isset($node_selementid[$source], $node_selementid[$target]) || $source === $target) {
				continue;
			}

			// 与自动发现一致：反向重复（B→A 已存在 A→B）整条丢掉
			if (isset($directed[$target.'|'.$source])) {
				continue;
			}

			$source_port = (string) ($link['source_port'] ?? '');
			$target_port = (string) ($link['target_port'] ?? '');
			$p1 = $node_pkey[$source] ?? '';
			$p2 = $node_pkey[$target] ?? '';
			$port_key = $this->linkMatchKey($p1, $p2, $source_port, $target_port);

			if (isset($pairs[$port_key])) {
				continue;
			}

			$pairs[$port_key] = true;
			$directed[$source.'|'.$target] = true;

			$trigger_source = $this->normalizeTriggerSource(
				$link['trigger_source'] ?? self::TRIGGER_SOURCE_AUTO
			);
			$triggerids = $this->matchLinkTriggers(
				$host_of_node[$source] ?? '',
				$host_of_node[$target] ?? '',
				$source_port,
				$target_port,
				$trigger_cache,
				$trigger_source
			);

			$row = [
				'selementid1' => $node_selementid[$source],
				'selementid2' => $node_selementid[$target],
				'color' => self::DISCOVERY_LINK_COLOR,
				'drawtype' => 0,
				// 命中的链路要按新端口重新绑定触发器（端口变了，旧的绑定就不对了）
				'linktriggers' => []
			];

			if ($triggerids) {
				foreach ($triggerids as $triggerid) {
					$row['linktriggers'][] = [
						'triggerid' => $triggerid,
						'drawtype' => 0,
						'color' => self::DISCOVERY_TRIGGER_COLOR
					];
				}

				$trigger_total += count($triggerids);
			}

			$meta = [
				'source_port' => $source_port,
				'target_port' => $target_port,
				'trigger_source' => $trigger_source
			];

			$reuse = '';

			if (isset($old_by_port[$port_key])) {
				foreach ($old_by_port[$port_key] as $candidate) {
					if (!isset($used[$candidate])) {
						$reuse = $candidate;
						break;
					}
				}
			}

			if ($reuse === '') {
				$pair_key = $this->linkMatchKey($p1, $p2, '', '');

				if (isset($old_by_pair[$pair_key])) {
					foreach ($old_by_pair[$pair_key] as $candidate) {
						if (!isset($used[$candidate])) {
							$reuse = $candidate;
							break;
						}
					}
				}
			}

			if ($reuse !== '') {
				// 复用旧 linkid：指标表里的带宽/流量配置原样保留，只更新端口与触发器来源
				$used[$reuse] = true;
				$old_row = $old_link_info[$reuse]['row'];
				$row['linkid'] = $reuse;

				if (!empty($old_row['color'])) {
					$row['color'] = (string) $old_row['color'];
				}

				if (isset($old_row['drawtype']) && $old_row['drawtype'] !== '') {
					$row['drawtype'] = (int) $old_row['drawtype'];
				}

				if (trim((string) ($old_row['label'] ?? '')) !== '') {
					$row['label'] = (string) $old_row['label'];
				}

				$reuse_meta[$reuse] = $meta;
				$kept_links++;
			}
			else {
				$new_meta[] = $meta;
				$added_links++;
			}

			$links[] = $row;
		}

		$kept_ids = [];

		foreach ($links as $row) {
			if (isset($row['linkid'])) {
				$kept_ids[(string) $row['linkid']] = true;
			}
		}

		// 未被本次发现的旧链路：手工线一律保留；自动线按用户选项保留或移除
		foreach ($old_links as $ol) {
			$linkid = (string) ($ol['linkid'] ?? '');

			if ($linkid === '' || isset($used[$linkid])) {
				continue;
			}

			if ($old_link_info[$linkid]['auto'] && $prune_links) {
				$removed_links++;
				continue;
			}

			$links[] = $ol;
			$kept_ids[$linkid] = true;
		}

		// ------------------------------------------------------------
		// 一次性提交合并结果
		// ------------------------------------------------------------

		$width = 80;
		$height = 80;

		foreach ($selements as $se) {
			$width = max($width, (int) ($se['x'] ?? 0) + 220);
			$height = max($height, (int) ($se['y'] ?? 0) + 180);
		}

		$update = [
			'sysmapid' => $sysmapid,
			// 大图（上百台设备的树）会很宽，上限放到 60000，避免画布右侧的节点
			// 被 10000 的旧上限截掉（8.0 API 实测接受 24000 的宽度）
			'width' => max(100, min(60000, max($width, (int) ($map['width'] ?? 0)))),
			'height' => max(100, min(60000, max($height, (int) ($map['height'] ?? 0)))),
			'selements' => $this->sanitizeSelements(array_values($selements)),
			'links' => $this->sanitizeLinks($links, $this->existingLinkIndicatorTypes((string) $sysmapid)),
			// 形状原样带回，避免 map.update 把用户画的框删掉
			'shapes' => $this->sanitizeShapes($old_shapes)
		];

		// 7.0.x 的链接更新校验要求带 sysmapid；7.4/8.0 的字段白名单不接受该字段（见 modeSave 的说明）
		if (self::linkSysmapidRequired()) {
			foreach ($update['links'] as &$link_row) {
				if (isset($link_row['linkid'])) {
					$link_row['sysmapid'] = $sysmapid;
				}
			}
			unset($link_row);
		}

		$message_offset = $this->apiMessageCount();
		$update_result = API::Map()->update([$update]);

		if ($update_result === false) {
			$detail = $this->apiErrorDetail($message_offset);

			if ($detail !== '') {
				throw new \Exception(LanguageManager::tf('重新发现：保存拓扑图失败：%1$s', $detail));
			}

			throw new \Exception(LanguageManager::t('重新发现：保存拓扑图失败'));
		}

		// ------------------------------------------------------------
		// 指标回写：新链路的 linkid 从回读结果里认领，其余沿用旧配置
		// ------------------------------------------------------------

		$maps = API::Map()->get([
			'sysmapids' => [$sysmapid],
			'output' => ['sysmapid'],
			'selectLinks' => 'extend'
		]);

		$db_links = ($maps && isset($maps[0]['links']) && is_array($maps[0]['links']))
			? array_values($maps[0]['links'])
			: [];
		$new_ids = [];

		foreach ($db_links as $db_link) {
			$id = (string) ($db_link['linkid'] ?? '');

			if ($id !== '' && !isset($kept_ids[$id])) {
				$new_ids[] = $id;
			}
		}

		sort($new_ids, SORT_NUMERIC);

		$final = [];

		foreach (array_keys($kept_ids) as $linkid) {
			$linkid = (string) $linkid;
			$metrics = $old_metrics[$linkid] ?? [
				'bw' => [], 'in' => [], 'out' => [], 'source_port' => '', 'target_port' => '',
				'trigger_source' => self::TRIGGER_SOURCE_AUTO
			];

			if (isset($reuse_meta[$linkid])) {
				$metrics['source_port'] = $reuse_meta[$linkid]['source_port'];
				$metrics['target_port'] = $reuse_meta[$linkid]['target_port'];
				$metrics['trigger_source'] = $reuse_meta[$linkid]['trigger_source'];
			}

			$final[$linkid] = $metrics;
		}

		foreach ($new_meta as $index => $meta) {
			if (!isset($new_ids[$index])) {
				break;
			}

			$final[$new_ids[$index]] = [
				'bw' => [],
				'in' => [],
				'out' => [],
				'source_port' => $meta['source_port'],
				'target_port' => $meta['target_port'],
				'trigger_source' => $meta['trigger_source']
			];
		}

		$this->saveLinkMetrics($sysmapid, $final);

		// 走线 / 折点：复用 linkid 的链路原样保留；被移除的链路行随整表重建一起消失，
		// 新链路默认自动曲线（没有行）。
		$final_paths = [];

		foreach (array_keys($kept_ids) as $linkid) {
			if (isset($old_paths[(string) $linkid])) {
				$final_paths[$linkid] = $old_paths[(string) $linkid];
			}
		}

		$this->saveLinkPaths($sysmapid, $final_paths);
		// 布局元数据回写：下一次重新发现直接用同一个根 / 方向
		$this->saveMapLayout((string) $sysmapid, $root_pkey, $direction);

		return [
			'success' => true,
			'sysmapid' => $sysmapid,
			'added_devices' => $added_devices,
			'kept_devices' => count($matched),
			'added_links' => $added_links,
			'kept_links' => $kept_links,
			'removed_links' => $removed_links,
			'triggers' => $trigger_total
		];
	}

	/**
	 * 主接口 IP（没标 main 就用第一个接口）。选择主机后自动写标签要用它。
	 */
	private function hostIp(array $host): string {
		if (empty($host['interfaces'])) {
			return '';
		}

		foreach ($host['interfaces'] as $iface) {
			if (isset($iface['main']) && (int) $iface['main'] === 1) {
				return (string) $iface['ip'];
			}
		}

		return (string) $host['interfaces'][0]['ip'];
	}

	/**
	 * {HOST.CONN} 的连接地址：优先主接口，其次 SNMP 接口，再退回到第一个可见接口。
	 * 接口 useip=1 取 IP，否则优先 DNS；任一为空时回退到另一个。
	 * 每次 modeGet 都会重新 host.get，所以主机改名/重新发现后取到的都是最新值。
	 */
	private function hostConn(array $host): string {
		if (empty($host['interfaces'])) {
			return '';
		}

		$chosen = null;

		foreach ($host['interfaces'] as $iface) {
			if (isset($iface['main']) && (int) $iface['main'] === 1) {
				$chosen = $iface;
				break;
			}
		}

		if ($chosen === null) {
			$snmp_type = defined('INTERFACE_TYPE_SNMP') ? (int) INTERFACE_TYPE_SNMP : 2;

			foreach ($host['interfaces'] as $iface) {
				if (isset($iface['type']) && (int) $iface['type'] === $snmp_type) {
					$chosen = $iface;
					break;
				}
			}
		}

		if ($chosen === null) {
			$chosen = $host['interfaces'][0];
		}

		$ip = trim((string) ($chosen['ip'] ?? ''));
		$dns = trim((string) ($chosen['dns'] ?? ''));
		$useip = isset($chosen['useip']) ? (int) $chosen['useip'] : 1;

		if ($useip === 1) {
			return $ip !== '' ? $ip : $dns;
		}

		return $dns !== '' ? $dns : $ip;
	}

	// ------------------------------------------------------------------
	// 自动发现的辅助方法
	// ------------------------------------------------------------------

	/**
	 * 逗号分隔的 ID 列表 → 去重后的纯数字数组。
	 */
	private function parseIdList(string $raw): array {
		$ids = [];

		foreach (explode(',', $raw) as $id) {
			$id = trim($id);

			if ($id !== '' && ctype_digit($id) && $id !== '0') {
				$ids[$id] = $id;
			}
		}

		return array_values($ids);
	}

	/**
	 * protocols=LLDP,CDP → ['LLDP', 'CDP']；非法值忽略，默认 LLDP。
	 */
	private function parseProtocols(string $raw): array {
		$allow = ['LLDP', 'CDP', 'EDP'];
		$result = [];

		foreach (explode(',', strtoupper($raw)) as $item) {
			$item = trim($item);

			if (in_array($item, $allow, true)) {
				$result[$item] = $item;
			}
		}

		return $result ? array_values($result) : ['LLDP'];
	}

	// ------------------------------------------------------------------
	// 自动发现：链路 ↔ 端口状态触发器的关联
	// ------------------------------------------------------------------

	/**
	 * 为一条自动发现的链路匹配「端口状态类」触发器（源端优先，源端未命中才回退目标端）。
	 *
	 * 源端优先的理由和 aiops 一样：命中即只用源端，避免两端都绑导致条数翻倍；
	 * 触发器只配置在对面设备上时，再回退到目标端。
	 *
	 * @param array $cache 按 hostid 缓存的触发器列表（引用传入，跨链路复用）
	 *
	 * @param int   $trigger_source 该链路的「触发器来源端」口径（见 TRIGGER_SOURCE_* 常量）
	 *
	 * @return string[] 命中的 triggerid
	 */
	private function matchLinkTriggers(string $src_hostid, string $tgt_hostid, string $src_port, string $tgt_port,
			array &$cache, int $trigger_source = self::TRIGGER_SOURCE_AUTO): array {
		if ($trigger_source === self::TRIGGER_SOURCE_NONE) {
			return [];
		}

		$src_port_key = $this->ifPortKey($src_port);
		$tgt_port_key = $this->ifPortKey($tgt_port);

		if ($trigger_source === self::TRIGGER_SOURCE_SOURCE) {
			return $this->matchPortTriggers($src_hostid, $src_port_key, $cache);
		}

		if ($trigger_source === self::TRIGGER_SOURCE_TARGET) {
			return $this->matchPortTriggers($tgt_hostid, $tgt_port_key, $cache);
		}

		$src = $this->matchPortTriggers($src_hostid, $src_port_key, $cache);

		if ($trigger_source === self::TRIGGER_SOURCE_BOTH) {
			$both = $src;

			foreach ($this->matchPortTriggers($tgt_hostid, $tgt_port_key, $cache) as $triggerid) {
				$both[$triggerid] = $triggerid;
			}

			return array_values($both);
		}

		// AUTO：源端优先，源端没有才回退本端（历史行为）
		if ($src) {
			return $src;
		}

		return $this->matchPortTriggers($tgt_hostid, $tgt_port_key, $cache);
	}

	/**
	 * 取某主机上「描述里命中关键词、且包含该端口名」的触发器。
	 *
	 * @param string $port 已归一化的端口名（如 ge006）
	 *
	 * @return string[]
	 */
	private function matchPortTriggers(string $hostid, string $port, array &$cache): array {
		if ($hostid === '' || $port === '') {
			return [];
		}

		if (!isset($cache[$hostid])) {
			$list = [];

			try {
				$rows = API::Trigger()->get([
					'hostids' => [$hostid],
					'output' => ['triggerid', 'description'],
					'monitored' => true,
					'skipDependent' => true
				]);

				foreach ($rows as $row) {
					$list[(string) $row['triggerid']] = (string) $row['description'];
				}
			}
			catch (\Throwable $e) {
				// 取不到就当这台主机没有可用触发器
			}

			$cache[$hostid] = $list;
		}

		// 字母数字边界匹配：ge006 不得命中 ge0060 / ge0061…，否则 6 号口会绑上 60+ 号口的触发器
		$pattern = '/(?<![a-z0-9])'.preg_quote($port, '/').'(?![a-z0-9])/';
		$out = [];

		foreach ($cache[$hostid] as $triggerid => $description) {
			$lower = mb_strtolower($description);

			if (!$this->hasLinkTriggerKeyword($lower)) {
				continue;
			}

			if (preg_match($pattern, $this->ifPortKey($description))) {
				$out[] = $triggerid;
			}
		}

		return $out;
	}

	private function hasLinkTriggerKeyword(string $lower_description): bool {
		foreach ($this->loadTriggerKeywords() as $keyword) {
			if (mb_strpos($lower_description, $keyword) !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * 端口名归一化键：去掉发现时附加的 " (描述)" 后缀，全称缩写 + 去分隔符，
	 * 用于跨格式匹配（Gi0/0/6 ↔ ge0_0_6 ↔ GigabitEthernet0/0/6）。
	 * 与 aiops 的 ifPortKey 保持一致。
	 */
	private function ifPortKey(string $s): string {
		$s = preg_split('/\s*\(/', $s)[0] ?? '';
		$s = mb_strtolower($s);
		$s = (string) preg_replace('/ten[-_ ]?gigabitethernet/', 'te', $s);
		$s = (string) preg_replace('/gigabitethernet/', 'ge', $s);
		$s = (string) preg_replace('/fastethernet/', 'fa', $s);
		$s = (string) preg_replace('/gi(?=[0-9\/])/', 'ge', $s);

		return (string) preg_replace('/[\s_\-:.\/]/', '', $s);
	}

	/**
	 * 拓扑元素匹配键：已纳管主机用 h:<hostid>，未纳管图片元素用 i:<标签首行小写>。
	 */
	private function elementPKey(array $selement): string {
		$type = (int) ($selement['elementtype'] ?? -1);

		if ($type === 0 && !empty($selement['elements'][0]['hostid'])) {
			return 'h:'.(string) $selement['elements'][0]['hostid'];
		}

		if ($type === 4) {
			$lines = preg_split('/\r?\n/', (string) ($selement['label'] ?? ''));
			$name = mb_strtolower(trim((string) ($lines[0] ?? '')));

			return $name === '' ? '' : 'i:'.$name;
		}

		return '';
	}

	/**
	 * 重新发现 payload 里节点的匹配键：与 elementPKey() 同口径。
	 */
	private function nodePKey(array $node): string {
		$hostid = trim((string) ($node['hostid'] ?? ''));

		if ($hostid !== '' && ctype_digit($hostid) && $hostid !== '0') {
			return 'h:'.$hostid;
		}

		$name = mb_strtolower(trim((string) ($node['name'] ?? '')));

		return $name === '' ? '' : 'i:'.$name;
	}

	/**
	 * 链路匹配键：两端 (元素键 + 归一化端口) 排序后拼接；
	 * 端口为空时自动退化成本设备对的键。
	 */
	private function linkMatchKey(string $p1, string $p2, string $port1, string $port2): string {
		$a = $p1.'#'.$this->ifPortKey($port1);
		$b = $p2.'#'.$this->ifPortKey($port2);

		return $a <= $b ? $a.'|'.$b : $b.'|'.$a;
	}

	/**
	 * 主机的主 SNMP 接口（type=2）；没有就返回 null。
	 */
	private function snmpInterface(array $host): ?array {
		$fallback = null;

		foreach ($host['interfaces'] ?? [] as $iface) {
			if ((int) $iface['type'] !== 2) {
				continue;
			}

			if (isset($iface['main']) && (int) $iface['main'] === 1) {
				return $iface;
			}

			if ($fallback === null) {
				$fallback = $iface;
			}
		}

		return $fallback;
	}

	/**
	 * 主机宏 + 全局宏（主机宏优先）。用来解析 SNMP 接口里填的 {$SNMP_COMMUNITY}。
	 */
	private function hostMacros(array $hostids): array {
		$global = [];
		$per_host = [];

		try {
			$globals = API::UserMacro()->get([
				'output' => ['macro', 'value'],
				'globalmacro' => true
			]);

			foreach ($globals as $macro) {
				$global[$macro['macro']] = (string) $macro['value'];
			}
		}
		catch (\Throwable $e) {
			// 取不到全局宏就只靠主机宏
		}

		try {
			$hosts = API::UserMacro()->get([
				'output' => ['macro', 'value'],
				'hostids' => $hostids
			]);

			foreach ($hosts as $macro) {
				$hostid = isset($macro['hostid']) ? (string) $macro['hostid'] : '';

				if ($hostid === '') {
					continue;
				}

				$per_host[$hostid][$macro['macro']] = (string) $macro['value'];
			}
		}
		catch (\Throwable $e) {
			// 同上
		}

		// 每台主机都要拿到一份完整的宏表：全局宏垫底，主机宏覆盖。
		// 只把全局宏挂在 $result[0] 上是错的 —— 主机没有自己的宏时取不到值，
		// {$SNMP_COMMUNITY} 这种「只定义在全局」的宏会解析失败。
		$result = [];

		foreach ($hostids as $hostid) {
			$hostid = (string) $hostid;
			$result[$hostid] = array_merge($global, $per_host[$hostid] ?? []);
		}

		return $result;
	}

	private function resolveMacros(string $text, array $macros): string {
		if ($text === '' || strpos($text, '{$') === false) {
			return $text;
		}

		return (string) preg_replace_callback('/\{\$[A-Za-z0-9_.\-]+\}/', function (array $match) use ($macros) {
			return array_key_exists($match[0], $macros) ? $macros[$match[0]] : $match[0];
		}, $text);
	}

	/**
	 * 全量主机索引：邻居设备名 / 管理 IP 都要能对回 Zabbix 主机。
	 * 一台主机可能有多个接口 IP，全部进索引。
	 *
	 * entry 里带上主机自己的管理 IP：邻居匹配上主机后，节点要显示「主机在 Zabbix 里的地址」，
	 * 而不是 LLDP 邻居上报的地址 —— 后者经常不准（H3C 会把本机管理地址回声给所有邻居口，
	 * 表现就是一堆设备都显示成同一个 IP）。
	 */
	private function discoveryIndex(): array {
		$index = ['by_name' => [], 'by_ip' => []];

		$hosts = API::Host()->get([
			'output' => ['hostid', 'name', 'host'],
			'selectInterfaces' => ['ip', 'main'],
			'limit' => 5000
		]);

		foreach ($hosts as $host) {
			$entry = ['hostid' => (string) $host['hostid'], 'name' => $host['name'], 'ip' => ''];

			// 展示用地址：主接口优先，其次任意一个可用接口
			$interfaces = $host['interfaces'] ?? [];
			$fallback = '';

			foreach ($interfaces as $iface) {
				$ip = trim((string) ($iface['ip'] ?? ''));

				if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
					continue;
				}

				if ($fallback === '') {
					$fallback = $ip;
				}

				if ((int) ($iface['main'] ?? 0) === 1) {
					$entry['ip'] = $ip;

					break;
				}
			}

			if ($entry['ip'] === '') {
				$entry['ip'] = $fallback;
			}

			foreach ([$host['name'], $host['host']] as $candidate) {
				$key = mb_strtolower(trim((string) $candidate));

				if ($key !== '' && !isset($index['by_name'][$key])) {
					$index['by_name'][$key] = $entry;
				}
			}

			foreach ($interfaces as $iface) {
				$ip = trim((string) ($iface['ip'] ?? ''));

				if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) && !isset($index['by_ip'][$ip])) {
					$index['by_ip'][$ip] = $entry;
				}
			}
		}

		return $index;
	}

	/**
	 * 邻居 → Zabbix 主机。先按设备名（sysName 通常等于 host name 或 host），
	 * 再按管理 IP。都对不上就是未纳管设备，交给图片元素显示。
	 *
	 * $self_ips 是当前这台交换机的接口 IP：有些设备（华为）把「自己的管理地址」
	 * 填进所有端口的 lldpRemManAddr，不排除掉就会匹配到自己。
	 */
	private function matchNeighbor(array $neighbor, array $index, array $self_ips = []): array {
		$name = trim((string) ($neighbor['sysname'] ?? ''));

		if ($name !== '') {
			$key = mb_strtolower($name);

			if (isset($index['by_name'][$key])) {
				return $index['by_name'][$key] + ['how' => 'name'];
			}

			// LLDP sysName 常带域名后缀（sw01.example.com），去掉再试一次
			$short = preg_replace('/\..*$/', '', $name);

			if ($short !== '' && $short !== $name && isset($index['by_name'][mb_strtolower($short)])) {
				return $index['by_name'][mb_strtolower($short)] + ['how' => 'name'];
			}
		}

		$ip = trim((string) ($neighbor['ip'] ?? ''));

		if ($ip !== '' && !in_array($ip, $self_ips, true) && isset($index['by_ip'][$ip])) {
			return $index['by_ip'][$ip] + ['how' => 'ip'];
		}

		return ['hostid' => '', 'name' => '', 'ip' => '', 'how' => ''];
	}

	/**
	 * 从 sysDescr / 设备名猜设备类型，前端据此挑图标。
	 * 猜不准就按交换机处理 —— 自动发现本来就是从交换机出发的。
	 */
	private function guessKind(string $sysdesc, string $sysname): string {
		$text = mb_strtolower($sysdesc.' '.$sysname);

		$rules = [
			'firewall' => ['firewall', 'usg', 'asa ', 'pix'],
			'router' => ['router', 'routeros', 'vrp', 'ios xe'],
			'camera' => ['camera', 'ipc', 'nvr', 'dvr', 'hikvision', '海康', '摄像'],
			'printer' => ['printer', 'laserjet', '打印'],
			'server' => ['server', 'vmware', 'esxi', 'linux', 'windows'],
			'ap' => ['access point', 'wireless', 'wlan', 'ap-', 'fit ap']
		];

		foreach ($rules as $kind => $words) {
			foreach ($words as $word) {
				if (strpos($text, $word) !== false) {
					return $kind;
				}
			}
		}

		return 'switch';
	}

	/**
	 * 拓扑图名称唯一：重名时自动加「 (2)」「 (3)」，
	 * 而不是像创建按钮那样直接报错 —— 自动发现重复点几次很常见。
	 */
	private function uniqueMapName(string $name): string {
		$candidate = $name;
		$seq = 2;

		while (true) {
			$exists = API::Map()->get([
				'output' => ['sysmapid'],
				'filter' => ['name' => $candidate]
			]);

			if (!$exists) {
				return $candidate;
			}

			$suffix = ' ('.$seq.')';
			$base = mb_substr($name, 0, max(1, 128 - mb_strlen($suffix)));
			$candidate = $base.$suffix;
			$seq++;

			if ($seq > 200) {
				return $base.' ('.date('His').')';
			}
		}
	}

	/**
	 * 自动发现新建的元素图标：优先 Server_(96)，没有就用拓扑图图标库里的第一个。
	 */
	private function defaultIconId(): string {
		$images = API::Image()->get([
			'output' => ['imageid', 'name'],
			'filter' => ['imagetype' => 1],
			'sortfield' => 'name',
			'sortorder' => 'ASC'
		]);

		if (!$images) {
			return '0';
		}

		foreach (['Server_(96)', 'Server_(64)', 'Switch_1', 'Cloud_(96)'] as $want) {
			foreach ($images as $image) {
				if (strcasecmp($image['name'], $want) === 0) {
					return (string) $image['imageid'];
				}
			}
		}

		return (string) $images[0]['imageid'];
	}

	/** 图标名（小写）=> imageid，一次请求内复用 */
	private $icon_ids = null;

	/**
	 * 按设备类型挑图标，对齐 aiops：
	 * 交换机 Switch_(128)、终端 PC Workstation_(128)、服务器 Server_(96)。
	 */
	private function iconForKind(string $kind): string {
		if ($this->icon_ids === null) {
			$this->icon_ids = [];

			$images = API::Image()->get([
				'output' => ['imageid', 'name'],
				'filter' => ['imagetype' => 1]
			]);

			foreach ($images as $image) {
				$this->icon_ids[strtolower((string) $image['name'])] = (string) $image['imageid'];
			}
		}

		$by_kind = [
			'switch' => ['Switch_(128)', 'Switch_(96)', 'Switch_(64)'],
			'router' => ['Router_(128)', 'Router_(96)'],
			'firewall' => ['Firewall_(128)', 'Firewall_(96)'],
			'server' => ['Server_(96)', 'Server_(64)'],
			'pc' => ['Workstation_(128)', 'Workstation_(96)'],
			'ap' => ['Workstation_(128)', 'Workstation_(96)'],
			'printer' => ['Printer_(128)', 'Printer_(96)'],
			'camera' => ['Video_terminal_(128)', 'Video_terminal_(96)']
		];

		foreach ($by_kind[$kind] ?? [] as $want) {
			$key = strtolower($want);

			if (isset($this->icon_ids[$key])) {
				return $this->icon_ids[$key];
			}
		}

		return $this->defaultIconId();
	}

	// ------------------------------------------------------------------
	// 入参校验 / 字段白名单
	// ------------------------------------------------------------------

	private function requireSysmapid(): string {
		$sysmapid = trim((string) $this->getInput('sysmapid', ''));

		if ($sysmapid === '' || !ctype_digit($sysmapid) || $sysmapid === '0') {
			throw new \Exception(LanguageManager::t('缺少有效的拓扑图 ID'));
		}

		return $sysmapid;
	}

	private function requireName(): string {
		return $this->normalizeName((string) $this->getInput('name', ''));
	}

	/**
	 * 画布宽 / 高：与原生拓扑图共用 sysmaps.width / height，元素拖拽的边界就是它。
	 * 不传返回 null（表示保持原值），避免把已有尺寸顺手改掉；传了就夹到 100..10000。
	 */
	private function dimension(string $key): ?int {
		$raw = trim((string) $this->getInput($key, ''));

		if ($raw === '') {
			return null;
		}

		return max(100, min(10000, (int) $raw));
	}

	private function normalizeName(string $name): string {
		$name = trim($name);

		if ($name === '') {
			throw new \Exception(LanguageManager::t('拓扑图名称不能为空'));
		}

		// sysmaps.name 的字段长度是 128
		if (mb_strlen($name) > 128) {
			throw new \Exception(LanguageManager::t('拓扑图名称不能超过 128 个字符'));
		}

		return $name;
	}

	/**
	 * 7.0.x 的链路更新校验要求每条带 linkid 的 link 补 sysmapid；
	 * 7.4/8.0 的 CMap::validateLinkFields() 是严格字段白名单，不接受 sysmapid。
	 */
	private static function linkSysmapidRequired(): bool {
		return !self::supportsLinkIndicatorType();
	}

	/**
	 * Zabbix 8.0 起 map link 增加了 indicator_type（0=静态、1=触发器、2=监控项值），
	 * 且只有 indicator_type=1 时才允许携带 linktriggers。7.x 没有该字段，写了反而会
	 * 被当成未知参数，所以这里按常量是否存在做特性探测。
	 */
	private static function supportsLinkIndicatorType(): bool {
		return defined('MAP_INDICATOR_TYPE_TRIGGER') && defined('MAP_INDICATOR_TYPE_STATIC_LINK');
	}

	/**
	 * 返回当前版本应写入的 indicator_type；7.x 返回 null 表示不写。
	 */
	private static function linkIndicatorType(bool $has_triggers): ?int {
		if (!self::supportsLinkIndicatorType()) {
			return null;
		}

		return $has_triggers
			? (int) constant('MAP_INDICATOR_TYPE_TRIGGER')
			: (int) constant('MAP_INDICATOR_TYPE_STATIC_LINK');
	}

	/**
	 * 当前已登记的消息条数。Zabbix 前端的 API wrapper 出错时只返回 false，
	 * 真正的错误被记进 CMessageHelper，这里用它把错误捞出来透传。
	 */
	private function apiMessageCount(): int {
		if (!class_exists('\CMessageHelper') || !method_exists('\CMessageHelper', 'getMessages')) {
			return 0;
		}

		return count(\CMessageHelper::getMessages());
	}

	/**
	 * 取 offset 之后新登记的 API 错误文本（多条用分号拼接）。
	 */
	private function apiErrorDetail(int $offset): string {
		if (!class_exists('\CMessageHelper') || !method_exists('\CMessageHelper', 'getMessages')) {
			return '';
		}

		$messages = array_slice(\CMessageHelper::getMessages(), $offset);
		$errors = [];

		foreach ($messages as $message) {
			if (($message['type'] ?? '') === 'error' && !empty($message['message'])) {
				$errors[] = trim((string) $message['message']);
			}
		}

		return implode('; ', array_unique($errors));
	}

	private function sanitizeSelements(array $items): array {
		$result = [];
		$seen_tags = [];

		foreach ($items as $item) {
			if (!is_array($item) || !isset($item['elementtype'])) {
				continue;
			}

			$row = [];

			foreach (self::SELEMENT_FIELDS as $field) {
				if (array_key_exists($field, $item)) {
					$row[$field] = $item[$field];
				}
			}

			$row['elementtype'] = (int) $row['elementtype'];

			if (!array_key_exists('iconid_off', $row)) {
				$row['iconid_off'] = 0;
			}

			foreach (['x', 'y', 'label_location', 'iconid_off', 'iconid_on', 'iconid_disabled',
					'iconid_maintenance', 'use_iconmap', 'evaltype'] as $field) {
				if (array_key_exists($field, $row) && $row[$field] !== '' && $row[$field] !== null) {
					$row[$field] = (int) $row[$field];
				}
			}

			if ($row['elementtype'] === 4) {
				// 图片元素不允许带 elements / 标签 / 自动图标
				unset($row['elements'], $row['tags'], $row['evaltype'], $row['use_iconmap']);
			}
			else {
				$elements = [];

				// 原生拓扑图里还会有触发器 / 主机组 / 嵌套图元素。本模块不编辑它们，
				// 但保存时必须原样带回去（缺 elements 会被 API 拒收，丢字段会删掉这些元素）。
				if (isset($row['elements']) && is_array($row['elements'])) {
					foreach ($row['elements'] as $element) {
						if (!is_array($element)) {
							continue;
						}

						if (!empty($element['hostid'])) {
							$elements[] = ['hostid' => (string) $element['hostid']];
						}
						elseif (!empty($element['sysmapid'])) {
							$elements[] = ['sysmapid' => (string) $element['sysmapid']];
						}
						elseif (!empty($element['groupid'])) {
							$elements[] = ['groupid' => (string) $element['groupid']];
						}
						elseif (!empty($element['triggerid'])) {
							$trigger = ['triggerid' => (string) $element['triggerid']];

							if (!empty($element['selement_triggerid'])) {
								$trigger['selement_triggerid'] = (string) $element['selement_triggerid'];
							}

							$elements[] = $trigger;
						}
					}
				}

				// 没挂对象的元素 API 会拒收，直接跳过，避免整张图保存失败
				if (!$elements) {
					continue;
				}

				$row['elements'] = $elements;
			}

			if (isset($row['tags']) && is_array($row['tags'])) {
				$tags = [];
				$key = isset($row['selementid']) ? (string) $row['selementid'] : ('n'.count($result));

				foreach ($row['tags'] as $tag) {
					if (!is_array($tag) || !isset($tag['tag']) || trim((string) $tag['tag']) === '') {
						continue;
					}

					$tag_name = trim((string) $tag['tag']);
					$tag_value = isset($tag['value']) ? (string) $tag['value'] : '';
					$uniq = $key.'|'.$tag_name.'|'.$tag_value;

					if (isset($seen_tags[$uniq])) {
						continue;
					}

					$seen_tags[$uniq] = true;

					$tags[] = [
						'tag' => $tag_name,
						'value' => $tag_value,
						'operator' => isset($tag['operator']) ? (int) $tag['operator'] : 0
					];
				}

				$row['tags'] = $tags;
			}

			if (isset($row['urls']) && is_array($row['urls'])) {
				$urls = [];

				foreach ($row['urls'] as $url) {
					if (is_array($url) && isset($url['name'], $url['url']) && (string) $url['url'] !== '') {
						$urls[] = ['name' => (string) $url['name'], 'url' => (string) $url['url']];
					}
				}

				$row['urls'] = $urls;
			}

			if (isset($row['label']) && mb_strlen((string) $row['label']) > 2048) {
				$row['label'] = mb_substr((string) $row['label'], 0, 2048);
			}

			$result[] = $row;
		}

		return $result;
	}

	/**
	 * 读取当前图上已有连线的 indicator_type（linkid => int）。7.x 没有该字段时返回空数组。
	 */
	private function existingLinkIndicatorTypes(string $sysmapid): array {
		if (!self::supportsLinkIndicatorType()) {
			return [];
		}

		$maps = API::Map()->get([
			'sysmapids' => $sysmapid,
			'output' => ['sysmapid'],
			'selectLinks' => ['linkid', 'indicator_type']
		]);

		if (!is_array($maps) || empty($maps[0]['links'])) {
			return [];
		}

		$result = [];

		foreach ($maps[0]['links'] as $link) {
			if (!empty($link['linkid'])) {
				$result[(string) $link['linkid']] = (int) ($link['indicator_type'] ?? 0);
			}
		}

		return $result;
	}

	/**
	 * @param array $items                   前端提交的连线
	 * @param array $existing_indicator_types 8.0 下已有连线的 indicator_type（linkid => int），
	 *                                        用于在清空触发器时只把「触发器型」链路切回静态，
	 *                                        不误伤用户在原生命中设置的「监控项值」链路。
	 */
	private function sanitizeLinks(array $items, array $existing_indicator_types = []): array {
		$result = [];

		foreach ($items as $item) {
			if (!is_array($item) || !isset($item['selementid1'], $item['selementid2'])) {
				continue;
			}

			$s1 = (string) $item['selementid1'];
			$s2 = (string) $item['selementid2'];

			if ($s1 === '' || $s2 === '' || $s1 === $s2) {
				continue;
			}

			$row = [];

			foreach (self::LINK_FIELDS as $field) {
				if (array_key_exists($field, $item) && $item[$field] !== '' && $item[$field] !== null) {
					$row[$field] = $item[$field];
				}
			}

			$row['selementid1'] = $s1;
			$row['selementid2'] = $s2;
			$row['label'] = isset($row['label']) ? (string) $row['label'] : '';

			if (isset($row['color'])) {
				$color = strtoupper(ltrim((string) $row['color'], '#'));

				if (!preg_match('/^[0-9A-F]{6}$/', $color)) {
					$color = '000000';
				}

				$row['color'] = $color;
			}

			if (isset($row['drawtype'])) {
				$drawtype = (int) $row['drawtype'];

				if (!in_array($drawtype, [0, 2, 3, 4], true)) {
					$drawtype = 0;
				}

				$row['drawtype'] = $drawtype;
			}

			// 连线绑定的触发器（链路指示器）：自动发现建的线会带，保存时必须原样回写，
			// 否则 map.update 重建 link 时会把它们丢掉（正常保存也会把绿线恢复成默认灰）。
			if (isset($item['linktriggers']) && is_array($item['linktriggers'])) {
				$linktriggers = [];

				foreach ($item['linktriggers'] as $lt) {
					if (!is_array($lt) || empty($lt['triggerid']) || !ctype_digit((string) $lt['triggerid'])) {
						continue;
					}

					$lt_color = strtoupper(ltrim((string) ($lt['color'] ?? self::DISCOVERY_TRIGGER_COLOR), '#'));

					if (!preg_match('/^[0-9A-F]{6}$/', $lt_color)) {
						$lt_color = self::DISCOVERY_TRIGGER_COLOR;
					}

					$linktriggers[] = [
						'triggerid' => (string) $lt['triggerid'],
						'drawtype' => 0,
						'color' => $lt_color
					];
				}

				// 空数组也要写：用户点「清空」时，只有把 linktriggers 明确传成 [] 才能让
				// CMap::update 把库里已有的绑定删掉。
				$row['linktriggers'] = $linktriggers;

				// 8.0 的校验是「indicator_type=1 才允许带 linktriggers」，反过来
				// 「indicator_type=1 时 linktriggers 不能为空」。有触发器就必须写 1；
				// 清空触发器时只把原本的「触发器型」链路切回静态，避免误伤监控项值链路。
				if (self::linkIndicatorType(true) !== null) {
					if ($linktriggers !== []) {
						$row['indicator_type'] = (int) constant('MAP_INDICATOR_TYPE_TRIGGER');
					}
					else {
						$linkid = (string) ($item['linkid'] ?? '');
						$current_type = $existing_indicator_types[$linkid] ?? null;

						if ($current_type === null || (int) $current_type === (int) constant('MAP_INDICATOR_TYPE_TRIGGER')) {
							$row['indicator_type'] = (int) constant('MAP_INDICATOR_TYPE_STATIC_LINK');
						}
					}
				}
			}

			$result[] = $row;
		}

		return $result;
	}

	private function sanitizeShapes(array $items): array {
		$result = [];

		foreach ($items as $item) {
			if (!is_array($item)) {
				continue;
			}

			$row = [];

			foreach (self::SHAPE_FIELDS as $field) {
				if (array_key_exists($field, $item) && $item[$field] !== '' && $item[$field] !== null) {
					$row[$field] = $item[$field];
				}
			}

			foreach (['sysmap_shapeid', 'type', 'x', 'y', 'width', 'height', 'font', 'font_size',
					'text_halign', 'text_valign', 'border_type', 'border_width', 'zindex'] as $field) {
				if (array_key_exists($field, $row)) {
					$row[$field] = (int) $row[$field];
				}
			}

			foreach (['font_color', 'border_color', 'background_color'] as $field) {
				if (array_key_exists($field, $row)) {
					$color = strtoupper(ltrim((string) $row[$field], '#'));

					if ($color === '') {
						unset($row[$field]);
					}
					elseif (preg_match('/^[0-9A-F]{6}$/', $color)) {
						$row[$field] = $color;
					}
					else {
						unset($row[$field]);
					}
				}
			}

			$row['type'] = isset($row['type']) ? (int) $row['type'] : 0;

			if (!in_array($row['type'], [0, 1, 2], true)) {
				$row['type'] = 0;
			}

			// 线的 x,y 是起点、width,height 是终点绝对坐标（与原生 sysmap_shape 一致），
			// 允许为 0；矩形/椭圆是最小 1 的宽高。
			if ($row['type'] === 2) {
				$row['width'] = isset($row['width']) && $row['width'] >= 0 ? (int) $row['width'] : 0;
				$row['height'] = isset($row['height']) && $row['height'] >= 0 ? (int) $row['height'] : 0;
			}
			else {
				$row['width'] = isset($row['width']) && $row['width'] > 0 ? (int) $row['width'] : 200;
				$row['height'] = isset($row['height']) && $row['height'] > 0 ? (int) $row['height'] : 100;
			}

			// sysmap_shape.text 是短文本列，超长会被 DB 层直接拒收
			$row['text'] = isset($row['text']) ? mb_substr((string) $row['text'], 0, 255) : '';

			$result[] = $row;
		}

		return $result;
	}
}
