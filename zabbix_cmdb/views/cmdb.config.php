<?php
require_once dirname(__DIR__).'/lib/ItemConfig.php';
require_once dirname(__DIR__).'/lib/ViewRenderer.php';
use Modules\ZabbixCmdb\Lib\ItemConfig;
use Modules\ZabbixCmdb\Lib\LanguageManager;
use Modules\ZabbixCmdb\Lib\ViewRenderer;

$t = function ($key) { return htmlspecialchars(LanguageManager::t($key), ENT_QUOTES, 'UTF-8'); };
$escape = function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
$labelKeys = ['Item Name', 'Exact Match', 'Fuzzy Match', 'Item name or key', 'Original Value', '100 − value', 'Move Up', 'Move Down', 'Delete', 'Rules', 'No rules configured', 'Add the first rule to enable this metric.'];
$labels = array_combine($labelKeys, array_map([LanguageManager::class, 't'], $labelKeys));
ob_start();
?>
<form id="cmdb-config" class="cmdb-config" method="post" action="zabbix.php?action=cmdb.config">
    <input type="hidden" name="config_token" value="<?= $escape($data['token']) ?>">
    <input type="hidden" name="rules_json" id="cmdb-rules-json">
    <div class="cmdb-info">
        <span class="cmdb-info__icon">i</span>
        <div class="cmdb-info__content">
            <p><?= $t('Rules are evaluated from top to bottom; the first match wins. Item name and key rules can be mixed in any order.') ?></p>
            <p><?= $t('Fuzzy matching is case-insensitive and supports substrings and the * wildcard. Exact matching is case-sensitive. If multiple enabled items match one rule, the item with the lowest itemid is selected.') ?></p>
            <p><?= $t('CPU total is measured in cores, memory total in bytes, and usage in %. For idle or available percentages, select “100 − value”. An empty rule list disables the metric.') ?></p>
        </div>
    </div>
    <?php if ($data['message']): ?><div role="status" class="cmdb-alert cmdb-alert--success"><span class="cmdb-alert__icon">✓</span><span><?= $escape($data['message']) ?></span></div><?php endif ?>
    <?php if ($data['error']): ?><div role="alert" class="cmdb-alert cmdb-alert--error"><span class="cmdb-alert__icon">!</span><span><?= $escape($data['error']) ?></span></div><?php endif ?>
    <?php foreach (ItemConfig::LABELS as $category => $label): ?>
    <section class="cmdb-rule-section" data-category="<?= $category ?>">
        <header class="cmdb-card__header">
            <div class="cmdb-card__title"><span class="cmdb-card__marker"></span><h2><?= $t($label) ?></h2><span class="cmdb-count">0 <?= $t('Rules') ?></span></div>
            <button type="button" class="cmdb-button cmdb-button--primary cmdb-add"><span class="cmdb-button__icon">＋</span><?= $t('Add Rule') ?></button>
        </header>
        <div class="cmdb-table-wrap">
            <table class="cmdb-table"><thead><tr><th><?= $t('Match Field') ?></th><th><?= $t('Match Type') ?></th><th><?= $t('Item Name / Key') ?></th><th><?= $t('Value Conversion') ?></th><th><?= $t('Order / Actions') ?></th></tr></thead><tbody></tbody></table>
            <div class="cmdb-empty"><span class="cmdb-empty__icon">＋</span><strong><?= $t('No rules configured') ?></strong><span><?= $t('Add the first rule to enable this metric.') ?></span></div>
        </div>
    </section>
    <?php endforeach ?>
    <div class="cmdb-actions"><a class="cmdb-button cmdb-button--default" href="zabbix.php?action=cmdb"><?= $t('Back to Host List') ?></a><button type="submit" class="cmdb-button cmdb-button--primary cmdb-button--save"><?= $t('Save Configuration') ?></button></div>
</form>
<script>
(function () {
    const labels = <?= json_encode($labels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const form = document.getElementById('cmdb-config');
    let rules = <?= json_encode($data['rules'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const submitted = <?= json_encode($data['submitted'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    if (submitted) { try { rules = JSON.parse(submitted); } catch (_) {} }
    function refreshSection(section) {
        const count = section.querySelectorAll('tbody tr').length;
        section.querySelector('.cmdb-count').textContent = count + ' ' + labels['Rules'];
        section.classList.toggle('is-empty', count === 0);
    }
    function addRow(section, rule = {}) {
        const body = section.querySelector('tbody');
        const row = document.createElement('tr');
        function select(name, values, value) {
            const input = document.createElement('select');
            input.dataset.field = name;
            Object.entries(values).forEach(([key, label]) => input.add(new Option(label, key)));
            input.value = value;
            const cell = row.insertCell(); cell.append(input);
            return input;
        }
        select('field', {key_: 'Key', name: labels['Item Name']}, rule.field || 'key_');
        select('match', {exact: labels['Exact Match'], fuzzy: labels['Fuzzy Match']}, rule.match || 'fuzzy');
        const pattern = document.createElement('input');
        pattern.type = 'text'; pattern.required = true; pattern.maxLength = 255;
        pattern.dataset.field = 'pattern'; pattern.value = rule.pattern || '';
        pattern.setAttribute('aria-label', labels['Item name or key']);
        row.insertCell().append(pattern);
        const transform = select('transform', {none: labels['Original Value'], subtract_from_100: labels['100 − value']}, rule.transform || 'none');
        if (!['cpu_usage', 'memory_usage'].includes(section.dataset.category)) {
            transform.value = 'none'; transform.disabled = true;
        }
        const actions = row.insertCell(); actions.className = 'cmdb-row-actions';
        [[labels['Move Up'], '↑', () => { if (row.previousElementSibling) body.insertBefore(row, row.previousElementSibling); }],
         [labels['Move Down'], '↓', () => { if (row.nextElementSibling) body.insertBefore(row.nextElementSibling, row); }],
         [labels['Delete'], '×', () => { row.remove(); refreshSection(section); }]].forEach(([label, icon, click], index) => {
            const button = document.createElement('button'); button.type = 'button';
            button.className = 'cmdb-icon-button' + (index === 2 ? ' cmdb-icon-button--danger' : '');
            button.textContent = icon; button.title = label; button.setAttribute('aria-label', label);
            button.addEventListener('click', click); actions.append(button);
        });
        body.append(row);
        refreshSection(section);
    }
    form.querySelectorAll('section').forEach(section => {
        const entries = rules && rules[section.dataset.category];
        (Array.isArray(entries) ? entries : []).forEach(rule => addRow(section, rule));
        section.querySelector('.cmdb-add').addEventListener('click', () => addRow(section));
        refreshSection(section);
    });
    form.addEventListener('submit', () => {
        const output = {};
        form.querySelectorAll('section').forEach(section => {
            output[section.dataset.category] = Array.from(section.querySelectorAll('tbody tr'), row => {
                const rule = {};
                row.querySelectorAll('[data-field]').forEach(input => rule[input.dataset.field] = input.value);
                return rule;
            });
        });
        document.getElementById('cmdb-rules-json').value = JSON.stringify(output);
    });
})();
</script>
<?php
$html = ob_get_clean();
$style = (new CTag('style', true))->addItem('
main{min-width:0!important;width:auto!important}.cmdb-config{box-sizing:border-box;width:100%;max-width:100%;--el-primary:#409eff;--el-primary-light:#ecf5ff;--el-border:#dcdfe6;--el-border-light:#ebeef5;--el-text:#303133;--el-text-secondary:#606266;--el-muted:#909399;--el-success:#67c23a;--el-danger:#f56c6c;color:var(--el-text)}
.cmdb-info{display:flex;gap:12px;margin:0 0 18px;padding:13px 16px;border:1px solid #a0cfff;border-radius:4px;background:var(--el-primary-light);color:#337ecc}.cmdb-info__icon,.cmdb-alert__icon{display:inline-flex;flex:0 0 auto;align-items:center;justify-content:center;width:18px;height:18px;border:2px solid currentColor;border-radius:50%;font-weight:700;line-height:1}.cmdb-info__content p{margin:0 0 5px;line-height:1.5}.cmdb-info__content p:last-child{margin:0}
.cmdb-alert{display:flex;align-items:center;gap:10px;margin:0 0 18px;padding:12px 16px;border:1px solid;border-radius:4px}.cmdb-alert--success{border-color:#b3e19d;background:#f0f9eb;color:#529b2e}.cmdb-alert--error{border-color:#fab6b6;background:#fef0f0;color:#c45656}
.cmdb-rule-section{margin:0 0 18px;border:1px solid var(--el-border);border-radius:4px;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.06);overflow:hidden}.cmdb-card__header{box-sizing:border-box;display:flex;align-items:center;justify-content:space-between;gap:16px;min-width:0!important;width:100%!important;padding:14px 18px;border-bottom:1px solid var(--el-border-light)}.cmdb-card__title{display:flex;align-items:center;gap:9px}.cmdb-card__marker{width:4px;height:18px;border-radius:2px;background:var(--el-primary)}.cmdb-card__title h2{margin:0;font-size:16px;line-height:24px}.cmdb-count{padding:2px 8px;border-radius:10px;background:#f4f4f5;color:var(--el-muted);font-size:12px}
.cmdb-table-wrap{overflow-x:auto}.cmdb-table{min-width:850px;width:100%;table-layout:fixed;border-collapse:collapse}.cmdb-table th{height:42px;padding:0 14px;border-bottom:1px solid var(--el-border-light);background:#f5f7fa;color:var(--el-text-secondary);font-weight:500;text-align:left}.cmdb-table td{height:48px;padding:5px 14px;border-bottom:1px solid var(--el-border-light)}.cmdb-table tbody tr{transition:background-color .2s}.cmdb-table tbody tr:hover{background:#f5f7fa}.cmdb-table tbody tr:last-child td{border-bottom:0}.cmdb-table th:nth-child(1){width:14%}.cmdb-table th:nth-child(2){width:14%}.cmdb-table th:nth-child(3){width:32%}.cmdb-table th:nth-child(4){width:16%}.cmdb-table th:nth-child(5){width:24%}.cmdb-table input,.cmdb-table select{box-sizing:border-box;width:100%;height:32px;padding:0 10px;border:1px solid var(--el-border);border-radius:4px;background:#fff;color:var(--el-text);outline:0;transition:border-color .2s,box-shadow .2s}.cmdb-table select{padding-right:24px}.cmdb-table input:hover,.cmdb-table select:hover{border-color:#c0c4cc}.cmdb-table input:focus,.cmdb-table select:focus{border-color:var(--el-primary);box-shadow:0 0 0 1px var(--el-primary)}.cmdb-table select:disabled{background:#f5f7fa;color:#a8abb2;cursor:not-allowed}
.cmdb-row-actions{white-space:nowrap}.cmdb-icon-button{display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;margin-right:7px;padding:0;border:1px solid #a0cfff;border-radius:4px;background:var(--el-primary-light);color:var(--el-primary);font-size:16px;line-height:1;cursor:pointer;transition:all .2s}.cmdb-icon-button:hover{border-color:var(--el-primary);background:var(--el-primary);color:#fff}.cmdb-icon-button--danger{border-color:#fab6b6;background:#fef0f0;color:var(--el-danger)}.cmdb-icon-button--danger:hover{border-color:var(--el-danger);background:var(--el-danger);color:#fff}
.cmdb-button{box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;gap:5px;min-height:32px;padding:7px 14px;border:1px solid var(--el-border);border-radius:4px;background:#fff;color:var(--el-text-secondary);font-weight:400;text-decoration:none;cursor:pointer;transition:all .2s}.cmdb-button:hover{border-color:#c6e2ff;background:var(--el-primary-light);color:var(--el-primary);text-decoration:none}.cmdb-button--primary{border-color:var(--el-primary);background:var(--el-primary);color:#fff}.cmdb-button--primary:hover{border-color:#79bbff;background:#79bbff;color:#fff}.cmdb-button__icon{font-size:16px}.cmdb-button--save{min-width:112px}
.cmdb-empty{display:none;box-sizing:border-box;min-width:850px;padding:34px;text-align:center;color:var(--el-muted)}.cmdb-empty__icon{display:flex;align-items:center;justify-content:center;width:34px;height:34px;margin:0 auto 9px;border-radius:50%;background:#f4f4f5;font-size:21px}.cmdb-empty strong,.cmdb-empty span{display:block;margin-top:4px}.cmdb-rule-section.is-empty .cmdb-table thead,.cmdb-rule-section.is-empty .cmdb-table tbody{display:none}.cmdb-rule-section.is-empty .cmdb-table{display:none}.cmdb-rule-section.is-empty .cmdb-empty{display:block}
.cmdb-actions{position:sticky;bottom:0;z-index:2;display:flex;justify-content:flex-end;gap:10px;margin-top:6px;padding:14px 18px;border:1px solid var(--el-border);border-radius:4px;background:rgba(255,255,255,.96);box-shadow:0 -2px 8px rgba(0,0,0,.06)}
@media(max-width:900px){.cmdb-card__header{align-items:flex-start}.cmdb-info{font-size:12px}.cmdb-actions{position:static}.cmdb-table-wrap{overflow-x:auto}}
');
ViewRenderer::render(LanguageManager::t('Item Configuration'), $style, (new CDiv())->addItem(new CObject($html)));
