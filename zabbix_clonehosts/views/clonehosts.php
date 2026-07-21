<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/

/**
 * @var CView $this
 * @var array $data
 */

use Modules\ZabbixClonehosts\LangHelper;
use Modules\ZabbixClonehosts\CompatHelper;

// Zabbix 6.0 does not auto-load assets declared in manifest.json.
// Detect once and emit manual <link>/<script> tags at the right places.
$need_manual_assets = CompatHelper::isZabbix60();
$module_asset_base = 'modules/zabbix_clonehosts/assets';

// Build group and template name lists for JavaScript reference.
$group_names = [];
foreach ($data['host_groups'] as $group) {
	$group_names[] = $group['name'];
}

$template_names = [];
foreach ($data['templates'] as $template) {
	$template_names[] = $template['name'];
}

// Build hosts data array for JavaScript filtering.
// Each host entry includes: hostid, name, host, groupids[]
$hosts_js = [];
$host_group_map = $data['host_group_map'] ?? [];
foreach ($data['hosts'] as $hostid => $host) {
	$hosts_js[] = [
		'hostid' => $host['hostid'],
		'name' => $host['name'],
		'host' => $host['host'],
		'groupids' => $host_group_map[$hostid] ?? []
	];
}

// Build host groups data for JavaScript (groupid + name).
$host_groups_js = [];
foreach ($data['host_groups'] as $group) {
	$host_groups_js[] = [
		'groupid' => $group['groupid'],
		'name' => $group['name']
	];
}
?>

<?php if ($need_manual_assets): ?>
<link rel="stylesheet" href="<?= $module_asset_base ?>/css/clonehosts.css?v=<?= rawurlencode($data['module_version'] ?? '1.2.0') ?>">
<?php endif; ?>

<div class="clonehosts-page">
	<!-- Step 1: Source Host Selection -->
	<div class="clonehosts-section">
		<h3><?= LangHelper::t('step1.title') ?></h3>
		<p class="description"><?= LangHelper::t('step1.description') ?></p>

		<!-- Host Group Multiselect -->
		<div class="filter-field-wrapper">
			<label class="filter-field-label" for="group-ms-input"><?= LangHelper::t('filter.host_group') ?></label>
			<div class="multiselect-wrapper" id="group-multiselect">
				<div class="multiselect-selected" id="group-ms-selected"></div>
				<div class="multiselect-input-row">
					<input type="text" id="group-ms-input" class="multiselect-input" placeholder="<?= LangHelper::t('multiselect.search_placeholder') ?>" autocomplete="off" />
					<button type="button" class="btn-grey multiselect-button" id="group-ms-btn"><?= LangHelper::t('multiselect.select_btn') ?></button>
				</div>
				<div class="multiselect-dropdown" id="group-ms-dropdown" style="display:none;"></div>
			</div>
		</div>

		<!-- Host Multiselect (linked to host group) -->
		<div class="filter-field-wrapper">
			<label class="filter-field-label" for="host-ms-input"><?= LangHelper::t('filter.host_name') ?></label>
			<div class="multiselect-wrapper" id="host-multiselect">
				<div class="multiselect-selected" id="host-ms-selected"></div>
				<div class="multiselect-input-row">
					<input type="text" id="host-ms-input" class="multiselect-input" placeholder="<?= LangHelper::t('multiselect.search_placeholder') ?>" autocomplete="off" />
					<button type="button" class="btn-grey multiselect-button" id="host-ms-btn"><?= LangHelper::t('multiselect.select_btn') ?></button>
				</div>
				<div class="multiselect-dropdown" id="host-ms-dropdown" style="display:none;"></div>
			</div>
		</div>

		<!-- Select button popup dialog (shared) -->
		<div class="ms-popup-overlay" id="ms-popup-overlay" style="display:none;">
			<div class="ms-popup-dialog">
				<div class="ms-popup-header">
					<span class="ms-popup-title" id="ms-popup-title"></span>
					<button type="button" class="ms-popup-close" id="ms-popup-close">&times;</button>
				</div>
				<div class="ms-popup-search">
					<input type="text" id="ms-popup-search-input" placeholder="<?= LangHelper::t('multiselect.popup_search_placeholder') ?>" autocomplete="off" />
				</div>
				<div class="ms-popup-list-wrapper">
					<table class="ms-popup-table">
						<thead>
							<tr>
								<th class="ms-popup-check-col"><input type="checkbox" id="ms-popup-select-all" /></th>
								<th class="ms-popup-name-col" id="ms-popup-name-header"><?= LangHelper::t('multiselect.popup_name_header') ?></th>
							</tr>
						</thead>
						<tbody id="ms-popup-tbody"></tbody>
					</table>
				</div>
				<div class="ms-popup-footer">
					<span class="ms-popup-count" id="ms-popup-count"></span>
					<div class="ms-popup-actions">
						<button type="button" class="btn-grey" id="ms-popup-cancel"><?= LangHelper::t('multiselect.popup_cancel') ?></button>
						<button type="button" class="btn-primary ms-popup-apply" id="ms-popup-apply"><?= LangHelper::t('multiselect.popup_apply') ?></button>
					</div>
				</div>
			</div>
		</div>

		<div id="source-host-info" class="source-host-info" style="display:none;"></div>
	</div>

	<!-- Step 2: Data Input -->
	<div class="clonehosts-section">
		<h3><?= LangHelper::t('step2.title') ?></h3>
		<p class="description"><?= LangHelper::t('step2.description') ?></p>

		<!-- Tab switcher -->
		<div class="tabs-nav">
			<button type="button" class="tab-btn active" data-tab="file"><?= LangHelper::t('step2.tab_file') ?></button>
			<button type="button" class="tab-btn" data-tab="table"><?= LangHelper::t('step2.tab_table') ?></button>
		</div>

		<!-- File Upload Tab -->
		<div id="tab-file" class="tab-content active">
			<div class="file-upload-area">
				<input type="file" id="csv-file-input" accept=".csv,.txt" />
				<select id="csv-encoding-select" class="encoding-select">
					<option value="UTF-8">UTF-8</option>
					<option value="GBK">GBK (ANSI)</option>
				</select>
				<button type="button" id="parse-csv-btn" class="btn btn-secondary" disabled><?= LangHelper::t('csv.parse_btn') ?></button>
				<button type="button" id="download-template-btn" class="btn btn-link"><?= LangHelper::t('csv.download_template') ?></button>
			</div>
			<p class="encoding-hint"><?= LangHelper::t('csv.encoding_hint') ?></p>
			<div id="csv-parse-result" class="csv-parse-result" style="display:none;"></div>
		</div>

		<!-- Online Table Entry Tab -->
		<div id="tab-table" class="tab-content" style="display:none;">
			<div class="table-toolbar">
				<button type="button" id="add-row-btn" class="btn btn-secondary"><?= LangHelper::t('table.add_row') ?></button>
				<button type="button" id="remove-row-btn" class="btn btn-secondary"><?= LangHelper::t('table.remove_row') ?></button>
				<button type="button" id="clear-table-btn" class="btn btn-secondary"><?= LangHelper::t('table.clear_all') ?></button>
				<span class="row-count"><?= LangHelper::t('table.row_count') ?> <span id="row-count">0</span></span>
			</div>
			<div class="table-wrapper">
				<table id="host-data-table" class="host-data-table list-table">
					<thead>
						<tr>
							<th class="col-checkbox"><input type="checkbox" id="select-all-rows" /></th>
							<th class="col-host"><?= LangHelper::t('col.host_name') ?> <span class="required">*</span></th>
							<th class="col-visible-name"><?= LangHelper::t('col.visible_name') ?></th>
							<th class="col-ip"><?= LangHelper::t('col.interface_ip') ?> <span class="required">*</span></th>
							<th class="col-port"><?= LangHelper::t('col.port') ?></th>
							<th class="col-groups"><?= LangHelper::t('col.host_groups') ?></th>
							<th class="col-templates"><?= LangHelper::t('col.templates') ?></th>
							<th class="col-tags"><?= LangHelper::t('col.tags') ?></th>
							<th class="col-macros"><?= LangHelper::t('col.macros') ?></th>
							<th class="col-description"><?= LangHelper::t('col.description') ?></th>
						</tr>
					</thead>
					<tbody id="host-data-tbody"></tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- Step 3: Preview -->
	<div class="clonehosts-section">
		<div class="action-bar">
			<button type="button" id="preview-btn" class="btn btn-primary" disabled><?= LangHelper::t('preview.btn') ?></button>
			<span class="preview-hint"><?= LangHelper::t('preview.hint_no_data') ?></span>
		</div>
	</div>
</div>

<!-- Hidden form for submitting to preview -->
<form id="preview-form" method="post" action="zabbix.php?action=clonehosts.preview" style="display:none;">
	<input type="hidden" name="source_hostid" id="hidden-source-hostid" value="" />
	<input type="hidden" name="host_data" id="hidden-host-data" value="" />
</form>

<script type="text/javascript">
	window.clonehostsData = {
		hosts: <?= json_encode($hosts_js) ?>,
		hostGroups: <?= json_encode($host_groups_js) ?>,
		groupNames: <?= json_encode($group_names) ?>,
		templateNames: <?= json_encode($template_names) ?>,
		ajaxUrl: 'zabbix.php?action=clonehosts.import',
		sourceInfoUrl: 'zabbix.php?action=clonehosts.source',
		lang: <?= json_encode($data['lang'] ?? LangHelper::getAllForJs()) ?>,
		returnSourceHostid: <?= json_encode($data['return_source_hostid'] ?? '') ?>,
		returnHostData: <?= json_encode($data['return_host_data'] ?? []) ?>
	};
</script>

<?php if ($need_manual_assets): ?>
<script type="text/javascript" src="<?= $module_asset_base ?>/js/clonehosts.js.php?v=<?= rawurlencode($data['module_version'] ?? '1.2.0') ?>"></script>
<?php endif; ?>
