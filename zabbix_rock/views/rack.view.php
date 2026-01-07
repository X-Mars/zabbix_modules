<?php
/**
 * 机柜视图页面
 */

// 引入语言管理器和兼容层
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';

use Modules\ZabbixRock\Lib\LanguageManager;
use Modules\ZabbixRock\Lib\ViewRenderer;

$lang = $data['lang'];
$rooms = $data['rooms'];
$racks = $data['racks'];
$currentRoomId = $data['current_room_id'];
$currentRackId = $data['current_rack_id'];
$currentRack = $data['current_rack'];
$hosts = $data['hosts'];
$search = $data['search'];
$searchResults = $data['search_results'];
$hostGroups = $data['host_groups'];

// 构建机柜占用映射
$occupiedSlots = [];
foreach ($hosts as $host) {
    if ($host['u_start'] && $host['u_end']) {
        for ($u = $host['u_start']; $u <= $host['u_end']; $u++) {
            $occupiedSlots[$u] = $host;
        }
    }
}

$pageTitle = LanguageManager::t('rack_view');

// 添加CSS样式 - 参考 CMDB 模块风格
$styleTag = new CTag('style', true, '
/* 页面容器 */
.rack-page-container {
    padding: 20px;
    width: 100%;
    margin: 0 auto;
}

/* 顶部筛选栏 - 现代化水平布局 */
.rack-top-filter {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px 25px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.rack-top-filter form {
    display: flex;
    align-items: flex-end;
    gap: 25px;
    flex-wrap: wrap;
}
.rack-top-filter .filter-item {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.rack-top-filter label {
    font-weight: 600;
    color: #495057;
    font-size: 13px;
    white-space: nowrap;
}
.rack-top-filter select, 
.rack-top-filter input[type="text"] {
    min-width: 200px;
    padding: 10px 14px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 14px;
    background-color: #fff;
    transition: all 0.2s ease;
    height: 42px;
    box-sizing: border-box;
}
.rack-top-filter select:focus, 
.rack-top-filter input[type="text"]:focus {
    outline: none;
    border-color: #80bdff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15);
}
.rack-top-filter select:hover, 
.rack-top-filter input[type="text"]:hover {
    border-color: #adb5bd;
}

/* 统一按钮样式 */
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    text-align: center;
    transition: all 0.2s ease;
    height: 42px;
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.btn-primary {
    color: #fff;
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    box-shadow: 0 2px 4px rgba(0, 123, 255, 0.3);
}
.btn-primary:hover {
    background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
    box-shadow: 0 4px 8px rgba(0, 123, 255, 0.4);
    transform: translateY(-1px);
}
.btn-secondary {
    color: #6c757d;
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
    box-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
}
.btn-success:hover {
    background: linear-gradient(135deg, #1e7e34 0%, #155724 100%);
}
.btn-danger {
    color: #fff;
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
}
.btn-danger:hover {
    background: linear-gradient(135deg, #c82333 0%, #a71d2a 100%);
}

/* 主容器布局 */
.rack-container {
    display: flex;
    gap: 25px;
}
.rack-sidebar {
    width: 320px;
    flex-shrink: 0;
}
.rack-main {
    flex: 1;
    min-width: 400px;
}

/* 侧边栏卡片样式 */
.sidebar-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    margin-bottom: 20px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.sidebar-card-header {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    padding: 12px 16px;
    border-bottom: 1px solid #dee2e6;
    font-weight: 600;
    font-size: 14px;
    color: #495057;
    display: flex;
    align-items: center;
    gap: 8px;
}
.sidebar-card-body {
    padding: 0;
}

/* 主机列表样式 */
.host-list {
    background: #fff;
    max-height: 350px;
    overflow-y: auto;
}
.host-list-item {
    padding: 12px 16px;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: all 0.2s ease;
}
.host-list-item:hover {
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
}
.host-list-item:last-child {
    border-bottom: none;
}
.host-list-item .host-name {
    font-weight: 600;
    color: #212529;
    font-size: 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.host-list-item .host-ip {
    color: #6c757d;
    font-size: 12px;
    margin-top: 4px;
}
.host-list-item .host-position {
    color: #007bff;
    font-size: 12px;
    margin-top: 2px;
    font-weight: 500;
}

/* 删除按钮 */
.btn-remove {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: #fff;
    border: none;
    border-radius: 4px;
    padding: 4px 10px;
    cursor: pointer;
    font-size: 11px;
    font-weight: 500;
    transition: all 0.2s ease;
}
.btn-remove:hover {
    background: linear-gradient(135deg, #c82333 0%, #a71d2a 100%);
    transform: scale(1.05);
}

/* 机柜可视化 */
.rack-visual {
    background: linear-gradient(180deg, #1a252f 0%, #2c3e50 100%);
    border-radius: 12px;
    padding: 20px;
    max-width: 320px;
    margin: 0 auto;
    box-shadow: 0 8px 24px rgba(0,0,0,0.15);
}
.rack-header {
    color: #fff;
    text-align: center;
    font-size: 16px;
    font-weight: 600;
    padding-bottom: 15px;
    border-bottom: 2px solid #34495e;
    margin-bottom: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.rack-units {
    display: flex;
    flex-direction: column;
}
.rack-unit {
    display: flex;
    align-items: center;
    height: 26px;
    margin: 1px 0;
}
.rack-unit-number {
    width: 35px;
    color: #95a5a6;
    font-size: 11px;
    text-align: right;
    padding-right: 10px;
    font-weight: 500;
}
.rack-unit-slot {
    flex: 1;
    height: 100%;
    background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    color: #fff;
    position: relative;
    border: 1px solid #3d566e;
}
.rack-unit-slot:hover {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    border-color: #2980b9;
    box-shadow: 0 0 8px rgba(52, 152, 219, 0.5);
}
.rack-unit-slot.occupied {
    background: linear-gradient(135deg, #27ae60 0%, #1e8449 100%);
    border-color: #1e8449;
    cursor: pointer;
}
.rack-unit-slot.occupied:hover {
    background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
    box-shadow: 0 0 10px rgba(46, 204, 113, 0.6);
    transform: scale(1.02);
}
.rack-unit-slot.occupied.status-disabled {
    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    border-color: #a93226;
}
.rack-unit-slot.occupied.status-disabled:hover {
    background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
    box-shadow: 0 0 10px rgba(231, 76, 60, 0.6);
}
.rack-unit-slot.occupied-start {
    border-top-left-radius: 6px;
    border-top-right-radius: 6px;
}
.rack-unit-slot.occupied-end {
    border-bottom-left-radius: 6px;
    border-bottom-right-radius: 6px;
}
.rack-unit-slot.occupied-middle {
    border-radius: 0;
    border-top: none;
    border-bottom: none;
}
.rack-unit-slot .host-name {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    padding: 0 8px;
    font-weight: 500;
}

/* 提示框 */
.host-tooltip {
    position: fixed;
    background: #fff;
    border: none;
    border-radius: 8px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.2);
    z-index: 1000;
    min-width: 240px;
    display: none;
    padding: 16px;
}
.host-tooltip.visible {
    display: block;
}
.host-tooltip h4 {
    margin: 0 0 12px 0;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
    font-size: 15px;
    color: #212529;
    display: flex;
    align-items: center;
    gap: 8px;
}
.host-tooltip p {
    margin: 8px 0;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.host-tooltip .label {
    color: #6c757d;
    font-weight: 500;
}

/* 无数据提示 */
.no-data {
    text-align: center;
    color: #6c757d;
    padding: 60px 20px;
    font-size: 16px;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px dashed #dee2e6;
}

/* 弹窗样式 */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.6);
    z-index: 2000;
    display: none;
    backdrop-filter: blur(4px);
}
.modal-overlay.visible {
    display: flex;
    align-items: center;
    justify-content: center;
}
.modal-content {
    background: #fff;
    border-radius: 12px;
    width: 520px;
    max-height: 85vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.3s ease;
}
@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
.modal-header {
    padding: 18px 24px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
}
.modal-header h3 {
    margin: 0;
    font-size: 18px;
    color: #212529;
    display: flex;
    align-items: center;
    gap: 10px;
}
.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6c757d;
    padding: 4px 8px;
    border-radius: 4px;
    transition: all 0.2s ease;
}
.modal-close:hover {
    background: #f8f9fa;
    color: #212529;
}
.modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
}
.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #e9ecef;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    background: #f8f9fa;
}

/* 表单样式 */
.form-group {
    margin-bottom: 18px;
}
.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: #495057;
    font-size: 13px;
}
.form-group select, 
.form-group input {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.2s ease;
    box-sizing: border-box;
    background: #fff;
    color: #212529;
}
.form-group select {
    appearance: auto;
    -webkit-appearance: menulist;
    -moz-appearance: menulist;
    cursor: pointer;
}
.form-group select option {
    padding: 8px 12px;
    color: #212529;
    background: #fff;
}
.form-group select:focus, 
.form-group input:focus {
    outline: none;
    border-color: #80bdff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15);
}
.form-row {
    display: flex;
    gap: 15px;
}
.form-row .form-group {
    flex: 1;
}

/* 主机选择列表 */
.host-select-list {
    max-height: 220px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    background: #fff;
}
.host-select-item {
    padding: 10px 14px;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    display: flex;
    align-items: center;
    transition: all 0.2s ease;
}
.host-select-item:hover {
    background: #f8f9fa;
}
.host-select-item.selected {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    border-left: 3px solid #2196f3;
}
.host-select-item .host-info {
    flex: 1;
}
.host-select-item .host-name {
    font-weight: 600;
    color: #212529;
    font-size: 13px;
}
.host-select-item .host-ip {
    font-size: 12px;
    color: #6c757d;
    margin-top: 2px;
}
.host-select-item .host-status {
    font-size: 11px;
    padding: 3px 8px;
    border-radius: 12px;
    font-weight: 500;
}
.host-select-item .host-status.in-rack {
    background: linear-gradient(135deg, #fff3e0 0%, #ffe0b2 100%);
    color: #e65100;
}

/* 统计卡片 */
.stats-row {
    display: flex;
    gap: 15px;
    margin-bottom: 20px;
}
.stat-card {
    flex: 1;
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px 18px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.2s ease;
}
.stat-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transform: translateY(-2px);
}
.stat-icon {
    font-size: 28px;
}
.stat-content {
    flex: 1;
}
.stat-number {
    font-size: 24px;
    font-weight: 700;
    color: #212529;
    display: block;
}
.stat-label {
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* 告警相关样式 */
.rack-unit-slot.has-problem {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
    border-color: #a71d2a !important;
}
.rack-unit-slot.has-problem:hover {
    background: linear-gradient(135deg, #e74c3c 0%, #dc3545 100%) !important;
    box-shadow: 0 0 12px rgba(220, 53, 69, 0.6);
}
.rack-unit-slot.no-problem {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%) !important;
    border-color: #1e7e34 !important;
}
.rack-unit-slot.no-problem:hover {
    background: linear-gradient(135deg, #2ecc71 0%, #28a745 100%) !important;
    box-shadow: 0 0 10px rgba(40, 167, 69, 0.6);
}
.problem-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: #fff;
    color: #dc3545;
    font-size: 10px;
    font-weight: 700;
    min-width: 18px;
    height: 18px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.3);
    cursor: pointer;
    z-index: 10;
    border: 2px solid #dc3545;
}
.problem-badge:hover {
    transform: scale(1.2);
}
.host-slot-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 0 8px;
    position: relative;
}

/* 告警弹窗样式 */
.problem-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.6);
    z-index: 3000;
    display: none;
    backdrop-filter: blur(4px);
}
.problem-modal.visible {
    display: flex;
    align-items: center;
    justify-content: center;
}
.problem-modal-content {
    background: #fff;
    border-radius: 12px;
    width: 600px;
    max-height: 80vh;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.3s ease;
}
.problem-modal-header {
    padding: 16px 20px;
    border-bottom: 1px solid #e9ecef;
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: #fff;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.problem-modal-header h3 {
    margin: 0;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.problem-modal-close {
    background: rgba(255,255,255,0.2);
    border: none;
    color: #fff;
    font-size: 20px;
    cursor: pointer;
    padding: 4px 10px;
    border-radius: 4px;
}
.problem-modal-close:hover {
    background: rgba(255,255,255,0.3);
}
.problem-modal-body {
    padding: 0;
    max-height: 60vh;
    overflow-y: auto;
}
.problem-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.problem-item {
    padding: 14px 20px;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    align-items: flex-start;
    gap: 12px;
}
.problem-item:last-child {
    border-bottom: none;
}
.problem-severity {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    flex-shrink: 0;
    margin-top: 5px;
}
.problem-severity.severity-0 { background: #97aab3; }
.problem-severity.severity-1 { background: #7499ff; }
.problem-severity.severity-2 { background: #ffc859; }
.problem-severity.severity-3 { background: #ffa059; }
.problem-severity.severity-4 { background: #e97659; }
.problem-severity.severity-5 { background: #e45959; }
.problem-info {
    flex: 1;
}
.problem-name {
    font-weight: 600;
    color: #212529;
    font-size: 14px;
    margin-bottom: 4px;
}
.problem-time {
    font-size: 12px;
    color: #6c757d;
}
.no-problems {
    padding: 40px 20px;
    text-align: center;
    color: #28a745;
    font-size: 15px;
}
');

// 页面容器开始
$html = '<div class="rack-page-container">';

// 顶部筛选栏 - 水平布局
$html .= '<div class="rack-top-filter">';
$html .= '<form id="filter-form" method="get" action="zabbix.php">';
$html .= '<input type="hidden" name="action" value="rack.view">';

// 搜索框
$html .= '<div class="filter-item">';
$html .= '<label>🔍 ' . LanguageManager::t('search') . '</label>';
$html .= '<input type="text" name="search" id="search-input" value="' . htmlspecialchars($search) . '" placeholder="' . LanguageManager::t('search_placeholder') . '">';
$html .= '</div>';

// 机房选择
$html .= '<div class="filter-item">';
$html .= '<label>🏢 ' . LanguageManager::t('room') . '</label>';
$html .= '<select name="room_id" id="room-select">';
$html .= '<option value="">' . LanguageManager::t('select_room') . '</option>';
foreach ($rooms as $room) {
    $selected = ($room['id'] === $currentRoomId) ? ' selected' : '';
    $html .= '<option value="' . htmlspecialchars($room['id']) . '"' . $selected . '>' . htmlspecialchars($room['name']) . '</option>';
}
$html .= '</select>';
$html .= '</div>';

// 机柜选择
$html .= '<div class="filter-item">';
$html .= '<label>🗄️ ' . LanguageManager::t('rack') . '</label>';
$html .= '<select name="rack_id" id="rack-select">';
$html .= '<option value="">' . LanguageManager::t('select_rack') . '</option>';
foreach ($racks as $rack) {
    $selected = ($rack['id'] === $currentRackId) ? ' selected' : '';
    $html .= '<option value="' . htmlspecialchars($rack['id']) . '"' . $selected . '>' . htmlspecialchars($rack['name']) . '</option>';
}
$html .= '</select>';
$html .= '</div>';

$html .= '<div class="filter-item">';
$html .= '<label>&nbsp;</label>';
$html .= '<button type="submit" class="btn btn-primary">🔎 ' . LanguageManager::t('filter') . '</button>';
$html .= '</div>';

$html .= '</form>';
$html .= '</div>'; // rack-top-filter

// 统计卡片
$totalHosts = count($hosts);
$usedU = 0;
foreach ($hosts as $host) {
    $usedU += ($host['u_end'] - $host['u_start'] + 1);
}
$rackHeight = $currentRack ? ($currentRack['height'] ?? 42) : 42;
$usagePercent = $rackHeight > 0 ? round(($usedU / $rackHeight) * 100, 1) : 0;

$html .= '<div class="stats-row">';
$html .= '<div class="stat-card"><span class="stat-icon">🖥️</span><div class="stat-content"><span class="stat-number">' . $totalHosts . '</span><span class="stat-label">' . LanguageManager::t('total_hosts') . '</span></div></div>';
$html .= '<div class="stat-card"><span class="stat-icon">📊</span><div class="stat-content"><span class="stat-number">' . $usedU . 'U / ' . $rackHeight . 'U</span><span class="stat-label">' . LanguageManager::t('space_usage') . '</span></div></div>';
$html .= '<div class="stat-card"><span class="stat-icon">📈</span><div class="stat-content"><span class="stat-number">' . $usagePercent . '%</span><span class="stat-label">' . LanguageManager::t('usage_rate') . '</span></div></div>';
$html .= '</div>';

// 主容器
$html .= '<div class="rack-container">';

// 左侧边栏 - 搜索结果和主机列表
$html .= '<div class="rack-sidebar">';

// 搜索结果
if (!empty($searchResults)) {
    $html .= '<div class="sidebar-card">';
    $html .= '<div class="sidebar-card-header">🔍 ' . LanguageManager::t('search_results') . ' (' . count($searchResults) . ')</div>';
    $html .= '<div class="sidebar-card-body"><div class="host-list">';
    foreach ($searchResults as $result) {
        $html .= '<div class="host-list-item">';
        $html .= '<div class="host-name">🖥️ ' . htmlspecialchars($result['name']) . '</div>';
        $html .= '<div class="host-ip">📍 ' . htmlspecialchars($result['main_ip']) . '</div>';
        $html .= '<div class="host-position">📦 ' . htmlspecialchars($result['room_name']) . ' / ' . htmlspecialchars($result['rack_name']) . ' (U' . $result['u_start'] . '-U' . $result['u_end'] . ')</div>';
        $html .= '</div>';
    }
    $html .= '</div></div>';
    $html .= '</div>';
}

// 当前机柜主机列表
if (!empty($hosts)) {
    $html .= '<div class="sidebar-card">';
    $html .= '<div class="sidebar-card-header">📋 ' . LanguageManager::t('hosts_in_rack') . ' (' . count($hosts) . ')</div>';
    $html .= '<div class="sidebar-card-body"><div class="host-list">';
    foreach ($hosts as $host) {
        $html .= '<div class="host-list-item">';
        $html .= '<div class="host-name">🖥️ ' . htmlspecialchars($host['name']);
        $html .= '<button class="btn-remove" onclick="removeHost(\'' . $host['hostid'] . '\')">🗑️ ' . LanguageManager::t('remove') . '</button>';
        $html .= '</div>';
        $html .= '<div class="host-ip">📍 ' . htmlspecialchars($host['main_ip']) . '</div>';
        $html .= '<div class="host-position">📦 U' . $host['u_start'] . '-U' . $host['u_end'] . ' (' . $host['u_height'] . 'U)</div>';
        $html .= '</div>';
    }
    $html .= '</div></div>';
    $html .= '</div>';
}

$html .= '</div>'; // rack-sidebar

// 主区域 - 机柜可视化
$html .= '<div class="rack-main">';

if ($currentRack) {
    $rackHeight = $currentRack['height'] ?? 42;
    
    $html .= '<div class="rack-visual">';
    $html .= '<div class="rack-header">🗄️ ' . htmlspecialchars($currentRack['name']) . ' (' . $rackHeight . 'U)</div>';
    $html .= '<div class="rack-units">';
    
    // 从上到下渲染U位（U42到U1）
    for ($u = $rackHeight; $u >= 1; $u--) {
        $html .= '<div class="rack-unit">';
        $html .= '<div class="rack-unit-number">U' . $u . '</div>';
        
        if (isset($occupiedSlots[$u])) {
            $host = $occupiedSlots[$u];
            $isStart = ($u == $host['u_end']);
            $isEnd = ($u == $host['u_start']);
            $isMiddle = !$isStart && !$isEnd;
            
            // 获取告警数量
            $problemCount = $host['problem_count'] ?? 0;
            $hasProblem = $problemCount > 0;
            
            $classes = ['rack-unit-slot', 'occupied'];
            if ($host['status'] == 1) {
                $classes[] = 'status-disabled';
            } elseif ($hasProblem) {
                $classes[] = 'has-problem';
            } else {
                $classes[] = 'no-problem';
            }
            if ($isStart) $classes[] = 'occupied-start';
            if ($isEnd) $classes[] = 'occupied-end';
            if ($isMiddle) $classes[] = 'occupied-middle';
            
            $hostData = htmlspecialchars(json_encode([
                'hostid' => $host['hostid'],
                'name' => $host['name'],
                'host' => $host['host'],
                'ip' => $host['main_ip'],
                'groups' => implode(', ', $host['groups']),
                'u_start' => $host['u_start'],
                'u_end' => $host['u_end'],
                'status' => $host['status'] == 0 ? LanguageManager::t('enabled') : LanguageManager::t('disabled'),
                'problem_count' => $problemCount
            ]), ENT_QUOTES);
            
            $html .= '<div class="' . implode(' ', $classes) . '" data-host=\'' . $hostData . '\' onclick="openEditModal(this)">';
            if ($isStart) {
                $html .= '<div class="host-slot-content">';
                $html .= '<span class="host-name">' . htmlspecialchars($host['name']) . '</span>';
                if ($hasProblem) {
                    $html .= '<span class="problem-badge" onclick="event.stopPropagation();showProblems(\'' . $host['hostid'] . '\',\'' . htmlspecialchars(addslashes($host['name'])) . '\')" title="' . $problemCount . ' ' . LanguageManager::t('problems') . '">' . $problemCount . '</span>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        } else {
            $html .= '<div class="rack-unit-slot" data-u="' . $u . '" onclick="openAssignModal(' . $u . ')">';
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</div>'; // rack-units
    $html .= '</div>'; // rack-visual
} else {
    $html .= '<div class="no-data">📭 ' . LanguageManager::t('no_rack_selected') . '</div>';
}

$html .= '</div>'; // rack-main
$html .= '</div>'; // rack-container
$html .= '</div>'; // rack-page-container

// 主机信息提示框
$html .= '<div id="host-tooltip" class="host-tooltip"></div>';

// 添加主机弹窗
$html .= '<div id="assign-modal" class="modal-overlay">';
$html .= '<div class="modal-content">';
$html .= '<div class="modal-header">';
$html .= '<h3 id="modal-title">📌 ' . LanguageManager::t('assign_host') . '</h3>';
$html .= '<button class="modal-close" onclick="closeAssignModal()">&times;</button>';
$html .= '</div>';
$html .= '<div class="modal-body">';

// 编辑模式下显示当前主机信息
$html .= '<div id="edit-host-info" class="form-group" style="display:none;background:linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);padding:12px 16px;border-radius:8px;margin-bottom:18px;border-left:4px solid #007bff;">';
$html .= '<div><strong id="edit-host-name" style="font-size:15px;"></strong></div>';
$html .= '<div style="font-size:12px;color:#6c757d;margin-top:4px;" id="edit-host-ip"></div>';
$html .= '</div>';

$html .= '<div id="host-select-section">';
$html .= '<div class="form-group">';
$html .= '<label>📂 ' . LanguageManager::t('host_group') . '</label>';
$html .= '<select id="modal-group-select" onchange="loadHosts()">';
$html .= '<option value="">' . LanguageManager::t('all_groups') . '</option>';
foreach ($hostGroups as $group) {
    $html .= '<option value="' . htmlspecialchars($group['groupid']) . '">' . htmlspecialchars($group['name']) . '</option>';
}
$html .= '</select>';
$html .= '</div>';

$html .= '<div class="form-group">';
$html .= '<label>🔍 ' . LanguageManager::t('search_host') . '</label>';
$html .= '<input type="text" id="modal-host-search" placeholder="' . LanguageManager::t('search_host_placeholder') . '" onkeyup="debounceLoadHosts()">';
$html .= '</div>';

$html .= '<div class="form-group">';
$html .= '<label>' . LanguageManager::t('select_host') . '</label>';
$html .= '<div id="host-select-list" class="host-select-list"></div>';
$html .= '</div>';
$html .= '</div>'; // host-select-section

$html .= '<div class="form-row">';
$html .= '<div class="form-group">';
$html .= '<label>⬆️ ' . LanguageManager::t('u_start') . '</label>';
$html .= '<input type="number" id="modal-u-start" min="1" max="' . ($currentRack['height'] ?? 42) . '">';
$html .= '</div>';
$html .= '<div class="form-group">';
$html .= '<label>⬇️ ' . LanguageManager::t('u_end') . '</label>';
$html .= '<input type="number" id="modal-u-end" min="1" max="' . ($currentRack['height'] ?? 42) . '">';
$html .= '</div>';
$html .= '</div>';

$html .= '</div>'; // modal-body

$html .= '<div class="modal-footer">';
$html .= '<button id="btn-remove-host" class="btn btn-danger" style="display:none;" onclick="removeHostFromModal()">🗑️ ' . LanguageManager::t('remove') . '</button>';
$html .= '<div style="flex:1;"></div>';
$html .= '<button class="btn btn-secondary" onclick="closeAssignModal()">❌ ' . LanguageManager::t('cancel') . '</button>';
$html .= '<button class="btn btn-success" onclick="saveHost()">✅ ' . LanguageManager::t('confirm') . '</button>';
$html .= '</div>';

$html .= '</div>'; // modal-content
$html .= '</div>'; // modal-overlay

// 告警弹窗
$html .= '<div id="problem-modal" class="problem-modal">';
$html .= '<div class="problem-modal-content">';
$html .= '<div class="problem-modal-header">';
$html .= '<h3>🚨 <span id="problem-host-name"></span> - ' . LanguageManager::t('problems') . '</h3>';
$html .= '<button class="problem-modal-close" onclick="closeProblemModal()">&times;</button>';
$html .= '</div>';
$html .= '<div class="problem-modal-body">';
$html .= '<ul class="problem-list" id="problem-list"></ul>';
$html .= '</div>';
$html .= '</div>';
$html .= '</div>';

// JavaScript
$roomId = htmlspecialchars($currentRoomId);
$rackId = htmlspecialchars($currentRackId);

$js = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    var selectedHostId = null;
    var debounceTimer = null;
    var currentRoomId = '{$roomId}';
    var currentRackId = '{$rackId}';
    var isEditMode = false;
    var editingHostId = null;
    
    // 机房选择变化时加载机柜
    var roomSelect = document.getElementById('room-select');
    if (roomSelect) {
        roomSelect.addEventListener('change', function() {
            var roomId = this.value;
            if (roomId) {
                window.location.href = 'zabbix.php?action=rack.view&room_id=' + encodeURIComponent(roomId);
            }
        });
    }
    
    // 机柜选择变化时加载
    var rackSelect = document.getElementById('rack-select');
    if (rackSelect) {
        rackSelect.addEventListener('change', function() {
            var rackId = this.value;
            if (rackId) {
                window.location.href = 'zabbix.php?action=rack.view&room_id=' + encodeURIComponent(currentRoomId) + '&rack_id=' + encodeURIComponent(rackId);
            }
        });
    }
    
    // 主机悬停提示
    var tooltip = document.getElementById('host-tooltip');
    var occupiedSlots = document.querySelectorAll('.rack-unit-slot.occupied');
    
    occupiedSlots.forEach(function(slot) {
        slot.addEventListener('mouseenter', function(e) {
            var hostData = JSON.parse(this.getAttribute('data-host'));
            tooltip.innerHTML = '<h4>' + escapeHtml(hostData.name) + '</h4>' +
                '<p><span class="label">主机名:</span> ' + escapeHtml(hostData.host) + '</p>' +
                '<p><span class="label">IP:</span> ' + escapeHtml(hostData.ip) + '</p>' +
                '<p><span class="label">主机组:</span> ' + escapeHtml(hostData.groups) + '</p>' +
                '<p><span class="label">位置:</span> U' + hostData.u_start + '-U' + hostData.u_end + '</p>' +
                '<p><span class="label">状态:</span> ' + escapeHtml(hostData.status) + '</p>';
            tooltip.classList.add('visible');
        });
        
        slot.addEventListener('mousemove', function(e) {
            tooltip.style.left = (e.pageX + 15) + 'px';
            tooltip.style.top = (e.pageY + 15) + 'px';
        });
        
        slot.addEventListener('mouseleave', function() {
            tooltip.classList.remove('visible');
        });
    });
    
    // 绑定全局函数
    window.openAssignModal = function(u) {
        isEditMode = false;
        editingHostId = null;
        selectedHostId = null;
        document.getElementById('modal-title').textContent = '分配主机';
        document.getElementById('modal-u-start').value = u;
        document.getElementById('modal-u-end').value = u;
        document.getElementById('modal-group-select').value = '';
        document.getElementById('modal-host-search').value = '';
        document.getElementById('host-select-list').innerHTML = '';
        document.getElementById('host-select-section').style.display = 'block';
        document.getElementById('edit-host-info').style.display = 'none';
        document.getElementById('btn-remove-host').style.display = 'none';
        document.getElementById('assign-modal').classList.add('visible');
        loadHosts();
    };
    
    window.openEditModal = function(elem) {
        var hostData = JSON.parse(elem.getAttribute('data-host'));
        isEditMode = true;
        editingHostId = hostData.hostid;
        selectedHostId = hostData.hostid;
        
        document.getElementById('modal-title').textContent = '编辑主机位置';
        document.getElementById('edit-host-name').textContent = hostData.name;
        document.getElementById('edit-host-ip').textContent = hostData.ip + ' | ' + hostData.groups;
        document.getElementById('modal-u-start').value = hostData.u_start;
        document.getElementById('modal-u-end').value = hostData.u_end;
        document.getElementById('host-select-section').style.display = 'none';
        document.getElementById('edit-host-info').style.display = 'block';
        document.getElementById('btn-remove-host').style.display = 'inline-block';
        document.getElementById('assign-modal').classList.add('visible');
    };
    
    window.closeAssignModal = function() {
        document.getElementById('assign-modal').classList.remove('visible');
    };
    
    window.loadHosts = function() {
        var groupId = document.getElementById('modal-group-select').value;
        var search = document.getElementById('modal-host-search').value;
        
        var url = 'zabbix.php?action=hosts.get';
        if (groupId) url += '&groupid=' + encodeURIComponent(groupId);
        if (search) url += '&search=' + encodeURIComponent(search);
        
        fetch(url)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    renderHostList(data.hosts);
                }
            });
    };
    
    window.debounceLoadHosts = function() {
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadHosts, 300);
    };
    
    function renderHostList(hosts) {
        var html = '';
        hosts.forEach(function(host) {
            var statusClass = host.in_rack ? ' in-rack' : '';
            var statusText = host.in_rack ? '(' + host.current_room + '/' + host.current_rack + ')' : '';
            html += '<div class="host-select-item" data-hostid="' + host.hostid + '" onclick="selectHost(this, \'' + host.hostid + '\')">';
            html += '<div class="host-info">';
            html += '<div>' + escapeHtml(host.name) + '</div>';
            html += '<div style="font-size:11px;color:#666">' + escapeHtml(host.main_ip) + '</div>';
            html += '</div>';
            if (host.in_rack) {
                html += '<span class="host-status in-rack">' + statusText + '</span>';
            }
            html += '</div>';
        });
        document.getElementById('host-select-list').innerHTML = html || '<div style="padding:20px;text-align:center;color:#666">无匹配主机</div>';
    }
    
    window.selectHost = function(elem, hostId) {
        document.querySelectorAll('.host-select-item').forEach(function(item) {
            item.classList.remove('selected');
        });
        elem.classList.add('selected');
        selectedHostId = hostId;
    };
    
    window.saveHost = function() {
        var hostId = isEditMode ? editingHostId : selectedHostId;
        
        if (!hostId) {
            alert('请选择一个主机');
            return;
        }
        
        var uStart = parseInt(document.getElementById('modal-u-start').value);
        var uEnd = parseInt(document.getElementById('modal-u-end').value);
        
        if (!uStart || !uEnd || uStart > uEnd) {
            alert('请输入有效的U位范围');
            return;
        }
        
        // 如果是编辑模式，先移除再重新分配
        if (isEditMode) {
            var removeFormData = new FormData();
            removeFormData.append('action', 'host.remove');
            removeFormData.append('hostid', hostId);
            
            fetch('zabbix.php', {
                method: 'POST',
                body: removeFormData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    // 移除成功后重新分配
                    doAssignHost(hostId, uStart, uEnd);
                } else {
                    alert(data.error || data.message || '操作失败');
                }
            });
        } else {
            doAssignHost(hostId, uStart, uEnd);
        }
    };
    
    function doAssignHost(hostId, uStart, uEnd) {
        var formData = new FormData();
        formData.append('action', 'host.assign');
        formData.append('hostid', hostId);
        formData.append('room_id', currentRoomId);
        formData.append('rack_id', currentRackId);
        formData.append('u_start', uStart);
        formData.append('u_end', uEnd);
        
        fetch('zabbix.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || data.message || '操作失败');
            }
        });
    }
    
    window.removeHostFromModal = function() {
        if (!editingHostId) return;
        
        if (!confirm('确定要从机柜中移除此主机吗？')) {
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'host.remove');
        formData.append('hostid', editingHostId);
        
        fetch('zabbix.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || data.message || '操作失败');
            }
        });
    };
    
    window.removeHost = function(hostId) {
        if (!confirm('确定要从机柜中移除此主机吗？')) {
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'host.remove');
        formData.append('hostid', hostId);
        
        fetch('zabbix.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || data.message || '操作失败');
            }
        });
    };
    
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }
    
    // 告警相关函数
    window.showProblems = function(hostId, hostName) {
        document.getElementById('problem-host-name').textContent = hostName;
        document.getElementById('problem-list').innerHTML = '<li class="problem-item" style="justify-content:center;"><span>加载中...</span></li>';
        document.getElementById('problem-modal').classList.add('visible');
        
        // 获取告警详情
        var formData = new FormData();
        formData.append('action', 'host.problems');
        formData.append('hostid', hostId);
        
        fetch('zabbix.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            var listHtml = '';
            if (data.success && data.problems && data.problems.length > 0) {
                data.problems.forEach(function(problem) {
                    listHtml += '<li class="problem-item">';
                    listHtml += '<span class="problem-severity severity-' + problem.severity + '"></span>';
                    listHtml += '<div class="problem-info">';
                    listHtml += '<div class="problem-name">' + escapeHtml(problem.name) + '</div>';
                    listHtml += '<div class="problem-time">🕐 ' + escapeHtml(problem.time) + ' | ' + escapeHtml(problem.severity_name) + '</div>';
                    listHtml += '</div>';
                    listHtml += '</li>';
                });
            } else {
                listHtml = '<li class="no-problems">✅ 当前无活跃告警</li>';
            }
            document.getElementById('problem-list').innerHTML = listHtml;
        })
        .catch(function(error) {
            document.getElementById('problem-list').innerHTML = '<li class="problem-item" style="color:#dc3545;">获取告警失败</li>';
        });
    };
    
    window.closeProblemModal = function() {
        document.getElementById('problem-modal').classList.remove('visible');
    };
    
    // 点击弹窗外部关闭
    document.getElementById('problem-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeProblemModal();
        }
    });
});
</script>
JS;

$html .= $js;

// 使用兼容渲染器显示页面
$content = new CDiv();
$content->addItem(new CJsScript($html));

ViewRenderer::render($pageTitle, $styleTag, $content);
