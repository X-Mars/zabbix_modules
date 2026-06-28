<?php

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/MibRepository.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';

use Modules\ZabbixSnmp\Lib\LanguageManager;
use Modules\ZabbixSnmp\Lib\MibRepository;
use Modules\ZabbixSnmp\Lib\ViewRenderer;

$pageTitle = $data['title'] ?? LanguageManager::t('Zabbix Walk');
$groupid = $data['groupid'] ?? '';
$hostid = $data['hostid'] ?? '';
$hostGroups = $data['host_groups'] ?? [];
$hosts = $data['hosts'] ?? [];
$hostConnection = $data['host_connection'] ?? [];
$walkOid = $data['walk_oid'] ?? '1.3.6.1.2.1';
$walkResult = $data['walk_result'] ?? null;
$templateGroups = $data['template_groups'] ?? [];

function walkShellQuote(string $value): string {
    return "'" . str_replace("'", "'\"'\"'", $value) . "'";
}

function buildWalkGetCommand(array $connection, string $oid): string {
    $oid = ltrim(trim($oid), '.');
    if ($oid === '' || $oid === '-') {
        return '# OID is empty';
    }

    $address = trim((string) ($connection['address'] ?? ''));
    if ($address === '') {
        return '# SNMP target is empty';
    }

    $port = trim((string) ($connection['port'] ?? '161'));
    $target = $address . ':' . ($port !== '' ? $port : '161');
    $version = strtolower(trim((string) ($connection['version'] ?? '2c')));

    if ($version === '1') {
        $community = (string) ($connection['community'] ?? 'public');
        return 'snmpget -v1 -c ' . walkShellQuote($community) . ' ' . walkShellQuote($target) . ' ' . walkShellQuote($oid);
    }

    if ($version === '3') {
        $securityName = (string) ($connection['securityname'] ?? '');
        $securityLevel = (string) ($connection['securitylevel'] ?? 'noAuthNoPriv');
        $authProtocol = strtoupper((string) ($connection['authprotocol'] ?? 'SHA'));
        $authPass = (string) ($connection['authpassphrase'] ?? '');
        $privProtocol = strtoupper((string) ($connection['privprotocol'] ?? 'AES'));
        $privPass = (string) ($connection['privpassphrase'] ?? '');

        $cmd = 'snmpget -v3 -u ' . walkShellQuote($securityName) . ' -l ' . walkShellQuote($securityLevel);
        if ($securityLevel === 'authNoPriv' || $securityLevel === 'authPriv') {
            $cmd .= ' -a ' . walkShellQuote($authProtocol) . ' -A ' . walkShellQuote($authPass);
        }
        if ($securityLevel === 'authPriv') {
            $cmd .= ' -x ' . walkShellQuote($privProtocol) . ' -X ' . walkShellQuote($privPass);
        }

        return $cmd . ' ' . walkShellQuote($target) . ' ' . walkShellQuote($oid);
    }

    $community = (string) ($connection['community'] ?? 'public');
    return 'snmpget -v2c -c ' . walkShellQuote($community) . ' ' . walkShellQuote($target) . ' ' . walkShellQuote($oid);
}

function getWalkCommandOid(array $entry): string {
    $numericOid = trim((string) ($entry['oid_numeric'] ?? ''));
    if ($numericOid !== '' && $numericOid !== '-') {
        return $numericOid;
    }

    return trim((string) ($entry['oid'] ?? ''));
}

$styleTag = new CTag('style', true, '
.snmp-walk-page {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.snmp-top {
    border: 1px solid #d9e3ec;
    border-radius: 10px;
    background: linear-gradient(135deg, #fbfcfe 0%, #f4f8fc 100%);
    padding: 16px;
}

.snmp-test-title {
    margin: 0;
    font-size: 15px;
    color: #243b53;
}

.snmp-form-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(240px, 1fr));
    gap: 10px;
}

.snmp-field {
    display: flex;
    align-items: center;
    gap: 8px;
}

.snmp-field-label {
    font-size: 12px;
    color: #627d98;
    white-space: nowrap;
}

.snmp-input,
.snmp-select,
.snmp-btn {
    height: 36px;
    border-radius: 8px;
    border: 1px solid #bcccdc;
    font-size: 13px;
}

.snmp-input,
.snmp-select {
    padding: 0 10px;
    width: 100%;
    background: #fff;
    color: #243b53;
}

.snmp-btn {
    padding: 0 14px;
    background: #1b6ec2;
    border-color: #1b6ec2;
    color: #fff;
    cursor: pointer;
}

.snmp-run-form {
    display: grid;
    grid-template-columns: minmax(260px, 1fr) auto;
    gap: 10px;
    align-items: center;
}

.snmp-profile {
    border: 1px solid #e5edf5;
    border-radius: 8px;
    background: #f9fbfd;
    padding: 10px 12px;
    display: flex;
    flex-wrap: wrap;
    gap: 10px 14px;
}

.snmp-profile-item {
    margin: 0;
    font-size: 12px;
    color: #486581;
}

.snmp-walk-result {
    border: 1px solid #d9e3ec;
    border-radius: 10px;
    background: #fff;
    overflow: hidden;
}

.snmp-result-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 14px;
    border-bottom: 1px solid #e5edf5;
    background: #f8fafc;
}

.snmp-result-title {
    margin: 0;
    font-size: 16px;
    color: #243b53;
}

.snmp-result-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 28px;
    padding: 0 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.snmp-success {
    background: #e8faf0;
    color: #127c56;
    border: 1px solid #b7ebc6;
}

.snmp-notice {
    background: #fff3f2;
    color: #9b1c1c;
    border: 1px solid #f3c7c6;
}

.snmp-result-head-right {
    display: flex;
    align-items: center;
    gap: 10px;
}

.snmp-result-rawbtn {
    height: 28px;
    line-height: 26px;
    padding: 0 12px;
    border-radius: 6px;
    border: 1px solid #1b6ec2;
    background: #fff;
    color: #1b6ec2;
    font-size: 12px;
    cursor: pointer;
    white-space: nowrap;
}

.snmp-walk-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
}

.snmp-row-btn,
.snmp-row-test {
    display: inline-block;
    height: 28px;
    line-height: 26px;
    padding: 0 10px;
    border-radius: 6px;
    text-decoration: none;
    white-space: nowrap;
    font-size: 12px;
    cursor: pointer;
}

.snmp-row-btn {
    border: 1px solid #1b6ec2;
    background: #fff;
    color: #1b6ec2;
}

.snmp-row-test {
    border: 1px solid #127c56;
    background: #fff;
    color: #127c56;
}

.snmp-create-popover {
    position: fixed;
    display: none;
    z-index: 2100;
    min-width: 240px;
    padding: 10px;
    border: 1px solid #d9e3ec;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.15);
}

.snmp-create-popover.open {
    display: block;
}

.snmp-create-popover-label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    color: #627d98;
}

.snmp-create-popover-input {
    width: 100%;
    height: 32px;
    padding: 0 8px;
    border: 1px solid #bcccdc;
    border-radius: 6px;
    font-size: 13px;
    color: #243b53;
    box-sizing: border-box;
}

.snmp-create-popover-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 8px;
}

.snmp-create-popover-btn {
    height: 28px;
    line-height: 26px;
    padding: 0 10px;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    white-space: nowrap;
}

.snmp-create-popover-confirm {
    border: 1px solid #127c56;
    background: #fff;
    color: #127c56;
}

.snmp-create-popover-cancel {
    border: 1px solid #bcccdc;
    background: #fff;
    color: #243b53;
}

.snmp-modal {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 2000;
}

.snmp-modal.open {
    display: flex;
}

.snmp-modal-card {
    width: min(1000px, 95vw);
    height: min(720px, 90vh);
    border-radius: 10px;
    background: #fff;
    border: 1px solid #d9e3ec;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.snmp-modal-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 12px 14px;
    border-bottom: 1px solid #e5edf5;
    background: #f8fafc;
}

.snmp-modal-title {
    margin: 0;
    font-size: 14px;
    color: #243b53;
}

.snmp-modal-close {
    border: 1px solid #bcccdc;
    background: #fff;
    color: #243b53;
    border-radius: 6px;
    height: 30px;
    padding: 0 10px;
    cursor: pointer;
}

.snmp-modal-pre {
    margin: 0;
    padding: 12px;
    flex: 1;
    overflow: auto;
    background: #10151b;
    color: #d9e2ec;
    font: 12px/1.45 Consolas, Monaco, monospace;
    white-space: pre;
}

.snmp-walk-table-wrap {
    border-top: 1px solid #e5edf5;
    max-height: min(68vh, 760px);
    overflow: auto;
}

.snmp-walk-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1020px;
}

.snmp-walk-table th,
.snmp-walk-table td {
    border-bottom: 1px solid #e5edf5;
    padding: 8px 10px;
    text-align: left;
    vertical-align: top;
    color: #243b53;
    font-size: 12px;
}

.snmp-walk-table thead th {
    background: #f8fafc;
    color: #334e68;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    position: sticky;
    top: 0;
    z-index: 2;
    box-shadow: 0 1px 0 #e5edf5;
}

.snmp-walk-table tbody tr:hover {
    background: #f8fbfe;
}

.snmp-col-select {
    width: 36px;
    text-align: center;
}

.snmp-walk-table td.snmp-col-select {
    text-align: center;
}

.snmp-walk-checkbox {
    width: 15px;
    height: 15px;
    cursor: pointer;
    margin: 0;
}

.snmp-walk-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 8px 14px;
    border-bottom: 1px solid #e5edf5;
    background: #fff;
}

.snmp-walk-selected-info {
    font-size: 12px;
    color: #486581;
}

.snmp-walk-toolbar-actions {
    display: flex;
    gap: 8px;
}

.snmp-walk-toolbar-btn {
    height: 30px;
    line-height: 28px;
    padding: 0 14px;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    white-space: nowrap;
}

.snmp-walk-toolbar-create {
    border: 1px solid #127c56;
    background: #127c56;
    color: #fff;
}

.snmp-walk-toolbar-create:disabled {
    border-color: #b7c2cc;
    background: #e4e9ee;
    color: #93a1ad;
    cursor: not-allowed;
}

.snmp-walk-toolbar-clear {
    border: 1px solid #bcccdc;
    background: #fff;
    color: #486581;
}

.snmp-template-card {
    height: auto;
    max-height: 90vh;
    width: min(560px, 95vw);
}

.snmp-template-body {
    padding: 14px;
    overflow: auto;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.snmp-template-label {
    font-size: 12px;
    color: #627d98;
    margin-top: 6px;
}

.snmp-template-input {
    width: 100%;
    height: 34px;
    padding: 0 10px;
    border: 1px solid #bcccdc;
    border-radius: 6px;
    font-size: 13px;
    color: #243b53;
    box-sizing: border-box;
}

.snmp-template-input.is-invalid {
    border-color: #d64545;
    background: #fff8f8;
}

.snmp-template-hint {
    font-size: 11px;
    color: #829ab1;
    line-height: 1.4;
    margin-bottom: 2px;
}

.snmp-template-error {
    display: none;
    font-size: 11px;
    color: #9b1c1c;
    line-height: 1.4;
    margin-bottom: 2px;
}

.snmp-template-error.visible {
    display: block;
}

.snmp-template-items-head {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 12px;
    font-size: 12px;
    color: #334e68;
    font-weight: 600;
}

.snmp-template-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    height: 20px;
    padding: 0 6px;
    border-radius: 999px;
    background: #e8faf0;
    color: #127c56;
    font-size: 11px;
}

.snmp-template-items {
    max-height: 280px;
    overflow: auto;
    border: 1px solid #e5edf5;
    border-radius: 8px;
    margin-top: 4px;
}

.snmp-template-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 8px 10px;
    border-bottom: 1px solid #f0f4f8;
    font-size: 12px;
}

.snmp-template-item:last-child {
    border-bottom: 0;
}

.snmp-template-item-name {
    color: #243b53;
    font-weight: 600;
}

.snmp-template-item-oid {
    color: #627d98;
    font-family: Consolas, Monaco, monospace;
    word-break: break-all;
}

.snmp-template-foot {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 12px 14px;
    border-top: 1px solid #e5edf5;
    background: #f8fafc;
}

.snmp-cell-primary {
    color: #243b53;
    word-break: break-all;
}

.snmp-cell-secondary {
    color: #627d98;
    font-size: 11px;
    word-break: break-all;
}

.snmp-cell-mono {
    font-family: Consolas, Monaco, monospace;
}

.snmp-walk-oid {
    min-width: 180px;
}

.snmp-walk-resolved-oid {
    min-width: 160px;
}

.snmp-col-no {
    width: 48px;
    white-space: nowrap;
    color: #627d98;
}

.snmp-walk-table td.snmp-walk-value {
    word-break: break-word;
    color: #127c56;
}

.snmp-walk-pager {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 14px;
    border: 1px solid #e5edf5;
    border-top: 0;
    border-radius: 0 0 10px 10px;
    background: #f8fafc;
    font-size: 12px;
    color: #486581;
}

.snmp-walk-pager-left {
    white-space: nowrap;
}

.snmp-walk-pager-right {
    display: flex;
    align-items: center;
    gap: 8px;
}

.snmp-walk-pager-btn,
.snmp-walk-pager-select {
    height: 30px;
    border: 1px solid #bcccdc;
    border-radius: 6px;
    background: #fff;
    color: #243b53;
    font-size: 12px;
}

.snmp-walk-pager-btn {
    padding: 0 12px;
    cursor: pointer;
}

.snmp-walk-pager-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.snmp-walk-pager-select {
    padding: 0 8px;
}

@media (max-width: 900px) {
    .snmp-form-row {
        grid-template-columns: 1fr;
    }

    .snmp-run-form {
        grid-template-columns: 1fr;
    }
}
');

$content = (new CDiv())->addClass('snmp-walk-page');

$top = (new CDiv())->addClass('snmp-top');

$top->addItem((new CTag('h2', true, LanguageManager::t('SNMP Connection Test')))->addClass('snmp-test-title'));

$filterForm = new CTag('form', true);
$filterForm->setAttribute('method', 'get');
$filterForm->setAttribute('action', 'zabbix.php');
$filterForm->addItem((new CTag('input'))->setAttribute('type', 'hidden')->setAttribute('name', 'action')->setAttribute('value', 'snmp.walk'));
$filterForm->addClass('snmp-form-row');

$groupField = (new CDiv())->addClass('snmp-field');
$groupField->addItem((new CSpan(LanguageManager::t('Host Group')))->addClass('snmp-field-label'));
$groupSelect = (new CTag('select', true))
    ->addClass('snmp-select')
    ->setAttribute('name', 'groupid')
    ->setAttribute('onchange', 'this.form.submit()');
foreach ($hostGroups as $group) {
    $option = (new CTag('option', true, htmlspecialchars((string) ($group['name'] ?? ''), ENT_QUOTES, 'UTF-8')))
        ->setAttribute('value', (string) ($group['groupid'] ?? ''));
    if ((string) ($group['groupid'] ?? '') === (string) $groupid) {
        $option->setAttribute('selected', 'selected');
    }
    $groupSelect->addItem($option);
}
$groupField->addItem($groupSelect);
$filterForm->addItem($groupField);

$hostField = (new CDiv())->addClass('snmp-field');
$hostField->addItem((new CSpan(LanguageManager::t('Host')))->addClass('snmp-field-label'));
$hostSelect = (new CTag('select', true))
    ->addClass('snmp-select')
    ->setAttribute('name', 'hostid')
    ->setAttribute('onchange', 'this.form.submit()');

if (empty($hosts)) {
    $hostSelect->addItem(
        (new CTag('option', true, LanguageManager::t('No SNMP hosts in selected group')))
            ->setAttribute('value', '')
            ->setAttribute('selected', 'selected')
    );
} else {
    foreach ($hosts as $host) {
        $option = (new CTag('option', true, htmlspecialchars((string) ($host['name'] ?? ''), ENT_QUOTES, 'UTF-8')))
            ->setAttribute('value', (string) ($host['hostid'] ?? ''));
        if ((string) ($host['hostid'] ?? '') === (string) $hostid) {
            $option->setAttribute('selected', 'selected');
        }
        $hostSelect->addItem($option);
    }
}
$hostField->addItem($hostSelect);
$filterForm->addItem($hostField);

$top->addItem($filterForm);

if (!empty($hostConnection)) {
    $profile = (new CDiv())->addClass('snmp-profile');
    $profile->addItem((new CDiv(LanguageManager::t('Host') . ': ' . ($hostConnection['host_name'] ?? '-')))->addClass('snmp-profile-item'));
    $profile->addItem((new CDiv(LanguageManager::t('Address') . ': ' . (($hostConnection['address'] ?? '-') . ':' . ($hostConnection['port'] ?? '161'))))->addClass('snmp-profile-item'));
    $profile->addItem((new CDiv(LanguageManager::t('Version') . ': ' . ($hostConnection['version'] ?? '-')))->addClass('snmp-profile-item'));

    if (($hostConnection['version'] ?? '') === '3') {
        $profile->addItem((new CDiv(LanguageManager::t('Security Name') . ': ' . ($hostConnection['securityname'] ?? '-')))->addClass('snmp-profile-item'));
    } else {
        $profile->addItem((new CDiv(LanguageManager::t('Community') . ': ' . ($hostConnection['community'] ?? '-')))->addClass('snmp-profile-item'));
    }

    $top->addItem($profile);
}

$runForm = new CTag('form', true);
$runForm->setAttribute('method', 'get');
$runForm->setAttribute('action', 'zabbix.php');
$runForm->addClass('snmp-run-form');
$runForm->addItem((new CTag('input'))->setAttribute('type', 'hidden')->setAttribute('name', 'action')->setAttribute('value', 'snmp.walk'));
$runForm->addItem((new CTag('input'))->setAttribute('type', 'hidden')->setAttribute('name', 'groupid')->setAttribute('value', (string) $groupid));
$runForm->addItem((new CTag('input'))->setAttribute('type', 'hidden')->setAttribute('name', 'hostid')->setAttribute('value', (string) $hostid));
$runForm->addItem((new CTag('input'))->setAttribute('type', 'hidden')->setAttribute('name', 'run')->setAttribute('value', '1'));

$oidInput = (new CTag('input'))
    ->addClass('snmp-input')
    ->setAttribute('type', 'text')
    ->setAttribute('name', 'walk_oid')
    ->setAttribute('value', (string) $walkOid)
    ->setAttribute('placeholder', LanguageManager::t('Enter OID to walk, e.g. 1.3.6.1.2.1'));
$runForm->addItem($oidInput);
$runForm->addItem((new CTag('button', true, LanguageManager::t('Run')))->addClass('snmp-btn')->setAttribute('type', 'submit'));

$top->addItem((new CSpan(LanguageManager::t('Walk OID')))->addClass('snmp-field-label'));
$top->addItem($runForm);

$content->addItem($top);

if ($walkResult !== null) {
    $isOk = !empty($walkResult['ok']);
    $lines = is_array($walkResult['lines'] ?? null) ? $walkResult['lines'] : [];
    $entries = is_array($walkResult['entries'] ?? null) ? $walkResult['entries'] : [];
    if ($isOk && empty($entries) && !empty($lines)) {
        $entries = MibRepository::parseWalkLines($lines);
    }

    $rawData = (string) ($walkResult['raw'] ?? implode("\n", $lines));

    $resultBlock = (new CDiv())->addClass('snmp-walk-result');

    $head = (new CDiv())->addClass('snmp-result-head');
    $head->addItem((new CTag('h2', true, LanguageManager::t('SNMP Walk Results')))->addClass('snmp-result-title'));

    $headRight = (new CDiv())->addClass('snmp-result-head-right');
    $headRight->addItem(
        (new CDiv($isOk ? LanguageManager::t('Success') : LanguageManager::t('Failed')))
            ->addClass('snmp-result-badge')
            ->addClass($isOk ? 'snmp-success' : 'snmp-notice')
    );
    if ($rawData !== '') {
        $headRight->addItem(
            (new CTag('button', true, LanguageManager::t('View Raw Data')))
                ->addClass('snmp-result-rawbtn js-walk-view-raw')
                ->setAttribute('type', 'button')
        );
    }
    $head->addItem($headRight);
    $resultBlock->addItem($head);

    if ($isOk) {
        $walkTableRows = [];
        foreach ($entries as $index => $entry) {
            $commandOid = getWalkCommandOid($entry);
            $walkTableRows[] = [
                'no' => $index + 1,
                'oid' => (string) ($entry['oid'] ?? '-'),
                'oid_numeric' => (string) ($entry['oid_numeric'] ?? '-'),
                'mib_file' => (string) ($entry['mib_file'] ?? '-'),
                'module' => (string) ($entry['module'] ?? '-'),
                'data_type' => (string) ($entry['data_type'] ?? '-'),
                'value' => (string) ($entry['value'] ?? '-'),
                'command' => buildWalkGetCommand($hostConnection, $commandOid),
                'command_oid' => $commandOid,
                'label_oid' => (string) ($entry['oid'] ?? '-')
            ];
        }

        if (!empty($walkTableRows)) {
            $toolbar = (new CDiv())->addClass('snmp-walk-toolbar');
            $toolbar->addItem(
                (new CSpan(''))->addClass('snmp-walk-selected-info')->setAttribute('id', 'snmp-walk-selected-info')
            );
            $toolbarActions = (new CDiv())->addClass('snmp-walk-toolbar-actions');
            $toolbarActions->addItem(
                (new CTag('button', true, LanguageManager::t('Clear selection')))
                    ->addClass('snmp-walk-toolbar-btn snmp-walk-toolbar-clear')
                    ->setAttribute('type', 'button')
                    ->setAttribute('id', 'snmp-walk-clear-selection')
            );
            $toolbarActions->addItem(
                (new CTag('button', true, LanguageManager::t('Create Template')))
                    ->addClass('snmp-walk-toolbar-btn snmp-walk-toolbar-create')
                    ->setAttribute('type', 'button')
                    ->setAttribute('id', 'snmp-create-template-btn')
                    ->setAttribute('disabled', 'disabled')
            );
            $toolbar->addItem($toolbarActions);
            $resultBlock->addItem($toolbar);
        }

        $tableWrap = (new CDiv())->addClass('snmp-walk-table-wrap');
        $table = new CTag('table', true);
        $table->addClass('snmp-walk-table');

        $thead = new CTag('thead', true);
        $headerRow = new CTag('tr', true);
        $selectTh = (new CTag('th', true))->addClass('snmp-col-select');
        $selectTh->addItem(
            (new CTag('input'))
                ->addClass('snmp-walk-checkbox')
                ->setAttribute('type', 'checkbox')
                ->setAttribute('id', 'snmp-walk-select-all')
                ->setAttribute('title', LanguageManager::t('Select all'))
        );
        $headerRow->addItem($selectTh);
        foreach ([
            LanguageManager::t('No.'),
            LanguageManager::t('OID'),
            LanguageManager::t('Resolved OID'),
            LanguageManager::t('MIB File'),
            LanguageManager::t('Module'),
            LanguageManager::t('Data Type'),
            LanguageManager::t('Value'),
            LanguageManager::t('Action')
        ] as $header) {
            $headerRow->addItem(new CTag('th', true, $header));
        }
        $thead->addItem($headerRow);
        $table->addItem($thead);

        $tbody = (new CTag('tbody', true))->setAttribute('id', 'snmp-walk-tbody');
        if (empty($walkTableRows)) {
            $row = new CTag('tr', true);
            $emptyCell = new CTag('td', true, LanguageManager::t('No walk results'));
            $emptyCell->setAttribute('colspan', '9');
            $row->addItem($emptyCell);
            $tbody->addItem($row);
        }
        $table->addItem($tbody);
        $tableWrap->addItem($table);
        $resultBlock->addItem($tableWrap);

        if (!empty($walkTableRows)) {
            $pager = (new CDiv())->addClass('snmp-walk-pager');
            $pager->addItem((new CDiv())->addClass('snmp-walk-pager-left')->setAttribute('id', 'snmp-walk-pager-info'));
            $pagerRight = (new CDiv())->addClass('snmp-walk-pager-right');
            $pagerRight->addItem(
                (new CTag('button', true, LanguageManager::t('Previous')))
                    ->addClass('snmp-walk-pager-btn')
                    ->setAttribute('type', 'button')
                    ->setAttribute('id', 'snmp-walk-prev')
            );
            $pagerRight->addItem(
                (new CTag('button', true, LanguageManager::t('Next')))
                    ->addClass('snmp-walk-pager-btn')
                    ->setAttribute('type', 'button')
                    ->setAttribute('id', 'snmp-walk-next')
            );
            $pageSizeSelect = (new CTag('select', true))->addClass('snmp-walk-pager-select')->setAttribute('id', 'snmp-walk-page-size');
            foreach ([50, 100, 200] as $pageSizeOption) {
                $option = (new CTag('option', true, (string) $pageSizeOption))
                    ->setAttribute('value', (string) $pageSizeOption);
                if ($pageSizeOption === 100) {
                    $option->setAttribute('selected', 'selected');
                }
                $pageSizeSelect->addItem($option);
            }
            $pagerRight->addItem((new CSpan(LanguageManager::t('Per page')))->addClass('snmp-field-label'));
            $pagerRight->addItem($pageSizeSelect);
            $pager->addItem($pagerRight);
            $resultBlock->addItem($pager);
        }

        if (!empty($walkTableRows)) {
            $resultBlock->addItem(
                (new CTag('script', true, json_encode($walkTableRows, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)))
                    ->setAttribute('type', 'application/json')
                    ->setAttribute('id', 'snmp-walk-rows-data')
            );
        }

        if ($rawData !== '') {
            $resultBlock->addItem(
                (new CTag('script', true, json_encode($rawData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)))
                    ->setAttribute('type', 'application/json')
                    ->setAttribute('id', 'snmp-walk-raw-data')
            );
        }
    }

    $content->addItem($resultBlock);
}

$rawModal = (new CDiv())->addClass('snmp-modal')->setAttribute('id', 'snmp-walk-raw-modal');
$rawModalCard = (new CDiv())->addClass('snmp-modal-card');
$rawModalHead = (new CDiv())->addClass('snmp-modal-head');
$rawModalHead->addItem((new CTag('h3', true, LanguageManager::t('Raw Data')))->addClass('snmp-modal-title'));
$rawModalHead->addItem((new CTag('button', true, LanguageManager::t('Close')))->addClass('snmp-modal-close')->setAttribute('type', 'button')->setAttribute('id', 'snmp-walk-raw-close'));
$rawModalCard->addItem($rawModalHead);
$rawModalCard->addItem((new CTag('pre', true, ''))->addClass('snmp-modal-pre')->setAttribute('id', 'snmp-walk-raw-content'));
$rawModal->addItem($rawModalCard);
$content->addItem($rawModal);

$createPopover = (new CDiv())->addClass('snmp-create-popover')->setAttribute('id', 'snmp-create-popover');
$createPopover->addItem((new CTag('label', true, LanguageManager::t('Item Name')))->addClass('snmp-create-popover-label')->setAttribute('for', 'snmp-create-name-input'));
$createPopover->addItem(
    (new CTag('input'))
        ->addClass('snmp-create-popover-input')
        ->setAttribute('type', 'text')
        ->setAttribute('id', 'snmp-create-name-input')
);
$popoverActions = (new CDiv())->addClass('snmp-create-popover-actions');
$popoverActions->addItem((new CTag('button', true, LanguageManager::t('Cancel')))->addClass('snmp-create-popover-btn snmp-create-popover-cancel')->setAttribute('type', 'button')->setAttribute('id', 'snmp-create-cancel'));
$popoverActions->addItem((new CTag('button', true, LanguageManager::t('Confirm')))->addClass('snmp-create-popover-btn snmp-create-popover-confirm')->setAttribute('type', 'button')->setAttribute('id', 'snmp-create-confirm'));
$createPopover->addItem($popoverActions);
$content->addItem($createPopover);

$tplModal = (new CDiv())->addClass('snmp-modal')->setAttribute('id', 'snmp-template-modal');
$tplCard = (new CDiv())->addClass('snmp-modal-card snmp-template-card');

$tplHead = (new CDiv())->addClass('snmp-modal-head');
$tplHead->addItem((new CTag('h3', true, LanguageManager::t('Create Template')))->addClass('snmp-modal-title'));
$tplHead->addItem((new CTag('button', true, LanguageManager::t('Close')))->addClass('snmp-modal-close')->setAttribute('type', 'button')->setAttribute('id', 'snmp-template-close'));
$tplCard->addItem($tplHead);

$tplBody = (new CDiv())->addClass('snmp-template-body');

$tplBody->addItem((new CTag('label', true, LanguageManager::t('Template Name')))->addClass('snmp-template-label')->setAttribute('for', 'snmp-template-name'));
$tplBody->addItem(
    (new CTag('input'))
        ->addClass('snmp-template-input')
        ->setAttribute('type', 'text')
        ->setAttribute('id', 'snmp-template-name')
        ->setAttribute('placeholder', LanguageManager::t('Enter template name'))
        ->setAttribute('pattern', '[a-zA-Z0-9._-]+')
        ->setAttribute('autocomplete', 'off')
);
$tplBody->addItem((new CDiv(LanguageManager::t('Template name hint')))->addClass('snmp-template-hint'));
$tplBody->addItem((new CDiv(''))->addClass('snmp-template-error')->setAttribute('id', 'snmp-template-name-error'));

$tplBody->addItem((new CTag('label', true, LanguageManager::t('Template Group')))->addClass('snmp-template-label')->setAttribute('for', 'snmp-template-group'));
$tplBody->addItem(
    (new CTag('input'))
        ->addClass('snmp-template-input')
        ->setAttribute('type', 'text')
        ->setAttribute('id', 'snmp-template-group')
        ->setAttribute('list', 'snmp-template-group-list')
        ->setAttribute('placeholder', LanguageManager::t('Enter template group'))
);
$tplDatalist = (new CTag('datalist', true))->setAttribute('id', 'snmp-template-group-list');
foreach ($templateGroups as $tplGroup) {
    $tplGroupName = (string) ($tplGroup['name'] ?? '');
    if ($tplGroupName === '') {
        continue;
    }
    $tplDatalist->addItem((new CTag('option', true))->setAttribute('value', $tplGroupName));
}
$tplBody->addItem($tplDatalist);

$tplItemsHead = (new CDiv())->addClass('snmp-template-items-head');
$tplItemsHead->addItem(new CSpan(LanguageManager::t('Selected items')));
$tplItemsHead->addItem((new CSpan('0'))->addClass('snmp-template-count')->setAttribute('id', 'snmp-template-count'));
$tplBody->addItem($tplItemsHead);
$tplBody->addItem((new CDiv())->addClass('snmp-template-items')->setAttribute('id', 'snmp-template-items'));

$tplCard->addItem($tplBody);

$tplFoot = (new CDiv())->addClass('snmp-template-foot');
$tplFoot->addItem((new CTag('button', true, LanguageManager::t('Cancel')))->addClass('snmp-create-popover-btn snmp-create-popover-cancel')->setAttribute('type', 'button')->setAttribute('id', 'snmp-template-cancel'));
$tplFoot->addItem((new CTag('button', true, LanguageManager::t('Confirm')))->addClass('snmp-create-popover-btn snmp-create-popover-confirm')->setAttribute('type', 'button')->setAttribute('id', 'snmp-template-confirm'));
$tplCard->addItem($tplFoot);

$tplModal->addItem($tplCard);
$content->addItem($tplModal);

$content->addItem(new CJsScript('<script>
(function() {
    "use strict";

    var rawModal = document.getElementById("snmp-walk-raw-modal");
    var rawContent = document.getElementById("snmp-walk-raw-content");
    var rawClose = document.getElementById("snmp-walk-raw-close");
    var copiedLabel = ' . json_encode(LanguageManager::t('Copied')) . ';
    var currentHostid = ' . json_encode((string) $hostid) . ';
    var createPopover = document.getElementById("snmp-create-popover");
    var createNameInput = document.getElementById("snmp-create-name-input");
    var createConfirmBtn = document.getElementById("snmp-create-confirm");
    var createCancelBtn = document.getElementById("snmp-create-cancel");
    var creatingLabel = ' . json_encode(LanguageManager::t('Creating...')) . ';
    var createdLabel = ' . json_encode(LanguageManager::t('Created')) . ';
    var selectHostLabel = ' . json_encode(LanguageManager::t('Please select a host first.')) . ';
    var pendingCreateBtn = null;
    var walkRowsDataEl = document.getElementById("snmp-walk-rows-data");
    var walkRawDataEl = document.getElementById("snmp-walk-raw-data");
    var walkTbody = document.getElementById("snmp-walk-tbody");
    var walkPagerInfo = document.getElementById("snmp-walk-pager-info");
    var walkPrevBtn = document.getElementById("snmp-walk-prev");
    var walkNextBtn = document.getElementById("snmp-walk-next");
    var walkPageSizeSelect = document.getElementById("snmp-walk-page-size");
    var walkSelectAll = document.getElementById("snmp-walk-select-all");
    var walkSelectedInfo = document.getElementById("snmp-walk-selected-info");
    var walkClearBtn = document.getElementById("snmp-walk-clear-selection");
    var createTemplateBtn = document.getElementById("snmp-create-template-btn");
    var tplModal = document.getElementById("snmp-template-modal");
    var tplCloseBtn = document.getElementById("snmp-template-close");
    var tplCancelBtn = document.getElementById("snmp-template-cancel");
    var tplConfirmBtn = document.getElementById("snmp-template-confirm");
    var tplNameInput = document.getElementById("snmp-template-name");
    var tplNameError = document.getElementById("snmp-template-name-error");
    var tplGroupInput = document.getElementById("snmp-template-group");
    var tplItemsEl = document.getElementById("snmp-template-items");
    var tplCountEl = document.getElementById("snmp-template-count");
    var walkRows = [];
    var walkCurrentPage = 1;
    var walkPageSize = 100;
    var selectedRows = {};
    var selectedCount = 0;
    var walkLabels = {
        copyCommand: ' . json_encode(LanguageManager::t('Copy Command')) . ',
        copyOid: ' . json_encode(LanguageManager::t('Copy OID')) . ',
        createItem: ' . json_encode(LanguageManager::t('Create Item')) . ',
        showing: ' . json_encode(LanguageManager::t('Showing %d-%d of %d')) . ',
        selected: ' . json_encode(LanguageManager::t('Selected %d items')) . ',
        noSelection: ' . json_encode(LanguageManager::t('No items selected.')) . ',
        enterName: ' . json_encode(LanguageManager::t('Please enter a template name.')) . ',
        enterGroup: ' . json_encode(LanguageManager::t('Please enter a template group.')) . ',
        creatingTpl: ' . json_encode(LanguageManager::t('Creating template...')) . ',
        createTpl: ' . json_encode(LanguageManager::t('Create Template')) . ',
        invalidName: ' . json_encode(LanguageManager::t('Invalid template name.')) . '
    };

    var templateNamePattern = /^[a-zA-Z0-9._-]+$/;

    function isValidTemplateName(name) {
        return name !== "" && templateNamePattern.test(name);
    }

    function setTemplateNameError(message) {
        if (!tplNameInput || !tplNameError) {
            return;
        }
        if (message) {
            tplNameInput.classList.add("is-invalid");
            tplNameError.textContent = message;
            tplNameError.classList.add("visible");
        } else {
            tplNameInput.classList.remove("is-invalid");
            tplNameError.textContent = "";
            tplNameError.classList.remove("visible");
        }
    }

    function validateTemplateName(showError) {
        if (!tplNameInput) {
            return false;
        }
        var name = tplNameInput.value.trim();
        if (name === "") {
            if (showError) {
                setTemplateNameError(walkLabels.enterName);
            }
            return false;
        }
        if (!isValidTemplateName(name)) {
            if (showError) {
                setTemplateNameError(walkLabels.invalidName);
            }
            return false;
        }
        setTemplateNameError("");
        return true;
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
    }

    function formatShowing(from, to, total) {
        return walkLabels.showing
            .replace("%d", String(from))
            .replace("%d", String(to))
            .replace("%d", String(total));
    }

    function renderWalkPage() {
        if (!walkTbody || walkRows.length === 0) {
            return;
        }

        var total = walkRows.length;
        var totalPages = Math.max(1, Math.ceil(total / walkPageSize));
        if (walkCurrentPage > totalPages) {
            walkCurrentPage = totalPages;
        }
        if (walkCurrentPage < 1) {
            walkCurrentPage = 1;
        }

        var start = (walkCurrentPage - 1) * walkPageSize;
        var end = Math.min(start + walkPageSize, total);
        var html = [];

        for (var i = start; i < end; i++) {
            var row = walkRows[i];
            html.push("<tr>");
            html.push("<td class=\"snmp-col-select\"><input type=\"checkbox\" class=\"snmp-walk-checkbox js-walk-row-check\" data-index=\"" + i + "\"" + (selectedRows[i] ? " checked" : "") + "></td>");
            html.push("<td class=\"snmp-col-no\">" + escapeHtml(row.no) + "</td>");
            html.push("<td class=\"snmp-walk-oid snmp-cell-primary\">" + escapeHtml(row.oid) + "</td>");
            html.push("<td class=\"snmp-walk-resolved-oid snmp-cell-secondary snmp-cell-mono\">" + escapeHtml(row.oid_numeric) + "</td>");
            html.push("<td>" + escapeHtml(row.mib_file) + "</td>");
            html.push("<td>" + escapeHtml(row.module) + "</td>");
            html.push("<td>" + escapeHtml(row.data_type) + "</td>");
            html.push("<td class=\"snmp-walk-value\">" + escapeHtml(row.value) + "</td>");
            html.push("<td><div class=\"snmp-walk-actions\">");
            html.push("<button type=\"button\" class=\"snmp-row-btn js-walk-copy\" data-copy=\"" + escapeHtml(row.command) + "\">" + escapeHtml(walkLabels.copyCommand) + "</button>");
            html.push("<button type=\"button\" class=\"snmp-row-btn js-walk-copy\" data-copy=\"" + escapeHtml(row.command_oid) + "\">" + escapeHtml(walkLabels.copyOid) + "</button>");
            html.push("<button type=\"button\" class=\"snmp-row-test js-walk-create-item\" data-oid=\"" + escapeHtml(row.command_oid) + "\" data-label-oid=\"" + escapeHtml(row.label_oid) + "\" data-type=\"" + escapeHtml(row.data_type) + "\" data-value=\"" + escapeHtml(row.value) + "\">" + escapeHtml(walkLabels.createItem) + "</button>");
            html.push("</div></td>");
            html.push("</tr>");
        }

        walkTbody.innerHTML = html.join("");

        if (walkPagerInfo) {
            walkPagerInfo.textContent = total > 0
                ? formatShowing(start + 1, end, total)
                : "";
        }
        if (walkPrevBtn) {
            walkPrevBtn.disabled = walkCurrentPage <= 1;
        }
        if (walkNextBtn) {
            walkNextBtn.disabled = walkCurrentPage >= totalPages;
        }

        syncSelectAllState();
    }

    function syncSelectAllState() {
        if (!walkSelectAll) {
            return;
        }

        var total = walkRows.length;
        var start = (walkCurrentPage - 1) * walkPageSize;
        var end = Math.min(start + walkPageSize, total);
        var pageHasRows = end > start;
        var allChecked = pageHasRows;

        for (var i = start; i < end; i++) {
            if (!selectedRows[i]) {
                allChecked = false;
                break;
            }
        }

        walkSelectAll.checked = allChecked;
    }

    function updateSelectionUi() {
        if (walkSelectedInfo) {
            walkSelectedInfo.textContent = walkLabels.selected.replace("%d", String(selectedCount));
        }
        if (createTemplateBtn) {
            createTemplateBtn.disabled = selectedCount === 0;
        }
    }

    function setRowSelected(index, checked) {
        if (checked) {
            if (!selectedRows[index]) {
                selectedRows[index] = true;
                selectedCount += 1;
            }
        } else if (selectedRows[index]) {
            delete selectedRows[index];
            selectedCount -= 1;
        }
    }

    function clearSelection() {
        selectedRows = {};
        selectedCount = 0;
        renderWalkPage();
        updateSelectionUi();
    }

    function initWalkTable() {
        if (!walkRowsDataEl) {
            return;
        }

        try {
            walkRows = JSON.parse(walkRowsDataEl.textContent || "[]");
        } catch (err) {
            walkRows = [];
        }

        if (walkPageSizeSelect) {
            walkPageSize = parseInt(walkPageSizeSelect.value, 10) || 100;
        }

        requestAnimationFrame(renderWalkPage);
    }

    function openRawModal(text) {
        if (!rawModal || !rawContent) {
            return;
        }
        rawContent.textContent = text || "";
        rawModal.classList.add("open");
    }

    function closeRawModal() {
        if (!rawModal) {
            return;
        }
        rawModal.classList.remove("open");
    }

    function copyText(text, btn) {
        if (!text) {
            return;
        }

        var originalLabel = btn.textContent;
        var finishCopy = function() {
            btn.textContent = copiedLabel;
            setTimeout(function() {
                btn.textContent = originalLabel;
            }, 1000);
        };

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(finishCopy).catch(function() {
                fallbackCopy(text);
                finishCopy();
            });
        } else {
            fallbackCopy(text);
            finishCopy();
        }
    }

    function fallbackCopy(text) {
        var ta = document.createElement("textarea");
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand("copy");
        document.body.removeChild(ta);
    }

    document.addEventListener("click", function(e) {
        var rawBtn = e.target.closest(".js-walk-view-raw");
        if (rawBtn) {
            e.preventDefault();
            var rawText = "";
            if (walkRawDataEl) {
                try {
                    rawText = JSON.parse(walkRawDataEl.textContent || "\"\"");
                } catch (err) {
                    rawText = walkRawDataEl.textContent || "";
                }
            }
            openRawModal(rawText);
            return;
        }

        var copyBtn = e.target.closest(".js-walk-copy");
        if (copyBtn) {
            e.preventDefault();
            copyText(copyBtn.getAttribute("data-copy") || "", copyBtn);
            return;
        }

        var createBtn = e.target.closest(".js-walk-create-item");
        if (createBtn) {
            e.preventDefault();
            openCreatePopover(createBtn);
            return;
        }

        if (createPopover && !e.target.closest("#snmp-create-popover") && !e.target.closest(".js-walk-create-item")) {
            closeCreatePopover();
        }

        if (rawModal && e.target === rawModal) {
            closeRawModal();
        }
    });

    function openCreatePopover(btn) {
        if (!currentHostid) {
            alert(selectHostLabel);
            return;
        }

        if (!createPopover || !createNameInput) {
            return;
        }

        var oid = btn.getAttribute("data-oid") || "";
        var labelOid = btn.getAttribute("data-label-oid") || oid;
        if (!oid) {
            return;
        }

        pendingCreateBtn = btn;
        createNameInput.value = "SNMP " + labelOid;
        positionCreatePopover(btn);
        createPopover.classList.add("open");
        createNameInput.focus();
        createNameInput.select();
    }

    function positionCreatePopover(btn) {
        if (!createPopover) {
            return;
        }

        var margin = 8;
        createPopover.style.visibility = "hidden";
        createPopover.style.display = "block";

        var rect = btn.getBoundingClientRect();
        var popRect = createPopover.getBoundingClientRect();

        var left = rect.left;
        if (left + popRect.width > window.innerWidth - margin) {
            left = window.innerWidth - popRect.width - margin;
        }
        if (left < margin) {
            left = margin;
        }

        var top = rect.top - popRect.height - margin;
        if (top < margin) {
            top = rect.bottom + margin;
        }
        if (top + popRect.height > window.innerHeight - margin) {
            top = window.innerHeight - popRect.height - margin;
        }

        createPopover.style.left = left + "px";
        createPopover.style.top = top + "px";
        createPopover.style.visibility = "visible";
    }

    function closeCreatePopover() {
        if (!createPopover) {
            return;
        }
        createPopover.classList.remove("open");
        createPopover.style.display = "";
        pendingCreateBtn = null;
    }

    function createItem(btn, itemName) {
        if (!currentHostid) {
            alert(selectHostLabel);
            return;
        }

        if (btn.disabled) {
            return;
        }

        var oid = btn.getAttribute("data-oid") || "";
        var value = btn.getAttribute("data-value") || "";
        var dataType = btn.getAttribute("data-type") || "";
        if (!oid) {
            return;
        }

        closeCreatePopover();

        btn.disabled = true;
        var originalLabel = btn.textContent;
        btn.textContent = creatingLabel;

        var params = new URLSearchParams();
        params.append("hostid", currentHostid);
        params.append("oid", oid);
        params.append("name", itemName);
        params.append("value", value);
        params.append("data_type", dataType);

        fetch("zabbix.php?action=snmp.item.create", {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: params.toString()
        })
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                if (data && data.ok) {
                    btn.textContent = createdLabel;
                    setTimeout(function() {
                        btn.textContent = originalLabel;
                        btn.disabled = false;
                    }, 1500);
                } else {
                    alert((data && data.message) ? data.message : "Error");
                    btn.textContent = originalLabel;
                    btn.disabled = false;
                }
            })
            .catch(function(err) {
                alert(String(err));
                btn.textContent = originalLabel;
                btn.disabled = false;
            });
    }

    if (rawClose) {
        rawClose.addEventListener("click", closeRawModal);
    }

    if (createCancelBtn) {
        createCancelBtn.addEventListener("click", function(e) {
            e.preventDefault();
            closeCreatePopover();
        });
    }

    if (createConfirmBtn) {
        createConfirmBtn.addEventListener("click", function(e) {
            e.preventDefault();
            if (!pendingCreateBtn || !createNameInput) {
                return;
            }
            var itemName = createNameInput.value.trim();
            if (itemName === "") {
                var labelOid = pendingCreateBtn.getAttribute("data-label-oid")
                    || pendingCreateBtn.getAttribute("data-oid")
                    || "";
                itemName = "SNMP " + labelOid;
            }
            createItem(pendingCreateBtn, itemName);
        });
    }

    if (createNameInput) {
        createNameInput.addEventListener("keydown", function(e) {
            if (e.key === "Enter" && createConfirmBtn) {
                e.preventDefault();
                createConfirmBtn.click();
            }
            if (e.key === "Escape") {
                e.preventDefault();
                closeCreatePopover();
            }
        });
    }

    if (walkPrevBtn) {
        walkPrevBtn.addEventListener("click", function(e) {
            e.preventDefault();
            if (walkCurrentPage > 1) {
                walkCurrentPage -= 1;
                renderWalkPage();
            }
        });
    }

    if (walkNextBtn) {
        walkNextBtn.addEventListener("click", function(e) {
            e.preventDefault();
            var totalPages = Math.max(1, Math.ceil(walkRows.length / walkPageSize));
            if (walkCurrentPage < totalPages) {
                walkCurrentPage += 1;
                renderWalkPage();
            }
        });
    }

    if (walkPageSizeSelect) {
        walkPageSizeSelect.addEventListener("change", function() {
            walkPageSize = parseInt(walkPageSizeSelect.value, 10) || 100;
            walkCurrentPage = 1;
            renderWalkPage();
        });
    }

    if (walkTbody) {
        walkTbody.addEventListener("change", function(e) {
            var cb = e.target.closest(".js-walk-row-check");
            if (!cb) {
                return;
            }
            var idx = parseInt(cb.getAttribute("data-index"), 10);
            if (isNaN(idx)) {
                return;
            }
            setRowSelected(idx, cb.checked);
            syncSelectAllState();
            updateSelectionUi();
        });
    }

    if (walkSelectAll) {
        walkSelectAll.addEventListener("change", function() {
            var total = walkRows.length;
            var start = (walkCurrentPage - 1) * walkPageSize;
            var end = Math.min(start + walkPageSize, total);
            for (var i = start; i < end; i++) {
                setRowSelected(i, walkSelectAll.checked);
            }
            renderWalkPage();
            updateSelectionUi();
        });
    }

    if (walkClearBtn) {
        walkClearBtn.addEventListener("click", function(e) {
            e.preventDefault();
            clearSelection();
        });
    }

    if (createTemplateBtn) {
        createTemplateBtn.addEventListener("click", function(e) {
            e.preventDefault();
            openTemplateModal();
        });
    }

    function getSelectedItems() {
        var items = [];
        for (var key in selectedRows) {
            if (!selectedRows.hasOwnProperty(key)) {
                continue;
            }
            var row = walkRows[parseInt(key, 10)];
            if (!row) {
                continue;
            }
            items.push({
                oid: row.command_oid,
                name: "SNMP " + row.label_oid,
                value: row.value,
                data_type: row.data_type
            });
        }
        return items;
    }

    function openTemplateModal() {
        if (selectedCount === 0) {
            alert(walkLabels.noSelection);
            return;
        }
        if (!tplModal) {
            return;
        }

        var items = getSelectedItems();
        if (tplCountEl) {
            tplCountEl.textContent = String(items.length);
        }
        if (tplItemsEl) {
            var html = [];
            for (var i = 0; i < items.length; i++) {
                html.push("<div class=\"snmp-template-item\">");
                html.push("<span class=\"snmp-template-item-name\">" + escapeHtml(items[i].name) + "</span>");
                html.push("<span class=\"snmp-template-item-oid\">" + escapeHtml(items[i].oid) + "</span>");
                html.push("</div>");
            }
            tplItemsEl.innerHTML = html.join("");
        }
        setTemplateNameError("");
        tplModal.classList.add("open");
        if (tplNameInput) {
            tplNameInput.focus();
        }
    }

    function closeTemplateModal() {
        if (tplModal) {
            tplModal.classList.remove("open");
        }
        setTemplateNameError("");
    }

    function submitTemplate() {
        var name = tplNameInput ? tplNameInput.value.trim() : "";
        var group = tplGroupInput ? tplGroupInput.value.trim() : "";

        if (!validateTemplateName(true)) {
            if (tplNameInput) {
                tplNameInput.focus();
            }
            return;
        }
        if (group === "") {
            alert(walkLabels.enterGroup);
            return;
        }

        var items = getSelectedItems();
        if (items.length === 0) {
            alert(walkLabels.noSelection);
            return;
        }

        if (!tplConfirmBtn) {
            return;
        }

        tplConfirmBtn.disabled = true;
        var originalLabel = tplConfirmBtn.textContent;
        tplConfirmBtn.textContent = walkLabels.creatingTpl;

        var params = new URLSearchParams();
        params.append("name", name);
        params.append("group", group);
        params.append("items", JSON.stringify(items));

        fetch("zabbix.php?action=snmp.template.create", {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: params.toString()
        })
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                tplConfirmBtn.disabled = false;
                tplConfirmBtn.textContent = originalLabel;
                if (data && data.ok) {
                    alert((data && data.message) ? data.message : "OK");
                    closeTemplateModal();
                    clearSelection();
                } else {
                    alert((data && data.message) ? data.message : "Error");
                }
            })
            .catch(function(err) {
                tplConfirmBtn.disabled = false;
                tplConfirmBtn.textContent = originalLabel;
                alert(String(err));
            });
    }

    if (tplCloseBtn) {
        tplCloseBtn.addEventListener("click", function(e) { e.preventDefault(); closeTemplateModal(); });
    }
    if (tplCancelBtn) {
        tplCancelBtn.addEventListener("click", function(e) { e.preventDefault(); closeTemplateModal(); });
    }
    if (tplConfirmBtn) {
        tplConfirmBtn.addEventListener("click", function(e) { e.preventDefault(); submitTemplate(); });
    }
    if (tplNameInput) {
        tplNameInput.addEventListener("input", function() {
            validateTemplateName(tplNameInput.value.trim() !== "");
        });
        tplNameInput.addEventListener("blur", function() {
            if (tplNameInput.value.trim() !== "") {
                validateTemplateName(true);
            }
        });
    }
    if (tplModal) {
        tplModal.addEventListener("click", function(e) {
            if (e.target === tplModal) {
                closeTemplateModal();
            }
        });
    }

    initWalkTable();
    updateSelectionUi();
})();
</script>'));

ViewRenderer::render($pageTitle, $styleTag, $content);
