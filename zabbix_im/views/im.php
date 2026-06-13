<?php

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';

use Modules\ZabbixIm\Lib\LanguageManager;
use Modules\ZabbixIm\Lib\ViewRenderer;

$pageTitle = $data['title'] ?? LanguageManager::t('IM Sync');
$isConfigured = !empty($data['is_configured']);
$hasActiveSetting = !empty($data['has_active_setting']);
$activeSettingName = (string) ($data['active_setting_name'] ?? '');
$providerLabel = (string) ($data['provider_label'] ?? '');
$useFullPath = !empty($data['use_full_path']);
$removeOrphans = !empty($data['remove_orphans']);
$removeOrphanUsers = !empty($data['remove_orphan_users']);
$autoCreateUsers = !empty($data['auto_create_users']);
$configPath = (string) ($data['config_path'] ?? '');
$registryPath = (string) ($data['registry_path'] ?? '');
$managedGroups = (int) ($data['managed_groups'] ?? 0);
$managedUsers = (int) ($data['managed_users'] ?? 0);
$userGroups = $data['user_groups'] ?? [];

$styleTag = new CTag('style', true, '
.im-container {
    padding: 20px;
    width: 100%;
    margin: 0 auto;
}
.im-toolbar {
    background-color: #f8f9fa;
    padding: 16px 20px;
    border-radius: 4px;
    margin-bottom: 20px;
    border: 1px solid #dee2e6;
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 14px;
}
.im-field {
    display: flex;
    flex-direction: column;
    min-width: 180px;
}
.im-field label {
    font-weight: 600;
    margin-bottom: 5px;
    color: #495057;
    font-size: 13px;
}
.im-field-value {
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
    background-color: #fff;
    min-height: 38px;
    box-sizing: border-box;
    display: flex;
    align-items: center;
}
.im-toolbar-actions {
    display: flex;
    gap: 10px;
    margin-left: auto;
}
.im-btn {
    padding: 8px 16px;
    border: 1px solid transparent;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    text-align: center;
    height: 38px;
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
}
.im-btn-primary {
    color: #fff;
    background-color: #007bff;
    border-color: #007bff;
}
.im-btn-primary:hover { background-color: #0056b3; }
.im-btn-success {
    color: #fff;
    background-color: #28a745;
    border-color: #28a745;
}
.im-btn-success:hover { background-color: #1e7e34; }
.im-btn-secondary {
    color: #fff;
    background-color: #6c757d;
    border-color: #6c757d;
}
a.im-btn,
a.im-btn:hover,
a.im-btn:visited,
a.im-btn:focus {
    color: #fff;
    text-decoration: none;
}
.im-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}
.im-alert {
    padding: 12px 16px;
    border-radius: 4px;
    margin-bottom: 20px;
    font-size: 14px;
    border: 1px solid #ffeeba;
    background-color: #fff3cd;
    color: #856404;
}
.im-alert-success {
    border-color: #c3e6cb;
    background-color: #d4edda;
    color: #155724;
}
.im-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}
.im-stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    padding: 18px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.im-stat-card.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
.im-stat-card.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.im-stat-card.blue { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.im-stat-label { font-size: 13px; opacity: 0.9; margin-bottom: 6px; }
.im-stat-value { font-size: 28px; font-weight: 700; }
.im-table-wrap {
    overflow-x: auto;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    background: #fff;
}
.im-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}
.im-table th,
.im-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #dee2e6;
    text-align: left;
}
.im-table th {
    background-color: #f8f9fa;
    font-weight: 600;
    color: #495057;
}
.im-table tr:hover { background-color: #f8f9fa; }
.im-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
}
.im-badge-ok { background: #d4edda; color: #155724; }
.im-badge-warn { background: #fff3cd; color: #856404; }
.im-modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.45);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}
.im-modal-backdrop.show { display: flex; }
.im-modal {
    background: #fff;
    border-radius: 8px;
    width: min(960px, 92vw);
    max-height: 85vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}
.im-modal-header {
    padding: 16px 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
}
.im-modal-body {
    padding: 16px 20px;
    overflow: auto;
}
.im-modal-footer {
    padding: 12px 20px;
    border-top: 1px solid #dee2e6;
    text-align: right;
}
.im-config-path {
    font-family: monospace;
    font-size: 12px;
    word-break: break-all;
}
.im-raw-section {
    margin-top: 16px;
}
.im-raw-section-title {
    font-weight: 600;
    margin-bottom: 8px;
    color: #495057;
}
.im-raw-json {
    font-family: Consolas, Monaco, monospace;
    font-size: 11px;
    max-height: 220px;
    overflow: auto;
    white-space: pre-wrap;
    word-break: break-word;
    margin: 0;
    background: #f8f9fa;
    padding: 8px 10px;
    border-radius: 4px;
    border: 1px solid #e9ecef;
}
.im-raw-cell {
    min-width: 280px;
    max-width: 420px;
}
.im-collapse-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    margin: 12px 0 8px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    background: #f8f9fa;
    color: #495057;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}
.im-collapse-toggle:hover {
    background: #e9ecef;
}
.im-collapse-body {
    display: none;
}
.im-collapse-body.open {
    display: block;
}
.im-password-cell {
    font-family: Consolas, Monaco, monospace;
    font-weight: 600;
    color: #155724;
    word-break: break-all;
}
.im-sync-list-title {
    font-weight: 600;
    margin: 16px 0 8px;
    color: #495057;
}
.im-password-notice {
    margin: 0 0 12px;
    padding: 10px 12px;
    background: #fff3cd;
    border: 1px solid #ffeeba;
    border-radius: 4px;
    color: #856404;
    font-size: 13px;
}
');

$container = (new CDiv())->addClass('im-container');

if (!$isConfigured) {
    $hint = $hasActiveSetting
        ? LanguageManager::t('The enabled sync setting is missing required credentials. Please edit it in IM Sync Settings.')
        : LanguageManager::t('No enabled sync setting. Please add and enable one in IM Sync Settings.');
    $container->addItem(
        (new CDiv([
            new CSpan($hint),
            (new CDiv([
                (new CTag('a', true, LanguageManager::t('Go to IM Sync Settings')))
                    ->addClass('im-btn im-btn-primary')
                    ->setAttribute('href', 'zabbix.php?action=im.settings'),
            ]))->addStyle('margin-top: 10px;'),
        ]))->addClass('im-alert')
    );
}

$toolbar = (new CDiv())->addClass('im-toolbar');

$toolbar->addItem(
    (new CDiv([
        new CTag('label', true, LanguageManager::t('Active sync setting')),
        (new CDiv($activeSettingName !== '' ? $activeSettingName : '-'))->addClass('im-field-value'),
    ]))->addClass('im-field')
);

$toolbar->addItem(
    (new CDiv([
        new CTag('label', true, LanguageManager::t('Provider')),
        (new CDiv($providerLabel !== '' ? $providerLabel : '-'))->addClass('im-field-value'),
    ]))->addClass('im-field')
);

$toolbar->addItem(
    (new CDiv([
        new CTag('label', true, LanguageManager::t('Status')),
        (new CDiv(
            $isConfigured ? LanguageManager::t('Configured') : LanguageManager::t('Not configured')
        ))->addClass('im-field-value'),
    ]))->addClass('im-field')
);

$actions = (new CDiv())->addClass('im-toolbar-actions');
$actions->addItem(
    (new CTag('a', true, LanguageManager::t('Sync settings')))
        ->addClass('im-btn im-btn-secondary')
        ->setAttribute('href', 'zabbix.php?action=im.settings')
);
$previewBtn = (new CTag('button', true, LanguageManager::t('Preview departments')))
    ->addClass('im-btn im-btn-secondary')
    ->setId('im-preview-btn')
    ->setAttribute('type', 'button');
if (!$isConfigured) {
    $previewBtn->setAttribute('disabled', 'disabled');
}
$syncBtn = (new CTag('button', true, LanguageManager::t('Sync all departments')))
    ->addClass('im-btn im-btn-success')
    ->setId('im-sync-btn')
    ->setAttribute('type', 'button');
if (!$isConfigured) {
    $syncBtn->setAttribute('disabled', 'disabled');
}
$syncUsersBtn = (new CTag('button', true, LanguageManager::t('Sync all users')))
    ->addClass('im-btn im-btn-primary')
    ->setId('im-sync-users-btn')
    ->setAttribute('type', 'button');
if (!$isConfigured) {
    $syncUsersBtn->setAttribute('disabled', 'disabled');
}
$actions->addItem($previewBtn);
$actions->addItem($syncBtn);
$actions->addItem($syncUsersBtn);
$toolbar->addItem($actions);
$container->addItem($toolbar);

$stats = (new CDiv())->addClass('im-stats');
$stats->addItem(
    (new CDiv([
        (new CDiv(LanguageManager::t('Managed User Groups')))->addClass('im-stat-label'),
        (new CDiv((string) $managedGroups))->addClass('im-stat-value')->setId('im-stat-groups'),
    ]))->addClass('im-stat-card')
);
$stats->addItem(
    (new CDiv([
        (new CDiv(LanguageManager::t('Managed Users')))->addClass('im-stat-label'),
        (new CDiv((string) $managedUsers))->addClass('im-stat-value'),
    ]))->addClass('im-stat-card green')
);
$stats->addItem(
    (new CDiv([
        (new CDiv(LanguageManager::t('Auto create users')))->addClass('im-stat-label'),
        (new CDiv($autoCreateUsers ? LanguageManager::t('Yes') : LanguageManager::t('No')))->addClass('im-stat-value'),
    ]))->addClass('im-stat-card blue')
);
$stats->addItem(
    (new CDiv([
        (new CDiv(LanguageManager::t('Remove orphan groups on sync')))->addClass('im-stat-label'),
        (new CDiv($removeOrphans ? LanguageManager::t('Yes') : LanguageManager::t('No')))->addClass('im-stat-value'),
    ]))->addClass('im-stat-card orange')
);
$container->addItem($stats);

$container->addItem(
    (new CDiv(LanguageManager::t('System and manual users/groups are never deleted.')))
        ->addStyle('margin-bottom: 16px; color: #6c757d; font-size: 13px;')
);

$table = (new CTable())->addClass('im-table');
$table->addRow([
    (new CColHeader(LanguageManager::t('User Group'))),
    (new CColHeader(LanguageManager::t('Members'))),
], true);

if (empty($userGroups)) {
    $table->addRow([
        (new CCol([
            (new CDiv(LanguageManager::t('No managed groups yet')))->addStyle('text-align:center;padding:20px;color:#6c757d;'),
        ]))->setColSpan(2),
    ]);
} else {
    foreach ($userGroups as $group) {
        $table->addRow([
            new CCol(htmlspecialchars((string) ($group['name'] ?? ''))),
            new CCol((string) ((int) ($group['user_count'] ?? 0))),
        ]);
    }
}

$container->addItem((new CDiv($table))->addClass('im-table-wrap'));

$modal = (new CDiv([
    (new CDiv([
        (new CDiv(LanguageManager::t('Sync Result')))->addClass('im-modal-header')->setId('im-modal-title'),
        (new CDiv())->addClass('im-modal-body')->setId('im-modal-body'),
        (new CDiv([
            (new CTag('button', true, LanguageManager::t('Close')))
                ->addClass('im-btn im-btn-primary')
                ->setId('im-modal-close')
                ->setAttribute('type', 'button'),
        ]))->addClass('im-modal-footer'),
    ]))->addClass('im-modal'),
]))->addClass('im-modal-backdrop')->setId('im-modal-backdrop');

$jsLabels = json_encode([
    'departments'    => LanguageManager::t('Departments'),
    'groupsCreated'  => LanguageManager::t('Groups Created'),
    'groupsUpdated'  => LanguageManager::t('Groups Updated'),
    'groupsDeleted'  => LanguageManager::t('Groups Deleted'),
    'usersCreated'   => LanguageManager::t('Users Created'),
    'usersMatched'   => LanguageManager::t('Users Matched'),
    'usersDeleted'   => LanguageManager::t('Users Deleted'),
    'usersUnmatched' => LanguageManager::t('Users Unmatched'),
    'usersSynced'    => LanguageManager::t('Users Synced'),
    'unmatchedList'  => LanguageManager::t('Unmatched user list'),
    'department'     => LanguageManager::t('Department'),
    'userGroup'      => LanguageManager::t('User Group'),
    'imUsers'        => LanguageManager::t('IM Users'),
    'matched'        => LanguageManager::t('Matched'),
    'unmatched'      => LanguageManager::t('Unmatched'),
    'status'         => LanguageManager::t('Status'),
    'previewing'     => LanguageManager::t('Previewing...'),
    'syncing'        => LanguageManager::t('Syncing...'),
    'previewResult'  => LanguageManager::t('Preview Result'),
    'syncResult'     => LanguageManager::t('Sync Result'),
    'syncFailed'     => LanguageManager::t('Sync failed'),
    'confirmSync'    => LanguageManager::t('Confirm sync all departments to Zabbix user groups?'),
    'syncUsers'      => LanguageManager::t('Sync all users'),
    'syncingUsers'   => LanguageManager::t('Syncing users...'),
    'userSyncResult' => LanguageManager::t('User Sync Result'),
    'userSyncFailed' => LanguageManager::t('User sync failed'),
    'confirmSyncUsers' => LanguageManager::t('Confirm sync all users and assign them to user groups?'),
    'syncGroupsFirst'  => LanguageManager::t('Some departments are not synced yet. Run sync all departments before syncing users.'),
    'imRawData'        => LanguageManager::t('IM raw user data'),
    'imUserId'         => LanguageManager::t('IM User ID'),
    'userName'         => LanguageManager::t('Name'),
    'zabbixUsername'   => LanguageManager::t('Zabbix Username'),
    'zabbixCreate'     => LanguageManager::t('Zabbix create debug'),
    'error'            => LanguageManager::t('Error'),
    'wecomApiRaw'      => LanguageManager::t('IM API raw response'),
    'imApiRaw'         => LanguageManager::t('IM API raw response'),
    'apiAction'        => LanguageManager::t('API action'),
    'httpRaw'          => LanguageManager::t('HTTP raw body'),
    'parsedResponse'   => LanguageManager::t('Parsed response'),
    'extractedUserids' => LanguageManager::t('Extracted userids'),
    'message'          => LanguageManager::t('Message'),
    'userSyncList'     => LanguageManager::t('User sync list'),
    'username'           => LanguageManager::t('Username'),
    'mobile'             => LanguageManager::t('Mobile'),
    'password'           => LanguageManager::t('Password'),
    'passwordNotice'     => LanguageManager::t('New user passwords are shown only once. Please save them securely.'),
    'showImRawData'      => LanguageManager::t('Show IM raw user data'),
    'hideImRawData'      => LanguageManager::t('Hide IM raw user data'),
    'showImApiRaw'       => LanguageManager::t('Show IM API raw response'),
    'hideImApiRaw'       => LanguageManager::t('Hide IM API raw response'),
    'noSyncedUsers'      => LanguageManager::t('No synced users'),
    'statusLabels'     => [
        'skipped'   => LanguageManager::t('skipped'),
        'failed'    => LanguageManager::t('failed'),
        'updated'   => LanguageManager::t('updated'),
        'created'   => LanguageManager::t('created'),
        'renamed'   => LanguageManager::t('renamed'),
        'existing'  => LanguageManager::t('existing'),
        'linked'    => LanguageManager::t('linked'),
        'matched'   => LanguageManager::t('matched'),
        'unmatched' => LanguageManager::t('unmatched'),
        'preview'   => LanguageManager::t('preview'),
    ],
], JSON_UNESCAPED_UNICODE);

$jsScript = '(function() {
    "use strict";

    var labels = ' . $jsLabels . ';
    var previewBtn = document.getElementById("im-preview-btn");
    var syncBtn = document.getElementById("im-sync-btn");
    var syncUsersBtn = document.getElementById("im-sync-users-btn");
    var modal = document.getElementById("im-modal-backdrop");
    var modalTitle = document.getElementById("im-modal-title");
    var modalBody = document.getElementById("im-modal-body");
    var modalClose = document.getElementById("im-modal-close");

    function showModal(title, html) {
        if (!modal || !modalTitle || !modalBody) {
            return;
        }
        modalTitle.textContent = title;
        modalBody.innerHTML = html;
        modal.classList.add("show");
    }

    function hideModal() {
        if (modal) {
            modal.classList.remove("show");
        }
    }

    function escapeHtml(text) {
        var div = document.createElement("div");
        div.textContent = text == null ? "" : String(text);
        return div.innerHTML;
    }

    function translateStatus(status) {
        if (!status) {
            return "";
        }
        var key = String(status);
        if (labels.statusLabels && labels.statusLabels[key]) {
            return labels.statusLabels[key];
        }
        return key;
    }

    function statCard(label, value, cls) {
        var cardClass = "im-stat-card" + (cls ? " " + cls : "");
        return "<div class=\"" + cardClass + "\"><div class=\"im-stat-label\">" +
            escapeHtml(label) + "</div><div class=\"im-stat-value\">" + value + "</div></div>";
    }

    function renderJsonBlock(value) {
        var text = "";
        try {
            text = JSON.stringify(value, null, 2);
        } catch (e) {
            text = String(value);
        }
        return "<pre class=\"im-raw-json\">" + escapeHtml(text) + "</pre>";
    }

    function renderCollapsible(count, bodyHtml, startOpen, showLabel, hideLabel) {
        var openClass = startOpen ? " open" : "";
        var toggleLabel = startOpen ? hideLabel : showLabel;
        var icon = startOpen ? "▼" : "▶";
        return "<div class=\"im-raw-section\">" +
            "<button type=\"button\" class=\"im-collapse-toggle\" data-label-show=\"" + escapeHtml(showLabel) +
            "\" data-label-hide=\"" + escapeHtml(hideLabel) + "\">" +
            icon + " " + escapeHtml(toggleLabel) + " (" + escapeHtml(String(count)) + ")" +
            "</button>" +
            "<div class=\"im-collapse-body" + openClass + "\">" + bodyHtml + "</div>" +
            "</div>";
    }

    function renderUserSyncList(users, isPreview) {
        users = users || [];
        if (isPreview || users.length === 0) {
            return "";
        }

        var hasNewPassword = users.some(function(row) {
            return row.password && String(row.password).length > 0;
        });

        var html = "<div class=\"im-sync-list-title\">" + escapeHtml(labels.userSyncList) + "</div>";
        if (hasNewPassword) {
            html += "<div class=\"im-password-notice\">" + escapeHtml(labels.passwordNotice) + "</div>";
        }

        html += "<div class=\"im-table-wrap\"><table class=\"im-table\"><thead><tr>";
        html += "<th>" + escapeHtml(labels.username) + "</th>";
        html += "<th>" + escapeHtml(labels.userName) + "</th>";
        html += "<th>" + escapeHtml(labels.mobile) + "</th>";
        html += "<th>" + escapeHtml(labels.password) + "</th>";
        html += "<th>" + escapeHtml(labels.department) + "</th>";
        html += "<th>" + escapeHtml(labels.status) + "</th>";
        html += "<th>" + escapeHtml(labels.error) + "</th>";
        html += "</tr></thead><tbody>";

        users.forEach(function(row) {
            html += "<tr>";
            html += "<td>" + escapeHtml(row.username || "") + "</td>";
            html += "<td>" + escapeHtml(row.name || "") + "</td>";
            html += "<td>" + escapeHtml(row.mobile || "") + "</td>";
            html += "<td class=\"im-password-cell\">" + escapeHtml(row.password || "") + "</td>";
            html += "<td>" + escapeHtml(row.department || "") + "</td>";
            html += "<td>" + escapeHtml(translateStatus(row.status || "")) + "</td>";
            html += "<td style=\"color:#dc3545;\">" + escapeHtml(row.error || "") + "</td>";
            html += "</tr>";
        });

        html += "</tbody></table></div>";
        return html;
    }

    function renderImRawUsers(users) {
        users = users || [];
        if (users.length === 0) {
            return "";
        }

        var tableHtml = "<div class=\"im-table-wrap\"><table class=\"im-table\"><thead><tr>";
        tableHtml += "<th>" + escapeHtml(labels.department) + "</th>";
        tableHtml += "<th>" + escapeHtml(labels.imUserId) + "</th>";
        tableHtml += "<th>" + escapeHtml(labels.userName) + "</th>";
        tableHtml += "<th>" + escapeHtml(labels.zabbixUsername) + "</th>";
        tableHtml += "<th>" + escapeHtml(labels.status) + "</th>";
        tableHtml += "<th>" + escapeHtml(labels.error) + "</th>";
        tableHtml += "<th class=\"im-raw-cell\">" + escapeHtml(labels.imRawData) + "</th>";
        tableHtml += "<th class=\"im-raw-cell\">" + escapeHtml(labels.zabbixCreate) + "</th>";
        tableHtml += "</tr></thead><tbody>";

        users.forEach(function(row) {
            tableHtml += "<tr>";
            tableHtml += "<td>" + escapeHtml(row.department || "") + "</td>";
            tableHtml += "<td>" + escapeHtml(row.im_user_id || "") + "</td>";
            tableHtml += "<td>" + escapeHtml(row.name || "") + "</td>";
            tableHtml += "<td>" + escapeHtml(row.zabbix_username || "") + "</td>";
            tableHtml += "<td>" + escapeHtml(translateStatus(row.status || "")) + "</td>";
            tableHtml += "<td>" + escapeHtml(row.error || "") + "</td>";
            tableHtml += "<td class=\"im-raw-cell\">" + renderJsonBlock(row.raw || {}) + "</td>";
            tableHtml += "<td class=\"im-raw-cell\">" + renderJsonBlock(row.zabbix_create || {}) + "</td>";
            tableHtml += "</tr>";
        });

        tableHtml += "</tbody></table></div>";
        return renderCollapsible(users.length, tableHtml, false, labels.showImRawData, labels.hideImRawData);
    }

    function renderImApiLog(logs) {
        logs = logs || [];
        if (logs.length === 0) {
            return "";
        }

        var bodyHtml = "";

        logs.forEach(function(entry) {
            bodyHtml += "<div style=\"margin-bottom:16px;padding:12px;border:1px solid #dee2e6;border-radius:4px;background:#fff;\">";
            bodyHtml += "<div style=\"margin-bottom:8px;font-weight:600;\">";
            bodyHtml += escapeHtml(entry.action || "");
            if (entry.department_id) {
                bodyHtml += " <span style=\"font-weight:400;color:#6c757d;\">department_id=" + escapeHtml(entry.department_id) + "</span>";
            }
            if (entry.root_department_id) {
                bodyHtml += " <span style=\"font-weight:400;color:#6c757d;\">root_department_id=" + escapeHtml(entry.root_department_id) + "</span>";
            }
            if (entry.http_status) {
                bodyHtml += " <span style=\"font-weight:400;color:#6c757d;\">HTTP " + entry.http_status + "</span>";
            }
            bodyHtml += "</div>";

            if (entry.userids && entry.userids.length) {
                bodyHtml += "<div style=\"margin-bottom:8px;font-size:13px;\"><strong>" + escapeHtml(labels.extractedUserids) + ":</strong> ";
                bodyHtml += escapeHtml(entry.userids.join(", "));
                bodyHtml += "</div>";
            }

            bodyHtml += "<div style=\"margin-bottom:6px;font-size:12px;color:#495057;\">" + escapeHtml(labels.parsedResponse) + ":</div>";
            bodyHtml += renderJsonBlock(entry.parsed || {});

            bodyHtml += "<div style=\"margin:8px 0 6px;font-size:12px;color:#495057;\">" + escapeHtml(labels.httpRaw) + ":</div>";
            bodyHtml += "<pre class=\"im-raw-json\">" + escapeHtml(entry.raw == null ? "" : String(entry.raw)) + "</pre>";

            bodyHtml += "</div>";
        });

        return renderCollapsible(logs.length, bodyHtml, false, labels.showImApiRaw, labels.hideImApiRaw);
    }

    function renderSummary(summary, isPreview) {
        summary = summary || {};
        var isUserSync = summary.type === "users";
        var html = "<div class=\"im-stats\">";
        html += statCard(labels.departments, summary.departments || 0);
        if (!isPreview && !isUserSync) {
            html += statCard(labels.groupsCreated, summary.groups_created || 0, "green");
            html += statCard(labels.groupsUpdated, summary.groups_updated || 0, "blue");
            html += statCard(labels.groupsDeleted, summary.groups_deleted || 0, "orange");
        }
        if (!isPreview && isUserSync) {
            html += statCard(labels.groupsUpdated, summary.groups_updated || 0, "blue");
        }
        if (!isPreview) {
            html += statCard(labels.usersCreated, summary.users_created || 0, "green");
            html += statCard(labels.usersSynced, summary.users_matched || 0, "green");
            if (isUserSync) {
                html += statCard(labels.usersDeleted, summary.users_deleted || 0, "orange");
            }
            if ((summary.users_unmatched || 0) > 0) {
                html += statCard(labels.usersUnmatched, summary.users_unmatched || 0, "orange");
            }
        } else {
            html += statCard(labels.usersSynced, summary.users_matched || 0, "green");
            if ((summary.users_unmatched || 0) > 0) {
                html += statCard(labels.usersUnmatched, summary.users_unmatched || 0, "orange");
            }
        }
        html += "</div>";

        var unmatchedList = summary.unmatched_users || [];
        if (unmatchedList.length > 0) {
            html += "<div style=\"margin:12px 0;padding:12px 14px;background:#fff3cd;border:1px solid #ffeeba;border-radius:4px;color:#856404;\">";
            html += "<strong>" + escapeHtml(labels.unmatchedList) + ":</strong> ";
            html += escapeHtml(unmatchedList.join(", "));
            html += "</div>";
        }

        if (isPreview && (summary.user_groups_pending || 0) > 0) {
            html += "<div style=\"margin:12px 0;padding:12px 14px;background:#e2e3e5;border:1px solid #d6d8db;border-radius:4px;color:#383d41;\">";
            html += escapeHtml(labels.syncGroupsFirst || "Some departments are not synced yet. Run sync all departments before syncing users.");
            html += "</div>";
        }

        var details = summary.details || [];
        if (details.length > 0) {
            html += "<div class=\"im-table-wrap\"><table class=\"im-table\"><thead><tr>";
            html += "<th>" + escapeHtml(labels.department) + "</th>";
            html += "<th>" + escapeHtml(labels.userGroup) + "</th>";
            html += "<th>" + escapeHtml(labels.imUsers) + "</th>";
            html += "<th>" + escapeHtml(labels.matched) + "</th>";
            html += "<th>" + escapeHtml(labels.unmatched) + "</th>";
            if (!isPreview) {
                html += "<th>" + escapeHtml(labels.status) + "</th>";
                html += "<th>" + escapeHtml(labels.message || "Message") + "</th>";
            }
            html += "</tr></thead><tbody>";

            details.forEach(function(row) {
                var unmatched = row.unmatched || row.unmatched_users || [];
                var detailMsg = row.message || "";
                if ((!detailMsg || detailMsg.length === 0) && Array.isArray(unmatched) && unmatched.length > 0) {
                    detailMsg = unmatched.join("; ");
                }
                html += "<tr>";
                html += "<td>" + escapeHtml(row.department || "") + "</td>";
                html += "<td>" + escapeHtml(row.group_name || "") + "</td>";
                html += "<td>" + (row.im_users || 0) + "</td>";
                html += "<td>" + (row.matched || row.matched_users || 0) + "</td>";
                html += "<td>" + (Array.isArray(unmatched) ? unmatched.length : unmatched) + "</td>";
                if (!isPreview) {
                    var statusColor = row.status === "failed" ? "#dc3545" : (row.status === "skipped" ? "#856404" : "#155724");
                    html += "<td style=\"color:" + statusColor + ";font-weight:600;\">" + escapeHtml(translateStatus(row.status || "")) + "</td>";
                    html += "<td style=\"color:#dc3545;\">" + escapeHtml(detailMsg) + "</td>";
                }
                html += "</tr>";
            });

            html += "</tbody></table></div>";
        }

        if (isUserSync && !isPreview) {
            html += renderUserSyncList(summary.users_sync_list || [], isPreview);
        }

        html += renderImRawUsers(summary.im_users_raw || []);
        html += renderImApiLog(summary.im_api_log || []);

        return html;
    }

    function callAction(action, loadingText, title, isPreview, btn) {
        var originalText = btn ? btn.textContent : "";

        if (btn) {
            btn.disabled = true;
            btn.textContent = loadingText;
        }
        if (previewBtn && btn !== previewBtn) previewBtn.disabled = true;
        if (syncBtn && btn !== syncBtn) syncBtn.disabled = true;
        if (syncUsersBtn && btn !== syncUsersBtn) syncUsersBtn.disabled = true;

        fetch("zabbix.php?action=" + action, {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" }
        })
            .then(function(resp) { return resp.json(); })
            .then(function(data) {
                if (data && data.ok) {
                    showModal(title, renderSummary(data.summary, isPreview));
                } else {
                    alert((data && data.message) ? data.message : labels.syncFailed);
                }
            })
            .catch(function(err) {
                alert(labels.syncFailed + ": " + String(err));
            })
            .then(function() {
                if (previewBtn) previewBtn.disabled = false;
                if (syncBtn) syncBtn.disabled = false;
                if (syncUsersBtn) syncUsersBtn.disabled = false;
                if (btn) {
                    btn.textContent = originalText;
                }
            });
    }

    if (modalBody) {
        modalBody.addEventListener("click", function(e) {
            var btn = e.target.closest(".im-collapse-toggle");
            if (!btn) {
                return;
            }
            var body = btn.nextElementSibling;
            if (!body) {
                return;
            }
            var isOpen = body.classList.contains("open");
            if (isOpen) {
                body.classList.remove("open");
                btn.textContent = "▶ " + btn.getAttribute("data-label-show");
            } else {
                body.classList.add("open");
                btn.textContent = "▼ " + btn.getAttribute("data-label-hide");
            }
        });
    }

    if (previewBtn) {
        previewBtn.addEventListener("click", function() {
            callAction("im.preview", labels.previewing, labels.previewResult, true, previewBtn);
        });
    }

    if (syncBtn) {
        syncBtn.addEventListener("click", function() {
            if (!window.confirm(labels.confirmSync)) {
                return;
            }
            callAction("im.sync", labels.syncing, labels.syncResult, false, syncBtn);
        });
    }

    if (syncUsersBtn) {
        syncUsersBtn.addEventListener("click", function() {
            if (!window.confirm(labels.confirmSyncUsers)) {
                return;
            }
            callAction("im.sync.users", labels.syncingUsers, labels.userSyncResult, false, syncUsersBtn);
        });
    }

    if (modalClose) {
        modalClose.addEventListener("click", hideModal);
    }
    if (modal) {
        modal.addEventListener("click", function(e) {
            if (e.target === modal) {
                hideModal();
            }
        });
    }
})();';

if (class_exists('CJsScript')) {
    $script = new CJsScript('<script>' . $jsScript . '</script>');
} else {
    $script = new CTag('script', true, $jsScript);
}

$container->addItem($modal);
$container->addItem($script);

ViewRenderer::render($pageTitle, $styleTag, $container);
