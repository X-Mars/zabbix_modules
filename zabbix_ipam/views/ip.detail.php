<?php

require_once dirname(__DIR__).'/lib/LanguageManager.php';
require_once dirname(__DIR__).'/lib/ViewRenderer.php';

use Modules\ZabbixIpam\Lib\LanguageManager;
use Modules\ZabbixIpam\Lib\ViewRenderer;

$t = [LanguageManager::class, 't'];
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$range_options = '<option value="">'.$t('All IP ranges').'</option>';
foreach ($data['ranges'] as $range) {
    $selected = $data['filters']['rangeid'] === $range['id'] ? ' selected' : '';
    $range_options .= '<option value="'.$e($range['id']).'"'.$selected.'>'.$e($range['name'].' · '.$range['range']).'</option>';
}

$status_options = '';
foreach (['all' => 'All IP statuses', 'alive' => 'Alive', 'down' => 'Unreachable', 'unscanned' => 'Not scanned'] as $value => $label) {
    $selected = $data['filters']['status'] === $value ? ' selected' : '';
    $status_options .= '<option value="'.$value.'"'.$selected.'>'.$t($label).'</option>';
}

$association_options = '';
foreach (['all' => 'All associations', 'yes' => 'Associated', 'no' => 'Unassociated'] as $value => $label) {
    $selected = $data['filters']['associated'] === $value ? ' selected' : '';
    $association_options .= '<option value="'.$value.'"'.$selected.'>'.$t($label).'</option>';
}

$rows = '';
foreach ($data['rows'] as $row) {
    $host_links = [];
    foreach ($row['hosts'] as $host) {
        $host_links[] = '<a class="ipam-host-link" href="'.$e($host['url']).'">'.$e($host['name']).'</a>';
    }

    $rows .= '<tr>'
        .'<td><strong class="ip-address">'.$e($row['ip']).'</strong></td>'
        .'<td>'.$e($row['range_name']).'</td>'
        .'<td><code>'.$e($row['range']).'</code></td>'
        .'<td><span class="ip-status ip-status-'.$e($row['state']).'">'.$t($row['state'] === 'alive' ? 'Alive' : ($row['state'] === 'down' ? 'Unreachable' : 'Not scanned')).'</span></td>'
        .'<td><span class="association-state '.($row['associated'] ? 'is-associated' : 'is-unassociated').'">'.$t($row['associated'] ? 'Associated' : 'Unassociated').'</span></td>'
        .'<td>'.($host_links ? implode('<br>', $host_links) : '<span class="ipam-muted">'.$t('No associated host').'</span>').'</td>'
        .'</tr>';
}

$filters = $data['filters'];
$page_url = static function (int $page) use ($filters): string {
    return 'zabbix.php?'.http_build_query([
        'action' => 'ip.detail',
        'search' => $filters['search'],
        'rangeid' => $filters['rangeid'],
        'status' => $filters['status'],
        'associated' => $filters['associated'],
        'page' => $page
    ]);
};

$pagination = '<div class="ipam-pagination"><span>'.sprintf($t('%1$d IPs in total'), $data['pagination']['total']).'</span><nav>';
if ($data['pagination']['page'] > 1) {
    $pagination .= '<a class="btn-alt" href="'.$e($page_url($data['pagination']['page'] - 1)).'">'.$t('Previous').'</a>';
}
$pagination .= '<span>'.sprintf($t('Page %1$d of %2$d'), $data['pagination']['page'], $data['pagination']['pages']).'</span>';
if ($data['pagination']['page'] < $data['pagination']['pages']) {
    $pagination .= '<a class="btn-alt" href="'.$e($page_url($data['pagination']['page'] + 1)).'">'.$t('Next').'</a>';
}
$pagination .= '</nav></div>';

$html = '<link rel="stylesheet" href="modules/zabbix_ipam/assets/css/ipam.css?v=1.2.0"><link rel="stylesheet" href="modules/zabbix_ipam/assets/css/ipam-responsive.css?v=1.2.0">'
    .'<div class="ipam"><header class="ipam-head"><div><h1>'.$t('IP Details').'</h1><p>'.$t('Browse every address, its latest ICMP status, and its matched Zabbix host.').'</p></div>'
    .'<div class="ipam-head-actions"><a class="btn-link" href="zabbix.php?action=ip.manager">'.$t('IP Management').'</a><a class="btn-link" href="zabbix.php?action=ip.scan">'.$t('Task Management').'</a></div></header>'
    .'<div class="ipam-help"><strong>'.$t('Association rules').'</strong><span>'.$t('A host is associated automatically when one of its Zabbix interfaces uses the same IPv4 address. Click the host name to open its Zabbix configuration page.').'</span></div>'
    .'<form class="ipam-toolbar ip-detail-toolbar" method="get"><input type="hidden" name="action" value="ip.detail">'
    .'<label><span>'.$t('Search').'</span><input name="search" value="'.$e($filters['search']).'" placeholder="'.$t('Search IP, range, or host name').'"></label>'
    .'<label><span>'.$t('IP range').'</span><select name="rangeid">'.$range_options.'</select></label>'
    .'<label><span>'.$t('IP status').'</span><select name="status">'.$status_options.'</select></label>'
    .'<label><span>'.$t('Host association').'</span><select name="associated">'.$association_options.'</select></label>'
    .'<button class="btn-alt">'.$t('Filter').'</button><a class="btn-link" href="zabbix.php?action=ip.detail">'.$t('Reset').'</a></form>'
    .'<div class="ipam-table-wrap"><table class="ipam-table ipam-detail-table"><thead><tr>'
    .'<th>'.$t('IP').'</th><th>'.$t('IP range name').'</th><th>'.$t('IP range').'</th><th>'.$t('IP status').'</th><th>'.$t('Host association').'</th><th>'.$t('Associated host').'</th>'
    .'</tr></thead><tbody>'.($rows ?: '<tr><td colspan="6" class="ipam-empty">'.$t('No IP addresses found.').'</td></tr>').'</tbody></table></div>'.$pagination.'</div>';

ViewRenderer::render($data['title'], $html);
