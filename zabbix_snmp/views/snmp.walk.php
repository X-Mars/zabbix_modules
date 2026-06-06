<?php

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';

use Modules\ZabbixSnmp\Lib\LanguageManager;
use Modules\ZabbixSnmp\Lib\ViewRenderer;

$pageTitle = $data['title'] ?? LanguageManager::t('Zabbix Walk');
$groupid = $data['groupid'] ?? '';
$hostid = $data['hostid'] ?? '';
$hostGroups = $data['host_groups'] ?? [];
$hosts = $data['hosts'] ?? [];
$hostConnection = $data['host_connection'] ?? [];
$walkOid = $data['walk_oid'] ?? '1.3.6.1.2.1';
$walkResult = $data['walk_result'] ?? null;

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

.snmp-title {
    margin: 0 0 6px;
    color: #243b53;
    font-size: 24px;
}

.snmp-subtitle {
    margin: 0;
    color: #627d98;
}

.snmp-testbox {
    margin-top: 14px;
    border: 1px solid #d9e3ec;
    border-radius: 8px;
    padding: 12px;
    background: #fff;
    display: flex;
    flex-direction: column;
    gap: 12px;
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

.snmp-result-meta {
    padding: 10px 14px;
    font-size: 12px;
    color: #486581;
    border-bottom: 1px solid #e5edf5;
}

.snmp-result-pre {
    margin: 0;
    padding: 12px;
    max-height: min(68vh, 760px);
    overflow: auto;
    background: #10151b;
    color: #d9e2ec;
    font: 12px/1.45 Consolas, Monaco, monospace;
    white-space: pre;
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
$top->addItem((new CTag('h1', true, LanguageManager::t('Zabbix Walk')))->addClass('snmp-title'));
$top->addItem((new CDiv(LanguageManager::t('SNMP Walk Runner')))->addClass('snmp-subtitle'));

$testBox = (new CDiv())->addClass('snmp-testbox');
$testBox->addItem((new CTag('h2', true, LanguageManager::t('SNMP Connection Test')))->addClass('snmp-test-title'));

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

$testBox->addItem($filterForm);

if (!empty($hostConnection)) {
    $profile = (new CDiv())->addClass('snmp-profile');
    $profile->addItem((new CDiv(LanguageManager::t('Host') . ': ' . ($hostConnection['host_name'] ?? '-')))->addClass('snmp-profile-item'));
    $profile->addItem((new CDiv(LanguageManager::t('Address') . ': ' . (($hostConnection['address'] ?? '-') . ':' . ($hostConnection['port'] ?? '161'))))->addClass('snmp-profile-item'));
    $profile->addItem((new CDiv(LanguageManager::t('Version') . ': ' . ($hostConnection['version'] ?? '-')))->addClass('snmp-profile-item'));
    $testBox->addItem($profile);
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

$testBox->addItem((new CSpan(LanguageManager::t('Walk OID')))->addClass('snmp-field-label'));
$testBox->addItem($runForm);

$top->addItem($testBox);
$content->addItem($top);

if ($walkResult !== null) {
    $isOk = !empty($walkResult['ok']);
    $lines = is_array($walkResult['lines'] ?? null) ? $walkResult['lines'] : [];
    $resultBlock = (new CDiv())->addClass('snmp-walk-result');

    $head = (new CDiv())->addClass('snmp-result-head');
    $head->addItem((new CTag('h2', true, LanguageManager::t('SNMP Walk Results')))->addClass('snmp-result-title'));
    $head->addItem(
        (new CDiv($isOk ? LanguageManager::t('Success') : LanguageManager::t('Failed')))
            ->addClass('snmp-result-badge')
            ->addClass($isOk ? 'snmp-success' : 'snmp-notice')
    );
    $resultBlock->addItem($head);

    if ($isOk) {
        $resultBlock->addItem((new CDiv(LanguageManager::t('Total lines') . ': ' . count($lines)))->addClass('snmp-result-meta'));
        $resultBlock->addItem((new CTag('pre', true, htmlspecialchars(implode("\n", $lines), ENT_QUOTES, 'UTF-8')))->addClass('snmp-result-pre'));
    } else {
        $message = (string) ($walkResult['message'] ?? LanguageManager::t('No walk results'));
        $resultBlock->addItem((new CDiv($message))->addClass('snmp-result-meta'));
    }

    $content->addItem($resultBlock);
}

ViewRenderer::render($pageTitle, $styleTag, $content);
