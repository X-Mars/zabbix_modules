<?php
require_once dirname(__DIR__).'/lib/ItemConfig.php';
require_once dirname(__DIR__).'/lib/ViewRenderer.php';
use Modules\ZabbixCmdb\Lib\ItemConfig;
use Modules\ZabbixCmdb\Lib\ViewRenderer;

$escape = function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
ob_start();
?>
<form id="cmdb-config" method="post" action="zabbix.php?action=cmdb.config">
    <input type="hidden" name="config_token" value="<?= $escape($data['token']) ?>">
    <input type="hidden" name="rules_json" id="cmdb-rules-json">
    <p>规则从上到下匹配，首条命中后停止。可混合设置监控项名称和 Key 的优先顺序。</p>
    <p>模糊匹配不区分大小写，支持包含匹配和 * 通配符；精确匹配区分大小写。同一规则命中多个监控项时，取 itemid 最小的启用项。</p>
    <p>CPU 总量单位为核，内存总量单位为字节，使用率单位为 %。空闲率或可用率请选择“100 − 原值”。留空规则列表将停用该指标。</p>
    <?php if ($data['message']): ?><p role="status" class="msg-good"><?= $escape($data['message']) ?></p><?php endif ?>
    <?php if ($data['error']): ?><p role="alert" class="msg-bad"><?= $escape($data['error']) ?></p><?php endif ?>
    <?php foreach (ItemConfig::LABELS as $category => $label): ?>
    <section class="cmdb-rule-section" data-category="<?= $category ?>">
        <h2><?= $label ?></h2>
        <table class="list-table"><thead><tr><th>匹配字段</th><th>匹配方式</th><th>监控项名称 / Key</th><th>数值转换</th><th>顺序 / 操作</th></tr></thead><tbody></tbody></table>
        <button type="button" class="cmdb-add">添加规则</button>
    </section>
    <?php endforeach ?>
    <p><button type="submit">保存配置</button> <a href="zabbix.php?action=cmdb">返回主机列表</a></p>
</form>
<script>
(function () {
    const form = document.getElementById('cmdb-config');
    let rules = <?= json_encode($data['rules'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const submitted = <?= json_encode($data['submitted'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    if (submitted) { try { rules = JSON.parse(submitted); } catch (_) {} }
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
        select('field', {key_: 'Key', name: '监控项名称'}, rule.field || 'key_');
        select('match', {exact: '精确匹配', fuzzy: '模糊匹配'}, rule.match || 'fuzzy');
        const pattern = document.createElement('input');
        pattern.type = 'text'; pattern.required = true; pattern.maxLength = 255;
        pattern.dataset.field = 'pattern'; pattern.value = rule.pattern || '';
        pattern.setAttribute('aria-label', '监控项名称或 Key'); pattern.style.width = '95%';
        row.insertCell().append(pattern);
        const transform = select('transform', {none: '原值', subtract_from_100: '100 − 原值'}, rule.transform || 'none');
        if (!['cpu_usage', 'memory_usage'].includes(section.dataset.category)) {
            transform.value = 'none'; transform.disabled = true;
        }
        const actions = row.insertCell();
        [['上移', () => { if (row.previousElementSibling) body.insertBefore(row, row.previousElementSibling); }],
         ['下移', () => { if (row.nextElementSibling) body.insertBefore(row.nextElementSibling, row); }],
         ['删除', () => row.remove()]].forEach(([label, click]) => {
            const button = document.createElement('button'); button.type = 'button';
            button.textContent = label; button.addEventListener('click', click); actions.append(button, ' ');
        });
        body.append(row);
    }
    form.querySelectorAll('section').forEach(section => {
        const entries = rules && rules[section.dataset.category];
        (Array.isArray(entries) ? entries : []).forEach(rule => addRow(section, rule));
        section.querySelector('.cmdb-add').addEventListener('click', () => addRow(section));
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
$style = (new CTag('style', true))->addItem('.cmdb-rule-section{margin:24px 0}.cmdb-rule-section h2{margin-bottom:10px}.cmdb-rule-section td{padding:6px}.cmdb-rule-section th:nth-child(3){width:40%}.cmdb-add{margin-top:8px}');
ViewRenderer::render('CMDB 配置', $style, (new CDiv())->addItem(new CObject($html)));
