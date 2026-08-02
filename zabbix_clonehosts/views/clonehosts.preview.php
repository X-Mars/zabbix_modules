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
$need_manual_assets = CompatHelper::isZabbix60();
$module_asset_base = 'modules/zabbix_clonehosts/assets';

$source_host = $data['source_host'];
$host_data = $data['host_data'];
$conflicts = $data['conflicts'];
$total_count = $data['total_count'];
$conflict_count = $data['conflict_count'];
$field_status = $data['field_status'] ?? [];

// Build source host summary.
$src_groups = [];
if (!empty($source_host['hostgroups'])) {
	foreach ($source_host['hostgroups'] as $g) {
		$src_groups[] = htmlspecialchars($g['name']);
	}
}

$src_templates = [];
if (!empty($source_host['parentTemplates'])) {
	foreach ($source_host['parentTemplates'] as $tp) {
		$src_templates[] = htmlspecialchars($tp['name']);
	}
}

$src_tags = [];
if (!empty($source_host['tags'])) {
	foreach ($source_host['tags'] as $tag) {
		$src_tags[] = htmlspecialchars($tag['tag'] . '=' . ($tag['value'] ?? ''));
	}
}

$src_macros = [];
if (!empty($source_host['macros'])) {
	foreach ($source_host['macros'] as $macro) {
		// Secret macros (type=1) don't return 'value' in Zabbix 7.x API.
		$macro_value = ($macro['type'] ?? 0) == 1 ? '******' : ($macro['value'] ?? '');
		$src_macros[] = htmlspecialchars($macro['macro'] . '=' . $macro_value);
	}
}

$src_interfaces = [];
if (!empty($source_host['interfaces'])) {
	foreach ($source_host['interfaces'] as $iface) {
		$type_names = [1 => LangHelper::t('iface.Agent'), 2 => LangHelper::t('iface.SNMP'), 3 => LangHelper::t('iface.IPMI'), 4 => LangHelper::t('iface.JMX')];
		$type_name = $type_names[$iface['type']] ?? (LangHelper::t('iface.Type') . $iface['type']);
		$src_interfaces[] = htmlspecialchars($type_name . ': ' . ($iface['useip'] ? $iface['ip'] : $iface['dns']) . ':' . $iface['port']);
	}
}

// Prepare host data for JavaScript (for AJAX import).
$js_host_data = [];
foreach ($host_data as $item) {
	$js_host_data[] = [
		'host' => $item['host'] ?? '',
		'visible_name' => $item['visible_name'] ?? '',
		'ip' => $item['ip'] ?? '',
		'port' => $item['port'] ?? '',
		'groups' => $item['groups'] ?? '',
		'templates' => $item['templates'] ?? '',
		'tags' => $item['tags'] ?? '',
		'macros' => $item['macros'] ?? '',
		'description' => $item['description'] ?? ''
	];
}
?>

<?php if ($need_manual_assets): ?>
<link rel="stylesheet" href="<?= $module_asset_base ?>/css/clonehosts.css?v=<?= rawurlencode($data['module_version'] ?? '1.2.0') ?>">
<?php endif; ?>

<div class="clonehosts-page">
	<!-- Source Host Summary -->
	<div class="clonehosts-section">
		<h3><?= LangHelper::t('preview.source_config') ?></h3>
		<div class="source-host-summary">
			<table class="list-table">
				<tr>
					<th><?= LangHelper::t('col.host_name') ?></th>
					<td><?= htmlspecialchars($source_host['host'] ?? '') ?></td>
				</tr>
				<tr>
					<th><?= LangHelper::t('col.visible_name') ?></th>
					<td><?= htmlspecialchars($source_host['name'] ?? '') ?></td>
				</tr>
				<tr>
					<th><?= LangHelper::t('preview.interfaces') ?></th>
					<td><?= !empty($src_interfaces) ? implode('<br/>', $src_interfaces) : LangHelper::t('preview.none') ?></td>
				</tr>
				<tr>
					<th><?= LangHelper::t('col.host_groups') ?></th>
					<td><?= !empty($src_groups) ? implode(', ', $src_groups) : LangHelper::t('preview.none') ?></td>
				</tr>
				<tr>
					<th><?= LangHelper::t('col.templates') ?></th>
					<td><?= !empty($src_templates) ? implode(', ', $src_templates) : LangHelper::t('preview.none') ?></td>
				</tr>
				<tr>
					<th><?= LangHelper::t('col.tags') ?></th>
					<td><?= !empty($src_tags) ? implode(', ', $src_tags) : LangHelper::t('preview.none') ?></td>
				</tr>
				<tr>
					<th><?= LangHelper::t('col.macros') ?></th>
					<td><?= !empty($src_macros) ? implode(', ', $src_macros) : LangHelper::t('preview.none') ?></td>
				</tr>
				<tr>
					<th><?= LangHelper::t('col.description') ?></th>
					<td><?= htmlspecialchars($source_host['description'] ?? '') ?></td>
				</tr>
			</table>
		</div>
	</div>

	<!-- Preview Summary -->
	<div class="clonehosts-section">
		<div class="preview-summary-bar">
			<?php if ($conflict_count > 0): ?>
				<span class="summary-item summary-total"><?= LangHelper::t('preview.total') ?> <?= $total_count ?></span>
				<span class="summary-item summary-conflict"><?= LangHelper::t('preview.conflicts') ?> <?= $conflict_count ?></span>
				<span class="summary-item summary-ok"><?= LangHelper::t('preview.importable') ?> <?= ($total_count - $conflict_count) ?></span>
			<?php else: ?>
				<span class="summary-item summary-total"><?= LangHelper::t('preview.pending_count') ?> <?= $total_count ?></span>
				<span class="summary-item summary-ok"><?= LangHelper::t('preview.no_conflicts') ?></span>
			<?php endif; ?>
		</div>
	</div>

	<!-- Preview Table -->
	<div class="clonehosts-section">
		<h3><?= LangHelper::t('preview.title') ?></h3>
		<div class="field-legend">
			<span class="field-legend-item">
				<span class="field-legend-swatch exists"></span> <?= LangHelper::t('legend.exists') ?>
			</span>
			<span class="field-legend-item">
				<span class="field-legend-swatch new"></span> <?= LangHelper::t('legend.new') ?>
			</span>
			<span class="field-legend-item">
				<span class="field-legend-swatch not_found"></span> <?= LangHelper::t('legend.not_found') ?>
			</span>
			<span class="field-legend-item">
				<span class="field-legend-swatch inherited"></span> <?= LangHelper::t('legend.inherited') ?>
			</span>
		</div>
		<div class="table-wrapper">
			<table id="preview-table" class="list-table host-data-table">
				<thead>
					<tr>
						<th class="col-select"><input type="checkbox" id="preview-select-all" checked /></th>
						<th>#</th>
						<th><?= LangHelper::t('col.status') ?></th>
						<th><?= LangHelper::t('col.host_name') ?></th>
						<th><?= LangHelper::t('col.visible_name') ?></th>
						<th><?= LangHelper::t('col.interface_ip') ?></th>
						<th><?= LangHelper::t('col.port') ?></th>
						<th><?= LangHelper::t('col.host_groups') ?></th>
						<th><?= LangHelper::t('col.templates') ?></th>
						<th><?= LangHelper::t('col.tags') ?></th>
						<th><?= LangHelper::t('col.macros') ?></th>
						<th><?= LangHelper::t('col.description') ?></th>
					</tr>
				</thead>
				<tbody>
					<?php $idx = 0; foreach ($host_data as $item): $idx++; ?>
						<?php
							$has_conflict = !empty($conflicts[$idx - 1]);
							$row_class = $has_conflict ? 'row-conflict' : 'row-ok';
							$status_icon = $has_conflict
								? '<span class="icon-conflict" title="' . htmlspecialchars(implode('; ', $conflicts[$idx - 1])) . '">!</span>'
								: '<span class="icon-ok" title="' . LangHelper::t('preview.ready') . '">&#10003;</span>';
							$fstatus = $field_status[$idx - 1] ?? [];
							// Conflict rows: disabled & unchecked; ready rows: checked by default.
							$checkbox = $has_conflict
								? '<input type="checkbox" class="row-select" data-ready="0" disabled />'
								: '<input type="checkbox" class="row-select" data-ready="1" checked />';
						?>
						<tr class="<?= $row_class ?>" data-index="<?= $idx - 1 ?>">
							<td class="col-select"><?= $checkbox ?></td>
							<td><?= $idx ?></td>
							<td class="col-status"><?= $status_icon ?></td>
							<td><?= htmlspecialchars($item['host'] ?? '') ?></td>
							<td><?= !empty($item['visible_name']) ? htmlspecialchars($item['visible_name']) : '<span class="inherited">' . LangHelper::t('preview.inherited') . '</span>' ?></td>
							<td><?= htmlspecialchars($item['ip'] ?? '') ?></td>
							<td><?= !empty($item['port']) ? htmlspecialchars($item['port']) : '<span class="inherited">' . LangHelper::t('preview.inherited') . '</span>' ?></td>
							<td class="col-field-status">
								<?php if (!empty($fstatus['groups'])): ?>
									<?php foreach ($fstatus['groups'] as $g): ?>
										<span class="field-badge field-badge-<?= $g['status'] ?>" title="<?= $g['status'] === 'exists' ? LangHelper::t('legend.exists_tooltip') : LangHelper::t('legend.new_tooltip_group') ?>">
											<?= htmlspecialchars($g['name']) ?>
										</span>
									<?php endforeach; ?>
								<?php elseif (!empty($fstatus['groups_inherited'])): ?>
									<span class="inherited"><?= LangHelper::t('preview.inherited') ?></span>
								<?php else: ?>
									<span class="inherited"><?= LangHelper::t('preview.inherited') ?></span>
								<?php endif; ?>
							</td>
							<td class="col-field-status">
								<?php if (!empty($fstatus['templates'])): ?>
									<?php foreach ($fstatus['templates'] as $tp): ?>
										<span class="field-badge field-badge-<?= $tp['status'] ?>" title="<?= $tp['status'] === 'exists' ? LangHelper::t('legend.exists_tooltip') : LangHelper::t('legend.not_found_tooltip_template') ?>">
											<?= htmlspecialchars($tp['name']) ?>
										</span>
									<?php endforeach; ?>
								<?php elseif (!empty($fstatus['templates_inherited'])): ?>
									<span class="inherited"><?= LangHelper::t('preview.inherited') ?></span>
								<?php else: ?>
									<span class="inherited"><?= LangHelper::t('preview.inherited') ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if (!empty($fstatus['tags_inherited']) || empty($item['tags'])): ?>
									<span class="inherited"><?= LangHelper::t('preview.inherited') ?></span>
								<?php else: ?>
									<span class="field-badge field-badge-new"><?= htmlspecialchars($item['tags']) ?></span>
								<?php endif; ?>
							</td>
							<td>
								<?php if (!empty($fstatus['macros_inherited']) || empty($item['macros'])): ?>
									<span class="inherited"><?= LangHelper::t('preview.inherited') ?></span>
								<?php else: ?>
									<span class="field-badge field-badge-new"><?= htmlspecialchars($item['macros']) ?></span>
								<?php endif; ?>
							</td>
							<td><?= !empty($item['description']) ? htmlspecialchars($item['description']) : '<span class="inherited">' . LangHelper::t('preview.inherited') . '</span>' ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Import Actions -->
	<div class="clonehosts-section">
		<div class="action-bar">
			<form id="back-form" method="post" action="zabbix.php?action=clonehosts" style="display:inline;">
				<input type="hidden" name="source_hostid" value="<?= htmlspecialchars($source_host['hostid'] ?? '') ?>" />
				<input type="hidden" name="host_data" value="<?= htmlspecialchars(json_encode($js_host_data)) ?>" />
				<input type="hidden" name="return_from_preview" value="1" />
				<button type="submit" class="btn btn-back">&#8592; <?= LangHelper::t('preview.back_edit') ?></button>
			</form>
			<button type="button" id="start-import-btn" class="btn btn-primary"><?= LangHelper::t('preview.import_selected', ['{count}' => ($total_count - $conflict_count)]) ?></button>
			<?php if ($conflict_count > 0): ?>
				<span class="conflict-warning"><?= LangHelper::t('preview.resolve_conflicts') ?></span>
			<?php endif; ?>
		</div>
	</div>

	<!-- Import Progress & Results (hidden initially) -->
	<div id="import-progress-section" class="clonehosts-section" style="display:none;">
		<h3><?= LangHelper::t('import.progress_title') ?></h3>
		<div class="progress-bar-wrapper">
			<div class="progress-bar-container">
				<div id="progress-bar" class="progress-bar" style="width: 0%;"></div>
			</div>
			<span id="progress-text" class="progress-text">0 / <?= $total_count ?></span>
		</div>

		<div id="progress-stats" class="progress-stats">
			<span class="progress-stat progress-success"><?= LangHelper::t('import.status_success') ?> <span id="progress-success">0</span></span>
			<span class="progress-stat progress-failed"><?= LangHelper::t('import.status_failed') ?> <span id="progress-failed">0</span></span>
			<span class="progress-stat progress-pending"><?= LangHelper::t('import.status_pending') ?> <span id="progress-pending"><?= $total_count ?></span></span>
		</div>

		<div id="import-results-section" style="display:none;">
			<h3><?= LangHelper::t('import.results_title') ?></h3>
			<div class="results-summary">
				<span class="result-item result-success"><?= LangHelper::t('import.success') ?> <span id="success-count">0</span></span>
				<span class="result-item result-failed"><?= LangHelper::t('import.failed') ?> <span id="failed-count">0</span></span>
				<span class="result-item result-total"><?= LangHelper::t('preview.total') ?> <span id="total-count"><?= $total_count ?></span></span>
				<button type="button" id="download-report-btn" class="btn btn-link" style="display:none;"><?= LangHelper::t('import.download_report') ?></button>
			</div>
			<div class="table-wrapper">
				<table id="results-table" class="list-table results-table">
					<thead>
						<tr>
							<th>#</th>
							<th><?= LangHelper::t('col.result') ?></th>
							<th><?= LangHelper::t('col.host_name') ?></th>
							<th><?= LangHelper::t('col.interface_ip') ?></th>
							<th><?= LangHelper::t('col.host_id') ?></th>
							<th><?= LangHelper::t('col.error') ?></th>
						</tr>
					</thead>
					<tbody id="results-tbody"></tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
	window.clonehostsPreview = {
		sourceHostid: <?= json_encode($source_host['hostid'] ?? '') ?>,
		hostData: <?= json_encode($js_host_data) ?>,
		ajaxUrl: 'zabbix.php?action=clonehosts.import',
		totalCount: <?= json_encode($total_count) ?>,
		lang: <?= json_encode(LangHelper::getAllForJs()) ?>
	};
</script>

<?php if ($need_manual_assets): ?>
<script type="text/javascript" src="<?= $module_asset_base ?>/js/clonehosts.preview.js.php?v=<?= rawurlencode($data['module_version'] ?? '1.2.0') ?>"></script>
<?php endif; ?>
