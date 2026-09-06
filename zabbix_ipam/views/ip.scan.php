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

$counts = $data['summary'];

$status_options = '';
foreach (['all' => 'All statuses', 'pending' => 'Pending', 'running' => 'Running', 'completed' => 'Completed', 'failed' => 'Failed', 'stopped' => 'Stopped'] as $value => $label) {
    $selected = $data['filters']['status'] === $value ? ' selected' : '';
    $status_options .= '<option value="'.$value.'"'.$selected.'>'.$t($label).'</option>';
}

$rows = '';
$row_number = (($data['pagination']['page'] - 1) * $data['pagination']['per_page']) + 1;
foreach ($data['tasks'] as $task) {
    $percent = $task['total'] ? min(100, round($task['scanned'] / $task['total'] * 100)) : 0;
    $action = in_array($task['status'], ['pending', 'running'], true)
        ? '<button class="btn-alt js-stop" data-task-id="'.$e($task['id']).'">'.$t('Stop').'</button>'
        : '<a class="btn-alt" href="zabbix.php?action=ip.manager&amp;rangeid='.$e($task['range_id']).'">'.$t('View range').'</a>';

    $created_at = TimeFormatter::display($task['created_at'] ?? '');
    $rows .= '<tr data-task-row="'.$e($task['id']).'">'
        .'<td class="ipam-index">'.$row_number++.'</td>'
        .'<td><code>'.$e($task['id']).'</code></td>'
        .'<td>'.$e($task['range_name']).'</td>'
        .'<td><span class="task-badge status-'.$e($task['status']).'">'.$t(ucfirst($task['status'])).'</span></td>'
        .'<td><div class="task-progress"><i style="width:'.$percent.'%"></i></div><small data-progress-text>'.(int) $task['scanned'].' / '.(int) $task['total'].' · '.$percent.'%</small></td>'
        .'<td data-alive>'.(int) $task['alive'].'</td>'
        .'<td data-shards>'.(int) $task['completed_shards'].' / '.count($task['shards']).'</td>'
        .'<td>'.($created_at !== '' ? '<time datetime="'.$e($task['created_at']).'">'.$e($created_at).'</time>' : '<span class="ipam-muted">'.$t('Never').'</span>').'</td>'
        .'<td class="ipam-actions">'.$action.'</td></tr>';
}

$root_attributes = 'data-loading="'.$e($t('Loading...')).'" '
    .'data-request-failed="'.$e($t('Request failed.')).'" '
    .'data-label-pending="'.$e($t('Pending')).'" '
    .'data-label-running="'.$e($t('Running')).'" '
    .'data-label-completed="'.$e($t('Completed')).'" '
    .'data-label-failed="'.$e($t('Failed')).'" '
    .'data-label-stopped="'.$e($t('Stopped')).'"';

$pagination_filters = $data['filters'];
if ($data['selected'] !== '') {
    $pagination_filters['taskid'] = $data['selected'];
}
$pagination = ViewPagination::render('ip.scan', $data['pagination'], $pagination_filters);

$html = '<link rel="stylesheet" href="modules/zabbix_ipam/assets/css/ipam.css?v=1.5.0"><link rel="stylesheet" href="modules/zabbix_ipam/assets/css/ipam-responsive.css?v=1.5.0">'
    .'<div class="ipam" '.$root_attributes.'><header class="ipam-head"><div><h1>'.$t('Task Management').'</h1><p>'.$t('Track ICMP scan progress and review completed background tasks.').'</p></div>'
    .'<div class="ipam-head-actions"><a class="btn-alt" href="zabbix.php?action=ip.detail">'.$t('IP Details').'</a><a class="btn-alt" href="zabbix.php?action=ip.manager">'.$t('IP Management').'</a></div></header>'
    .'<div class="task-summary"><div><strong>'.$data['pagination']['total'].'</strong><span>'.$t('Tasks').'</span></div>'
    .'<div><strong>'.$counts['running'].'</strong><span>'.$t('Running').'</span></div><div><strong>'.$counts['completed'].'</strong><span>'.$t('Completed').'</span></div><div><strong>'.$counts['failed'].'</strong><span>'.$t('Failed').'</span></div></div>'
    .'<form class="ipam-toolbar js-auto-filter" method="get"><input type="hidden" name="action" value="ip.scan"><input type="hidden" name="per_page" value="'.$e($data['pagination']['per_page']).'">'
    .'<label><span>'.$t('Search').'</span><input name="search" value="'.$e($data['filters']['search']).'" placeholder="'.$t('Search task ID or range name').'"></label>'
    .'<label><span>'.$t('Status').'</span><select name="status">'.$status_options.'</select></label></form>'
    .'<div class="ipam-table-wrap"><table class="ipam-table ipam-task-table"><thead><tr><th>'.$t('No.').'</th><th>'.$t('Task ID').'</th><th>'.$t('IP range name').'</th><th>'.$t('Status').'</th><th>'.$t('Progress').'</th><th>'.$t('Alive').'</th><th>'.$t('Shards').'</th><th>'.$t('Created').'</th><th>'.$t('Actions').'</th></tr></thead>'
    .'<tbody>'.($rows ?: '<tr><td colspan="9" class="ipam-empty">'.$t('No scan tasks found.').'</td></tr>').'</tbody></table></div>'.$pagination.'</div>'
    .'<div class="ipam-toast" id="ipam-toast" hidden></div><script src="modules/zabbix_ipam/assets/js/ipam.js.php?v=1.5.0"></script>';

ViewRenderer::render($data['title'], $html);
