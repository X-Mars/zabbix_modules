<?php

require_once dirname(__DIR__).'/lib/ViewRenderer.php';
require_once dirname(__DIR__).'/lib/LanguageManager.php';
require_once dirname(__DIR__).'/lib/TimeFormatter.php';
require_once dirname(__DIR__).'/lib/ViewPagination.php';

use Modules\ZabbixIpam\Lib\LanguageManager;
use Modules\ZabbixIpam\Lib\TimeFormatter;
use Modules\ZabbixIpam\Lib\ViewPagination;
use Modules\ZabbixIpam\Lib\ViewRenderer;

$t = [LanguageManager::class, 't'];
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$range_options = '<option value="">'.$t('All IP ranges').'</option>';
foreach ($data['all_ranges'] as $range) {
    $selected = $data['filters']['rangeid'] === $range['id'] ? ' selected' : '';
    $range_options .= '<option value="'.$e($range['id']).'"'.$selected.'>'.$e($range['name'].' · '.$range['range']).'</option>';
}

$enabled_options = '';
foreach (['all' => 'All enabled statuses', 'enabled' => 'Enabled', 'disabled' => 'Disabled'] as $value => $label) {
    $selected = $data['filters']['enabled_status'] === $value ? ' selected' : '';
    $enabled_options .= '<option value="'.$value.'"'.$selected.'>'.$t($label).'</option>';
}

$scan_options = '';
foreach (['all' => 'All scan statuses', 'never' => 'Never', 'pending' => 'Pending', 'running' => 'Running', 'completed' => 'Completed', 'failed' => 'Failed', 'stopped' => 'Stopped'] as $value => $label) {
    $selected = $data['filters']['scan_status'] === $value ? ' selected' : '';
    $scan_options .= '<option value="'.$value.'"'.$selected.'>'.$t($label).'</option>';
}

$rows = '';
$row_number = (($data['pagination']['page'] - 1) * $data['pagination']['per_page']) + 1;
foreach ($data['rows'] as $row) {
    $range = $row['range'];
    $task = $row['task'];
    $status = $row['task_status'];
    $edit_data = [
        'id' => $range['id'],
        'name' => $range['name'],
        'range' => $range['range'],
        'enabled' => !empty($range['enabled'])
    ];
    $task_url = $task
        ? 'zabbix.php?action=ip.scan&amp;taskid='.$e($task['id'])
        : 'zabbix.php?action=ip.scan&amp;search='.rawurlencode((string) $range['name']);

    $last_scan = TimeFormatter::display($row['last_scan']);
    $rows .= '<tr><td class="ipam-index">'.$row_number++.'</td>'
        .'<td>'.$e($range['name']).'</td>'
        .'<td><div class="ipam-range-main">'.$e($range['range']).'</div></td>'
        .'<td><a class="ipam-count" href="#" data-range-id="'.$e($range['id']).'"><strong>'.(int) $row['alive'].'</strong><span>/</span>'.(int) $row['total'].'</a>'
        .'</td><td><span class="ipam-state '.(!empty($range['enabled']) ? 'is-on' : 'is-off').'">'.$t(!empty($range['enabled']) ? 'Enabled' : 'Disabled').'</span></td>'
        .'<td><span class="ipam-task-state status-'.$e($status).'">'.$t(ucfirst($status)).'</span></td>'
        .'<td>'.($last_scan !== '' ? '<time datetime="'.$e($row['last_scan']).'">'.$e($last_scan).'</time>' : '<span class="ipam-muted">'.$t('Never').'</span>').'</td>'
        .'<td class="ipam-actions"><button class="btn-alt btn-scan js-scan" data-range-id="'.$e($range['id']).'">'.$t('Start scan').'</button>'
        .'<a class="btn-alt btn-task" href="'.$task_url.'">'.$t('View task').'</a>'
        .'<button class="btn-alt btn-edit js-edit" data-range="'.$e(json_encode($edit_data, JSON_UNESCAPED_UNICODE)).'">'.$t('Edit').'</button>'
        .'<button class="btn-alt btn-delete js-delete" data-range-id="'.$e($range['id']).'">'.$t('Delete').'</button></td></tr>';
}

$pagination = ViewPagination::render('ip.manager', $data['pagination'], $data['filters']);

$html = '<link rel="stylesheet" href="modules/zabbix_ipam/assets/css/ipam.css?v=1.5.0"><link rel="stylesheet" href="modules/zabbix_ipam/assets/css/ipam-responsive.css?v=1.5.0">'
    .'<div class="ipam" data-confirm-delete="'.$e($t('Delete this IP range?')).'" data-loading="'.$e($t('Loading...')).'" data-request-failed="'.$e($t('Request failed.')).'">'
    .'<header class="ipam-head"><div><h1>'.$t('IPAM').'</h1><p>'.$t('Manage IPv4 ranges and run ICMP-only availability scans.').'</p></div>'
    .'<div class="ipam-head-actions"><a class="btn-alt" href="zabbix.php?action=ip.detail">'.$t('IP Details').'</a><a class="btn-alt" href="zabbix.php?action=ip.scan">'.$t('Task Management').'</a><button class="btn-alt js-add">'.$t('Add IP range').'</button></div></header>'
    .'<div class="ipam-help"><strong>'.$t('Quick guide').'</strong><span>'.$t('Add a range, start an ICMP scan, then click the IP count to view the availability matrix. Green means alive; gray means unreachable or not scanned.').'</span></div>'
    .'<form class="ipam-toolbar js-auto-filter" method="get"><input type="hidden" name="action" value="ip.manager"><input type="hidden" name="per_page" value="'.$e($data['pagination']['per_page']).'">'
    .'<label><span>'.$t('Search').'</span><input name="search" value="'.$e($data['filters']['search']).'" placeholder="'.$t('Search name or CIDR').'"></label>'
    .'<label><span>'.$t('IP range').'</span><select name="rangeid">'.$range_options.'</select></label>'
    .'<label><span>'.$t('Enabled status').'</span><select name="enabled_status">'.$enabled_options.'</select></label>'
    .'<label><span>'.$t('Scan status').'</span><select name="scan_status">'.$scan_options.'</select></label></form>'
    .'<div class="ipam-table-wrap"><table class="ipam-table ipam-range-table"><thead><tr><th>'.$t('No.').'</th><th>'.$t('IP range name').'</th><th>'.$t('IP range').'</th><th>'.$t('Alive IPs / Total').'</th><th>'.$t('Enabled status').'</th><th>'.$t('Scan status').'</th><th>'.$t('Last scan time').'</th><th>'.$t('Actions').'</th></tr></thead>'
    .'<tbody>'.($rows ?: '<tr><td colspan="8" class="ipam-empty">'.$t('No IP ranges found.').'</td></tr>').'</tbody></table></div>'.$pagination.'</div>';

$html .= '<div class="ipam-modal" id="range-modal" hidden><div class="ipam-modal-backdrop" data-close></div><section class="ipam-dialog" role="dialog" aria-modal="true">'
    .'<header><h2 id="range-modal-title">'.$t('Add IP range').'</h2><button class="btn-alt ipam-close" data-close aria-label="'.$t('Close').'">×</button></header>'
    .'<form id="range-form"><input type="hidden" name="id"><label>'.$t('Name').'<input name="name" required maxlength="128" placeholder="'.$t('Example: Office network').'"></label>'
    .'<label>'.$t('IP range').'<input name="range" required placeholder="192.168.1.0/24"></label>'
    .'<div class="ipam-form-note"><strong>'.$t('Scan schedule').'</strong><span>'.$t('The scan interval is controlled by crontab. Each enabled IP range is scanned whenever the cron job runs.').'</span></div>'
    .'<label class="ipam-switch-row"><span><strong>'.$t('Enable scheduled scans').'</strong><small>'.$t('When enabled, this range is scanned each time the IPAM cron job runs.').'</small></span><input name="enabled" type="checkbox" checked></label>'
    .'<footer><button type="button" class="btn-alt" data-close>'.$t('Cancel').'</button><button type="button" class="btn-alt js-save-range">'.$t('Save').'</button></footer></form></section></div>';

$html .= '<div class="ipam-modal" id="matrix-modal" hidden><div class="ipam-modal-backdrop" data-close></div><section class="ipam-dialog ipam-dialog-wide" role="dialog" aria-modal="true">'
    .'<header><div><h2>'.$t('IP availability matrix').'</h2><p id="matrix-subtitle"></p></div><button class="btn-alt ipam-close" data-close aria-label="'.$t('Close').'">×</button></header>'
    .'<div class="ipam-legend"><span><i class="alive"></i>'.$t('Alive').'</span><span><i class="dead"></i>'.$t('Unreachable / not scanned').'</span></div><div class="ipam-grid" id="ip-grid"></div></section></div>'
    .'<div class="ipam-toast" id="ipam-toast" hidden></div><script src="modules/zabbix_ipam/assets/js/ipam.js.php?v=1.5.0"></script>';

ViewRenderer::render($data['title'], $html);
