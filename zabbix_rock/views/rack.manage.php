<?php
/**
 * 机柜管理页面 - 重构版
 * 功能：机房和机柜的增删改查管理
 */

// 引入依赖
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';

use Modules\ZabbixRock\Lib\LanguageManager;
use Modules\ZabbixRock\Lib\ViewRenderer;

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
    backdrop-filter: blur(4px);
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
    overflow: hidden;
    box-shadow: 0 25px 80px rgba(0,0,0,0.3);
    transform: scale(0.9) translateY(-20px);
    transition: transform 0.3s ease;
}

.modal-overlay.visible .modal-content {
    transform: scale(1) translateY(0);
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

select.form-control {
    appearance: none;
    background-image: url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\'%3e%3cpath fill=\'none\' stroke=\'%23343a40\' stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M2 5l6 6 6-6\'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 12px;
    padding-right: 40px;
    cursor: pointer;
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

// 构建 HTML
$html = '<div class="manage-wrapper">';
$html .= '<div class="manage-container">';

// ==================== 统计卡片 ====================
$html .= '<div class="stats-row">';
$html .= '<div class="stat-card rooms">';
$html .= '<div class="stat-icon">🏢</div>';
$html .= '<div class="stat-content">';
$html .= '<div class="stat-number">' . $totalRooms . '</div>';
$html .= '<div class="stat-label">' . LanguageManager::t('total_rooms') . '</div>';
$html .= '</div></div>';

$html .= '<div class="stat-card racks">';
$html .= '<div class="stat-icon">🗄️</div>';
$html .= '<div class="stat-content">';
$html .= '<div class="stat-number">' . $totalRacks . '</div>';
$html .= '<div class="stat-label">' . LanguageManager::t('total_racks') . '</div>';
$html .= '</div></div>';

$html .= '<div class="stat-card capacity">';
$html .= '<div class="stat-icon">📐</div>';
$html .= '<div class="stat-content">';
$html .= '<div class="stat-number">' . $totalU . 'U</div>';
$html .= '<div class="stat-label">' . LanguageManager::t('total_capacity') . '</div>';
$html .= '</div></div>';

$html .= '<div class="stat-card used">';
$html .= '<div class="stat-icon">📊</div>';
$html .= '<div class="stat-content">';
$html .= '<div class="stat-number">' . $usedU . 'U <small style="font-size:14px;color:#6c757d;">(' . $usagePercent . '%)</small></div>';
$html .= '<div class="stat-label">' . LanguageManager::t('used_capacity') . '</div>';
$html .= '</div></div>';
$html .= '</div>';

// ==================== 机房管理区域 ====================
$html .= '<div class="manage-section">';
$html .= '<div class="section-header">';
$html .= '<h2>🏢 ' . LanguageManager::t('room_management') . '</h2>';
$html .= '<button class="btn btn-primary" onclick="RackManager.openRoomModal()">';
$html .= '<span>➕</span> ' . LanguageManager::t('add_room') . '</button>';
$html .= '</div>';
$html .= '<div class="section-body">';

if (empty($rooms)) {
    $html .= '<div class="no-data">';
    $html .= '<div class="no-data-icon">📭</div>';
    $html .= '<div>' . LanguageManager::t('no_rooms') . '</div>';
    $html .= '</div>';
} else {
    $html .= '<table class="data-table">';
    $html .= '<thead><tr>';
    $html .= '<th>' . LanguageManager::t('room_name') . '</th>';
    $html .= '<th>' . LanguageManager::t('description') . '</th>';
    $html .= '<th>' . LanguageManager::t('rack_count') . '</th>';
    $html .= '<th>' . LanguageManager::t('created_at') . '</th>';
    $html .= '<th style="width:120px;">' . LanguageManager::t('actions') . '</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';
    
    foreach ($rooms as $room) {
        $rackCount = $roomRackCount[$room['id']] ?? 0;
        $roomId = htmlspecialchars($room['id'], ENT_QUOTES);
        $roomName = htmlspecialchars($room['name'], ENT_QUOTES);
        
        $html .= '<tr data-room-id="' . $roomId . '">';
        $html .= '<td><div class="cell-main">' . htmlspecialchars($room['name']) . '</div></td>';
        $html .= '<td>' . htmlspecialchars($room['description'] ?? '-') . '</td>';
        $html .= '<td><span class="badge badge-blue">🗄️ ' . $rackCount . '</span></td>';
        $html .= '<td>' . htmlspecialchars($room['created_at'] ?? '-') . '</td>';
        $html .= '<td>';
        $html .= '<div class="action-btns">';
        $html .= '<button class="btn btn-sm btn-secondary" onclick="RackManager.editRoom(\'' . $roomId . '\')">';
        $html .= '✏️</button>';
        $html .= '<button class="btn btn-sm btn-danger" onclick="RackManager.confirmDeleteRoom(\'' . $roomId . '\', \'' . addslashes($roomName) . '\', ' . $rackCount . ')">';
        $html .= '🗑️</button>';
        $html .= '</div>';
        $html .= '</td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';
}

$html .= '</div></div>';

// ==================== 机柜管理区域 ====================
$html .= '<div class="manage-section">';
$html .= '<div class="section-header">';
$html .= '<h2>🗄️ ' . LanguageManager::t('rack_management') . '</h2>';
$html .= '<button class="btn btn-primary" onclick="RackManager.openRackModal()">';
$html .= '<span>➕</span> ' . LanguageManager::t('add_rack') . '</button>';
$html .= '</div>';
$html .= '<div class="section-body">';

if (empty($racks)) {
    $html .= '<div class="no-data">';
    $html .= '<div class="no-data-icon">📭</div>';
    $html .= '<div>' . LanguageManager::t('no_racks') . '</div>';
    $html .= '</div>';
} else {
    $html .= '<table class="data-table">';
    $html .= '<thead><tr>';
    $html .= '<th>' . LanguageManager::t('rack_name') . '</th>';
    $html .= '<th>' . LanguageManager::t('room') . '</th>';
    $html .= '<th>' . LanguageManager::t('height') . '</th>';
    $html .= '<th>' . LanguageManager::t('used') . '</th>';
    $html .= '<th>' . LanguageManager::t('description') . '</th>';
    $html .= '<th>' . LanguageManager::t('created_at') . '</th>';
    $html .= '<th style="width:120px;">' . LanguageManager::t('actions') . '</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';
    
    foreach ($racks as $rack) {
        $rackId = htmlspecialchars($rack['id'], ENT_QUOTES);
        $rackName = htmlspecialchars($rack['name'], ENT_QUOTES);
        $currentRoomId = $rack['room_id'] ?? '';
        $rackHeight = (int)($rack['height'] ?? 42);
        $rackUsedU = (int)($rack['used_u'] ?? 0);
        $rackUsagePercent = $rackHeight > 0 ? round(($rackUsedU / $rackHeight) * 100, 1) : 0;
        
        // 根据使用率决定徽章颜色
        $usageBadgeClass = 'badge-green';
        if ($rackUsagePercent >= 90) {
            $usageBadgeClass = 'badge-red';
        } elseif ($rackUsagePercent >= 70) {
            $usageBadgeClass = 'badge-yellow';
        }
        
        $html .= '<tr data-rack-id="' . $rackId . '">';
        $html .= '<td><div class="cell-main">' . htmlspecialchars($rack['name']) . '</div></td>';
        $html .= '<td>' . htmlspecialchars($rack['room_name'] ?? '-') . '</td>';
        $html .= '<td><span class="badge badge-green">📐 ' . $rackHeight . 'U</span></td>';
        $html .= '<td><span class="badge ' . $usageBadgeClass . '">📊 ' . $rackUsedU . 'U (' . $rackUsagePercent . '%)</span></td>';
        $html .= '<td>' . htmlspecialchars($rack['description'] ?? '-') . '</td>';
        $html .= '<td>' . htmlspecialchars($rack['created_at'] ?? '-') . '</td>';
        $html .= '<td>';
        $html .= '<div class="action-btns">';
        $html .= '<button class="btn btn-sm btn-secondary" onclick="RackManager.editRack(\'' . $rackId . '\')">';
        $html .= '✏️</button>';
        $html .= '<button class="btn btn-sm btn-danger" onclick="RackManager.confirmDeleteRack(\'' . $rackId . '\', \'' . addslashes($rackName) . '\')">';
        $html .= '🗑️</button>';
        $html .= '</div>';
        // 为每个机柜生成隐藏的机房下拉框（带 selected 属性）
        $html .= '<select class="rack-room-options" data-rack-id="' . $rackId . '" style="display:none;">';
        $html .= '<option value="">-- ' . LanguageManager::t('select_room') . ' --</option>';
        foreach ($rooms as $room) {
            $selected = ($room['id'] === $currentRoomId) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($room['id']) . '"' . $selected . '>' . htmlspecialchars($room['name']) . '</option>';
        }
        $html .= '</select>';
        $html .= '</td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';
}

$html .= '</div></div>';
$html .= '</div>'; // manage-wrapper

// ==================== 机房编辑弹窗 ====================
$html .= '<div id="room-modal" class="modal-overlay">';
$html .= '<div class="modal-content">';
$html .= '<div class="modal-header">';
$html .= '<h3 id="room-modal-title">🏢 ' . LanguageManager::t('add_room') . '</h3>';
$html .= '<button class="modal-close" onclick="RackManager.closeRoomModal()">&times;</button>';
$html .= '</div>';
$html .= '<div class="modal-body">';
$html .= '<form id="room-form" onsubmit="return false;">';
$html .= '<input type="hidden" id="room-id" name="id">';

$html .= '<div class="form-group" id="room-name-group">';
$html .= '<label class="form-label">' . LanguageManager::t('room_name') . '<span class="required">*</span></label>';
$html .= '<input type="text" id="room-name" name="name" class="form-control" placeholder="' . LanguageManager::t('enter_room_name') . '" maxlength="100" autocomplete="off">';
$html .= '<div class="form-error">' . LanguageManager::t('room_name_required') . '</div>';
$html .= '</div>';

$html .= '<div class="form-group">';
$html .= '<label class="form-label">' . LanguageManager::t('description') . '</label>';
$html .= '<textarea id="room-description" name="description" class="form-control" placeholder="' . LanguageManager::t('enter_room_description') . '" maxlength="500"></textarea>';
$html .= '<div class="form-hint">' . LanguageManager::t('optional_field') . '</div>';
$html .= '</div>';

$html .= '</form>';
$html .= '</div>';
$html .= '<div class="modal-footer">';
$html .= '<button class="btn btn-secondary" onclick="RackManager.closeRoomModal()">' . LanguageManager::t('cancel') . '</button>';
$html .= '<button id="room-save-btn" class="btn btn-success" onclick="RackManager.saveRoom()">';
$html .= '<span>✓</span> ' . LanguageManager::t('save') . '</button>';
$html .= '</div>';
$html .= '</div></div>';

// ==================== 机柜编辑弹窗 ====================
$html .= '<div id="rack-modal" class="modal-overlay">';
$html .= '<div class="modal-content">';
$html .= '<div class="modal-header">';
$html .= '<h3 id="rack-modal-title">🗄️ ' . LanguageManager::t('add_rack') . '</h3>';
$html .= '<button class="modal-close" onclick="RackManager.closeRackModal()">&times;</button>';
$html .= '</div>';
$html .= '<div class="modal-body">';
$html .= '<form id="rack-form" onsubmit="return false;">';
$html .= '<input type="hidden" id="rack-id" name="id">';

$html .= '<div class="form-group" id="rack-name-group">';
$html .= '<label class="form-label">' . LanguageManager::t('rack_name') . '<span class="required">*</span></label>';
$html .= '<input type="text" id="rack-name" name="name" class="form-control" placeholder="' . LanguageManager::t('enter_rack_name') . '" maxlength="100" autocomplete="off">';
$html .= '<div class="form-error">' . LanguageManager::t('rack_name_required') . '</div>';
$html .= '</div>';

$html .= '<div class="form-group" id="rack-room-group">';
$html .= '<label class="form-label">' . LanguageManager::t('room') . '<span class="required">*</span></label>';
$html .= '<select id="rack-room-id" name="room_id" class="form-control">';
$html .= '<option value="">-- ' . LanguageManager::t('select_room') . ' --</option>';
foreach ($rooms as $room) {
    $html .= '<option value="' . htmlspecialchars($room['id']) . '">' . htmlspecialchars($room['name']) . '</option>';
}
$html .= '</select>';
$html .= '<div class="form-error">' . LanguageManager::t('room_required') . '</div>';
$html .= '</div>';

$html .= '<div class="form-group">';
$html .= '<label class="form-label">' . LanguageManager::t('height') . '</label>';
$html .= '<div class="input-with-unit">';
$html .= '<input type="number" id="rack-height" name="height" class="form-control" value="42" min="1" max="60">';
$html .= '<span class="input-unit">U</span>';
$html .= '</div>';
$html .= '<div class="form-hint">' . LanguageManager::t('rack_height_hint') . '</div>';
$html .= '</div>';

$html .= '<div class="form-group">';
$html .= '<label class="form-label">' . LanguageManager::t('description') . '</label>';
$html .= '<textarea id="rack-description" name="description" class="form-control" placeholder="' . LanguageManager::t('enter_rack_description') . '" maxlength="500"></textarea>';
$html .= '</div>';

$html .= '</form>';
$html .= '</div>';
$html .= '<div class="modal-footer">';
$html .= '<button class="btn btn-secondary" onclick="RackManager.closeRackModal()">' . LanguageManager::t('cancel') . '</button>';
$html .= '<button id="rack-save-btn" class="btn btn-success" onclick="RackManager.saveRack()">';
$html .= '<span>✓</span> ' . LanguageManager::t('save') . '</button>';
$html .= '</div>';
$html .= '</div></div>';

// ==================== 删除确认弹窗 ====================
$html .= '<div id="confirm-modal" class="modal-overlay confirm-modal">';
$html .= '<div class="modal-content">';
$html .= '<div class="modal-body" style="padding:30px;">';
$html .= '<div class="confirm-icon">⚠️</div>';
$html .= '<div class="confirm-title" id="confirm-title">' . LanguageManager::t('confirm_delete') . '</div>';
$html .= '<div class="confirm-message" id="confirm-message"></div>';
$html .= '<div class="confirm-item" id="confirm-item"></div>';
$html .= '</div>';
$html .= '<div class="modal-footer" style="justify-content:center;">';
$html .= '<button class="btn btn-secondary" onclick="RackManager.closeConfirmModal()">' . LanguageManager::t('cancel') . '</button>';
$html .= '<button id="confirm-btn" class="btn btn-danger">';
$html .= '<span>🗑️</span> ' . LanguageManager::t('delete') . '</button>';
$html .= '</div>';
$html .= '</div></div>';

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
];
$i18nJson = json_encode($i18n, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$js = <<<JS
<script>
(function() {
    'use strict';
    
    // 国际化文本
    var i18n = {$i18nJson};
    
    // 机房数据（以 ID 为键的映射）
    var roomsData = {$roomsJson};
    
    // 机柜数据（以 ID 为键的映射）
    var racksData = {$racksJson};
    
    // 管理器对象
    var RackManager = {
        // 当前操作的数据
        currentDeleteType: null,
        currentDeleteId: null,
        
        // ==================== 机房相关 ====================
        openRoomModal: function() {
            document.getElementById('room-modal-title').innerHTML = '🏢 ' + i18n.add_room;
            document.getElementById('room-id').value = '';
            document.getElementById('room-name').value = '';
            document.getElementById('room-description').value = '';
            this.clearFormErrors('room-form');
            this.showModal('room-modal');
            // 自动聚焦到名称输入框
            setTimeout(function() {
                document.getElementById('room-name').focus();
            }, 100);
        },
        
        closeRoomModal: function() {
            this.hideModal('room-modal');
        },
        
        editRoom: function(roomId) {
            // 从数据映射中获取机房信息
            var room = roomsData[roomId];
            if (!room) {
                console.error('Room not found:', roomId);
                alert(i18n.operation_failed);
                return;
            }
            
            document.getElementById('room-modal-title').innerHTML = '🏢 ' + i18n.edit_room;
            document.getElementById('room-id').value = room.id || '';
            document.getElementById('room-name').value = room.name || '';
            document.getElementById('room-description').value = room.description || '';
            this.clearFormErrors('room-form');
            this.showModal('room-modal');
        },
        
        saveRoom: function() {
            var self = this;
            var name = document.getElementById('room-name').value.trim();
            var description = document.getElementById('room-description').value.trim();
            var id = document.getElementById('room-id').value;
            
            // 验证
            this.clearFormErrors('room-form');
            if (!name) {
                this.showFormError('room-name-group');
                document.getElementById('room-name').focus();
                return;
            }
            
            // 显示加载状态
            var saveBtn = document.getElementById('room-save-btn');
            saveBtn.classList.add('loading');
            
            // 发送请求
            var formData = new FormData();
            formData.append('action', 'room.save');
            formData.append('id', id);
            formData.append('name', name);
            formData.append('description', description);
            
            fetch('zabbix.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                saveBtn.classList.remove('loading');
                if (data.success) {
                    self.closeRoomModal();
                    location.reload();
                } else {
                    alert(data.error || data.message || i18n.operation_failed);
                }
            })
            .catch(function(error) {
                saveBtn.classList.remove('loading');
                alert(i18n.operation_failed);
            });
        },
        
        confirmDeleteRoom: function(roomId, roomName, rackCount) {
            this.currentDeleteType = 'room';
            this.currentDeleteId = roomId;
            
            var message = i18n.confirm_delete_room;
            if (rackCount > 0) {
                message += '<br><br><strong style="color:#dc3545;">' + i18n.room_has_racks_warning.replace('{count}', rackCount) + '</strong>';
            }
            
            document.getElementById('confirm-title').textContent = '删除机房';
            document.getElementById('confirm-message').innerHTML = message;
            document.getElementById('confirm-item').textContent = roomName;
            document.getElementById('confirm-btn').onclick = this.executeDelete.bind(this);
            
            this.showModal('confirm-modal');
        },
        
        // ==================== 机柜相关 ====================
        openRackModal: function() {
            document.getElementById('rack-modal-title').innerHTML = '🗄️ ' + i18n.add_rack;
            document.getElementById('rack-id').value = '';
            document.getElementById('rack-name').value = '';
            document.getElementById('rack-height').value = '42';
            document.getElementById('rack-description').value = '';
            
            // 重置下拉框为默认状态（无选中）
            var targetSelect = document.getElementById('rack-room-id');
            // 从任意一个隐藏 select 复制 options，但去掉 selected
            var hiddenSelect = document.querySelector('.rack-room-options');
            if (hiddenSelect) {
                targetSelect.innerHTML = '';
                for (var i = 0; i < hiddenSelect.options.length; i++) {
                    var opt = document.createElement('option');
                    opt.value = hiddenSelect.options[i].value;
                    opt.textContent = hiddenSelect.options[i].textContent;
                    targetSelect.appendChild(opt);
                }
            }
            targetSelect.selectedIndex = 0; // 选中第一个（空选项）
            
            this.clearFormErrors('rack-form');
            this.showModal('rack-modal');
            setTimeout(function() {
                document.getElementById('rack-name').focus();
            }, 100);
        },
        
        closeRackModal: function() {
            this.hideModal('rack-modal');
        },
        
        editRack: function(rackId) {
            // 从数据映射中获取机柜信息
            var rack = racksData[rackId];
            if (!rack) {
                console.error('Rack not found:', rackId);
                alert(i18n.operation_failed);
                return;
            }
            
            document.getElementById('rack-modal-title').innerHTML = '🗄️ ' + i18n.edit_rack;
            document.getElementById('rack-id').value = rack.id || '';
            document.getElementById('rack-name').value = rack.name || '';
            document.getElementById('rack-height').value = rack.height || 42;
            document.getElementById('rack-description').value = rack.description || '';
            
            // 【关键】从 PHP 生成的隐藏 select 复制 options（已带 selected 属性）
            var hiddenSelect = document.querySelector('.rack-room-options[data-rack-id="' + rackId + '"]');
            var targetSelect = document.getElementById('rack-room-id');
            
            if (hiddenSelect) {
                // 清空目标 select 并复制所有 options
                targetSelect.innerHTML = hiddenSelect.innerHTML;
            }
            
            this.clearFormErrors('rack-form');
            this.showModal('rack-modal');
        },
        
        saveRack: function() {
            var self = this;
            var name = document.getElementById('rack-name').value.trim();
            var roomId = document.getElementById('rack-room-id').value;
            var height = document.getElementById('rack-height').value || 42;
            var description = document.getElementById('rack-description').value.trim();
            var id = document.getElementById('rack-id').value;
            
            // 验证
            this.clearFormErrors('rack-form');
            var hasError = false;
            
            if (!name) {
                this.showFormError('rack-name-group');
                if (!hasError) document.getElementById('rack-name').focus();
                hasError = true;
            }
            
            if (!roomId) {
                this.showFormError('rack-room-group');
                if (!hasError) document.getElementById('rack-room-id').focus();
                hasError = true;
            }
            
            if (hasError) return;
            
            // 显示加载状态
            var saveBtn = document.getElementById('rack-save-btn');
            saveBtn.classList.add('loading');
            
            // 发送请求
            var formData = new FormData();
            formData.append('action', 'rack.save');
            formData.append('id', id);
            formData.append('name', name);
            formData.append('room_id', roomId);
            formData.append('height', height);
            formData.append('description', description);
            
            fetch('zabbix.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                saveBtn.classList.remove('loading');
                if (data.success) {
                    self.closeRackModal();
                    location.reload();
                } else {
                    alert(data.error || data.message || i18n.operation_failed);
                }
            })
            .catch(function(error) {
                saveBtn.classList.remove('loading');
                alert(i18n.operation_failed);
            });
        },
        
        confirmDeleteRack: function(rackId, rackName) {
            this.currentDeleteType = 'rack';
            this.currentDeleteId = rackId;
            
            document.getElementById('confirm-title').textContent = '删除机柜';
            document.getElementById('confirm-message').innerHTML = i18n.confirm_delete_rack;
            document.getElementById('confirm-item').textContent = rackName;
            document.getElementById('confirm-btn').onclick = this.executeDelete.bind(this);
            
            this.showModal('confirm-modal');
        },
        
        // ==================== 删除操作 ====================
        executeDelete: function() {
            var self = this;
            var action = this.currentDeleteType === 'room' ? 'room.delete' : 'rack.delete';
            
            var confirmBtn = document.getElementById('confirm-btn');
            confirmBtn.classList.add('loading');
            
            var formData = new FormData();
            formData.append('action', action);
            formData.append('id', this.currentDeleteId);
            
            fetch('zabbix.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                confirmBtn.classList.remove('loading');
                if (data.success) {
                    self.closeConfirmModal();
                    location.reload();
                } else {
                    alert(data.error || data.message || i18n.operation_failed);
                }
            })
            .catch(function(error) {
                confirmBtn.classList.remove('loading');
                alert(i18n.operation_failed);
            });
        },
        
        closeConfirmModal: function() {
            this.hideModal('confirm-modal');
            this.currentDeleteType = null;
            this.currentDeleteId = null;
        },
        
        // ==================== 通用方法 ====================
        showModal: function(modalId) {
            var modal = document.getElementById(modalId);
            modal.classList.add('visible');
            document.body.style.overflow = 'hidden';
        },
        
        hideModal: function(modalId) {
            var modal = document.getElementById(modalId);
            modal.classList.remove('visible');
            document.body.style.overflow = '';
        },
        
        clearFormErrors: function(formId) {
            var form = document.getElementById(formId);
            var groups = form.querySelectorAll('.form-group');
            groups.forEach(function(group) {
                group.classList.remove('has-error');
            });
            var controls = form.querySelectorAll('.form-control');
            controls.forEach(function(control) {
                control.classList.remove('error');
            });
        },
        
        showFormError: function(groupId) {
            var group = document.getElementById(groupId);
            if (group) {
                group.classList.add('has-error');
                var control = group.querySelector('.form-control');
                if (control) control.classList.add('error');
            }
        }
    };
    
    // 暴露到全局
    window.RackManager = RackManager;
    
    // 绑定键盘事件
    document.addEventListener('keydown', function(e) {
        // ESC 关闭弹窗
        if (e.key === 'Escape') {
            var modals = document.querySelectorAll('.modal-overlay.visible');
            modals.forEach(function(modal) {
                modal.classList.remove('visible');
            });
            document.body.style.overflow = '';
        }
        
        // Enter 提交表单
        if (e.key === 'Enter' && !e.shiftKey) {
            var roomModal = document.getElementById('room-modal');
            var rackModal = document.getElementById('rack-modal');
            
            if (roomModal.classList.contains('visible')) {
                e.preventDefault();
                RackManager.saveRoom();
            } else if (rackModal.classList.contains('visible')) {
                var activeElement = document.activeElement;
                if (activeElement.tagName !== 'TEXTAREA') {
                    e.preventDefault();
                    RackManager.saveRack();
                }
            }
        }
    });
    
    // 点击弹窗外部关闭
    document.querySelectorAll('.modal-overlay').forEach(function(modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('visible');
                document.body.style.overflow = '';
            }
        });
    });
    
    // 输入框实时清除错误状态
    document.querySelectorAll('.form-control').forEach(function(control) {
        control.addEventListener('input', function() {
            this.classList.remove('error');
            var group = this.closest('.form-group');
            if (group) group.classList.remove('has-error');
        });
    });
    
})();
</script>
JS;

$html .= $js;

// 渲染页面
$content = new CDiv();
$content->addItem(new CJsScript($html));

ViewRenderer::render($pageTitle, $styleTag, $content);
