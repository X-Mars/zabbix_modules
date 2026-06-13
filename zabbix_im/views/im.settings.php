<?php

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ConfigManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';

use Modules\ZabbixIm\Lib\LanguageManager;
use Modules\ZabbixIm\Lib\ConfigManager;
use Modules\ZabbixIm\Lib\ViewRenderer;

$pageTitle = $data['title'] ?? LanguageManager::t('IM Sync Settings');
$settings = $data['settings'] ?? [];
$configPath = (string) ($data['config_path'] ?? '');

$styleTag = new CTag('style', true, '
.ims-container { padding: 20px; width: 100%; margin: 0 auto; }
.ims-toolbar {
    background-color: #f8f9fa;
    padding: 16px 20px;
    border-radius: 4px;
    margin-bottom: 20px;
    border: 1px solid #dee2e6;
    display: flex;
    align-items: center;
    gap: 12px;
}
.ims-toolbar .ims-spacer { margin-left: auto; }
.ims-note { color: #6c757d; font-size: 13px; margin-bottom: 16px; }
.ims-btn {
    padding: 8px 16px;
    border: 1px solid transparent;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    height: 38px;
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
    text-decoration: none;
}
.ims-btn-primary { color: #fff; background-color: #007bff; border-color: #007bff; }
.ims-btn-primary:hover { background-color: #0056b3; }
.ims-btn-success { color: #fff; background-color: #28a745; border-color: #28a745; }
.ims-btn-secondary { color: #fff; background-color: #6c757d; border-color: #6c757d; }
a.ims-btn,
a.ims-btn:hover,
a.ims-btn:visited,
a.ims-btn:focus {
    color: #fff;
    text-decoration: none;
}
.ims-btn-danger { color: #fff; background-color: #dc3545; border-color: #dc3545; }
.ims-btn-sm { height: 30px; padding: 4px 10px; font-size: 12px; }
.ims-btn:disabled { opacity: 0.6; cursor: not-allowed; }
.ims-table-wrap { overflow-x: auto; border: 1px solid #dee2e6; border-radius: 4px; background: #fff; }
.ims-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.ims-table th, .ims-table td { padding: 12px 14px; border-bottom: 1px solid #dee2e6; text-align: left; }
.ims-table th { background-color: #f8f9fa; font-weight: 600; color: #495057; }
.ims-table tr:hover { background-color: #f8f9fa; }
.ims-badge { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: 12px; font-weight: 600; }
.ims-badge-on { background: #d4edda; color: #155724; }
.ims-badge-off { background: #e2e3e5; color: #6c757d; }
.ims-badge-ok { background: #d4edda; color: #155724; }
.ims-badge-warn { background: #fff3cd; color: #856404; }
.ims-actions { display: flex; gap: 6px; flex-wrap: wrap; }
.ims-config-path { font-family: monospace; font-size: 12px; color: #6c757d; word-break: break-all; }
.ims-modal-backdrop {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45);
    z-index: 10000; align-items: center; justify-content: center;
}
.ims-modal-backdrop.show { display: flex; }
.ims-modal {
    background: #fff; border-radius: 8px; width: min(560px, 92vw); max-height: 88vh;
    overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}
.ims-modal-header { padding: 16px 20px; border-bottom: 1px solid #dee2e6; font-weight: 600; }
.ims-modal-body { padding: 16px 20px; overflow: auto; }
.ims-modal-footer { padding: 12px 20px; border-top: 1px solid #dee2e6; text-align: right; display: flex; gap: 10px; justify-content: flex-end; }
.ims-field { margin-bottom: 14px; }
.ims-field label { display: block; font-weight: 600; margin-bottom: 5px; color: #495057; font-size: 13px; }
.ims-field input[type=text], .ims-field input[type=password], .ims-field select {
    width: 100%; padding: 8px 12px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px; box-sizing: border-box;
}
#ims-f-provider {
    height: 42px;
    min-height: 42px;
}
.ims-field .ims-hint { color: #6c757d; font-size: 12px; margin-top: 4px; }
.ims-check { display: flex; align-items: center; gap: 8px; }
.ims-check input { width: 16px; height: 16px; }
.ims-provider-group { display: none; }
.ims-provider-group.active { display: block; }
');

$container = (new CDiv())->addClass('ims-container');

// 工具栏
$toolbar = (new CDiv())->addClass('ims-toolbar');
$toolbar->addItem(
    (new CTag('button', true, LanguageManager::t('Add sync setting')))
        ->addClass('ims-btn ims-btn-primary')
        ->setId('ims-add-btn')
        ->setAttribute('type', 'button')
);
$toolbar->addItem(
    (new CTag('a', true, LanguageManager::t('Go to IM Sync')))
        ->addClass('ims-btn ims-btn-secondary ims-spacer')
        ->setAttribute('href', 'zabbix.php?action=im')
);
$container->addItem($toolbar);

$container->addItem(
    (new CDiv(LanguageManager::t('Only one sync setting can be enabled at a time. The sync page uses the enabled setting.')))
        ->addClass('ims-note')
);

// 列表
$table = (new CTable())->addClass('ims-table');
$table->addRow([
    new CColHeader(LanguageManager::t('Setting name')),
    new CColHeader(LanguageManager::t('Provider')),
    new CColHeader(LanguageManager::t('Root department ID')),
    new CColHeader(LanguageManager::t('Credentials')),
    new CColHeader(LanguageManager::t('Status')),
    new CColHeader(LanguageManager::t('Actions')),
], true);

if (empty($settings)) {
    $table->addRow([
        (new CCol(
            (new CDiv(LanguageManager::t('No sync settings yet. Click "Add sync setting" to create one.')))
                ->addStyle('text-align:center;padding:20px;color:#6c757d;')
        ))->setColSpan(6),
    ]);
} else {
    foreach ($settings as $setting) {
        $enabled = !empty($setting['enabled']);
        $configured = !empty($setting['configured']);

        $statusBadge = $enabled
            ? (new CSpan(LanguageManager::t('Enabled')))->addClass('ims-badge ims-badge-on')
            : (new CSpan(LanguageManager::t('Disabled')))->addClass('ims-badge ims-badge-off');

        $credBadge = $configured
            ? (new CSpan(LanguageManager::t('Configured')))->addClass('ims-badge ims-badge-ok')
            : (new CSpan(LanguageManager::t('Not configured')))->addClass('ims-badge ims-badge-warn');

        $actions = (new CDiv())->addClass('ims-actions');

        if ($enabled) {
            $actions->addItem(
                (new CTag('button', true, LanguageManager::t('Disable')))
                    ->addClass('ims-btn ims-btn-secondary ims-btn-sm')
                    ->setAttribute('type', 'button')
                    ->setAttribute('data-action', 'disable')
                    ->setAttribute('data-id', (string) $setting['id'])
            );
        } else {
            $actions->addItem(
                (new CTag('button', true, LanguageManager::t('Enable')))
                    ->addClass('ims-btn ims-btn-success ims-btn-sm')
                    ->setAttribute('type', 'button')
                    ->setAttribute('data-action', 'enable')
                    ->setAttribute('data-id', (string) $setting['id'])
            );
        }

        $actions->addItem(
            (new CTag('button', true, LanguageManager::t('Edit')))
                ->addClass('ims-btn ims-btn-primary ims-btn-sm')
                ->setAttribute('type', 'button')
                ->setAttribute('data-action', 'edit')
                ->setAttribute('data-id', (string) $setting['id'])
        );
        $actions->addItem(
            (new CTag('button', true, LanguageManager::t('Delete')))
                ->addClass('ims-btn ims-btn-danger ims-btn-sm')
                ->setAttribute('type', 'button')
                ->setAttribute('data-action', 'delete')
                ->setAttribute('data-id', (string) $setting['id'])
        );

        $table->addRow([
            new CCol(htmlspecialchars((string) $setting['name'])),
            new CCol(htmlspecialchars((string) $setting['provider_label'])),
            new CCol(htmlspecialchars((string) $setting['root_department_id'])),
            new CCol($credBadge),
            new CCol($statusBadge),
            new CCol($actions),
        ]);
    }
}

$container->addItem((new CDiv($table))->addClass('ims-table-wrap'));

if ($configPath !== '') {
    $container->addItem(
        (new CDiv([
            new CSpan(LanguageManager::t('Configuration File') . ': '),
            (new CSpan($configPath))->addClass('ims-config-path'),
        ]))->addStyle('margin-top:14px;')
    );
}

// 模态表单
$providerOptions = '';
foreach (['wecom', 'feishu', 'dingtalk'] as $p) {
    $providerOptions .= '<option value="' . $p . '">'
        . htmlspecialchars(ConfigManager::providerLabelFor($p)) . '</option>';
}

$formHtml = ''
    . '<input type="hidden" id="ims-f-id" value="">'
    . '<div class="ims-field"><label>' . htmlspecialchars(LanguageManager::t('Setting name')) . '</label>'
    . '<input type="text" id="ims-f-name" autocomplete="off"></div>'
    . '<div class="ims-field"><label>' . htmlspecialchars(LanguageManager::t('Provider')) . '</label>'
    . '<select id="ims-f-provider">' . $providerOptions . '</select></div>'
    // WeCom
    . '<div class="ims-provider-group" data-provider="wecom">'
    . '<div class="ims-field"><label>' . htmlspecialchars(LanguageManager::t('Corp ID')) . '</label>'
    . '<input type="text" id="ims-f-corp_id" autocomplete="off"></div>'
    . '<div class="ims-field"><label>' . htmlspecialchars(LanguageManager::t('Corp Secret')) . '</label>'
    . '<input type="password" id="ims-f-corp_secret" autocomplete="new-password">'
    . '<div class="ims-hint" id="ims-hint-corp_secret"></div></div>'
    . '</div>'
    // Feishu
    . '<div class="ims-provider-group" data-provider="feishu">'
    . '<div class="ims-field"><label>' . htmlspecialchars(LanguageManager::t('App ID')) . '</label>'
    . '<input type="text" id="ims-f-app_id" autocomplete="off"></div>'
    . '<div class="ims-field"><label>' . htmlspecialchars(LanguageManager::t('App Secret')) . '</label>'
    . '<input type="password" id="ims-f-app_secret" autocomplete="new-password">'
    . '<div class="ims-hint" id="ims-hint-app_secret"></div></div>'
    . '</div>'
    // DingTalk
    . '<div class="ims-provider-group" data-provider="dingtalk">'
    . '<div class="ims-field"><label>' . htmlspecialchars(LanguageManager::t('App Key')) . '</label>'
    . '<input type="text" id="ims-f-app_key" autocomplete="off"></div>'
    . '<div class="ims-field"><label>' . htmlspecialchars(LanguageManager::t('App Secret')) . '</label>'
    . '<input type="password" id="ims-f-app_secret_dingtalk" autocomplete="new-password">'
    . '<div class="ims-hint" id="ims-hint-app_secret_dingtalk"></div></div>'
    . '</div>'
    . '<div class="ims-field"><label>' . htmlspecialchars(LanguageManager::t('Root department ID')) . '</label>'
    . '<input type="text" id="ims-f-root" autocomplete="off">'
    . '<div class="ims-hint">' . htmlspecialchars(LanguageManager::t('WeCom/DingTalk default 1, Feishu default 0')) . '</div></div>'
    . '<div class="ims-field"><div class="ims-check">'
    . '<input type="checkbox" id="ims-f-enabled"><label for="ims-f-enabled" style="margin:0;">'
    . htmlspecialchars(LanguageManager::t('Enable this setting (disables others)')) . '</label></div></div>';

$modal = (new CDiv([
    (new CDiv([
        (new CDiv(LanguageManager::t('Add sync setting')))->addClass('ims-modal-header')->setId('ims-modal-title'),
        (new CDiv())->addClass('ims-modal-body')->setId('ims-modal-body'),
        (new CDiv([
            (new CTag('button', true, LanguageManager::t('Cancel')))
                ->addClass('ims-btn ims-btn-secondary')->setId('ims-modal-cancel')->setAttribute('type', 'button'),
            (new CTag('button', true, LanguageManager::t('Save')))
                ->addClass('ims-btn ims-btn-primary')->setId('ims-modal-save')->setAttribute('type', 'button'),
        ]))->addClass('ims-modal-footer'),
    ]))->addClass('ims-modal'),
]))->addClass('ims-modal-backdrop')->setId('ims-modal-backdrop');

$container->addItem($modal);

$jsData = json_encode([
    'settings' => $settings,
    'formHtml' => $formHtml,
    'secretHint' => LanguageManager::t('Leave blank to keep unchanged'),
    'labels' => [
        'addTitle'      => LanguageManager::t('Add sync setting'),
        'editTitle'     => LanguageManager::t('Edit sync setting'),
        'confirmDelete' => LanguageManager::t('Confirm delete this sync setting?'),
        'saveFailed'    => LanguageManager::t('Save failed'),
        'deleteFailed'  => LanguageManager::t('Delete failed'),
        'nameRequired'  => LanguageManager::t('Setting name is required.'),
    ],
], JSON_UNESCAPED_UNICODE);

$jsScript = '(function() {
    "use strict";
    var data = ' . $jsData . ';
    var settings = data.settings || [];
    var labels = data.labels || {};

    var modal = document.getElementById("ims-modal-backdrop");
    var modalTitle = document.getElementById("ims-modal-title");
    var modalBody = document.getElementById("ims-modal-body");

    function byId(id) { return document.getElementById(id); }

    function showModal(title) {
        modalBody.innerHTML = data.formHtml;
        modalTitle.textContent = title;
        bindForm();
        modal.classList.add("show");
    }
    function hideModal() { modal.classList.remove("show"); }

    function currentProvider() {
        var sel = byId("ims-f-provider");
        return sel ? sel.value : "wecom";
    }

    function syncProviderGroups() {
        var p = currentProvider();
        var groups = modalBody.querySelectorAll(".ims-provider-group");
        groups.forEach(function(g) {
            if (g.getAttribute("data-provider") === p) {
                g.classList.add("active");
            } else {
                g.classList.remove("active");
            }
        });
    }

    function bindForm() {
        var sel = byId("ims-f-provider");
        if (sel) {
            sel.addEventListener("change", syncProviderGroups);
        }
        syncProviderGroups();
    }

    function resetSecretHints() {
        ["ims-hint-corp_secret", "ims-hint-app_secret", "ims-hint-app_secret_dingtalk"].forEach(function(id) {
            var el = byId(id);
            if (el) { el.textContent = ""; }
        });
    }

    function openCreate() {
        showModal(labels.addTitle);
        byId("ims-f-id").value = "";
        byId("ims-f-name").value = "";
        byId("ims-f-provider").value = "wecom";
        byId("ims-f-corp_id").value = "";
        byId("ims-f-corp_secret").value = "";
        byId("ims-f-app_id").value = "";
        byId("ims-f-app_secret").value = "";
        byId("ims-f-app_key").value = "";
        byId("ims-f-app_secret_dingtalk").value = "";
        byId("ims-f-root").value = "";
        byId("ims-f-enabled").checked = false;
        resetSecretHints();
        syncProviderGroups();
    }

    function findSetting(id) {
        for (var i = 0; i < settings.length; i++) {
            if (String(settings[i].id) === String(id)) { return settings[i]; }
        }
        return null;
    }

    function openEdit(id) {
        var s = findSetting(id);
        if (!s) { return; }
        showModal(labels.editTitle);
        byId("ims-f-id").value = s.id;
        byId("ims-f-name").value = s.name || "";
        byId("ims-f-provider").value = s.provider || "wecom";
        byId("ims-f-corp_id").value = s.corp_id || "";
        byId("ims-f-app_id").value = s.app_id || "";
        byId("ims-f-app_key").value = s.app_key || "";
        byId("ims-f-corp_secret").value = "";
        byId("ims-f-app_secret").value = "";
        byId("ims-f-app_secret_dingtalk").value = "";
        byId("ims-f-root").value = s.root_department_id || "";
        byId("ims-f-enabled").checked = !!s.enabled;
        resetSecretHints();
        if (s.corp_secret_set) { byId("ims-hint-corp_secret").textContent = data.secretHint; }
        if (s.app_secret_set) {
            byId("ims-hint-app_secret").textContent = data.secretHint;
            byId("ims-hint-app_secret_dingtalk").textContent = data.secretHint;
        }
        syncProviderGroups();
    }

    function postForm(action, params, cb) {
        var body = Object.keys(params).map(function(k) {
            return encodeURIComponent(k) + "=" + encodeURIComponent(params[k]);
        }).join("&");
        fetch("zabbix.php?action=" + action, {
            method: "POST",
            credentials: "same-origin",
            headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
            body: body
        }).then(function(r) { return r.json(); }).then(cb).catch(function(err) {
            alert(labels.saveFailed + ": " + String(err));
        });
    }

    function save() {
        var provider = currentProvider();
        var name = byId("ims-f-name").value.trim();
        if (name === "") { alert(labels.nameRequired); return; }

        var appSecret = provider === "dingtalk"
            ? byId("ims-f-app_secret_dingtalk").value
            : byId("ims-f-app_secret").value;

        var params = {
            id: byId("ims-f-id").value,
            name: name,
            provider: provider,
            root_department_id: byId("ims-f-root").value.trim(),
            corp_id: byId("ims-f-corp_id").value.trim(),
            corp_secret: byId("ims-f-corp_secret").value,
            app_id: byId("ims-f-app_id").value.trim(),
            app_secret: appSecret,
            app_key: byId("ims-f-app_key").value.trim(),
            enabled: byId("ims-f-enabled").checked ? "1" : "0"
        };

        var saveBtn = byId("ims-modal-save");
        if (saveBtn) { saveBtn.disabled = true; }
        postForm("im.settings.save", params, function(resp) {
            if (resp && resp.ok) {
                window.location.reload();
            } else {
                if (saveBtn) { saveBtn.disabled = false; }
                alert((resp && resp.message) ? resp.message : labels.saveFailed);
            }
        });
    }

    function doEnable(id, enable) {
        postForm("im.settings.enable", { id: id, enable: enable ? "1" : "0" }, function(resp) {
            if (resp && resp.ok) { window.location.reload(); }
            else { alert((resp && resp.message) ? resp.message : labels.saveFailed); }
        });
    }

    function doDelete(id) {
        if (!window.confirm(labels.confirmDelete)) { return; }
        postForm("im.settings.delete", { id: id }, function(resp) {
            if (resp && resp.ok) { window.location.reload(); }
            else { alert((resp && resp.message) ? resp.message : labels.deleteFailed); }
        });
    }

    var addBtn = byId("ims-add-btn");
    if (addBtn) { addBtn.addEventListener("click", openCreate); }

    var cancelBtn = byId("ims-modal-cancel");
    if (cancelBtn) { cancelBtn.addEventListener("click", hideModal); }
    var saveBtn = byId("ims-modal-save");
    if (saveBtn) { saveBtn.addEventListener("click", save); }
    if (modal) {
        modal.addEventListener("click", function(e) {
            if (e.target === modal) { hideModal(); }
        });
    }

    document.addEventListener("click", function(e) {
        var btn = e.target.closest ? e.target.closest("[data-action]") : null;
        if (!btn) { return; }
        var action = btn.getAttribute("data-action");
        var id = btn.getAttribute("data-id");
        if (action === "edit") { openEdit(id); }
        else if (action === "delete") { doDelete(id); }
        else if (action === "enable") { doEnable(id, true); }
        else if (action === "disable") { doEnable(id, false); }
    });
})();';

if (class_exists('CJsScript')) {
    $script = new CJsScript('<script>' . $jsScript . '</script>');
} else {
    $script = new CTag('script', true, $jsScript);
}
$container->addItem($script);

ViewRenderer::render($pageTitle, $styleTag, $container);
