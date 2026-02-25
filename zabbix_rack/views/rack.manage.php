<?php
/**
 * 机柜管理页面 - 重构版
 * 功能：机房和机柜的增删改查管理
 */

// 引入依赖
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';

use Modules\ZabbixRack\Lib\LanguageManager;
use Modules\ZabbixRack\Lib\ViewRenderer;

// 获取数据
$lang = $data['lang'];
$rooms = $data['rooms'] ?? [];
$racks = $data['racks'] ?? [];

$pageTitle = LanguageManager::t('rack_manage');

// 计算统计数据
$totalRooms = count($rooms);
$totalRacks = count($racks);
$totalU = 0;
$usedU = 0;
foreach ($racks as $rack) {
    $totalU += (int)($rack['height'] ?? 42);
    $usedU += (int)($rack['used_u'] ?? 0);
}
// 计算使用率百分比
$usagePercent = $totalU > 0 ? round(($usedU / $totalU) * 100, 1) : 0;

// 构建机房->机柜数量映射
$roomRackCount = [];
foreach ($racks as $rack) {
    $roomId = $rack['room_id'] ?? '';
    if (!isset($roomRackCount[$roomId])) {
        $roomRackCount[$roomId] = 0;
    }
    $roomRackCount[$roomId]++;
}

// 为 JavaScript 准备数据（构建以 ID 为键的映射，便于快速查找）
$roomsMap = [];
foreach ($rooms as $room) {
    $roomsMap[$room['id']] = [
        'id' => $room['id'],
        'name' => $room['name'],
        'description' => $room['description'] ?? ''
    ];
}
$roomsJson = json_encode($roomsMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$racksMap = [];
foreach ($racks as $rack) {
    $racksMap[$rack['id']] = [
        'id' => $rack['id'],
        'name' => $rack['name'],
        'room_id' => $rack['room_id'] ?? '',
        'height' => $rack['height'] ?? 42,
        'description' => $rack['description'] ?? ''
    ];
}
$racksJson = json_encode($racksMap, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

// CSS 样式
$styleTag = new CTag('style', true, '
/* ==================== 自适应布局 ==================== */
.manage-wrapper {
    width: 100%;
    min-height: 100%;
    box-sizing: border-box;
}

/* ==================== 页面布局 ==================== */
.manage-container {
    padding: 20px;
    width: 100%;
    margin: 0 auto;
    max-width: 100%;
    box-sizing: border-box;
}

/* ==================== 统计卡片 ==================== */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
}

.stat-card.rooms { border-left: 4px solid #007bff; }
.stat-card.racks { border-left: 4px solid #28a745; }
.stat-card.capacity { border-left: 4px solid #ffc107; }
.stat-card.used { border-left: 4px solid #17a2b8; }

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-card.rooms .stat-icon { background: rgba(0,123,255,0.1); }
.stat-card.racks .stat-icon { background: rgba(40,167,69,0.1); }
.stat-card.capacity .stat-icon { background: rgba(255,193,7,0.1); }
.stat-card.used .stat-icon { background: rgba(23,162,184,0.1); }

.stat-content {
    flex: 1;
}

.stat-number {
    font-size: 28px;
    font-weight: 700;
    color: #212529;
    line-height: 1.2;
}

.stat-label {
    font-size: 13px;
    color: #6c757d;
    margin-top: 2px;
}

/* ==================== 管理区域 ==================== */
.manage-section {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    margin-bottom: 25px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,0.04);
}

.section-header {
    padding: 18px 24px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
}

.section-header h2 {
    margin: 0;
    font-size: 17px;
    font-weight: 600;
    color: #212529;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-body {
    padding: 0;
}

/* ==================== 按钮样式 ==================== */
.btn {
    padding: 10px 18px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-decoration: none;
}

.btn-primary {
    color: #fff;
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    box-shadow: 0 2px 8px rgba(0,123,255,0.3);
}

.btn-primary:hover {
    background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
    box-shadow: 0 4px 15px rgba(0,123,255,0.4);
    transform: translateY(-1px);
}

.btn-secondary {
    color: #495057;
    background: #fff;
    border: 1px solid #ced4da;
}

.btn-secondary:hover {
    background: #f8f9fa;
    border-color: #adb5bd;
}

.btn-success {
    color: #fff;
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
    box-shadow: 0 2px 8px rgba(40,167,69,0.3);
}

.btn-success:hover {
    background: linear-gradient(135deg, #1e7e34 0%, #155724 100%);
    transform: translateY(-1px);
}

.btn-danger {
    color: #fff;
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    box-shadow: 0 2px 6px rgba(220,53,69,0.3);
}

.btn-danger:hover {
    background: linear-gradient(135deg, #c82333 0%, #a71d2a 100%);
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
    border-radius: 6px;
}

.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    border-radius: 6px;
}

.action-btns {
    display: flex;
    gap: 8px;
}

/* ==================== 数据表格 ==================== */
.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table th {
    padding: 14px 20px;
    text-align: left;
    background: #f8f9fa;
    font-weight: 600;
    font-size: 13px;
    color: #495057;
    border-bottom: 2px solid #e9ecef;
    white-space: nowrap;
}

.data-table td {
    padding: 14px 20px;
    text-align: left;
    border-bottom: 1px solid #f0f0f0;
    font-size: 14px;
    color: #212529;
    vertical-align: middle;
}

.data-table tbody tr {
    transition: background 0.2s ease;
}

.data-table tbody tr:hover {
    background: #f8f9fa;
}

.data-table tbody tr:last-child td {
    border-bottom: none;
}

/* 表格单元格内容 */
.cell-main {
    font-weight: 600;
    color: #212529;
}

.cell-sub {
    font-size: 12px;
    color: #6c757d;
    margin-top: 2px;
}

/* 徽章 */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}

.badge-blue {
    background: linear-gradient(135deg, #e7f1ff 0%, #cfe2ff 100%);
    color: #0d6efd;
}

.badge-green {
    background: linear-gradient(135deg, #d1e7dd 0%, #badbcc 100%);
    color: #198754;
}

.badge-yellow {
    background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
    color: #997404;
}

.badge-red {
    background: linear-gradient(135deg, #f8d7da 0%, #f5c2c7 100%);
    color: #dc3545;
}

.badge-gray {
    background: #f0f0f0;
    color: #6c757d;
}

/* 无数据提示 */
.no-data {
    text-align: center;
    padding: 60px 20px;
    color: #6c757d;
    font-size: 15px;
}

.no-data-icon {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

/* ==================== 弹窗样式 ==================== */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    display: none;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modal-overlay.visible {
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 1;
}

.modal-content {
    background: #fff;
    border-radius: 16px;
    width: 480px;
    max-width: 95vw;
    max-height: 90vh;
    overflow: visible;
    box-shadow: 0 25px 80px rgba(0,0,0,0.3);
}

.modal-overlay.visible .modal-content {
    animation: modalFadeIn 0.3s ease;
}

@keyframes modalFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
}

.modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #212529;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-close {
    width: 36px;
    height: 36px;
    border: none;
    background: #f0f0f0;
    border-radius: 8px;
    font-size: 20px;
    cursor: pointer;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: #e9ecef;
    color: #dc3545;
}

.modal-body {
    padding: 24px;
    overflow-y: auto;
    overflow-x: visible;
    max-height: calc(90vh - 180px);
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #e9ecef;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    background: #f8f9fa;
}

/* ==================== 表单样式 ==================== */
.form-group {
    margin-bottom: 20px;
}

.form-group:last-child {
    margin-bottom: 0;
}

.form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #495057;
    font-size: 14px;
}

.form-label .required {
    color: #dc3545;
    margin-left: 2px;
}

.form-control {
    width: 100%;
    padding: 12px 14px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
    transition: all 0.2s ease;
    background: #fff;
    color: #212529;
}

.form-control:hover {
    border-color: #ced4da;
}

.form-control:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 4px rgba(0,123,255,0.1);
}

.form-control.error {
    border-color: #dc3545;
    box-shadow: 0 0 0 4px rgba(220,53,69,0.1);
}

/* z-select (Zabbix CSelect) 自适应宽度 + 圆角 */
z-select {
    width: 100% !important;
    min-width: 0 !important;
}
z-select button.focusable {
    border-radius: 6px !important;
}
z-select .list {
    border-radius: 6px !important;
    overflow: hidden;
}

textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

.form-hint {
    font-size: 12px;
    color: #6c757d;
    margin-top: 6px;
}

.form-error {
    font-size: 12px;
    color: #dc3545;
    margin-top: 6px;
    display: none;
}

.form-group.has-error .form-error {
    display: block;
}

/* 数字输入框带单位 */
.input-with-unit {
    position: relative;
    display: flex;
    align-items: center;
}

.input-with-unit .form-control {
    padding-right: 50px;
}

.input-unit {
    position: absolute;
    right: 14px;
    color: #6c757d;
    font-size: 14px;
    font-weight: 500;
    pointer-events: none;
}

/* ==================== 删除确认弹窗 ==================== */
.confirm-modal .modal-content {
    width: 400px;
}

.confirm-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
    font-size: 28px;
}

.confirm-title {
    font-size: 18px;
    font-weight: 600;
    color: #212529;
    text-align: center;
    margin-bottom: 10px;
}

.confirm-message {
    font-size: 14px;
    color: #6c757d;
    text-align: center;
    line-height: 1.6;
}

.confirm-item {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 12px 16px;
    margin-top: 15px;
    text-align: center;
    font-weight: 500;
    color: #495057;
}

/* ==================== 加载状态 ==================== */
.btn.loading {
    position: relative;
    color: transparent;
    pointer-events: none;
}

.btn.loading::after {
    content: "";
    position: absolute;
    width: 16px;
    height: 16px;
    border: 2px solid #fff;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* ==================== 响应式布局 ==================== */
/* 大屏幕 - 宽度占满 */
@media (min-width: 1400px) {
    .manage-container {
        max-width: none;
    }
}

/* 中等屏幕 */
@media (max-width: 1200px) {
    .stats-row {
        grid-template-columns: repeat(3, 1fr);
    }
    
    .stat-number {
        font-size: 24px;
    }
}

/* 小屏幕 */
@media (max-width: 992px) {
    .manage-container {
        padding: 15px;
    }
    
    .stats-row {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
    }
    
    .stat-card {
        padding: 15px;
    }
    
    .data-table th,
    .data-table td {
        padding: 12px 15px;
    }
}

/* 平板及以下 */
@media (max-width: 768px) {
    .manage-container {
        padding: 12px;
    }
    
    .stats-row {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .section-header {
        flex-direction: column;
        gap: 12px;
        align-items: stretch;
        padding: 15px;
    }
    
    .section-header .btn {
        width: 100%;
        justify-content: center;
    }
    
    .data-table {
        display: block;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }
    
    .data-table th,
    .data-table td {
        padding: 10px 12px;
        font-size: 13px;
    }
    
    .action-btns {
        flex-wrap: nowrap;
    }
    
    .modal-content {
        width: 95vw;
        margin: 10px;
        max-height: 95vh;
    }
    
    .modal-body {
        max-height: calc(95vh - 160px);
    }
}

/* 手机屏幕 */
@media (max-width: 480px) {
    .manage-container {
        padding: 10px;
    }
    
    .stat-card {
        padding: 12px;
        gap: 10px;
    }
    
    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 20px;
    }
    
    .stat-number {
        font-size: 22px;
    }
    
    .section-header h2 {
        font-size: 15px;
    }
    
    .btn {
        padding: 8px 14px;
        font-size: 13px;
    }
    
    .btn-sm {
        padding: 5px 10px;
        font-size: 11px;
    }
}
');

// ==================== 构建页面内容（CTag 模式，与 CMDB 保持一致） ====================

$content = (new CDiv())->addClass('manage-wrapper');
$container = (new CDiv())->addClass('manage-container');

// ── 统计卡片 ──
$statsRow = (new CDiv())->addClass('stats-row');

$statsRow->addItem(
    (new CDiv())
        ->addClass('stat-card rooms')
        ->addItem((new CDiv('🏢'))->addClass('stat-icon'))
        ->addItem(
            (new CDiv())
                ->addClass('stat-content')
                ->addItem((new CDiv((string)$totalRooms))->addClass('stat-number'))
                ->addItem((new CDiv(LanguageManager::t('total_rooms')))->addClass('stat-label'))
        )
);

$statsRow->addItem(
    (new CDiv())
        ->addClass('stat-card racks')
        ->addItem((new CDiv('🗄️'))->addClass('stat-icon'))
        ->addItem(
            (new CDiv())
                ->addClass('stat-content')
                ->addItem((new CDiv((string)$totalRacks))->addClass('stat-number'))
                ->addItem((new CDiv(LanguageManager::t('total_racks')))->addClass('stat-label'))
        )
);

$statsRow->addItem(
    (new CDiv())
        ->addClass('stat-card capacity')
        ->addItem((new CDiv('📐'))->addClass('stat-icon'))
        ->addItem(
            (new CDiv())
                ->addClass('stat-content')
                ->addItem((new CDiv($totalU . 'U'))->addClass('stat-number'))
                ->addItem((new CDiv(LanguageManager::t('total_capacity')))->addClass('stat-label'))
        )
);

$usedNumberDiv = (new CDiv())->addClass('stat-number');
$usedNumberDiv->addItem($usedU . 'U ');
$usedNumberDiv->addItem(
    (new CTag('small', true, '(' . $usagePercent . '%)'))
        ->setAttribute('style', 'font-size:14px;color:#6c757d;')
);
$statsRow->addItem(
    (new CDiv())
        ->addClass('stat-card used')
        ->addItem((new CDiv('📊'))->addClass('stat-icon'))
        ->addItem(
            (new CDiv())
                ->addClass('stat-content')
                ->addItem($usedNumberDiv)
                ->addItem((new CDiv(LanguageManager::t('used_capacity')))->addClass('stat-label'))
        )
);

$container->addItem($statsRow);

// ── 机房管理区域 ──
$roomSection = (new CDiv())->addClass('manage-section');

$roomHeader = (new CDiv())->addClass('section-header');
$roomHeader->addItem(new CTag('h2', true, '🏢 ' . LanguageManager::t('room_management')));
$roomHeader->addItem(
    (new CTag('button', true))
        ->addClass('btn btn-primary')
        ->setAttribute('onclick', 'RackManager.openRoomModal()')
        ->addItem(new CSpan('➕'))
        ->addItem(' ' . LanguageManager::t('add_room'))
);
$roomSection->addItem($roomHeader);

$roomBody = (new CDiv())->addClass('section-body');

if (empty($rooms)) {
    $roomBody->addItem(
        (new CDiv())
            ->addClass('no-data')
            ->addItem((new CDiv('📭'))->addClass('no-data-icon'))
            ->addItem(new CDiv(LanguageManager::t('no_rooms')))
    );
} else {
    $roomTable = (new CTable())->addClass('data-table');
    $roomTable->setHeader([
        LanguageManager::t('room_name'),
        LanguageManager::t('description'),
        LanguageManager::t('rack_count'),
        LanguageManager::t('created_at'),
        (new CCol(LanguageManager::t('actions')))->setAttribute('style', 'width:120px;')
    ]);

    foreach ($rooms as $room) {
        $rackCount = $roomRackCount[$room['id']] ?? 0;
        $roomId = htmlspecialchars($room['id'], ENT_QUOTES);
        $roomName = htmlspecialchars($room['name'], ENT_QUOTES);

        $nameCol = new CCol(
            (new CDiv(htmlspecialchars($room['name'])))->addClass('cell-main')
        );
        $descCol = new CCol(htmlspecialchars($room['description'] ?? '-'));
        $countCol = new CCol(
            (new CSpan('🗄️ ' . $rackCount))->addClass('badge badge-blue')
        );
        $dateCol = new CCol(htmlspecialchars($room['created_at'] ?? '-'));

        $actionBtns = (new CDiv())->addClass('action-btns');
        $actionBtns->addItem(
            (new CTag('button', true, '✏️'))
                ->addClass('btn btn-sm btn-secondary')
                ->setAttribute('onclick', "RackManager.editRoom('" . $roomId . "')")
        );
        $actionBtns->addItem(
            (new CTag('button', true, '🗑️'))
                ->addClass('btn btn-sm btn-danger')
                ->setAttribute('onclick', "RackManager.confirmDeleteRoom('" . $roomId . "', '" . addslashes($roomName) . "', " . $rackCount . ")")
        );
        $actionCol = new CCol($actionBtns);

        $roomTable->addRow([$nameCol, $descCol, $countCol, $dateCol, $actionCol]);
    }

    $roomBody->addItem($roomTable);
}

$roomSection->addItem($roomBody);
$container->addItem($roomSection);

// ── 机柜管理区域（不再生成隐藏 select，改用 JS 直接设置 value） ──
$rackSection = (new CDiv())->addClass('manage-section');

$rackHeader = (new CDiv())->addClass('section-header');
$rackHeader->addItem(new CTag('h2', true, '🗄️ ' . LanguageManager::t('rack_management')));
$rackHeader->addItem(
    (new CTag('button', true))
        ->addClass('btn btn-primary')
        ->setAttribute('onclick', 'RackManager.openRackModal()')
        ->addItem(new CSpan('➕'))
        ->addItem(' ' . LanguageManager::t('add_rack'))
);
$rackSection->addItem($rackHeader);

$rackBody = (new CDiv())->addClass('section-body');

if (empty($racks)) {
    $rackBody->addItem(
        (new CDiv())
            ->addClass('no-data')
            ->addItem((new CDiv('📭'))->addClass('no-data-icon'))
            ->addItem(new CDiv(LanguageManager::t('no_racks')))
    );
} else {
    $rackTable = (new CTable())->addClass('data-table');
    $rackTable->setHeader([
        LanguageManager::t('rack_name'),
        LanguageManager::t('room'),
        LanguageManager::t('height'),
        LanguageManager::t('used'),
        LanguageManager::t('description'),
        LanguageManager::t('created_at'),
        (new CCol(LanguageManager::t('actions')))->setAttribute('style', 'width:120px;')
    ]);

    foreach ($racks as $rack) {
        $rackId = htmlspecialchars($rack['id'], ENT_QUOTES);
        $rackName = htmlspecialchars($rack['name'], ENT_QUOTES);
        $rackHeight = (int)($rack['height'] ?? 42);
        $rackUsedU = (int)($rack['used_u'] ?? 0);
        $rackUsagePercent = $rackHeight > 0 ? round(($rackUsedU / $rackHeight) * 100, 1) : 0;

        $usageBadgeClass = 'badge-green';
        if ($rackUsagePercent >= 90) {
            $usageBadgeClass = 'badge-red';
        } elseif ($rackUsagePercent >= 70) {
            $usageBadgeClass = 'badge-yellow';
        }

        $nameCol = new CCol(
            (new CDiv(htmlspecialchars($rack['name'])))->addClass('cell-main')
        );
        $roomCol = new CCol(htmlspecialchars($rack['room_name'] ?? '-'));
        $heightCol = new CCol(
            (new CSpan('📐 ' . $rackHeight . 'U'))->addClass('badge badge-green')
        );
        $usedCol = new CCol(
            (new CSpan('📊 ' . $rackUsedU . 'U (' . $rackUsagePercent . '%)'))->addClass('badge ' . $usageBadgeClass)
        );
        $descCol = new CCol(htmlspecialchars($rack['description'] ?? '-'));
        $dateCol = new CCol(htmlspecialchars($rack['created_at'] ?? '-'));

        $actionBtns = (new CDiv())->addClass('action-btns');
        $actionBtns->addItem(
            (new CTag('button', true, '✏️'))
                ->addClass('btn btn-sm btn-secondary')
                ->setAttribute('onclick', "RackManager.editRack('" . $rackId . "')")
        );
        $actionBtns->addItem(
            (new CTag('button', true, '🗑️'))
                ->addClass('btn btn-sm btn-danger')
                ->setAttribute('onclick', "RackManager.confirmDeleteRack('" . $rackId . "', '" . addslashes($rackName) . "')")
        );
        $actionCol = new CCol($actionBtns);

        $rackTable->addRow([$nameCol, $roomCol, $heightCol, $usedCol, $descCol, $dateCol, $actionCol]);
    }

    $rackBody->addItem($rackTable);
}

$rackSection->addItem($rackBody);
$container->addItem($rackSection);

$content->addItem($container);

// ==================== 机房编辑弹窗 ====================
$roomModal = (new CDiv())
    ->setAttribute('id', 'room-modal')
    ->addClass('modal-overlay');

$roomModalContent = (new CDiv())->addClass('modal-content');

// 弹窗头部
$roomModalContent->addItem(
    (new CDiv())
        ->addClass('modal-header')
        ->addItem(
            (new CTag('h3', true))
                ->setAttribute('id', 'room-modal-title')
                ->addItem('🏢 ' . LanguageManager::t('add_room'))
        )
        ->addItem(
            (new CTag('button', true, '×'))
                ->addClass('modal-close')
                ->setAttribute('onclick', 'RackManager.closeRoomModal()')
        )
);

// 弹窗主体
$roomForm = (new CTag('form', true))
    ->setAttribute('id', 'room-form')
    ->setAttribute('onsubmit', 'return false;');

$roomForm->addItem(
    (new CInput('hidden', 'id', ''))->setAttribute('id', 'room-id')
);

// 名称字段
$nameGroup = (new CDiv())
    ->addClass('form-group')
    ->setAttribute('id', 'room-name-group');
$nameGroup->addItem(
    (new CTag('label', true))
        ->addClass('form-label')
        ->addItem(LanguageManager::t('room_name'))
        ->addItem((new CSpan('*'))->addClass('required'))
);
$nameGroup->addItem(
    (new CTextBox('name', ''))
        ->setAttribute('id', 'room-name')
        ->addClass('form-control')
        ->setAttribute('placeholder', LanguageManager::t('enter_room_name'))
        ->setAttribute('maxlength', '100')
        ->setAttribute('autocomplete', 'off')
);
$nameGroup->addItem(
    (new CDiv(LanguageManager::t('room_name_required')))->addClass('form-error')
);
$roomForm->addItem($nameGroup);

// 描述字段
$descGroup = (new CDiv())->addClass('form-group');
$descGroup->addItem(
    (new CTag('label', true, LanguageManager::t('description')))->addClass('form-label')
);
$descGroup->addItem(
    (new CTag('textarea', true, ''))
        ->setAttribute('id', 'room-description')
        ->setAttribute('name', 'description')
        ->addClass('form-control')
        ->setAttribute('placeholder', LanguageManager::t('enter_room_description'))
        ->setAttribute('maxlength', '500')
);
$roomForm->addItem($descGroup);

$roomModalBody = (new CDiv())->addClass('modal-body');
$roomModalBody->addItem($roomForm);
$roomModalContent->addItem($roomModalBody);

// 弹窗底部
$roomModalContent->addItem(
    (new CDiv())
        ->addClass('modal-footer')
        ->addItem(
            (new CTag('button', true, LanguageManager::t('cancel')))
                ->addClass('btn btn-secondary')
                ->setAttribute('onclick', 'RackManager.closeRoomModal()')
        )
        ->addItem(
            (new CTag('button', true))
                ->setAttribute('id', 'room-save-btn')
                ->addClass('btn btn-success')
                ->setAttribute('onclick', 'RackManager.saveRoom()')
                ->addItem(new CSpan('✓'))
                ->addItem(' ' . LanguageManager::t('save'))
        )
);

$roomModal->addItem($roomModalContent);
$content->addItem($roomModal);

// ==================== 机柜编辑弹窗 ====================
$rackModal = (new CDiv())
    ->setAttribute('id', 'rack-modal')
    ->addClass('modal-overlay');

$rackModalContent = (new CDiv())->addClass('modal-content');

// 弹窗头部
$rackModalContent->addItem(
    (new CDiv())
        ->addClass('modal-header')
        ->addItem(
            (new CTag('h3', true))
                ->setAttribute('id', 'rack-modal-title')
                ->addItem('🗄️ ' . LanguageManager::t('add_rack'))
        )
        ->addItem(
            (new CTag('button', true, '×'))
                ->addClass('modal-close')
                ->setAttribute('onclick', 'RackManager.closeRackModal()')
        )
);

// 弹窗主体
$rackForm = (new CTag('form', true))
    ->setAttribute('id', 'rack-form')
    ->setAttribute('onsubmit', 'return false;');

$rackForm->addItem(
    (new CInput('hidden', 'id', ''))->setAttribute('id', 'rack-id')
);

// 名称字段
$rackNameGroup = (new CDiv())
    ->addClass('form-group')
    ->setAttribute('id', 'rack-name-group');
$rackNameGroup->addItem(
    (new CTag('label', true))
        ->addClass('form-label')
        ->addItem(LanguageManager::t('rack_name'))
        ->addItem((new CSpan('*'))->addClass('required'))
);
$rackNameGroup->addItem(
    (new CTextBox('name', ''))
        ->setAttribute('id', 'rack-name')
        ->addClass('form-control')
        ->setAttribute('placeholder', LanguageManager::t('enter_rack_name'))
        ->setAttribute('maxlength', '100')
        ->setAttribute('autocomplete', 'off')
);
$rackNameGroup->addItem(
    (new CDiv(LanguageManager::t('rack_name_required')))->addClass('form-error')
);
$rackForm->addItem($rackNameGroup);

// 【关键】机房选择 - 使用 Zabbix CSelect（z-select），确保下拉框在 Zabbix 框架中正确渲染
$roomSelectGroup = (new CDiv())
    ->addClass('form-group')
    ->setAttribute('id', 'rack-room-group');
$roomSelectGroup->addItem(
    (new CTag('label', true))
        ->addClass('form-label')
        ->addItem(LanguageManager::t('room'))
        ->addItem((new CSpan('*'))->addClass('required'))
);

$roomSelect = (new CSelect('room_id'))
    ->setAttribute('id', 'rack-room-id')
    ->addOption(new CSelectOption('', '-- ' . LanguageManager::t('select_room') . ' --'));

foreach ($rooms as $room) {
    $roomSelect->addOption(new CSelectOption($room['id'], $room['name']));
}

$roomSelectGroup->addItem($roomSelect);
$roomSelectGroup->addItem(
    (new CDiv(LanguageManager::t('room_selection_required')))->addClass('form-error')
);
$rackForm->addItem($roomSelectGroup);

// 高度字段
$heightGroup = (new CDiv())->addClass('form-group');
$heightGroup->addItem(
    (new CTag('label', true, LanguageManager::t('height')))->addClass('form-label')
);
$heightInputWrapper = (new CDiv())->addClass('input-with-unit');
$heightInputWrapper->addItem(
    (new CTag('input', false))
        ->setAttribute('type', 'number')
        ->setAttribute('id', 'rack-height')
        ->setAttribute('name', 'height')
        ->addClass('form-control')
        ->setAttribute('value', '42')
        ->setAttribute('min', '1')
        ->setAttribute('max', '60')
);
$heightInputWrapper->addItem(
    (new CSpan('U'))->addClass('input-unit')
);
$heightGroup->addItem($heightInputWrapper);
$heightGroup->addItem(
    (new CDiv(LanguageManager::t('rack_height_hint')))->addClass('form-hint')
);
$rackForm->addItem($heightGroup);

// 描述字段
$rackDescGroup = (new CDiv())->addClass('form-group');
$rackDescGroup->addItem(
    (new CTag('label', true, LanguageManager::t('description')))->addClass('form-label')
);
$rackDescGroup->addItem(
    (new CTag('textarea', true, ''))
        ->setAttribute('id', 'rack-description')
        ->setAttribute('name', 'description')
        ->addClass('form-control')
        ->setAttribute('placeholder', LanguageManager::t('enter_rack_description'))
        ->setAttribute('maxlength', '500')
);
$rackForm->addItem($rackDescGroup);

$rackModalBody = (new CDiv())->addClass('modal-body');
$rackModalBody->addItem($rackForm);
$rackModalContent->addItem($rackModalBody);

// 弹窗底部
$rackModalContent->addItem(
    (new CDiv())
        ->addClass('modal-footer')
        ->addItem(
            (new CTag('button', true, LanguageManager::t('cancel')))
                ->addClass('btn btn-secondary')
                ->setAttribute('onclick', 'RackManager.closeRackModal()')
        )
        ->addItem(
            (new CTag('button', true))
                ->setAttribute('id', 'rack-save-btn')
                ->addClass('btn btn-success')
                ->setAttribute('onclick', 'RackManager.saveRack()')
                ->addItem(new CSpan('✓'))
                ->addItem(' ' . LanguageManager::t('save'))
        )
);

$rackModal->addItem($rackModalContent);
$content->addItem($rackModal);

// ==================== 删除确认弹窗 ====================
$confirmModal = (new CDiv())
    ->setAttribute('id', 'confirm-modal')
    ->addClass('modal-overlay confirm-modal');

$confirmModalContent = (new CDiv())->addClass('modal-content');

$confirmBody = (new CDiv())
    ->addClass('modal-body')
    ->setAttribute('style', 'padding:30px;');
$confirmBody->addItem((new CDiv('⚠️'))->addClass('confirm-icon'));
$confirmBody->addItem(
    (new CDiv(LanguageManager::t('confirm')))->addClass('confirm-title')->setAttribute('id', 'confirm-title')
);
$confirmBody->addItem(
    (new CDiv())->addClass('confirm-message')->setAttribute('id', 'confirm-message')
);
$confirmBody->addItem(
    (new CDiv())->addClass('confirm-item')->setAttribute('id', 'confirm-item')
);
$confirmModalContent->addItem($confirmBody);

$confirmModalContent->addItem(
    (new CDiv())
        ->addClass('modal-footer')
        ->setAttribute('style', 'justify-content:center;')
        ->addItem(
            (new CTag('button', true, LanguageManager::t('cancel')))
                ->addClass('btn btn-secondary')
                ->setAttribute('onclick', 'RackManager.closeConfirmModal()')
        )
        ->addItem(
            (new CTag('button', true))
                ->setAttribute('id', 'confirm-btn')
                ->addClass('btn btn-danger')
                ->addItem(new CSpan('🗑️'))
                ->addItem(' ' . LanguageManager::t('delete'))
        )
);

$confirmModal->addItem($confirmModalContent);
$content->addItem($confirmModal);

// ==================== JavaScript ====================
$i18n = [
    'add_room' => LanguageManager::t('add_room'),
    'edit_room' => LanguageManager::t('edit_room'),
    'add_rack' => LanguageManager::t('add_rack'),
    'edit_rack' => LanguageManager::t('edit_rack'),
    'confirm_delete_room' => LanguageManager::t('confirm_delete_room'),
    'confirm_delete_rack' => LanguageManager::t('confirm_delete_rack'),
    'room_has_racks_warning' => LanguageManager::t('room_has_racks_warning'),
    'save_success' => LanguageManager::t('save_success'),
    'delete_success' => LanguageManager::t('delete_success'),
    'operation_failed' => LanguageManager::t('operation_failed'),
    'delete_room_title' => LanguageManager::t('delete_room_title'),
    'delete_rack_title' => LanguageManager::t('delete_rack_title'),
];
$i18nJson = json_encode($i18n, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$content->addItem(new CJsScript('<script>
(function() {
    "use strict";
    
    // 国际化文本
    var i18n = ' . $i18nJson . ';
    
    // 机房数据（以 ID 为键的映射）
    var roomsData = ' . $roomsJson . ';
    
    // 机柜数据（以 ID 为键的映射）
    var racksData = ' . $racksJson . ';
    
    // 管理器对象
    var RackManager = {
        // 当前操作的数据
        currentDeleteType: null,
        currentDeleteId: null,
        
        // ==================== 机房相关 ====================
        openRoomModal: function() {
            document.getElementById("room-modal-title").innerHTML = "🏢 " + i18n.add_room;
            document.getElementById("room-id").value = "";
            document.getElementById("room-name").value = "";
            document.getElementById("room-description").value = "";
            this.clearFormErrors("room-form");
            this.showModal("room-modal");
            setTimeout(function() {
                document.getElementById("room-name").focus();
            }, 100);
        },
        
        closeRoomModal: function() {
            this.hideModal("room-modal");
        },
        
        editRoom: function(roomId) {
            var room = roomsData[roomId];
            if (!room) {
                console.error("Room not found:", roomId);
                alert(i18n.operation_failed);
                return;
            }
            
            document.getElementById("room-modal-title").innerHTML = "🏢 " + i18n.edit_room;
            document.getElementById("room-id").value = room.id || "";
            document.getElementById("room-name").value = room.name || "";
            document.getElementById("room-description").value = room.description || "";
            this.clearFormErrors("room-form");
            this.showModal("room-modal");
        },
        
        saveRoom: function() {
            var self = this;
            var name = document.getElementById("room-name").value.trim();
            var description = document.getElementById("room-description").value.trim();
            var id = document.getElementById("room-id").value;
            
            this.clearFormErrors("room-form");
            if (!name) {
                this.showFormError("room-name-group");
                document.getElementById("room-name").focus();
                return;
            }
            
            var saveBtn = document.getElementById("room-save-btn");
            saveBtn.classList.add("loading");
            
            var formData = new FormData();
            formData.append("action", "room.save");
            formData.append("id", id);
            formData.append("name", name);
            formData.append("description", description);
            
            fetch("zabbix.php", { method: "POST", body: formData })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                saveBtn.classList.remove("loading");
                if (data.success) {
                    self.closeRoomModal();
                    location.reload();
                } else {
                    alert(data.error || data.message || i18n.operation_failed);
                }
            })
            .catch(function() {
                saveBtn.classList.remove("loading");
                alert(i18n.operation_failed);
            });
        },
        
        confirmDeleteRoom: function(roomId, roomName, rackCount) {
            this.currentDeleteType = "room";
            this.currentDeleteId = roomId;
            
            var message = i18n.confirm_delete_room;
            if (rackCount > 0) {
                message += "<br><br><strong style=\\"color:#dc3545;\\">" + i18n.room_has_racks_warning.replace("{count}", rackCount) + "</strong>";
            }
            
            document.getElementById("confirm-title").textContent = i18n.delete_room_title;
            document.getElementById("confirm-message").innerHTML = message;
            document.getElementById("confirm-item").textContent = roomName;
            document.getElementById("confirm-btn").onclick = this.executeDelete.bind(this);
            
            this.showModal("confirm-modal");
        },
        
        // ==================== 机柜相关 ====================
        openRackModal: function() {
            document.getElementById("rack-modal-title").innerHTML = "🗄️ " + i18n.add_rack;
            document.getElementById("rack-id").value = "";
            document.getElementById("rack-name").value = "";
            document.getElementById("rack-height").value = "42";
            document.getElementById("rack-description").value = "";
            
            // 重置机房下拉框为默认选项（z-select 通过 value 设置）
            var targetSelect = document.getElementById("rack-room-id");
            targetSelect.value = "";
            
            this.clearFormErrors("rack-form");
            this.showModal("rack-modal");
            setTimeout(function() {
                document.getElementById("rack-name").focus();
            }, 100);
        },
        
        closeRackModal: function() {
            this.hideModal("rack-modal");
        },
        
        editRack: function(rackId) {
            var rack = racksData[rackId];
            if (!rack) {
                console.error("Rack not found:", rackId);
                alert(i18n.operation_failed);
                return;
            }
            
            document.getElementById("rack-modal-title").innerHTML = "🗄️ " + i18n.edit_rack;
            document.getElementById("rack-id").value = rack.id || "";
            document.getElementById("rack-name").value = rack.name || "";
            document.getElementById("rack-height").value = rack.height || 42;
            document.getElementById("rack-description").value = rack.description || "";
            
            // 【关键】直接通过 value 设置选中的机房（z-select 支持 .value 属性）
            var targetSelect = document.getElementById("rack-room-id");
            targetSelect.value = rack.room_id || "";
            
            this.clearFormErrors("rack-form");
            this.showModal("rack-modal");
        },
        
        saveRack: function() {
            var self = this;
            var name = document.getElementById("rack-name").value.trim();
            var roomId = document.getElementById("rack-room-id").value;
            var height = document.getElementById("rack-height").value || 42;
            var description = document.getElementById("rack-description").value.trim();
            var id = document.getElementById("rack-id").value;
            
            this.clearFormErrors("rack-form");
            var hasError = false;
            
            if (!name) {
                this.showFormError("rack-name-group");
                if (!hasError) document.getElementById("rack-name").focus();
                hasError = true;
            }
            
            if (!roomId) {
                this.showFormError("rack-room-group");
                if (!hasError) document.getElementById("rack-room-id").focus();
                hasError = true;
            }
            
            if (hasError) return;
            
            var saveBtn = document.getElementById("rack-save-btn");
            saveBtn.classList.add("loading");
            
            var formData = new FormData();
            formData.append("action", "rack.save");
            formData.append("id", id);
            formData.append("name", name);
            formData.append("room_id", roomId);
            formData.append("height", height);
            formData.append("description", description);
            
            fetch("zabbix.php", { method: "POST", body: formData })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                saveBtn.classList.remove("loading");
                if (data.success) {
                    self.closeRackModal();
                    location.reload();
                } else {
                    alert(data.error || data.message || i18n.operation_failed);
                }
            })
            .catch(function() {
                saveBtn.classList.remove("loading");
                alert(i18n.operation_failed);
            });
        },
        
        confirmDeleteRack: function(rackId, rackName) {
            this.currentDeleteType = "rack";
            this.currentDeleteId = rackId;
            
            document.getElementById("confirm-title").textContent = i18n.delete_rack_title;
            document.getElementById("confirm-message").innerHTML = i18n.confirm_delete_rack;
            document.getElementById("confirm-item").textContent = rackName;
            document.getElementById("confirm-btn").onclick = this.executeDelete.bind(this);
            
            this.showModal("confirm-modal");
        },
        
        // ==================== 删除操作 ====================
        executeDelete: function() {
            var self = this;
            var action = this.currentDeleteType === "room" ? "room.delete" : "rack.delete";
            
            var confirmBtn = document.getElementById("confirm-btn");
            confirmBtn.classList.add("loading");
            
            var formData = new FormData();
            formData.append("action", action);
            formData.append("id", this.currentDeleteId);
            
            fetch("zabbix.php", { method: "POST", body: formData })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                confirmBtn.classList.remove("loading");
                if (data.success) {
                    self.closeConfirmModal();
                    location.reload();
                } else {
                    alert(data.error || data.message || i18n.operation_failed);
                }
            })
            .catch(function() {
                confirmBtn.classList.remove("loading");
                alert(i18n.operation_failed);
            });
        },
        
        closeConfirmModal: function() {
            this.hideModal("confirm-modal");
            this.currentDeleteType = null;
            this.currentDeleteId = null;
        },
        
        // ==================== 通用方法 ====================
        showModal: function(modalId) {
            var modal = document.getElementById(modalId);
            modal.classList.add("visible");
            document.body.style.overflow = "hidden";
        },
        
        hideModal: function(modalId) {
            var modal = document.getElementById(modalId);
            modal.classList.remove("visible");
            document.body.style.overflow = "";
        },
        
        clearFormErrors: function(formId) {
            var form = document.getElementById(formId);
            var groups = form.querySelectorAll(".form-group");
            groups.forEach(function(group) {
                group.classList.remove("has-error");
            });
            var controls = form.querySelectorAll(".form-control, z-select");
            controls.forEach(function(control) {
                control.classList.remove("error");
            });
        },
        
        showFormError: function(groupId) {
            var group = document.getElementById(groupId);
            if (group) {
                group.classList.add("has-error");
                var control = group.querySelector(".form-control, z-select");
                if (control) control.classList.add("error");
            }
        }
    };
    
    // 暴露到全局
    window.RackManager = RackManager;
    
    // 绑定键盘事件
    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape") {
            var modals = document.querySelectorAll(".modal-overlay.visible");
            modals.forEach(function(modal) {
                modal.classList.remove("visible");
            });
            document.body.style.overflow = "";
        }
        
        if (e.key === "Enter" && !e.shiftKey) {
            var roomModal = document.getElementById("room-modal");
            var rackModal = document.getElementById("rack-modal");
            
            if (roomModal.classList.contains("visible")) {
                e.preventDefault();
                RackManager.saveRoom();
            } else if (rackModal.classList.contains("visible")) {
                var activeElement = document.activeElement;
                if (activeElement.tagName !== "TEXTAREA") {
                    e.preventDefault();
                    RackManager.saveRack();
                }
            }
        }
    });
    
    // 点击弹窗外部关闭
    document.querySelectorAll(".modal-overlay").forEach(function(modal) {
        modal.addEventListener("click", function(e) {
            if (e.target === this) {
                this.classList.remove("visible");
                document.body.style.overflow = "";
            }
        });
    });
    
    // 输入框实时清除错误状态
    document.querySelectorAll(".form-control").forEach(function(control) {
        control.addEventListener("input", function() {
            this.classList.remove("error");
            var group = this.closest(".form-group");
            if (group) group.classList.remove("has-error");
        });
    });
    
})();
</script>'));

// 使用兼容渲染器显示页面
ViewRenderer::render($pageTitle, $styleTag, $content);
