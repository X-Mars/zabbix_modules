<?php

require_once dirname(__DIR__).'/lib/ViewRenderer.php';
require_once dirname(__DIR__).'/lib/LanguageManager.php';

use Modules\ZabbixIpam\Lib\LanguageManager;
use Modules\ZabbixIpam\Lib\ViewRenderer;

$t = [LanguageManager::class, 't'];
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$counts = ['pending' => 0, 'running' => 0, 'completed' => 0, 'failed' => 0];
foreach ($data['tasks'] as $task) {
    if (isset($counts[$task['status']])) {
        $counts[$task['status']]++;
    }
}

$status_options = '';
foreach (['all' => 'All statuses', 'pending' => 'Pending', 'running' => 'Running', 'completed' => 'Completed', 'failed' => 'Failed', 'stopped' => 'Stopped'] as $value => $label) {
    $selected = $data['filters']['status'] === $value ? ' selected' : '';
    $status_options .= '<option value="'.$value.'"'.$selected.'>'.$t($label).'</option>';
}

$rows = '';
foreach ($data['tasks'] as $task) {
    $percent = $task['total'] ? min(100, round($task['scanned'] / $task['total'] * 100)) : 0;
    $action = in_array($task['status'], ['pending', 'running'], true)
        ? '<button class="btn-danger-link js-stop" data-task-id="'.$e($task['id']).'">'.$t('Stop').'</button>'
        : '<a class="btn-link" href="zabbix.php?action=ip.manager&amp;rangeid='.$e($task['range_id']).'">'.$t('View range').'</a>';

    $rows .= '<tr data-task-row="'.$e($task['id']).'">'
        .'<td><code>'.$e($task['id']).'</code><div class="ipam-range-sub">'.$e($task['range_name']).'</div></td>'
        .'<td><span class="task-badge status-'.$e($task['status']).'">'.$t(ucfirst($task['status'])).'</span></td>'
        .'<td><div class="task-progress"><i style="width:'.$percent.'%"></i></div><small data-progress-text>'.(int) $task['scanned'].' / '.(int) $task['total'].' · '.$percent.'%</small></td>'
        .'<td data-alive>'.(int) $task['alive'].'</td>'
        .'<td data-shards>'.(int) $task['completed_shards'].' / '.count($task['shards']).'</td>'
        .'<td>'.$e($task['created_at']).'</td>'
        .'<td class="ipam-actions">'.$action.'</td></tr>';
}

$root_attributes = 'data-loading="'.$e($t('Loading...')).'" '
    .'data-request-failed="'.$e($t('Request failed.')).'" '
    .'data-label-pending="'.$e($t('Pending')).'" '
    .'data-label-running="'.$e($t('Running')).'" '
    .'data-label-completed="'.$e($t('Completed')).'" '
    .'data-label-failed="'.$e($t('Failed')).'" '
    .'data-label-stopped="'.$e($t('Stopped')).'"';

$html = '<link rel="stylesheet" href="modules/zabbix_ipam/assets/css/ipam.css?v=1.2.0"><link rel="stylesheet" href="modules/zabbix_ipam/assets/css/ipam-responsive.css?v=1.2.0">'
    .'<div class="ipam" '.$root_attributes.'><header class="ipam-head"><div><h1>'.$t('Task Management').'</h1><p>'.$t('Track ICMP scan progress and review completed background tasks.').'</p></div>'
    .'<div class="ipam-head-actions"><a class="btn-link" href="zabbix.php?action=ip.detail">'.$t('IP Details').'</a><a class="btn-primary" href="zabbix.php?action=ip.manager">'.$t('IP Management').'</a></div></header>'
    .'<div class="task-summary"><div><strong>'.count($data['tasks']).'</strong><span>'.$t('Tasks').'</span></div>'
    .'<div><strong>'.$counts['running'].'</strong><span>'.$t('Running').'</span></div><div><strong>'.$counts['completed'].'</strong><span>'.$t('Completed').'</span></div><div><strong>'.$counts['failed'].'</strong><span>'.$t('Failed').'</span></div></div>'
    .'<form class="ipam-toolbar" method="get"><input type="hidden" name="action" value="ip.scan">'
    .'<label><span>'.$t('Search').'</span><input name="search" value="'.$e($data['filters']['search']).'" placeholder="'.$t('Search task ID or range name').'"></label>'
    .'<label><span>'.$t('Status').'</span><select name="status">'.$status_options.'</select></label><button class="btn-alt">'.$t('Filter').'</button><a class="btn-link" href="zabbix.php?action=ip.scan">'.$t('Reset').'</a></form>'
    .'<div class="ipam-table-wrap"><table class="ipam-table ipam-task-table"><thead><tr><th>'.$t('Task / IP range').'</th><th>'.$t('Status').'</th><th>'.$t('Progress').'</th><th>'.$t('Alive').'</th><th>'.$t('Shards').'</th><th>'.$t('Created').'</th><th>'.$t('Actions').'</th></tr></thead>'
    .'<tbody>'.($rows ?: '<tr><td colspan="7" class="ipam-empty">'.$t('No scan tasks found.').'</td></tr>').'</tbody></table></div></div>'
    .'<div class="ipam-toast" id="ipam-toast" hidden></div><script src="modules/zabbix_ipam/assets/js/ipam.js.php?v=1.2.0"></script>';

ViewRenderer::render($data['title'], $html);
