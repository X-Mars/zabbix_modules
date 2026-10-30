/*
 * 自动拓扑图（Zabbix 前端模块 topomap）
 *
 * 页面骨架在 views/topomap.view.php，数据接口在 actions/TopomapData.php。
 * 数据本身存于 Zabbix 原生 sysmaps（map.create / map.update），
 * 所以这里读写的是「监测 → 拓扑图」同一份数据。
 *
 * 注意：Zabbix 把模块 JS 以无 defer 的 <script> 注入 <head>，执行时 <body> 还没解析，
 * 因此所有初始化必须延后到 DOMContentLoaded，否则 getElementById 恒为 null。
 */
(function () {
	'use strict';

	// ---------------------------------------------------------------
	// 中英文支持：词典由 PHP 侧 LanguageManager::jsMap() 注入
	// （window.TOPOMAP_I18N）。中文环境词典为空，T() 直接返回原文；
	// 英文环境查不到译文时同样回退原文，保证任何情况下都有文案。
	// ---------------------------------------------------------------

	// 注意：词典是页面骨架里的内联脚本注入的，本文件在 <head> 里先执行，
	// 所以这里只能每次现读 window.TOPOMAP_I18N，不能在加载时缓存。
	function T(s) {
		var dict = window.TOPOMAP_I18N;

		if (!dict || window.TOPOMAP_LANG === 'zh_CN') {
			return s;
		}

		return Object.prototype.hasOwnProperty.call(dict, s) ? dict[s] : s;
	}

	// 带占位符的翻译：TF('共 %s 个拓扑图', n)。%s / %d 依次替换。
	function TF(s) {
		var args = Array.prototype.slice.call(arguments, 1);
		var i = 0;

		return T(s).replace(/%s|%d/g, function () {
			return i < args.length ? args[i++] : '';
		});
	}

	function start() {
		var root = document.getElementById('topomap-root');

		if (!root) {
			return;
		}

		// ---------------------------------------------------------------
		// 常量 / 基础
		// ---------------------------------------------------------------

		var ET_HOST = 0;
		var ET_MAP = 1;
		var ET_TRIGGER = 2;
		var ET_GROUP = 3;
		var ET_IMAGE = 4;

		// sysmaps.label_type 取值（与 7.0.28 ui/js/class.cmap.js CMap.LABEL_TYPE_* 一致）
		var LT_LABEL = 0;    // 元素自己的标签文本
		var LT_IP = 1;       // 主机 IP
		var LT_NAME = 2;     // 元素名称（主机名）
		var LT_STATUS = 3;   // 只显示状态
		var LT_NOTHING = 4;  // 不显示标签
		var LT_CUSTOM = 5;   // 用 sysmaps.label_string_* 宏模板（预览里按标签文本回退）

		// 状态行颜色兜底（后端会带 sysmap 里配置的真实颜色）
		var STATUS_COLORS = {
			ok: '009900',
			ok_ack: '009900',
			problem: 'E45959',
			problem_ack: 'E97659',
			disabled: '960000',
			maintenance: 'EE9600'
		};

		var LABEL_LOCS = [[-1, T('默认')], [0, T('底部')], [1, T('左侧')], [2, T('右侧')], [3, T('顶部')]];
		var ICON_LOCS = [[0, T('默认')], [1, T('底部')], [2, T('左侧')], [3, T('右侧')], [4, T('顶部')]];
		var TAG_OPERATORS = [[0, T('包含')], [1, T('等于')], [2, T('不包含')], [3, T('不等于')], [4, T('存在')], [5, T('不存在')]];
		var LINK_DRAWTYPES = [[0, T('实线')], [2, T('粗线')], [3, T('点线')], [4, T('虚线')]];
		// 每链路的走线模式：0 自动曲线（现状）/ 1 自动直角 / 2 手动折点（在画布上点选、拖动）
		var LINK_ROUTINGS = [[0, T('自动曲线')], [1, T('自动直角')], [2, T('手动折点')]];
		// 每链路的「触发器来源端」口径，与后端 TopomapData::TRIGGER_SOURCE_* 一一对应。
		// 连线方向 = selementid1（源端）→ selementid2（本端）。
		var TRIGGER_SOURCES = [
			[0, T('自动（源端优先）')],
			[1, T('仅源端')],
			[2, T('仅本端')],
			[3, T('两端都关联')],
			[4, T('不关联')]
		];
		// 形类型与原生 SVGMapShape 一致：0 矩形 / 1 椭圆 / 2 线。
		// 线的 x、y 是起点，width、height 是终点的绝对坐标（不是宽高），与原生 sysmap_shape 语义相同。
		var SHAPE_TYPES = [[0, T('矩形')], [1, T('椭圆')], [2, T('线')]];
		var SHAPE_BORDERS = [[0, T('无')], [1, T('实线')], [2, T('点线')], [3, T('虚线')]];
		// 字体：下标即 sysmap_shape.font，名称与原生 shapeForm 的下拉一致；
		// 具体画出来的族名取原生 SVGMap.FONTS（原生里 9 显示 Helvetica，SVG 却画 Trebuchet MS，
		// 这里照抄原生的实际渲染结果，保证和自带拓扑图一模一样）。
		var SHAPE_FONT_NAMES = ['Georgia', 'Palatino', 'Times New Roman', 'Arial', 'Arial Black', 'Comic Sans',
			'Impact', 'Lucida Sans', 'Tahoma', 'Helvetica', 'Verdana', 'Courier New', 'Lucida Console'];
		var SHAPE_FONT_FAMILIES = ['Georgia, serif', '"Palatino Linotype", "Book Antiqua", Palatino, serif',
			'"Times New Roman", Times, serif', 'Arial, Helvetica, sans-serif', '"Arial Black", Gadget, sans-serif',
			'"Comic Sans MS", cursive, sans-serif', 'Impact, Charcoal, sans-serif',
			'"Lucida Sans Unicode", "Lucida Grande", sans-serif', 'Tahoma, Geneva, sans-serif',
			'"Trebuchet MS", Helvetica, sans-serif', 'Verdana, Geneva, sans-serif',
			'"Courier New", Courier, monospace', '"Lucida Console", Monaco, monospace'];
		// 原生 SVGMap.BORDER_TYPES：点线/虚线的 dash 数组会随线宽放大
		var SHAPE_BORDER_DASHES = ['', 'none', '1,2', '4,4'];
		var SHAPE_TEXT_PADDING = 5;
		// 选中控制点：7×7 圆形，白底、1px 深蓝描边（对齐原生 .ui-resize-dot）
		var SHAPE_HANDLE_RADIUS = 3.5;
		var SEVERITY_COLORS = ['#97AAB3', '#7499FF', '#FFC859', '#FFA059', '#E97659', '#E45959'];
		var DEFAULT_LINK_COLOR = '00CC00';
		// 连线绑定的触发器处于问题态时的线色（自动发现建的线会绑端口状态类触发器）
		var DEFAULT_TRIGGER_COLOR = 'DD0000';
		// 同一对元素之间有多条连线（链路聚合的多个成员口）时，相邻两条线的垂直间距（像素）。
		// 画成对称偏移的二次贝塞尔曲线，避免完全重叠成一条。取值对齐 aiops：线贴近成束，
		// 每条线的指标徽标再沿曲线错开位置（避免相邻线的框叠在一起）。
		var LINK_PAIR_SPACING = 12;
		// 自动发现的「发现深度」可自定义（1~10 层，默认 3 层），之前把选项写死成 1~4 层
		var DISCOVER_DEPTH_MIN = 1;
		var DISCOVER_DEPTH_MAX = 10;
		var DISCOVER_DEPTH_DEFAULT = 3;

		// 画布尺寸不写死：最小铺满可视区域，拖拽/添加超出右边或下边时实时长大，
		// 保存时收缩到「内容外接框」与「可视区域」中较大的那个。
		// 这两个最小值只是画布还没布局出来时的兜底。
		var MIN_CANVAS_W = 1000;
		var MIN_CANVAS_H = 700;
		var CANVAS_PAD = 60;   // 内容四周留白
		var CANVAS_MAX = 10000; // sysmaps.width/height 的上限
		var DRAG_EDGE = 56;    // 拖拽时指针距离画布边缘多少像素开始自动平移
		var PAN_STEP = 16;     // 每次自动平移的像素（屏幕坐标）

		var theme = (document.documentElement.getAttribute('theme') || document.body.className || '').toLowerCase();
		var isDark = theme.indexOf('dark') !== -1;
		root.classList.add(isDark ? 'tm-dark' : 'tm-light');

		// 从自身脚本 URL 反推 zabbix.php / imgstore.php 所在目录，兼容子目录部署
		var scriptSrc = document.currentScript && document.currentScript.src ? String(document.currentScript.src) : '';
		scriptSrc = scriptSrc.split('?')[0].split('#')[0];

		var baseDir = '';

		if (scriptSrc) {
			var idx = scriptSrc.indexOf('modules/');
			baseDir = idx > 0 ? scriptSrc.slice(0, idx) : '';
		}

		var API_URL = baseDir + 'zabbix.php?action=topomap.data';
		var IMG_URL = baseDir + 'imgstore.php?iconid=';

		var state = {
			maps: [],
			icons: [],
			defaultIcon: '0',
			canEdit: false,
			map: null,
			editing: false,
			dirty: false,
			removeMode: null,
			linkMode: false,
			linkFirst: null,
			// 折点编辑中的链路（整组代表）：非空时画布点击空白处追加折点、显示折点圆点
			bendLink: null,
			// 当前选中的形（对齐原生：点一下出 8 个圆形控制点，拖动控制点改大小）
			shapeSel: null,
			selected: null,
			zoom: 1,
			panX: 0,
			panY: 0,
			tmpSeq: 0,
			iconSize: {},
			hostPick: null,
			itemPick: null,
			trigPick: null,
			// 已绑触发器的名字缓存（triggerid => description），画布/对话框里的 chip 用它
			triggerNames: {},
			// 触发器是否正在报警（triggerid => true）：手工刚绑上就能立刻按触发器色画线
			triggerProblems: {},
			// 列表：筛选 / 排序 / 勾选（对齐原生拓扑图列表的交互）
			filterName: '',
			sortField: 'name',
			sortOrder: 'ASC',
			checked: {}
		};

		// ---------------------------------------------------------------
		// DOM
		// ---------------------------------------------------------------

		var elListView = document.getElementById('tm-list-view');
		var elCanvasView = document.getElementById('tm-canvas-view');
		var elCreateBtn = document.getElementById('tm-create-btn');
		var elDiscoverBtn = document.getElementById('tm-discover-btn');
		// 「创建拓扑图」只属于列表页；画布视图里要藏起来
		var elHeaderControls = document.querySelector('#topomap-root .header-controls');
		var elListMsg = document.getElementById('tm-list-msg');
		var elListBody = document.getElementById('tm-list-body');
		var elListForm = document.getElementById('tm-list-form');

		var elFilterToggle = document.getElementById('tm-filter-toggle');
		var elFilterContainer = document.getElementById('tm-filter-container');
		var elFilterName = document.getElementById('tm-filter-name');
		var elCheckAll = document.getElementById('tm-check-all');
		var elSortName = document.getElementById('tm-sort-name');
		var elSortWidth = document.getElementById('tm-sort-width');
		var elSortHeight = document.getElementById('tm-sort-height');
		var elTableStats = document.getElementById('tm-table-stats');
		var elSelectedCount = document.getElementById('tm-selected-count');
		var elMassDelete = document.getElementById('tm-mass-delete');
		var elExportBtn = document.getElementById('tm-export-btn');

		var elCanvasTitle = document.getElementById('tm-canvas-title');
		var elCanvasSize = document.getElementById('tm-canvas-size');
		var elHint = document.getElementById('tm-hint');
		var elBackBtn = document.getElementById('tm-back-btn');
		var elZoomIn = document.getElementById('tm-zoom-in');
		var elZoomOut = document.getElementById('tm-zoom-out');
		var elZoomReset = document.getElementById('tm-zoom-reset');
		var elFitBtn = document.getElementById('tm-fit-btn');
		var elEditBtn = document.getElementById('tm-edit-btn');
		var elRediscoverBtn = document.getElementById('tm-rediscover-btn');
		var elSaveBtn = document.getElementById('tm-save-btn');
		var elExitBtn = document.getElementById('tm-exit-btn');
		var elEditbar = document.getElementById('tm-editbar');
		var elWrap = document.getElementById('tm-canvas-wrap');
		var elStage = document.getElementById('tm-stage');
		var elSvg = document.getElementById('tm-svg');

		var elOverlay = document.getElementById('tm-overlay');
		var elDialog = document.getElementById('tm-dialog');
		var elDialogTitle = document.getElementById('tm-dialog-title');
		var elDialogBody = document.getElementById('tm-dialog-body');
		var elDialogFoot = document.getElementById('tm-dialog-foot');
		var elDialogClose = document.getElementById('tm-dialog-close');

		var elHostOverlay = document.getElementById('tm-host-overlay');
		var elHostSearch = document.getElementById('tm-host-search');
		var elHostList = document.getElementById('tm-host-list');

		var elItemOverlay = document.getElementById('tm-item-overlay');
		var elItemTitle = document.getElementById('tm-item-title');
		var elItemSearch = document.getElementById('tm-item-search');
		var elItemList = document.getElementById('tm-item-list');
		var elItemHostBox = document.getElementById('tm-item-hostbox');
		var elItemHostPick = document.getElementById('tm-item-hostpick');
		var elItemAll = document.getElementById('tm-item-all');
		var elItemOk = document.getElementById('tm-item-ok');
		var elItemCancel = document.getElementById('tm-item-cancel');
		var elItemClose = document.getElementById('tm-item-close');

		// 主机弹层（监控项弹层里「主机 → 选择」打开）
		var elHpOverlay = document.getElementById('tm-hp-overlay');
		var elHpGroupBox = document.getElementById('tm-hp-groupbox');
		var elHpGroupPick = document.getElementById('tm-hp-grouppick');
		var elHpSearch = document.getElementById('tm-hp-search');
		var elHpList = document.getElementById('tm-hp-list');
		var elHpAll = document.getElementById('tm-hp-all');
		var elHpOk = document.getElementById('tm-hp-ok');
		var elHpCancel = document.getElementById('tm-hp-cancel');
		var elHpClose = document.getElementById('tm-hp-close');

		// 主机群组弹层（主机弹层里「主机群组 → 选择」打开）
		var elGgOverlay = document.getElementById('tm-gg-overlay');
		var elGgSearch = document.getElementById('tm-gg-search');
		var elGgList = document.getElementById('tm-gg-list');
		var elGgAll = document.getElementById('tm-gg-all');
		var elGgOk = document.getElementById('tm-gg-ok');
		var elGgCancel = document.getElementById('tm-gg-cancel');
		var elGgClose = document.getElementById('tm-gg-close');

		// 触发器弹层（链接属性里「触发器 → 选择」打开）
		var elTrigOverlay = document.getElementById('tm-trig-overlay');
		var elTrigGroupBox = document.getElementById('tm-trig-groupbox');
		var elTrigGroupPick = document.getElementById('tm-trig-grouppick');
		var elTrigHostBox = document.getElementById('tm-trig-hostbox');
		var elTrigHostPick = document.getElementById('tm-trig-hostpick');
		var elTrigSearch = document.getElementById('tm-trig-search');
		var elTrigList = document.getElementById('tm-trig-list');
		var elTrigAll = document.getElementById('tm-trig-all');
		var elTrigOk = document.getElementById('tm-trig-ok');
		var elTrigCancel = document.getElementById('tm-trig-cancel');
		var elTrigClose = document.getElementById('tm-trig-close');

		// ---------------------------------------------------------------
		// 工具函数
		// ---------------------------------------------------------------

		function clear(node) {
			while (node && node.firstChild) {
				node.removeChild(node.firstChild);
			}
		}

		function num(value, fallback) {
			var n = parseInt(value, 10);

			return isNaN(n) ? fallback : n;
		}

		function clamp(value, min, max) {
			return value < min ? min : (value > max ? max : value);
		}

		function copy(obj) {
			return JSON.parse(JSON.stringify(obj));
		}

		function make(tag, className, text) {
			var node = document.createElement(tag);

			if (className) {
				node.className = className;
			}

			if (text !== undefined && text !== null) {
				node.textContent = String(text);
			}

			return node;
		}

		function makeSelect(options, value, extraClass) {
			var sel = make('select', extraClass || null);

			options.forEach(function (pair) {
				var opt = make('option', null, pair[1]);
				opt.value = String(pair[0]);
				sel.appendChild(opt);
			});

			sel.value = String(value);

			return sel;
		}

		function msg(node, text, kind) {
			if (!node) {
				return;
			}

			node.textContent = text || '';
			node.className = 'tm-msg' + (kind === 'error' ? ' tm-msg-error' : (kind === 'ok' ? ' tm-msg-ok' : ''));
		}

		function setHint(text) {
			elHint.textContent = text || '';
		}

		/*
		 * 统一显隐：既写 hidden 属性，也写内联 display。
		 *
		 * 只靠 hidden 属性在真浏览器里不可靠：作者样式 `.tm-overlay{display:flex}`
		 * 会盖掉用户代理样式 `[hidden]{display:none}`（作者 > UA），于是
		 * `el.hidden = true` 只是加了个属性，视觉上什么都没变 —— 弹层关不掉。
		 * 内联样式的优先级高于任何作者样式选择器，且不受「CSS 文件被缓存成旧版」
		 * 的影响，所以两条腿走路。恢复显示时把内联值清空，交还给样式表。
		 */
		function setHidden(el, hide) {
			if (!el) {
				return;
			}

			el.hidden = !!hide;
			el.style.display = hide ? 'none' : '';
		}

		/**
		 * 给接口调用加一道前端超时：fetch 本身没有超时参数，后端卡住时只能自己 race，
		 * 否则发现对话框会永远停在「扫描 xxx …」。
		 */
		function withTimeout(promise, ms) {
			return new Promise(function (resolve, reject) {
				var timer = setTimeout(function () {
					reject(new Error(T('扫描超时（') + Math.round(ms / 1000) + T(' 秒仍无返回），已跳过这批设备')));
				}, ms);

				promise.then(function (value) {
					clearTimeout(timer);
					resolve(value);
				}, function (err) {
					clearTimeout(timer);
					reject(err);
				});
			});
		}

		// 统一的接口调用：先 text() 再 JSON.parse，区分 HTTP 错误 / 空响应 / 非 JSON
		function api(mode, params) {
			var body = new URLSearchParams();

			body.set('mode', mode);

			Object.keys(params || {}).forEach(function (key) {
				body.set(key, params[key] === null || params[key] === undefined ? '' : String(params[key]));
			});

			return fetch(API_URL, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: body.toString()
			}).then(function (res) {
				return res.text().then(function (text) {
					if (!res.ok) {
						throw new Error(T('接口返回 HTTP ') + res.status + T('：') + text.slice(0, 150));
					}

					if (!text) {
						throw new Error(T('接口返回空响应（HTTP ') + res.status + T('）'));
					}

					var data;

					try {
						data = JSON.parse(text);
					}
					catch (e) {
						throw new Error(T('接口返回的不是 JSON：') + text.slice(0, 150));
					}

					if (!data || data.success !== true) {
						throw new Error(data && data.error ? data.error : T('接口返回失败'));
					}

					return data;
				});
			});
		}

		// ---------------------------------------------------------------
		// 预览模式：点主机图标弹原生主机菜单
		// ---------------------------------------------------------------

		// 菜单数据由 Zabbix 自带的 menu.popup 控制器生成（视图/配置/链接/脚本四段，
		// 权限判断、URL、脚本、以及第三方模块注入的「View Neighbors (LLDP/CDP)」都在里面），
		// 拿回来后用原生 getMenuPopupHost + $.fn.menuPopup 渲染，外观与自带拓扑图一致。
		function openHostMenu(se, node, event) {
			if (se.elementtype !== ET_HOST || !se._hostid) {
				return;
			}

			event.stopPropagation();
			event.preventDefault();

			var body = new URLSearchParams();

			body.set('type', 'host');
			body.set('data[hostid]', String(se._hostid));

			fetch('zabbix.php?action=menu.popup', {
				method: 'POST',
				credentials: 'same-origin',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: body.toString()
			}).then(function (res) {
				return res.text();
			}).then(function (text) {
				var resp;

				try {
					resp = JSON.parse(text);
				}
				catch (e) {
					throw new Error(T('菜单接口返回的不是 JSON'));
				}

				if (!resp || !resp.data) {
					throw new Error(resp && resp.error ? resp.error : T('菜单数据为空'));
				}

				showHostMenu(se, node, event, resp.data);
			}).catch(function (err) {
				openNoticeDialog(T('无法打开主机菜单'), err && err.message ? err.message : T('请求失败'));
			});
		}

		function showHostMenu(se, node, event, menuData) {
			var $ = window.jQuery;

			if (!$ || !$.fn || typeof $.fn.menuPopup !== 'function' || typeof getMenuPopupHost !== 'function') {
				return;
			}

			var $el = $(node);
			var hostid = String(se._hostid);
			var sections = getMenuPopupHost(menuData, $el);

			// 原生地图页有 view.editHost 来打开主机编辑弹窗，模块页没有，
			// 改成直接调 Zabbix 的 PopUp（同一个 popup.host.edit 页面）
			sections.forEach(function (section) {
				(section.items || []).forEach(function (item) {
					if (item.label !== '主机' && item.label !== 'Host') {
						return;
					}

					item.disabled = !menuData.isWriteable;
					item.clickCallback = function () {
						if (typeof PopUp === 'function') {
							PopUp('popup.host.edit', {hostid: hostid}, {
								dialogueid: 'host_edit',
								dialogue_class: 'modal-popup-large',
								prevent_navigation: true,
								trigger_element: node
							});
						}
						else {
							window.location.href = 'zabbix.php?action=host.edit&hostid=' + encodeURIComponent(hostid);
						}
					};
				});
			});

			// menuPopup 默认按点击位置展开，需要 jQuery 事件（原生 event.originalEvent）
			var jqEvent = $.Event(event);

			jqEvent.pageX = event.pageX;
			jqEvent.pageY = event.pageY;

			$el.menuPopup(sections, jqEvent, {});
		}

		// ---------------------------------------------------------------
		// 图标
		// ---------------------------------------------------------------

		function iconUrl(iconid) {
			// 离线演示页会把图标以 data URI 挂在 window.TOPOMAP_ICON_DATA 上；
			// 真实环境里没有这个变量，直接走 imgstore.php。
			if (window.TOPOMAP_ICON_DATA && window.TOPOMAP_ICON_DATA[iconid]) {
				return window.TOPOMAP_ICON_DATA[iconid];
			}

			return IMG_URL + encodeURIComponent(iconid);
		}

		function iconSize(iconid) {
			return state.iconSize[iconid] || {w: 48, h: 48};
		}

		function preloadIcons(ids) {
			var tasks = [];

			ids.forEach(function (iconid) {
				if (!iconid || iconid === '0' || state.iconSize[iconid]) {
					return;
				}

				tasks.push(new Promise(function (resolve) {
					var img = new Image();

					img.onload = function () {
						state.iconSize[iconid] = {
							w: img.naturalWidth || 48,
							h: img.naturalHeight || 48
						};
						resolve();
					};

					img.onerror = function () {
						state.iconSize[iconid] = {w: 48, h: 48};
						resolve();
					};

					img.src = iconUrl(iconid);
				}));
			});

			return Promise.all(tasks);
		}

		function iconForElement(se) {
			if (se.elementtype === ET_HOST) {
				if (se._status === 1 && se.iconid_disabled !== '0') {
					return se.iconid_disabled;
				}

				if (se._problems > 0 && se.iconid_on !== '0') {
					return se.iconid_on;
				}

				// 维护中（且没有问题）：原生用「维护」图标
				if (se._maintenance === 1 && se.iconid_maintenance !== '0') {
					return se.iconid_maintenance;
				}
			}

			return se.iconid_off;
		}

		/*
		 * 图标后面的高亮图形，对齐原生 getMapHighligts() + SVGMapElement.updateHighlight()：
		 *   问题中 → 严重级别色的圆（已确认问题加 4px 绿描边）
		 *   维护中 → 橙色方框（50% 透明）
		 *   已停用 → 灰色方框（50% 透明）
		 * 只有图的 highlight 开关为奇数（原生 SYSMAP_HIGHLIGHT_ON=1）时才画。
		 */
		function highlightForElement(se) {
			var map = state.map;

			if (!map || num(map.highlight, 0) % 2 !== 1) {
				return null;
			}

			if (se.elementtype !== ET_HOST) {
				return null;
			}

			if (se._status === 1) {
				return {st: '999999'};
			}

			if (se._problems > 0) {
				var sev = num(se._severity, 0);
				var color = SEVERITY_COLORS[sev >= 0 && sev < SEVERITY_COLORS.length ? sev : 0];

				return {hl: String(color).replace('#', ''), ack: num(se._unack, 0) === 0};
			}

			if (se._maintenance === 1) {
				return {st: 'FF9933'};
			}

			return null;
		}

		// ---------------------------------------------------------------
		// 数据归一化
		// ---------------------------------------------------------------

		// 某类元素该用哪个 label_type：开了「高级标签」(label_format=1) 就按元素类型取，
		// 否则统一用全局 label_type —— 和 7.0.28 class.cmap.js 的 updateLabel() 一致。
		function labelTypeFor(map, elementtype) {
			var type = map.label_type;

			if (map.label_format == 1) {
				switch (elementtype) {
					case ET_HOST:
						type = map.label_type_host;
						break;

					case ET_MAP:
						type = map.label_type_map;
						break;

					case ET_TRIGGER:
						type = map.label_type_trigger;
						break;

					case ET_GROUP:
						type = map.label_type_hostgroup;
						break;

					case ET_IMAGE:
						type = map.label_type_image;
						break;
				}
			}

			if (type === undefined || type === null || type < 0 || isNaN(type)) {
				type = map.label_type;
			}

			return num(type, LT_LABEL);
		}

		function statusColor(name) {
			var map = state.map;
			var val = map && map.colors ? map.colors[name] : '';

			return String(val || STATUS_COLORS[name] || '000000').replace('#', '');
		}

		function normalizeMap(raw) {
			var map = raw;

			map.width = num(map.width, 1000);
			map.height = num(map.height, 700);
			map.label_location = num(map.label_location, 0);
			map.selements = map.selements || [];
			map.links = map.links || [];
			map.shapes = map.shapes || [];
			map.hosts = map.hosts || {};
			map.colors = map.colors || {};
			// 链路指标：每链路的监控项 ID（后端自建表），以及这些监控项的最新值元信息
			map.metric_items = map.metric_items || {};

			// 标签配置：label_format=1 时才按元素类型取 label_type_*，否则用全局 label_type
			map.label_format = num(map.label_format, 0);
			map.label_type = num(map.label_type, 0);
			map.label_type_host = num(map.label_type_host, -1);
			map.label_type_hostgroup = num(map.label_type_hostgroup, -1);
			map.label_type_trigger = num(map.label_type_trigger, -1);
			map.label_type_map = num(map.label_type_map, -1);
			map.label_type_image = num(map.label_type_image, -1);
			map.show_unack = num(map.show_unack, 0);
			map.expandproblem = num(map.expandproblem, 0);
			// 元素高亮（原生「高亮」开关）：开着才有图标后面的严重级别色圆 / 维护态方框
			map.highlight = num(map.highlight, 0);

			map.selements.forEach(function (se) {
				se.elementtype = num(se.elementtype, ET_IMAGE);
				se.x = num(se.x, 0);
				se.y = num(se.y, 0);
				se.label_location = num(se.label_location, -1);
				se.use_iconmap = num(se.use_iconmap, 0);
				se.evaltype = num(se.evaltype, 0);
				se.iconid_off = String(se.iconid_off || '0');
				se.iconid_on = String(se.iconid_on || '0');
				se.iconid_disabled = String(se.iconid_disabled || '0');
				se.iconid_maintenance = String(se.iconid_maintenance || '0');
				se.label = se.label || '';
				se.tags = se.tags || [];
				se.urls = se.urls || [];
				se.elements = se.elements || [];

				var hostid = '';

				if (se.elementtype === ET_HOST && se.elements.length && se.elements[0].hostid) {
					hostid = String(se.elements[0].hostid);
				}

				var host = hostid && map.hosts[hostid] ? map.hosts[hostid] : null;

				se._hostid = hostid;
				se._hostName = host ? host.name : '';
				se._ip = host ? String(host.ip || '') : '';
				se._status = host ? num(host.status, 0) : 0;
				se._maintenance = host ? num(host.maintenance, 0) : 0;
				se._problems = host ? num(host.problems, 0) : 0;
				se._unack = host ? num(host.unack, 0) : 0;
				se._severity = host ? num(host.severity, -1) : -1;
				se._title = host ? String(host.title || '') : '';
				se._labelType = labelTypeFor(map, se.elementtype);
			});

			map.links.forEach(function (link) {
				link.drawtype = num(link.drawtype, 0);
				link.color = link.color || DEFAULT_LINK_COLOR;
				link.label = link.label || '';
				link.linktriggers = link.linktriggers || [];
				link.metrics = normalizeMetrics(link.metrics);
				// 两端端口名与「触发器来源端」口径：后端存在模块自建表里，随链路一起带回
				link.source_port = String(link.source_port || '');
				link.target_port = String(link.target_port || '');
				link.trigger_source = num(link.trigger_source, 0);
				// 走线模式与手动折点（后端表 topomap_link_paths）。
				// 自动发现的连线（两端端口名是发现时写进来的）默认走「自动直角」，
				// 按树形布局从父节点底下统一分叉；手工连线仍默认「自动曲线」。
				var autoRouting = (link.source_port || link.target_port) ? 1 : 0;
				link.routing = Math.max(0, Math.min(2, num(link.routing, autoRouting)));
				link.waypoints = normalizeWaypointList(link.waypoints);
			});

			map.shapes.forEach(function (shape) {
				// 默认值与原生 Shape 构造器一致：10,10 起、50×50、Helvetica 11、黑框 2px
				shape.type = Math.max(0, Math.min(2, num(shape.type, 0)));
				shape.x = num(shape.x, 10);
				shape.y = num(shape.y, 10);
				shape.width = num(shape.width, 50);
				shape.height = num(shape.height, 50);
				shape.text = shape.text || '';
				shape.font = Math.max(0, Math.min(SHAPE_FONT_NAMES.length - 1, num(shape.font, 9)));
				shape.font_size = num(shape.font_size, 11);
				shape.font_color = shape.font_color || '000000';
				shape.text_halign = num(shape.text_halign, 0);
				shape.text_valign = num(shape.text_valign, 0);
				shape.border_type = Math.max(0, Math.min(3, num(shape.border_type, 1)));
				shape.border_width = num(shape.border_width, 2);
				shape.border_color = shape.border_color || '000000';
				shape.background_color = shape.background_color || '';
				shape.zindex = num(shape.zindex, 0);
			});

			return map;
		}

		// ---------------------------------------------------------------
		// 列表
		// ---------------------------------------------------------------

		function loadIcons() {
			return api('icons').then(function (data) {
				state.icons = data.icons || [];
				state.defaultIcon = String(data.default || '0');
			}).catch(function () {
				state.icons = [];
			});
		}

		function loadList() {
			msg(elListMsg, T('加载中…'));
			clear(elListBody);

			var tr = make('tr');
			var td = make('td', 'tm-empty', T('加载中…'));
			td.colSpan = 3;
			tr.appendChild(td);
			elListBody.appendChild(tr);

			return api('list').then(function (data) {
				state.maps = data.maps || [];
				state.canEdit = !!data.can_edit;
				syncCreateState();
				renderList();
				msg(elListMsg, T('共 ') + state.maps.length + T(' 个拓扑图'));

				if (!state.canEdit) {
					msg(elListMsg, T('当前用户没有创建 / 编辑拓扑图的权限，仅供查看'), 'error');
				}
			}).catch(function (e) {
				clear(elListBody);

				var row = make('tr');
				var cell = make('td', 'tm-empty', T('加载失败：') + e.message);
				cell.colSpan = 3;
				row.appendChild(cell);
				elListBody.appendChild(row);
				msg(elListMsg, T('加载失败：') + e.message, 'error');
			});
		}

		function syncCreateState() {
			elCreateBtn.disabled = !state.canEdit;
		}

		// ---------------------------------------------------------------
		// 列表：筛选 / 排序 / 勾选 / 批量操作
		// 结构与交互对齐原生「监测 → 拓扑图」
		// ---------------------------------------------------------------

		function visibleMaps() {
			var kw = state.filterName.trim().toLowerCase();
			var rows = state.maps.filter(function (map) {
				return !kw || String(map.name).toLowerCase().indexOf(kw) !== -1;
			});
			var field = state.sortField;
			var dir = state.sortOrder === 'DESC' ? -1 : 1;

			rows.sort(function (a, b) {
				var va = a[field];
				var vb = b[field];
				var cmp;

				if (typeof va === 'number' && typeof vb === 'number') {
					cmp = va - vb;
				}
				else {
					// 必须用 localeCompare：直接比字符串是码点序，「机房B」会排到「核心网络」前面
					cmp = String(va).localeCompare(String(vb), 'zh-CN', {numeric: true, sensitivity: 'base'});
				}

				if (cmp === 0) {
					cmp = String(a.name).localeCompare(String(b.name), 'zh-CN');
				}

				return cmp * dir;
			});

			return rows;
		}

		function checkedIds() {
			return state.maps
				.filter(function (m) { return state.checked[m.sysmapid]; })
				.map(function (m) { return m.sysmapid; });
		}

		function updateSelection() {
			var ids = checkedIds();
			var shown = visibleMaps().length;

			elSelectedCount.textContent = ids.length + T(' 已选');

			if (elMassDelete) {
				elMassDelete.disabled = ids.length === 0;
			}

			if (elCheckAll) {
				elCheckAll.checked = shown > 0 && ids.length >= shown;
				elCheckAll.indeterminate = ids.length > 0 && ids.length < shown;
			}
		}

		function renderList() {
			clear(elListBody);

			var rows = visibleMaps();

			elTableStats.textContent = T('显示 ') + rows.length + T('，共找到 ') + state.maps.length + ' ';

			if (!rows.length) {
				var emptyRow = make('tr');
				var emptyCell = make('td', 'tm-empty', state.maps.length
					? T('没有匹配的拓扑图。')
					: T('暂无拓扑图。点击右上角「创建拓扑图」新建一个。'));
				emptyCell.colSpan = 5;
				emptyRow.appendChild(emptyCell);
				elListBody.appendChild(emptyRow);
				updateSelection();

				return;
			}

			rows.forEach(function (map) {
				var tr = make('tr');

				// 勾选（原生 .checkbox-radio + label>span 的画法）
				var tdCheck = make('td');
				var cb = make('input', 'checkbox-radio');
				cb.type = 'checkbox';
				cb.id = 'tm-maps_' + map.sysmapid;
				cb.checked = !!state.checked[map.sysmapid];
				cb.disabled = !state.canEdit;
				cb.addEventListener('change', function () {
					if (cb.checked) {
						state.checked[map.sysmapid] = true;
					}
					else {
						delete state.checked[map.sysmapid];
					}

					updateSelection();
				});

				var lb = make('label');
				lb.htmlFor = cb.id;
				lb.appendChild(make('span'));
				tdCheck.appendChild(cb);
				tdCheck.appendChild(lb);

				// 名称（点击进画布预览）
				var tdName = make('td', 'wordbreak');
				var link = make('a', '', map.name);
				link.href = '#';
				link.title = T('元素 ') + map.elements + T(' 个');
				link.addEventListener('click', function (e) {
					e.preventDefault();
					openMap(map.sysmapid, false);
				});
				tdName.appendChild(link);

				var tdW = make('td', '', String(map.width));
				var tdH = make('td', '', String(map.height));

				// 动作：属性 / 编辑
				var tdAct = make('td');
				var ul = make('ul', 'hor-list');

				if (state.canEdit) {
					var prop = make('a', '', T('属性'));
					prop.href = '#';
					prop.addEventListener('click', function (e) {
						e.preventDefault();
						openPropertiesDialog(map);
					});

					var edit = make('a', '', T('编辑'));
					edit.href = '#';
					edit.addEventListener('click', function (e) {
						e.preventDefault();
						openMap(map.sysmapid, true);
					});

					var liProp = make('li');
					var liEdit = make('li');
					liProp.appendChild(prop);
					liEdit.appendChild(edit);
					ul.appendChild(liProp);
					ul.appendChild(liEdit);
				}
				else {
					var liDash = make('li', 'tm-hint', '—');
					ul.appendChild(liDash);
				}

				tdAct.appendChild(ul);

				tr.appendChild(tdCheck);
				tr.appendChild(tdName);
				tr.appendChild(tdW);
				tr.appendChild(tdH);
				tr.appendChild(tdAct);
				elListBody.appendChild(tr);
			});

			updateSelection();
		}

		function syncSortArrows() {
			[[elSortName, 'name'], [elSortWidth, 'width'], [elSortHeight, 'height']].forEach(function (pair) {
				var el = pair[0];

				if (!el) {
					return;
				}

				var span = el.querySelector('span');

				if (!span) {
					span = make('span');
					el.appendChild(span);
				}

				span.className = (pair[1] === state.sortField)
					? (state.sortOrder === 'ASC' ? 'arrow-up' : 'arrow-down')
					: '';
			});
		}

		function sortBy(field) {
			if (state.sortField === field) {
				state.sortOrder = state.sortOrder === 'ASC' ? 'DESC' : 'ASC';
			}
			else {
				state.sortField = field;
				state.sortOrder = 'ASC';
			}

			syncSortArrows();
			renderList();
		}

		function applyFilter() {
			state.filterName = elFilterName.value;
			renderList();
		}

		function resetFilter() {
			elFilterName.value = '';
			state.filterName = '';
			renderList();
			elFilterName.focus();
		}

		function toggleCheckAll() {
			var check = !!elCheckAll.checked;

			visibleMaps().forEach(function (map) {
				if (check) {
					state.checked[map.sysmapid] = true;
				}
				else {
					delete state.checked[map.sysmapid];
				}
			});

			renderList();
		}

		function massDelete() {
			var ids = checkedIds();

			if (!ids.length) {
				return;
			}

			confirmDialog(T('删除 ') + ids.length + T(' 张拓扑图？'),
				T('删除后无法恢复。这些图与「监测 → 拓扑图」共用同一份数据，原生列表里也会一起消失。'),
				T('删除')
			).then(function (ok) {
				if (!ok) {
					return;
				}

				api('delete', {sysmapids: ids.join(',')}).then(function () {
					ids.forEach(function (id) {
						delete state.checked[id];
					});
					msg(elListMsg, T('已删除 ') + ids.length + T(' 张拓扑图'), 'ok');

					return loadList();
				}).catch(function (e) {
					msg(elListMsg, T('删除失败：') + e.message, 'error');
				});
			});
		}

		function exportMaps() {
			var ids = checkedIds();

			api('export', {sysmapids: ids.join(',')}).then(function (data) {
				var a = document.createElement('a');

				if (typeof URL !== 'undefined' && URL.createObjectURL) {
					var url = URL.createObjectURL(new Blob([data.json], {type: 'application/json'}));

					a.href = url;
					setTimeout(function () {
						URL.revokeObjectURL(url);
					}, 2000);
				}
				else {
					a.href = 'data:application/json;charset=utf-8,' + encodeURIComponent(data.json);
				}

				a.download = data.filename || 'topomap-export.json';
				document.body.appendChild(a);
				a.click();
				document.body.removeChild(a);
				msg(elListMsg, T('已导出 ') + data.count + T(' 张拓扑图'), 'ok');
			}).catch(function (e) {
				msg(elListMsg, T('导出失败：') + e.message, 'error');
			});
		}

		// 创建拓扑图：创建按钮 → 名称 / 画布尺寸 / 应用 / 重设
		function openCreateDialog() {
			if (!state.canEdit) {
				openNoticeDialog(T('没有权限'), T('当前用户没有创建 / 编辑拓扑图的权限（需要角色里的「创建和编辑拓扑图」）。'));

				return;
			}

			openDialog({
				title: T('创建拓扑图'),
				narrow: true,
				build: function (body) {
					var tbody = dialogForm(body);
					var nameRow = formRow(tbody, T('名称'), true);
					var input = make('input', 'tm-input tm-input-block');
					input.type = 'text';
					input.maxLength = 128;
					input.placeholder = T('输入拓扑图名称');
					input.autocomplete = 'off';
					nameRow.td.appendChild(input);

					// 画布尺寸不再填：进去后拖到哪儿画布就长到哪儿，保存时自动收紧
					var sizeRow = formRow(tbody, T('画布尺寸'), false);
					sizeRow.td.appendChild(make('div', 'tm-size-auto', T('自动')));
					sizeRow.td.appendChild(make('div', 'tm-hint',
						T('创建后画布会随元素自动扩展，保存时按内容收紧到合适大小')));

					var warn = make('div', 'tm-msg');
					warn.style.marginTop = '6px';
					nameRow.td.appendChild(warn);

					setTimeout(function () {
						input.focus();
					}, 0);

					input.addEventListener('keydown', function (e) {
						if (e.key === 'Enter') {
							openCreateDialog.submit();
						}
					});

					openCreateDialog.reset = function () {
						input.value = '';
						warn.textContent = '';
						input.focus();
					};

					openCreateDialog.submit = function () {
						var name = input.value.trim();

						if (!name) {
							warn.className = 'tm-msg tm-msg-error';
							warn.textContent = T('名称不能为空');
							input.focus();

							return;
						}

						warn.className = 'tm-msg';
						warn.textContent = T('创建中…');
						elCreateBtn.disabled = true;

						api('create', {name: name}).then(function (data) {
							closeDialog();

							return loadList().then(function () {
								msg(elListMsg, T('已创建「') + name + T('」'), 'ok');
								openMap(data.sysmapid, false);
							});
						}).catch(function (e) {
							warn.className = 'tm-msg tm-msg-error';
							warn.textContent = T('创建失败：') + e.message;
						}).then(function () {
							syncCreateState();
						});
					};
				},
				buttons: [
					{label: T('应用'), primary: true, onClick: function () { openCreateDialog.submit(); }},
					{label: T('重设'), onClick: function () { openCreateDialog.reset(); }}
				]
			});
		}

		// ---------------------------------------------------------------
		// 自动发现（SNMP LLDP / CDP）
		// ---------------------------------------------------------------

		// 弹窗内的临时状态：勾了哪些起点、扫到哪些节点/链路
		var discover = null;

		function openDiscoverDialog() {
			discover = {
				mode: 'create',
				hosts: [],
				picked: {},
				nodes: {},
				links: {},
				scanned: {},
				running: false,
				finished: false
			};

			openDialog({
				title: T('自动发现拓扑'),
				build: buildDiscoverBody,
				buttons: [
					{label: T('取消'), onClick: closeDialog},
					{
						label: T('开始发现'),
						primary: true,
						// 发现完成后同一个按钮变成「生成拓扑图」
						onClick: function () {
							if (discover && discover.finished) {
								saveDiscover();
							}
							else {
								startDiscover();
							}
						}
					}
				]
			});

			loadDiscoverHosts('');
		}

		function isRediscover() {
			return !!(discover && discover.mode === 'rediscover');
		}

		// 画布内「重新发现」：以当前图里的主机元素为种子重新扫描，
		// 结果就地并入当前图（保留已配置的链路指标）。
		function openRediscoverDialog() {
			if (!state.map) {
				return;
			}

			if (!state.canEdit) {
				openNoticeDialog(T('没有权限'), T('当前用户没有创建 / 编辑拓扑图的权限（需要角色里的「创建和编辑拓扑图」）。'));

				return;
			}

			var seeds = [];

			state.map.selements.forEach(function (se) {
				if (se.elementtype === ET_HOST && se._hostid) {
					seeds.push(String(se._hostid));
				}
			});

			if (!seeds.length) {
				msg(elListMsg, T('当前拓扑图里没有主机元素，无法重新发现'), 'error');

				return;
			}

			discover = {
				mode: 'rediscover',
				hostids: seeds,
				hosts: [],
				picked: {},
				nodes: {},
				links: {},
				scanned: {},
				running: false,
				finished: false
			};

			seeds.forEach(function (hostid) {
				discover.picked[hostid] = true;
			});

			openDialog({
				title: T('重新发现并更新拓扑图'),
				build: buildDiscoverBody,
				buttons: [
					{label: T('取消'), onClick: closeDialog},
					{
						label: T('开始发现'),
						primary: true,
						onClick: function () {
							if (discover && discover.finished) {
								applyRediscover();
							}
							else {
								startDiscover();
							}
						}
					}
				]
			});

			// 种子主机的名字 / IP 用于新节点的标签；取不到就用 hostid 兜底
			api('hosts', {hostids: seeds.join(',')}).then(function (data) {
				discover.hosts = data.hosts || [];
			}).catch(function () {});
		}

		function buildDiscoverBody(body) {
			if (isRediscover()) {
				body.appendChild(make('p', 'tm-discover-tip',
					T('以当前图里的 ') + discover.hostids.length + T(' 台主机为起点重新扫描；新增设备/链路会并入当前图，')
					+ T('已配置的带宽/流量监控项保留。')));
			}
			else {
				body.appendChild(make('p', 'tm-discover-tip',
					T('从交换机读取 LLDP / CDP 邻居表，自动生成拓扑图。起点设备需要配置 SNMP 接口（推荐 v2c）。')));

				var searchRow = make('div', 'tm-discover-search');
				var search = make('input', 'tm-input');
				search.type = 'text';
				search.placeholder = T('搜索主机名称…');
				search.autocomplete = 'off';

				var timer = null;
				search.addEventListener('input', function () {
					clearTimeout(timer);
					timer = setTimeout(function () {
						loadDiscoverHosts(search.value.trim());
					}, 300);
				});

				searchRow.appendChild(search);

				var pick = make('button', 'tm-btn tm-btn-sm', T('全选有 SNMP 的'));
				pick.type = 'button';
				pick.addEventListener('click', function () {
					discover.hosts.forEach(function (host) {
						if (host.snmp) {
							discover.picked[host.hostid] = true;
						}
					});
					renderDiscoverHosts();
				});
				searchRow.appendChild(pick);

				var counter = make('span', 'tm-discover-count', T('已选 0 台'));
				counter.id = 'tm-discover-count';
				searchRow.appendChild(counter);
				body.appendChild(searchRow);

				var list = make('div', 'tm-discover-hosts');
				list.id = 'tm-discover-hosts';
				body.appendChild(list);
			}

			var opts = make('div', 'tm-discover-opts');

			var depthLabel = make('label', 'tm-discover-field');
			depthLabel.appendChild(make('span', 'tm-discover-field-name', T('发现深度（1-10 层）')));
			// 之前是写死的 1~4 层下拉，深层网络到第 5 层就再也发现不下去，改成可自定义的数字框
			var depth = make('input', 'tm-input');
			depth.type = 'number';
			depth.id = 'tm-discover-depth';
			depth.min = String(DISCOVER_DEPTH_MIN);
			depth.max = String(DISCOVER_DEPTH_MAX);
			depth.step = '1';
			depth.value = String(DISCOVER_DEPTH_DEFAULT);
			depthLabel.appendChild(depth);
			opts.appendChild(depthLabel);

			opts.appendChild(discoverCheck('tm-discover-lldp', 'LLDP', true));
			opts.appendChild(discoverCheck('tm-discover-cdp', 'CDP', false));
			opts.appendChild(discoverCheck('tm-discover-edp', 'EDP', false));
			opts.appendChild(discoverCheck('tm-discover-unmanaged', T('包含未纳管设备'), true));

			// 重新发现专用的两个选项：布局方式、未再发现的自动连线是否移除
			if (isRediscover()) {
				var layoutField = make('label', 'tm-discover-field');
				layoutField.appendChild(make('span', 'tm-discover-field-name', T('布局')));
				var layoutSel = make('select', 'tm-input');
				layoutSel.id = 'tm-rediscover-layout';
				[['tree', T('全部重排成树形')], ['keep', T('保留现有坐标')]].forEach(function (opt) {
					layoutSel.appendChild(make('option', '', opt[1])).value = opt[0];
				});
				layoutField.appendChild(layoutSel);
				opts.appendChild(layoutField);

				var pruneField = make('label', 'tm-discover-field');
				pruneField.appendChild(make('span', 'tm-discover-field-name', T('未再发现的自动连线')));
				var pruneSel = make('select', 'tm-input');
				pruneSel.id = 'tm-rediscover-prune';
				[['0', T('保留')], ['1', T('移除')]].forEach(function (opt) {
					pruneSel.appendChild(make('option', '', opt[1])).value = opt[0];
				});
				pruneField.appendChild(pruneSel);
				opts.appendChild(pruneField);
			}

			body.appendChild(opts);

			// 自动关联触发器的关键词：一行一个，发现生成图时按它去匹配端口状态类触发器。
			// 之前写死在代码里，用户遇到设备描述不一样（比如「处于关闭状态」）就没法改。
			var kwField = make('label', 'tm-discover-field tm-discover-kw');
			kwField.appendChild(make('span', 'tm-discover-field-name', T('自动关联触发器关键词（一行一个）')));

			var keywords = make('textarea', 'tm-input tm-discover-keywords');
			keywords.id = 'tm-discover-keywords';
			keywords.rows = 4;
			keywords.value = T('处于关闭状态');
			keywords.placeholder = T('一行一个，例如：\n处于关闭状态\nlink down');
			kwField.appendChild(keywords);
			body.appendChild(kwField);

			// 用上次保存过的关键词覆盖默认值（没保存过就保留「处于关闭状态」）
			api('settings').then(function (data) {
				var el = document.getElementById('tm-discover-keywords');

				if (el && data && data.trigger_keywords && data.trigger_keywords.length) {
					el.value = data.trigger_keywords.join('\n');
				}
			}).catch(function () {});

			var log = make('div', 'tm-discover-log');
			log.id = 'tm-discover-log';
			body.appendChild(log);

			var nameRow = make('div', 'tm-discover-namerow');
			nameRow.id = 'tm-discover-name-row';
			// .tm-discover-namerow 是 display:flex，光设 hidden 属性会被作者样式盖掉，
			// 统一走 setHidden（属性 + 内联 display 一起改）
			setHidden(nameRow, true);
			nameRow.appendChild(make('span', 'tm-discover-field-name', T('拓扑图名称')));
			var nameInput = make('input', 'tm-input');
			nameInput.type = 'text';
			nameInput.id = 'tm-discover-name';
			nameInput.maxLength = 128;
			nameInput.value = defaultDiscoverName();
			nameRow.appendChild(nameInput);
			body.appendChild(nameRow);
		}

		function discoverCheck(id, label, isChecked) {
			var wrap = make('label', 'tm-discover-check');
			var input = make('input');
			input.type = 'checkbox';
			input.id = id;
			input.checked = !!isChecked;
			wrap.appendChild(input);
			wrap.appendChild(make('span', '', label));

			return wrap;
		}

		function loadDiscoverHosts(search) {
			var list = document.getElementById('tm-discover-hosts');

			if (!list) {
				return;
			}

			list.textContent = T('加载中…');

			api('hosts', {search: search || ''}).then(function (data) {
				// 只保留有 SNMP 接口的勾选状态，避免「勾了但扫不了」
				discover.hosts = data.hosts || [];
				renderDiscoverHosts();
			}).catch(function (e) {
				clear(list);
				list.appendChild(make('div', 'tm-discover-empty', T('加载主机失败：') + e.message));
			});
		}

		function renderDiscoverHosts() {
			var list = document.getElementById('tm-discover-hosts');

			if (!list) {
				return;
			}

			clear(list);

			if (!discover.hosts.length) {
				list.appendChild(make('div', 'tm-discover-empty', T('没有匹配的主机（试试清空搜索词）')));

				return;
			}

			discover.hosts.forEach(function (host) {
				var item = make('label', 'tm-discover-host' + (host.snmp ? '' : ' tm-discover-host-off'));

				var box = make('input');
				box.type = 'checkbox';
				box.value = host.hostid;
				box.checked = !!discover.picked[host.hostid];
				box.disabled = !host.snmp || discover.running;
				box.addEventListener('change', function () {
					if (box.checked) {
						discover.picked[host.hostid] = true;
					}
					else {
						delete discover.picked[host.hostid];
					}
					updateDiscoverCount();
				});

				item.appendChild(box);
				item.appendChild(make('span', 'tm-discover-host-name', host.name));

				if (host.ip) {
					item.appendChild(make('span', 'tm-discover-host-ip', host.ip));
				}

				item.appendChild(make('span', 'tm-discover-host-tag' + (host.snmp ? '' : ' tm-dim'),
					host.snmp ? 'SNMP' : T('无 SNMP')));
				list.appendChild(item);
			});

			updateDiscoverCount();
		}

		function updateDiscoverCount() {
			var counter = document.getElementById('tm-discover-count');

			if (counter) {
				counter.textContent = T('已选 ') + Object.keys(discover.picked).length + T(' 台');
			}
		}

		function appendDiscoverLog(text, kind) {
			var log = document.getElementById('tm-discover-log');

			if (!log) {
				return;
			}

			log.appendChild(make('div', 'tm-discover-line' + (kind ? ' tm-discover-' + kind : ''), text));
			log.scrollTop = log.scrollHeight;
		}

		function discoverOption(id) {
			var el = document.getElementById(id);

			return !!(el && el.checked);
		}

		function defaultDiscoverName() {
			var now = new Date();

			function pad(n) {
				return (n < 10 ? '0' : '') + n;
			}

			return T('自动发现 ') + now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate())
				+ ' ' + pad(now.getHours()) + ':' + pad(now.getMinutes());
		}

		function setDiscoverBusy(busy, label) {
			var buttons = elDialogFoot.querySelectorAll('button');

			if (buttons[0]) {
				buttons[0].disabled = busy;
			}

			if (buttons[1]) {
				buttons[1].disabled = busy;
				buttons[1].textContent = label;
			}

			document.querySelectorAll('#tm-dialog-body input, #tm-dialog-body select').forEach(function (el) {
				el.disabled = busy;
			});
		}

		function startDiscover() {
			if (!discover || discover.running) {
				return;
			}

			var seeds = Object.keys(discover.picked);

			if (!seeds.length) {
				appendDiscoverLog(T('请先勾选至少一台起点设备。'), 'error');

				return;
			}

			var protocols = [];

			if (discoverOption('tm-discover-lldp')) {
				protocols.push('LLDP');
			}

			if (discoverOption('tm-discover-cdp')) {
				protocols.push('CDP');
			}

			if (discoverOption('tm-discover-edp')) {
				protocols.push('EDP');
			}

			if (!protocols.length) {
				protocols = ['LLDP'];
			}

			var depthEl = document.getElementById('tm-discover-depth');
			var maxDepth = depthEl ? parseInt(depthEl.value, 10) : DISCOVER_DEPTH_DEFAULT;

			if (!isFinite(maxDepth)) {
				maxDepth = DISCOVER_DEPTH_DEFAULT;
			}

			// 收敛到 1~10：0 层等于不发现，超过 10 层对 SNMP 扫描来说太贵了
			maxDepth = Math.min(DISCOVER_DEPTH_MAX, Math.max(DISCOVER_DEPTH_MIN, maxDepth));

			if (depthEl) {
				depthEl.value = String(maxDepth);
			}

			var includeUnmanaged = discoverOption('tm-discover-unmanaged');

			var hostMap = {};
			discover.hosts.forEach(function (host) {
				hostMap[host.hostid] = host;
			});

			discover.running = true;
			discover.finished = false;
			discover.nodes = {};
			discover.links = {};
			discover.scanned = {};

			clear(document.getElementById('tm-discover-log'));

			seeds.forEach(function (hostid) {
				var host = hostMap[hostid] || {hostid: hostid, name: hostid, ip: ''};

				addDiscoverNode('h:' + hostid, {
					hostid: hostid,
					name: host.name,
					ip: host.ip || '',
					depth: 0,
					kind: 'switch'
				});
			});

			var queue = seeds.map(function (hostid) {
				return {hostid: hostid, depth: 0};
			});
			var queued = {};

			seeds.forEach(function (hostid) {
				queued[hostid] = true;
			});

			appendDiscoverLog(T('开始发现：') + seeds.length + T(' 台起点，深度 ') + maxDepth + T('，协议 ')
				+ protocols.join(' / '));

			setDiscoverBusy(true, T('发现中…'));

			// 串行小批量请求：每批 3 台，避免单次请求里的 SNMP 扫描把 fastcgi 超时顶爆
			(async function pump() {
				while (queue.length) {
					var batch = queue.splice(0, 3);

					appendDiscoverLog(T('扫描 ') + batch.map(function (item) {
						var host = hostMap[item.hostid];

						return host ? host.name : item.hostid;
					}).join(T('、')) + T(' …'));

					var data = null;

					try {
						// 后端 SNMP 万一卡在某台设备上，请求会长时间不返回；加一道前端超时，
						// 至少让发现对话框报「扫描失败」而不是永远停在「扫描 xxx …」。
						// 后端自己的整批预算是 20s，45s 只在真卡住时才会触发。
						data = await withTimeout(api('discover', {
							hostids: batch.map(function (item) { return item.hostid; }).join(','),
							protocols: protocols.join(','),
							timeout: '1200'
						}), 45000);
					}
					catch (e) {
						appendDiscoverLog(T('扫描失败：') + e.message, 'error');
					}

					if (data) {
						(data.hosts || []).forEach(function (res) {
							collectDiscoverResult(res, hostMap, includeUnmanaged, maxDepth, queue, queued);
						});
					}

					batch.forEach(function (item) {
						discover.scanned[item.hostid] = true;
					});
				}

				discover.running = false;
				discover.finished = true;
				finishDiscover();
			})();
		}

		function collectDiscoverResult(res, hostMap, includeUnmanaged, maxDepth, queue, queued) {
			var sourceKey = 'h:' + res.hostid;
			var source = discover.nodes[sourceKey];

			if (source && res.sysname && !source.sysname) {
				source.sysname = res.sysname;
			}

			if (!res.ok) {
				appendDiscoverLog('· ' + (res.name || res.hostid) + T('：') + (res.error || T('读取失败')), 'warn');

				return;
			}

			var sourceDepth = source ? source.depth : 0;

			if (!res.neighbors.length) {
				appendDiscoverLog('· ' + (res.name || res.hostid) + T('：没有 LLDP / CDP / EDP 邻居（设备可能没开邻居发现协议）'), 'warn');
			}

			res.neighbors.forEach(function (nb) {
				var targetKey;

				if (nb.hostid) {
					targetKey = 'h:' + nb.hostid;
				}
				else if (includeUnmanaged) {
					// 终端 / AP / 打印机常常只有 chassisId（MAC），sysName 为空，
					// 用 chassis 兜底做节点键，否则所有无名邻居会挤成同一个「n:」节点。
					targetKey = 'n:' + String(nb.sysname || nb.chassis || nb.ip || '').toLowerCase();
				}
				else {
					return;
				}

				if (!targetKey || targetKey === 'n:') {
					return;
				}

				if (!discover.nodes[targetKey]) {
					var known = nb.hostid ? hostMap[nb.hostid] : null;

					addDiscoverNode(targetKey, {
						hostid: nb.hostid || '',
						name: nb.name || (known ? known.name : '') || nb.sysname || nb.chassis || nb.ip || T('未命名设备'),
						ip: nb.ip || (known ? known.ip : ''),
						depth: sourceDepth + 1,
						kind: nb.kind || 'switch'
					});
				}

				addDiscoverLink(sourceKey, targetKey, nb);

				// 已纳管的邻居才有 SNMP 可读，继续往下一层展开
				if (nb.hostid && !discover.scanned[nb.hostid] && !queued[nb.hostid]
						&& sourceDepth + 1 < maxDepth) {
					queued[nb.hostid] = true;
					queue.push({hostid: nb.hostid, depth: sourceDepth + 1});
				}
			});
		}

		function addDiscoverNode(key, node) {
			if (!discover.nodes[key]) {
				discover.nodes[key] = node;
			}
		}

		// 端口名归一化键：与后端 TopomapData::ifPortKey 保持一致
		// （Gi1/0/11 ↔ ge1_0_11 ↔ GigabitEthernet1/0/11 归一后都是 ge1011）。
		function portKey(s) {
			s = String(s || '').split(/\s*\(/)[0];
			s = s.toLowerCase();
			s = s.replace(/ten[-_ ]?gigabitethernet/, 'te');
			s = s.replace(/gigabitethernet/, 'ge');
			s = s.replace(/fastethernet/, 'fa');
			s = s.replace(/gi(?=[0-9/])/, 'ge');

			return s.replace(/[\s_\-:./]/g, '');
		}

		function addDiscoverLink(source, target, nb) {
			if (source === target) {
				return;
			}

			// 反向重复一律跳过（aiops 踩过同一个坑之后的结论）：
			// 同一对设备，只要已经按「发现方向」建过链路，反向扫到的那条记录就整个丢掉。
			//
			// 为什么不能只靠下面的「端口对」去重：两端对本地端口名的解析可能对不上
			// （设备被限流时 lldpLocPortId 回的表不完整，端口名会解析成另一个口），于是
			// 汇聚报 ge0_0_23 ↔ 接入 Gi0/0_48、接入反扫报 ge0_0_32 ↔ 汇聚 Gi0/0_23，
			// 端口对不同 → 去重失效 → 多出一条重复链路，还把接入侧那个**不相干端口**
			// （GigabitEthernet0/0/32）的「处于关闭状态」触发器错绑到这条真实连线上。
			var reversed = false;

			Object.keys(discover.links).forEach(function (key) {
				var item = discover.links[key];

				if (item.source === target && item.target === source) {
					reversed = true;
				}
			});

			if (reversed) {
				return;
			}

			// 去重键要带「两端端口」：链路聚合的多个成员口是同一对设备之间的多条线，
			// 不能按设备对去重；同方向再扫到同一对端口时按「归一化端口对」的无序键合并。
			// 归一化是必须的：核心交换机报 Comware 原始数字、对端报 Gi1/0/11，
			// 不归一化就合不掉，图上只剩视觉重叠的多条重复线。
			var endA = source + '#' + portKey(nb.local_port || '');
			var endB = target + '#' + portKey(nb.port_id || '');
			var pair = endA < endB ? endA + '|' + endB : endB + '|' + endA;

			if (discover.links[pair]) {
				return;
			}

			discover.links[pair] = {
				source: source,
				target: target,
				source_port: nb.local_port || '',
				target_port: nb.port_id || '',
				protocol: nb.protocol || '',
				// 新建时按默认「源端优先」匹配触发器；用户可在链接属性里改
				trigger_source: 0
			};
		}

		function finishDiscover() {
			var devices = Object.keys(discover.nodes).length;
			var links = Object.keys(discover.links).length;

			appendDiscoverLog(T('发现完成：') + devices + T(' 台设备、') + links + T(' 条链路'), devices ? 'ok' : 'warn');

			if (devices) {
				var matched = 0;

				Object.keys(discover.nodes).forEach(function (key) {
					if (discover.nodes[key].hostid) {
						matched++;
					}
				});

				appendDiscoverLog(T('其中 ') + matched + T(' 台已在 Zabbix 里，') + (devices - matched) + T(' 台为未纳管设备。'));
			}

			var nameRow = document.getElementById('tm-discover-name-row');

			if (nameRow) {
				setHidden(nameRow, isRediscover() || devices === 0);
			}

			var buttons = elDialogFoot.querySelectorAll('button');

			if (buttons[0]) {
				buttons[0].disabled = false;
			}

			if (buttons[1]) {
				buttons[1].disabled = devices === 0;
				buttons[1].textContent = isRediscover() ? T('更新拓扑图') : T('生成拓扑图');
			}

			document.querySelectorAll('#tm-dialog-body input, #tm-dialog-body select').forEach(function (el) {
				el.disabled = false;
			});
		}

		// 读发现页的关键词文本框：一行一个，去空行、去首尾空格、去重。
		function discoverKeywords() {
			var el = document.getElementById('tm-discover-keywords');
			var seen = {};

			return (el ? el.value : '').split(/\r?\n/).map(function (line) {
				return line.trim();
			}).filter(function (line) {
				if (line === '' || seen[line.toLowerCase()]) {
					return false;
				}

				seen[line.toLowerCase()] = true;

				return true;
			});
		}

		// 把当前发现结果序列化成后端要的 payload（新建图与重新发现共用）
		function discoverPayload() {
			var nodes = [];

			Object.keys(discover.nodes).forEach(function (key) {
				var node = discover.nodes[key];

				nodes.push({
					key: key,
					hostid: node.hostid || '',
					name: node.name,
					ip: node.ip || '',
					kind: node.kind || 'switch',
					depth: node.depth || 0
				});
			});

			// 按层级排序，后端按顺序在同层里竖排
			nodes.sort(function (a, b) {
				return a.depth - b.depth;
			});

			var links = Object.keys(discover.links).map(function (key) {
				return discover.links[key];
			});

			// 自动关联触发器关键词（发现页填的，一行一个）随生成请求一起提交，
			// 后端落库后链接属性里改「触发器来源」重匹配也用同一份。
			var payload = {
				nodes: nodes,
				links: links,
				keywords: discoverKeywords()
			};

			if (isRediscover()) {
				var layoutEl = document.getElementById('tm-rediscover-layout');
				var pruneEl = document.getElementById('tm-rediscover-prune');

				payload.layout = layoutEl && layoutEl.value === 'keep' ? 'keep' : 'tree';
				payload.prune_links = pruneEl && pruneEl.value === '1' ? 1 : 0;
			}

			return payload;
		}

		function saveDiscover() {
			if (isRediscover()) {
				applyRediscover();

				return;
			}

			var nameInput = document.getElementById('tm-discover-name');
			var name = nameInput && nameInput.value.trim() !== '' ? nameInput.value.trim() : defaultDiscoverName();

			setDiscoverBusy(true, T('生成中…'));

			api('discoverSave', {
				name: name,
				payload: JSON.stringify(discoverPayload())
			}).then(function (data) {
				closeDialog();

				return loadList().then(function () {
					var extra = data.triggers ? T('、关联 ') + data.triggers + T(' 个触发器') : '';

					msg(elListMsg, T('已生成「') + data.name + T('」：') + data.devices + T(' 台设备、') + data.links + T(' 条链路') + extra, 'ok');

					return openMap(data.sysmapid, false);
				});
			}).catch(function (e) {
				appendDiscoverLog(T('生成失败：') + e.message, 'error');
				setDiscoverBusy(false, T('生成拓扑图'));
			});
		}

		// 重新发现第二步：把本次结果交给后端，就地并入当前图
		function applyRediscover() {
			if (!discover || !state.map) {
				return;
			}

			var sysmapid = String(state.map.sysmapid);

			setDiscoverBusy(true, T('更新中…'));

			api('rediscover', {
				sysmapid: sysmapid,
				payload: JSON.stringify(discoverPayload())
			}).then(function (data) {
				closeDialog();

				var removed = data.removed_links ? T('，移除 ') + data.removed_links + T(' 条旧连线') : '';

				return openMap(sysmapid, false, true).then(function () {
					setHint(T('重新发现完成：新增 ') + data.added_devices + T(' 台设备、') + data.added_links
						+ T(' 条链路，保留 ') + data.kept_links + T(' 条链路配置') + removed);
					msg(elListMsg, T('已更新拓扑图：新增 ') + data.added_devices + T(' 台设备、')
						+ data.added_links + T(' 条链路') + removed, 'ok');
				});
			}).catch(function (e) {
				appendDiscoverLog(T('更新失败：') + e.message, 'error');
				setDiscoverBusy(false, T('更新拓扑图'));
			});
		}

		// ---------------------------------------------------------------
		// 视图状态记忆（对齐原生 map.view / sysmaps.php）
		// ---------------------------------------------------------------

		// 原生：打开 map.view 时把 sysmapid 写进用户 profile（web.maps.sysmapid），
		// 打开列表页 sysmaps.php 时把它删掉；左侧菜单固定指 action=map.view，
		// 于是「从菜单点回来」总是回到上次的视图。
		// 本模块列表与画布同属一个 action，所以只在打开/返回时各同步一次。
		function rememberMap(sysmapid) {
			// 失败保持静默：最坏结果只是「下次点菜单回不到这张图」，不该弹错打断用户。
			api('remember', {sysmapid: sysmapid || ''}).catch(function () {});
		}

		// 地址栏也跟状态走，浏览器后退才能像原生一样从画布退回列表。
		// 只动 sysmapid 参数，其他查询参数（action 等）原样保留。
		function viewUrl(sysmapid) {
			var params = new URLSearchParams(window.location.search);

			if (sysmapid) {
				params.set('sysmapid', sysmapid);
			}
			else {
				params.delete('sysmapid');
			}

			return window.location.pathname + '?' + params.toString();
		}

		// mode: 'push'（用户动作，默认）/ 'replace'（页面初始状态）/ 'none'（响应后退，别再记一笔）
		function syncViewUrl(sysmapid, mode) {
			if (mode === 'none' || !window.history || !window.history.pushState) {
				return;
			}

			var url = viewUrl(sysmapid);

			if (url === window.location.pathname + window.location.search) {
				return;
			}

			try {
				history[mode === 'replace' ? 'replaceState' : 'pushState'](
					{tm_sysmapid: sysmapid || ''}, '', url
				);
			}
			catch (e) {
				// file:// 等特殊环境下 pushState 会抛 SecurityError。
				// 地址栏同步只是锦上添花，绝不能因此打断「打开拓扑图」本身。
			}
		}

		// ---------------------------------------------------------------
		// 打开 / 关闭画布
		// ---------------------------------------------------------------

		function openMap(sysmapid, editing, keepView, urlMode) {
			setHint(T('加载中…'));

			return api('get', {sysmapid: sysmapid}).then(function (data) {
				state.map = normalizeMap(data.map);
				// 已绑定的触发器只有 triggerid，名字取回来给「链接」对话框的 chip 用
				var triggerids = [];
				state.map.links.forEach(function (link) {
					(link.linktriggers || []).forEach(function (lt) {
						if (lt && lt.triggerid) {
							triggerids.push(String(lt.triggerid));
						}
					});
				});
				resolveTriggerNames(triggerids);
				state.canEdit = !!data.can_edit;
				state.selected = null;
				state.dirty = false;
				state.removeMode = null;
				state.linkMode = false;
				state.linkFirst = null;
				state.bendLink = null;

				if (!keepView) {
					state.zoom = 1;
					state.panX = 0;
					state.panY = 0;
				}

				setHidden(elListView, true);
				setHidden(elCanvasView, false);
				// 画布页右上角不该再出现「创建拓扑图」（那是列表页的动作）
				setHidden(elHeaderControls, true);
				elCanvasTitle.textContent = state.map.name;

				// 记住「现在看的是哪张图」并同步地址栏
				rememberMap(state.map.sysmapid);
				syncViewUrl(String(state.map.sysmapid), urlMode || 'push');

				setEditing(!!editing && state.canEdit);
				renderCanvas();

				// 一进来就按内容收紧画布：以前存的大画布（比如 3000×3000）不会再让整屏空白。
				// 只在内存里算，落库要等用户点保存。
				autoSizeCanvas();

				if (!keepView) {
					// 进入时按 100% 显示（原生 map.view 也是 100%），不要自动缩放
					resetView();
				}

				return loadIcons().then(function () {
					renderCanvas();
					applyView();
					startAutoRefresh();
				});
			}).catch(function (e) {
				setHint('');
				msg(elListMsg, T('打开失败：') + e.message, 'error');
			});
		}

		// ---------------------------------------------------------------
		// 画布自动刷新：链路触发器报警态 / 主机状态 / 带宽流量值都要跟着库走，
		// 否则页面开着的时候「测试不会变红、指标一直是一个值」。
		// 编辑态不刷（有未保存修改），回到列表也停。
		// ---------------------------------------------------------------

		var autoRefreshTimer = null;
		var AUTO_REFRESH_MS = 60000;

		function stopAutoRefresh() {
			if (autoRefreshTimer) {
				clearInterval(autoRefreshTimer);
				autoRefreshTimer = null;
			}
		}

		function startAutoRefresh() {
			stopAutoRefresh();
			autoRefreshTimer = setInterval(refreshMapState, AUTO_REFRESH_MS);
		}

		function refreshMapState() {
			if (!state.map || state.editing || document.hidden || elCanvasView.hidden) {
				return;
			}

			var sysmapid = String(state.map.sysmapid);
			var selected = state.selected;

			api('get', {sysmapid: sysmapid}).then(function (data) {
				if (!state.map || String(state.map.sysmapid) !== sysmapid || state.editing) {
					return;
				}

				// 只换数据，不动缩放/平移/选中，避免每分钟把用户视野重置一次
				state.map = normalizeMap(data.map);
				state.canEdit = !!data.can_edit;
				state.selected = selected;
				state.dirty = false;
				// 增量刷新：保留元素 DOM（尤其 <img>），只更新状态 / 标签 / 高亮 / 连线，
				// 整层重建会让所有主机图标闪一下，而 Zabbix 自带拓扑图是原地更新。
				refreshCanvas();
			}).catch(function () {
				// 自动刷新失败不打扰用户，下一轮再试
			});
		}

		/**
		 * 自动刷新用的增量重绘：复用已有 .tm-el 节点（图标不重建），
		 * 只改位置 / 状态类名 / 标签，再重画高亮层与连线（纯 SVG，没有图片加载）。
		 */
		function refreshCanvas() {
			var map = state.map;

			if (!map) {
				return;
			}

			var oldNodes = elStage.querySelectorAll('.tm-el');
			var boxes = {};
			var seen = {};

			for (var i = 0; i < oldNodes.length; i++) {
				boxes[oldNodes[i].getAttribute('data-id')] = oldNodes[i];
			}

			map.selements.forEach(function (se) {
				var id = String(se.selementid);

				seen[id] = true;

				if (boxes[id]) {
					paintElementState(se, boxes[id]);
				}
				else {
					elStage.appendChild(renderElement(se));
				}
			});

			Object.keys(boxes).forEach(function (id) {
				if (!seen[id] && boxes[id].parentNode) {
					boxes[id].parentNode.removeChild(boxes[id]);
				}
			});

			var hint = elStage.querySelector('.tm-blank-hint');

			if (map.selements.length) {
				if (hint) {
					elStage.removeChild(hint);
				}
			}
			else if (!hint) {
				elStage.appendChild(make('div', 'tm-blank-hint', T('这张拓扑图还没有元素')));
			}

			drawShapes(map);
			drawHighlights(map);
			drawLinks(map);
			resizeStage();
		}

		function backToList() {
			if (state.editing && state.dirty) {
				confirmDialog(T('放弃未保存的修改？'), T('当前拓扑图有未保存的修改，返回列表会丢失这些修改。'), T('放弃修改')).then(function (ok) {
					if (ok) {
						doBackToList();
					}
				});

				return;
			}

			doBackToList();
		}

		function doBackToList(urlMode) {
			stopAutoRefresh();
			state.map = null;
			state.editing = false;
			state.dirty = false;
			clearModes();

			// 回到列表 = 忘掉上次的图 + 地址栏去掉 sysmapid（对齐原生 sysmaps.php 的 CProfile::delete）
			rememberMap('');
			syncViewUrl('', urlMode || 'push');

			// 只清 stage 的直接子节点（元素 div 与空白提示），SVG 留着复用。
			// 注意不能遍历 querySelectorAll('*')：img/span 是 div 的后代，removeChild 会抛 NotFoundError。
			var node = elStage.firstChild;

			while (node) {
				var next = node.nextSibling;

				if (node !== elSvg) {
					elStage.removeChild(node);
				}

				node = next;
			}

			clear(elSvg);
			setHidden(elCanvasView, true);
			setHidden(elListView, false);
			setHidden(elHeaderControls, false);
			loadList();
		}

		function setEditing(flag) {
			state.editing = !!flag;
			root.classList.toggle('tm-editing', state.editing);
			setHidden(elEditbar, !state.editing);
			setHidden(elEditBtn, state.editing);
			setHidden(elRediscoverBtn, state.editing);
			setHidden(elSaveBtn, !state.editing);
			setHidden(elExitBtn, !state.editing);
			elEditBtn.disabled = !state.canEdit;
			elRediscoverBtn.disabled = !state.canEdit;

			if (!state.editing) {
				clearModes();
			}

			setHint(state.editing ? '' : T('预览模式'));

			// 编辑工具条出现/消失会改变画布顶部位置，重新算一次可用高度
			layoutCanvasWrap();
			// 画布高度变了，可能高于当前画布尺寸（退出编辑时），顺手补满
			ensureCanvasFitsViewport();
		}

		function clearModes() {
			state.removeMode = null;
			state.linkMode = false;
			state.linkFirst = null;
			state.bendLink = null;
			state.shapeSel = null;
			syncModeButtons();
			setHint(state.editing ? '' : T('预览模式'));
		}

		// 移除 / 链接模式在工具条上高亮，避免「按了不知道在哪个模式」
		function syncModeButtons() {
			var buttons = elEditbar.querySelectorAll('button[data-group]');

			for (var i = 0; i < buttons.length; i++) {
				var group = buttons[i].getAttribute('data-group');
				var op = buttons[i].getAttribute('data-op');
				var active = (op === 'remove' && state.removeMode === group)
					|| (op === 'add' && group === 'link' && state.linkMode);

				buttons[i].classList.toggle('tm-btn-active', active);
			}
		}

		function markDirty() {
			state.dirty = true;
			// 折点编辑中还停在提示条上，用户才知道怎么加点 / 删点
			setHint(state.bendLink ? BEND_HINT : T('有未保存的修改'));
		}

		// ---------------------------------------------------------------
		// 画布渲染
		// ---------------------------------------------------------------

		function renderCanvas() {
			var map = state.map;

			if (!map) {
				return;
			}

			var wanted = {};

			map.selements.forEach(function (se) {
				wanted[iconForElement(se)] = true;
			});

			preloadIcons(Object.keys(wanted)).then(function () {
				if (state.map === map) {
					paintCanvas();
				}
			});

			paintCanvas();
			applyView();
		}

		function paintCanvas() {
			var map = state.map;

			if (!map) {
				return;
			}

			elStage.style.width = map.width + 'px';
			elStage.style.height = map.height + 'px';
			elSvg.setAttribute('width', String(map.width));
			elSvg.setAttribute('height', String(map.height));
			elSvg.setAttribute('viewBox', '0 0 ' + map.width + ' ' + map.height);

			clear(elSvg);

			var oldEls = elStage.querySelectorAll('.tm-el');

			for (var i = 0; i < oldEls.length; i++) {
				elStage.removeChild(oldEls[i]);
			}

			drawShapes(map);
			// 高亮层要在连线下面（原生分层：shapes → highlights → links → elements），
			// 这样连线会压过高亮圆、直接画到主机图标上，而不是停在圆边上。
			drawHighlights(map);
			drawLinks(map);
			// 控制点画在最上层，避免被连线压住
			drawShapeHandles();

			map.selements.forEach(function (se) {
				elStage.appendChild(renderElement(se));
			});

			if (!map.selements.length) {
				var hint = make('div', 'tm-blank-hint', state.editing ? T('点击「地图元素: 添加」开始布置拓扑图') : T('这张拓扑图还没有元素'));
				elStage.appendChild(hint);
			}
		}

		// ---------------------------------------------------------------
		// 形：渲染（与原生 SVGMapShape 同一套语义）
		// ---------------------------------------------------------------

		function svgNode(tag) {
			return document.createElementNS('http://www.w3.org/2000/svg', tag);
		}

		// 原生会把点线/虚线的 dash 数组按线宽放大（线宽 > 1 时才放大）
		function shapeDashArray(shape) {
			var raw = SHAPE_BORDER_DASHES[shape.border_type] || '';
			var width = Math.max(0, num(shape.border_width, 0));

			if (raw === '' || raw === 'none' || width <= 1) {
				return raw;
			}

			return raw.split(',').map(function (part) {
				var value = parseInt(part, 10);

				return String(value === 1 && width > 2 ? 1 : value * width);
			}).join(',');
		}

		function applyShapePaint(node, shape) {
			node.setAttribute('fill', shape.type === 2 || !shape.background_color
				? 'none' : '#' + shape.background_color);

			if (shape.border_type === 0) {
				node.setAttribute('stroke', 'none');
				node.setAttribute('stroke-width', '0');

				return;
			}

			var width = Math.max(1, num(shape.border_width, 1));
			var dash = shapeDashArray(shape);

			node.setAttribute('stroke', '#' + shape.border_color);
			node.setAttribute('stroke-width', String(width));

			if (dash !== '' && dash !== 'none') {
				node.setAttribute('stroke-dasharray', dash);

				// 点线在粗描边下让笔画圆头，和原生一致
				if (shape.border_type === 2 && width > 2) {
					node.setAttribute('stroke-linecap', 'round');
				}
			}
		}

		// 几何：矩形/椭圆按 x,y,width,height 画；线把 width,height 当终点坐标（原生语义）
		function applyShapeGeometry(node, shape) {
			if (shape.type === 2) {
				node.setAttribute('x1', String(shape.x));
				node.setAttribute('y1', String(shape.y));
				node.setAttribute('x2', String(shape.width));
				node.setAttribute('y2', String(shape.height));
			}
			else if (shape.type === 1) {
				node.setAttribute('cx', String(shape.x + shape.width / 2));
				node.setAttribute('cy', String(shape.y + shape.height / 2));
				node.setAttribute('rx', String(shape.width / 2));
				node.setAttribute('ry', String(shape.height / 2));
			}
			else {
				node.setAttribute('x', String(shape.x));
				node.setAttribute('y', String(shape.y));
				node.setAttribute('width', String(shape.width));
				node.setAttribute('height', String(shape.height));
			}
		}

		function shapeTag(shape) {
			return shape.type === 2 ? 'line' : (shape.type === 1 ? 'ellipse' : 'rect');
		}

		// 文字：对齐方式、字体、内边距都按原生（内边距 5px），并裁剪在形内部
		function drawShapeText(shape, clipId) {
			// 原生对线不画文字
			if (shape.type === 2 || !shape.text) {
				return;
			}

			var lines = String(shape.text).split(/\r?\n/);
			var fontSize = Math.max(1, num(shape.font_size, 11));
			var lineH = fontSize * 1.2;
			var total = lines.length * lineH;
			var x = shape.x + shape.width / 2;
			var anchor = 'middle';

			if (shape.text_halign === 1) {
				x = shape.x + SHAPE_TEXT_PADDING;
				anchor = 'start';
			}
			else if (shape.text_halign === 2) {
				x = shape.x + shape.width - SHAPE_TEXT_PADDING;
				anchor = 'end';
			}

			var top;

			if (shape.text_valign === 1) {
				top = shape.y + SHAPE_TEXT_PADDING;
			}
			else if (shape.text_valign === 2) {
				top = shape.y + shape.height - SHAPE_TEXT_PADDING - total;
			}
			else {
				top = shape.y + shape.height / 2 - total / 2;
			}

			var text = svgNode('text');

			text.setAttribute('class', 'tm-shape-text');
			text.setAttribute('fill', '#' + shape.font_color);
			text.setAttribute('font-family', SHAPE_FONT_FAMILIES[num(shape.font, 9)] || SHAPE_FONT_FAMILIES[9]);
			text.setAttribute('font-size', String(fontSize));
			text.setAttribute('text-anchor', anchor);
			text.setAttribute('y', String(top + fontSize));

			if (clipId) {
				text.setAttribute('clip-path', 'url(#' + clipId + ')');
			}

			lines.forEach(function (line, index) {
				var tspan = svgNode('tspan');

				tspan.setAttribute('x', String(x));
				tspan.setAttribute('dy', index === 0 ? '0' : String(lineH));
				tspan.textContent = line;
				text.appendChild(tspan);
			});

			elSvg.appendChild(text);
		}

		function drawShapes(map) {
			// 幂等：先清掉上一轮的形（含文字与裁剪路径）。这样拖拽中也能单独重画形，
			// 不必走 paintCanvas() 重建元素 DOM（否则拖拽中的节点引用会失效）。
			var olds = elSvg.querySelectorAll('.tm-shape, .tm-shape-text, .tm-shape-clip');

			for (var k = 0; k < olds.length; k++) {
				elSvg.removeChild(olds[k]);
			}

			map.shapes.forEach(function (shape, index) {
				var node = svgNode(shapeTag(shape));

				applyShapeGeometry(node, shape);
				applyShapePaint(node, shape);
				node.setAttribute('class', 'tm-shape');
				node.setAttribute('data-shapeid', String(shape.sysmap_shapeid || shape._tmpid || ''));
				node.style.cursor = state.editing ? 'move' : 'default';

				if (state.editing) {
					node.classList.toggle('tm-removable', state.removeMode === 'shape');
				}

				node.addEventListener('mousedown', function (e) {
					if (!state.editing || state.removeMode || state.linkMode) {
						return;
					}

					e.stopPropagation();
					selectShape(shape);
					startShapeDrag(shape, e);
				});

				// 原生：单击选中出控制点，双击打开属性窗口
				node.addEventListener('click', function (e) {
					if (!state.editing) {
						return;
					}

					e.stopPropagation();

					if (state.removeMode === 'shape') {
						removeShape(shape);
						return;
					}

					if (!state.removeMode && !state.linkMode) {
						selectShape(shape);
					}
				});

				node.addEventListener('dblclick', function (e) {
					if (!state.editing || state.removeMode || state.linkMode) {
						return;
					}

					e.stopPropagation();
					selectShape(shape);
					openShapeDialog(shape, false);
				});

				elSvg.appendChild(node);

				var hasText = shape.type !== 2 && !!shape.text;
				var clipId = '';

				if (hasText) {
					clipId = 'tm-shape-clip-' + String(shape.sysmap_shapeid || shape._tmpid || index);

					var clip = svgNode('clipPath');
					var clipShape = svgNode(shapeTag(shape));

					clip.setAttribute('id', clipId);
					clip.setAttribute('class', 'tm-shape-clip');
					applyShapeGeometry(clipShape, shape);
					clip.appendChild(clipShape);
					elSvg.appendChild(clip);
				}

				drawShapeText(shape, clipId);
			});
		}

		function selectShape(shape) {
			if (state.shapeSel === shape) {
				return;
			}

			state.shapeSel = shape;
			drawShapeHandles();
		}

		// 选中控制点：矩形/椭圆 8 个（四角 + 四边中点），线 2 个端点 —— 与原生一致
		function drawShapeHandles() {
			var olds = elSvg.querySelectorAll('.tm-shape-handle');

			for (var k = 0; k < olds.length; k++) {
				elSvg.removeChild(olds[k]);
			}

			var shape = state.shapeSel;

			if (!shape || !state.editing || state.removeMode || state.linkMode) {
				return;
			}

			var handles = [];

			if (shape.type === 2) {
				// 线的 x,y 与 width,height 都是绝对坐标（起点 / 终点）
				handles = [
					{x: shape.x, y: shape.y, dir: 'p1', cursor: 'move'},
					{x: shape.width, y: shape.height, dir: 'p2', cursor: 'move'}
				];
			}
			else {
				var x1 = shape.x;
				var y1 = shape.y;
				var x2 = shape.x + shape.width;
				var y2 = shape.y + shape.height;
				var mx = (x1 + x2) / 2;
				var my = (y1 + y2) / 2;

				handles = [
					{x: x1, y: y1, dir: 'nw', cursor: 'nwse-resize'},
					{x: mx, y: y1, dir: 'n', cursor: 'ns-resize'},
					{x: x2, y: y1, dir: 'ne', cursor: 'nesw-resize'},
					{x: x2, y: my, dir: 'e', cursor: 'ew-resize'},
					{x: x2, y: y2, dir: 'se', cursor: 'nwse-resize'},
					{x: mx, y: y2, dir: 's', cursor: 'ns-resize'},
					{x: x1, y: y2, dir: 'sw', cursor: 'nesw-resize'},
					{x: x1, y: my, dir: 'w', cursor: 'ew-resize'}
				];
			}

			handles.forEach(function (handle) {
				var dot = svgNode('circle');

				dot.setAttribute('class', 'tm-shape-handle');
				dot.setAttribute('data-dir', handle.dir);
				dot.setAttribute('cx', String(handle.x));
				dot.setAttribute('cy', String(handle.y));
				dot.setAttribute('r', String(SHAPE_HANDLE_RADIUS));
				dot.style.cursor = handle.cursor;

				dot.addEventListener('mousedown', function (e) {
					e.stopPropagation();
					e.preventDefault();
					startShapeResize(shape, handle.dir, e);
				});

				elSvg.appendChild(dot);
			});
		}

		function startShapeResize(shape, dir, e) {
			var startX = e.clientX;
			var startY = e.clientY;
			var start = pointerToMap(e);
			var orig = {x: shape.x, y: shape.y, width: shape.width, height: shape.height};
			var moved = false;
			var isLine = shape.type === 2;

			function move(ev) {
				if (Math.abs(ev.clientX - startX) > 1 || Math.abs(ev.clientY - startY) > 1) {
					moved = true;
				}

				var pt = pointerToMap(ev);
				var dx = Math.round(pt.x - start.x);
				var dy = Math.round(pt.y - start.y);

				if (isLine) {
					if (dir === 'p1') {
						shape.x = Math.max(0, orig.x + dx);
						shape.y = Math.max(0, orig.y + dy);
					}
					else {
						shape.width = Math.max(0, orig.width + dx);
						shape.height = Math.max(0, orig.height + dy);
					}
				}
				else {
					var x1 = orig.x;
					var y1 = orig.y;
					var x2 = orig.x + orig.width;
					var y2 = orig.y + orig.height;

					if (dir.indexOf('w') !== -1) {
						x1 = Math.min(Math.max(0, orig.x + dx), x2 - 1);
					}
					else if (dir.indexOf('e') !== -1) {
						x2 = Math.max(x1 + 1, orig.x + orig.width + dx);
					}

					if (dir.indexOf('n') !== -1) {
						y1 = Math.min(Math.max(0, orig.y + dy), y2 - 1);
					}
					else if (dir.indexOf('s') !== -1) {
						y2 = Math.max(y1 + 1, orig.y + orig.height + dy);
					}

					shape.x = x1;
					shape.y = y1;
					shape.width = Math.max(1, x2 - x1);
					shape.height = Math.max(1, y2 - y1);
				}

				var right = isLine ? Math.max(shape.x, shape.width) : shape.x + shape.width;
				var bottom = isLine ? Math.max(shape.y, shape.height) : shape.y + shape.height;

				growCanvasTo(right + CANVAS_PAD, bottom + CANVAS_PAD);
				repaintSoon();
			}

			function up() {
				document.removeEventListener('mousemove', move);
				document.removeEventListener('mouseup', up);

				if (moved) {
					markDirty();
				}
			}

			document.addEventListener('mousemove', move);
			document.addEventListener('mouseup', up);
		}

		function elementCenter(se) {
			var size = iconSize(iconForElement(se));

			return {x: se.x + size.w / 2, y: se.y + size.h / 2, w: size.w, h: size.h};
		}

		// ---------------------------------------------------------------
		// 树形布局的连线锚点
		// ---------------------------------------------------------------

		// 连线末端与图标 / 文字块之间留的间隙；直角走线从父锚点先竖直走的「主干」长度
		var LINK_EDGE_GAP = 6;
		var LINK_TRUNK_GAP = 30;

		// 图标下方标签文字块的高度：名称行 + 状态行（正常 / 问题 / 已停用 / 在维护中），
		// 每行 16px、距图标底 3px，和 .tm-el-label 的样式一致。
		// 标签不在下方（左侧 / 右侧 / 顶部）时不占图标下方的空间，按 0 算。
		function elementBottomLabelHeight(se) {
			var loc = se.label_location === -1 && state.map ? state.map.label_location : se.label_location;

			if (loc !== 0 && loc !== -1) {
				return 0;
			}

			var name = elementLabel(se);
			var nameCount = name ? String(name).split('\n').length : 0;
			var count = nameCount + (elementStatusLines(se) || []).length;

			return count ? 3 + count * 16 : 0;
		}

		// 「同一层」判定：两个元素中心在垂直方向相差不到 3/4 个图标高度。
		// 同层的左右相邻主机仍按中心连，不能被拉到上下锚点上。
		function sameRowElements(a, b) {
			var sa = iconSize(iconForElement(a));
			var sb = iconSize(iconForElement(b));

			return Math.abs((a.y + sa.h / 2) - (b.y + sb.h / 2)) < Math.max(sa.h, sb.h) * 0.75;
		}

		/*
		 * 连线两端锚点。上下两层的连线用树形锚点：
		 *   父（上面那台）＝ 图标 + 名称 + 「正常 / 问题」文字块的下沿，再留 6px；
		 *   子（下面那台）＝ 图标顶边上方 6px。
		 * 同层或斜向相邻的连线仍按元素中心连。
		 * 返回的 pa 永远是「父」这一端，pb 是「子」这一端（线本身没有箭头，方向无所谓）。
		 */
		function elementAnchors(a, b) {
			var ca = elementCenter(a);
			var cb = elementCenter(b);

			if (sameRowElements(a, b)) {
				return {pa: {x: ca.x, y: ca.y}, pb: {x: cb.x, y: cb.y}, vertical: false};
			}

			var sa = iconSize(iconForElement(a));
			var sb = iconSize(iconForElement(b));

			if (b.y > a.y) {
				return {
					pa: {x: ca.x, y: a.y + sa.h + elementBottomLabelHeight(a) + LINK_EDGE_GAP},
					pb: {x: cb.x, y: b.y - LINK_EDGE_GAP},
					vertical: true,
					parent: a
				};
			}

			return {
				pa: {x: cb.x, y: b.y + sb.h + elementBottomLabelHeight(b) + LINK_EDGE_GAP},
				pb: {x: ca.x, y: a.y - LINK_EDGE_GAP},
				vertical: true,
				parent: b
			};
		}

		/*
		 * 链路聚合的成员偏移：整束都落在「子节点那一侧」，不再像以前那样对称偏移。
		 * 对称偏移时靠另一侧的那条会横穿同一父节点下「正下方那台主机」的竖线，视觉上就是交叉。
		 */
		function treeMemberOffset(index, total, dx) {
			if (total <= 1) {
				return 0;
			}

			if (Math.abs(dx) < 1) {
				return (index - (total - 1) / 2) * LINK_PAIR_SPACING;
			}

			return (dx < 0 ? -1 : 1) * (index + 0.5) * LINK_PAIR_SPACING;
		}

		// 树形直角：父锚点 → 竖直走一小段到主干 → 横向分到子节点上方 → 竖直落到子锚点。
		// 传进来的 pa / pb 已经带好聚合成员偏移，所以两端都是竖直的，不会再出现斜的尖角；
		// railOff 只让同一组各成员的横段上下错开几像素，不至于完全重叠成一条线。
		// 层级挨得近时把主干往上收，保证横段落在子图标上方，不会从子图标顶上穿过去。
		function treeRoute(pa, pb, railOff) {
			var rail = Math.min(pa.y + LINK_TRUNK_GAP + railOff, pb.y - 8);

			rail = Math.max(rail, pa.y + 4);

			return [
				{x: pa.x, y: pa.y},
				{x: pa.x, y: rail},
				{x: pb.x, y: rail},
				{x: pb.x, y: pb.y}
			];
		}

		// ---------------------------------------------------------------
		// 链路指标（带宽 / 上行 / 下行）
		// ---------------------------------------------------------------

		// 每链路三组监控项 ID。用户自己从 Zabbix 监控项里挑；聚合成员口就是多条，显示时求和。
		var METRIC_KINDS = [
			{key: 'bw', label: T('带宽'), prefix: '≡', color: '#FACC15'},
			{key: 'in', label: T('上行流量'), prefix: '↑', color: '#00D4FF'},
			{key: 'out', label: T('下行流量'), prefix: '↓', color: '#00FF88'}
		];

		function normalizeMetrics(raw) {
			var m = raw || {};
			var out = {};

			METRIC_KINDS.forEach(function (kind) {
				var list = m[kind.key];
				out[kind.key] = Array.isArray(list) ? list.map(String) : [];
			});

			return out;
		}

		// 折点归一：只保留 {x,y} 数字对，取整并钳制为非负（和 sysmaps 坐标一致）
		function normalizeWaypointList(raw) {
			if (!Array.isArray(raw)) {
				return [];
			}

			var out = [];

			raw.forEach(function (point) {
				if (!point) {
					return;
				}

				var x = Number(point.x);
				var y = Number(point.y);

				if (!isFinite(x) || !isFinite(y)) {
					return;
				}

				out.push({x: Math.max(0, Math.round(x)), y: Math.max(0, Math.round(y))});
			});

			return out;
		}

		function metricKind(key) {
			for (var i = 0; i < METRIC_KINDS.length; i++) {
				if (METRIC_KINDS[i].key === key) {
					return METRIC_KINDS[i];
				}
			}

			return null;
		}

		function selementById(selementid) {
			var list = (state.map && state.map.selements) || [];

			for (var i = 0; i < list.length; i++) {
				if (String(list[i].selementid) === String(selementid)) {
					return list[i];
				}
			}

			return null;
		}

		// 一条链路两端元素对应的 Zabbix 主机（图片元素没有 hostid）。指标监控项只从这两台里挑。
		function linkHostids(link) {
			var out = [];

			[link.selementid1, link.selementid2].forEach(function (sid) {
				var se = selementById(sid);

				if (se && se._hostid && out.indexOf(String(se._hostid)) === -1) {
					out.push(String(se._hostid));
				}
			});

			return out;
		}

		// 监控项选择器默认勾选的主机：**优先源端**（selementid1），源端不是主机时回退本端。
		function defaultPickHostids(link) {
			var ends = linkEnds(link);

			if (ends.srcHostid) {
				return [ends.srcHostid];
			}

			if (ends.tgtHostid) {
				return [ends.tgtHostid];
			}

			return [];
		}

		// 链路两端的主机（源端 = selementid1，本端 = selementid2）。
		function linkEnds(link) {
			var a = selementById(link.selementid1);
			var b = selementById(link.selementid2);

			return {
				srcHostid: a && a._hostid ? String(a._hostid) : '',
				tgtHostid: b && b._hostid ? String(b._hostid) : ''
			};
		}

		// 两端主机（去重）：手工绑定触发器时要在这两端的主机里挑
		function linkEndHostids(link) {
			var ends = linkEnds(link);
			var ids = [];

			[ends.srcHostid, ends.tgtHostid].forEach(function (id) {
				if (id && ids.indexOf(id) === -1) {
					ids.push(id);
				}
			});

			return ids;
		}

		// 按当前「触发器来源」口径重新匹配这条链路的端口状态类触发器。
		// 命中后原生地图与模块画布都会在报警时把这条线画红。
		function rematchLinkTriggers(link) {
			var ends = linkEnds(link);

			// 两端都要有主机、且至少有一个端口名，才有东西可匹配。
			// 手工建的线没有端口信息，这时不重匹配，免得把用户手工绑的触发器清空。
			if ((!ends.srcHostid && !ends.tgtHostid) || (!link.source_port && !link.target_port)) {
				return;
			}

			// 保留用户在这条线上已调好的线型/颜色（按 triggerid 复用）
			var previous = {};

			(link.linktriggers || []).forEach(function (lt) {
				if (lt && lt.triggerid) {
					previous[String(lt.triggerid)] = lt;
				}
			});

			api('linkTriggers', {
				hostid1: ends.srcHostid,
				hostid2: ends.tgtHostid,
				port1: link.source_port || '',
				port2: link.target_port || '',
				trigger_source: num(link.trigger_source, 0)
			}).then(function (res) {
				var triggers = (res && res.triggers) ? res.triggers : [];

				link.linktriggers = triggers.map(function (trigger) {
					var tid = String(trigger.triggerid);
					var old = previous[tid] || {};

					return {
						triggerid: tid,
						drawtype: num(old.drawtype, 0),
						color: String(old.color || DEFAULT_TRIGGER_COLOR).replace('#', '')
					};
				});

				markDirty();
				renderCanvas();
			}).catch(function () {
				// 重匹配失败不打断编辑；用户稍后重新点「应用」即可
			});
		}

		// 一组监控项最新值的和（聚合成员口求和）；取不到数值就返回 null。
		// units 取第一个有单位的监控项。
		function sumMetricItems(itemids) {
			var items = (state.map && state.map.metric_items) || {};
			var total = null;
			var units = '';

			(itemids || []).forEach(function (itemid) {
				var item = items[String(itemid)];

				if (!item) {
					return;
				}

				var n = parseFloat(item.lastvalue);

				if (isNaN(n)) {
					return;
				}

				total = (total === null ? 0 : total) + n;

				if (!units && item.units) {
					units = String(item.units);
				}
			});

			return total === null ? null : {value: total, units: units};
		}

		function formatBps(n) {
			if (n === null || n === undefined || isNaN(n)) {
				return '-';
			}

			if (n < 1000) {
				return Math.round(n) + ' bps';
			}

			if (n < 1000000) {
				return (n / 1000).toFixed(1) + ' Kbps';
			}

			if (n < 1000000000) {
				return (n / 1000000).toFixed(1) + ' Mbps';
			}

			return (n / 1000000000).toFixed(2) + ' Gbps';
		}

		function trimNum(n) {
			return Math.abs(n - Math.round(n)) < 1e-9 ? String(Math.round(n)) : String(Math.round(n * 100) / 100);
		}

		/*
		 * 指标值 → 显示文本。
		 * 单位是 bps（小写 b，比特/秒）的按速率换算；单位是 Bps / B / bytes / octets（字节）
		 * 的先 ×8 换成比特/秒再换算；其它单位（%、个、s…）直接显示数值 + 单位。
		 */
		function metricText(res) {
			if (!res) {
				return '-';
			}

			var units = res.units || '';

			if (/bps/.test(units)) {
				return formatBps(res.value);
			}

			if (/^Bps$|^B\/s$|bytes|octets|^B$/.test(units)) {
				return formatBps(res.value * 8);
			}

			return trimNum(res.value) + (units ? ' ' + units : '');
		}

		// 连线上要画的小字：标签 + 已配置的指标（数据取不到就写「-」）。
		function linkMetricLines(link) {
			var m = normalizeMetrics(link.metrics);
			var lines = [];

			METRIC_KINDS.forEach(function (kind) {
				if (!m[kind.key].length) {
					return;
				}

				lines.push({
					text: kind.prefix + ' ' + metricText(sumMetricItems(m[kind.key])),
					color: kind.color
				});
			});

			return lines;
		}

		// 连线上绑定的触发器里只要有一个处于问题态，就用触发器自己的颜色画线（自动发现建的是 DD0000）；
		// 否则保持连线自身颜色（自动发现建的是 00CC00，手工建的是用户选的色）。
		function linkProblemColor(link) {
			var list = link.linktriggers || [];

			for (var i = 0; i < list.length; i++) {
				if (list[i] && list[i].problem) {
					return String(list[i].color || DEFAULT_TRIGGER_COLOR);
				}
			}

			return '';
		}

		// 「元素对」的无序键（与方向无关）：同一对元素之间的多条连线归为同一组。
		function linkPairKey(link) {
			var a = String(link.selementid1);
			var b = String(link.selementid2);

			return a < b ? a + '|' + b : b + '|' + a;
		}

		/*
		 * 同一对元素之间的所有连线（链路聚合的多个成员口、LLDP 双向重复线）算「一条链路」：
		 * 共用一份配置（标签 / 指标 / 触发器来源），点任意一条打开的都是同一个「链接」对话框，
		 * 编辑与移除都作用于整组 —— 对齐 aiops 的做法，不让用户逐条去改。
		 * 线本身还是按成员口画成多条贴近成束的曲线，保留聚合的视觉信息。
		 */
		function linkFamily(link) {
			var key = linkPairKey(link);
			var list = (state.map && state.map.links) || [];
			var family = [];

			list.forEach(function (item) {
				if (linkPairKey(item) === key) {
					family.push(item);
				}
			});

			return family.length ? family : [link];
		}

		// 整组的指标监控项：合并所有成员口的配置并去重（老数据可能分散在各条线上）。
		function familyMetrics(family) {
			var out = normalizeMetrics(null);
			var seen = {};

			family.forEach(function (link) {
				var m = normalizeMetrics(link.metrics);

				METRIC_KINDS.forEach(function (kind) {
					m[kind.key].forEach(function (id) {
						if (!seen[id]) {
							seen[id] = true;
							out[kind.key].push(id);
						}
					});
				});
			});

			return out;
		}

		// 组的标签：取第一个非空值（整组共用一份标签）
		function familyLabel(family) {
			for (var i = 0; i < family.length; i++) {
				if (family[i].label) {
					return String(family[i].label);
				}
			}

			return '';
		}

		// 组的绑定触发器：取第一个有绑定的成员（整组共用一份）
		function familyLinkTriggers(family) {
			for (var i = 0; i < family.length; i++) {
				if (family[i].linktriggers && family[i].linktriggers.length) {
					return copy(family[i].linktriggers);
				}
			}

			return [];
		}

		// 组的端口名（聚合时是多个成员口）：在「链接」对话框里列出来，方便确认这条链路覆盖了哪些口。
		// 端口名不再画在连线上（用户要求移掉），只在对话框里作为信息展示。
		function familyPorts(family) {
			var src = [];
			var tgt = [];

			family.forEach(function (link) {
				var s = String(link.source_port || '');
				var t = String(link.target_port || '');

				if (s && src.indexOf(s) === -1) {
					src.push(s);
				}

				if (t && tgt.indexOf(t) === -1) {
					tgt.push(t);
				}
			});

			return {src: src, tgt: tgt};
		}

		// 移除整组连线（链路聚合的多成员口一起消失）
		function removeLinkFamily(link) {
			var family = linkFamily(link);

			state.map.links = state.map.links.filter(function (item) {
				return family.indexOf(item) === -1;
			});
			markDirty();
			renderCanvas();
		}

		// ---------------------------------------------------------------
		// 走线 / 折点
		// ---------------------------------------------------------------

		var BEND_HINT = T('折点模式：点击空白处添加折点，拖动圆点调整，双击圆点删除，Esc 完成');

		// 自动直角布线：与障碍物（其它元素图标 + 标签文字框）保持的间距、
		// 每拐一次弯的代价（越大越倾向于少拐弯）、以及只考虑连线附近多大范围内的障碍物
		var ORTHO_GAP = 18;
		var ORTHO_PAD = 8;
		var ORTHO_BEND_COST = 26;
		var ORTHO_MARGIN = 220;

		// 估算一个元素占的矩形：图标本身 + 标签文字框（含状态行），
		// 让自动直角能绕开图标和文字，而不是从中间穿过去。
		function elementObstacleRect(se) {
			var size = iconSize(iconForElement(se));
			var pad = ORTHO_PAD;
			var cx = se.x + size.w / 2;
			var cy = se.y + size.h / 2;
			var x1 = se.x - pad;
			var x2 = se.x + size.w + pad;
			var y1 = se.y - pad;
			var y2 = se.y + size.h + pad;

			var nameLines = elementLabel(se) ? String(elementLabel(se)).split('\n') : [];
			var statusLines = elementStatusLines(se) || [];
			var count = nameLines.length + statusLines.length;

			if (count) {
				var lineW = 0;

				nameLines.forEach(function (line) {
					lineW = Math.max(lineW, textWidth(line, 12));
				});
				statusLines.forEach(function (line) {
					lineW = Math.max(lineW, textWidth(line.text || '', 12));
				});

				var textW = lineW + 10;
				var textH = count * 16;
				var loc = se.label_location === -1 && state.map ? state.map.label_location : se.label_location;

				if (loc === 3) {
					y1 = Math.min(y1, se.y - 4 - textH - pad);
					x1 = Math.min(x1, cx - textW / 2 - pad);
					x2 = Math.max(x2, cx + textW / 2 + pad);
				}
				else if (loc === 1) {
					x1 = Math.min(x1, se.x - 4 - textW - pad);
					y1 = Math.min(y1, cy - textH / 2 - pad);
					y2 = Math.max(y2, cy + textH / 2 + pad);
				}
				else if (loc === 2) {
					x2 = Math.max(x2, se.x + size.w + 4 + textW + pad);
					y1 = Math.min(y1, cy - textH / 2 - pad);
					y2 = Math.max(y2, cy + textH / 2 + pad);
				}
				else {
					y2 = Math.max(y2, se.y + size.h + 3 + textH + pad);
					x1 = Math.min(x1, cx - textW / 2 - pad);
					x2 = Math.max(x2, cx + textW / 2 + pad);
				}
			}

			return {x: x1, y: y1, w: x2 - x1, h: y2 - y1, owner: String(se.selementid)};
		}

		function collectLinkObstacles(map) {
			var out = [];

			(map.selements || []).forEach(function (se) {
				out.push(elementObstacleRect(se));
			});

			return out;
		}

		function uniqueSorted(values) {
			var sorted = values.slice().sort(function (a, b) {
				return a - b;
			});
			var out = [];

			sorted.forEach(function (value) {
				if (!out.length || Math.abs(value - out[out.length - 1]) > 0.01) {
					out.push(value);
				}
			});

			return out;
		}

		// 线段是否穿过任何障碍矩形（横/竖线段都按外接矩形判，够用且快）
		function orthoSegmentFree(x1, y1, x2, y2, obstacles) {
			var minX = Math.min(x1, x2);
			var maxX = Math.max(x1, x2);
			var minY = Math.min(y1, y2);
			var maxY = Math.max(y1, y2);

			for (var i = 0; i < obstacles.length; i++) {
				var o = obstacles[i];

				if (o.x < maxX && o.x + o.w > minX && o.y < maxY && o.y + o.h > minY) {
					return false;
				}
			}

			return true;
		}

		function routeHeapPush(heap, item) {
			heap.push(item);

			var i = heap.length - 1;

			while (i > 0) {
				var p = (i - 1) >> 1;

				if (heap[p].f <= heap[i].f) {
					break;
				}

				var t = heap[p];
				heap[p] = heap[i];
				heap[i] = t;
				i = p;
			}
		}

		function routeHeapPop(heap) {
			var top = heap[0];
			var last = heap.pop();

			if (heap.length) {
				heap[0] = last;

				var i = 0;

				for (;;) {
					var l = i * 2 + 1;
					var r = l + 1;
					var m = i;

					if (l < heap.length && heap[l].f < heap[m].f) {
						m = l;
					}
					if (r < heap.length && heap[r].f < heap[m].f) {
						m = r;
					}
					if (m === i) {
						break;
					}

					var t = heap[m];
					heap[m] = heap[i];
					heap[i] = t;
					i = m;
				}
			}

			return top;
		}

		// 合并重复点、去掉同一直线上的中间点
		function compressRoute(points) {
			var out = [];

			points.forEach(function (point) {
				var last = out[out.length - 1];

				if (last && Math.abs(last.x - point.x) < 0.01 && Math.abs(last.y - point.y) < 0.01) {
					return;
				}

				out.push({x: point.x, y: point.y});
			});

			var clean = [out[0]];

			for (var i = 1; i < out.length - 1; i++) {
				var a = clean[clean.length - 1];
				var b = out[i];
				var c = out[i + 1];
				var collinear = (Math.abs(a.x - b.x) < 0.01 && Math.abs(b.x - c.x) < 0.01)
					|| (Math.abs(a.y - b.y) < 0.01 && Math.abs(b.y - c.y) < 0.01);

				if (!collinear) {
					clean.push(b);
				}
			}

			if (out.length > 1) {
				clean.push(out[out.length - 1]);
			}

			return clean;
		}

		// 正交 A*：在障碍物的边界及其外侧通道上找一条横平竖直、绕开障碍的路径。
		// 找不到时退回原来的「中点折一次」路径。
		function orthogonalRoute(pa, pb, obstacles) {
			var near = [];
			var left = Math.min(pa.x, pb.x) - ORTHO_MARGIN;
			var right = Math.max(pa.x, pb.x) + ORTHO_MARGIN;
			var top = Math.min(pa.y, pb.y) - ORTHO_MARGIN;
			var bottom = Math.max(pa.y, pb.y) + ORTHO_MARGIN;

			obstacles.forEach(function (o) {
				if (o.x >= right || o.x + o.w <= left || o.y >= bottom || o.y + o.h <= top) {
					return;
				}

				// 起点/终点落在障碍里（元素重叠）时忽略该障碍，否则永远无解
				var hasPa = pa.x >= o.x && pa.x <= o.x + o.w && pa.y >= o.y && pa.y <= o.y + o.h;
				var hasPb = pb.x >= o.x && pb.x <= o.x + o.w && pb.y >= o.y && pb.y <= o.y + o.h;

				if (!hasPa && !hasPb) {
					near.push(o);
				}
			});

			function midRoute() {
				var dx = pb.x - pa.x;
				var dy = pb.y - pa.y;

				if (Math.abs(dx) >= Math.abs(dy)) {
					var mx = (pa.x + pb.x) / 2;

					return [{x: pa.x, y: pa.y}, {x: mx, y: pa.y}, {x: mx, y: pb.y}, {x: pb.x, y: pb.y}];
				}

				var my = (pa.y + pb.y) / 2;

				return [{x: pa.x, y: pa.y}, {x: pa.x, y: my}, {x: pb.x, y: my}, {x: pb.x, y: pb.y}];
			}

			if (!near.length) {
				return midRoute();
			}

			var xs = [pa.x, pb.x];
			var ys = [pa.y, pb.y];
			var minX = Infinity;
			var maxX = -Infinity;
			var minY = Infinity;
			var maxY = -Infinity;

			near.forEach(function (o) {
				xs.push(Math.max(0, o.x - ORTHO_GAP), o.x + o.w + ORTHO_GAP);
				ys.push(Math.max(0, o.y - ORTHO_GAP), o.y + o.h + ORTHO_GAP);
				minX = Math.min(minX, o.x);
				maxX = Math.max(maxX, o.x + o.w);
				minY = Math.min(minY, o.y);
				maxY = Math.max(maxY, o.y + o.h);
			});

			xs.push(Math.max(0, minX - ORTHO_GAP), maxX + ORTHO_GAP);
			ys.push(Math.max(0, minY - ORTHO_GAP), maxY + ORTHO_GAP);

			xs = uniqueSorted(xs);
			ys = uniqueSorted(ys);

			var nx = xs.length;
			var ny = ys.length;
			var starts = [];

			xs.forEach(function (x, xi) {
				if (Math.abs(x - pa.x) < 0.01) {
					starts.push({xi: xi, x: x});
				}
			});

			var startYi = -1;
			var endXi = -1;
			var endYi = -1;

			ys.forEach(function (y, yi) {
				if (Math.abs(y - pa.y) < 0.01) {
					startYi = yi;
				}
				if (Math.abs(y - pb.y) < 0.01) {
					endYi = yi;
				}
			});
			xs.forEach(function (x, xi) {
				if (Math.abs(x - pb.x) < 0.01) {
					endXi = xi;
				}
			});

			var goalXi = xs.length - 1;
			var goalYi = ys.length - 1;

			endXi = Math.max(0, endXi);
			endYi = Math.max(0, endYi);

			if (startYi < 0 || xs.length < 2 || ys.length < 2) {
				return midRoute();
			}

			var startXi = 0;

			xs.forEach(function (x, xi) {
				if (Math.abs(x - pa.x) < 0.01) {
					startXi = xi;
				}
			});

			// 状态 = 网格点 × 进入方向（0 起点 / 1 横向 / 2 纵向）
			var total = nx * ny;
			var g = [];
			var prev = [];

			for (var s = 0; s < total * 3; s++) {
				g.push(Infinity);
				prev.push(-1);
			}

			var heap = [];
			var startState = (startYi * nx + startXi) * 3;

			g[startState] = 0;
			routeHeapPush(heap, {state: startState, f: Math.abs(pa.x - pb.x) + Math.abs(pa.y - pb.y)});

			var moves = [[-1, 0, 1], [1, 0, 1], [0, -1, 2], [0, 1, 2]];
			var bestState = -1;
			var guard = 0;

			while (heap.length && guard++ < 20000) {
				var item = routeHeapPop(heap);
				var state = item.state;
				var node = Math.floor(state / 3);
				var dir = state % 3;
				var xi = node % nx;
				var yi = Math.floor(node / nx);

				if (xi === endXi && yi === endYi) {
					bestState = state;
					break;
				}

				for (var m = 0; m < moves.length; m++) {
					var xi2 = xi + moves[m][0];
					var yi2 = yi + moves[m][1];
					var dir2 = moves[m][2];

					if (xi2 < 0 || xi2 >= nx || yi2 < 0 || yi2 >= ny) {
						continue;
					}

					var x2 = xs[xi2];
					var y2 = ys[yi2];

					if (!orthoSegmentFree(xs[xi], ys[yi], x2, y2, near)) {
						continue;
					}

					var cost = Math.abs(x2 - xs[xi]) + Math.abs(y2 - ys[yi]);

					if (dir !== 0 && dir !== dir2) {
						cost += ORTHO_BEND_COST;
					}

					var state2 = (yi2 * nx + xi2) * 3 + dir2;
					var g2 = g[state] + cost;

					if (g2 < g[state2]) {
						g[state2] = g2;
						prev[state2] = state;
						routeHeapPush(heap, {
							state: state2,
							f: g2 + Math.abs(xs[xi2] - pb.x) + Math.abs(ys[yi2] - pb.y)
						});
					}
				}
			}

			if (bestState < 0) {
				return midRoute();
			}

			var seq = [];
			var cur = bestState;

			while (cur !== -1) {
				var node2 = Math.floor(cur / 3);

				seq.push({
					x: xs[node2 % nx],
					y: ys[Math.floor(node2 / nx)]
				});
				cur = prev[cur];
			}

			seq.reverse();

			if (!seq.length || Math.abs(seq[0].x - pa.x) > 0.01 || Math.abs(seq[0].y - pa.y) > 0.01) {
				seq.unshift({x: pa.x, y: pa.y});
			}

			seq.push({x: pb.x, y: pb.y});

			return compressRoute(seq);
		}

		// 折线点集：routing 1 = 自动直角（绕开图标 / 文字框），routing 2 = 手动折点。
		// 返回 null 表示按原来的二次贝塞尔画（routing 0）。
		// off 是链路聚合成员线的垂直偏移，只作用在中间点/折点上，两端仍落在元素中心。
		function linkPolyline(link, pa, pb, nx, ny, off, obstacles) {
			var routing = num(link.routing, 0);
			var points;

			if (routing === 1) {
				points = orthogonalRoute(pa, pb, obstacles || []);

				if (off) {
					points = points.map(function (point, i) {
						if (i === 0 || i === points.length - 1) {
							return point;
						}

						return {x: point.x + nx * off, y: point.y + ny * off};
					});
				}

				return points;
			}

			if (routing === 2) {
				points = [{x: pa.x, y: pa.y}];

				normalizeWaypointList(link.waypoints).forEach(function (wp) {
					points.push({x: wp.x + nx * off, y: wp.y + ny * off});
				});

				points.push({x: pb.x, y: pb.y});

				return points;
			}

			return null;
		}

		function polylinePath(points) {
			var d = '';

			points.forEach(function (point, i) {
				d += (i ? ' L ' : 'M ') + point.x + ' ' + point.y;
			});

			return d;
		}

		// 折线总长的中点：标签 / 指标框放在这里，而不是首尾的中点
		function polylineMidpoint(points) {
			if (!points.length) {
				return {x: 0, y: 0};
			}

			var lengths = [];
			var total = 0;

			for (var i = 1; i < points.length; i++) {
				var dx = points[i].x - points[i - 1].x;
				var dy = points[i].y - points[i - 1].y;
				var len = Math.sqrt(dx * dx + dy * dy);

				lengths.push(len);
				total += len;
			}

			if (total <= 0) {
				return {x: points[0].x, y: points[0].y};
			}

			var remain = total / 2;

			for (var j = 0; j < lengths.length; j++) {
				if (remain <= lengths[j] || j === lengths.length - 1) {
					var t = lengths[j] > 0 ? Math.max(0, Math.min(1, remain / lengths[j])) : 0;

					return {
						x: points[j].x + (points[j + 1].x - points[j].x) * t,
						y: points[j].y + (points[j + 1].y - points[j].y) * t
					};
				}

				remain -= lengths[j];
			}

			return {x: points[points.length - 1].x, y: points[points.length - 1].y};
		}

		// 当前正在编辑折点的整组连线（链路聚合整组共享一份折点）
		function bendFamily() {
			if (!state.bendLink) {
				return [];
			}

			var family = linkFamily(state.bendLink);

			return family.length ? family : [state.bendLink];
		}

		// 把同一组折点写到整组的每条成员线（每条线各自存一份，保存时按 linkid 落库）
		function setFamilyWaypoints(family, points) {
			family.forEach(function (link) {
				link.routing = 2;
				link.waypoints = points.map(function (point) {
					return {x: point.x, y: point.y};
				});
			});
		}

		function startWaypointDrag(index, e) {
			var family = bendFamily();

			if (!family.length) {
				return;
			}

			var start = pointerToMap(e);
			var origin = family[0].waypoints[index];

			if (!origin) {
				return;
			}

			// mousedown 时把折点坐标拷成数字快照：move 里会改写 waypoints，
			// 若继续引用 origin 对象，每帧的增量会叠加到已经移动过的坐标上，
			// 鼠标动一点点折点就会飞出去。
			var originX = Number(origin.x) || 0;
			var originY = Number(origin.y) || 0;
			var moved = false;

			function move(ev) {
				var pt = pointerToMap(ev);
				var nx = Math.max(0, Math.round(originX + pt.x - start.x));
				var ny = Math.max(0, Math.round(originY + pt.y - start.y));

				family.forEach(function (link) {
					if (!link.waypoints || !link.waypoints[index]) {
						return;
					}

					link.waypoints[index].x = nx;
					link.waypoints[index].y = ny;
				});

				moved = true;
				drawLinks(state.map);
			}

			function up() {
				document.removeEventListener('mousemove', move);
				document.removeEventListener('mouseup', up);

				if (moved) {
					markDirty();
				}
			}

			document.addEventListener('mousemove', move);
			document.addEventListener('mouseup', up);
		}

		function removeWaypointAt(index) {
			var family = bendFamily();

			family.forEach(function (link) {
				if (link.waypoints && link.waypoints[index]) {
					link.waypoints.splice(index, 1);
				}
			});

			markDirty();
			drawLinks(state.map);
		}

		function exitBendMode() {
			if (!state.bendLink) {
				return false;
			}

			state.bendLink = null;
			setHint(state.dirty ? T('有未保存的修改') : (state.editing ? '' : T('预览模式')));
			drawLinks(state.map);

			return true;
		}

		// 估算 SVG 文本宽度（够用即可）：中日韩等全角字符按字号算，半角按 0.6 倍算。
		function textWidth(text, size) {
			var w = 0;

			for (var i = 0; i < text.length; i++) {
				w += text.charCodeAt(i) > 0x2E80 ? size : size * 0.6;
			}

			return w;
		}

		// 高亮层：原生 SVG 图的分层是 shapes → highlights → links → elements。
		// 单独放一层，位置固定在「所有形之后、所有连线之前」，重画时不会跑到连线上面去。
		var hlLayer = null;

		function highlightLayer() {
			if (!hlLayer || hlLayer.parentNode !== elSvg) {
				hlLayer = document.createElementNS('http://www.w3.org/2000/svg', 'g');
				hlLayer.setAttribute('class', 'tm-hl-layer');
				hlLayer.setAttribute('pointer-events', 'none');
				elSvg.appendChild(hlLayer);
			}

			// 形是往末尾重画的，把高亮层重新挪到所有形之后、第一条连线之前
			var firstLink = elSvg.querySelector('.tm-link-group');

			elSvg.insertBefore(hlLayer, firstLink || null);

			return hlLayer;
		}

		/**
		 * 元素高亮（原生 sysmaps.highlight）。
		 *
		 * 问题中画的是「正圆」：原生用 ellipse 但 rx = ry = floor(图标宽/2)+10、圆心取元素中心，
		 * 所以图标不是正方形时也还是圆。之前用 CSS 百分比画（宽高都 +20px、border-radius 50%），
		 * 图标高一点就变成椭圆了。
		 */
		function drawHighlights(map) {
			var layer = highlightLayer();

			clear(layer);

			map.selements.forEach(function (se) {
				var hl = highlightForElement(se);

				if (!hl) {
					return;
				}

				var size = iconSize(iconForElement(se));
				var node;

				if (hl.st) {
					node = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
					node.setAttribute('x', String(se.x - 2));
					node.setAttribute('y', String(se.y - 2));
					node.setAttribute('width', String(size.w + 4));
					node.setAttribute('height', String(size.h + 4));
					node.setAttribute('fill', '#' + hl.st);
					node.setAttribute('fill-opacity', '0.5');
				}
				else {
					var radius = Math.floor(size.w / 2) + 10;

					node = document.createElementNS('http://www.w3.org/2000/svg', 'ellipse');
					node.setAttribute('cx', String(se.x + size.w / 2));
					node.setAttribute('cy', String(se.y + size.h / 2));
					node.setAttribute('rx', String(radius));
					node.setAttribute('ry', String(radius));
					node.setAttribute('fill', '#' + hl.hl);

					if (hl.ack) {
						node.setAttribute('stroke', '#329632');
						node.setAttribute('stroke-width', '4');
					}
				}

				node.setAttribute('class', 'tm-hl');
				node.setAttribute('data-hl-id', String(se.selementid));
				node.setAttribute('pointer-events', 'none');
				layer.appendChild(node);
			});
		}

		// 拖拽时只挪这一个高亮（不重建整层，避免拖拽过程中高亮闪）
		function syncHighlight(se) {
			var node = hlLayer && hlLayer.parentNode === elSvg
				? hlLayer.querySelector('[data-hl-id="' + se.selementid + '"]')
				: null;

			if (!node) {
				return;
			}

			var size = iconSize(iconForElement(se));

			if (node.tagName.toLowerCase() === 'rect') {
				node.setAttribute('x', String(se.x - 2));
				node.setAttribute('y', String(se.y - 2));
				node.setAttribute('width', String(size.w + 4));
				node.setAttribute('height', String(size.h + 4));

				return;
			}

			node.setAttribute('cx', String(se.x + size.w / 2));
			node.setAttribute('cy', String(se.y + size.h / 2));
			node.setAttribute('rx', String(Math.floor(size.w / 2) + 10));
			node.setAttribute('ry', String(Math.floor(size.w / 2) + 10));
		}

		function drawLinks(map) {
			var nodes = elSvg.querySelectorAll('.tm-link-group, .tm-link-badge-group, .tm-waypoint-group');

			for (var i = 0; i < nodes.length; i++) {
				elSvg.removeChild(nodes[i]);
			}

			var byId = {};

			map.selements.forEach(function (se) {
				byId[String(se.selementid)] = se;
			});

			// 同一对元素之间可能有多条连线（链路聚合的多个成员口、双向重复线），
			// 从元素中心到中心画直线会完全重叠成一条。按「元素对」分组，
			// 组内给每条线一个对称的垂直偏移，画成二次贝塞尔曲线把线拉开，
			// 标签也跟着落在曲线中点上。
			var pairCount = {};
			var pairSeq = {};
			// 标签框收集起来最后统一插入：SVG 按插入顺序叠，晚插入的才盖在上面。
			// 链路聚合时同组线只隔 12px，若逐条边画边插，后画的成员线会从框中间穿过去。
			var pendingBadges = [];

			map.links.forEach(function (link) {
				if (!byId[String(link.selementid1)] || !byId[String(link.selementid2)]) {
					return;
				}

				var countKey = linkPairKey(link);
				pairCount[countKey] = (pairCount[countKey] || 0) + 1;
			});

			// 自动直角布线的障碍物：画布上其它元素的图标 + 标签文字框。
			// 整张图算一次，每条连线再剔掉自己两端（不能让起点/终点所在元素挡住自己）。
			var linkObstacles = collectLinkObstacles(map);

			// 树形布局：每个父元素下面挂了几个「子元素」（按元素对去重，链路聚合的多个成员口算一台）。
			// 只有 1 台子元素走直线，多台才自动直角从共用主干分叉。
			var treeChildren = {};

			map.links.forEach(function (link) {
				var a = byId[String(link.selementid1)];
				var b = byId[String(link.selementid2)];

				if (!a || !b || sameRowElements(a, b)) {
					return;
				}

				var parent = b.y > a.y ? a : b;
				var pid = String(parent.selementid);

				treeChildren[pid] = treeChildren[pid] || {};
				treeChildren[pid][linkPairKey(link)] = true;
			});

			map.links.forEach(function (link) {
				var a = byId[String(link.selementid1)];
				var b = byId[String(link.selementid2)];

				if (!a || !b) {
					return;
				}

				// 上下两层的连线接「父节点文字块下方 → 子节点图标上方」，同层仍接中心
				var anchors = elementAnchors(a, b);
				var pa = anchors.pa;
				var pb = anchors.pb;
				var color = '#' + String(linkProblemColor(link) || link.color || DEFAULT_LINK_COLOR).replace('#', '');
				var width = link.drawtype === 2 ? 4 : 1.6;
				var dash = link.drawtype === 3 ? '2 4' : (link.drawtype === 4 ? '6 4' : '');

				// 组内序号 → 垂直偏移；单条线偏移 0，等价于原来的直线。
				var pairKey = linkPairKey(link);
				var total = pairCount[pairKey] || 1;
				var index = pairSeq[pairKey] || 0;
				pairSeq[pairKey] = index + 1;
				// 徽标只画在组中间那条线上（单条线就是它自己）
				var midIndex = Math.floor((total - 1) / 2);

				var dx = pb.x - pa.x;
				var dy = pb.y - pa.y;
				var len = Math.sqrt(dx * dx + dy * dy) || 1;
				// 垂直于连线的单位向量
				var nx = -dy / len;
				var ny = dx / len;
				var midX = (pa.x + pb.x) / 2;
				var midY = (pa.y + pb.y) / 2;
				var off = (index - (total - 1) / 2) * LINK_PAIR_SPACING;
				// 走线模式：0 自动曲线（二次贝塞尔）/ 1 自动直角 / 2 手动折点。
				// 折线只把中间点 / 折点做聚合偏移，两端仍接在锚点上。
				var ownA = String(link.selementid1);
				var ownB = String(link.selementid2);
				var obstacles = linkObstacles.filter(function (o) {
					return o.owner !== ownA && o.owner !== ownB;
				});
				var routing = num(link.routing, 0);
				var polyline;
				// 树形直角时指标框不放在走线中点（会落在横干上、看不出属于哪台主机），
				// 改放在子节点图标正上方
				var badgeAnchor = null;

				if (anchors.vertical && routing === 1) {
					// 树形直角：父锚点先竖直走到主干，再横向分到各子节点上方，最后竖直落到子锚点。
					// 只有 1 台子元素时直接连直线；聚合的多个成员口两端都按同样偏移并排，
					// 画成两条平行的竖线（以前只偏中间点，两端收成尖角，很难看）。
					var childCount = Object.keys(treeChildren[String(anchors.parent.selementid)] || {}).length;
					var treeOff = treeMemberOffset(index, total, pb.x - pa.x);
					var from = {x: pa.x + treeOff, y: pa.y};
					var to = {x: pb.x + treeOff, y: pb.y};
					// 同组各成员的横段上下错开一个线间距，避免整段重叠成一条
					var railOff = (index - (total - 1) / 2) * LINK_PAIR_SPACING;

					// 只有父节点下挂了多台子元素才分叉走直角；
					// 只有 1 台子元素时直接直线（链路聚合的多个成员口就是几条平行直线，
					// 不能因为成员口数量 > 1 就强行拐直角）。
					polyline = childCount > 1 ? treeRoute(from, to, railOff) : [from, to];
					// 指标框落在最后一段（竖线）的中点，居中在子节点正上方
					var tail = polyline[polyline.length - 1];
					var prev = polyline[polyline.length - 2];
					badgeAnchor = {x: pb.x, y: (prev.y + tail.y) / 2};
				}
				else {
					polyline = linkPolyline(link, pa, pb, nx, ny, off, obstacles);
				}
				var pathData;
				var labelX;
				var labelY;

				if (polyline) {
					pathData = polylinePath(polyline);

					var lineMid = polylineMidpoint(polyline);

					labelX = lineMid.x;
					labelY = lineMid.y;
				}
				else {
					// 二次贝塞尔控制点偏移 off*2：曲线在 t=0.5 处的点正好落在 中点 + 垂直偏移 off
					var cx = midX + nx * off * 2;
					var cy = midY + ny * off * 2;

					pathData = 'M ' + pa.x + ' ' + pa.y + ' Q ' + cx + ' ' + cy + ' ' + pb.x + ' ' + pb.y;
					// 整组只在中间那条线上画一个标签框（t=0.5，即曲线中点）：
					// 链路聚合的多条线共用一份配置，逐条画标签只会互相叠住、也没法分别编辑。
					var t = 0.5;
					var it = 1 - t;

					labelX = it * it * pa.x + 2 * it * t * cx + t * t * pb.x;
					labelY = it * it * pa.y + 2 * it * t * cy + t * t * pb.y;
				}

				var group = document.createElementNS('http://www.w3.org/2000/svg', 'g');
				group.setAttribute('class', 'tm-link-group');

				var line = document.createElementNS('http://www.w3.org/2000/svg', 'path');
				line.setAttribute('class', 'tm-link-line');
				line.setAttribute('d', pathData);
				line.setAttribute('stroke', color);
				line.setAttribute('stroke-width', String(width));

				if (dash) {
					line.setAttribute('stroke-dasharray', dash);
				}

				group.appendChild(line);

				var hit = document.createElementNS('http://www.w3.org/2000/svg', 'path');
				hit.setAttribute('class', 'tm-link-hit');
				hit.setAttribute('d', pathData);

				if (state.editing && state.removeMode === 'link') {
					hit.classList.add('tm-removable');
				}

				hit.addEventListener('mousedown', function (e) {
					e.stopPropagation();
				});

				hit.addEventListener('click', function (e) {
					if (!state.editing) {
						return;
					}

					e.stopPropagation();

					if (state.removeMode === 'link') {
						removeLinkFamily(link);
						return;
					}

					// 点组内任意一条线，打开的都是同一个「链接」对话框：整组共用一份配置
					if (!state.removeMode && !state.linkMode) {
						openLinkDialog(link, false);
					}
				});

				group.appendChild(hit);

				// 整组只画一个标签框（落在组中间那条线上）：标签 + 带宽 / 上行 / 下行。
				// 指标取整组（聚合的多个成员口）配置的并集；线本身不再标注两端端口。
				if (index !== midIndex) {
					elSvg.appendChild(group);

					return;
				}

				var lines = [];
				var badgeGroup = null;
				var groupLabel = familyLabel(linkFamily(link));

				if (groupLabel) {
					lines.push({text: groupLabel, metric: false});
				}

				linkMetricLines({metrics: familyMetrics(linkFamily(link))}).forEach(function (ml) {
					lines.push({text: ml.text, metric: true});
				});

				if (lines.length) {
					// 标签 + 指标画在一个圆角矩形框里（对齐 aiops）：框底用浅色，文字统一黑色。
					// 用浅色底是为了黑色文字在深色主题下也能看清。
					var step = 13;
					var padX = 6;
					var boxW = 0;

					lines.forEach(function (line) {
						boxW = Math.max(boxW, textWidth(line.text, 11));
					});

					boxW += padX * 2;

					var boxH = (lines.length - 1) * step + 20;

					if (badgeAnchor) {
						// 树形直角：框居中在子节点正上方，纵向落在「主干拐点 → 子节点锚点」这段竖线的中点；
						// 子节点下移、层间距变大时线段变长，框会自动跟着居中（对齐 Zabbix 自带拓扑图）
						labelX = badgeAnchor.x;
						labelY = badgeAnchor.y;
					}

					var startY = labelY - boxH / 2 + 14;   // 第一行文字的基线

					// 框单独放进一个「徽标层」：延后到整组的线都插进 SVG 之后再叠上去
					badgeGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
					badgeGroup.setAttribute('class', 'tm-link-badge-group');

					var rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
					rect.setAttribute('class', 'tm-link-badge');
					rect.setAttribute('x', String(labelX - boxW / 2));
					rect.setAttribute('y', String(labelY - boxH / 2));
					rect.setAttribute('width', String(boxW));
					rect.setAttribute('height', String(boxH));
					rect.setAttribute('rx', '5');
					rect.setAttribute('ry', '5');
					rect.setAttribute('fill', isDark ? '#f4f7f8' : '#ffffff');
					// 边框跟随连线本身的颜色：正常绿、断开/报警红
					rect.setAttribute('stroke', color);
					rect.setAttribute('stroke-width', '1.5');
					// 框压在连线的命中区上面，必须让它「不接收指针事件」，否则点线打不开「链接」对话框
					rect.setAttribute('pointer-events', 'none');
					badgeGroup.appendChild(rect);

					lines.forEach(function (line, i) {
						var text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
						var cls = 'tm-link-label';

						if (line.metric) {
							cls += ' tm-link-metric';
						}

						text.setAttribute('class', cls);
						text.setAttribute('x', String(labelX));
						text.setAttribute('y', String(startY + i * step));
						text.setAttribute('fill', '#000000');
						text.setAttribute('pointer-events', 'none');
						text.textContent = line.text;
						badgeGroup.appendChild(text);
					});
				}

				elSvg.appendChild(group);

				// 框延后插入：等整组的线都进 SVG 之后再叠上去（SVG 按插入顺序叠，没有 z-index）
				if (badgeGroup) {
					pendingBadges.push(badgeGroup);
				}
			});

			pendingBadges.forEach(function (badgeGroup) {
				elSvg.appendChild(badgeGroup);
			});

			// 折点手柄：只在折点编辑模式下画，放在最上层（要能盖住线、框，方便拖动/双击）
			if (state.bendLink) {
				var family = bendFamily();
				var points = (family[0] && family[0].waypoints) || [];

				if (points.length) {
					var bendGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
					bendGroup.setAttribute('class', 'tm-waypoint-group');

					points.forEach(function (point, index) {
						var dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
						dot.setAttribute('class', 'tm-waypoint');
						dot.setAttribute('cx', String(point.x));
						dot.setAttribute('cy', String(point.y));
						dot.setAttribute('r', '5');
						dot.setAttribute('data-index', String(index));

						dot.addEventListener('mousedown', function (e) {
							e.preventDefault();
							e.stopPropagation();
							startWaypointDrag(index, e);
						});

						dot.addEventListener('dblclick', function (e) {
							e.preventDefault();
							e.stopPropagation();
							removeWaypointAt(index);
						});

						bendGroup.appendChild(dot);
					});

					elSvg.appendChild(bendGroup);
				}
			}
		}

		function renderElement(se) {
			var box = make('div', 'tm-el');
			var size = iconSize(iconForElement(se));

			box.setAttribute('data-id', String(se.selementid));

			if (state.linkMode) {
				box.classList.add('tm-linkable');
			}

			if (state.editing && state.removeMode === 'element') {
				box.classList.add('tm-removable');
			}

			var img = make('img', 'tm-el-icon');
			img.src = iconUrl(iconForElement(se));
			img.width = size.w;
			img.height = size.h;
			img.alt = '';
			img.draggable = false;
			img.addEventListener('error', function () {
				img.style.display = 'none';
				box.classList.add('tm-el-noicon');
			});
			box.appendChild(img);

			paintElementState(se, box);

			box.addEventListener('mousedown', function (e) {
				if (!state.editing || state.removeMode) {
					return;
				}

				if (state.linkMode) {
					return;
				}

				e.stopPropagation();
				startElementDrag(se, box, e);
			});

			box.addEventListener('click', function (e) {
				if (!state.editing) {
					// 预览模式和自带拓扑图一样：点主机图标弹主机菜单
					openHostMenu(se, box, e);

					return;
				}

				e.stopPropagation();

				// 刚拖拽过的这一次 click 不打开弹窗
				if (se._suppressClick) {
					return;
				}

				if (state.removeMode === 'element') {
					removeElement(se);
					return;
				}

				if (state.linkMode) {
					pickLinkEndpoint(se);
					return;
				}

				state.selected = se.selementid;

				if (!isEditableElement(se)) {
					renderCanvas();
					openNoticeDialog(T('无法编辑该元素'), T('该元素类型（主机组 / 触发器 / 嵌套图）由 Zabbix 原生拓扑图编辑器维护，在自动拓扑图里只能预览和移动位置。'));

					return;
				}

				openElementDialog(se, false);
			});

			return box;
		}

		// 元素上「跟状态有关」的部分：位置 / 尺寸 / 状态类名 / 标签 / 悬停提示。
		// 自动刷新时只调它，<img> 保持原节点 —— 整层重建会让所有主机图标闪一下，
		// 而 Zabbix 自带拓扑图是原地更新，不闪。
		function paintElementState(se, box) {
			var size = iconSize(iconForElement(se));

			box.style.left = se.x + 'px';
			box.style.top = se.y + 'px';
			box.style.width = size.w + 'px';
			box.style.height = size.h + 'px';

			box.classList.toggle('tm-selected', state.selected === se.selementid);
			box.classList.toggle('tm-disabled', se._status === 1);
			box.classList.toggle('tm-problem', se._problems > 0);

			paintElementLabel(se, box);

			if (!state.editing) {
				var tip = elementTitle(se);

				if (tip) {
					box.title = tip;
				}
				else {
					box.removeAttribute('title');
				}
			}
		}

		// 原生拓扑图不在图标右上角放问题数角标，问题数只写在标签行里，所以这里没有 badge。
		function paintElementLabel(se, box) {
			var old = box.querySelector('.tm-el-label');

			if (old) {
				box.removeChild(old);
			}

			var labelText = elementLabel(se);
			var statusLines = elementStatusLines(se);
			var nameLines = labelText ? String(labelText).split('\n') : [];

			if (!nameLines.length && !statusLines.length) {
				return;
			}

			var loc = se.label_location === -1 ? state.map.label_location : se.label_location;
			var label = make('span', 'tm-el-label tm-loc-' + loc);

			nameLines.forEach(function (text) {
				label.appendChild(make('span', 'tm-el-line tm-el-name', text));
			});

			statusLines.forEach(function (line) {
				var node = make('span', 'tm-el-line tm-el-status', line.text);

				if (line.color) {
					node.style.color = '#' + line.color;
				}

				label.appendChild(node);
			});

			box.appendChild(label);
		}

		function elementName(se) {
			if (se.elementtype === ET_HOST) {
				return se._hostName || T('未知主机');
			}

			if (se.elementtype === ET_IMAGE) {
				return T('图片');
			}

			return se.label || '';
		}

		// 标签第一段：具体显示什么由 label_type 决定，语义对齐 7.0.28 maps.inc.php
		function elementLabel(se) {
			var type = num(se._labelType, LT_LABEL);

			if (type === LT_NAME) {
				return elementName(se);
			}

			if (type === LT_NOTHING || type === LT_STATUS) {
				return '';
			}

			if (type === LT_IP) {
				return se._ip || '';
			}

			// LT_LABEL / LT_CUSTOM：用元素自己的标签文本
			return se.label || '';
		}

		// 标签第二段：状态行。原生拓扑图在主机标签下面追加「正常 / 问题 / 已停用 / 在维护中」，
		// 颜色跟着严重级别走 —— 这就是用户说的「原生预览会显示正常，有问题也会显示」。
		function elementStatusLines(se) {
			var map = state.map;
			var lines = [];

			if (!map || se.elementtype !== ET_HOST || se._labelType === LT_NOTHING) {
				return lines;
			}

			if (se._status === 1) {
				lines.push({text: T('已停用'), color: statusColor('disabled')});

				return lines;
			}

			if (se._maintenance === 1) {
				// 维护中只要没有未确认问题，原生就显示维护色
				if (!se._problems) {
					lines.push({text: T('在维护中'), color: statusColor('maintenance')});

					return lines;
				}
			}

			if (!se._problems) {
				// 原生 getSelementLabelColor(false, ack)：没问题时 ack = true，用「已确认正常」色
				lines.push({text: T('正常'), color: statusColor('ok_ack')});

				return lines;
			}

			lines = problemLines(se, map.show_unack, map.expandproblem);

			return lines.length ? lines : [{text: se._problems + T(' 个问题'), color: statusColor('problem')}];
		}

		// 对齐 7.0.28 maps.inc.php getSelementInfo() 里 show_unack × expandproblem 的组合。
		// expandproblem：0 = 展开单个问题，1 = 只显示问题数量，2 = 数量 + 展开最严重的那条。
		// 唯一的偏离：只要**只有 1 个问题**就写触发器名称 —— 用户的图 expandproblem=1，
		// 1 个问题时原生只写「1 problem」，看不出是哪个触发器，他明确要触发器名称。
		function problemLines(se, showUnack, expand) {
			var lines = [];
			var total = se._problems;
			var unack = se._unack;
			var title = se._title || '';
			// 全部已确认时用「已确认问题」色，有未确认用未确认色（原生 getSelementLabelColor）
			var color = statusColor(unack > 0 ? 'problem' : 'problem_ack');

			function countLine(n, word) {
				return word ? TF('%s 个未确认问题', n) : TF('%s 个问题', n);
			}

			// total > 0 的那一段（show_unack 为 0 全部 / 2 两者都显示）
			if (showUnack !== 1 && total > 0) {
				if (total === 1) {
					lines.push({text: title || countLine(1, ''), color: color});
				}
				else if (expand === 1) {
					lines.push({text: countLine(total, ''), color: color});
				}
				else if (expand === 2) {
					lines.push({text: title || countLine(total, ''), color: color});
					lines.push({text: countLine(total, ''), color: color});
				}
				else {
					lines.push({text: countLine(total, ''), color: color});
				}
			}

			// 未确认那一段（show_unack 为 1 只看未确认 / 2 两者都显示）
			if (showUnack !== 0 && unack > 0) {
				if (showUnack === 1) {
					var unackColor = statusColor('problem');

					if (unack === 1) {
						lines.push({text: title || countLine(1, T('未确认')), color: unackColor});
					}
					else if (expand === 1) {
						lines.push({text: countLine(unack, T('未确认')), color: unackColor});
					}
					else if (expand === 2) {
						lines.push({text: title || countLine(unack, T('未确认')), color: unackColor});
						lines.push({text: countLine(unack, T('未确认')), color: unackColor});
					}
					else {
						lines.push({text: countLine(unack, T('未确认')), color: unackColor});
					}
				}
				else if (showUnack === 2) {
					// 两者都显示时，未确认那行固定用「未确认问题」色
					lines.push({text: countLine(unack, T('未确认')), color: statusColor('problem')});
				}
			}

			return lines;
		}

		// 一个元素标签占几行（画布内容外接框要把它算进去）；标签文本支持换行
		function labelLineCount(se) {
			var text = elementLabel(se);

			return (text ? String(text).split('\n').length : 0) + elementStatusLines(se).length;
		}

		function elementTitle(se) {
			var parts = [];

			if (se.elementtype === ET_HOST) {
				parts.push(se._hostName || T('未知主机'));

				if (se._status === 1) {
					parts.push(T('已停用'));
				}

				if (se._problems > 0) {
					parts.push(T('当前问题 ') + se._problems + T(' 个'));
				}
			}

			if (se.tags && se.tags.length) {
				parts.push(T('问题标签：') + se.tags.map(function (tag) {
					return tag.tag + (tag.value ? '=' + tag.value : '');
				}).join(T('、')));
			}

			return parts.join(' · ');
		}

		function isEditableElement(se) {
			return se.elementtype === ET_HOST || se.elementtype === ET_IMAGE;
		}

		// ---------------------------------------------------------------
		// 画布尺寸（自动）与画布容器高度（铺满视口）
		// ---------------------------------------------------------------

		// 让画布区域一直顶到视口底部：视口高度 − 容器上方到内容区顶部的距离 − 页脚高度。
		// 不写死 calc(100vh - 235px)，因为页头、工具条、编辑工具条、页脚都可能变高。
		function layoutCanvasWrap() {
			if (!elWrap || elCanvasView.hidden) {
				return;
			}

			// 页面被滚动过时 top 可能是负数，夹到 0 以免算出超过视口的高度
			var top = Math.max(0, elWrap.getBoundingClientRect().top);
			var footer = document.getElementById('page-footer') || document.querySelector('footer');
			var footerH = footer ? footer.offsetHeight : 0;
			var viewportH = document.documentElement.clientHeight || window.innerHeight;
			var avail = viewportH - top - footerH - 12;

			elWrap.style.height = Math.max(300, Math.round(avail)) + 'px';
		}

		// 画布至少要铺满可视区域：这样「画布边界」就落到看不见的地方，
		// 在屏幕上任意位置拖动元素都不会顶到画布宽高。
		function canvasMinSize() {
			// clientWidth/Height 已排除边框，正是画布应该铺满的内容区
			return {
				w: Math.max(MIN_CANVAS_W, elWrap.clientWidth || 0),
				h: Math.max(MIN_CANVAS_H, elWrap.clientHeight || 0)
			};
		}

		function ensureCanvasFitsViewport() {
			var map = state.map;

			if (!map) {
				return;
			}

			var min = canvasMinSize();
			var changed = false;

			if (map.width < min.w) {
				map.width = Math.min(CANVAS_MAX, Math.ceil(min.w));
				changed = true;
			}

			if (map.height < min.h) {
				map.height = Math.min(CANVAS_MAX, Math.ceil(min.h));
				changed = true;
			}

			if (changed) {
				resizeStage();
			}
		}

		function resizeStage() {
			var map = state.map;

			if (!map) {
				return;
			}

			elStage.style.width = map.width + 'px';
			elStage.style.height = map.height + 'px';
			elSvg.setAttribute('width', String(map.width));
			elSvg.setAttribute('height', String(map.height));
			elSvg.setAttribute('viewBox', '0 0 ' + map.width + ' ' + map.height);
			applyView();
		}

		// 内容外接框：元素图标（含下方标签）与形的右下角
		function contentExtent() {
			var map = state.map;
			var right = 0;
			var bottom = 0;

			map.selements.forEach(function (se) {
				var size = iconSize(iconForElement(se));
				var labelH = labelLineCount(se) * 18;

				right = Math.max(right, se.x + size.w);
				bottom = Math.max(bottom, se.y + size.h + labelH);
			});

			map.shapes.forEach(function (shape) {
				right = Math.max(right, shape.x + num(shape.width, 0));
				bottom = Math.max(bottom, shape.y + num(shape.height, 0));
			});

			// 手动折点可能伸到元素/形之外，画布范围也要把它包进来
			map.links.forEach(function (link) {
				normalizeWaypointList(link.waypoints).forEach(function (point) {
					right = Math.max(right, point.x);
					bottom = Math.max(bottom, point.y);
				});
			});

			return {w: right, h: bottom};
		}

		// 拖拽/添加时只在需要时向右下扩展，绝不缩小 —— 所以拖动不会有硬边界
		function growCanvasTo(needW, needH) {
			var map = state.map;
			var changed = false;

			if (!map) {
				return;
			}

			if (needW > map.width) {
				map.width = Math.min(CANVAS_MAX, Math.ceil(needW));
				changed = true;
			}

			if (needH > map.height) {
				map.height = Math.min(CANVAS_MAX, Math.ceil(needH));
				changed = true;
			}

			if (changed) {
				resizeStage();
			}
		}

		// 内容外接框的左上角。往左/往上拖过之后内容会整体偏到右下，
		// 保存前要用它把内容贴回 (0, 0)，否则画布左边/上边会留一片空白。
		function contentMin() {
			var map = state.map;
			var minX = Infinity;
			var minY = Infinity;

			map.selements.forEach(function (se) {
				minX = Math.min(minX, se.x);
				minY = Math.min(minY, se.y);
			});

			map.shapes.forEach(function (shape) {
				minX = Math.min(minX, shape.x);
				minY = Math.min(minY, shape.y);
			});

			map.links.forEach(function (link) {
				normalizeWaypointList(link.waypoints).forEach(function (point) {
					minX = Math.min(minX, point.x);
					minY = Math.min(minY, point.y);
				});
			});

			return {
				x: isFinite(minX) ? Math.max(0, Math.round(minX)) : 0,
				y: isFinite(minY) ? Math.max(0, Math.round(minY)) : 0
			};
		}

		// 保存前把画布收缩到内容大小（不低于可视区域），避免留下一大片空白
		function autoSizeCanvas() {
			var map = state.map;
			var origin;
			var extent;
			var min;

			if (!map) {
				return;
			}

			origin = contentMin();

			if (origin.x > 0 || origin.y > 0) {
				map.selements.forEach(function (se) {
					se.x -= origin.x;
					se.y -= origin.y;
				});

				map.shapes.forEach(function (shape) {
					shape.x -= origin.x;
					shape.y -= origin.y;
				});

				// 折点与元素/形一起平移，否则保存后连线会歪
				map.links.forEach(function (link) {
					(link.waypoints || []).forEach(function (point) {
						point.x = Math.max(0, point.x - origin.x);
						point.y = Math.max(0, point.y - origin.y);
					});
				});

				// 视觉上别跳：pan 反向补偿（内容左移多少，视图就右移多少）
				state.panX += origin.x * state.zoom;
				state.panY += origin.y * state.zoom;
			}

			extent = contentExtent();
			min = canvasMinSize();

			map.width = clamp(Math.ceil(Math.max(extent.w + CANVAS_PAD, min.w)), MIN_CANVAS_W, CANVAS_MAX);
			map.height = clamp(Math.ceil(Math.max(extent.h + CANVAS_PAD, min.h)), MIN_CANVAS_H, CANVAS_MAX);
			resizeStage();

			// 坐标变了，重画一次（openMap / saveMap 都不在拖拽过程中，可以整块重绘）
			paintCanvas();
			applyView();
		}

		// ---------------------------------------------------------------
		// 视图缩放 / 平移
		// ---------------------------------------------------------------

		function applyView() {
			elStage.style.transform = 'translate(' + state.panX + 'px,' + state.panY + 'px) scale(' + state.zoom + ')';
			elZoomReset.textContent = Math.round(state.zoom * 100) + '%';

			if (elCanvasSize && state.map) {
				elCanvasSize.textContent = T('自动 · ') + state.map.width + ' × ' + state.map.height + ' px';
				elCanvasSize.title = T('画布尺寸不固定：最小铺满可视区域，往右/下拖元素时会自动扩展，')
					+ T('拖到边缘时视图会自动跟着平移；保存时收缩到内容大小。这里显示的是当前实际尺寸。');
			}
		}

		// 100%：1 个单位 = 1 像素，从画布左上角开始（和原生视图一致）
		function resetView() {
			state.zoom = 1;
			state.panX = 0;
			state.panY = 0;
			applyView();
		}

		function fitView() {
			var map = state.map;

			if (!map) {
				return;
			}

			var w = elWrap.clientWidth - 40;
			var h = elWrap.clientHeight - 40;

			if (!elWrap.clientWidth || w <= 0 || h <= 0) {
				state.zoom = 1;
				state.panX = 0;
				state.panY = 0;
				applyView();

				return;
			}

			state.zoom = clamp(Math.min(1, w / map.width, h / map.height), 0.2, 3);
			state.panX = Math.max(0, (elWrap.clientWidth - map.width * state.zoom) / 2);
			state.panY = Math.max(0, (elWrap.clientHeight - map.height * state.zoom) / 2);
			applyView();
		}

		function zoomBy(factor) {
			var cw = elWrap.clientWidth / 2;
			var ch = elWrap.clientHeight / 2;
			var next = clamp(state.zoom * factor, 0.2, 3);
			var k = next / state.zoom;

			state.panX = cw - (cw - state.panX) * k;
			state.panY = ch - (ch - state.panY) * k;
			state.zoom = next;
			applyView();
		}

		function viewCenterInMap() {
			return {
				x: (elWrap.clientWidth / 2 - state.panX) / state.zoom,
				y: (elWrap.clientHeight / 2 - state.panY) / state.zoom
			};
		}

		// ---------------------------------------------------------------
		// 拖拽
		// ---------------------------------------------------------------

		function startPan(e) {
			var startX = e.clientX;
			var startY = e.clientY;
			var ox = state.panX;
			var oy = state.panY;

			// 给「折点模式点空白加点」用：拖动画布超过 3px 就不算点击，避免平移后误加折点
			panDragged = false;

			elWrap.classList.add('tm-panning');

			function move(ev) {
				if (Math.abs(ev.clientX - startX) > 3 || Math.abs(ev.clientY - startY) > 3) {
					panDragged = true;
				}

				state.panX = ox + (ev.clientX - startX);
				state.panY = oy + (ev.clientY - startY);
				applyView();
			}

			function up() {
				elWrap.classList.remove('tm-panning');
				document.removeEventListener('mousemove', move);
				document.removeEventListener('mouseup', up);
			}

			document.addEventListener('mousemove', move);
			document.addEventListener('mouseup', up);
		}

		// 拖拽时指针贴住画布边缘就自动平移视图 —— 元素永远能继续往下/往右拖，
		// 不会出现「拖到屏幕边缘就拖不动、还得先松开手去平移」的手感。
		// 往左/往上不靠平移，而是 shiftContent() 把内容整体外推（见下方），
		// 那种情况下 map 坐标不能为负，只能靠「画布向左上长出来」实现无限延伸。
		function autoPan(ev) {
			var rect = elWrap.getBoundingClientRect();

			// 画布还没布局出来（宽或高小到两边都算边缘）时不平移，否则会一直往一个方向漂
			if (rect.width < DRAG_EDGE * 2 || rect.height < DRAG_EDGE * 2) {
				return false;
			}

			var insideX = ev.clientX - rect.left;
			var insideY = ev.clientY - rect.top;
			var moved = false;

			if (insideX > rect.width - DRAG_EDGE) {
				state.panX -= PAN_STEP;
				moved = true;
			}

			if (insideY > rect.height - DRAG_EDGE) {
				state.panY -= PAN_STEP;
				moved = true;
			}

			return moved;
		}

		// 指针位置 → 地图坐标（要算上当前缩放与平移）
		function pointerToMap(ev) {
			var rect = elWrap.getBoundingClientRect();

			return {
				x: (ev.clientX - rect.left - state.panX) / state.zoom,
				y: (ev.clientY - rect.top - state.panY) / state.zoom
			};
		}

		// 内容整体平移：元素/形的坐标同加 (dx, dy)，画布同量变大，视图反向偏移 ——
		// 屏幕上已经画着的元素原地不动，效果等于「画布往左上长出来一块」。
		// sysmaps_elements.x/y 与原生编辑器一样不能为负，所以往左/上无限拖只能这么做。
		function shiftContent(dx, dy) {
			var map = state.map;

			dx = Math.round(dx) || 0;
			dy = Math.round(dy) || 0;

			if (!map || (!dx && !dy)) {
				return false;
			}

			map.selements.forEach(function (se) {
				se.x += dx;
				se.y += dy;
			});

			map.shapes.forEach(function (shape) {
				shape.x += dx;
				shape.y += dy;
			});

			// 折点跟随内容一起平移，绕行关系保持不变
			map.links.forEach(function (link) {
				(link.waypoints || []).forEach(function (point) {
					point.x += dx;
					point.y += dy;
				});
			});

			map.width = Math.min(CANVAS_MAX, map.width + dx);
			map.height = Math.min(CANVAS_MAX, map.height + dy);

			// 视图反向平移，保证屏幕上的内容不跳
			state.panX -= dx * state.zoom;
			state.panY -= dy * state.zoom;

			syncElementNodes();
			// 先形后链接：链接要压在形上面（高亮层夹在形与链接之间）
			drawShapes(map);
			drawHighlights(map);
			drawLinks(map);
			resizeStage();

			return true;
		}

		// 只同步元素节点的 left/top，不重建 DOM（拖拽中的节点引用必须保住）
		function syncElementNodes() {
			var map = state.map;
			var byId = {};
			var nodes;

			if (!map) {
				return;
			}

			map.selements.forEach(function (se) {
				byId[String(se.selementid)] = se;
			});

			nodes = elStage.querySelectorAll('.tm-el');

			for (var i = 0; i < nodes.length; i++) {
				var se = byId[nodes[i].getAttribute('data-id')];

				if (se) {
					nodes[i].style.left = se.x + 'px';
					nodes[i].style.top = se.y + 'px';
				}
			}
		}

		function startElementDrag(se, box, e) {
			var startX = e.clientX;
			var startY = e.clientY;
			// 抓取偏移：保证拖动时元素跟着指针走，指针停在元素哪个点就一直停在那个点
			var startPt = pointerToMap(e);
			var grabX = startPt.x - se.x;
			var grabY = startPt.y - se.y;
			var size = iconSize(iconForElement(se));
			var moved = false;

			function move(ev) {
				if (Math.abs(ev.clientX - startX) > 2 || Math.abs(ev.clientY - startY) > 2) {
					moved = true;
				}

				var panned = autoPan(ev);
				var pt = pointerToMap(ev);
				var nx = Math.round(pt.x - grabX);
				var ny = Math.round(pt.y - grabY);

				// 拖到左/上边界之外：把整张图往右下推，拖的这块贴回 0（视觉上原地不动），
				// 画布同量变宽变高 —— 于是左、上方向也没有边界。
				if (nx < 0 || ny < 0) {
					shiftContent(nx < 0 ? -nx : 0, ny < 0 ? -ny : 0);
					nx = Math.max(0, nx);
					ny = Math.max(0, ny);
				}

				se.x = nx;
				se.y = ny;
				// 拖到当前画布右/下边缘之外就顺手把画布撑大，不设死边界
				growCanvasTo(se.x + size.w + CANVAS_PAD, se.y + size.h + CANVAS_PAD);
				box.style.left = se.x + 'px';
				box.style.top = se.y + 'px';
				syncHighlight(se);
				drawLinks(state.map);

				if (panned) {
					applyView();
				}
			}

			function up() {
				document.removeEventListener('mousemove', move);
				document.removeEventListener('mouseup', up);

				if (moved) {
					markDirty();
					// 拖拽后不应该再触发 click 打开弹窗
					se._suppressClick = true;
					setTimeout(function () {
						se._suppressClick = false;
					}, 0);
				}
			}

			document.addEventListener('mousemove', move);
			document.addEventListener('mouseup', up);
		}

		function startShapeDrag(shape, e) {
			var startX = e.clientX;
			var startY = e.clientY;
			var startPt = pointerToMap(e);
			var grabX = startPt.x - shape.x;
			var grabY = startPt.y - shape.y;
			var moved = false;

			function move(ev) {
				if (Math.abs(ev.clientX - startX) > 2 || Math.abs(ev.clientY - startY) > 2) {
					moved = true;
				}

				var panned = autoPan(ev);
				var pt = pointerToMap(ev);
				var nx = Math.round(pt.x - grabX);
				var ny = Math.round(pt.y - grabY);

				// 同元素拖拽：越过左/上边界时把整张图往右下推，画布跟着长
				if (nx < 0 || ny < 0) {
					shiftContent(nx < 0 ? -nx : 0, ny < 0 ? -ny : 0);
					nx = Math.max(0, nx);
					ny = Math.max(0, ny);
				}

				shape.x = nx;
				shape.y = ny;
				growCanvasTo(shape.x + shape.width + CANVAS_PAD, shape.y + shape.height + CANVAS_PAD);
				repaintSoon();

				if (panned) {
					applyView();
				}
			}

			function up() {
				document.removeEventListener('mousemove', move);
				document.removeEventListener('mouseup', up);

				if (moved) {
					markDirty();
				}
			}

			document.addEventListener('mousemove', move);
			document.addEventListener('mouseup', up);
		}

		var repaintPending = false;
		// 本次按下后是否拖动过画布（折点模式下区分「点击加点」和「拖动平移」）
		var panDragged = false;

		function repaintSoon() {
			if (repaintPending) {
				return;
			}

			repaintPending = true;

			function run() {
				repaintPending = false;

				if (state.map) {
					paintCanvas();
					applyView();
				}
			}

			if (window.requestAnimationFrame) {
				window.requestAnimationFrame(run);
			}
			else {
				setTimeout(run, 16);
			}
		}

		elWrap.addEventListener('mousedown', function (e) {
			if (e.target && (e.target.closest('.tm-el') || e.target.classList.contains('tm-shape')
					|| e.target.classList.contains('tm-shape-handle')
					|| e.target.classList.contains('tm-link-hit') || e.target.classList.contains('tm-waypoint'))) {
				return;
			}

			if (e.button !== 0) {
				return;
			}

			// 点空白处取消形的选中（原生也是点别处就收起点）
			if (state.shapeSel) {
				state.shapeSel = null;
				drawShapeHandles();
			}

			startPan(e);
		});

		// 折点模式：点击空白处追加折点。拖动平移不会触发（panDragged 为 true 时跳过），
		// 点在元素 / 形 / 连线 / 折点圆点上也不会追加。
		elWrap.addEventListener('click', function (e) {
			if (!state.bendLink || !state.editing || panDragged) {
				return;
			}

			var target = e.target;

			if (!target) {
				return;
			}

			if (target.closest && target.closest('.tm-el')) {
				return;
			}

			if (target.classList && (target.classList.contains('tm-shape')
					|| target.classList.contains('tm-link-hit') || target.classList.contains('tm-waypoint'))) {
				return;
			}

			var family = bendFamily();

			if (!family.length) {
				return;
			}

			var pt = pointerToMap(e);
			var points = normalizeWaypointList(family[0].waypoints);

			points.push({x: Math.max(0, Math.round(pt.x)), y: Math.max(0, Math.round(pt.y))});
			setFamilyWaypoints(family, points);
			markDirty();
			drawLinks(state.map);
		});

		// ---------------------------------------------------------------
		// 编辑操作：添加 / 移除
		// ---------------------------------------------------------------

		// 新增元素需要一个「临时 selementid」给链接引用，真实 id 由 map.update 落库时替换。
		// 必须是纯数字且远大于任何真实 id：Zabbix 的 dbConditionId() 会把 selementid 当 bigint 过滤，
		// 传 "n1" 这种字符串在 PostgreSQL 上会直接报类型错误（MySQL 只是隐式转 0）。
		function nextTempId() {
			var id;

			do {
				state.tmpSeq += 1;
				id = String(900000000 + state.tmpSeq);
			} while (state.map.selements.some(function (se) {
				return String(se.selementid) === id;
			}));

			return id;
		}

		function addElement() {
			var map = state.map;
			var center = viewCenterInMap();
			var size = iconSize(state.defaultIcon);

			var se = {
				selementid: nextTempId(),
				elementtype: ET_IMAGE,
				elements: [],
				iconid_off: state.defaultIcon,
				iconid_on: '0',
				iconid_disabled: '0',
				iconid_maintenance: '0',
				label: T('新的组件'),
				label_location: -1,
				x: Math.max(0, Math.round(center.x - size.w / 2)),
				y: Math.max(0, Math.round(center.y - size.h / 2)),
				use_iconmap: 0,
				evaltype: 0,
				tags: [],
				urls: [],
				_hostid: '',
				_hostName: '',
				_status: 0,
				_problems: 0,
				_severity: -1
			};

			map.selements.push(se);
			growCanvasTo(se.x + size.w + CANVAS_PAD, se.y + size.h + CANVAS_PAD);
			markDirty();
			renderCanvas();
			openElementDialog(se, true);
		}

		function addShape() {
			var map = state.map;

			state.tmpSeq += 1;

			// 默认值与原生 Shape 构造器完全一致：10,10 起、50×50、黑框 2px、
			// Helvetica 11 号黑字，形按钮点一下就是这套值再按需改。
			var shape = {
				_tmpid: 's' + state.tmpSeq,
				type: 0,
				x: 10,
				y: 10,
				width: 50,
				height: 50,
				text: '',
				font: 9,
				font_size: 11,
				font_color: '000000',
				text_halign: 0,
				text_valign: 0,
				border_type: 1,
				border_width: 2,
				border_color: '000000',
				background_color: '',
				zindex: map.shapes.length
			};

			map.shapes.push(shape);
			state.shapeSel = shape;
			growCanvasTo(shape.x + shape.width + CANVAS_PAD, shape.y + shape.height + CANVAS_PAD);
			markDirty();
			renderCanvas();
			openShapeDialog(shape, true);
		}

		function removeElement(se) {
			var map = state.map;

			map.selements = map.selements.filter(function (item) {
				return item !== se;
			});

			map.links = map.links.filter(function (link) {
				return String(link.selementid1) !== String(se.selementid) && String(link.selementid2) !== String(se.selementid);
			});

			markDirty();
			renderCanvas();
		}

		function removeShape(shape) {
			state.map.shapes = state.map.shapes.filter(function (item) {
				return item !== shape;
			});

			if (state.shapeSel === shape) {
				state.shapeSel = null;
			}

			markDirty();
			renderCanvas();
		}

		function setRemoveMode(kind) {
			if (state.removeMode === kind) {
				clearModes();
				renderCanvas();

				return;
			}

			state.removeMode = kind;
			state.linkMode = false;
			state.linkFirst = null;
			syncModeButtons();
			setHint(T('移除模式：点击画布上要移除的') + (kind === 'element' ? T('地图元素') : (kind === 'shape' ? T('形') : T('链接'))) + T('（Esc 取消）'));
			renderCanvas();
		}

		function startLinkMode() {
			state.linkMode = true;
			state.removeMode = null;
			state.linkFirst = null;
			syncModeButtons();
			setHint(T('链接模式：依次点击两个元素建立链接（Esc 取消）'));
			renderCanvas();
		}

		function pickLinkEndpoint(se) {
			if (!state.linkFirst) {
				state.linkFirst = se;
				state.selected = se.selementid;
				setHint(T('链接模式：已选中「') + (elementLabel(se) || T('元素')) + T('」，再点击另一个元素'));
				renderCanvas();

				return;
			}

			if (state.linkFirst === se) {
				return;
			}

			var link = {
				selementid1: state.linkFirst.selementid,
				selementid2: se.selementid,
				label: '',
				color: DEFAULT_LINK_COLOR,
				drawtype: 0
			};

			state.map.links.push(link);
			state.linkFirst = null;
			state.linkMode = false;
			state.selected = null;
			markDirty();
			renderCanvas();
			openLinkDialog(link, true);
		}

		// ---------------------------------------------------------------
		// 通用弹层
		// ---------------------------------------------------------------

		function openDialog(options) {
			elDialogTitle.textContent = options.title;
			clear(elDialogBody);
			clear(elDialogFoot);
			elDialog.className = 'tm-dialog' + (options.narrow ? ' tm-dialog-narrow' : '');

			if (options.build) {
				options.build(elDialogBody);
			}

			(options.buttons || []).forEach(function (button) {
				var node = make('button', 'tm-btn' + (button.primary ? ' tm-btn-primary' : ''), button.label);
				node.type = 'button';
				node.addEventListener('click', function () {
					button.onClick();
				});
				elDialogFoot.appendChild(node);
			});

			setHidden(elOverlay, false);
		}

		function closeDialog() {
			setHidden(elOverlay, true);
			clear(elDialogBody);
			clear(elDialogFoot);

			// Esc / 右上角 × / 点遮罩关闭确认框时，按「取消」处理，避免 Promise 悬空
			if (state.pendingConfirm) {
				var resolve = state.pendingConfirm;
				state.pendingConfirm = null;
				resolve(false);
			}
		}

		function dialogForm(body) {
			var table = make('table', 'tm-form');
			var tbody = make('tbody');
			table.appendChild(tbody);
			body.appendChild(table);

			return tbody;
		}

		function formRow(tbody, labelText, required) {
			var tr = make('tr');
			var th = make('th', required ? 'tm-req' : null, labelText);
			var td = make('td');
			tr.appendChild(th);
			tr.appendChild(td);
			tbody.appendChild(tr);

			return {tr: tr, td: td};
		}

		function fieldError(node, text) {
			var err = node.querySelector('.tm-field-error');

			if (!err) {
				err = make('div', 'tm-msg tm-msg-error');
				err.style.marginTop = '4px';
				node.appendChild(err);
			}

			err.textContent = text;
		}

		// ---------------------------------------------------------------
		// 属性（重命名 / 删除）
		// ---------------------------------------------------------------

		function openPropertiesDialog(map) {
			openDialog({
				title: T('拓扑图属性'),
				build: function (body) {
					var tbody = dialogForm(body);
					var nameRow = formRow(tbody, T('名称'), true);
					var input = make('input', 'tm-input tm-input-block');
					input.type = 'text';
					input.maxLength = 128;
					input.value = map.name;
					nameRow.td.appendChild(input);

					// 画布尺寸改成自动：拖动时会扩展，保存时收紧到内容大小，不再让用户填数字
					var sizeRow = formRow(tbody, T('画布尺寸'), false);
					sizeRow.td.appendChild(make('div', 'tm-size-auto',
						T('自动（当前 ') + map.width + ' × ' + map.height + T(' px）')));
					sizeRow.td.appendChild(make('div', 'tm-hint',
						T('往右 / 往下拖元素时画布会跟着变大，保存时按内容收紧，不会留下大片空白')));

					var warn = make('div', 'tm-msg');
					warn.style.marginTop = '6px';
					nameRow.td.appendChild(warn);

					setTimeout(function () {
						input.focus();
						input.select();
					}, 0);

					openDialog.rename = function () {
						var name = input.value.trim();

						if (!name) {
							warn.className = 'tm-msg tm-msg-error';
							warn.textContent = T('名称不能为空');

							return;
						}

						api('rename', {sysmapid: map.sysmapid, name: name}).then(function () {
							closeDialog();
							loadList();
							msg(elListMsg, T('已保存「') + name + T('」的属性'), 'ok');
						}).catch(function (e) {
							warn.className = 'tm-msg tm-msg-error';
							warn.textContent = T('保存失败：') + e.message;
						});
					};

					openDialog.remove = function () {
						confirmDialog(T('删除拓扑图？'), T('将永久删除「') + map.name + T('」及其所有元素、链接、形。此操作不可撤销。'), T('删除')).then(function (ok) {
							if (!ok) {
								return;
							}

							api('delete', {sysmapid: map.sysmapid}).then(function () {
								closeDialog();
								delete state.checked[map.sysmapid];
								loadList();
								msg(elListMsg, T('已删除「') + map.name + T('」'), 'ok');
							}).catch(function (e) {
								warn.className = 'tm-msg tm-msg-error';
								warn.textContent = T('删除失败：') + e.message;
							});
						});
					};
				},
				buttons: [
					{label: T('应用'), primary: true, onClick: function () { openDialog.rename(); }},
					{label: T('删除'), onClick: function () { openDialog.remove(); }},
					{label: T('关闭'), onClick: closeDialog}
				]
			});
		}

		// ---------------------------------------------------------------
		// 元素属性弹窗（对照原生「地图元素」表单）
		// ---------------------------------------------------------------

		function openElementDialog(se, isNew) {
			var draft = copy(se);
			var hostRows = [];
			var iconExtraRows = [];

			openDialog({
				title: T('元素属性'),
				build: function (body) {
					var tbody = dialogForm(body);

					// 类型
					var typeRow = formRow(tbody, T('类型'), false);
					var typeSel = makeSelect([[ET_IMAGE, T('图片')], [ET_HOST, T('主机')]], draft.elementtype, 'tm-w-200');
					typeSel.style.width = '180px';
					typeRow.td.appendChild(typeSel);

					// 标签：选择主机后自动写入「主机名 + IP」，用户手改过就不再覆盖
					var labelTouched = !!String(draft.label || '').trim() && draft.label !== T('新的组件');

					var labelRow = formRow(tbody, T('标签'), false);
					var labelInput = make('textarea');
					labelInput.rows = 2;
					labelInput.value = draft.label || '';
					labelInput.maxLength = 2048;
					labelInput.placeholder = T('可换行；选择主机后会自动填「主机名 + IP」');
					labelInput.addEventListener('input', function () {
						labelTouched = true;
					});
					labelRow.td.appendChild(labelInput);

					// 标签位置
					var locRow = formRow(tbody, T('标签位置'), false);
					var locSel = makeSelect(LABEL_LOCS, draft.label_location === undefined ? -1 : draft.label_location);
					locSel.style.width = '180px';
					locRow.td.appendChild(locSel);

					// 主机
					var hostRow = formRow(tbody, T('主机'), true);
					var hostLine = make('div', 'tm-inline');
					var hostInput = make('input', 'tm-input tm-grow');
					hostInput.type = 'text';
					hostInput.placeholder = T('在此输入搜索');
					hostInput.value = draft._hostName || '';
					hostInput.readOnly = true;
					var hostBtn = make('button', 'tm-btn', T('选择'));
					hostBtn.type = 'button';
					hostLine.appendChild(hostInput);
					hostLine.appendChild(hostBtn);
					hostRow.td.appendChild(hostLine);
					hostRows.push(hostRow.tr);
					openElementDialog.hostTd = hostRow.td;

					hostBtn.addEventListener('click', function () {
						openHostPicker(function (host) {
							draft._hostid = host.hostid;
							draft._hostName = host.name;
							draft._ip = host.ip || '';
							draft.elements = [{hostid: host.hostid}];
							hostInput.value = host.name + (host.ip ? T('（') + host.ip + T('）') : '');

							// 标签是空 / 还是「新的组件」占位时，自动填「主机名 + IP」两行
							if (!labelTouched) {
								labelInput.value = host.ip ? host.name + '\n' + host.ip : host.name;
							}
						});
					});

					// 问题标签
					var tagRow = formRow(tbody, T('问题标签'), false);
					var tagsBox = make('div', 'tm-tags');
					var evalLine = make('div', 'tm-tags-eval');
					var evalName = 'tm-eval-' + Date.now();
					[[0, T('与/或 (默认)')], [2, T('或')]].forEach(function (pair) {
						var lab = make('label');
						var radio = make('input');
						radio.type = 'radio';
						radio.name = evalName;
						radio.value = String(pair[0]);
						radio.checked = num(draft.evaltype, 0) === pair[0];
						lab.appendChild(radio);
						lab.appendChild(document.createTextNode(pair[1]));
						evalLine.appendChild(lab);
					});
					tagsBox.appendChild(evalLine);

					var tagList = make('div');
					tagsBox.appendChild(tagList);

					var addTag = make('button', 'tm-linkbtn tm-tags-add', T('添加'));
					addTag.type = 'button';
					addTag.addEventListener('click', function () {
						tagList.appendChild(buildTagRow({tag: '', operator: 0, value: ''}));
					});
					tagsBox.appendChild(addTag);
					tagRow.td.appendChild(tagsBox);
					hostRows.push(tagRow.tr);

					(draft.tags || []).forEach(function (tag) {
						tagList.appendChild(buildTagRow(tag));
					});

					// 自动选择图标
					var autoRow = formRow(tbody, T('自动选择图标'), false);
					var autoLine = make('label', 'tm-checkline');
					var autoBox = make('input');
					autoBox.type = 'checkbox';
					autoBox.checked = num(draft.use_iconmap, 0) === 1;
					autoLine.appendChild(autoBox);
					autoRow.td.appendChild(autoLine);
					hostRows.push(autoRow.tr);

					// 图标
					var iconRow = formRow(tbody, T('图标'), false);
					var iconBox = make('div', 'tm-icons-box');
					var iconTable = make('table', 'tm-icons-table');
					var iconBody = make('tbody');
					iconTable.appendChild(iconBody);
					iconBox.appendChild(iconTable);

					var iconOff = buildIconSelect(draft.iconid_off, false);
					var iconOn = buildIconSelect(draft.iconid_on, true);
					var iconMaint = buildIconSelect(draft.iconid_maintenance, true);
					var iconDisabled = buildIconSelect(draft.iconid_disabled, true);

					appendIconRow(iconBody, T('默认'), iconOff, null);
					appendIconRow(iconBody, T('问题'), iconOn, iconExtraRows);
					appendIconRow(iconBody, T('维护'), iconMaint, iconExtraRows);
					appendIconRow(iconBody, T('停用'), iconDisabled, iconExtraRows);

					iconRow.td.appendChild(iconBox);

					// 类型切换：图片只留「默认」图标；主机显示主机/标签/自动图标/四个图标
					function syncType() {
						var type = num(typeSel.value, ET_IMAGE);
						var isHost = type === ET_HOST;

						hostRows.forEach(function (tr) {
							setHidden(tr, !isHost);
						});

						iconExtraRows.forEach(function (tr) {
							setHidden(tr, !isHost);
						});

						var autoMode = isHost && autoBox.checked;
						[iconOff, iconOn, iconMaint, iconDisabled].forEach(function (sel) {
							sel.disabled = autoMode;
						});
					}

					typeSel.addEventListener('change', syncType);
					autoBox.addEventListener('change', syncType);
					syncType();

					// 弹窗内部状态挂到闭包里，供底部按钮读取
					openElementDialog.collect = function () {
						draft.elementtype = num(typeSel.value, ET_IMAGE);
						draft.label = labelInput.value;
						draft.label_location = num(locSel.value, -1);
						draft.use_iconmap = autoBox.checked ? 1 : 0;
						draft.iconid_off = String(iconOff.value);
						draft.iconid_on = String(iconOn.value);
						draft.iconid_maintenance = String(iconMaint.value);
						draft.iconid_disabled = String(iconDisabled.value);
						draft.evaltype = 0;
						draft.tags = [];

						var checked = evalLine.querySelector('input:checked');

						if (checked) {
							draft.evaltype = num(checked.value, 0);
						}

						var rows = tagList.querySelectorAll('.tm-tag-row');

						for (var i = 0; i < rows.length; i++) {
							var name = rows[i].querySelector('.tm-tag-name').value.trim();
							var value = rows[i].querySelector('.tm-tag-value').value;

							if (!name) {
								continue;
							}

							draft.tags.push({
								tag: name,
								operator: num(rows[i].querySelector('select').value, 0),
								value: value
							});
						}

						if (draft.elementtype === ET_IMAGE) {
							draft.elements = [];
							draft.tags = [];
							draft.use_iconmap = 0;
							draft.iconid_on = '0';
							draft.iconid_maintenance = '0';
							draft.iconid_disabled = '0';
						}
						else if (!draft._hostid) {
							return T('请选择主机');
						}
						else {
							draft.elements = [{hostid: draft._hostid}];
						}

						return null;
					};
				},
				buttons: [
					{label: T('应用'), primary: true, onClick: function () { applyElement(); }},
					{label: T('移除'), onClick: function () { closeDialog(); removeElement(se); }},
					{label: T('关闭'), onClick: closeDialog}
				]
			});

			function applyElement() {
				var err = openElementDialog.collect();

				if (err) {
					if (openElementDialog.hostTd) {
						fieldError(openElementDialog.hostTd, err);
					}

					return;
				}

				draft._status = se._status;
				draft._problems = se._problems;
				draft._severity = se._severity;

				if (draft._hostid) {
					var host = state.map.hosts[draft._hostid];

					if (host) {
						// 换了主机就把状态/问题一起换成新主机的，别沿用旧主机的问题数
						draft._hostName = host.name;
						draft._ip = String(host.ip || draft._ip || '');
						draft._status = num(host.status, 0);
						draft._maintenance = num(host.maintenance, 0);
						draft._problems = num(host.problems, 0);
						draft._unack = num(host.unack, 0);
						draft._severity = num(host.severity, -1);
						draft._title = host.title || '';
					}
				}
				else {
					draft._hostName = '';
				}

				Object.keys(draft).forEach(function (key) {
					se[key] = draft[key];
				});
				se._suppressClick = false;

				markDirty();
				closeDialog();
				renderCanvas();
			}

			function buildTagRow(tag) {
				var row = make('div', 'tm-tag-row');

				var name = make('input', 'tm-input tm-tag-name');
				name.type = 'text';
				name.placeholder = T('标记');
				name.value = tag.tag || '';
				name.maxLength = 255;

				var op = makeSelect(TAG_OPERATORS, num(tag.operator, 0));

				var value = make('input', 'tm-input tm-tag-value');
				value.type = 'text';
				value.placeholder = T('值');
				value.value = tag.value || '';
				value.maxLength = 255;

				var del = make('button', 'tm-linkbtn', T('移除'));
				del.type = 'button';
				del.addEventListener('click', function () {
					row.parentNode.removeChild(row);
				});

				row.appendChild(name);
				row.appendChild(op);
				row.appendChild(value);
				row.appendChild(del);

				return row;
			}

			function buildIconSelect(value, withDefault) {
				var options = [];

				if (withDefault) {
					options.push(['0', T('默认')]);
				}

				state.icons.forEach(function (icon) {
					options.push([icon.imageid, icon.name]);
				});

				if (!options.length) {
					options.push(['0', T('（无可用图标）')]);
				}

				return makeSelect(options, String(value || '0'));
			}

			function appendIconRow(tbody, title, select, bucket) {
				var tr = make('tr');
				var td1 = make('td', null, title);
				var td2 = make('td');
				td2.appendChild(select);
				tr.appendChild(td1);
				tr.appendChild(td2);
				tbody.appendChild(tr);

				if (bucket) {
					bucket.push(tr);
				}
			}
		}

		// ---------------------------------------------------------------
		// 形属性弹窗
		// ---------------------------------------------------------------

		function openShapeDialog(shape, isNew) {
			var draft = copy(shape);

			openDialog({
				title: T('拓扑图形状'),
				build: function (body) {
					var tbody = dialogForm(body);

					// 形：矩形 / 椭圆 / 线 —— 原生是并排的三段按钮
					var typeRow = formRow(tbody, T('形'), false);
					var seg = make('div', 'tm-seg');
					var typeButtons = [];

					SHAPE_TYPES.forEach(function (pair) {
						var btn = make('button', 'tm-seg-item', pair[1]);

						btn.type = 'button';
						btn.setAttribute('data-shape-type', String(pair[0]));
						btn.addEventListener('click', function () {
							setShapeType(pair[0]);
						});
						seg.appendChild(btn);
						typeButtons.push(btn);
					});
					typeRow.td.appendChild(seg);

					var textRow = formRow(tbody, T('文本'), false);
					var textInput = make('textarea');
					textInput.rows = 2;
					textInput.maxLength = 255;
					textInput.value = draft.text || '';
					textRow.td.appendChild(textInput);

					// 字体 / 字体大小 / 颜色（原生放在文本下方，没有单独行标题）
					var fontRow = formRow(tbody, '');
					var fontLine = make('div', 'tm-inline');
					var fontSel = makeSelect(SHAPE_FONT_NAMES.map(function (name, index) {
						return [index, name];
					}), draft.font);
					fontSel.style.width = '150px';
					var fontColor = buildColorInput(draft.font_color);
					var fontSize = make('input', 'tm-input');
					fontSize.type = 'text';
					fontSize.value = String(draft.font_size || 11);
					fontSize.style.width = '52px';
					fontLine.appendChild(make('span', 'tm-hint', T('字体')));
					fontLine.appendChild(fontSel);
					fontLine.appendChild(make('span', 'tm-hint', T('字体大小')));
					fontLine.appendChild(fontSize);
					fontLine.appendChild(make('span', 'tm-hint', T('颜色')));
					fontLine.appendChild(fontColor.wrap);
					fontRow.td.appendChild(fontLine);

					var alignRow = formRow(tbody, '');
					var alignLine = make('div', 'tm-inline');
					var halignSel = makeSelect([[0, T('居中')], [1, T('左')], [2, T('右')]], draft.text_halign);
					var valignSel = makeSelect([[0, T('居中')], [1, T('上')], [2, T('下')]], draft.text_valign);
					alignLine.appendChild(make('span', 'tm-hint', T('横向对齐')));
					alignLine.appendChild(halignSel);
					alignLine.appendChild(make('span', 'tm-hint', T('纵向对齐')));
					alignLine.appendChild(valignSel);
					alignRow.td.appendChild(alignLine);

					var fillRow = formRow(tbody, T('背景'));
					var fillColor = buildColorInput(draft.background_color);
					var fillLine = make('div', 'tm-inline');
					fillLine.appendChild(make('span', 'tm-hint', T('颜色')));
					fillLine.appendChild(fillColor.wrap);
					fillRow.td.appendChild(fillLine);

					var borderRow = formRow(tbody, T('边框'));
					var borderLabel = borderRow.tr.firstChild;
					var borderLine = make('div', 'tm-inline');
					var borderSel = makeSelect(SHAPE_BORDERS, draft.border_type);
					var borderColor = buildColorInput(draft.border_color);
					var borderWidth = make('input', 'tm-input');
					borderWidth.type = 'text';
					borderWidth.value = String(num(draft.border_width, 2));
					borderWidth.style.width = '52px';
					borderLine.appendChild(make('span', 'tm-hint', T('类型')));
					borderLine.appendChild(borderSel);
					borderLine.appendChild(make('span', 'tm-hint', T('宽(px)')));
					borderLine.appendChild(borderWidth);
					borderLine.appendChild(make('span', 'tm-hint', T('颜色')));
					borderLine.appendChild(borderColor.wrap);
					borderRow.td.appendChild(borderLine);

					var coordRow = formRow(tbody, T('坐标'));
					var coordLabel = coordRow.tr.firstChild;
					var coordLine = make('div', 'tm-inline');
					var xLabel = make('span', 'tm-hint', 'X');
					var yLabel = make('span', 'tm-hint', 'Y');
					var xInput = make('input', 'tm-input');
					xInput.type = 'text';
					xInput.value = String(draft.x);
					xInput.style.width = '70px';
					var yInput = make('input', 'tm-input');
					yInput.type = 'text';
					yInput.value = String(draft.y);
					yInput.style.width = '70px';
					coordLine.appendChild(xLabel);
					coordLine.appendChild(xInput);
					coordLine.appendChild(yLabel);
					coordLine.appendChild(yInput);
					coordRow.td.appendChild(coordLine);

					var sizeRow = formRow(tbody, T('大小'));
					var sizeLine = make('div', 'tm-inline');
					var wLabel = make('span', 'tm-hint', T('宽(px)'));
					var hLabel = make('span', 'tm-hint', T('高度(px)'));
					var wInput = make('input', 'tm-input');
					wInput.type = 'text';
					wInput.value = String(draft.width);
					wInput.style.width = '70px';
					var hInput = make('input', 'tm-input');
					hInput.type = 'text';
					hInput.value = String(draft.height);
					hInput.style.width = '70px';
					sizeLine.appendChild(wLabel);
					sizeLine.appendChild(wInput);
					sizeLine.appendChild(hLabel);
					sizeLine.appendChild(hInput);
					sizeRow.td.appendChild(sizeLine);

					// 线的语义和图形不同：x,y 是起点、width,height 是终点；
					// 原生切到线时把「宽/高」换算成终点的绝对坐标，切回来再取外接框。
					function setShapeType(type) {
						var isLine = type === 2;
						var wasLine = draft.type === 2;

						if (!wasLine && isLine) {
							wInput.value = String(num(xInput.value, 0) + num(wInput.value, 0));
							hInput.value = String(num(yInput.value, 0) + num(hInput.value, 0));
						}
						else if (wasLine && !isLine) {
							var ax = num(xInput.value, 0);
							var ay = num(yInput.value, 0);
							var bx = num(wInput.value, 0);
							var by = num(hInput.value, 0);
							var left = Math.min(ax, bx);
							var top = Math.min(ay, by);

							xInput.value = String(left);
							yInput.value = String(top);
							wInput.value = String(Math.max(ax, bx) - left);
							hInput.value = String(Math.max(ay, by) - top);
						}

						draft.type = type;

						typeButtons.forEach(function (btn) {
							btn.classList.toggle('tm-seg-active', num(btn.getAttribute('data-shape-type'), 0) === type);
							btn.setAttribute('aria-pressed', String(num(btn.getAttribute('data-shape-type'), 0) === type));
						});

						// 线没有文本和背景，标签也换成原生的「线 / 点 / X1,Y1 / X2,Y2」
						setHidden(textRow.tr, isLine);
						setHidden(fillRow.tr, isLine);
						borderLabel.textContent = isLine ? T('线') : T('边框');
						coordLabel.textContent = isLine ? T('点') : T('坐标');
						xLabel.textContent = isLine ? 'X1' : 'X';
						yLabel.textContent = isLine ? 'Y1' : 'Y';
						wLabel.textContent = isLine ? 'X2' : T('宽(px)');
						hLabel.textContent = isLine ? 'Y2' : T('高度(px)');
					}

					var active = SHAPE_TYPES.some(function (pair) {
						return pair[0] === draft.type;
					}) ? draft.type : 0;

					setShapeType(active);

					openShapeDialog.collect = function () {
						draft.text = textInput.value;
						draft.font = Math.max(0, Math.min(SHAPE_FONT_NAMES.length - 1, num(fontSel.value, 9)));
						draft.font_size = Math.max(1, num(fontSize.value, 11));
						draft.font_color = fontColor.value() || '000000';
						draft.text_halign = num(halignSel.value, 0);
						draft.text_valign = num(valignSel.value, 0);
						draft.background_color = fillColor.value();
						draft.border_type = num(borderSel.value, 1);
						draft.border_width = Math.max(0, num(borderWidth.value, 2));
						draft.border_color = borderColor.value() || '000000';
						draft.x = Math.max(0, num(xInput.value, draft.x));
						draft.y = Math.max(0, num(yInput.value, draft.y));

						// 线的终点允许 0；矩形/椭圆最小 1（与原生一致）
						var minSize = draft.type === 2 ? 0 : 1;
						draft.width = Math.max(minSize, num(wInput.value, draft.width));
						draft.height = Math.max(minSize, num(hInput.value, draft.height));

						return null;
					};
				},
				buttons: [
					{label: T('应用'), primary: true, onClick: function () {
						openShapeDialog.collect();

						Object.keys(draft).forEach(function (key) {
							shape[key] = draft[key];
						});

						markDirty();
						closeDialog();
						renderCanvas();
					}},
					{label: T('移除'), onClick: function () {
						closeDialog();
						removeShape(shape);
					}},
					{label: T('关闭'), onClick: closeDialog}
				]
			});
		}

		// ---------------------------------------------------------------
		// 链接属性弹窗
		// ---------------------------------------------------------------

		function openLinkDialog(link, isNew) {
			// 整组连线共用一个对话框：同一对元素之间的多条线（链路聚合的成员口）
			// 在这里一次改完，不做逐条编辑。
			var family = linkFamily(link);
			var draft = copy(link);
			// 触发器也是整组共用一份：取组里第一个有绑定的成员
			var triggersEdited = false;

			draft.metrics = familyMetrics(family);
			draft.label = familyLabel(family);
			draft.linktriggers = familyLinkTriggers(family);
			draft.routing = Math.max(0, Math.min(2, num(draft.routing, 0)));
			draft.waypoints = normalizeWaypointList(draft.waypoints);

			openDialog({
				title: T('链接属性'),
				build: function (body) {
					var tbody = dialogForm(body);

					if (family.length > 1) {
						var groupHint = make('div', 'tm-hint');
						groupHint.textContent = T('这条链路共 ') + family.length
							+ T(' 条成员连线（链路聚合），修改会同时应用到全部成员。');
						body.appendChild(groupHint);
					}

					var labelRow = formRow(tbody, T('标签'), false);
					var labelInput = make('input', 'tm-input tm-input-block');
					labelInput.type = 'text';
					labelInput.maxLength = 2048;
					labelInput.value = draft.label || '';
					labelRow.td.appendChild(labelInput);

					var colorRow = formRow(tbody, T('颜色'), false);
					var color = buildColorInput(draft.color || DEFAULT_LINK_COLOR);
					colorRow.td.appendChild(color.wrap);

					var typeRow = formRow(tbody, T('线型'), false);
					var typeSel = makeSelect(LINK_DRAWTYPES, draft.drawtype);
					typeSel.style.width = '180px';
					typeRow.td.appendChild(typeSel);

					// 走线：自动曲线 / 自动直角 / 手动折点。选手动后点按钮进画布加折点。
					var routeRow = formRow(tbody, T('走线'), false);
					var routeWrap = make('div', 'tm-metric-row');
					var routeSel = makeSelect(LINK_ROUTINGS, num(draft.routing, 0));
					routeSel.style.width = '180px';
					var bendBtn = make('button', 'tm-btn tm-btn-sm', T('在画布上点选折点'));
					bendBtn.type = 'button';

					bendBtn.addEventListener('click', function () {
						openLinkDialog.collect();

						// 整组共用一份走线：切到手动折点后进画布，点击空白处加点
						family.forEach(function (item) {
							item.routing = 2;
							item.waypoints = normalizeWaypointList(item.waypoints);
						});

						state.bendLink = family[0];
						markDirty();
						closeDialog();
						renderCanvas();
					});

					routeWrap.appendChild(routeSel);
					routeWrap.appendChild(bendBtn);
					routeRow.td.appendChild(routeWrap);

					var routeHint = make('div', 'tm-hint');
					routeHint.textContent = T('自动直角按横/竖折线走；选「手动折点」后点按钮，')
						+ T('在画布空白处点击加点、拖动圆点调整、双击圆点删除，Esc 完成。');
					routeRow.td.appendChild(routeHint);

					// 触发器来源端：连线方向是 源端(selementid1) → 本端(selementid2)。
					// 自动发现默认按「源端优先」匹配端口状态类触发器；这里可改成只按某端、
					// 两端都关联或干脆不关联（改完点「应用」会立刻重新匹配一次）。
					var srcRow = formRow(tbody, T('触发器来源'), false);
					var srcSel = makeSelect(TRIGGER_SOURCES, num(draft.trigger_source, 0));
					srcSel.style.width = '220px';
					srcRow.td.appendChild(srcSel);

					// 端口信息只在对话框里展示（画布上不再标注端口）：聚合时列出全部成员口
					var ports = familyPorts(family);
					var portHint = make('div', 'tm-hint');
					portHint.textContent = T('源端端口：') + (ports.src.length ? ports.src.join(T('、')) : T('未知'))
						+ T('　本端端口：') + (ports.tgt.length ? ports.tgt.join(T('、')) : T('未知'))
						+ T('（源端 = 连线起点，本端 = 连线终点）');
					srcRow.td.appendChild(portHint);

					// 带宽 / 上行 / 下行：从两端主机（聚合成员口）的监控项里手工多选，
					// 显示时把选中的监控项最新值求和，再按单位换算。
					var rows = {};

					METRIC_KINDS.forEach(function (kind) {
						var defRow = formRow(tbody, kind.label, false);
						var wrap = make('div', 'tm-metric-row');
						var summary = make('span', 'tm-metric-summary');
						var pick = make('button', 'tm-btn tm-btn-sm', T('选择…'));
						pick.type = 'button';
						var clearBtn = make('button', 'tm-btn tm-btn-sm', T('清空'));
						clearBtn.type = 'button';

						function refresh() {
							summary.textContent = metricSummaryText(draft.metrics[kind.key]);
							summary.title = metricSummaryTitle(draft.metrics[kind.key]);
						}

						pick.addEventListener('click', function () {
							openItemPicker(kind.key, defaultPickHostids(link), draft.metrics[kind.key], function (itemids) {
								draft.metrics[kind.key] = itemids;
								refresh();
							});
						});

						clearBtn.addEventListener('click', function () {
							draft.metrics[kind.key] = [];
							refresh();
						});

						wrap.appendChild(summary);
						wrap.appendChild(pick);
						wrap.appendChild(clearBtn);
						defRow.td.appendChild(wrap);
						rows[kind.key] = refresh;
					});

					var tip = make('div', 'tm-hint');
					tip.textContent = T('监控项只从这条链路两端的主机里选；链路聚合的多个成员口可以全选，显示时求和。');
					body.appendChild(tip);

					Object.keys(rows).forEach(function (key) {
						rows[key]();
					});

					// 触发器：手工绑定。自动关联只认端口状态关键词 + 端口名，手工建的线
					// （比如新加的一台电脑接到交换机）没有端口信息，只能在这里直接挑。
					// 绑上之后，这条线在触发器报警时同样会变红（和自动关联的效果一致）。
					var trigRow = formRow(tbody, T('触发器'), false);
					var trigWrap = make('div', 'tm-metric-row');
					var trigBox = make('div', 'tm-pick-box tm-trig-box');
					var trigPick = make('button', 'tm-btn tm-btn-sm', T('选择…'));
					trigPick.type = 'button';
					var trigClear = make('button', 'tm-btn tm-btn-sm', T('清空'));
					trigClear.type = 'button';
					var trigNames = {};

					function renderTriggerChips() {
						var ids = (draft.linktriggers || []).map(function (lt) {
							return String(lt.triggerid);
						});

						ids.forEach(function (id) {
							trigNames[id] = triggerName(id);
						});

						renderChips(trigBox, ids, trigNames, T('未关联触发器'), function (id) {
							draft.linktriggers = (draft.linktriggers || []).filter(function (lt) {
								return String(lt.triggerid) !== id;
							});
							triggersEdited = true;
							renderTriggerChips();
						});
					}

					trigPick.addEventListener('click', function () {
						openTriggerPicker(linkEndHostids(link), draft.linktriggers, function (triggerids) {
							var previous = {};

							(draft.linktriggers || []).forEach(function (lt) {
								previous[String(lt.triggerid)] = lt;
							});

							draft.linktriggers = triggerids.map(function (tid) {
								var old = previous[tid] || {};

								return {
									triggerid: String(tid),
									drawtype: num(old.drawtype, 0),
									color: String(old.color || DEFAULT_TRIGGER_COLOR).replace('#', ''),
									// 手工挑的触发器如果正在报警，画布上立刻按触发器色画（和自动关联一致）
									problem: !!state.triggerProblems[tid]
								};
							});
							triggersEdited = true;
							renderTriggerChips();
						});
					});

					trigClear.addEventListener('click', function () {
						draft.linktriggers = [];
						triggersEdited = true;
						renderTriggerChips();
					});

					trigWrap.appendChild(trigBox);
					trigWrap.appendChild(trigPick);
					trigWrap.appendChild(trigClear);
					trigRow.td.appendChild(trigWrap);

					var trigHint = make('div', 'tm-hint');
					trigHint.textContent = T('自动关联只匹配端口状态类关键词（link down / 处于关闭状态 / 接口关闭…）')
						+ T('并按连线上的端口名定位；手工建的连线在这里直接选，保存后不会被自动匹配覆盖。');
					trigRow.td.appendChild(trigHint);

					// 库里只存 triggerid，名字按 triggerid 批量取一次再刷新 chip
					resolveTriggerNames((draft.linktriggers || []).map(function (lt) {
						return String(lt.triggerid);
					})).then(renderTriggerChips);
					renderTriggerChips();

					openLinkDialog.collect = function () {
						draft.label = labelInput.value;
						draft.color = color.value() || DEFAULT_LINK_COLOR;
						draft.drawtype = num(typeSel.value, 0);
						draft.routing = Math.max(0, Math.min(2, num(routeSel.value, 0)));
						draft.trigger_source = num(srcSel.value, 0);
					};
				},
				buttons: [
					{label: T('应用'), primary: true, onClick: function () {
						var previousTriggerSource = num(link.trigger_source, 0);

						openLinkDialog.collect();

						// 整组一起改：标签 / 颜色 / 线型 / 触发器来源 / 绑定触发器对所有成员生效
						var linktriggers = (draft.linktriggers || []).map(function (lt) {
							return {
								triggerid: String(lt.triggerid),
								drawtype: num(lt.drawtype, 0),
								color: String(lt.color || DEFAULT_TRIGGER_COLOR).replace('#', ''),
								problem: !!lt.problem
							};
						});

						// 整组共用一份折点：应用时把同一组坐标写给每个成员（保存时各自按 linkid 落库）
						var waypoints = normalizeWaypointList(draft.waypoints);

						family.forEach(function (item) {
							item.label = draft.label;
							item.color = draft.color;
							item.drawtype = draft.drawtype;
							item.routing = draft.routing;
							item.waypoints = waypoints.map(function (p) {
								return {x: p.x, y: p.y};
							});
							item.trigger_source = draft.trigger_source;
							item.linktriggers = copy(linktriggers);
						});

						// 指标只挂在组里第一条连线上（整组共用一份），避免同一批监控项被重复求和
						var metrics = normalizeMetrics(draft.metrics);

						family.forEach(function (item, i) {
							item.metrics = i === 0 ? metrics : normalizeMetrics(null);
						});

						markDirty();
						closeDialog();
						renderCanvas();

						// 只有用户真的改了「触发器来源」才重新匹配：避免随手改个标签/颜色
						// 就把人家在原生界面手工绑好的触发器冲掉。
						// 本次手工挑过触发器（选择/清空/chip 的 ×）就一律不再重匹配。
						if (!triggersEdited && num(link.trigger_source, 0) !== previousTriggerSource) {
							family.forEach(rematchLinkTriggers);
						}
					}},
					{label: T('移除'), onClick: function () {
						closeDialog();
						removeLinkFamily(link);
					}},
					{label: T('关闭'), onClick: closeDialog}
				]
			});
		}

		function buildColorInput(hex) {
			var wrap = make('div', 'tm-colorline');
			var picker = make('input');
			picker.type = 'color';
			picker.value = '#' + String(hex || '7A8B94').replace('#', '');
			var text = make('input', 'tm-input');
			text.type = 'text';
			text.value = String(hex || '').replace('#', '');
			text.maxLength = 6;

			picker.addEventListener('input', function () {
				text.value = picker.value.replace('#', '').toUpperCase();
			});

			text.addEventListener('input', function () {
				var v = text.value.replace(/[^0-9a-fA-F]/g, '').slice(0, 6);

				if (v.length === 6) {
					picker.value = '#' + v;
				}
			});

			wrap.appendChild(picker);
			wrap.appendChild(text);

			return {
				wrap: wrap,
				value: function () {
					var v = text.value.replace(/[^0-9a-fA-F]/g, '').toUpperCase();

					return v.length === 6 ? v : '';
				}
			};
		}

		// ---------------------------------------------------------------
		// 提示 / 确认弹窗
		// ---------------------------------------------------------------

		function openNoticeDialog(title, text) {
			openDialog({
				title: title,
				narrow: true,
				build: function (body) {
					var p = make('div', null, text);
					p.style.lineHeight = '1.6';
					body.appendChild(p);
				},
				buttons: [{label: T('关闭'), primary: true, onClick: closeDialog}]
			});
		}

		function confirmDialog(title, text, okLabel) {
			return new Promise(function (resolve) {
				function done(value) {
					state.pendingConfirm = null;
					closeDialog();
					resolve(value);
				}

				state.pendingConfirm = function () {
					resolve(false);
				};

				openDialog({
					title: title,
					narrow: true,
					build: function (body) {
						var p = make('div', null, text);
						p.style.lineHeight = '1.6';
						body.appendChild(p);
					},
					buttons: [
						{label: okLabel || T('确定'), primary: true, onClick: function () { done(true); }},
						{label: T('取消'), onClick: function () { done(false); }}
					]
				});
			});
		}

		// ---------------------------------------------------------------
		// 主机选择器
		// ---------------------------------------------------------------

		function openHostPicker(callback) {
			state.hostPick = callback;
			setHidden(elHostOverlay, false);
			elHostSearch.value = '';
			loadHosts('');
			setTimeout(function () { elHostSearch.focus(); }, 0);
		}

		var hostSearchTimer = null;

		function loadHosts(search) {
			clear(elHostList);
			elHostList.appendChild(make('div', 'tm-host-empty', T('加载中…')));

			api('hosts', {search: search}).then(function (data) {
				clear(elHostList);

				if (!data.hosts.length) {
					elHostList.appendChild(make('div', 'tm-host-empty', T('没有匹配的主机')));
					return;
				}

				data.hosts.forEach(function (host) {
					var item = make('div', 'tm-host-item', host.name);

					if (host.ip) {
						item.appendChild(make('span', 'tm-host-ip', host.ip));
					}

					if (host.status === 1) {
						item.appendChild(make('span', 'tm-host-dim', T('已停用')));
					}

					item.addEventListener('click', function () {
						setHidden(elHostOverlay, true);

						if (state.hostPick) {
							state.hostPick(host);
						}
					});

					elHostList.appendChild(item);
				});
			}).catch(function (e) {
				clear(elHostList);
				elHostList.appendChild(make('div', 'tm-host-empty', T('加载失败：') + e.message));
			});
		}

		elHostSearch.addEventListener('input', function () {
			if (hostSearchTimer) {
				clearTimeout(hostSearchTimer);
			}

			hostSearchTimer = setTimeout(function () {
				loadHosts(elHostSearch.value.trim());
			}, 250);
		});

		// ---------------------------------------------------------------
		// 监控项选择器（连线指标：带宽 / 上行 / 下行）
		// ---------------------------------------------------------------

		function metricItemInfo(itemid) {
			return ((state.map && state.map.metric_items) || {})[String(itemid)] || null;
		}

		// 已选监控项的可读名：优先用后端带回的元信息（加载时 map.metric_items 已经有配置过的项）。
		function metricItemLabel(itemid) {
			var info = metricItemInfo(itemid);

			if (!info) {
				return '#' + itemid + T('（已删除或无权访问）');
			}

			return info.name + T('（') + (info.hostname ? info.hostname + ' · ' : '') + (info.key_ || '') + T('）');
		}

		function metricSummaryText(itemids) {
			if (!itemids || !itemids.length) {
				return T('未选择');
			}

			return itemids.length + T(' 个监控项');
		}

		function metricSummaryTitle(itemids) {
			if (!itemids || !itemids.length) {
				return '';
			}

			return itemids.map(metricItemLabel).join('\n');
		}

		// 监控项「类型」/「信息类型」列的中文名（对齐原生下拉里的叫法）。
		var ITEM_TYPE_LABELS = {
			0: T('Zabbix 代理'), 1: T('Zabbix 代理（主动式）'), 2: T('简单检查'), 3: T('SNMP 代理'), 4: T('Zabbix 采集器'),
			5: T('简单检查'), 7: T('Zabbix 内部'), 9: T('外部检查'), 10: T('数据库监控'), 11: T('聚合'), 12: T('HTTP 代理'),
			13: T('IPMI 代理'), 14: T('SSH 代理'), 15: T('TELNET 代理'), 16: T('计算'), 17: T('JMX 代理'), 18: T('依赖项'),
			19: T('HTTP 代理'), 20: T('SNMP 采集器'), 21: T('脚本')
		};
		var VALUE_TYPE_LABELS = {0: T('浮点数'), 1: T('字符'), 2: T('日志'), 3: T('数字（无正负）'), 4: T('文本')};

		// 「主机」弹层 /「主机群组」弹层的临时勾选状态（各自打开时重建）。
		var hpState = null;
		var ggState = null;

		// 弹层列表里的空态：漏斗图标 + 主标题 + 副标题（对齐原生弹层的空态样式）。
		function makeFunnelIcon() {
			var ns = 'http://www.w3.org/2000/svg';
			var svg = document.createElementNS(ns, 'svg');
			svg.setAttribute('class', 'tm-pick-empty-icon');
			svg.setAttribute('viewBox', '0 0 24 24');
			svg.setAttribute('width', '44');
			svg.setAttribute('height', '44');
			var path = document.createElementNS(ns, 'path');
			path.setAttribute('d', 'M3 5h18l-7 9v6l-4-2v-4z');
			path.setAttribute('fill', 'none');
			path.setAttribute('stroke', 'currentColor');
			path.setAttribute('stroke-width', '1.5');
			path.setAttribute('stroke-linejoin', 'round');
			svg.appendChild(path);

			return svg;
		}

		function pickEmptyRow(title, subtitle, colspan, withIcon) {
			var tr = make('tr', 'tm-pick-emptyrow');
			var td = make('td');
			td.colSpan = colspan;
			var box = make('div', 'tm-pick-empty');

			if (withIcon) {
				box.appendChild(makeFunnelIcon());
			}

			box.appendChild(make('div', 'tm-pick-empty-title', title));

			if (subtitle) {
				box.appendChild(make('div', 'tm-pick-empty-sub', subtitle));
			}

			td.appendChild(box);
			tr.appendChild(td);

			return tr;
		}

		// 通用的「chip 列表」渲染：主机框 / 主机群组框都用它；每颗 chip 带 × 可单独移除。
		function renderChips(box, ids, names, emptyText, onRemove) {
			clear(box);

			if (!ids.length) {
				box.appendChild(make('span', 'tm-pick-empty-inline', emptyText));

				return;
			}

			ids.forEach(function (id) {
				var chip = make('span', 'tm-chip');
				chip.appendChild(make('span', 'tm-chip-name', names[id] || ('#' + id)));
				var x = make('button', 'tm-chip-x', '×');
				x.type = 'button';
				x.addEventListener('click', function (e) {
					e.stopPropagation();
					onRemove(id);
				});
				chip.appendChild(x);
				box.appendChild(chip);
			});
		}

		// 主机的显示名：后端只回了 hostid 时，按 hostid 批量取一次名字（用于 chip）。
		function resolveHostNames(hostids) {
			if (!hostids || !hostids.length) {
				return Promise.resolve({});
			}

			return api('hosts', {hostids: hostids.join(',')}).then(function (data) {
				var names = {};
				(data.hosts || []).forEach(function (host) {
					names[String(host.hostid)] = host.name;
				});

				return names;
			}).catch(function () {
				return {};
			});
		}

		function renderHostChips() {
			if (!state.itemPick) {
				return;
			}

			renderChips(elItemHostBox, state.itemPick.hostids, state.itemPick.hostNames, T('未设置过滤器'),
				function (hostid) {
					state.itemPick.hostids = state.itemPick.hostids.filter(function (id) {
						return id !== hostid;
					});
					renderHostChips();
					loadPickerItems(elItemSearch.value.trim());
				});
		}

		function openItemPicker(kind, hostids, current, onDone) {
			var def = metricKind(kind) || {label: T('监控项')};

			state.itemPick = {
				hostids: (hostids || []).map(String),
				hostNames: {},
				checked: {},
				fetched: [],
				done: onDone
			};

			(current || []).forEach(function (itemid) {
				state.itemPick.checked[String(itemid)] = true;
			});

			elItemTitle.textContent = def.label + T(' 监控项');
			elItemSearch.value = '';
			elItemAll.checked = false;
			elItemAll.indeterminate = false;
			setHidden(elItemOverlay, false);
			renderHostChips();
			loadPickerItems('');

			// chip 上的主机名单独取一次（hostids 里可能有本端主机，本地没有它的名字）
			resolveHostNames(state.itemPick.hostids).then(function (names) {
				if (!state.itemPick) {
					return;
				}

				state.itemPick.hostNames = names || {};
				renderHostChips();
			});

			setTimeout(function () { elItemSearch.focus(); }, 0);
		}

		function closeItemPicker() {
			closePickHost();
			closeGroupPicker();
			setHidden(elItemOverlay, true);
			clear(elItemList);
			state.itemPick = null;
		}

		function loadPickerItems(search) {
			if (!state.itemPick) {
				return;
			}

			clear(elItemList);

			if (!state.itemPick.hostids.length) {
				elItemList.appendChild(pickEmptyRow(T('未设置过滤器'), T('使用过滤器显示结果'), 6, true));
				elItemAll.checked = false;
				elItemAll.indeterminate = false;

				return;
			}

			elItemList.appendChild(pickEmptyRow(T('加载中…'), '', 6, false));

			api('items', {hostids: state.itemPick.hostids.join(','), search: search}).then(function (data) {
				if (!state.itemPick) {
					return;
				}

				state.itemPick.fetched = data.items || [];
				renderPickerList();
			}).catch(function (e) {
				clear(elItemList);
				elItemList.appendChild(pickEmptyRow(T('加载失败'), e.message, 6, false));
			});
		}

		function syncItemMaster() {
			var list = (state.itemPick && state.itemPick.fetched) || [];
			var n = list.filter(function (item) {
				return state.itemPick.checked[String(item.itemid)];
			}).length;

			elItemAll.checked = list.length > 0 && n === list.length;
			elItemAll.indeterminate = n > 0 && n < list.length;
		}

		function renderPickerList() {
			var list = (state.itemPick && state.itemPick.fetched) || [];

			clear(elItemList);

			if (!list.length) {
				elItemList.appendChild(pickEmptyRow(T('未设置过滤器'), T('使用过滤器显示结果'), 6, true));
				elItemAll.checked = false;
				elItemAll.indeterminate = false;

				return;
			}

			list.forEach(function (item) {
				var itemid = String(item.itemid);
				var tr = make('tr');

				if (state.itemPick.checked[itemid]) {
					tr.classList.add('tm-row-checked');
				}

				var tdCheck = make('td', 'tm-pick-check');
				var box = make('input', 'checkbox-radio');
				box.type = 'checkbox';
				box.autocomplete = 'off';
				box.id = 'tm-item-cb-' + itemid;
				box.checked = !!state.itemPick.checked[itemid];

				var lbl = make('label');
				lbl.setAttribute('for', box.id);
				lbl.appendChild(make('span'));

				function toggleItem(on) {
					if (on) {
						state.itemPick.checked[itemid] = true;
					}
					else {
						delete state.itemPick.checked[itemid];
					}

					tr.classList.toggle('tm-row-checked', on);
					syncItemMaster();
				}

				box.addEventListener('change', function () {
					toggleItem(box.checked);
				});

				tdCheck.appendChild(box);
				tdCheck.appendChild(lbl);

				tr.appendChild(tdCheck);
				tr.appendChild(make('td', 'tm-pick-name', item.name));
				tr.appendChild(make('td', 'tm-pick-key', item.key_ || ''));
				tr.appendChild(make('td', null, ITEM_TYPE_LABELS[item.type] || (T('类型 ') + item.type)));
				tr.appendChild(make('td', null, VALUE_TYPE_LABELS[item.value_type] || (T('信息类型 ') + item.value_type)));
				tr.appendChild(make('td', 'tm-pick-status ' + (num(item.status, 0) === 0 ? 'tm-on' : 'tm-off'),
					num(item.status, 0) === 0 ? T('已启用') : T('停用')));

				// 点整行也能勾选（复选框/标签自身除外，避免重复触发）
				tr.addEventListener('click', function (e) {
					if (e.target.type === 'checkbox' || (e.target.closest && e.target.closest('label'))) {
						return;
					}

					box.checked = !box.checked;
					toggleItem(box.checked);
				});

				elItemList.appendChild(tr);
			});

			syncItemMaster();
		}

		elItemOk.addEventListener('click', function () {
			if (!state.itemPick) {
				closeItemPicker();

				return;
			}

			var picked = Object.keys(state.itemPick.checked);
			var done = state.itemPick.done;

			closeItemPicker();

			if (done) {
				done(picked);
			}
		});

		elItemCancel.addEventListener('click', closeItemPicker);
		elItemClose.addEventListener('click', closeItemPicker);

		elItemOverlay.addEventListener('mousedown', function (e) {
			if (e.target === elItemOverlay) {
				closeItemPicker();
			}
		});

		elItemAll.addEventListener('change', function () {
			if (!state.itemPick) {
				return;
			}

			var on = elItemAll.checked;

			(state.itemPick.fetched || []).forEach(function (item) {
				var itemid = String(item.itemid);

				if (on) {
					state.itemPick.checked[itemid] = true;
				}
				else {
					delete state.itemPick.checked[itemid];
				}
			});

			renderPickerList();
		});

		elItemHostPick.addEventListener('click', openPickHost);
		elItemHostBox.addEventListener('click', openPickHost);

		var itemSearchTimer = null;

		elItemSearch.addEventListener('input', function () {
			if (itemSearchTimer) {
				clearTimeout(itemSearchTimer);
			}

			itemSearchTimer = setTimeout(function () {
				if (state.itemPick) {
					loadPickerItems(elItemSearch.value.trim());
				}
			}, 250);
		});

		// ---------------------------------------------------------------
		// 触发器选择器（连线手工绑定触发器）
		// ---------------------------------------------------------------

		var TRIGGER_SEVERITY_LABELS = [T('未分类'), T('信息'), T('警告'), T('一般严重'), T('严重'), T('灾难')];

		// 已绑定触发器的名字：库里只存 triggerid，打开画布后按 triggerid 批量取一次描述
		function resolveTriggerNames(triggerids) {
			var missing = (triggerids || []).map(String).filter(function (id) {
				return id !== '' && !state.triggerNames[id];
			});

			if (!missing.length) {
				return Promise.resolve();
			}

			return api('hostTriggers', {triggerids: missing.join(',')}).then(function (data) {
				(data.triggers || []).forEach(function (trigger) {
					state.triggerNames[String(trigger.triggerid)] = trigger.description;
					state.triggerProblems[String(trigger.triggerid)] = num(trigger.value, 0) === 1;
				});
			}).catch(function () {
				// 取不到名字就退回 #id，不影响编辑
			});
		}

		function triggerName(triggerid) {
			return state.triggerNames[String(triggerid)] || ('#' + triggerid);
		}

		function openTriggerPicker(hostids, current, onDone) {
			state.trigPick = {
				hostids: (hostids || []).map(String),
				hostNames: {},
				groups: [],
				groupNames: {},
				checked: {},
				fetched: [],
				done: onDone
			};

			(current || []).forEach(function (lt) {
				state.trigPick.checked[String(lt.triggerid || lt)] = true;
			});

			elTrigSearch.value = '';
			elTrigAll.checked = false;
			elTrigAll.indeterminate = false;
			setHidden(elTrigOverlay, false);
			renderTriggerGroupChips();
			renderTriggerHostChips();
			loadTriggerList('');

			// chip 上的主机名单独取一次（两端主机里可能有一端本地没有名字）
			resolveHostNames(state.trigPick.hostids).then(function (names) {
				if (!state.trigPick) {
					return;
				}

				state.trigPick.hostNames = names || {};
				renderTriggerHostChips();
			});

			setTimeout(function () { elTrigSearch.focus(); }, 0);
		}

		// 触发器选择器的三行过滤：主机群组 / 主机 / 过滤（对齐监控项选择器）
		function renderTriggerGroupChips() {
			if (!state.trigPick) {
				return;
			}

			renderChips(elTrigGroupBox, state.trigPick.groups, state.trigPick.groupNames, T('未设置过滤器'),
				function (groupid) {
					state.trigPick.groups = state.trigPick.groups.filter(function (id) {
						return id !== groupid;
					});
					renderTriggerGroupChips();
					loadTriggerList(elTrigSearch.value.trim());
				});
		}

		function renderTriggerHostChips() {
			if (!state.trigPick) {
				return;
			}

			renderChips(elTrigHostBox, state.trigPick.hostids, state.trigPick.hostNames, T('未设置过滤器'),
				function (hostid) {
					state.trigPick.hostids = state.trigPick.hostids.filter(function (id) {
						return id !== hostid;
					});
					renderTriggerHostChips();
					loadTriggerList(elTrigSearch.value.trim());
				});
		}

		function closeTriggerPicker() {
			closePickHost();
			closeGroupPicker();
			setHidden(elTrigOverlay, true);
			clear(elTrigList);
			state.trigPick = null;
		}

		function loadTriggerList(search) {
			if (!state.trigPick) {
				return;
			}

			clear(elTrigList);

			if (!state.trigPick.hostids.length && !state.trigPick.groups.length) {
				elTrigList.appendChild(pickEmptyRow(T('未设置过滤器'),
					T('先用「主机」或「主机群组」选一下，再挑触发器'), 5, true));
				elTrigAll.checked = false;
				elTrigAll.indeterminate = false;

				return;
			}

			elTrigList.appendChild(pickEmptyRow(T('加载中…'), '', 5, false));

			api('hostTriggers', {
				hostids: state.trigPick.hostids.join(','),
				groupids: state.trigPick.groups.join(','),
				search: search
			}).then(function (data) {
				if (!state.trigPick) {
					return;
				}

				state.trigPick.fetched = data.triggers || [];
				renderTriggerList();
			}).catch(function (e) {
				clear(elTrigList);
				elTrigList.appendChild(pickEmptyRow(T('加载失败'), e.message, 5, false));
			});
		}

		function syncTriggerMaster() {
			var list = (state.trigPick && state.trigPick.fetched) || [];
			var n = list.filter(function (trigger) {
				return state.trigPick.checked[String(trigger.triggerid)];
			}).length;

			elTrigAll.checked = list.length > 0 && n === list.length;
			elTrigAll.indeterminate = n > 0 && n < list.length;
		}

		function renderTriggerList() {
			var list = (state.trigPick && state.trigPick.fetched) || [];

			clear(elTrigList);

			if (!list.length) {
				elTrigList.appendChild(pickEmptyRow(T('没有匹配的触发器'), T('换个关键字试试'), 5, true));
				elTrigAll.checked = false;
				elTrigAll.indeterminate = false;

				return;
			}

			list.forEach(function (trigger) {
				var tid = String(trigger.triggerid);
				var tr = make('tr');

				if (state.trigPick.checked[tid]) {
					tr.classList.add('tm-row-checked');
				}

				var tdCheck = make('td', 'tm-pick-check');
				var box = make('input', 'checkbox-radio');
				box.type = 'checkbox';
				box.autocomplete = 'off';
				box.id = 'tm-trig-cb-' + tid;
				box.checked = !!state.trigPick.checked[tid];

				var lbl = make('label');
				lbl.setAttribute('for', box.id);
				lbl.appendChild(make('span'));

				function toggle(on) {
					if (on) {
						state.trigPick.checked[tid] = true;
					}
					else {
						delete state.trigPick.checked[tid];
					}

					tr.classList.toggle('tm-row-checked', on);
					syncTriggerMaster();
				}

				box.addEventListener('change', function () {
					toggle(box.checked);
				});

				tdCheck.appendChild(box);
				tdCheck.appendChild(lbl);

				// 严重级别：色块 + 文案，和原生触发器列表一致
				var sev = num(trigger.priority, 0);
				var tdSev = make('td');
				var dot = make('span', 'tm-sev-dot');
				dot.style.background = SEVERITY_COLORS[sev] || SEVERITY_COLORS[0];
				tdSev.appendChild(dot);
				tdSev.appendChild(document.createTextNode(
					TRIGGER_SEVERITY_LABELS[sev] || (T('级别 ') + sev)));

				tr.appendChild(tdCheck);
				tr.appendChild(make('td', 'tm-pick-name', trigger.description));
				tr.appendChild(tdSev);
				tr.appendChild(make('td', 'tm-pick-status ' + (num(trigger.status, 0) === 0 ? 'tm-on' : 'tm-off'),
					num(trigger.status, 0) === 0 ? T('已启用') : T('停用')));
				tr.appendChild(make('td', null, trigger.hostname || ''));

				// 点整行也能勾选（复选框/标签自身除外）
				tr.addEventListener('click', function (e) {
					if (e.target.type === 'checkbox' || (e.target.closest && e.target.closest('label'))) {
						return;
					}

					box.checked = !box.checked;
					toggle(box.checked);
				});

				// 勾选过的触发器顺手把名字缓存下来，chip 立刻就有名字
				state.triggerNames[tid] = trigger.description;
				state.triggerProblems[tid] = num(trigger.value, 0) === 1;

				elTrigList.appendChild(tr);
			});

			syncTriggerMaster();
		}

		elTrigOk.addEventListener('click', function () {
			if (!state.trigPick) {
				closeTriggerPicker();

				return;
			}

			var picked = Object.keys(state.trigPick.checked);
			var list = state.trigPick.fetched || [];
			var done = state.trigPick.done;

			list.forEach(function (trigger) {
				state.triggerNames[String(trigger.triggerid)] = trigger.description;
			});

			closeTriggerPicker();

			if (done) {
				done(picked);
			}
		});

		elTrigCancel.addEventListener('click', closeTriggerPicker);
		elTrigClose.addEventListener('click', closeTriggerPicker);

		elTrigGroupPick.addEventListener('click', openGroupPicker);
		elTrigGroupBox.addEventListener('click', openGroupPicker);
		elTrigHostPick.addEventListener('click', openPickHost);
		elTrigHostBox.addEventListener('click', openPickHost);

		elTrigOverlay.addEventListener('mousedown', function (e) {
			if (e.target === elTrigOverlay) {
				closeTriggerPicker();
			}
		});

		elTrigAll.addEventListener('change', function () {
			if (!state.trigPick) {
				return;
			}

			var on = elTrigAll.checked;

			(state.trigPick.fetched || []).forEach(function (trigger) {
				var tid = String(trigger.triggerid);

				if (on) {
					state.trigPick.checked[tid] = true;
				}
				else {
					delete state.trigPick.checked[tid];
				}
			});

			renderTriggerList();
		});

		var triggerSearchTimer = null;

		elTrigSearch.addEventListener('input', function () {
			if (triggerSearchTimer) {
				clearTimeout(triggerSearchTimer);
			}

			triggerSearchTimer = setTimeout(function () {
				if (state.trigPick) {
					loadTriggerList(elTrigSearch.value.trim());
				}
			}, 250);
		});

		// ---------------------------------------------------------------
		// 「主机」弹层：主机群组 + 选择 → 主机群组弹层；列表带复选框
		// ---------------------------------------------------------------

		function renderGroupChips() {
			if (!hpState) {
				return;
			}

			renderChips(elHpGroupBox, hpState.groups, hpState.groupNames, T('未设置过滤器'),
				function (groupid) {
					hpState.groups = hpState.groups.filter(function (id) {
						return id !== groupid;
					});
					renderGroupChips();
					loadPickHost(elHpSearch.value.trim());
				});
		}

		// 当前在挑选主机的是哪个弹层（监控项选择器 / 触发器选择器）
		function activePick() {
			return state.itemPick || state.trigPick;
		}

		function openPickHost() {
			var pick = activePick();

			if (!pick) {
				return;
			}

			hpState = {checked: {}, fetched: [], groups: [], groupNames: {}};

			pick.hostids.forEach(function (hostid) {
				hpState.checked[hostid] = true;
			});

			elHpSearch.value = '';
			elHpAll.checked = false;
			elHpAll.indeterminate = false;
			renderGroupChips();
			setHidden(elHpOverlay, false);
			loadPickHost('');
			setTimeout(function () { elHpSearch.focus(); }, 0);
		}

		function closePickHost() {
			setHidden(elHpOverlay, true);
			clear(elHpList);
			hpState = null;
		}

		function loadPickHost(search) {
			if (!hpState) {
				return;
			}

			clear(elHpList);
			elHpList.appendChild(pickEmptyRow(T('加载中…'), '', 2, false));

			api('hosts', {search: search, groupids: hpState.groups.join(',')}).then(function (data) {
				if (!hpState) {
					return;
				}

				hpState.fetched = data.hosts || [];
				renderPickHostList();
			}).catch(function (e) {
				clear(elHpList);
				elHpList.appendChild(pickEmptyRow(T('加载失败'), e.message, 2, false));
			});
		}

		function syncHpMaster() {
			var list = (hpState && hpState.fetched) || [];
			var n = list.filter(function (host) {
				return hpState.checked[String(host.hostid)];
			}).length;

			elHpAll.checked = list.length > 0 && n === list.length;
			elHpAll.indeterminate = n > 0 && n < list.length;
		}

		function renderPickHostList() {
			var list = (hpState && hpState.fetched) || [];

			clear(elHpList);

			if (!list.length) {
				elHpList.appendChild(pickEmptyRow(T('未设置过滤器'), T('使用过滤器显示结果'), 2, true));
				elHpAll.checked = false;
				elHpAll.indeterminate = false;

				return;
			}

			list.forEach(function (host) {
				var hostid = String(host.hostid);
				var tr = make('tr');

				if (hpState.checked[hostid]) {
					tr.classList.add('tm-row-checked');
				}

				var tdCheck = make('td', 'tm-pick-check');
				var box = make('input', 'checkbox-radio');
				box.type = 'checkbox';
				box.autocomplete = 'off';
				box.id = 'tm-hp-cb-' + hostid;
				box.checked = !!hpState.checked[hostid];

				var lbl = make('label');
				lbl.setAttribute('for', box.id);
				lbl.appendChild(make('span'));

				function toggleHost(on) {
					if (on) {
						hpState.checked[hostid] = true;
					}
					else {
						delete hpState.checked[hostid];
					}

					tr.classList.toggle('tm-row-checked', on);
					syncHpMaster();
				}

				box.addEventListener('change', function () {
					toggleHost(box.checked);
				});

				tdCheck.appendChild(box);
				tdCheck.appendChild(lbl);

				var tdName = make('td', 'tm-pick-name');
				tdName.appendChild(make('span', null, host.name));

				if (host.ip) {
					tdName.appendChild(make('span', 'tm-pick-dim', host.ip));
				}

				tr.appendChild(tdCheck);
				tr.appendChild(tdName);

				tr.addEventListener('click', function (e) {
					if (e.target.type === 'checkbox' || (e.target.closest && e.target.closest('label'))) {
						return;
					}

					box.checked = !box.checked;
					toggleHost(box.checked);
				});

				elHpList.appendChild(tr);
			});

			syncHpMaster();
		}

		elHpOk.addEventListener('click', function () {
			var pick = activePick();

			if (!pick || !hpState) {
				closePickHost();

				return;
			}

			var ids = Object.keys(hpState.checked);

			(hpState.fetched || []).forEach(function (host) {
				if (hpState.checked[String(host.hostid)]) {
					pick.hostNames[String(host.hostid)] = host.name;
				}
			});

			pick.hostids = ids;
			closePickHost();

			if (state.itemPick) {
				renderHostChips();
				loadPickerItems(elItemSearch.value.trim());
			}
			else {
				renderTriggerHostChips();
				loadTriggerList(elTrigSearch.value.trim());
			}
		});

		elHpCancel.addEventListener('click', closePickHost);
		elHpClose.addEventListener('click', closePickHost);

		elHpGroupPick.addEventListener('click', openGroupPicker);
		elHpGroupBox.addEventListener('click', openGroupPicker);

		elHpOverlay.addEventListener('mousedown', function (e) {
			if (e.target === elHpOverlay) {
				closePickHost();
			}
		});

		elHpAll.addEventListener('change', function () {
			if (!hpState) {
				return;
			}

			var on = elHpAll.checked;

			(hpState.fetched || []).forEach(function (host) {
				var hostid = String(host.hostid);

				if (on) {
					hpState.checked[hostid] = true;
				}
				else {
					delete hpState.checked[hostid];
				}
			});

			renderPickHostList();
		});

		var hpSearchTimer = null;

		elHpSearch.addEventListener('input', function () {
			if (hpSearchTimer) {
				clearTimeout(hpSearchTimer);
			}

			hpSearchTimer = setTimeout(function () {
				if (hpState) {
					loadPickHost(elHpSearch.value.trim());
				}
			}, 250);
		});

		// ---------------------------------------------------------------
		// 「主机群组」弹层：搜索框 + 带复选框的列表
		// ---------------------------------------------------------------

		function openGroupPicker() {
			var owner = hpState || state.trigPick;

			if (!owner) {
				return;
			}

			ggState = {checked: {}, fetched: []};

			owner.groups.forEach(function (groupid) {
				ggState.checked[groupid] = true;
			});

			elGgSearch.value = '';
			elGgAll.checked = false;
			elGgAll.indeterminate = false;
			setHidden(elGgOverlay, false);
			loadGroupPicker('');
			setTimeout(function () { elGgSearch.focus(); }, 0);
		}

		function closeGroupPicker() {
			setHidden(elGgOverlay, true);
			clear(elGgList);
			ggState = null;
		}

		function loadGroupPicker(search) {
			if (!ggState) {
				return;
			}

			clear(elGgList);
			elGgList.appendChild(pickEmptyRow(T('加载中…'), '', 2, false));

			api('hostgroups', {search: search}).then(function (data) {
				if (!ggState) {
					return;
				}

				ggState.fetched = data.hostgroups || [];
				renderGroupPickerList();
			}).catch(function (e) {
				clear(elGgList);
				elGgList.appendChild(pickEmptyRow(T('加载失败'), e.message, 2, false));
			});
		}

		function syncGgMaster() {
			var list = (ggState && ggState.fetched) || [];
			var n = list.filter(function (group) {
				return ggState.checked[String(group.groupid)];
			}).length;

			elGgAll.checked = list.length > 0 && n === list.length;
			elGgAll.indeterminate = n > 0 && n < list.length;
		}

		function renderGroupPickerList() {
			var list = (ggState && ggState.fetched) || [];

			clear(elGgList);

			if (!list.length) {
				elGgList.appendChild(pickEmptyRow(T('未设置过滤器'), T('使用过滤器显示结果'), 2, true));
				elGgAll.checked = false;
				elGgAll.indeterminate = false;

				return;
			}

			list.forEach(function (group) {
				var groupid = String(group.groupid);
				var tr = make('tr');

				if (ggState.checked[groupid]) {
					tr.classList.add('tm-row-checked');
				}

				var tdCheck = make('td', 'tm-pick-check');
				var box = make('input', 'checkbox-radio');
				box.type = 'checkbox';
				box.autocomplete = 'off';
				box.id = 'tm-gg-cb-' + groupid;
				box.checked = !!ggState.checked[groupid];

				var lbl = make('label');
				lbl.setAttribute('for', box.id);
				lbl.appendChild(make('span'));

				function toggleGroup(on) {
					if (on) {
						ggState.checked[groupid] = true;
					}
					else {
						delete ggState.checked[groupid];
					}

					tr.classList.toggle('tm-row-checked', on);
					syncGgMaster();
				}

				box.addEventListener('change', function () {
					toggleGroup(box.checked);
				});

				tdCheck.appendChild(box);
				tdCheck.appendChild(lbl);

				tr.appendChild(tdCheck);
				tr.appendChild(make('td', 'tm-pick-name', group.name));

				tr.addEventListener('click', function (e) {
					if (e.target.type === 'checkbox' || (e.target.closest && e.target.closest('label'))) {
						return;
					}

					box.checked = !box.checked;
					toggleGroup(box.checked);
				});

				elGgList.appendChild(tr);
			});

			syncGgMaster();
		}

		elGgOk.addEventListener('click', function () {
			var owner = hpState || state.trigPick;

			if (!owner || !ggState) {
				closeGroupPicker();

				return;
			}

			var ids = Object.keys(ggState.checked);

			(ggState.fetched || []).forEach(function (group) {
				if (ggState.checked[String(group.groupid)]) {
					owner.groupNames[String(group.groupid)] = group.name;
				}
			});

			owner.groups = ids;
			closeGroupPicker();

			if (hpState) {
				renderGroupChips();
				loadPickHost(elHpSearch.value.trim());
			}
			else {
				renderTriggerGroupChips();
				loadTriggerList(elTrigSearch.value.trim());
			}
		});

		elGgCancel.addEventListener('click', closeGroupPicker);
		elGgClose.addEventListener('click', closeGroupPicker);

		elGgOverlay.addEventListener('mousedown', function (e) {
			if (e.target === elGgOverlay) {
				closeGroupPicker();
			}
		});

		elGgAll.addEventListener('change', function () {
			if (!ggState) {
				return;
			}

			var on = elGgAll.checked;

			(ggState.fetched || []).forEach(function (group) {
				var groupid = String(group.groupid);

				if (on) {
					ggState.checked[groupid] = true;
				}
				else {
					delete ggState.checked[groupid];
				}
			});

			renderGroupPickerList();
		});

		var ggSearchTimer = null;

		elGgSearch.addEventListener('input', function () {
			if (ggSearchTimer) {
				clearTimeout(ggSearchTimer);
			}

			ggSearchTimer = setTimeout(function () {
				if (ggState) {
					loadGroupPicker(elGgSearch.value.trim());
				}
			}, 250);
		});

		// ---------------------------------------------------------------
		// 保存 / 退出编辑
		// ---------------------------------------------------------------

		function saveMap() {
			if (!state.map) {
				return;
			}

			// 保存前把画布收紧到内容大小，避免图上只摆了几个元素却存着 3000×3000 的大白板
			autoSizeCanvas();

			// 每链路指标配置（带宽 / 上行 / 下行监控项）：已有链路带 linkid 直接对上；
			// 新建链路没有 linkid，按它在 links 数组里的出现顺序编号（new_seq），
			// 后端把新建的 linkid 升序排列后按 new_seq 依次认领。
			var linkMetrics = [];
			var newSeq = 0;

			state.map.links.forEach(function (link) {
				if (!link.linkid) {
					newSeq++;
				}

				var m = normalizeMetrics(link.metrics);
				var sourcePort = String(link.source_port || '');
				var targetPort = String(link.target_port || '');
				var triggerSource = num(link.trigger_source, 0);

				// 指标、两端端口、触发器来源三者全空就不落库；有端口/来源就得写，
				// 否则下次打开弹窗、重匹配触发器都取不到这些信息。
				if (!m.bw.length && !m.in.length && !m.out.length
						&& sourcePort === '' && targetPort === '' && triggerSource === 0) {
					return;
				}

				linkMetrics.push({
					linkid: link.linkid ? String(link.linkid) : '',
					new_seq: link.linkid ? 0 : newSeq,
					bw_itemids: m.bw,
					in_itemids: m.in,
					out_itemids: m.out,
					source_port: sourcePort,
					target_port: targetPort,
					trigger_source: triggerSource
				});
			});

			// 每链路走线（自动直角 / 手动折点）：linkid / new_seq 与指标同一套认领规则
			var linkPaths = [];
			var pathSeq = 0;

			state.map.links.forEach(function (link) {
				if (!link.linkid) {
					pathSeq++;
				}

				var routing = Math.max(0, Math.min(2, num(link.routing, 0)));
				var waypoints = normalizeWaypointList(link.waypoints);

				// 「自动曲线」也要落库：自动发现的连线默认自动直角，用户改成自动曲线后
				// 不写这一行，下次打开又会被默认值渲染回自动直角。
				linkPaths.push({
					linkid: link.linkid ? String(link.linkid) : '',
					new_seq: link.linkid ? 0 : pathSeq,
					routing: routing,
					waypoints: waypoints
				});
			});

			var payload = {
				sysmapid: state.map.sysmapid,
				name: state.map.name,
				width: state.map.width,
				height: state.map.height,
				selements: state.map.selements.map(function (se) {
					var out = {
						selementid: se.selementid,
						elementtype: se.elementtype,
						iconid_off: se.iconid_off,
						iconid_on: se.iconid_on,
						iconid_disabled: se.iconid_disabled,
						iconid_maintenance: se.iconid_maintenance,
						label: se.label || '',
						label_location: se.label_location,
						x: se.x,
						y: se.y,
						use_iconmap: se.use_iconmap,
						evaltype: se.evaltype,
						tags: se.tags || [],
						urls: se.urls || []
					};

					if (se.elementtype !== ET_IMAGE) {
						out.elements = se.elements || [];
					}

					if (se.elementtype === ET_HOST || se.elementtype === ET_GROUP) {
						out.elementsubtype = se.elementsubtype;
						out.areatype = se.areatype;
						out.width = se.width;
						out.height = se.height;
						out.viewtype = se.viewtype;
					}

					return out;
				}),
				links: state.map.links.map(function (link) {
					var out = {
						selementid1: link.selementid1,
						selementid2: link.selementid2,
						label: link.label || '',
						color: link.color || DEFAULT_LINK_COLOR,
						drawtype: link.drawtype
					};

					if (link.linkid) {
						out.linkid = link.linkid;
					}

					// 连线绑定的触发器（链路指示器）要原样回写：不传的话 map.update 会把它们清掉。
					// 空数组也要传：用户在对话框里点「清空」时，靠这个空数组把库里的绑定删掉。
					out.linktriggers = (link.linktriggers || []).map(function (lt) {
						return {
							triggerid: lt.triggerid,
							drawtype: num(lt.drawtype, 0),
							color: String(lt.color || DEFAULT_TRIGGER_COLOR).replace('#', '')
						};
					});

					return out;
				}),
				link_metrics: linkMetrics,
				link_paths: linkPaths,
				shapes: state.map.shapes.map(function (shape) {
					var out = {
						type: shape.type,
						x: shape.x,
						y: shape.y,
						width: shape.width,
						height: shape.height,
						text: shape.text || '',
						font: shape.font,
						font_size: shape.font_size,
						font_color: shape.font_color,
						text_halign: shape.text_halign,
						text_valign: shape.text_valign,
						border_type: shape.border_type,
						border_width: shape.border_width,
						border_color: shape.border_color,
						background_color: shape.background_color || '',
						zindex: shape.zindex
					};

					if (shape.sysmap_shapeid) {
						out.sysmap_shapeid = shape.sysmap_shapeid;
					}

					return out;
				})
			};

			elSaveBtn.disabled = true;
			setHint(T('保存中…'));

			api('save', {sysmapid: state.map.sysmapid, payload: JSON.stringify(payload)}).then(function () {
				return openMap(state.map.sysmapid, true, true, 'none');
			}).then(function () {
				setHint(T('已保存 ') + new Date().toLocaleTimeString());
			}).catch(function (e) {
				setHint(T('保存失败：') + e.message);
				openNoticeDialog(T('保存失败'), e.message);
			}).then(function () {
				elSaveBtn.disabled = false;
			});
		}

		function exitEditing() {
			if (state.dirty) {
				confirmDialog(T('放弃未保存的修改？'), T('退出编辑模式会丢失本次未保存的修改。'), T('放弃修改')).then(function (ok) {
					if (ok) {
						state.dirty = false;
						setEditing(false);
						renderCanvas();
					}
				});

				return;
			}

			setEditing(false);
			renderCanvas();
		}

		// ---------------------------------------------------------------
		// 事件绑定
		// ---------------------------------------------------------------

		elCreateBtn.addEventListener('click', openCreateDialog);
		elDiscoverBtn.addEventListener('click', openDiscoverDialog);

		// 列表：条件检索 / 排序 / 全选 / 批量删除 / 导出
		elFilterToggle.addEventListener('click', function (e) {
			e.preventDefault();
			var hidden = elFilterContainer.style.display === 'none';

			elFilterContainer.style.display = hidden ? '' : 'none';
		});
		document.getElementById('tm-filter-form').addEventListener('submit', function (e) {
			e.preventDefault();
			applyFilter();
		});
		document.getElementById('tm-filter-apply').addEventListener('click', function (e) {
			e.preventDefault();
			applyFilter();
		});
		document.getElementById('tm-filter-reset').addEventListener('click', resetFilter);
		elSortName.addEventListener('click', function (e) { e.preventDefault(); sortBy('name'); });
		elSortWidth.addEventListener('click', function (e) { e.preventDefault(); sortBy('width'); });
		elSortHeight.addEventListener('click', function (e) { e.preventDefault(); sortBy('height'); });
		elCheckAll.addEventListener('change', toggleCheckAll);
		elMassDelete.addEventListener('click', massDelete);
		elExportBtn.addEventListener('click', exportMaps);

		elBackBtn.addEventListener('click', backToList);
		elZoomIn.addEventListener('click', function () { zoomBy(1.2); });
		elZoomOut.addEventListener('click', function () { zoomBy(1 / 1.2); });
		elZoomReset.addEventListener('click', resetView);

		if (elFitBtn) {
			elFitBtn.addEventListener('click', fitView);
		}

		elRediscoverBtn.addEventListener('click', openRediscoverDialog);

		elEditBtn.addEventListener('click', function () {
			if (!state.canEdit) {
				openNoticeDialog(T('没有权限'), T('当前用户没有创建 / 编辑拓扑图的权限（需要角色里的「创建和编辑拓扑图」）。'));

				return;
			}

			setEditing(true);
			renderCanvas();
		});

		elSaveBtn.addEventListener('click', saveMap);
		elExitBtn.addEventListener('click', exitEditing);

		elEditbar.addEventListener('click', function (e) {
			var button = e.target.closest('button[data-group]');

			if (!button) {
				return;
			}

			var group = button.getAttribute('data-group');
			var op = button.getAttribute('data-op');

			if (op === 'add') {
				clearModes();

				if (group === 'element') {
					addElement();
				}
				else if (group === 'shape') {
					addShape();
				}
				else {
					startLinkMode();
				}

				return;
			}

			if (group === 'link') {
				setRemoveMode('link');
			}
			else {
				setRemoveMode(group);
			}
		});

		elDialogClose.addEventListener('click', closeDialog);

		elOverlay.addEventListener('mousedown', function (e) {
			if (e.target === elOverlay) {
				closeDialog();
			}
		});

		document.getElementById('tm-host-close').addEventListener('click', function () {
			setHidden(elHostOverlay, true);
		});
		document.getElementById('tm-host-cancel').addEventListener('click', function () {
			setHidden(elHostOverlay, true);
		});

		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape') {
				return;
			}

			// 三级弹层叠在链接属性弹窗之上，Esc 从最上面一层往下关：
			// 主机群组 → 主机 → 监控项
			if (!elGgOverlay.hidden) {
				closeGroupPicker();
				return;
			}

			if (!elHpOverlay.hidden) {
				closePickHost();
				return;
			}

			if (!elItemOverlay.hidden) {
				closeItemPicker();
				return;
			}

			if (!elOverlay.hidden) {
				closeDialog();
				return;
			}

			if (!elHostOverlay.hidden) {
				setHidden(elHostOverlay, true);
				return;
			}

			if (state.bendLink) {
				exitBendMode();
				return;
			}

			if (state.removeMode || state.linkMode) {
				clearModes();
				renderCanvas();
			}
		});

		// 浏览器后退 / 前进：按地址栏里的 sysmapid 决定落到画布还是列表
		window.addEventListener('popstate', function (event) {
			var want = (event.state && event.state.tm_sysmapid) || '';
			var current = state.map ? String(state.map.sysmapid) : '';

			if (want === current) {
				return;
			}

			if (want) {
				openMap(want, false, false, 'none');
			}
			else if (state.map) {
				doBackToList('none');
			}
		});

		window.addEventListener('resize', function () {
			if (state.map) {
				layoutCanvasWrap();
				// 窗口变大后画布跟着铺满，不会留下「右边/下边不是画布」的区域
				ensureCanvasFitsViewport();
				applyView();
			}
		});

		// ---------------------------------------------------------------
		// 启动
		// ---------------------------------------------------------------

		syncSortArrows();

		loadIcons().then(loadList);

		var initial = root.getAttribute('data-sysmapid') || '';

		if (initial) {
			// 服务端按用户 profile 判断要不要直接进画布；这里是「页面初始状态」，不新增历史记录
			openMap(initial, false, false, 'replace');
		}
		else {
			// 列表态：地址栏若残留 sysmapid（手改链接等）就抹掉，避免后退/刷新又跳回画布
			syncViewUrl('', 'replace');
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	}
	else {
		start();
	}
})();
