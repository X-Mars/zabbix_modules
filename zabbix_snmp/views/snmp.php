<?php

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';

use Modules\ZabbixSnmp\Lib\LanguageManager;
use Modules\ZabbixSnmp\Lib\ViewRenderer;

$pageTitle = $data['title'] ?? LanguageManager::t('Zabbix Mibs');
$directories = $data['directories'] ?? [];
$files = $data['files'] ?? [];
$selectedDirectory = $data['selected_directory'] ?? '';
$selectedFile = $data['selected_file'] ?? null;
$selectedFilePath = $data['selected_file_path'] ?? '';
$search = $data['search'] ?? '';
$selectedMissing = !empty($data['selected_missing']);

$connMode = $data['conn_mode'] ?? 'host';
$groupid = $data['groupid'] ?? '';
$hostid = $data['hostid'] ?? '';
$hostGroups = $data['host_groups'] ?? [];
$hosts = $data['hosts'] ?? [];
$hostConnection = $data['host_connection'] ?? [];
$manualConnection = $data['manual_connection'] ?? [];
$testResult = $data['test_result'] ?? null;

function buildSnmpUrl(array $params, array $data): string {
    $url = 'zabbix.php?action=snmp';

    $carry = [
        'directory' => $data['selected_directory'] ?? '',
        'search' => $data['search'] ?? '',
        'conn_mode' => $data['conn_mode'] ?? 'host',
        'groupid' => $data['groupid'] ?? '',
        'hostid' => $data['hostid'] ?? '',
        'manual_address' => $data['manual_connection']['address'] ?? '',
        'manual_port' => $data['manual_connection']['port'] ?? '161',
        'manual_version' => $data['manual_connection']['version'] ?? '2c',
        'manual_community' => $data['manual_connection']['community'] ?? 'public',
        'manual_securityname' => $data['manual_connection']['securityname'] ?? '',
        'manual_securitylevel' => $data['manual_connection']['securitylevel'] ?? 'noAuthNoPriv',
        'manual_authprotocol' => $data['manual_connection']['authprotocol'] ?? 'SHA',
        'manual_authpassphrase' => $data['manual_connection']['authpassphrase'] ?? '',
        'manual_privprotocol' => $data['manual_connection']['privprotocol'] ?? 'AES',
        'manual_privpassphrase' => $data['manual_connection']['privpassphrase'] ?? '',
        'manual_contextname' => $data['manual_connection']['contextname'] ?? ''
    ];

    foreach ($carry as $key => $value) {
        if (!isset($params[$key]) && $value !== '' && $value !== null) {
            $params[$key] = $value;
        }
    }

    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            continue;
        }

        $url .= '&' . $key . '=' . urlencode((string) $value);
    }

    return $url;
}

function shellQuote(string $value): string {
    return "'" . str_replace("'", "'\"'\"'", $value) . "'";
}

function getObjectTestOid(array $object): string {
    $oid = trim((string) ($object['oid_numeric'] ?? $object['oid'] ?? ''));
    if ($oid === '' || $oid === '-') {
        return '';
    }

    $kind = strtoupper(trim((string) ($object['kind'] ?? '')));
    $syntax = strtoupper(trim((string) ($object['syntax'] ?? '')));
    if ($kind === 'OBJECT-TYPE' && stripos($syntax, 'SEQUENCE OF') === false && !preg_match('/\.0$/', $oid)) {
        $oid .= '.0';
    }

    return $oid;
}

function buildSnmpGetCommand(array $connection, array $object): string {
    $address = trim((string) ($connection['address'] ?? ''));
    if ($address === '') {
        return '# SNMP target is empty';
    }

    $port = trim((string) ($connection['port'] ?? '161'));
    $target = $address . ':' . ($port !== '' ? $port : '161');
    $oid = getObjectTestOid($object);
    if ($oid === '') {
        return '# OID is empty';
    }

    $version = strtolower(trim((string) ($connection['version'] ?? '2c')));
    if ($version === '1') {
        $community = (string) ($connection['community'] ?? 'public');
        return 'snmpget -v1 -c ' . shellQuote($community) . ' ' . shellQuote($target) . ' ' . shellQuote($oid);
    }

    if ($version === '3') {
        $securityName = (string) ($connection['securityname'] ?? '');
        $securityLevel = (string) ($connection['securitylevel'] ?? 'noAuthNoPriv');
        $authProtocol = strtoupper((string) ($connection['authprotocol'] ?? 'SHA'));
        $authPass = (string) ($connection['authpassphrase'] ?? '');
        $privProtocol = strtoupper((string) ($connection['privprotocol'] ?? 'AES'));
        $privPass = (string) ($connection['privpassphrase'] ?? '');

        $cmd = 'snmpget -v3 -u ' . shellQuote($securityName) . ' -l ' . shellQuote($securityLevel);
        if ($securityLevel === 'authNoPriv' || $securityLevel === 'authPriv') {
            $cmd .= ' -a ' . shellQuote($authProtocol) . ' -A ' . shellQuote($authPass);
        }
        if ($securityLevel === 'authPriv') {
            $cmd .= ' -x ' . shellQuote($privProtocol) . ' -X ' . shellQuote($privPass);
        }

        return $cmd . ' ' . shellQuote($target) . ' ' . shellQuote($oid);
    }

    $community = (string) ($connection['community'] ?? 'public');
    return 'snmpget -v2c -c ' . shellQuote($community) . ' ' . shellQuote($target) . ' ' . shellQuote($oid);
}

$styleTag = new CTag('style', true, '
.snmp-page {
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

.snmp-title {
    margin: 0 0 6px;
    color: #243b53;
    font-size: 24px;
}

.snmp-subtitle {
    margin: 0;
    color: #627d98;
}

.snmp-controls {
    margin-top: 12px;
    display: grid;
    grid-template-columns: minmax(200px, 1fr) minmax(200px, 1fr) minmax(160px, 0.9fr) minmax(160px, 0.9fr) auto;
    gap: 10px;
    align-items: center;
}

.snmp-input,
.snmp-select,
.snmp-btn,
.snmp-btn-link {
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

.snmp-btn-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 14px;
    text-decoration: none;
    background: #fff;
    color: #243b53;
}

.snmp-btn-outline {
    padding: 0 14px;
    background: #fff;
    border-color: #1b6ec2;
    color: #1b6ec2;
    cursor: pointer;
}

.snmp-testbox {
    margin-top: 14px;
    width: 100%;
    box-sizing: border-box;
}

.snmp-test-title {
    margin: 0;
    font-size: 15px;
    color: #243b53;
}

.snmp-host-test {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
    align-items: start;
    width: 100%;
}

.snmp-filter-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(260px, 1fr));
    gap: 10px;
    width: 100%;
}

.snmp-profile {
    border: 1px solid #e5edf5;
    border-radius: 8px;
    background: #f9fbfd;
    padding: 10px 12px;
    min-height: auto;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: nowrap;
    overflow-x: auto;
}

.snmp-profile-title {
    margin: 0;
    font-size: 13px;
    color: #334e68;
    white-space: nowrap;
}

.snmp-profile-item {
    margin: 0;
    font-size: 12px;
    color: #486581;
    white-space: nowrap;
}

.snmp-v3 {
    border-top: 1px dashed #c8d6e5;
    margin-top: 8px;
    padding-top: 8px;
}

.snmp-manual {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    width: 100%;
}

.snmp-field {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 8px;
}

.snmp-field-label {
    font-size: 12px;
    color: #627d98;
    white-space: nowrap;
}

.snmp-field .snmp-select,
.snmp-field .snmp-input {
    flex: 1;
    min-width: 0;
}

.snmp-layout {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.snmp-objects-section {
    border: 1px solid #d9e3ec;
    border-radius: 10px;
    background: #fff;
    overflow: hidden;
}

.snmp-panel-title-wrap {
    padding: 12px 14px;
    border-bottom: 1px solid #e5edf5;
    background: #f8fafc;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.snmp-panel-title-block {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.snmp-panel-actions {
    flex: 0 0 auto;
}

.snmp-panel-btn {
    display: inline-block;
    height: 30px;
    line-height: 28px;
    padding: 0 12px;
    border-radius: 6px;
    border: 1px solid #1b6ec2;
    background: #fff;
    color: #1b6ec2;
    font-size: 12px;
    cursor: pointer;
    white-space: nowrap;
}

.snmp-panel-title {
    margin: 0;
    color: #243b53;
    font-size: 16px;
}

.snmp-panel-hint {
    margin-top: 4px;
    font-size: 12px;
    color: #627d98;
}

.snmp-file-list {
    list-style: none;
    margin: 0;
    padding: 0;
    max-height: 800px;
    overflow-y: auto;
}

.snmp-file-item {
    border-top: 1px solid #edf2f7;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 0 14px;
}

.snmp-file-link {
    display: block;
    flex: 1 1 auto;
    min-width: 0;
    padding: 10px 0;
    color: #000;
    text-decoration: none;
}

.snmp-file-link:visited,
.snmp-file-link:hover,
.snmp-file-link:focus,
.snmp-file-link:active {
    color: #000;
}

.snmp-file-link:hover {
    background: #f4f8fc;
}

.snmp-file-link.active {
    color: #1b6ec2;
    background: transparent;
    box-shadow: none;
}

.snmp-file-name {
    display: block;
    font-weight: 600;
    word-break: break-word;
}

.snmp-file-meta {
    display: none;
}

.snmp-file-meta-btn {
    flex: 0 0 auto;
    height: auto;
    padding: 0;
    border: none;
    background: transparent;
    color: #1b6ec2;
    text-decoration: underline;
}

.snmp-empty {
    padding: 14px;
    color: #7b8794;
}

.snmp-right {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 12px;
    overflow: hidden;
}

.snmp-notice {
    padding: 10px 12px;
    border: 1px solid #f3c7c6;
    border-radius: 8px;
    background: #fff5f5;
    color: #9b1c1c;
}

.snmp-success {
    padding: 10px 12px;
    border: 1px solid #b7ebc6;
    border-radius: 8px;
    background: #effcf4;
    color: #127c56;
}

.snmp-test-result-card {
    border: 1px solid #d9e3ec;
    border-radius: 12px;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    padding: 14px;
    box-shadow: 0 8px 24px rgba(36, 59, 83, 0.08);
}

.snmp-test-result-card.snmp-success {
    border-color: #7dd3a8;
    background: linear-gradient(180deg, #f6fff9 0%, #e9fced 100%);
    color: #0f5f3f;
}

.snmp-test-result-card.snmp-notice {
    border-color: #f4a7a4;
    background: linear-gradient(180deg, #fff8f8 0%, #fff0f0 100%);
    color: #8a1f1f;
    position: sticky;
    z-index: 4;
}

.snmp-test-result-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.snmp-test-result-title {
    margin: 0;
    color: #243b53;
    font-size: 16px;
}

.snmp-test-result-badge {
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

.snmp-test-result-badge.snmp-success {
    background: #e8faf0;
    color: #127c56;
    border: 1px solid #b7ebc6;
}

.snmp-test-result-badge.snmp-notice {
    background: #fff3f2;
    color: #9b1c1c;
    border: 1px solid #f3c7c6;
}

.snmp-test-result-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.snmp-test-result-field {
    border: 1px solid #e5edf5;
    border-radius: 10px;
    background: #fff;
    padding: 10px 12px;
}

.snmp-test-result-label {
    margin-bottom: 4px;
    font-size: 12px;
    color: #627d98;
}

.snmp-test-result-value {
    margin: 0;
    color: #243b53;
    font-size: 13px;
    word-break: break-word;
}

.snmp-test-result-field-result {
    grid-column: 1 / -1;
}

.snmp-test-result-value-success {
    color: #0f6b45;
    font-weight: 600;
}

.snmp-test-result-value-failed {
    color: #9b1c1c;
    font-weight: 600;
}

.snmp-file-summary {
    border: 1px solid #e5edf5;
    border-radius: 8px;
    padding: 10px 12px;
    background: #f9fbfd;
    display: grid;
    grid-template-columns: repeat(2, minmax(180px, 1fr));
    gap: 8px;
    position: sticky;
    top: 12px;
    z-index: 3;
}

.snmp-right.has-test-result .snmp-file-summary {
    top: 178px;
}

.snmp-summary-item {
    font-size: 12px;
    color: #486581;
    word-break: break-word;
}

.snmp-summary-actions {
    grid-column: 1 / -1;
    display: flex;
    justify-content: flex-end;
}

.snmp-table-wrap {
    border: 1px solid #d9e3ec;
    border-radius: 8px;
    overflow: auto;
    max-height: min(68vh, 760px);
    overscroll-behavior: contain;
}

.snmp-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 720px;
}

.snmp-table th,
.snmp-table td {
    border-bottom: 1px solid #e5edf5;
    padding: 8px 10px;
    text-align: left;
    vertical-align: top;
    color: #243b53;
    font-size: 12px;
}

.snmp-table thead th {
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

.snmp-table tbody tr:hover {
    background: #f8fbfe;
}

.snmp-cell-stack {
    display: flex;
    flex-direction: column;
    gap: 3px;
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

.snmp-cell-meta-line {
    font-size: 11px;
    color: #486581;
    line-height: 1.4;
}

.snmp-cell-label {
    color: #829ab1;
    margin-right: 4px;
}

.snmp-col-desc {
    max-width: 200px;
    word-break: break-word;
}

.snmp-row-actions {
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
    width: min(1100px, 95vw);
    height: min(760px, 90vh);
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

.snmp-modal-fullscreen .snmp-modal-card {
    width: 96vw;
    height: 96vh;
    max-width: none;
    border-radius: 10px;
}

.snmp-modal-body {
    flex: 1;
    overflow: auto;
    min-height: 0;
}

.snmp-modal-fullscreen .snmp-table-wrap {
    max-height: none;
    border: none;
    border-radius: 0;
}

@media (max-width: 1280px) {
    .snmp-controls {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 820px) {
    .snmp-controls {
        grid-template-columns: 1fr;
    }

    .snmp-filter-row {
        grid-template-columns: 1fr;
    }

    .snmp-file-summary {
        grid-template-columns: 1fr;
    }
}
');

$content = (new CDiv())->addClass('snmp-page');

$top = (new CDiv())->addClass('snmp-top');
$top->addItem((new CTag('h1', true, LanguageManager::t('Zabbix Mibs')))->addClass('snmp-title'));
$top->addItem((new CDiv(LanguageManager::t('Browse operating system MIB files from common SNMP directories.')))->addClass('snmp-subtitle'));

$topForm = new CTag('form', true);
$topForm->addClass('snmp-controls');
$topForm->setAttribute('method', 'get');
$topForm->setAttribute('action', 'zabbix.php');
$topForm->addItem((new CTag('input'))->setAttribute('type', 'hidden')->setAttribute('name', 'action')->setAttribute('value', 'snmp'));
$topForm->addItem((new CTag('input'))->setAttribute('type', 'hidden')->setAttribute('name', 'conn_mode')->setAttribute('value', $connMode));

$directorySelect = (new CTag('select', true))
    ->addClass('snmp-select')
    ->setAttribute('name', 'directory')
    ->setAttribute('onchange', 'this.form.submit()');
if (empty($directories)) {
    $directorySelect->addItem((new CTag('option', true, LanguageManager::t('No directories with MIB files found.')))->setAttribute('value', ''));
} else {
    foreach ($directories as $directory) {
        $label = ($directory['path'] ?? '') . ' (' . (string) ($directory['file_count'] ?? 0) . ' ' . LanguageManager::t('files') . ')';
        $option = (new CTag('option', true, htmlspecialchars($label, ENT_QUOTES, 'UTF-8')))
            ->setAttribute('value', $directory['path']);
        if ($directory['path'] === $selectedDirectory) {
            $option->setAttribute('selected', 'selected');
        }
        $directorySelect->addItem($option);
    }
}
$topForm->addItem($directorySelect);

$fileSelect = (new CTag('select', true))
    ->addClass('snmp-select')
    ->setAttribute('name', 'file')
    ->setAttribute('onchange', 'this.form.submit()');
if (empty($files)) {
    $fileSelect->addItem((new CTag('option', true, LanguageManager::t('No MIB files found in this directory.')))->setAttribute('value', ''));
} else {
    foreach ($files as $file) {
        $option = (new CTag('option', true, htmlspecialchars((string) ($file['name'] ?? ''), ENT_QUOTES, 'UTF-8')))
            ->setAttribute('value', $file['path']);
        if ($selectedFilePath !== '' && $selectedFilePath === $file['path']) {
            $option->setAttribute('selected', 'selected');
        }
        $fileSelect->addItem($option);
    }
}
$topForm->addItem($fileSelect);

$groupSelect = (new CTag('select', true))
    ->addClass('snmp-select')
    ->setAttribute('name', 'groupid')
    ->setAttribute('onchange', 'this.form.submit()');
foreach ($hostGroups as $group) {
    $option = (new CTag('option', true, htmlspecialchars((string) ($group['name'] ?? ''), ENT_QUOTES, 'UTF-8')))
        ->setAttribute('value', $group['groupid']);
    if ((string) $group['groupid'] === (string) $groupid) {
        $option->setAttribute('selected', 'selected');
    }
    $groupSelect->addItem($option);
}
$topForm->addItem($groupSelect);

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
    $selectedHostFound = false;
    foreach ($hosts as $host) {
        $option = (new CTag('option', true, htmlspecialchars((string) ($host['name'] ?? ''), ENT_QUOTES, 'UTF-8')))
            ->setAttribute('value', $host['hostid']);
        if ((string) $host['hostid'] === (string) $hostid) {
            $option->setAttribute('selected', 'selected');
            $selectedHostFound = true;
        }
        $hostSelect->addItem($option);
    }

    if (!$selectedHostFound && isset($hosts[0])) {
        $hostSelect = (new CTag('select', true))
            ->addClass('snmp-select')
            ->setAttribute('name', 'hostid')
            ->setAttribute('onchange', 'this.form.submit()');

        foreach ($hosts as $index => $host) {
            $option = (new CTag('option', true, htmlspecialchars((string) ($host['name'] ?? ''), ENT_QUOTES, 'UTF-8')))
                ->setAttribute('value', $host['hostid']);
            if ($index === 0) {
                $option->setAttribute('selected', 'selected');
            }
            $hostSelect->addItem($option);
        }
    }
}
$topForm->addItem($hostSelect);

$topForm->addItem(
    (new CTag('button', true, LanguageManager::t('View Source')))
        ->addClass('snmp-btn snmp-btn-outline js-view-source')
        ->setAttribute('type', 'button')
        ->setAttribute('data-file', $selectedFilePath)
        ->setAttribute('data-symbol', '')
        ->setAttribute('data-directory', $selectedDirectory)
        ->setAttribute('data-search', '')
);

$top->addItem($topForm);

$testBox = (new CDiv())->addClass('snmp-testbox');

$profile = (new CDiv())->addClass('snmp-profile');
$profile->addItem((new CTag('h3', true, LanguageManager::t('Current Host SNMP Profile')))->addClass('snmp-profile-title'));

if (!empty($hostConnection)) {
    $profileRows = [
        LanguageManager::t('Host') . ': ' . ($hostConnection['host_name'] ?? '-'),
        LanguageManager::t('Address') . ': ' . (($hostConnection['address'] ?? '-') . ':' . ($hostConnection['port'] ?? '161')),
        LanguageManager::t('Version') . ': ' . ($hostConnection['version'] ?? '-')
    ];

    if (($hostConnection['version'] ?? '') === '3') {
        $profileRows[] = LanguageManager::t('Security Name') . ': ' . ($hostConnection['securityname'] ?? '-');
        $profileRows[] = LanguageManager::t('Security Level') . ': ' . ($hostConnection['securitylevel'] ?? '-');
        $profileRows[] = LanguageManager::t('Auth Protocol') . ': ' . ($hostConnection['authprotocol'] ?? '-');
        $profileRows[] = LanguageManager::t('Priv Protocol') . ': ' . ($hostConnection['privprotocol'] ?? '-');
    } else {
        $profileRows[] = LanguageManager::t('Community') . ': ' . ($hostConnection['community'] ?? '-');
    }

    foreach ($profileRows as $rowText) {
        $profile->addItem((new CDiv($rowText))->addClass('snmp-profile-item'));
    }
} else {
    $profile->addItem((new CDiv(LanguageManager::t('No SNMP interface found on this host.')))->addClass('snmp-profile-item'));
}
$testBox->addItem($profile);

$top->addItem($testBox);
$content->addItem($top);

$layout = (new CDiv())->addClass('snmp-layout');

$rightPanel = (new CDiv())->addClass('snmp-objects-section');
$rightPanelTitleWrap = (new CDiv())->addClass('snmp-panel-title-wrap');
$rightPanelTitle = (new CDiv())->addClass('snmp-panel-title-block');
$rightPanelTitle->addItem((new CTag('h2', true, LanguageManager::t('SNMP Objects')))->addClass('snmp-panel-title'));
$rightPanelTitleWrap->addItem($rightPanelTitle);
$rightPanelTitleWrap->addItem(
    (new CDiv())->addClass('snmp-panel-actions')->addItem(
        (new CTag('button', true, LanguageManager::t('Fullscreen')))
            ->addClass('snmp-panel-btn js-snmp-objects-fullscreen')
            ->setAttribute('type', 'button')
    )
);
$rightPanel->addItem($rightPanelTitleWrap);

$right = (new CDiv())->addClass('snmp-right')->setAttribute('id', 'snmp-right');
if ($testResult !== null) {
    $right->addClass('has-test-result');
}

if ($selectedMissing) {
    $right->addItem((new CDiv(LanguageManager::t('The selected MIB file is unavailable.')))->addClass('snmp-notice'));
}

if ($testResult !== null) {
    $resultClass = !empty($testResult['ok']) ? 'snmp-success' : 'snmp-notice';
    $resultTitle = !empty($testResult['ok']) ? LanguageManager::t('SNMP Test Success') : LanguageManager::t('SNMP Test Failed');
    $resultCard = (new CDiv())->addClass('snmp-test-result-card')->addClass($resultClass);

    $resultHead = (new CDiv())->addClass('snmp-test-result-head');
    $resultHead->addItem((new CTag('h3', true, $resultTitle))->addClass('snmp-test-result-title'));
    $resultHead->addItem((new CDiv(!empty($testResult['ok']) ? LanguageManager::t('Success') : LanguageManager::t('Failed')))->addClass('snmp-test-result-badge')->addClass($resultClass));
    $resultCard->addItem($resultHead);

    $resultGrid = (new CDiv())->addClass('snmp-test-result-grid');
    $resultRows = [
        ['label' => LanguageManager::t('Name'), 'value' => $testResult['symbol'] ?? '-', 'is_result' => false],
        ['label' => LanguageManager::t('OID'), 'value' => $testResult['oid'] ?? '-', 'is_result' => false],
        ['label' => LanguageManager::t('Resolved OID'), 'value' => $testResult['resolved_oid'] ?? '-', 'is_result' => false],
        ['label' => LanguageManager::t('Result'), 'value' => $testResult['message'] ?? '-', 'is_result' => true]
    ];

    foreach ($resultRows as $resultRow) {
        $field = (new CDiv())->addClass('snmp-test-result-field');
        if (!empty($resultRow['is_result'])) {
            $field->addClass('snmp-test-result-field-result');
        }

        $field->addItem((new CDiv($resultRow['label']))->addClass('snmp-test-result-label'));
        $valueDiv = (new CDiv((string) $resultRow['value']))->addClass('snmp-test-result-value');
        if (!empty($resultRow['is_result'])) {
            $valueDiv->addClass(!empty($testResult['ok']) ? 'snmp-test-result-value-success' : 'snmp-test-result-value-failed');
        }
        $field->addItem($valueDiv);
        $resultGrid->addItem($field);
    }

    $resultCard->addItem($resultGrid);
    $right->addItem($resultCard);
}

if ($selectedFile !== null) {
    $objects = $selectedFile['snmp_objects'] ?? [];
    if (!empty($objects)) {
        $tableWrap = (new CDiv())->addClass('snmp-table-wrap');
        $table = new CTag('table', true);
        $table->addClass('snmp-table');

        $thead = new CTag('thead', true);
        $headerRow = new CTag('tr', true);
        foreach ([
            LanguageManager::t('Name'),
            LanguageManager::t('Kind'),
            LanguageManager::t('OID'),
            LanguageManager::t('Attributes'),
            LanguageManager::t('Description'),
            LanguageManager::t('Line'),
            LanguageManager::t('Action')
        ] as $headerIndex => $header) {
            $th = new CTag('th', true, $header);
            if ($headerIndex === 4) {
                $th->addClass('snmp-col-desc');
            }
            $headerRow->addItem($th);
        }
        $thead->addItem($headerRow);
        $table->addItem($thead);

        $tbody = new CTag('tbody', true);
        foreach ($objects as $object) {
            $row = new CTag('tr', true);
            $row->addItem(new CTag('td', true, htmlspecialchars((string) $object['name'])));
            $row->addItem(new CTag('td', true, htmlspecialchars((string) $object['kind'])));

            $oidCell = new CTag('td', true);
            $oidStack = (new CDiv())->addClass('snmp-cell-stack');
            $oidStack->addItem((new CDiv(htmlspecialchars((string) $object['oid'])))->addClass('snmp-cell-primary'));
            $oidStack->addItem((new CDiv(htmlspecialchars((string) ($object['oid_numeric'] ?? '-'))))->addClass('snmp-cell-secondary snmp-cell-mono'));
            $oidCell->addItem($oidStack);
            $row->addItem($oidCell);

            $attrCell = new CTag('td', true);
            $attrStack = (new CDiv())->addClass('snmp-cell-stack');
            foreach ([
                LanguageManager::t('Syntax') => (string) $object['syntax'],
                LanguageManager::t('Access') => (string) $object['access'],
                LanguageManager::t('Status') => (string) $object['status']
            ] as $attrLabel => $attrValue) {
                $attrLine = (new CDiv())->addClass('snmp-cell-meta-line');
                $attrLine->addItem((new CTag('span', true, $attrLabel . ': '))->addClass('snmp-cell-label'));
                $attrLine->addItem(new CTag('span', true, htmlspecialchars($attrValue)));
                $attrStack->addItem($attrLine);
            }
            $attrCell->addItem($attrStack);
            $row->addItem($attrCell);

            $row->addItem((new CTag('td', true, htmlspecialchars((string) $object['description'])))->addClass('snmp-col-desc'));
            $row->addItem(new CTag('td', true, (string) ($object['start_line'] ?? '-')));

            $actionCell = new CTag('td', true);
            $actions = (new CDiv())->addClass('snmp-row-actions');
            $copyButton = (new CTag('button', true, LanguageManager::t('Copy Command')))
                ->addClass('snmp-row-test js-copy-snmpcmd')
                ->setAttribute('type', 'button')
                ->setAttribute('data-cmd', buildSnmpGetCommand($hostConnection, $object));
            $testLink = new CLink(
                LanguageManager::t('Test'),
                buildSnmpUrl([
                    'file' => $selectedFile['path'] ?? '',
                    'test' => 1,
                    'test_oid' => $object['oid'] ?? '',
                    'test_symbol' => $object['name'] ?? ''
                ], $data)
            );
            $testLink->addClass('snmp-row-test js-snmp-test-link');
            $actions->addItem($testLink);
            $actions->addItem($copyButton);
            $actionCell->addItem($actions);
            $row->addItem($actionCell);

            $tbody->addItem($row);
        }

        $table->addItem($tbody);
        $tableWrap->addItem($table);
        $right->addItem($tableWrap);
    } else {
        $right->addItem((new CDiv(LanguageManager::t('No SNMP objects parsed from this file.')))->addClass('snmp-empty'));
    }
} else {
    $right->addItem((new CDiv(LanguageManager::t('Select a MIB file from the dropdown above.')))->addClass('snmp-empty'));
}

$rightPanel->addItem($right);

$layout->addItem($rightPanel);
$content->addItem($layout);

$modal = (new CDiv())->addClass('snmp-modal')->setAttribute('id', 'snmp-source-modal');
$modalCard = (new CDiv())->addClass('snmp-modal-card');
$modalHead = (new CDiv())->addClass('snmp-modal-head');
$modalHead->addItem((new CTag('h3', true, LanguageManager::t('Source Preview')))->addClass('snmp-modal-title')->setAttribute('id', 'snmp-source-title'));
$modalHead->addItem((new CTag('button', true, LanguageManager::t('Close')))->addClass('snmp-modal-close')->setAttribute('type', 'button')->setAttribute('id', 'snmp-source-close'));
$modalCard->addItem($modalHead);
$modalCard->addItem((new CTag('pre', true, LanguageManager::t('Loading source...')))->addClass('snmp-modal-pre')->setAttribute('id', 'snmp-source-content'));
$modal->addItem($modalCard);
$content->addItem($modal);

$objectsModal = (new CDiv())->addClass('snmp-modal snmp-modal-fullscreen')->setAttribute('id', 'snmp-objects-modal');
$objectsModalCard = (new CDiv())->addClass('snmp-modal-card');
$objectsModalHead = (new CDiv())->addClass('snmp-modal-head');
$objectsModalHead->addItem((new CTag('h3', true, LanguageManager::t('SNMP Objects')))->addClass('snmp-modal-title'));
$objectsModalHead->addItem((new CTag('button', true, LanguageManager::t('Close')))->addClass('snmp-modal-close')->setAttribute('type', 'button')->setAttribute('id', 'snmp-objects-close'));
$objectsModalCard->addItem($objectsModalHead);
$objectsModalCard->addItem((new CDiv())->addClass('snmp-modal-body')->setAttribute('id', 'snmp-objects-modal-body'));
$objectsModal->addItem($objectsModalCard);
$content->addItem($objectsModal);

$noSourceAvailableText = json_encode(LanguageManager::t('No source available'));

$content->addItem(new CJsScript('<script>
(function() {
    "use strict";

    var modal = document.getElementById("snmp-source-modal");
    var closeBtn = document.getElementById("snmp-source-close");
    var title = document.getElementById("snmp-source-title");
    var content = document.getElementById("snmp-source-content");
    var objectsModal = document.getElementById("snmp-objects-modal");
    var objectsModalBody = document.getElementById("snmp-objects-modal-body");
    var objectsModalClose = document.getElementById("snmp-objects-close");
    var noSourceAvailable = ' . $noSourceAvailableText . ';

    function openModal() {
        modal.classList.add("open");
    }

    function closeModal() {
        modal.classList.remove("open");
    }

    function openObjectsModal() {
        var right = document.querySelector("#snmp-right");
        if (!right || !objectsModal || !objectsModalBody) {
            return;
        }

        objectsModalBody.innerHTML = right.innerHTML;
        objectsModal.classList.add("open");
        document.body.style.overflow = "hidden";
    }

    function closeObjectsModal() {
        if (!objectsModal || !objectsModalBody) {
            return;
        }

        objectsModal.classList.remove("open");
        objectsModalBody.innerHTML = "";
        document.body.style.overflow = "";
    }

    function setSourceLoading() {
        content.textContent = ' . json_encode(LanguageManager::t('Loading source...')) . ';
    }

    function setSourceText(text) {
        content.textContent = text || "";
    }

    function refreshRightPanel(url) {
        var currentRight = document.querySelector("#snmp-right");
        var tableWrap = currentRight ? currentRight.querySelector(".snmp-table-wrap") : null;
        var scrollTop = tableWrap ? tableWrap.scrollTop : 0;

        return fetch(url, { credentials: "same-origin" })
            .then(function(resp) { return resp.text(); })
            .then(function(html) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(html, "text/html");
                var nextRight = doc.querySelector("#snmp-right");

                if (nextRight && currentRight) {
                    currentRight.innerHTML = nextRight.innerHTML;
                    bindTableWheelScope();

                    var newTableWrap = currentRight.querySelector(".snmp-table-wrap");
                    if (newTableWrap) {
                        newTableWrap.scrollTop = scrollTop;
                    }
                }
            });
    }

    function bindTableWheelScope() {
        var right = document.querySelector("#snmp-right");
        if (!right || right.dataset.wheelBound === "1") {
            return;
        }

        right.dataset.wheelBound = "1";
        right.addEventListener("wheel", function(evt) {
            var tableWrap = right.querySelector(".snmp-table-wrap");
            if (!tableWrap) {
                return;
            }

            evt.preventDefault();
            tableWrap.scrollTop += evt.deltaY;
        }, { passive: false });
    }

    bindTableWheelScope();

    document.addEventListener("click", function(e) {
        var fullscreenBtn = e.target.closest(".js-snmp-objects-fullscreen");
        if (fullscreenBtn) {
            e.preventDefault();
            openObjectsModal();
            return;
        }

        var sourceBtn = e.target.closest(".js-view-source");
        if (sourceBtn) {
            e.preventDefault();

            var file = sourceBtn.getAttribute("data-file") || "";
            var symbol = sourceBtn.getAttribute("data-symbol") || "";
            var directory = sourceBtn.getAttribute("data-directory") || "";
            var search = sourceBtn.getAttribute("data-search") || "";
            var fileName = file !== "" ? file.split(/[\\\/]/).pop() : "";
            var titleSuffix = fileName !== "" ? " - " + fileName : "";
            if (symbol !== "") {
                titleSuffix += " (" + symbol + ")";
            }

            title.textContent = ' . json_encode(LanguageManager::t('Source Preview')) . ' + titleSuffix;
            setSourceLoading();
            openModal();

            var url = "zabbix.php?action=snmp.source"
                + "&directory=" + encodeURIComponent(directory)
                + "&file=" + encodeURIComponent(file)
                + "&symbol=" + encodeURIComponent(symbol)
                + "&search=" + encodeURIComponent(search);

            fetch(url, { credentials: "same-origin" })
                .then(function(resp) { return resp.json(); })
                .then(function(data) {
                    if (data && data.ok) {
                        setSourceText(data.source || "");
                    } else {
                        setSourceText((data && data.message) ? data.message : noSourceAvailable);
                    }
                })
                .catch(function(err) {
                    setSourceText(String(err));
                });
            return;
        }

        var testLink = e.target.closest(".js-snmp-test-link");
        if (testLink) {
            e.preventDefault();
            refreshRightPanel(testLink.href);
            return;
        }

        var copyBtn = e.target.closest(".js-copy-snmpcmd");
        if (copyBtn) {
            e.preventDefault();

            var cmd = copyBtn.getAttribute("data-cmd") || "";
            if (!cmd) {
                return;
            }

            var copiedLabel = ' . json_encode(LanguageManager::t('Copied')) . ';
            var originalLabel = copyBtn.textContent;
            var finishCopy = function() {
                copyBtn.textContent = copiedLabel;
                setTimeout(function() {
                    copyBtn.textContent = originalLabel;
                }, 1000);
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(cmd).then(finishCopy).catch(function() {
                    var ta = document.createElement("textarea");
                    ta.value = cmd;
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand("copy");
                    document.body.removeChild(ta);
                    finishCopy();
                });
            } else {
                var ta = document.createElement("textarea");
                ta.value = cmd;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand("copy");
                document.body.removeChild(ta);
                finishCopy();
            }
            return;
        }

        if (e.target === modal) {
            closeModal();
        }

        if (objectsModal && e.target === objectsModal) {
            closeObjectsModal();
        }
    });

    closeBtn.addEventListener("click", closeModal);
    if (objectsModalClose) {
        objectsModalClose.addEventListener("click", closeObjectsModal);
    }
})();
</script>'));

ViewRenderer::render($pageTitle, $styleTag, $content);
