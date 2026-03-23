<?php
/**
 * 机柜视图页面
 */

// 引入语言管理器和兼容层
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';

use Modules\ZabbixRack\Lib\LanguageManager;
use Modules\ZabbixRack\Lib\ViewRenderer;

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
$showOverview = $data['show_overview'] ?? false;
$allRacksData = $data['all_racks_data'] ?? [];

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
.rack-page-wrapper {
    width: 100%;
    min-height: 100%;
    box-sizing: border-box;
}

.rack-page-container {
    padding: 20px;
    width: 100%;
    max-width: 100%;
    margin: 0 auto;
    box-sizing: border-box;
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
.rack-top-filter z-select, 
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
.rack-top-filter z-select {
    min-width: 200px !important;
}
/* z-select 圆角样式 */
z-select button.focusable {
    border-radius: 6px !important;
}
z-select .list {
    border-radius: 6px !important;
    overflow: hidden;
}
.rack-top-filter z-select:focus, 
.rack-top-filter input[type="text"]:focus {
    outline: none;
    border-color: #80bdff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15);
}
.rack-top-filter z-select:hover, 
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
    overflow: visible;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
@keyframes modalSlideIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
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
    overflow-x: visible;
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
.form-group z-select, 
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
.form-group z-select {
    width: 100% !important;
}
.form-group z-select button.focusable {
    border-radius: 6px !important;
}
.form-group z-select .list {
    border-radius: 6px !important;
    overflow: hidden;
}
.form-group z-select:focus, 
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

/* 根据告警严重程度着色 - Zabbix 标准颜色 */
/* 灾难 - 红色 (#E45959) */
.rack-unit-slot.severity-disaster {
    background: linear-gradient(135deg, #e45959 0%, #dc3545 100%) !important;
    border-color: #c82333 !important;
    animation: pulseAlert 1.5s ease-in-out infinite;
}
.rack-unit-slot.severity-disaster:hover {
    box-shadow: 0 0 12px rgba(228, 89, 89, 0.8);
}

/* 严重 - 浅红 (#E97659) */
.rack-unit-slot.severity-high {
    background: linear-gradient(135deg, #e97659 0%, #e45959 100%) !important;
    border-color: #dc3545 !important;
    animation: pulseAlert 2s ease-in-out infinite;
}
.rack-unit-slot.severity-high:hover {
    box-shadow: 0 0 12px rgba(233, 118, 89, 0.8);
}

/* 一般 - 橙色 (#FFA059) */
.rack-unit-slot.severity-average {
    background: linear-gradient(135deg, #ffa059 0%, #ff8c42 100%) !important;
    border-color: #ff8833 !important;
}
.rack-unit-slot.severity-average:hover {
    box-shadow: 0 0 12px rgba(255, 160, 89, 0.6);
}

/* 警告 - 黄色 (#FFC859) */
.rack-unit-slot.severity-warning {
    background: linear-gradient(135deg, #ffc859 0%, #ffb847 100%) !important;
    border-color: #ffb833 !important;
}
.rack-unit-slot.severity-warning:hover {
    box-shadow: 0 0 12px rgba(255, 200, 89, 0.6);
}

/* 信息 - 蓝色 (#7499FF) */
.rack-unit-slot.severity-info {
    background: linear-gradient(135deg, #7499ff 0%, #5a7fd4 100%) !important;
    border-color: #5a7fd4 !important;
}
.rack-unit-slot.severity-info:hover {
    box-shadow: 0 0 12px rgba(116, 153, 255, 0.6);
}

/* 未分类 - 灰色 (#97AAB3) */
.rack-unit-slot.severity-not-classified {
    background: linear-gradient(135deg, #97aab3 0%, #7a8c99 100%) !important;
    border-color: #7a8c99 !important;
}
.rack-unit-slot.severity-not-classified:hover {
    box-shadow: 0 0 12px rgba(151, 170, 179, 0.6);
}

/* 告警脉冲动画 */
@keyframes pulseAlert {
    0%, 100% {
        box-shadow: 0 0 8px rgba(228, 89, 89, 0.5);
    }
    50% {
        box-shadow: 0 0 12px rgba(228, 89, 89, 0.8);
    }
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
    animation: badgePulse 1s ease-in-out infinite;
}

@keyframes badgePulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.15);
    }
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

/* 告警弹窗样式 - z-index 设为 4000，确保在所有其他弹窗之上 */
.problem-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.6);
    z-index: 4000;
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

/* ==================== 多机柜展示样式 ==================== */
/* 机柜网格布局 */
.racks-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
    padding: 20px 0;
}

/* 机柜卡片 */
.rack-card {
    background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
    border: 2px solid #dee2e6;
    border-radius: 10px;
    padding: 16px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.rack-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 12px 24px rgba(0,0,0,0.15);
    border-color: #007bff;
}

/* 机柜卡片带告警 */
.rack-card.has-alert {
    border-color: #e45959;
    background: linear-gradient(135deg, #fff5f5 0%, #ffe8e8 100%);
}

.rack-card.has-alert:hover {
    box-shadow: 0 12px 28px rgba(228, 89, 89, 0.3);
}

/* 告警等级 - 根据最高严重程度着色 */
.rack-card.severity-0 { border-color: #97aab3; } /* 未分类 */
.rack-card.severity-1 { border-color: #7499ff; } /* 信息 */
.rack-card.severity-2 { border-color: #ffc859; } /* 警告 */
.rack-card.severity-3 { border-color: #ffa059; } /* 一般 */
.rack-card.severity-4 { border-color: #e97659; } /* 严重 */
.rack-card.severity-5 { border-color: #e45959; } /* 灾难 */

/* 颜色背景 */
.rack-card.severity-0 { background: linear-gradient(135deg, #f8fafb 0%, #eef2f5 100%); }
.rack-card.severity-1 { background: linear-gradient(135deg, #f0f4ff 0%, #e8eeff 100%); }
.rack-card.severity-2 { background: linear-gradient(135deg, #fffbf0 0%, #fff9f0 100%); }
.rack-card.severity-3 { background: linear-gradient(135deg, #fff5f0 0%, #fff0e8 100%); }
.rack-card.severity-4 { background: linear-gradient(135deg, #fff0ed 0%, #ffe8e8 100%); }
.rack-card.severity-5 { background: linear-gradient(135deg, #fff0f0 0%, #ffe8e8 100%); }

/* 机柜卡片头部 */
.rack-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.rack-card-title {
    font-weight: 700;
    font-size: 15px;
    color: #212529;
    flex: 1;
}

/* 告警指示器 */
.rack-alert-badge {
    color: #fff;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 12px;
    flex-shrink: 0;
    animation: pulse 2s ease-in-out infinite;
    box-shadow: 0 0 0 0 rgba(228, 89, 89, 0.7);
}

.rack-alert-badge.severity-0 {
    background: linear-gradient(135deg, #97aab3 0%, #768d99 100%);
    box-shadow: 0 0 0 0 rgba(151, 170, 179, 0.7);
}
.rack-alert-badge.severity-1 {
    background: linear-gradient(135deg, #7499ff 0%, #5a7fd4 100%);
    box-shadow: 0 0 0 0 rgba(116, 153, 255, 0.7);
}
.rack-alert-badge.severity-2 {
    background: linear-gradient(135deg, #ffc859 0%, #e6b040 100%);
    box-shadow: 0 0 0 0 rgba(255, 200, 89, 0.7);
}
.rack-alert-badge.severity-3 {
    background: linear-gradient(135deg, #ffa059 0%, #e68840 100%);
    box-shadow: 0 0 0 0 rgba(255, 160, 89, 0.7);
}
.rack-alert-badge.severity-4 {
    background: linear-gradient(135deg, #e97659 0%, #d45e39 100%);
    box-shadow: 0 0 0 0 rgba(233, 118, 89, 0.7);
}
.rack-alert-badge.severity-5 {
    background: linear-gradient(135deg, #e45959 0%, #dc3545 100%);
    box-shadow: 0 0 0 0 rgba(228, 89, 89, 0.7);
}

@keyframes pulse {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(228, 89, 89, 0.7);
    }
    50% {
        box-shadow: 0 0 0 8px rgba(228, 89, 89, 0);
    }
}

/* 机柜卡片内容 */
.rack-card-body {
    font-size: 13px;
    color: #495057;
    margin-bottom: 12px;
    line-height: 1.8;
}

.rack-card-stat {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 0;
}

.rack-card-stat-label {
    color: #6c757d;
}

.rack-card-stat-value {
    font-weight: 600;
    color: #212529;
}

/* 机柜卡片小型可视化 */
.rack-mini-visual {
    display: flex;
    gap: 2px;
    margin: 10px 0;
    height: 60px;
    background: linear-gradient(180deg, #1a252f 0%, #2c3e50 100%);
    border-radius: 6px;
    padding: 4px;
    overflow: hidden;
}

.rack-mini-unit {
    flex: 1;
    background: #34495e;
    border-radius: 2px;
    border: 1px solid #3d566e;
    transition: all 0.2s ease;
    position: relative;
}

.rack-mini-unit.occupied {
    background: linear-gradient(135deg, #27ae60 0%, #1e8449 100%);
    border-color: #1e8449;
}

.rack-mini-unit.occupied.severity-1 {
    background: linear-gradient(135deg, #7499ff 0%, #5a7fd4 100%);
    border-color: #5a7fd4;
}

.rack-mini-unit.occupied.severity-2 {
    background: linear-gradient(135deg, #ffc859 0%, #ffb833 100%);
    border-color: #ffb833;
}

.rack-mini-unit.occupied.severity-3 {
    background: linear-gradient(135deg, #ffa059 0%, #ff8833 100%);
    border-color: #ff8833;
}

.rack-mini-unit.occupied.severity-4 {
    background: linear-gradient(135deg, #e97659 0%, #e55039 100%);
    border-color: #e55039;
}

.rack-mini-unit.occupied.severity-5 {
    background: linear-gradient(135deg, #e45959 0%, #dc3545 100%);
    border-color: #dc3545;
}

.rack-mini-unit:hover {
    flex: 1.2;
    z-index: 10;
    box-shadow: 0 0 8px rgba(0,0,0,0.3);
}

/* 机柜卡片页脚 */
.rack-card-footer {
    display: flex;
    justify-content: flex-end;
    padding-top: 12px;
    border-top: 1px solid rgba(0,0,0,0.05);
}

.rack-card-btn {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: #fff;
    border: none;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
}

.rack-card-btn:hover {
    background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
    transform: scale(1.05);
}

/* 大型机柜弹窗 */
.rack-detail-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 3500;
    display: none;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(6px);
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.rack-detail-modal.visible {
    display: flex;
}

.rack-detail-content {
    background: #fff;
    border-radius: 12px;
    width: 90%;
    max-width: 900px;
    max-height: 90vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 50px rgba(0,0,0,0.3);
    animation: slideUp 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(40px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.rack-detail-header {
    padding: 20px 24px;
    border-bottom: 2px solid #e9ecef;
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.rack-detail-title {
    font-size: 22px;
    font-weight: 700;
    color: #212529;
}

.rack-detail-close {
    background: none;
    border: none;
    font-size: 28px;
    cursor: pointer;
    color: #6c757d;
    padding: 4px;
    transition: all 0.2s ease;
}

.rack-detail-close:hover {
    color: #212529;
    transform: rotate(90deg);
}

.rack-detail-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
}

.rack-detail-visual {
    background: linear-gradient(180deg, #1a252f 0%, #2c3e50 100%);
    border-radius: 10px;
    padding: 24px;
    max-width: 400px;
    margin: 0 auto;
}

/* 加载动画 */
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.loading-spinner {
    border: 3px solid rgba(0,123,255,0.1);
    border-top: 3px solid #007bff;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 1s linear infinite;
    margin: 20px auto;
}

/* ==================== 响应式布局 ==================== */
/* 大屏幕 */
@media (min-width: 1400px) {
    .rack-page-container {
        max-width: 100%;
    }
}

/* 中等屏幕 */
@media (max-width: 1200px) {
    .rack-top-filter form {
        gap: 15px;
    }
    
    .rack-top-filter z-select, 
    .rack-top-filter input[type="text"] {
        min-width: 160px;
    }
    .rack-top-filter z-select {
        min-width: 160px !important;
    }
    
    .rack-overview-grid {
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    }
}

/* 小屏幕 */
@media (max-width: 992px) {
    .rack-page-container {
        padding: 15px;
    }
    
    .rack-top-filter {
        padding: 15px;
    }
    
    .rack-top-filter form {
        flex-direction: column;
        align-items: stretch;
    }
    
    .rack-top-filter .filter-item {
        width: 100%;
    }
    
    .rack-top-filter z-select, 
    .rack-top-filter input[type="text"] {
        width: 100%;
        min-width: auto;
    }
    .rack-top-filter z-select {
        width: 100% !important;
        min-width: auto !important;
    }
    
    .rack-top-filter .btn {
        width: 100%;
        margin-top: 5px;
    }
    
    .rack-overview-grid {
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .rack-main-content {
        flex-direction: column;
    }
}

/* 平板及以下 */
@media (max-width: 768px) {
    .rack-page-container {
        padding: 12px;
    }
    
    .rack-top-filter {
        padding: 12px;
        margin-bottom: 15px;
    }
    
    .rack-overview-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 12px;
    }
    
    .rack-card {
        padding: 12px;
    }
    
    .rack-detail-modal .modal-content {
        width: 95vw;
        max-height: 95vh;
    }
    
    .problem-modal-content {
        width: 95vw;
    }
    
    .host-assign-modal-content {
        width: 95vw;
    }
}

/* 手机屏幕 */
@media (max-width: 480px) {
    .rack-page-container {
        padding: 10px;
    }
    
    .rack-overview-grid {
        grid-template-columns: 1fr;
    }
    
    .rack-card-title {
        font-size: 14px;
    }
    
    .mini-rack-container {
        max-width: 100%;
    }
}

');


// ==================== 构建页面内容（CTag 模式，与 rack.manage.php 保持一致） ====================

$content = (new CDiv())->addClass('rack-page-wrapper');
$container = (new CDiv())->addClass('rack-page-container');

// ── 顶部筛选栏 ──
$filterBar = (new CDiv())->addClass('rack-top-filter');
$filterForm = (new CTag('form', true))
    ->setAttribute('id', 'filter-form')
    ->setAttribute('method', 'get')
    ->setAttribute('action', 'zabbix.php');
$filterForm->addItem(
    (new CInput('hidden', 'action', 'rack.view'))
);

// 搜索框
$searchItem = (new CDiv())->addClass('filter-item');
$searchItem->addItem(
    (new CTag('label', true, '🔍 ' . LanguageManager::t('search')))
);
$searchItem->addItem(
    (new CTag('input', false))
        ->setAttribute('type', 'text')
        ->setAttribute('name', 'search')
        ->setAttribute('id', 'search-input')
        ->setAttribute('value', htmlspecialchars($search))
        ->setAttribute('placeholder', LanguageManager::t('search_placeholder'))
);
$filterForm->addItem($searchItem);

// 机房选择 - 使用 CSelect（z-select）
$roomFilterItem = (new CDiv())->addClass('filter-item');
$roomFilterItem->addItem(
    (new CTag('label', true, '🏢 ' . LanguageManager::t('room')))
);
$roomFilterSelect = (new CSelect('room_id'))
    ->setAttribute('id', 'room-select')
    ->addOption(new CSelectOption('', LanguageManager::t('select_room')));
foreach ($rooms as $room) {
    $roomFilterSelect->addOption(new CSelectOption($room['id'], $room['name']));
}
if ($currentRoomId) {
    $roomFilterSelect->setValue($currentRoomId);
}
$roomFilterItem->addItem($roomFilterSelect);
$filterForm->addItem($roomFilterItem);

// 机柜选择 - 使用 CSelect（z-select）
$rackFilterItem = (new CDiv())->addClass('filter-item');
$rackFilterItem->addItem(
    (new CTag('label', true, '🗄️ ' . LanguageManager::t('rack')))
);
$rackFilterSelect = (new CSelect('rack_id'))
    ->setAttribute('id', 'rack-select')
    ->addOption(new CSelectOption('', LanguageManager::t('select_rack')));
foreach ($racks as $rack) {
    $rackFilterSelect->addOption(new CSelectOption($rack['id'], $rack['name']));
}
if ($currentRackId) {
    $rackFilterSelect->setValue($currentRackId);
}
$rackFilterItem->addItem($rackFilterSelect);
$filterForm->addItem($rackFilterItem);

// 筛选按钮
$filterBtnItem = (new CDiv())->addClass('filter-item');
$filterBtnItem->addItem(
    (new CTag('button', true, '🔎 ' . LanguageManager::t('filter')))
        ->setAttribute('type', 'submit')
        ->addClass('btn btn-primary')
);
$filterForm->addItem($filterBtnItem);

$filterBar->addItem($filterForm);
$container->addItem($filterBar);

// ── 统计卡片 ──
$totalHosts = count($hosts);
$usedU = 0;
foreach ($hosts as $host) {
    $usedU += ($host['u_end'] - $host['u_start'] + 1);
}
$rackHeight = $currentRack ? ($currentRack['height'] ?? 42) : 42;
$usagePercent = $rackHeight > 0 ? round(($usedU / $rackHeight) * 100, 1) : 0;

$statsRow = (new CDiv())->addClass('stats-row');

if ($showOverview) {
    // 概览模式下显示整个机房统计
    $totalRackCount = count($racks);
    $totalHostsInRoom = 0;
    $totalProblemsInRoom = 0;
    foreach ($allRacksData as $rackData) {
        $totalHostsInRoom += $rackData['host_count'];
        $totalProblemsInRoom += $rackData['problem_count'];
    }
    $statsRow->addItem(
        (new CDiv())
            ->addClass('stat-card')
            ->addItem((new CSpan('🗄️'))->addClass('stat-icon'))
            ->addItem(
                (new CDiv())
                    ->addClass('stat-content')
                    ->addItem((new CSpan((string)$totalRackCount))->addClass('stat-number'))
                    ->addItem((new CSpan(LanguageManager::t('total_racks')))->addClass('stat-label'))
            )
    );
    $statsRow->addItem(
        (new CDiv())
            ->addClass('stat-card')
            ->addItem((new CSpan('🖥️'))->addClass('stat-icon'))
            ->addItem(
                (new CDiv())
                    ->addClass('stat-content')
                    ->addItem((new CSpan((string)$totalHostsInRoom))->addClass('stat-number'))
                    ->addItem((new CSpan(LanguageManager::t('total_hosts')))->addClass('stat-label'))
            )
    );
    $statsRow->addItem(
        (new CDiv())
            ->addClass('stat-card')
            ->addItem((new CSpan('🚨'))->addClass('stat-icon'))
            ->addItem(
                (new CDiv())
                    ->addClass('stat-content')
                    ->addItem((new CSpan((string)$totalProblemsInRoom))->addClass('stat-number'))
                    ->addItem((new CSpan(LanguageManager::t('total_problems')))->addClass('stat-label'))
            )
    );
} else {
    // 单机柜模式下显示机柜统计
    $statsRow->addItem(
        (new CDiv())
            ->addClass('stat-card')
            ->addItem((new CSpan('🖥️'))->addClass('stat-icon'))
            ->addItem(
                (new CDiv())
                    ->addClass('stat-content')
                    ->addItem((new CSpan((string)$totalHosts))->addClass('stat-number'))
                    ->addItem((new CSpan(LanguageManager::t('total_hosts')))->addClass('stat-label'))
            )
    );
    $statsRow->addItem(
        (new CDiv())
            ->addClass('stat-card')
            ->addItem((new CSpan('📊'))->addClass('stat-icon'))
            ->addItem(
                (new CDiv())
                    ->addClass('stat-content')
                    ->addItem((new CSpan($usedU . 'U / ' . $rackHeight . 'U'))->addClass('stat-number'))
                    ->addItem((new CSpan(LanguageManager::t('space_usage')))->addClass('stat-label'))
            )
    );
    $statsRow->addItem(
        (new CDiv())
            ->addClass('stat-card')
            ->addItem((new CSpan('📈'))->addClass('stat-icon'))
            ->addItem(
                (new CDiv())
                    ->addClass('stat-content')
                    ->addItem((new CSpan($usagePercent . '%'))->addClass('stat-number'))
                    ->addItem((new CSpan(LanguageManager::t('usage_rate')))->addClass('stat-label'))
            )
    );
}
$container->addItem($statsRow);

// ── 主容器 ──
$rackContainer = (new CDiv())->addClass('rack-container');

if ($showOverview) {
    // ===== 概览模式：显示多个机柜 =====
    $rackMain = (new CDiv())
        ->addClass('rack-main')
        ->setAttribute('style', 'width:100%;margin:0;');

    if (empty($allRacksData)) {
        $rackMain->addItem(
            (new CDiv('📭 ' . LanguageManager::t('no_racks')))->addClass('no-data')
        );
    } else {
        $racksGrid = (new CDiv())->addClass('racks-grid');

        foreach ($allRacksData as $rackData) {
            $severityClass = '';
            $hasAlert = $rackData['problem_count'] > 0;
            if ($hasAlert) {
                $severityClass = 'severity-' . $rackData['max_severity'];
            }

            $cardClasses = 'rack-card';
            if ($hasAlert) {
                $cardClasses .= ' has-alert ' . $severityClass;
            }

            $rackCard = (new CDiv())
                ->addClass($cardClasses)
                ->setAttribute('data-rack-id', htmlspecialchars($rackData['id']))
                ->setAttribute('onclick', "openRackDetail('" . htmlspecialchars($rackData['id']) . "')");

            // 卡片头部
            $cardHeader = (new CDiv())->addClass('rack-card-header');
            $cardHeader->addItem(
                (new CDiv('🗄️ ' . htmlspecialchars($rackData['name'])))->addClass('rack-card-title')
            );
            if ($hasAlert) {
                $cardHeader->addItem(
                    (new CDiv((string)$rackData['problem_count']))
                        ->addClass('rack-alert-badge ' . $severityClass)
                        ->setAttribute('title', $rackData['problem_count'] . ' ' . LanguageManager::t('problems'))
                );
            }
            $rackCard->addItem($cardHeader);

            // 卡片内容
            $cardBody = (new CDiv())->addClass('rack-card-body');

            $hostStat = (new CDiv())->addClass('rack-card-stat');
            $hostStat->addItem(
                (new CSpan('🖥️ ' . LanguageManager::t('hosts') . ':'))->addClass('rack-card-stat-label')
            );
            $hostStat->addItem(
                (new CSpan((string)$rackData['host_count']))->addClass('rack-card-stat-value')
            );
            $cardBody->addItem($hostStat);

            $usageStat = (new CDiv())->addClass('rack-card-stat');
            $usageStat->addItem(
                (new CSpan('📏 ' . LanguageManager::t('usage') . ':'))->addClass('rack-card-stat-label')
            );
            $cardUsagePercent = $rackData['height'] > 0 ? round(($rackData['used_u'] / $rackData['height']) * 100, 1) : 0;
            $usageStat->addItem(
                (new CSpan($rackData['used_u'] . 'U / ' . $rackData['height'] . 'U (' . $cardUsagePercent . '%)'))->addClass('rack-card-stat-value')
            );
            $cardBody->addItem($usageStat);

            // 迷你机柜可视化
            $miniVisual = (new CDiv())->addClass('rack-mini-visual');
            for ($i = 0; $i < $rackData['height']; $i++) {
                $unitClass = 'rack-mini-unit';
                $isOccupied = false;
                $unitSeverity = -1;
                foreach ($rackData['hosts'] as $rhost) {
                    $uPos = $rackData['height'] - $i;
                    if ($uPos >= $rhost['u_start'] && $uPos <= $rhost['u_end']) {
                        $isOccupied = true;
                        if (isset($rhost['max_severity']) && $rhost['max_severity'] >= 0) {
                            $unitSeverity = $rhost['max_severity'];
                        }
                        break;
                    }
                }
                if ($isOccupied) {
                    $unitClass .= ' occupied';
                    if ($unitSeverity >= 0) {
                        $unitClass .= ' severity-' . $unitSeverity;
                    }
                }
                $miniVisual->addItem(
                    (new CDiv())->addClass($unitClass)
                );
            }
            $cardBody->addItem($miniVisual);
            $rackCard->addItem($cardBody);

            // 卡片页脚
            $cardFooter = (new CDiv())->addClass('rack-card-footer');
            $cardFooter->addItem(
                (new CTag('button', true, LanguageManager::t('view_details') . ' →'))
                    ->addClass('rack-card-btn')
                    ->setAttribute('onclick', "event.stopPropagation();viewRackDetail('" . htmlspecialchars($rackData['id']) . "')")
            );
            $rackCard->addItem($cardFooter);

            $racksGrid->addItem($rackCard);
        }

        $rackMain->addItem($racksGrid);
    }

    $rackContainer->addItem($rackMain);

    // 概览模式下的搜索结果
    if (!empty($searchResults)) {
        $searchSection = (new CDiv())->addClass('sidebar-card');
        $searchSection->setAttribute('style', 'margin-top: 20px;');
        $searchSection->addItem(
            (new CDiv('🔍 ' . LanguageManager::t('search_results') . ' (' . count($searchResults) . ')'))
                ->addClass('sidebar-card-header')
        );
        $searchBody = (new CDiv())->addClass('sidebar-card-body');
        $searchList = (new CDiv())->addClass('host-list');
        foreach ($searchResults as $result) {
            $searchItem = (new CDiv())->addClass('host-list-item');
            $searchItem->addItem(
                (new CDiv('🖥️ ' . htmlspecialchars($result['name'])))->addClass('host-name')
            );
            $searchItem->addItem(
                (new CDiv('📍 ' . htmlspecialchars($result['main_ip'])))->addClass('host-ip')
            );
            $searchItem->addItem(
                (new CDiv('📦 ' . htmlspecialchars($result['room_name']) . ' / ' . htmlspecialchars($result['rack_name']) . ' (U' . $result['u_start'] . '-U' . $result['u_end'] . ')'))
                    ->addClass('host-position')
            );
            $searchList->addItem($searchItem);
        }
        $searchBody->addItem($searchList);
        $searchSection->addItem($searchBody);
        $rackContainer->addItem($searchSection);
    }
} else {
    // ===== 单机柜详情模式 =====

    // 左侧边栏
    $sidebar = (new CDiv())->addClass('rack-sidebar');

    // 搜索结果
    if (!empty($searchResults)) {
        $searchCard = (new CDiv())->addClass('sidebar-card');
        $searchCard->addItem(
            (new CDiv('🔍 ' . LanguageManager::t('search_results') . ' (' . count($searchResults) . ')'))
                ->addClass('sidebar-card-header')
        );
        $searchCardBody = (new CDiv())->addClass('sidebar-card-body');
        $searchHostList = (new CDiv())->addClass('host-list');
        foreach ($searchResults as $result) {
            $searchItem = (new CDiv())->addClass('host-list-item');
            $searchItem->addItem(
                (new CDiv('🖥️ ' . htmlspecialchars($result['name'])))->addClass('host-name')
            );
            $searchItem->addItem(
                (new CDiv('📍 ' . htmlspecialchars($result['main_ip'])))->addClass('host-ip')
            );
            $searchItem->addItem(
                (new CDiv('📦 ' . htmlspecialchars($result['room_name']) . ' / ' . htmlspecialchars($result['rack_name']) . ' (U' . $result['u_start'] . '-U' . $result['u_end'] . ')'))
                    ->addClass('host-position')
            );
            $searchHostList->addItem($searchItem);
        }
        $searchCardBody->addItem($searchHostList);
        $searchCard->addItem($searchCardBody);
        $sidebar->addItem($searchCard);
    }

    // 当前机柜主机列表
    if (!empty($hosts)) {
        $hostCard = (new CDiv())->addClass('sidebar-card');
        $hostCard->addItem(
            (new CDiv('📋 ' . LanguageManager::t('hosts_in_rack') . ' (' . count($hosts) . ')'))
                ->addClass('sidebar-card-header')
        );
        $hostCardBody = (new CDiv())->addClass('sidebar-card-body');
        $hostList = (new CDiv())->addClass('host-list');
        foreach ($hosts as $host) {
            $hostItem = (new CDiv())->addClass('host-list-item');

            $hostNameDiv = (new CDiv())->addClass('host-name');
            $hostNameDiv->addItem('🖥️ ' . htmlspecialchars($host['name']));
            $hostNameDiv->addItem(
                (new CTag('button', true, '🗑️ ' . LanguageManager::t('remove')))
                    ->addClass('btn-remove')
                    ->setAttribute('onclick', "removeHost('" . $host['hostid'] . "')")
            );
            $hostItem->addItem($hostNameDiv);

            $hostItem->addItem(
                (new CDiv('📍 ' . htmlspecialchars($host['main_ip'])))->addClass('host-ip')
            );
            $hostItem->addItem(
                (new CDiv('📦 U' . $host['u_start'] . '-U' . $host['u_end'] . ' (' . $host['u_height'] . 'U)'))
                    ->addClass('host-position')
            );
            $hostList->addItem($hostItem);
        }
        $hostCardBody->addItem($hostList);
        $hostCard->addItem($hostCardBody);
        $sidebar->addItem($hostCard);
    }

    $rackContainer->addItem($sidebar);

    // 主区域 - 机柜可视化
    $rackMainDiv = (new CDiv())->addClass('rack-main');

    // 重新构建机柜占用映射
    $occupiedSlots = [];
    foreach ($hosts as $host) {
        if ($host['u_start'] && $host['u_end']) {
            for ($u = $host['u_start']; $u <= $host['u_end']; $u++) {
                $occupiedSlots[$u] = $host;
            }
        }
    }

    if ($currentRack) {
        $rackHeight = $currentRack['height'] ?? 42;

        $rackVisual = (new CDiv())->addClass('rack-visual');
        $rackVisual->addItem(
            (new CDiv('🗄️ ' . htmlspecialchars($currentRack['name']) . ' (' . $rackHeight . 'U)'))
                ->addClass('rack-header')
        );

        $rackUnits = (new CDiv())->addClass('rack-units');

        // 从上到下渲染U位（U42到U1）
        for ($u = $rackHeight; $u >= 1; $u--) {
            $unitRow = (new CDiv())->addClass('rack-unit');
            $unitRow->addItem(
                (new CDiv('U' . $u))->addClass('rack-unit-number')
            );

            if (isset($occupiedSlots[$u])) {
                $host = $occupiedSlots[$u];
                $isStart = ($u == $host['u_end']);
                $isEnd = ($u == $host['u_start']);
                $isMiddle = !$isStart && !$isEnd;

                $problemCount = $host['problem_count'] ?? 0;
                $maxSeverity = $host['max_severity'] ?? -1;
                $hasProblem = $problemCount > 0;

                $slotClasses = ['rack-unit-slot', 'occupied'];

                if ($host['status'] == 1) {
                    $slotClasses[] = 'status-disabled';
                } elseif ($hasProblem && $maxSeverity >= 0) {
                    switch ($maxSeverity) {
                        case 5: $slotClasses[] = 'severity-disaster'; break;
                        case 4: $slotClasses[] = 'severity-high'; break;
                        case 3: $slotClasses[] = 'severity-average'; break;
                        case 2: $slotClasses[] = 'severity-warning'; break;
                        case 1: $slotClasses[] = 'severity-info'; break;
                        case 0: $slotClasses[] = 'severity-not-classified'; break;
                        default: $slotClasses[] = 'has-problem';
                    }
                } else {
                    $slotClasses[] = 'no-problem';
                }

                if ($isStart) $slotClasses[] = 'occupied-start';
                if ($isEnd) $slotClasses[] = 'occupied-end';
                if ($isMiddle) $slotClasses[] = 'occupied-middle';

                $hostDataJson = json_encode([
                    'hostid' => $host['hostid'],
                    'name' => $host['name'],
                    'host' => $host['host'],
                    'ip' => $host['main_ip'],
                    'groups' => implode(', ', $host['groups']),
                    'u_start' => $host['u_start'],
                    'u_end' => $host['u_end'],
                    'status' => $host['status'] == 0 ? LanguageManager::t('enabled') : LanguageManager::t('disabled'),
                    'problem_count' => $problemCount
                ]);

                $slotDiv = (new CDiv())
                    ->addClass(implode(' ', $slotClasses))
                    ->setAttribute('data-host', $hostDataJson)
                    ->setAttribute('onclick', 'openEditModal(this)');

                if ($isStart) {
                    $slotContent = (new CDiv())->addClass('host-slot-content');
                    $slotContent->addItem(
                        (new CSpan(htmlspecialchars($host['name'])))->addClass('host-name')
                    );
                    if ($hasProblem) {
                        $slotContent->addItem(
                            (new CSpan((string)$problemCount))
                                ->addClass('problem-badge')
                                ->setAttribute('onclick', "event.stopPropagation();showProblems('" . $host['hostid'] . "','" . htmlspecialchars(addslashes($host['name'])) . "')")
                                ->setAttribute('title', $problemCount . ' ' . LanguageManager::t('problems'))
                        );
                    }
                    $slotDiv->addItem($slotContent);
                }

                $unitRow->addItem($slotDiv);
            } else {
                $unitRow->addItem(
                    (new CDiv())
                        ->addClass('rack-unit-slot')
                        ->setAttribute('data-u', (string)$u)
                        ->setAttribute('onclick', 'openAssignModal(' . $u . ')')
                );
            }

            $rackUnits->addItem($unitRow);
        }

        $rackVisual->addItem($rackUnits);
        $rackMainDiv->addItem($rackVisual);
    } else {
        $rackMainDiv->addItem(
            (new CDiv('📭 ' . LanguageManager::t('no_rack_selected')))->addClass('no-data')
        );
    }

    $rackContainer->addItem($rackMainDiv);
}

$container->addItem($rackContainer);
$content->addItem($container);

// ==================== 主机信息提示框 ====================
$content->addItem(
    (new CDiv())
        ->setAttribute('id', 'host-tooltip')
        ->addClass('host-tooltip')
);

// ==================== 大型机柜弹窗（概览模式下点击卡片时显示） ====================
$rackDetailModal = (new CDiv())
    ->setAttribute('id', 'rack-detail-modal')
    ->addClass('rack-detail-modal');

$rackDetailContent = (new CDiv())->addClass('rack-detail-content');

$rackDetailHeader = (new CDiv())->addClass('rack-detail-header');
$rackDetailTitle = (new CDiv())->addClass('rack-detail-title');
$rackDetailTitle->addItem('🗄️ ');
$rackDetailTitle->addItem(
    (new CSpan())->setAttribute('id', 'detail-rack-name')
);
$rackDetailHeader->addItem($rackDetailTitle);
$rackDetailHeader->addItem(
    (new CTag('button', true, '×'))
        ->addClass('rack-detail-close')
        ->setAttribute('onclick', 'closeRackDetail()')
);
$rackDetailContent->addItem($rackDetailHeader);

$rackDetailBody = (new CDiv())->addClass('rack-detail-body');
$rackDetailBody->addItem(
    (new CDiv())
        ->setAttribute('id', 'detail-rack-visual')
        ->addClass('rack-detail-visual')
);
$rackDetailContent->addItem($rackDetailBody);

$rackDetailModal->addItem($rackDetailContent);
$content->addItem($rackDetailModal);

// ==================== 分配/编辑主机弹窗 ====================
$assignModal = (new CDiv())
    ->setAttribute('id', 'assign-modal')
    ->addClass('modal-overlay');

$assignModalContent = (new CDiv())->addClass('modal-content');

// 弹窗头部
$assignModalContent->addItem(
    (new CDiv())
        ->addClass('modal-header')
        ->addItem(
            (new CTag('h3', true))
                ->setAttribute('id', 'modal-title')
                ->addItem('📌 ' . LanguageManager::t('assign_host'))
        )
        ->addItem(
            (new CTag('button', true, '×'))
                ->addClass('modal-close')
                ->setAttribute('onclick', 'closeAssignModal()')
        )
);

// 弹窗主体
$assignModalBody = (new CDiv())->addClass('modal-body');

// 编辑模式下显示当前主机信息
$editHostInfo = (new CDiv())
    ->setAttribute('id', 'edit-host-info')
    ->addClass('form-group')
    ->setAttribute('style', 'display:none;background:linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);padding:12px 16px;border-radius:8px;margin-bottom:18px;border-left:4px solid #007bff;');
$editHostInfo->addItem(
    (new CDiv())
        ->addItem(
            (new CTag('strong', true, ''))
                ->setAttribute('id', 'edit-host-name')
                ->setAttribute('style', 'font-size:15px;')
        )
);
$editHostInfo->addItem(
    (new CDiv())
        ->setAttribute('style', 'font-size:12px;color:#6c757d;margin-top:4px;')
        ->setAttribute('id', 'edit-host-ip')
);
$assignModalBody->addItem($editHostInfo);

// 主机选择区域
$hostSelectSection = (new CDiv())->setAttribute('id', 'host-select-section');

// 主机组选择 - 使用 CSelect（z-select）
$groupSelectGroup = (new CDiv())->addClass('form-group');
$groupSelectGroup->addItem(
    (new CTag('label', true, '📂 ' . LanguageManager::t('host_group')))
);
$groupSelect = (new CSelect('group'))
    ->setAttribute('id', 'modal-group-select')
    ->addOption(new CSelectOption('', LanguageManager::t('all_groups')));
foreach ($hostGroups as $group) {
    $groupSelect->addOption(new CSelectOption($group['groupid'], $group['name']));
}
$groupSelectGroup->addItem($groupSelect);
$hostSelectSection->addItem($groupSelectGroup);

// 搜索主机
$hostSearchGroup = (new CDiv())->addClass('form-group');
$hostSearchGroup->addItem(
    (new CTag('label', true, '🔍 ' . LanguageManager::t('search_host')))
);
$hostSearchGroup->addItem(
    (new CTag('input', false))
        ->setAttribute('type', 'text')
        ->setAttribute('id', 'modal-host-search')
        ->setAttribute('placeholder', LanguageManager::t('search_host_placeholder'))
        ->setAttribute('onkeyup', 'debounceLoadHosts()')
);
$hostSelectSection->addItem($hostSearchGroup);

// 主机列表
$hostListGroup = (new CDiv())->addClass('form-group');
$hostListGroup->addItem(
    (new CTag('label', true, LanguageManager::t('select_host')))
);
$hostListGroup->addItem(
    (new CDiv())
        ->setAttribute('id', 'host-select-list')
        ->addClass('host-select-list')
);
$hostSelectSection->addItem($hostListGroup);
$assignModalBody->addItem($hostSelectSection);

// U位范围
$maxU = $currentRack['height'] ?? 42;
$formRow = (new CDiv())->addClass('form-row');

$uStartGroup = (new CDiv())->addClass('form-group');
$uStartGroup->addItem(
    (new CTag('label', true, '⬆️ ' . LanguageManager::t('u_start')))
);
$uStartGroup->addItem(
    (new CTag('input', false))
        ->setAttribute('type', 'number')
        ->setAttribute('id', 'modal-u-start')
        ->setAttribute('min', '1')
        ->setAttribute('max', (string)$maxU)
);
$formRow->addItem($uStartGroup);

$uEndGroup = (new CDiv())->addClass('form-group');
$uEndGroup->addItem(
    (new CTag('label', true, '⬇️ ' . LanguageManager::t('u_end')))
);
$uEndGroup->addItem(
    (new CTag('input', false))
        ->setAttribute('type', 'number')
        ->setAttribute('id', 'modal-u-end')
        ->setAttribute('min', '1')
        ->setAttribute('max', (string)$maxU)
);
$formRow->addItem($uEndGroup);
$assignModalBody->addItem($formRow);

$assignModalContent->addItem($assignModalBody);

// 弹窗底部
$assignModalFooter = (new CDiv())->addClass('modal-footer');
$assignModalFooter->addItem(
    (new CTag('button', true, '🗑️ ' . LanguageManager::t('remove')))
        ->setAttribute('id', 'btn-remove-host')
        ->addClass('btn btn-danger')
        ->setAttribute('style', 'display:none;')
        ->setAttribute('onclick', 'removeHostFromModal()')
);
$assignModalFooter->addItem(
    (new CDiv())->setAttribute('style', 'flex:1;')
);
$assignModalFooter->addItem(
    (new CTag('button', true, '❌ ' . LanguageManager::t('cancel')))
        ->addClass('btn btn-secondary')
        ->setAttribute('onclick', 'closeAssignModal()')
);
$assignModalFooter->addItem(
    (new CTag('button', true, '✅ ' . LanguageManager::t('confirm')))
        ->addClass('btn btn-success')
        ->setAttribute('onclick', 'saveHost()')
);
$assignModalContent->addItem($assignModalFooter);

$assignModal->addItem($assignModalContent);
$content->addItem($assignModal);

// ==================== 告警弹窗 ====================
$problemModal = (new CDiv())
    ->setAttribute('id', 'problem-modal')
    ->addClass('problem-modal');

$problemModalContent = (new CDiv())->addClass('problem-modal-content');

// 告警弹窗头部
$problemHeader = (new CDiv())->addClass('problem-modal-header');
$problemH3 = (new CTag('h3', true));
$problemH3->addItem('🚨 ');
$problemH3->addItem((new CSpan())->setAttribute('id', 'problem-host-name'));
$problemH3->addItem(' - ' . LanguageManager::t('problems'));
$problemHeader->addItem($problemH3);
$problemHeader->addItem(
    (new CTag('button', true, '×'))
        ->addClass('problem-modal-close')
        ->setAttribute('onclick', 'closeProblemModal()')
);
$problemModalContent->addItem($problemHeader);

// 告警弹窗主体
$problemBody = (new CDiv())->addClass('problem-modal-body');
$problemBody->addItem(
    (new CTag('ul', true))
        ->addClass('problem-list')
        ->setAttribute('id', 'problem-list')
);
$problemModalContent->addItem($problemBody);

$problemModal->addItem($problemModalContent);
$content->addItem($problemModal);

// ==================== JavaScript ====================
$roomId = htmlspecialchars($currentRoomId);
$rackId = htmlspecialchars($currentRackId);
$allRacksDataJson = json_encode($allRacksData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$i18n = [
    'host_name_label' => LanguageManager::t('host_name_label'),
    'ip_label' => LanguageManager::t('ip_label'),
    'host_group_label' => LanguageManager::t('host_group_label'),
    'position_label' => LanguageManager::t('position_label'),
    'status_label' => LanguageManager::t('status_label'),
    'unnamed_host' => LanguageManager::t('unnamed_host'),
    'enabled' => LanguageManager::t('enabled'),
    'disabled' => LanguageManager::t('disabled'),
    'edit_host_position' => LanguageManager::t('edit_host_position'),
    'assign_host' => LanguageManager::t('assign_host'),
    'no_matching_hosts' => LanguageManager::t('no_matching_hosts'),
    'select_a_host' => LanguageManager::t('select_a_host'),
    'invalid_u_range' => LanguageManager::t('invalid_u_range'),
    'operation_failed' => LanguageManager::t('operation_failed'),
    'confirm_remove_host' => LanguageManager::t('confirm_remove_host'),
    'loading' => LanguageManager::t('loading'),
    'no_active_problems' => LanguageManager::t('no_active_problems'),
    'fetch_problems_failed' => LanguageManager::t('fetch_problems_failed'),
];
$i18nJson = json_encode($i18n, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$content->addItem(new CJsScript('<script>
(function() {
    "use strict";

    var allRacksData = ' . $allRacksDataJson . ';
    var i18n = ' . $i18nJson . ';

    var selectedHostId = null;
    var debounceTimer = null;
    var currentRoomId = "' . $roomId . '";
    var currentRackId = "' . $rackId . '";
    var isEditMode = false;
    var editingHostId = null;
    var detailRackData = null;

    // HTML 转义
    function escapeHtml(text) {
        if (!text) return "";
        var div = document.createElement("div");
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // ============ 筛选栏联动（z-select 事件监听） ============
    var roomSelect = document.getElementById("room-select");
    if (roomSelect) {
        roomSelect.addEventListener("change", function() {
            var roomId = this.value;
            if (roomId) {
                window.location.href = "zabbix.php?action=rack.view&room_id=" + encodeURIComponent(roomId);
            }
        });
    }

    var rackSelect = document.getElementById("rack-select");
    if (rackSelect) {
        rackSelect.addEventListener("change", function() {
            var rackId = this.value;
            if (rackId) {
                window.location.href = "zabbix.php?action=rack.view&room_id=" + encodeURIComponent(currentRoomId) + "&rack_id=" + encodeURIComponent(rackId);
            }
        });
    }

    // 主机组下拉框（z-select）change 事件 → 替代原来的 onchange="loadHosts()"
    var groupSelect = document.getElementById("modal-group-select");
    if (groupSelect) {
        groupSelect.addEventListener("change", function() {
            loadHosts();
        });
    }

    // ============ 主机悬停提示 ============
    var tooltip = document.getElementById("host-tooltip");
    var occupiedSlots = document.querySelectorAll(".rack-unit-slot.occupied");

    occupiedSlots.forEach(function(slot) {
        slot.addEventListener("mouseenter", function(e) {
            var hostData = JSON.parse(this.getAttribute("data-host"));
            tooltip.innerHTML = "<h4>" + escapeHtml(hostData.name) + "</h4>" +
                "<p><span class=\\"label\\">" + i18n.host_name_label + "</span> " + escapeHtml(hostData.host) + "</p>" +
                "<p><span class=\\"label\\">" + i18n.ip_label + "</span> " + escapeHtml(hostData.ip) + "</p>" +
                "<p><span class=\\"label\\">" + i18n.host_group_label + "</span> " + escapeHtml(hostData.groups) + "</p>" +
                "<p><span class=\\"label\\">" + i18n.position_label + "</span> U" + hostData.u_start + "-U" + hostData.u_end + "</p>" +
                "<p><span class=\\"label\\">" + i18n.status_label + "</span> " + escapeHtml(hostData.status) + "</p>";
            tooltip.classList.add("visible");
        });

        slot.addEventListener("mousemove", function(e) {
            tooltip.style.left = (e.clientX + 15) + "px";
            tooltip.style.top = (e.clientY + 15) + "px";
        });

        slot.addEventListener("mouseleave", function() {
            tooltip.classList.remove("visible");
        });
    });

    // ============ 概览模式 - 多机柜展示 ============
    window.openRackDetail = function(rackId) {
        viewRackDetail(rackId);
    };

    window.viewRackDetail = function(rackId) {
        var rackData = null;
        for (var i = 0; i < allRacksData.length; i++) {
            if (allRacksData[i].id === rackId) {
                rackData = allRacksData[i];
                break;
            }
        }
        if (!rackData) {
            console.error("viewRackDetail: rackData not found for id:", rackId);
            return;
        }

        var hosts = rackData.hosts || [];
        if (!Array.isArray(hosts)) {
            console.error("viewRackDetail: hosts is not an array:", hosts);
            hosts = [];
        }

        // 构建 U 位映射
        var occupiedMap = {};
        for (var i = 0; i < hosts.length; i++) {
            var host = hosts[i];
            var uStart = parseInt(host.u_start);
            var uEnd = parseInt(host.u_end);
            if (uStart > 0 && uEnd > 0) {
                for (var u = uStart; u <= uEnd; u++) {
                    occupiedMap[u] = host;
                }
            }
        }

        detailRackData = rackData;
        currentRackId = rackData.id;
        currentRoomId = rackData.room_id;

        document.getElementById("detail-rack-name").textContent = rackData.name;

        var rackHeight = parseInt(rackData.height) || 42;
        var html = "<div class=\\"rack-visual\\" style=\\"max-width:600px;margin:0 auto;\\">";
        html += "<div class=\\"rack-header\\">🗄️ " + escapeHtml(rackData.name) + " (" + rackHeight + "U)</div>";
        html += "<div class=\\"rack-units\\">";

        for (var u = rackHeight; u >= 1; u--) {
            html += "<div class=\\"rack-unit\\">";
            html += "<div class=\\"rack-unit-number\\">U" + u + "</div>";

            if (occupiedMap[u]) {
                var host = occupiedMap[u];
                var uStart = parseInt(host.u_start);
                var uEnd = parseInt(host.u_end);
                var isStart = (u == uEnd);
                var isEnd = (u == uStart);
                var isMiddle = !isStart && !isEnd;
                var hostUHeight = uEnd - uStart + 1;

                var hostName = host.name || host.host || i18n.unnamed_host;
                var hostId = host.hostid || "";
                var problemCount = parseInt(host.problem_count) || 0;
                var maxSeverity = parseInt(host.max_severity);
                if (isNaN(maxSeverity)) maxSeverity = -1;

                var classes = ["rack-unit-slot", "occupied"];

                var hostStatus = parseInt(host.status);
                if (hostStatus === 1) {
                    classes.push("status-disabled");
                } else if (problemCount > 0 && maxSeverity >= 0) {
                    switch (maxSeverity) {
                        case 5: classes.push("severity-disaster"); break;
                        case 4: classes.push("severity-high"); break;
                        case 3: classes.push("severity-average"); break;
                        case 2: classes.push("severity-warning"); break;
                        case 1: classes.push("severity-info"); break;
                        case 0: classes.push("severity-not-classified"); break;
                        default: classes.push("has-problem");
                    }
                } else {
                    classes.push("no-problem");
                }

                if (isStart) classes.push("occupied-start");
                if (isEnd) classes.push("occupied-end");
                if (isMiddle) classes.push("occupied-middle");

                var hostDataJson = JSON.stringify({
                    hostid: hostId,
                    name: hostName,
                    host: host.host || hostName,
                    ip: host.ip || host.main_ip || "",
                    groups: host.groups || "",
                    u_start: uStart,
                    u_end: uEnd,
                    status: hostStatus === 0 ? i18n.enabled : i18n.disabled,
                    problem_count: problemCount
                }).replace(/\x27/g, "\\\x27").replace(/"/g, "&quot;");

                html += "<div class=\\"" + classes.join(" ") + "\\" data-host=\\"" + hostDataJson + "\\" onclick=\\"openEditModalFromDetail(this)\\" style=\\"cursor:pointer;\\">";

                if (isStart) {
                    html += "<div class=\\"host-slot-content\\">";
                    var displayName = escapeHtml(hostName);
                    if (hostUHeight > 1) {
                        displayName += " <span style=\\"font-size:10px;opacity:0.8;\\">(" + hostUHeight + "U)</span>";
                    }
                    html += "<span class=\\"host-name\\">" + displayName + "</span>";
                    if (problemCount > 0) {
                        html += "<span class=\\"problem-badge\\" onclick=\\"event.stopPropagation();showProblemsFromDetail(\x27" + hostId + "\x27,\x27" + escapeHtml(hostName).replace(/\x27/g, "\\\x27") + "\x27)\\">" + problemCount + "</span>";
                    }
                    html += "</div>";
                }
                html += "</div>";
            } else {
                html += "<div class=\\"rack-unit-slot\\" data-u=\\"" + u + "\\" onclick=\\"openAssignModalFromDetail(" + u + ")\\" style=\\"cursor:pointer;\\"></div>";
            }

            html += "</div>";
        }

        html += "</div></div>";
        document.getElementById("detail-rack-visual").innerHTML = html;
        document.getElementById("rack-detail-modal").classList.add("visible");
    };

    window.openAssignModalFromDetail = function(u) {
        closeRackDetail();
        openAssignModal(u);
    };

    window.openEditModalFromDetail = function(elem) {
        closeRackDetail();
        var hostData = JSON.parse(elem.getAttribute("data-host"));

        isEditMode = true;
        editingHostId = hostData.hostid;
        selectedHostId = hostData.hostid;

        document.getElementById("modal-title").textContent = i18n.edit_host_position;
        document.getElementById("edit-host-name").textContent = hostData.name;
        document.getElementById("edit-host-ip").textContent = (hostData.ip || "-") + " | " + (hostData.groups || "-");
        document.getElementById("modal-u-start").value = hostData.u_start;
        document.getElementById("modal-u-end").value = hostData.u_end;
        document.getElementById("host-select-section").style.display = "none";
        document.getElementById("edit-host-info").style.display = "block";
        document.getElementById("btn-remove-host").style.display = "inline-block";
        document.getElementById("assign-modal").classList.add("visible");
    };

    window.showProblemsFromDetail = function(hostId, hostName) {
        if (typeof showProblems === "function") {
            showProblems(hostId, hostName);
        }
    };

    window.closeRackDetail = function() {
        document.getElementById("rack-detail-modal").classList.remove("visible");
    };

    document.getElementById("rack-detail-modal").addEventListener("click", function(e) {
        if (e.target === this) {
            closeRackDetail();
        }
    });

    // ============ 单机柜管理函数 ============
    window.openAssignModal = function(u) {
        isEditMode = false;
        editingHostId = null;
        selectedHostId = null;
        document.getElementById("modal-title").textContent = i18n.assign_host;
        document.getElementById("modal-u-start").value = u;
        document.getElementById("modal-u-end").value = u;
        document.getElementById("modal-group-select").value = "";
        document.getElementById("modal-host-search").value = "";
        document.getElementById("host-select-list").innerHTML = "";
        document.getElementById("host-select-section").style.display = "block";
        document.getElementById("edit-host-info").style.display = "none";
        document.getElementById("btn-remove-host").style.display = "none";
        document.getElementById("assign-modal").classList.add("visible");
        loadHosts();
    };

    window.openEditModal = function(elem) {
        var hostData = JSON.parse(elem.getAttribute("data-host"));
        isEditMode = true;
        editingHostId = hostData.hostid;
        selectedHostId = hostData.hostid;

        document.getElementById("modal-title").textContent = i18n.edit_host_position;
        document.getElementById("edit-host-name").textContent = hostData.name;
        document.getElementById("edit-host-ip").textContent = hostData.ip + " | " + hostData.groups;
        document.getElementById("modal-u-start").value = hostData.u_start;
        document.getElementById("modal-u-end").value = hostData.u_end;
        document.getElementById("host-select-section").style.display = "none";
        document.getElementById("edit-host-info").style.display = "block";
        document.getElementById("btn-remove-host").style.display = "inline-block";
        document.getElementById("assign-modal").classList.add("visible");
    };

    window.closeAssignModal = function() {
        document.getElementById("assign-modal").classList.remove("visible");
    };

    window.loadHosts = function() {
        var groupId = document.getElementById("modal-group-select").value;
        var search = document.getElementById("modal-host-search").value;

        var url = "zabbix.php?action=hosts.get";
        if (groupId) url += "&groupid=" + encodeURIComponent(groupId);
        if (search) url += "&search=" + encodeURIComponent(search);

        fetch(url)
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    renderHostList(data.hosts);
                }
            })
            .catch(function() {
                document.getElementById("host-select-list").innerHTML = "<div style=\\"padding:20px;text-align:center;color:#dc3545\\">" + i18n.operation_failed + "</div>";
            });
    };

    window.debounceLoadHosts = function() {
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadHosts, 300);
    };

    function renderHostList(hosts) {
        var html = "";
        hosts.forEach(function(host) {
            var statusClass = host.in_rack ? " in-rack" : "";
            var statusText = host.in_rack ? "(" + host.current_room + "/" + host.current_rack + ")" : "";
            html += "<div class=\\"host-select-item\\" data-hostid=\\"" + host.hostid + "\\" onclick=\\"selectHost(this, \x27" + host.hostid + "\x27)\\">";
            html += "<div class=\\"host-info\\">";
            html += "<div>" + escapeHtml(host.name) + "</div>";
            html += "<div style=\\"font-size:11px;color:#666\\">" + escapeHtml(host.main_ip) + "</div>";
            html += "</div>";
            if (host.in_rack) {
                html += "<span class=\\"host-status in-rack\\">" + statusText + "</span>";
            }
            html += "</div>";
        });
        document.getElementById("host-select-list").innerHTML = html || "<div style=\\"padding:20px;text-align:center;color:#666\\">" + i18n.no_matching_hosts + "</div>";
    }

    window.selectHost = function(elem, hostId) {
        document.querySelectorAll(".host-select-item").forEach(function(item) {
            item.classList.remove("selected");
        });
        elem.classList.add("selected");
        selectedHostId = hostId;
    };

    window.saveHost = function() {
        var hostId = isEditMode ? editingHostId : selectedHostId;

        if (!hostId) {
            alert(i18n.select_a_host);
            return;
        }

        var uStart = parseInt(document.getElementById("modal-u-start").value);
        var uEnd = parseInt(document.getElementById("modal-u-end").value);

        if (!uStart || !uEnd || uStart > uEnd) {
            alert(i18n.invalid_u_range);
            return;
        }

        if (isEditMode) {
            var removeFormData = new FormData();
            removeFormData.append("action", "host.remove");
            removeFormData.append("hostid", hostId);

            fetch("zabbix.php", {
                method: "POST",
                body: removeFormData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    doAssignHost(hostId, uStart, uEnd);
                } else {
                    alert(data.error || data.message || i18n.operation_failed);
                }
            })
            .catch(function() {
                alert(i18n.operation_failed);
            });
        } else {
            doAssignHost(hostId, uStart, uEnd);
        }
    };

    function doAssignHost(hostId, uStart, uEnd) {
        var formData = new FormData();
        formData.append("action", "host.assign");
        formData.append("hostid", hostId);
        formData.append("room_id", currentRoomId);
        formData.append("rack_id", currentRackId);
        formData.append("u_start", uStart);
        formData.append("u_end", uEnd);

        fetch("zabbix.php", {
            method: "POST",
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || data.message || i18n.operation_failed);
            }
        })
        .catch(function() {
            alert(i18n.operation_failed);
        });
    }

    window.removeHostFromModal = function() {
        if (!editingHostId) return;

        if (!confirm(i18n.confirm_remove_host)) {
            return;
        }

        var formData = new FormData();
        formData.append("action", "host.remove");
        formData.append("hostid", editingHostId);

        fetch("zabbix.php", {
            method: "POST",
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || data.message || i18n.operation_failed);
            }
        })
        .catch(function() {
            alert(i18n.operation_failed);
        });
    };

    window.removeHost = function(hostId) {
        if (!confirm(i18n.confirm_remove_host)) {
            return;
        }

        var formData = new FormData();
        formData.append("action", "host.remove");
        formData.append("hostid", hostId);

        fetch("zabbix.php", {
            method: "POST",
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || data.message || i18n.operation_failed);
            }
        })
        .catch(function() {
            alert(i18n.operation_failed);
        });
    };

    // ============ 告警相关函数 ============
    window.showProblems = function(hostId, hostName) {
        document.getElementById("problem-host-name").textContent = hostName;
        document.getElementById("problem-list").innerHTML = "<li class=\\"problem-item\\" style=\\"justify-content:center;\\"><span>" + i18n.loading + "</span></li>";
        document.getElementById("problem-modal").classList.add("visible");

        var formData = new FormData();
        formData.append("action", "host.problems");
        formData.append("hostid", hostId);

        fetch("zabbix.php", {
            method: "POST",
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            var listHtml = "";
            if (data.success && data.problems && data.problems.length > 0) {
                data.problems.forEach(function(problem) {
                    listHtml += "<li class=\\"problem-item\\">";
                    listHtml += "<span class=\\"problem-severity severity-" + problem.severity + "\\"></span>";
                    listHtml += "<div class=\\"problem-info\\">";
                    listHtml += "<div class=\\"problem-name\\">" + escapeHtml(problem.name) + "</div>";
                    listHtml += "<div class=\\"problem-time\\">🕐 " + escapeHtml(problem.time) + " | " + escapeHtml(problem.severity_name) + "</div>";
                    listHtml += "</div>";
                    listHtml += "</li>";
                });
            } else {
                listHtml = "<li class=\\"no-problems\\">" + i18n.no_active_problems + "</li>";
            }
            document.getElementById("problem-list").innerHTML = listHtml;
        })
        .catch(function(error) {
            document.getElementById("problem-list").innerHTML = "<li class=\\"problem-item\\" style=\\"color:#dc3545;\\">" + i18n.fetch_problems_failed + "</li>";
        });
    };

    window.closeProblemModal = function() {
        document.getElementById("problem-modal").classList.remove("visible");
    };

    // ============ 键盘事件与弹窗外部关闭 ============
    document.addEventListener("keydown", function(e) {
        if (e.key === "Escape") {
            document.querySelectorAll(".modal-overlay.visible, .problem-modal.visible, .rack-detail-modal.visible").forEach(function(modal) {
                modal.classList.remove("visible");
            });
        }
    });

    document.querySelectorAll(".modal-overlay").forEach(function(modal) {
        modal.addEventListener("click", function(e) {
            if (e.target === this) {
                this.classList.remove("visible");
            }
        });
    });

    document.getElementById("problem-modal").addEventListener("click", function(e) {
        if (e.target === this) {
            closeProblemModal();
        }
    });

    // ============ 告警状态自动刷新 ============
    var REFRESH_INTERVAL = 30000; // 30秒刷新一次

    /**
     * 收集当前页面上所有需要监控的主机ID
     */
    function collectAllHostIds() {
        var hostIds = [];
        // 从概览模式的 allRacksData 中收集
        if (allRacksData && allRacksData.length > 0) {
            for (var i = 0; i < allRacksData.length; i++) {
                var hosts = allRacksData[i].hosts || [];
                for (var j = 0; j < hosts.length; j++) {
                    if (hosts[j].hostid && hostIds.indexOf(hosts[j].hostid) === -1) {
                        hostIds.push(hosts[j].hostid);
                    }
                }
            }
        }
        // 从单机柜模式的 DOM 中收集
        document.querySelectorAll(".rack-unit-slot.occupied[data-host]").forEach(function(slot) {
            try {
                var hostData = JSON.parse(slot.getAttribute("data-host"));
                if (hostData.hostid && hostIds.indexOf(hostData.hostid) === -1) {
                    hostIds.push(hostData.hostid);
                }
            } catch(e) {}
        });
        return hostIds;
    }

    /**
     * 根据严重级别获取 CSS 类名
     */
    function getSeverityClass(severity) {
        switch (parseInt(severity)) {
            case 5: return "severity-disaster";
            case 4: return "severity-high";
            case 3: return "severity-average";
            case 2: return "severity-warning";
            case 1: return "severity-info";
            case 0: return "severity-not-classified";
            default: return "";
        }
    }

    /**
     * 移除元素上的所有告警相关 CSS 类
     */
    function removeSeverityClasses(elem) {
        // 使用正则匹配移除所有 severity- 开头的类以及告警相关类
        // 这比固定列表更稳健，能覆盖所有可能的 severity 类名变体
        var toRemove = [];
        for (var i = 0; i < elem.classList.length; i++) {
            var cls = elem.classList[i];
            if (cls.indexOf("severity-") === 0 || cls === "has-problem" || cls === "no-problem" || cls === "has-alert") {
                toRemove.push(cls);
            }
        }
        for (var j = 0; j < toRemove.length; j++) {
            elem.classList.remove(toRemove[j]);
        }
    }

    /**
     * 更新概览模式中的机柜卡片和 allRacksData 中的告警数据
     */
    function updateOverviewMode(problemData) {
        if (!allRacksData || allRacksData.length === 0) return;

        for (var i = 0; i < allRacksData.length; i++) {
            var rackData = allRacksData[i];
            var hosts = rackData.hosts || [];
            var totalProblems = 0;
            var maxSeverity = -1;

            // 更新每个主机的告警状态
            for (var j = 0; j < hosts.length; j++) {
                var hostId = hosts[j].hostid;
                if (problemData[hostId]) {
                    hosts[j].problem_count = problemData[hostId].problem_count;
                    hosts[j].max_severity = problemData[hostId].max_severity;
                    totalProblems += problemData[hostId].problem_count;
                    if (problemData[hostId].max_severity > maxSeverity) {
                        maxSeverity = problemData[hostId].max_severity;
                    }
                } else {
                    hosts[j].problem_count = 0;
                    hosts[j].max_severity = -1;
                }
            }

            // 更新 allRacksData
            rackData.problem_count = totalProblems;
            rackData.max_severity = maxSeverity;

            // 更新 DOM - 机柜卡片（通过 data-rack-id 精确匹配）
            var rackCards = document.querySelectorAll(".rack-card[data-rack-id]");
            rackCards.forEach(function(card) {
                if (card.getAttribute("data-rack-id") !== rackData.id) return;

                removeSeverityClasses(card);
                // 更新告警徽章
                var badge = card.querySelector(".rack-alert-badge");
                if (totalProblems > 0) {
                    card.classList.add("has-alert");
                    card.classList.add("severity-" + maxSeverity);
                    if (badge) {
                        badge.textContent = totalProblems;
                        badge.setAttribute("title", totalProblems + " problems");
                    } else {
                        // 创建新的告警徽章
                        var header = card.querySelector(".rack-card-header");
                        if (header) {
                            var newBadge = document.createElement("div");
                            newBadge.className = "rack-alert-badge";
                            newBadge.textContent = totalProblems;
                            newBadge.setAttribute("title", totalProblems + " problems");
                            header.appendChild(newBadge);
                        }
                    }
                } else {
                    if (badge) {
                        badge.remove();
                    }
                }

                // 更新迷你可视化中的告警颜色
                var miniUnits = card.querySelectorAll(".rack-mini-unit");
                miniUnits.forEach(function(unit) {
                    removeSeverityClasses(unit);
                });
                // 重新计算迷你可视化的颜色
                var rackHeight = parseInt(rackData.height) || 42;
                for (var k = 0; k < rackHeight; k++) {
                    var uPos = rackHeight - k;
                    var unitElem = miniUnits[k];
                    if (!unitElem) continue;
                    for (var h = 0; h < hosts.length; h++) {
                        if (uPos >= hosts[h].u_start && uPos <= hosts[h].u_end) {
                            if (hosts[h].max_severity >= 0) {
                                unitElem.classList.add("severity-" + hosts[h].max_severity);
                            }
                            break;
                        }
                    }
                }
            });
        }

        // 更新统计卡片中的告警数量
        var statNumbers = document.querySelectorAll(".stat-card .stat-number");
        if (statNumbers.length >= 3) {
            var totalProblemsInRoom = 0;
            for (var r = 0; r < allRacksData.length; r++) {
                totalProblemsInRoom += allRacksData[r].problem_count;
            }
            // 第三个统计卡片是告警数量（概览模式下）
            var statLabels = document.querySelectorAll(".stat-card .stat-label");
            for (var s = 0; s < statLabels.length; s++) {
                var labelParent = statLabels[s].closest(".stat-card");
                if (labelParent && labelParent.querySelector(".stat-icon") &&
                    labelParent.querySelector(".stat-icon").textContent.indexOf("🚨") !== -1) {
                    labelParent.querySelector(".stat-number").textContent = totalProblemsInRoom;
                    break;
                }
            }
        }
    }

    /**
     * 更新单机柜模式中的机架 U 位告警状态
     */
    function updateSingleRackMode(problemData) {
        document.querySelectorAll(".rack-unit-slot.occupied[data-host]").forEach(function(slot) {
            try {
                var hostData = JSON.parse(slot.getAttribute("data-host"));
                var hostId = hostData.hostid;
                if (!hostId || !problemData[hostId]) return;

                var newCount = problemData[hostId].problem_count;
                var newSeverity = problemData[hostId].max_severity;

                // 更新 data-host 属性中的数据
                hostData.problem_count = newCount;
                slot.setAttribute("data-host", JSON.stringify(hostData));

                // 更新 CSS 类
                removeSeverityClasses(slot);
                var hostStatus = parseInt(hostData.status === "disabled" ? 1 : 0) || 0;
                // 检查是否是通过 status 文字判断
                if (slot.classList.contains("status-disabled")) {
                    // 保持禁用状态不变
                } else if (newCount > 0 && newSeverity >= 0) {
                    slot.classList.add(getSeverityClass(newSeverity));
                } else {
                    slot.classList.add("no-problem");
                }

                // 更新告警数量徽章
                var badge = slot.querySelector(".problem-badge");
                if (newCount > 0) {
                    if (badge) {
                        badge.textContent = newCount;
                        badge.setAttribute("title", newCount + " problems");
                    } else {
                        // 需要在 host-slot-content 中添加徽章
                        var content = slot.querySelector(".host-slot-content");
                        if (content) {
                            var hostName = hostData.name || hostData.host || "";
                            var newBadge = document.createElement("span");
                            newBadge.className = "problem-badge";
                            newBadge.textContent = newCount;
                            newBadge.setAttribute("title", newCount + " problems");
                            newBadge.setAttribute("onclick",
                                "event.stopPropagation();showProblems(\x27" + hostId + "\x27,\x27" + escapeHtml(hostName).replace(/\x27/g, "\\\x27") + "\x27)");
                            content.appendChild(newBadge);
                        }
                    }
                } else {
                    if (badge) {
                        badge.remove();
                    }
                }
            } catch(e) {
                console.error("updateSingleRackMode error:", e);
            }
        });
    }

    /**
     * 刷新告警数据的主函数
     */
    function refreshProblems() {
        var hostIds = collectAllHostIds();
        if (hostIds.length === 0) return;

        fetch("zabbix.php?action=rack.problems.refresh&hostids=" + encodeURIComponent(hostIds.join(",")))
            .then(function(response) { return response.json(); })
            .then(function(result) {
                if (!result.success || !result.data) return;

                var problemData = result.data;

                // 更新概览模式
                updateOverviewMode(problemData);

                // 更新单机柜模式
                updateSingleRackMode(problemData);

                // 如果详情弹窗打开着，也更新详情弹窗
                var detailModal = document.getElementById("rack-detail-modal");
                if (detailModal && detailModal.classList.contains("visible") && detailRackData) {
                    // 更新 detailRackData 中的告警状态然后重新渲染
                    var hosts = detailRackData.hosts || [];
                    var needRerender = false;
                    for (var i = 0; i < hosts.length; i++) {
                        var hid = hosts[i].hostid;
                        if (problemData[hid]) {
                            var oldCount = parseInt(hosts[i].problem_count) || 0;
                            var newCount = problemData[hid].problem_count;
                            if (oldCount !== newCount) needRerender = true;
                            hosts[i].problem_count = newCount;
                            hosts[i].max_severity = problemData[hid].max_severity;
                        }
                    }
                    if (needRerender) {
                        viewRackDetail(detailRackData.id);
                    }
                }
            })
            .catch(function(err) {
                console.warn("refreshProblems failed:", err);
            });
    }

    // 启动定时刷新
    setInterval(refreshProblems, REFRESH_INTERVAL);

})();
</script>'));

// 使用兼容渲染器显示页面
ViewRenderer::render($pageTitle, $styleTag, $content);
