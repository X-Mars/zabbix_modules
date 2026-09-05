<?php

require_once dirname(__DIR__).'/lib/ViewRenderer.php';
require_once dirname(__DIR__).'/lib/LanguageManager.php';

use Modules\ZabbixIpam\Lib\LanguageManager;
use Modules\ZabbixIpam\Lib\ViewRenderer;

$t = [LanguageManager::class, 't'];
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$range_options = '<option value="">'.$t('All IP ranges').'</option>';
foreach ($data['all_ranges'] as $range) {
    $selected = $data['filters']['rangeid'] === $range['id'] ? ' selected' : '';
    $range_options .= '<option value="'.$e($range['id']).'"'.$selected.'>'.$e($range['name'].' · '.$range['range']).'</option>';
}

$status_options = '';
foreach (['all' => 'All statuses', 'enabled' => 'Enabled', 'disabled' => 'Disabled', 'pending' => 'Pending', 'running' => 'Running', 'completed' => 'Completed', 'failed' => 'Failed'] as $value => $label) {
    $selected = $data['filters']['status'] === $value ? ' selected' : '';
    $status_options .= '<option value="'.$value.'"'.$selected.'>'.$t($label).'</option>';
}

$rows = '';
foreach ($data['rows'] as $row) {
    $range = $row['range'];
    $task = $row['task'];
    $status = $row['task_status'];
    $edit_data = [
        'id' => $range['id'],
        'name' => $range['name'],
        'range' => $range['range'],
        'scan_interval' => (int) ($range['scan_interval'] ?? 60),
        'enabled' => !empty($range['enabled'])
    ];
    $task_url = $task
        ? 'zabbix.php?action=ip.scan&amp;taskid='.$e($task['id'])
        : 'zabbix.php?action=ip.scan&amp;search='.rawurlencode((string) $range['name']);

    $rows .= '<tr><td><div class="ipam-range-main">'.$e($range['range']).'</div>'
        .'<div class="ipam-range-sub">'.$e($range['name']).' <span class="ipam-state '.(!empty($range['enabled']) ? 'is-on' : 'is-off').'">'.$t(!empty($range['enabled']) ? 'Enabled' : 'Disabled').'</span></div></td>'
        .'<td><button class="ipam-count" data-range-id="'.$e($range['id']).'"><strong>'.(int) $row['alive'].'</strong><span>/</span>'.(int) $row['total'].'</button>'
        .'<div class="ipam-task-state status-'.$e($status).'">'.$t(ucfirst($status)).'</div></td>'
        .'<td class="ipam-actions"><button class="btn-alt js-scan" data-range-id="'.$e($range['id']).'">'.$t('Start scan').'</button>'
        .'<a class="btn-link" href="'.$task_url.'">'.$t('View task').'</a>'
        .'<button class="btn-link js-edit" data-range="'.$e(json_encode($edit_data, JSON_UNESCAPED_UNICODE)).'">'.$t('Edit').'</button>'
        .'<button class="btn-danger-link js-delete" data-range-id="'.$e($range['id']).'">'.$t('Delete').'</button></td></tr>';
}

$html = '<link rel="stylesheet" href="modules/zabbix_ipam/assets/css/ipam.css?v=1.2.0"><link rel="stylesheet" href="modules/zabbix_ipam/assets/css/ipam-responsive.css?v=1.2.0">'
    .'<div class="ipam" data-confirm-delete="'.$e($t('Delete this IP range?')).'" data-loading="'.$e($t('Loading...')).'" data-request-failed="'.$e($t('Request failed.')).'">'
    .'<header class="ipam-head"><div><h1>'.$t('IPAM').'</h1><p>'.$t('Manage IPv4 ranges and run ICMP-only availability scans.').'</p></div>'
    .'<div class="ipam-head-actions"><a class="btn-link" href="zabbix.php?action=ip.detail">'.$t('IP Details').'</a><a class="btn-link" href="zabbix.php?action=ip.scan">'.$t('Task Management').'</a><button class="btn-primary js-add">'.$t('Add IP range').'</button></div></header>'
    .'<div class="ipam-help"><strong>'.$t('Quick guide').'</strong><span>'.$t('Add a range, start an ICMP scan, then click the IP count to view the availability matrix. Green means alive; gray means unreachable or not scanned.').'</span></div>'
    .'<form class="ipam-toolbar" method="get"><input type="hidden" name="action" value="ip.manager">'
    .'<label><span>'.$t('Search').'</span><input name="search" value="'.$e($data['filters']['search']).'" placeholder="'.$t('Search name or CIDR').'"></label>'
    .'<label><span>'.$t('IP range').'</span><select name="rangeid">'.$range_options.'</select></label>'
    .'<label><span>'.$t('Status').'</span><select name="status">'.$status_options.'</select></label>'
    .'<button class="btn-alt">'.$t('Filter').'</button><a class="btn-link" href="zabbix.php?action=ip.manager">'.$t('Reset').'</a></form>'
    .'<div class="ipam-table-wrap"><table class="ipam-table ipam-range-table"><thead><tr><th>'.$t('IP range').'</th><th>'.$t('Alive IPs / Total').'</th><th>'.$t('Actions').'</th></tr></thead>'
    .'<tbody>'.($rows ?: '<tr><td colspan="3" class="ipam-empty">'.$t('No IP ranges found.').'</td></tr>').'</tbody></table></div></div>';

$html .= '<div class="ipam-modal" id="range-modal" hidden><div class="ipam-modal-backdrop" data-close></div><section class="ipam-dialog" role="dialog" aria-modal="true">'
    .'<header><h2 id="range-modal-title">'.$t('Add IP range').'</h2><button class="ipam-close" data-close aria-label="'.$t('Close').'">×</button></header>'
    .'<form id="range-form"><input type="hidden" name="id"><label>'.$t('Name').'<input name="name" required maxlength="128" placeholder="'.$t('Example: Office network').'"></label>'
    .'<label>'.$t('IP range').'<input name="range" required placeholder="192.168.1.0/24"></label>'
    .'<label>'.$t('Scan interval (minutes)').'<input name="scan_interval" type="number" min="1" max="525600" value="60" required></label>'
    .'<label class="ipam-switch-row"><span><strong>'.$t('Enable scheduled scans').'</strong><small>'.$t('The cron entry scans this range when its interval is due.').'</small></span><input name="enabled" type="checkbox" checked></label>'
    .'<footer><button type="button" class="btn-link" data-close>'.$t('Cancel').'</button><button type="button" class="btn-primary js-save-range">'.$t('Save').'</button></footer></form></section></div>';

$html .= '<div class="ipam-modal" id="matrix-modal" hidden><div class="ipam-modal-backdrop" data-close></div><section class="ipam-dialog ipam-dialog-wide" role="dialog" aria-modal="true">'
    .'<header><div><h2>'.$t('IP availability matrix').'</h2><p id="matrix-subtitle"></p></div><button class="ipam-close" data-close aria-label="'.$t('Close').'">×</button></header>'
    .'<div class="ipam-legend"><span><i class="alive"></i>'.$t('Alive').'</span><span><i class="dead"></i>'.$t('Unreachable / not scanned').'</span></div><div class="ipam-grid" id="ip-grid"></div></section></div>'
    .'<div class="ipam-toast" id="ipam-toast" hidden></div><script src="modules/zabbix_ipam/assets/js/ipam.js.php?v=1.2.0"></script>';

ViewRenderer::render($data['title'], $html);
