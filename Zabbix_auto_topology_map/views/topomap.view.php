<?php declare(strict_types = 0);

use Modules\Topomap\Lib\LanguageManager;

/**
 * 「监测 → 自动拓扑图」页面骨架。
 *
 * 列表区结构刻意对齐原生「监测 → 拓扑图」（filter-space + list-table + action-buttons），
 * 用的是 Zabbix 主题里现成的类名，所以外观、深浅主题、表格样式都跟原生一致。
 * 交互全部在 assets/js/topomap.js，样式补充在 assets/css/topomap.css。
 *
 * 中英文：静态文案用 LanguageManager::t() 翻译，动态文案由内联脚本把词典
 * 注入 window.TOPOMAP_I18N，topomap.js 里的 T()/TF() 查表（查不到回退中文原文）。
 *
 * @var CView $this
 * @var array $data
 */

$sysmapid = isset($data['sysmapid']) ? (string) $data['sysmapid'] : '';
$t = static fn(string $s): string => htmlspecialchars(LanguageManager::t($s), ENT_QUOTES, 'UTF-8');
?>
<div id="topomap-root" data-sysmapid="<?= htmlspecialchars($sysmapid, ENT_QUOTES, 'UTF-8') ?>">

	<!-- 词典必须在 topomap.js 的 DOMContentLoaded 回调之前就位：
	     模块 JS 在 <head> 里加载，这里的内联脚本在 <body> 里先于 DOMContentLoaded 执行。 -->
	<script>
		window.TOPOMAP_LANG = <?= json_encode(LanguageManager::detectLanguage()) ?>;
		window.TOPOMAP_I18N = <?= json_encode((object) LanguageManager::jsMap(),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
	</script>

	<!-- 页头：layout.htmlpage 只输出 <div class="wrapper"> + main_block，不产出 header，
	     所以标题和右上角按钮得自己写；结构与原生列表页一致，样式由主题的 header 规则接管。 -->
	<header class="header-title">
		<div><h1 id="page-title-general"><?= $t('自动拓扑图') ?></h1></div>
		<div class="header-controls">
			<nav aria-label="<?= $t('内容控制') ?>">
				<ul>
					<li><button type="button" id="tm-discover-btn" class="btn-alt"><?= $t('自动发现') ?></button></li>
					<li><button type="button" id="tm-create-btn"><?= $t('创建拓扑图') ?></button></li>
				</ul>
			</nav>
		</div>
	</header>

	<main>
	<!-- 列表视图 -->
	<div class="tm-list-view" id="tm-list-view">

		<div class="tm-msg-line"><span class="tm-msg" id="tm-list-msg"></span></div>

		<!-- 条件检索：结构与原生 sysmap.list 一致 -->
		<div class="filter-space" id="tm-filter-space" aria-label="<?= $t('条件检索') ?>">
			<ul class="filter-btn-container">
				<li><a class="btn zi-filter filter-trigger" id="tm-filter-toggle" href="#" role="button"><?= $t('条件检索') ?></a></li>
			</ul>
			<form id="tm-filter-form" action="#" onsubmit="return false;">
				<div class="filter-container" id="tm-filter-container">
					<div class="table filter-forms">
						<div class="row">
							<div class="cell">
								<ul class="table-forms">
									<li>
										<div class="table-forms-td-left"><label for="tm-filter-name"><?= $t('名称') ?></label></div>
										<div class="table-forms-td-right">
											<input type="text" id="tm-filter-name" maxlength="255" autocomplete="off" style="width: 300px;">
										</div>
									</li>
								</ul>
							</div>
						</div>
					</div>
					<div class="filter-forms">
						<button type="submit" id="tm-filter-apply"><?= $t('应用') ?></button>
						<button type="button" class="btn-alt" id="tm-filter-reset"><?= $t('重设') ?></button>
					</div>
				</div>
			</form>
		</div>

		<form id="tm-list-form" action="#" onsubmit="return false;">
			<table class="list-table" id="tm-list-table">
				<thead>
					<tr>
						<th class="cell-width">
							<input type="checkbox" id="tm-check-all" class="checkbox-radio" autocomplete="off">
							<label for="tm-check-all"><span></span></label>
						</th>
						<th><a href="#" id="tm-sort-name"><?= $t('名称') ?><span class="arrow-up"></span></a></th>
						<th><a href="#" id="tm-sort-width"><?= $t('宽(px)') ?><span class="arrow-up"></span></a></th>
						<th><a href="#" id="tm-sort-height"><?= $t('高度(px)') ?><span class="arrow-up"></span></a></th>
						<th><?= $t('动作') ?></th>
					</tr>
				</thead>
				<tbody id="tm-list-body">
					<tr><td colspan="5" class="tm-loading"><?= $t('加载中…') ?></td></tr>
				</tbody>
			</table>

			<div class="table-paging">
				<nav class="paging-btn-container" role="navigation" aria-label="<?= $t('分页栏') ?>">
					<div class="table-stats" id="tm-table-stats"></div>
				</nav>
			</div>

			<div class="action-buttons" id="tm-action-buttons">
				<span class="selected-item-count" id="tm-selected-count"><?= $t('0 已选') ?></span>
				<button type="button" class="btn-alt" id="tm-export-btn"><?= $t('导出') ?></button>
				<button type="button" class="btn-alt" id="tm-mass-delete" disabled><?= $t('删除') ?></button>
			</div>
		</form>
	</div>

	<!-- 画布视图（预览 / 编辑） -->
	<div class="tm-canvas-view" id="tm-canvas-view" hidden>
		<div class="tm-toolbar">
			<button class="tm-btn" id="tm-back-btn" type="button">← <?= $t('返回列表') ?></button>
			<span class="tm-canvas-title" id="tm-canvas-title"></span>
			<span class="tm-spacer"></span>
			<span class="tm-hint" id="tm-hint"></span>
			<span class="tm-canvas-size" id="tm-canvas-size"></span>
			<button class="tm-btn tm-btn-icon" id="tm-zoom-out" type="button" title="<?= $t('缩小') ?>">−</button>
			<button class="tm-btn tm-btn-zoom" id="tm-zoom-reset" type="button" title="<?= $t('恢复 100%') ?>">100%</button>
			<button class="tm-btn tm-btn-icon" id="tm-zoom-in" type="button" title="<?= $t('放大') ?>">+</button>
			<button class="tm-btn" id="tm-fit-btn" type="button" title="<?= $t('缩放到适合窗口') ?>"><?= $t('适应窗口') ?></button>
			<button class="tm-btn" id="tm-rediscover-btn" type="button" title="<?= $t('重新扫描并就地更新当前拓扑图') ?>"><?= $t('重新发现') ?></button>
			<button class="tm-btn tm-btn-primary" id="tm-edit-btn" type="button"><?= $t('编辑拓扑图') ?></button>
			<button class="tm-btn tm-btn-primary" id="tm-save-btn" type="button" hidden><?= $t('保存') ?></button>
			<button class="tm-btn" id="tm-exit-btn" type="button" hidden><?= $t('退出编辑') ?></button>
		</div>

		<div class="tm-editbar" id="tm-editbar" hidden>
			<span class="tm-editbar-group">
				<span class="tm-editbar-label"><?= $t('地图元素:') ?></span>
				<button class="tm-btn tm-btn-sm" data-group="element" data-op="add" type="button"><?= $t('添加') ?></button>
				<button class="tm-btn tm-btn-sm" data-group="element" data-op="remove" type="button"><?= $t('移除') ?></button>
			</span>
			<span class="tm-editbar-sep"></span>
			<span class="tm-editbar-group">
				<span class="tm-editbar-label"><?= $t('形:') ?></span>
				<button class="tm-btn tm-btn-sm" data-group="shape" data-op="add" type="button"><?= $t('添加') ?></button>
				<button class="tm-btn tm-btn-sm" data-group="shape" data-op="remove" type="button"><?= $t('移除') ?></button>
			</span>
			<span class="tm-editbar-sep"></span>
			<span class="tm-editbar-group">
				<span class="tm-editbar-label"><?= $t('链接:') ?></span>
				<button class="tm-btn tm-btn-sm" data-group="link" data-op="add" type="button"><?= $t('添加') ?></button>
				<button class="tm-btn tm-btn-sm" data-group="link" data-op="remove" type="button"><?= $t('移除') ?></button>
			</span>
			<span class="tm-spacer"></span>
			<span class="tm-hint"><?= $t('拖动元素可移动位置；单击元素/形/链接可编辑属性') ?></span>
		</div>

		<div class="tm-canvas-wrap" id="tm-canvas-wrap">
			<div class="tm-stage" id="tm-stage">
				<svg class="tm-svg" id="tm-svg" xmlns="http://www.w3.org/2000/svg"></svg>
			</div>
		</div>
	</div>

	</main>

	<!-- 弹层容器：元素属性 / 形属性 / 链接属性 / 主机选择 -->
	<div class="tm-overlay" id="tm-overlay" hidden>
		<div class="tm-dialog" id="tm-dialog" role="dialog" aria-modal="true">
			<div class="tm-dialog-head">
				<span class="tm-dialog-title" id="tm-dialog-title"></span>
				<button class="tm-dialog-close" id="tm-dialog-close" type="button" title="<?= $t('关闭') ?>">×</button>
			</div>
			<div class="tm-dialog-body" id="tm-dialog-body"></div>
			<div class="tm-dialog-foot" id="tm-dialog-foot"></div>
		</div>
	</div>

	<!-- 监控项选择器：连线指标（带宽 / 上行 / 下行）从监控项里挑。
	     结构对齐 Zabbix 原生「监控项」弹层：主机编辑框（优先自动选源端主机）+ 选择按钮
	     → 主机弹层（主机群组 + 选择）→ 主机群组弹层（搜索 + 带复选框的列表）。 -->
	<div class="tm-overlay" id="tm-item-overlay" hidden>
		<div class="tm-dialog tm-dialog-wide" role="dialog" aria-modal="true">
			<div class="tm-dialog-head">
				<span class="tm-dialog-title" id="tm-item-title"><?= $t('监控项') ?></span>
				<button class="tm-dialog-close" id="tm-item-close" type="button" title="<?= $t('关闭') ?>">×</button>
			</div>
			<div class="tm-dialog-body">
				<div class="tm-pick-field">
					<span class="tm-pick-label"><?= $t('主机') ?></span>
					<div class="tm-pick-box" id="tm-item-hostbox" role="button" tabindex="0"></div>
					<button class="tm-btn" id="tm-item-hostpick" type="button"><?= $t('选择') ?></button>
				</div>
				<div class="tm-pick-field">
					<span class="tm-pick-label"><?= $t('过滤') ?></span>
					<input class="tm-input" id="tm-item-search" type="text"
						placeholder="<?= $t('在此输入搜索') ?>" autocomplete="off">
				</div>
				<div class="tm-pick-scroll">
					<table class="tm-pick-table" id="tm-item-table">
						<thead>
							<tr>
								<th class="tm-pick-check">
									<input type="checkbox" class="checkbox-radio" id="tm-item-all" autocomplete="off">
									<label for="tm-item-all"><span></span></label>
								</th>
								<th><?= $t('名称') ?></th>
								<th><?= $t('键值') ?></th>
								<th><?= $t('类型') ?></th>
								<th><?= $t('信息类型') ?></th>
								<th><?= $t('状态') ?></th>
							</tr>
						</thead>
						<tbody id="tm-item-list"></tbody>
					</table>
				</div>
			</div>
			<div class="tm-dialog-foot">
				<button class="tm-btn tm-btn-primary" id="tm-item-ok" type="button"><?= $t('选择') ?></button>
				<button class="tm-btn" id="tm-item-cancel" type="button"><?= $t('取消') ?></button>
			</div>
		</div>
	</div>

	<!-- 主机弹层（从监控项弹层的「主机 → 选择」打开）：主机群组 + 选择 → 主机群组弹层；列表带复选框 -->
	<div class="tm-overlay" id="tm-hp-overlay" hidden>
		<div class="tm-dialog" role="dialog" aria-modal="true">
			<div class="tm-dialog-head">
				<span class="tm-dialog-title"><?= $t('主机') ?></span>
				<button class="tm-dialog-close" id="tm-hp-close" type="button" title="<?= $t('关闭') ?>">×</button>
			</div>
			<div class="tm-dialog-body">
				<div class="tm-pick-field">
					<span class="tm-pick-label"><?= $t('主机群组') ?></span>
					<div class="tm-pick-box" id="tm-hp-groupbox" role="button" tabindex="0"></div>
					<button class="tm-btn" id="tm-hp-grouppick" type="button"><?= $t('选择') ?></button>
				</div>
				<div class="tm-pick-field">
					<span class="tm-pick-label"><?= $t('过滤') ?></span>
					<input class="tm-input" id="tm-hp-search" type="text"
						placeholder="<?= $t('在此输入搜索') ?>" autocomplete="off">
				</div>
				<div class="tm-pick-scroll">
					<table class="tm-pick-table" id="tm-hp-table">
						<thead>
							<tr>
								<th class="tm-pick-check">
									<input type="checkbox" class="checkbox-radio" id="tm-hp-all" autocomplete="off">
									<label for="tm-hp-all"><span></span></label>
								</th>
								<th><?= $t('名称') ?></th>
							</tr>
						</thead>
						<tbody id="tm-hp-list"></tbody>
					</table>
				</div>
			</div>
			<div class="tm-dialog-foot">
				<button class="tm-btn tm-btn-primary" id="tm-hp-ok" type="button"><?= $t('选择') ?></button>
				<button class="tm-btn" id="tm-hp-cancel" type="button"><?= $t('取消') ?></button>
			</div>
		</div>
	</div>

	<!-- 主机群组弹层（从主机弹层的「主机群组 → 选择」打开）：搜索框 + 带复选框的列表 -->
	<div class="tm-overlay" id="tm-gg-overlay" hidden>
		<div class="tm-dialog tm-dialog-narrow" role="dialog" aria-modal="true">
			<div class="tm-dialog-head">
				<span class="tm-dialog-title"><?= $t('主机群组') ?></span>
				<button class="tm-dialog-close" id="tm-gg-close" type="button" title="<?= $t('关闭') ?>">×</button>
			</div>
			<div class="tm-dialog-body">
				<div class="tm-pick-field">
					<span class="tm-pick-label"><?= $t('过滤') ?></span>
					<input class="tm-input" id="tm-gg-search" type="text"
						placeholder="<?= $t('在此输入搜索') ?>" autocomplete="off">
				</div>
				<div class="tm-pick-scroll">
					<table class="tm-pick-table" id="tm-gg-table">
						<thead>
							<tr>
								<th class="tm-pick-check">
									<input type="checkbox" class="checkbox-radio" id="tm-gg-all" autocomplete="off">
									<label for="tm-gg-all"><span></span></label>
								</th>
								<th><?= $t('名称') ?></th>
							</tr>
						</thead>
						<tbody id="tm-gg-list"></tbody>
					</table>
				</div>
			</div>
			<div class="tm-dialog-foot">
				<button class="tm-btn tm-btn-primary" id="tm-gg-ok" type="button"><?= $t('选择') ?></button>
				<button class="tm-btn" id="tm-gg-cancel" type="button"><?= $t('取消') ?></button>
			</div>
		</div>
	</div>

	<!-- 触发器选择器：连线手工绑定触发器（新加的电脑 ↔ 交换机这种没有 LLDP 端口信息的线，
	     自动关键词匹配覆盖不到，只能手工挑）。列表就是两端主机上的触发器。 -->
	<div class="tm-overlay" id="tm-trig-overlay" hidden>
		<div class="tm-dialog tm-dialog-wide" role="dialog" aria-modal="true">
			<div class="tm-dialog-head">
				<span class="tm-dialog-title" id="tm-trig-title"><?= $t('触发器') ?></span>
				<button class="tm-dialog-close" id="tm-trig-close" type="button" title="<?= $t('关闭') ?>">×</button>
			</div>
			<div class="tm-dialog-body">
				<div class="tm-pick-field">
					<span class="tm-pick-label"><?= $t('主机群组') ?></span>
					<div class="tm-pick-box" id="tm-trig-groupbox" role="button" tabindex="0"></div>
					<button class="tm-btn" id="tm-trig-grouppick" type="button"><?= $t('选择') ?></button>
				</div>
				<div class="tm-pick-field">
					<span class="tm-pick-label"><?= $t('主机') ?></span>
					<div class="tm-pick-box" id="tm-trig-hostbox" role="button" tabindex="0"></div>
					<button class="tm-btn" id="tm-trig-hostpick" type="button"><?= $t('选择') ?></button>
				</div>
				<div class="tm-pick-field">
					<span class="tm-pick-label"><?= $t('过滤') ?></span>
					<input class="tm-input" id="tm-trig-search" type="text"
						placeholder="<?= $t('在此输入搜索') ?>" autocomplete="off">
				</div>
				<div class="tm-pick-scroll">
					<table class="tm-pick-table" id="tm-trig-table">
						<thead>
							<tr>
								<th class="tm-pick-check">
									<input type="checkbox" class="checkbox-radio" id="tm-trig-all" autocomplete="off">
									<label for="tm-trig-all"><span></span></label>
								</th>
								<th><?= $t('名称') ?></th>
								<th><?= $t('严重级别') ?></th>
								<th><?= $t('状态') ?></th>
								<th><?= $t('主机') ?></th>
							</tr>
						</thead>
						<tbody id="tm-trig-list"></tbody>
					</table>
				</div>
			</div>
			<div class="tm-dialog-foot">
				<button class="tm-btn tm-btn-primary" id="tm-trig-ok" type="button"><?= $t('选择') ?></button>
				<button class="tm-btn" id="tm-trig-cancel" type="button"><?= $t('取消') ?></button>
			</div>
		</div>
	</div>

	<!-- 主机选择器 -->
	<div class="tm-overlay" id="tm-host-overlay" hidden>
		<div class="tm-dialog tm-dialog-narrow" role="dialog" aria-modal="true">
			<div class="tm-dialog-head">
				<span class="tm-dialog-title"><?= $t('选择主机') ?></span>
				<button class="tm-dialog-close" id="tm-host-close" type="button" title="<?= $t('关闭') ?>">×</button>
			</div>
			<div class="tm-dialog-body">
				<input class="tm-input tm-input-block" id="tm-host-search" type="text"
					placeholder="<?= $t('在此输入搜索') ?>" autocomplete="off">
				<div class="tm-host-list" id="tm-host-list"></div>
			</div>
			<div class="tm-dialog-foot">
				<button class="tm-btn" id="tm-host-cancel" type="button"><?= $t('关闭') ?></button>
			</div>
		</div>
	</div>
</div>
