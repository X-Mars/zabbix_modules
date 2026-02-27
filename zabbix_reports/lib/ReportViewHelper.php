<?php

namespace Modules\ZabbixReports\Lib;

require_once __DIR__ . '/LanguageManager.php';

/**
 * 报表视图助手 - 集中管理所有报表视图的公共组件
 * 
 * 提供：统一 CSS、摘要卡片、表格构建器、JS 交互增强
 */
class ReportViewHelper {

    /**
     * 获取统一的报表 CSS 样式（现代仪表盘风格）
     */
    public static function getStyleTag(): \CTag {
        return new \CTag('style', true, '
/* ===== 报表全局变量 ===== */
:root {
    --rpt-primary: #4361ee;
    --rpt-primary-light: #eef1ff;
    --rpt-success: #2ec4b6;
    --rpt-success-light: #e8f8f5;
    --rpt-warning: #ff9f1c;
    --rpt-warning-light: #fff8ee;
    --rpt-danger: #e63946;
    --rpt-danger-light: #fdebed;
    --rpt-info: #457b9d;
    --rpt-info-light: #eaf2f7;
    --rpt-text: #2b2d42;
    --rpt-text-secondary: #6c757d;
    --rpt-bg: #f8f9fc;
    --rpt-card-bg: #ffffff;
    --rpt-border: #e9ecef;
    --rpt-shadow: 0 2px 12px rgba(0,0,0,0.06);
    --rpt-shadow-hover: 0 4px 20px rgba(0,0,0,0.1);
    --rpt-radius: 10px;
    --rpt-radius-sm: 6px;
}

/* ===== 报表容器 ===== */
.rpt-container {
    padding: 24px;
    width: 100%;
    margin: 0 auto;
    background: var(--rpt-bg);
    min-height: calc(100vh - 120px);
}

/* ===== 报表头部 ===== */
.rpt-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.rpt-title {
    font-size: 22px;
    font-weight: 700;
    color: var(--rpt-text);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.rpt-title-icon {
    font-size: 26px;
    line-height: 1;
}

.rpt-subtitle {
    font-size: 14px;
    color: var(--rpt-text-secondary);
    font-weight: 400;
    margin-left: 4px;
}

/* ===== 操作按钮组 ===== */
.rpt-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.rpt-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 18px;
    border-radius: var(--rpt-radius-sm);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    text-decoration: none;
    line-height: 1.4;
}

.rpt-btn-primary {
    background: var(--rpt-primary);
    color: #fff;
    border-color: var(--rpt-primary);
}

.rpt-btn-primary:hover {
    background: #3651d4;
    box-shadow: 0 4px 12px rgba(67, 97, 238, 0.35);
    transform: translateY(-1px);
}

.rpt-btn-outline {
    background: var(--rpt-card-bg);
    color: var(--rpt-text-secondary);
    border-color: var(--rpt-border);
}

.rpt-btn-outline:hover {
    background: var(--rpt-bg);
    color: var(--rpt-text);
    border-color: #ced4da;
}

.rpt-btn[disabled] {
    opacity: 0.5;
    cursor: not-allowed;
    transform: none !important;
    box-shadow: none !important;
}

.rpt-btn-icon {
    font-size: 15px;
    line-height: 1;
}

/* ===== 统计摘要卡片 ===== */
.rpt-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.rpt-stat-card {
    background: var(--rpt-card-bg);
    border-radius: var(--rpt-radius);
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: var(--rpt-shadow);
    transition: all 0.25s ease;
    border: 1px solid var(--rpt-border);
}

.rpt-stat-card:hover {
    box-shadow: var(--rpt-shadow-hover);
    transform: translateY(-2px);
}

.rpt-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.rpt-stat-icon.danger { background: var(--rpt-danger-light); color: var(--rpt-danger); }
.rpt-stat-icon.success { background: var(--rpt-success-light); color: var(--rpt-success); }
.rpt-stat-icon.warning { background: var(--rpt-warning-light); color: var(--rpt-warning); }
.rpt-stat-icon.info { background: var(--rpt-info-light); color: var(--rpt-info); }
.rpt-stat-icon.primary { background: var(--rpt-primary-light); color: var(--rpt-primary); }

.rpt-stat-body {
    flex: 1;
    min-width: 0;
}

.rpt-stat-value {
    font-size: 24px;
    font-weight: 700;
    color: var(--rpt-text);
    line-height: 1.2;
}

.rpt-stat-label {
    font-size: 12px;
    color: var(--rpt-text-secondary);
    margin-top: 2px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 500;
}

/* ===== 区域卡片 ===== */
.rpt-section {
    background: var(--rpt-card-bg);
    border-radius: var(--rpt-radius);
    box-shadow: var(--rpt-shadow);
    margin-bottom: 20px;
    border: 1px solid var(--rpt-border);
    overflow: hidden;
}

.rpt-section-header {
    padding: 16px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid var(--rpt-border);
    background: linear-gradient(135deg, var(--rpt-card-bg), var(--rpt-bg));
}

.rpt-section-title {
    font-size: 15px;
    font-weight: 700;
    color: var(--rpt-text);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.rpt-section-badge {
    font-size: 11px;
    font-weight: 600;
    padding: 3px 10px;
    border-radius: 20px;
    background: var(--rpt-primary-light);
    color: var(--rpt-primary);
}

.rpt-section-tools {
    display: flex;
    align-items: center;
    gap: 8px;
}

.rpt-search-input {
    padding: 6px 12px 6px 32px;
    border: 1px solid var(--rpt-border);
    border-radius: var(--rpt-radius-sm);
    font-size: 12px;
    width: 200px;
    background: var(--rpt-card-bg) url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'14\' height=\'14\' viewBox=\'0 0 24 24\' fill=\'none\' stroke=\'%236c757d\' stroke-width=\'2\'%3E%3Ccircle cx=\'11\' cy=\'11\' r=\'8\'/%3E%3Cline x1=\'21\' y1=\'21\' x2=\'16.65\' y2=\'16.65\'/%3E%3C/svg%3E") 10px center no-repeat;
    transition: border-color 0.2s;
}

.rpt-search-input:focus {
    border-color: var(--rpt-primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
}

.rpt-section-body {
    padding: 0;
}

/* ===== 表格样式 ===== */
.rpt-table {
    width: 100%;
    border-collapse: collapse;
}

.rpt-table thead th {
    background: var(--rpt-bg);
    color: var(--rpt-text-secondary);
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 12px 16px;
    text-align: left;
    border-bottom: 2px solid var(--rpt-border);
    white-space: nowrap;
    cursor: pointer;
    user-select: none;
    position: relative;
}

.rpt-table thead th:hover {
    color: var(--rpt-primary);
}

.rpt-table thead th .sort-icon {
    display: inline-block;
    margin-left: 4px;
    font-size: 10px;
    opacity: 0.3;
    transition: opacity 0.2s;
}

.rpt-table thead th:hover .sort-icon,
.rpt-table thead th.sorted .sort-icon {
    opacity: 1;
}

.rpt-table tbody td {
    padding: 11px 16px;
    border-bottom: 1px solid var(--rpt-border);
    font-size: 13px;
    color: var(--rpt-text);
    vertical-align: middle;
}

.rpt-table tbody tr {
    transition: background-color 0.15s;
}

.rpt-table tbody tr:hover {
    background-color: var(--rpt-primary-light);
}

.rpt-table tbody tr:last-child td {
    border-bottom: none;
}

.rpt-table .rpt-no-data td {
    text-align: center;
    padding: 40px 16px;
    color: var(--rpt-text-secondary);
    font-size: 14px;
}

/* ===== 排名序号 ===== */
.rpt-rank {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    font-size: 11px;
    font-weight: 700;
}

.rpt-rank-1 { background: linear-gradient(135deg, #FFD700, #FFA000); color: #fff; }
.rpt-rank-2 { background: linear-gradient(135deg, #C0C0C0, #9E9E9E); color: #fff; }
.rpt-rank-3 { background: linear-gradient(135deg, #CD7F32, #A0522D); color: #fff; }
.rpt-rank-n { background: var(--rpt-bg); color: var(--rpt-text-secondary); }

/* ===== 进度条 ===== */
.rpt-progress-cell {
    display: flex;
    align-items: center;
    gap: 10px;
}

.rpt-progress-bar {
    flex: 1;
    height: 8px;
    background: var(--rpt-border);
    border-radius: 4px;
    overflow: hidden;
    min-width: 80px;
}

.rpt-progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.6s ease;
}

.rpt-progress-fill.level-low { background: linear-gradient(90deg, var(--rpt-success), #38d9a9); }
.rpt-progress-fill.level-mid { background: linear-gradient(90deg, var(--rpt-warning), #ffc078); }
.rpt-progress-fill.level-high { background: linear-gradient(90deg, var(--rpt-danger), #ff8787); }

.rpt-progress-text {
    font-size: 13px;
    font-weight: 600;
    min-width: 52px;
    text-align: right;
}

.rpt-progress-text.level-low { color: var(--rpt-success); }
.rpt-progress-text.level-mid { color: var(--rpt-warning); }
.rpt-progress-text.level-high { color: var(--rpt-danger); }

/* ===== 严重等级颜色标签 ===== */
.rpt-severity-tag {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.rpt-severity-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}

/* 严重等级配色 */
.rpt-sev-not-classified { background: #97AAB3; color: #fff; }
.rpt-sev-information   { background: #7499FF; color: #fff; }
.rpt-sev-warning       { background: #FFC859; color: #333; }
.rpt-sev-average       { background: #FFA059; color: #fff; }
.rpt-sev-high          { background: #E97659; color: #fff; }
.rpt-sev-disaster      { background: #E45959; color: #fff; }

/* ===== 时间标签 ===== */
.rpt-time {
    font-family: "SF Mono", "Menlo", "Monaco", monospace;
    font-size: 12px;
    color: var(--rpt-text-secondary);
}

.rpt-time-recovered {
    color: var(--rpt-success);
}

.rpt-host-name {
    font-weight: 600;
    color: var(--rpt-text);
}

/* ===== 双列布局 ===== */
.rpt-grid-2 {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

/* ===== 空状态 ===== */
.rpt-empty {
    text-align: center;
    padding: 48px 20px;
    color: var(--rpt-text-secondary);
}

.rpt-empty-icon {
    font-size: 40px;
    opacity: 0.4;
    margin-bottom: 12px;
}

.rpt-empty-text {
    font-size: 14px;
}

/* ===== 日期表单区 ===== */
.rpt-date-form {
    background: var(--rpt-card-bg);
    padding: 24px;
    border-radius: var(--rpt-radius);
    margin-bottom: 24px;
    box-shadow: var(--rpt-shadow);
    border: 1px solid var(--rpt-border);
    display: flex;
    align-items: flex-end;
    gap: 20px;
    flex-wrap: wrap;
    justify-content: center;
}

.rpt-form-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.rpt-form-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--rpt-text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.rpt-form-input {
    padding: 9px 14px;
    border: 1px solid var(--rpt-border);
    border-radius: var(--rpt-radius-sm);
    font-size: 14px;
    color: var(--rpt-text);
    transition: border-color 0.2s;
}

.rpt-form-input:focus {
    border-color: var(--rpt-primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
}

/* ===== 错误提示 ===== */
.rpt-error {
    background: var(--rpt-danger-light);
    border: 1px solid rgba(230, 57, 70, 0.2);
    border-radius: var(--rpt-radius-sm);
    padding: 12px 16px;
    margin-bottom: 20px;
    color: var(--rpt-danger);
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* ===== 页脚信息 ===== */
.rpt-footer {
    text-align: center;
    padding: 16px;
    font-size: 12px;
    color: var(--rpt-text-secondary);
    border-top: 1px solid var(--rpt-border);
    margin-top: 20px;
}

/* ===== 动画效果 ===== */
@keyframes rptFadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}

.rpt-animate {
    animation: rptFadeIn 0.4s ease forwards;
}

.rpt-animate-delay-1 { animation-delay: 0.05s; opacity: 0; }
.rpt-animate-delay-2 { animation-delay: 0.1s; opacity: 0; }
.rpt-animate-delay-3 { animation-delay: 0.15s; opacity: 0; }
.rpt-animate-delay-4 { animation-delay: 0.2s; opacity: 0; }

/* ===== 响应式 ===== */
@media (max-width: 1200px) {
    .rpt-grid-2 {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .rpt-container {
        padding: 12px;
    }
    .rpt-header {
        flex-direction: column;
        align-items: flex-start;
    }
    .rpt-stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    .rpt-table thead th,
    .rpt-table tbody td {
        padding: 8px 10px;
        font-size: 12px;
    }
    .rpt-search-input {
        width: 140px;
    }
}

@media (max-width: 480px) {
    .rpt-stats-grid {
        grid-template-columns: 1fr;
    }
}

/* ===== 打印优化 ===== */
@media print {
    .rpt-container {
        padding: 0;
        background: #fff;
    }
    .rpt-actions,
    .rpt-search-input,
    .rpt-section-tools {
        display: none !important;
    }
    .rpt-section {
        box-shadow: none;
        break-inside: avoid;
        border: 1px solid #ddd;
    }
    .rpt-stat-card {
        box-shadow: none;
        border: 1px solid #ddd;
    }
    .rpt-stat-card:hover,
    .rpt-section:hover {
        transform: none;
        box-shadow: none;
    }
}
');
    }

    /**
     * 获取报表 JavaScript 增强代码
     */
    public static function getScriptTag(): string {
        $i18n = json_encode([
            'noResults' => LanguageManager::t('No data available'),
            'rows' => LanguageManager::t('rows'),
            'of' => LanguageManager::t('of'),
        ], JSON_UNESCAPED_UNICODE);

        return '<script>
(function() {
    "use strict";

    // ===== 表格搜索 =====
    document.querySelectorAll(".rpt-search-input").forEach(function(input) {
        var tableId = input.getAttribute("data-table");
        input.addEventListener("input", function() {
            filterTable(tableId, this.value);
        });
    });

    function filterTable(tableId, text) {
        var table = document.getElementById(tableId);
        if (!table) return;
        var rows = table.querySelectorAll("tbody tr:not(.rpt-no-data)");
        var lower = text.toLowerCase();
        var visibleCount = 0;
        rows.forEach(function(row) {
            var content = row.textContent.toLowerCase();
            var match = !text || content.indexOf(lower) !== -1;
            row.style.display = match ? "" : "none";
            if (match) visibleCount++;
        });
        // show/hide no-data row
        var noDataRow = table.querySelector("tbody tr.rpt-no-data");
        if (noDataRow) {
            noDataRow.style.display = visibleCount === 0 && text ? "" : "none";
        }
    }

    // ===== 表格排序 =====
    document.querySelectorAll(".rpt-table thead th[data-sortable]").forEach(function(th) {
        th.addEventListener("click", function() {
            var table = th.closest("table");
            var idx = Array.from(th.parentNode.children).indexOf(th);
            var tbody = table.querySelector("tbody");
            var rows = Array.from(tbody.querySelectorAll("tr:not(.rpt-no-data)"));
            var asc = !th.classList.contains("sorted-asc");

            // reset other headers
            th.parentNode.querySelectorAll("th").forEach(function(h) {
                h.classList.remove("sorted", "sorted-asc", "sorted-desc");
                var icon = h.querySelector(".sort-icon");
                if (icon) icon.textContent = "\u2195";
            });

            th.classList.add("sorted", asc ? "sorted-asc" : "sorted-desc");
            var icon = th.querySelector(".sort-icon");
            if (icon) icon.textContent = asc ? "\u2191" : "\u2193";

            rows.sort(function(a, b) {
                var aText = a.children[idx] ? a.children[idx].getAttribute("data-sort-value") || a.children[idx].textContent.trim() : "";
                var bText = b.children[idx] ? b.children[idx].getAttribute("data-sort-value") || b.children[idx].textContent.trim() : "";
                var aNum = parseFloat(aText);
                var bNum = parseFloat(bText);
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return asc ? aNum - bNum : bNum - aNum;
                }
                return asc ? aText.localeCompare(bText) : bText.localeCompare(aText);
            });

            rows.forEach(function(r) { tbody.appendChild(r); });
        });
    });

    // ===== 数字动画 =====
    document.querySelectorAll(".rpt-stat-value[data-count]").forEach(function(el) {
        var target = parseInt(el.getAttribute("data-count")) || 0;
        if (target === 0) { el.textContent = "0"; return; }
        var start = 0;
        var duration = 600;
        var startTime = null;
        function step(t) {
            if (!startTime) startTime = t;
            var progress = Math.min((t - startTime) / duration, 1);
            var ease = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.floor(start + (target - start) * ease);
            if (progress < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    });

})();
</script>';
    }

    /**
     * 构建统计摘要卡片组
     */
    public static function buildStatsGrid(array $data): \CDiv {
        $grid = (new \CDiv())->addClass('rpt-stats-grid');

        // 问题数
        $grid->addItem(self::buildStatCard(
            "\u{26A0}", 'danger',
            $data['problem_count'] ?? 0,
            LanguageManager::t('Problem Count'),
            'rpt-animate rpt-animate-delay-1'
        ));

        // 已解决数
        $grid->addItem(self::buildStatCard(
            "\u{2705}", 'success',
            $data['resolved_count'] ?? 0,
            LanguageManager::t('Resolved Count'),
            'rpt-animate rpt-animate-delay-2'
        ));

        // 主机组数
        $groupCount = 0;
        if (!empty($data['hosts_by_group'])) {
            $groupCount = count($data['hosts_by_group']);
        }
        $grid->addItem(self::buildStatCard(
            "\u{1F5C2}", 'info',
            $groupCount,
            LanguageManager::t('Host Groups'),
            'rpt-animate rpt-animate-delay-3'
        ));

        // 主机数
        $hostCount = 0;
        if (!empty($data['hosts_by_group'])) {
            foreach ($data['hosts_by_group'] as $hosts) {
                $hostCount += count($hosts);
            }
        }
        $grid->addItem(self::buildStatCard(
            "\u{1F5A5}", 'primary',
            $hostCount,
            LanguageManager::t('Total Hosts'),
            'rpt-animate rpt-animate-delay-4'
        ));

        return $grid;
    }

    /**
     * 构建单个统计卡片
     */
    private static function buildStatCard(string $icon, string $color, $value, string $label, string $animClass = ''): \CDiv {
        $card = (new \CDiv())->addClass('rpt-stat-card');
        if ($animClass) {
            $card->addClass($animClass);
        }
        $card->addItem(
            (new \CDiv($icon))->addClass('rpt-stat-icon ' . $color)
        );
        $card->addItem(
            (new \CDiv())
                ->addClass('rpt-stat-body')
                ->addItem(
                    (new \CDiv((string)$value))
                        ->addClass('rpt-stat-value')
                        ->setAttribute('data-count', (int)$value)
                )
                ->addItem(
                    (new \CDiv($label))->addClass('rpt-stat-label')
                )
        );
        return $card;
    }

    /**
     * 严重等级名称和CSS类映射
     */
    private static function getSeverityInfo(int $severity): array {
        $map = [
            0 => ['label' => LanguageManager::t('Not classified'), 'class' => 'rpt-sev-not-classified'],
            1 => ['label' => LanguageManager::t('Information'),    'class' => 'rpt-sev-information'],
            2 => ['label' => LanguageManager::t('Warning'),        'class' => 'rpt-sev-warning'],
            3 => ['label' => LanguageManager::t('Average'),        'class' => 'rpt-sev-average'],
            4 => ['label' => LanguageManager::t('High'),           'class' => 'rpt-sev-high'],
            5 => ['label' => LanguageManager::t('Disaster'),       'class' => 'rpt-sev-disaster'],
        ];
        return $map[$severity] ?? $map[0];
    }

    /**
     * 构建告警信息区域（含搜索）
     */
    public static function buildAlertSection(array $alerts, int $limit = 50): \CDiv {
        $section = (new \CDiv())->addClass('rpt-section rpt-animate');
        $alertCount = count($alerts);

        // 头部
        $header = (new \CDiv())->addClass('rpt-section-header');
        $title = (new \CTag('h3', true))
            ->addClass('rpt-section-title')
            ->addItem("\u{1F514} " . LanguageManager::t('Alert Information'));
        $badge = (new \CSpan($alertCount > $limit ? $limit . '+' : (string)$alertCount))
            ->addClass('rpt-section-badge');
        $title->addItem($badge);
        $header->addItem($title);

        $tools = (new \CDiv())->addClass('rpt-section-tools');
        $tools->addItem(
            (new \CTag('input', false))
                ->setAttribute('type', 'text')
                ->setAttribute('class', 'rpt-search-input')
                ->setAttribute('placeholder', LanguageManager::t('Search...'))
                ->setAttribute('data-table', 'alert-table')
        );
        $header->addItem($tools);
        $section->addItem($header);

        // 表格
        $body = (new \CDiv())->addClass('rpt-section-body');
        $table = (new \CTag('table', true))
            ->addClass('rpt-table')
            ->setAttribute('id', 'alert-table');

        // 表头
        $thead = new \CTag('thead', true);
        $headRow = new \CTag('tr', true);
        $headRow->addItem(new \CTag('th', true, '#'));
        $columns = [
            LanguageManager::t('Host Name'),
            LanguageManager::t('Severity'),
            LanguageManager::t('Alert Name'),
            LanguageManager::t('Alert Time'),
            LanguageManager::t('Recovery Time')
        ];
        foreach ($columns as $col) {
            $th = (new \CTag('th', true, $col))
                ->setAttribute('data-sortable', '1');
            $th->addItem((new \CSpan("\u{2195}"))->addClass('sort-icon'));
            $headRow->addItem($th);
        }
        $thead->addItem($headRow);
        $table->addItem($thead);

        // 表体
        $tbody = new \CTag('tbody', true);
        if (!empty($alerts)) {
            $count = 0;
            foreach ($alerts as $alert) {
                if ($count >= $limit) break;
                $count++;
                $row = new \CTag('tr', true);
                $row->addItem(
                    (new \CTag('td', true))
                        ->addItem((new \CSpan((string)$count))->addClass('rpt-rank rpt-rank-n'))
                );
                $row->addItem(
                    (new \CTag('td', true))
                        ->addItem((new \CSpan($alert['host']))->addClass('rpt-host-name'))
                );
                // 严重等级
                $severity = (int)($alert['severity'] ?? 0);
                $sevInfo = self::getSeverityInfo($severity);
                $row->addItem(
                    (new \CTag('td', true))
                        ->addItem(
                            (new \CSpan($sevInfo['label']))
                                ->addClass('rpt-severity-tag ' . $sevInfo['class'])
                        )
                        ->setAttribute('data-sort-value', $severity)
                );
                $row->addItem(new \CTag('td', true, $alert['alert']));
                $row->addItem(
                    (new \CTag('td', true))
                        ->addItem((new \CSpan($alert['time']))->addClass('rpt-time'))
                        ->setAttribute('data-sort-value', strtotime($alert['time'] ?? '') ?: 0)
                );
                $recoveryTime = $alert['recovery_time'] ?: '-';
                $recoveryClass = $recoveryTime !== '-' ? 'rpt-time rpt-time-recovered' : 'rpt-time';
                $row->addItem(
                    (new \CTag('td', true))
                        ->addItem((new \CSpan($recoveryTime))->addClass($recoveryClass))
                        ->setAttribute('data-sort-value', $recoveryTime !== '-' ? (strtotime($recoveryTime) ?: 0) : 0)
                );
                $tbody->addItem($row);
            }
        }
        // 空行（隐藏/显示由JS控制）
        $noDataRow = (new \CTag('tr', true))
            ->addClass('rpt-no-data')
            ->addItem(
                (new \CTag('td', true, LanguageManager::t('No alerts found')))
                    ->setAttribute('colspan', '6')
            );
        if (!empty($alerts)) {
            $noDataRow->setAttribute('style', 'display:none');
        }
        $tbody->addItem($noDataRow);
        $table->addItem($tbody);
        $body->addItem($table);
        $section->addItem($body);

        return $section;
    }

    /**
     * 构建主机群组信息区域
     */
    public static function buildHostGroupSection(array $hostsByGroup, int $limit = 100): \CDiv {
        $section = (new \CDiv())->addClass('rpt-section rpt-animate');
        $totalHosts = 0;
        foreach ($hostsByGroup as $hosts) {
            $totalHosts += count($hosts);
        }

        // 头部
        $header = (new \CDiv())->addClass('rpt-section-header');
        $title = (new \CTag('h3', true))
            ->addClass('rpt-section-title')
            ->addItem("\u{1F4CB} " . LanguageManager::t('Host Group Information'));
        $badge = (new \CSpan((string)$totalHosts))->addClass('rpt-section-badge');
        $title->addItem($badge);
        $header->addItem($title);

        $tools = (new \CDiv())->addClass('rpt-section-tools');
        $tools->addItem(
            (new \CTag('input', false))
                ->setAttribute('type', 'text')
                ->setAttribute('class', 'rpt-search-input')
                ->setAttribute('placeholder', LanguageManager::t('Search...'))
                ->setAttribute('data-table', 'group-table')
        );
        $header->addItem($tools);
        $section->addItem($header);

        // 表格
        $body = (new \CDiv())->addClass('rpt-section-body');
        $table = (new \CTag('table', true))
            ->addClass('rpt-table')
            ->setAttribute('id', 'group-table');

        // 表头
        $thead = new \CTag('thead', true);
        $headRow = new \CTag('tr', true);
        $headRow->addItem(new \CTag('th', true, '#'));
        $columns = [
            LanguageManager::t('Host Group'),
            LanguageManager::t('Host Name'),
            LanguageManager::t('CPU Usage'),
            LanguageManager::t('CPU Total'),
            LanguageManager::t('Memory Usage'),
            LanguageManager::t('Memory Total')
        ];
        foreach ($columns as $col) {
            $th = (new \CTag('th', true, $col))->setAttribute('data-sortable', '1');
            $th->addItem((new \CSpan("\u{2195}"))->addClass('sort-icon'));
            $headRow->addItem($th);
        }
        $thead->addItem($headRow);
        $table->addItem($thead);

        // 表体
        $tbody = new \CTag('tbody', true);
        if (!empty($hostsByGroup)) {
            $count = 0;
            foreach ($hostsByGroup as $groupName => $hosts) {
                foreach ($hosts as $host) {
                    if ($count >= $limit) break 2;
                    $count++;
                    $row = new \CTag('tr', true);
                    $row->addItem(
                        (new \CTag('td', true))
                            ->addItem((new \CSpan((string)$count))->addClass('rpt-rank rpt-rank-n'))
                    );
                    $row->addItem(new \CTag('td', true, $groupName));
                    $row->addItem(
                        (new \CTag('td', true))
                            ->addItem((new \CSpan($host['name']))->addClass('rpt-host-name'))
                    );
                    $row->addItem(new \CTag('td', true, $host['cpu_usage']));
                    $row->addItem(new \CTag('td', true, $host['cpu_total']));
                    $row->addItem(new \CTag('td', true, $host['mem_usage']));
                    $row->addItem(new \CTag('td', true, $host['mem_total']));
                    $tbody->addItem($row);
                }
            }
        }
        $noDataRow = (new \CTag('tr', true))
            ->addClass('rpt-no-data')
            ->addItem(
                (new \CTag('td', true, LanguageManager::t('No host data available')))
                    ->setAttribute('colspan', '7')
            );
        if (!empty($hostsByGroup)) {
            $noDataRow->setAttribute('style', 'display:none');
        }
        $tbody->addItem($noDataRow);
        $table->addItem($tbody);
        $body->addItem($table);
        $section->addItem($body);

        return $section;
    }

    /**
     * 构建 Top CPU / Memory 双列区域（带排名和进度条）
     */
    public static function buildTopResourceSection(array $data): \CDiv {
        $grid = (new \CDiv())->addClass('rpt-grid-2');

        // CPU 区域
        $grid->addItem(self::buildTopTable(
            'cpu-table',
            "\u{1F4BB} " . LanguageManager::t('CPU Information (TOP 5)'),
            $data['top_cpu_hosts'] ?? [],
            $data['cpu_total'] ?? [],
            'cpu'
        ));

        // Memory 区域
        $grid->addItem(self::buildTopTable(
            'mem-table',
            "\u{1F4BE} " . LanguageManager::t('Memory Information (TOP 5)'),
            $data['top_mem_hosts'] ?? [],
            $data['mem_total'] ?? [],
            'mem'
        ));

        return $grid;
    }

    /**
     * 构建 Top 资源表格（带排名和进度条）
     */
    private static function buildTopTable(string $tableId, string $title, array $topHosts, array $totals, string $type): \CDiv {
        $section = (new \CDiv())->addClass('rpt-section rpt-animate');

        // 头部
        $header = (new \CDiv())->addClass('rpt-section-header');
        $header->addItem(
            (new \CTag('h3', true))->addClass('rpt-section-title')->addItem($title)
        );
        $section->addItem($header);

        // 表格
        $body = (new \CDiv())->addClass('rpt-section-body');
        $table = (new \CTag('table', true))
            ->addClass('rpt-table')
            ->setAttribute('id', $tableId);

        // 表头
        $thead = new \CTag('thead', true);
        $headRow = new \CTag('tr', true);
        $headRow->addItem(new \CTag('th', true, '#'));

        $headRow->addItem(
            (new \CTag('th', true, LanguageManager::t('Host Name')))
                ->setAttribute('data-sortable', '1')
                ->addItem((new \CSpan("\u{2195}"))->addClass('sort-icon'))
        );

        $usageLabel = $type === 'cpu' 
            ? LanguageManager::t('CPU Usage')
            : LanguageManager::t('Memory Usage');
        $headRow->addItem(
            (new \CTag('th', true, $usageLabel))
                ->setAttribute('data-sortable', '1')
                ->addItem((new \CSpan("\u{2195}"))->addClass('sort-icon'))
        );

        $totalLabel = $type === 'cpu' 
            ? LanguageManager::t('CPU Total')
            : LanguageManager::t('Memory Total');
        $headRow->addItem(new \CTag('th', true, $totalLabel));

        $thead->addItem($headRow);
        $table->addItem($thead);

        // 表体
        $tbody = new \CTag('tbody', true);
        if (!empty($topHosts)) {
            $rank = 0;
            foreach ($topHosts as $host => $usage) {
                if ($rank >= 5) break;
                $rank++;
                $row = new \CTag('tr', true);

                // 排名
                $rankClass = $rank <= 3 ? 'rpt-rank rpt-rank-' . $rank : 'rpt-rank rpt-rank-n';
                $row->addItem(
                    (new \CTag('td', true))
                        ->addItem((new \CSpan((string)$rank))->addClass($rankClass))
                );

                // 主机名
                $row->addItem(
                    (new \CTag('td', true))
                        ->addItem((new \CSpan($host))->addClass('rpt-host-name'))
                );

                // 使用率（带进度条）
                $usageVal = number_format($usage, 2);
                $level = $usage < 60 ? 'low' : ($usage < 85 ? 'mid' : 'high');
                $progressCell = (new \CTag('td', true))
                    ->setAttribute('data-sort-value', $usageVal);

                $progressDiv = (new \CDiv())
                    ->addClass('rpt-progress-cell');

                $barOuter = (new \CDiv())->addClass('rpt-progress-bar');
                $barInner = (new \CDiv())
                    ->addClass('rpt-progress-fill level-' . $level)
                    ->setAttribute('style', 'width:' . min($usage, 100) . '%');
                $barOuter->addItem($barInner);
                $progressDiv->addItem($barOuter);

                $progressDiv->addItem(
                    (new \CSpan($usageVal . '%'))->addClass('rpt-progress-text level-' . $level)
                );
                $progressCell->addItem($progressDiv);
                $row->addItem($progressCell);

                // 总量
                if ($type === 'mem') {
                    $totalVal = isset($totals[$host]) 
                        ? number_format($totals[$host] / (1024*1024*1024), 2) . ' GB' 
                        : 'N/A';
                } else {
                    $totalVal = $totals[$host] ?? 'N/A';
                }
                $row->addItem(new \CTag('td', true, $totalVal));

                $tbody->addItem($row);
            }
        }

        $noDataRow = (new \CTag('tr', true))
            ->addClass('rpt-no-data')
            ->addItem(
                (new \CTag('td', true, LanguageManager::t('No data available')))
                    ->setAttribute('colspan', '4')
            );
        if (!empty($topHosts)) {
            $noDataRow->setAttribute('style', 'display:none');
        }
        $tbody->addItem($noDataRow);

        $table->addItem($tbody);
        $body->addItem($table);
        $section->addItem($body);

        return $section;
    }

    /**
     * 构建页面头部（标题 + 操作按钮）
     */
    public static function buildHeader(string $titleText, string $icon, string $exportAction, array $exportParams = []): \CDiv {
        $header = (new \CDiv())->addClass('rpt-header');

        $title = (new \CTag('h1', true))
            ->addClass('rpt-title')
            ->addItem((new \CSpan($icon))->addClass('rpt-title-icon'))
            ->addItem($titleText);
        $header->addItem($title);

        $actions = (new \CDiv())->addClass('rpt-actions');

        // 导出按钮
        $exportUrl = '?action=' . $exportAction . '&format=pdf';
        foreach ($exportParams as $k => $v) {
            $exportUrl .= '&' . urlencode($k) . '=' . urlencode($v);
        }
        $actions->addItem(
            (new \CButton('export_pdf', ''))
                ->addClass('rpt-btn rpt-btn-primary')
                ->setAttribute('onclick', 'javascript: window.open("' . $exportUrl . '", "_blank");')
                ->addItem((new \CSpan("\u{1F4E5}"))->addClass('rpt-btn-icon'))
                ->addItem(LanguageManager::t('Export PDF'))
        );

        // 邮件按钮
        $actions->addItem(
            (new \CButton('send_email', ''))
                ->addClass('rpt-btn rpt-btn-outline')
                ->setAttribute('disabled', 'disabled')
                ->setAttribute('title', LanguageManager::t('In Development'))
                ->addItem((new \CSpan("\u{2709}"))->addClass('rpt-btn-icon'))
                ->addItem(LanguageManager::t('Send Email'))
        );

        $header->addItem($actions);

        return $header;
    }

    /**
     * 构建 "问题主机" 信息行
     */
    public static function buildTopProblemHosts(array $topProblemHosts): \CDiv {
        if (empty($topProblemHosts)) {
            return new \CDiv();
        }

        $section = (new \CDiv())->addClass('rpt-section rpt-animate');
        $header = (new \CDiv())->addClass('rpt-section-header');
        $header->addItem(
            (new \CTag('h3', true))
                ->addClass('rpt-section-title')
                ->addItem("\u{1F525} " . LanguageManager::t('Top Problem Hosts'))
        );
        $section->addItem($header);

        $body = (new \CDiv())
            ->addClass('rpt-section-body')
            ->setAttribute('style', 'padding: 16px 20px;');

        $hostTags = [];
        foreach ($topProblemHosts as $host => $count) {
            $tag = (new \CSpan($host . ' (' . $count . ')'))
                ->addClass('rpt-severity-tag')
                ->setAttribute('style', 'background:var(--rpt-warning-light);color:var(--rpt-warning);margin:3px 6px 3px 0;');
            $hostTags[] = $tag;
        }
        $body->addItem($hostTags);
        $section->addItem($body);

        return $section;
    }
}
