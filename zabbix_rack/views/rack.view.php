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
    background: linear-gradient(135deg, #e45959 0%, #dc3545 100%);
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
    
    .rack-top-filter select, 
    .rack-top-filter input[type="text"] {
        min-width: 160px;
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
    
    .rack-top-filter select, 
    .rack-top-filter input[type="text"] {
        width: 100%;
        min-width: auto;
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

// 页面容器开始
$html = '<div class="rack-page-wrapper">';
$html .= '<div class="rack-page-container">';

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
if ($showOverview) {
    // 概览模式下显示整个机房统计
    $totalRackCount = count($racks);
    $totalHostsInRoom = 0;
    $totalProblemsInRoom = 0;
    foreach ($allRacksData as $rackData) {
        $totalHostsInRoom += $rackData['host_count'];
        $totalProblemsInRoom += $rackData['problem_count'];
    }
    $html .= '<div class="stat-card"><span class="stat-icon">🗄️</span><div class="stat-content"><span class="stat-number">' . $totalRackCount . '</span><span class="stat-label">' . LanguageManager::t('total_racks') . '</span></div></div>';
    $html .= '<div class="stat-card"><span class="stat-icon">🖥️</span><div class="stat-content"><span class="stat-number">' . $totalHostsInRoom . '</span><span class="stat-label">' . LanguageManager::t('total_hosts') . '</span></div></div>';
    $html .= '<div class="stat-card"><span class="stat-icon">🚨</span><div class="stat-content"><span class="stat-number">' . $totalProblemsInRoom . '</span><span class="stat-label">' . LanguageManager::t('total_problems') . '</span></div></div>';
} else {
    // 单机柜模式下显示机柜统计
    $html .= '<div class="stat-card"><span class="stat-icon">🖥️</span><div class="stat-content"><span class="stat-number">' . $totalHosts . '</span><span class="stat-label">' . LanguageManager::t('total_hosts') . '</span></div></div>';
    $html .= '<div class="stat-card"><span class="stat-icon">📊</span><div class="stat-content"><span class="stat-number">' . $usedU . 'U / ' . $rackHeight . 'U</span><span class="stat-label">' . LanguageManager::t('space_usage') . '</span></div></div>';
    $html .= '<div class="stat-card"><span class="stat-icon">📈</span><div class="stat-content"><span class="stat-number">' . $usagePercent . '%</span><span class="stat-label">' . LanguageManager::t('usage_rate') . '</span></div></div>';
}
$html .= '</div>';

// 主容器
$html .= '<div class="rack-container">';

if ($showOverview) {
    // ===== 概览模式：显示多个机柜 =====
    $html .= '<div class="rack-main" style="width:100%;margin:0;">';
    
    if (empty($allRacksData)) {
        $html .= '<div class="no-data">📭 ' . LanguageManager::t('no_racks') . '</div>';
    } else {
        $html .= '<div class="racks-grid">';
        
        foreach ($allRacksData as $rackData) {
            // 确定卡片的告警等级和样式
            $severityClass = '';
            $hasAlert = $rackData['problem_count'] > 0;
            
            if ($hasAlert) {
                $severityClass = 'severity-' . $rackData['max_severity'];
            }
            
            $cardClasses = ['rack-card'];
            if ($hasAlert) {
                $cardClasses[] = 'has-alert';
                $cardClasses[] = $severityClass;
            }
            
            $rackJson = htmlspecialchars(json_encode($rackData), ENT_QUOTES);
            
            $html .= '<div class="' . implode(' ', $cardClasses) . '" onclick="openRackDetail(\'' . htmlspecialchars($rackData['id']) . '\')">';
            
            // 卡片头部
            $html .= '<div class="rack-card-header">';
            $html .= '<div class="rack-card-title">🗄️ ' . htmlspecialchars($rackData['name']) . '</div>';
            if ($hasAlert) {
                $html .= '<div class="rack-alert-badge" title="' . $rackData['problem_count'] . ' ' . LanguageManager::t('problems') . '">' . $rackData['problem_count'] . '</div>';
            }
            $html .= '</div>';
            
            // 卡片内容
            $html .= '<div class="rack-card-body">';
            $html .= '<div class="rack-card-stat">';
            $html .= '<span class="rack-card-stat-label">🖥️ ' . LanguageManager::t('hosts') . ':</span>';
            $html .= '<span class="rack-card-stat-value">' . $rackData['host_count'] . '</span>';
            $html .= '</div>';
            
            $html .= '<div class="rack-card-stat">';
            $html .= '<span class="rack-card-stat-label">📏 ' . LanguageManager::t('usage') . ':</span>';
            $usagePercent = $rackData['height'] > 0 ? round(($rackData['used_u'] / $rackData['height']) * 100, 1) : 0;
            $html .= '<span class="rack-card-stat-value">' . $rackData['used_u'] . 'U / ' . $rackData['height'] . 'U (' . $usagePercent . '%)</span>';
            $html .= '</div>';
            
            // 迷你机柜可视化
            $html .= '<div class="rack-mini-visual">';
            for ($i = 0; $i < $rackData['height']; $i++) {
                $unitClass = 'rack-mini-unit';
                
                // 检查此U位是否被占用及其告警等级
                $isOccupied = false;
                $unitSeverity = -1;
                foreach ($rackData['hosts'] as $host) {
                    $u = $rackData['height'] - $i;
                    if ($u >= $host['u_start'] && $u <= $host['u_end']) {
                        $isOccupied = true;
                        if (isset($host['max_severity']) && $host['max_severity'] >= 0) {
                            $unitSeverity = $host['max_severity'];
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
                
                $html .= '<div class="' . $unitClass . '"></div>';
            }
            $html .= '</div>';
            
            $html .= '</div>'; // rack-card-body
            
            // 卡片页脚
            $html .= '<div class="rack-card-footer">';
            $html .= '<button class="rack-card-btn" onclick="event.stopPropagation();viewRackDetail(\'' . htmlspecialchars($rackData['id']) . '\')">' . LanguageManager::t('view_details') . ' →</button>';
            $html .= '</div>';
            
            $html .= '</div>'; // rack-card
        }
        
        $html .= '</div>'; // racks-grid
    }
    
    $html .= '</div>'; // rack-main
} else {
    // ===== 单机柜详情模式 =====
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
    
    // 构建机柜占用映射
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
                
                // 获取告警数量和严重程度
                $problemCount = $host['problem_count'] ?? 0;
                $maxSeverity = $host['max_severity'] ?? -1;
                $hasProblem = $problemCount > 0;
                
                $classes = ['rack-unit-slot', 'occupied'];
                
                // 根据告警严重程度和主机状态设置颜色
                if ($host['status'] == 1) {
                    $classes[] = 'status-disabled';
                } elseif ($hasProblem && $maxSeverity >= 0) {
                    // 根据Zabbix告警等级设置颜色
                    switch ($maxSeverity) {
                        case 5: // 灾难 - 红色
                            $classes[] = 'severity-disaster';
                            break;
                        case 4: // 严重 - 浅红
                            $classes[] = 'severity-high';
                            break;
                        case 3: // 一般 - 橙色
                            $classes[] = 'severity-average';
                            break;
                        case 2: // 警告 - 黄色
                            $classes[] = 'severity-warning';
                            break;
                        case 1: // 信息 - 蓝色
                            $classes[] = 'severity-info';
                            break;
                        case 0: // 未分类 - 灰色
                            $classes[] = 'severity-not-classified';
                            break;
                        default:
                            $classes[] = 'has-problem';
                    }
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
}

$html .= '</div>'; // rack-container
$html .= '</div>'; // rack-page-container
$html .= '</div>'; // rack-page-wrapper

// 主机信息提示框
$html .= '<div id="host-tooltip" class="host-tooltip"></div>';

// 大型机柜弹窗（点击卡片时显示）
$html .= '<div id="rack-detail-modal" class="rack-detail-modal">';
$html .= '<div class="rack-detail-content">';
$html .= '<div class="rack-detail-header">';
$html .= '<div class="rack-detail-title">🗄️ <span id="detail-rack-name"></span></div>';
$html .= '<button class="rack-detail-close" onclick="closeRackDetail()">&times;</button>';
$html .= '</div>';
$html .= '<div class="rack-detail-body">';
$html .= '<div id="detail-rack-visual" class="rack-detail-visual"></div>';
$html .= '</div>';
$html .= '</div>';
$html .= '</div>';

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

// 为JavaScript准备的数据 - 使用JSON_HEX_*标志确保安全嵌入HTML/JS
// 这些标志会将特殊字符转换为Unicode转义序列，避免XSS并保持JS语法正确
$allRacksDataJson = json_encode($allRacksData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$js = <<<JS
<script>
var allRacksData = {$allRacksDataJson};

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
    
    // ============ 多机柜展示相关函数 ============
    window.openRackDetail = function(rackId) {
        viewRackDetail(rackId);
    };
    
    // 当前详情弹窗中显示的机柜数据
    var detailRackData = null;
    
    window.viewRackDetail = function(rackId) {
        var rackData = allRacksData.find(function(r) { return r.id === rackId; });
        if (!rackData) {
            console.error('viewRackDetail: rackData not found for id:', rackId);
            return;
        }
        
        // 确保 hosts 是数组
        var hosts = rackData.hosts || [];
        if (!Array.isArray(hosts)) {
            console.error('viewRackDetail: hosts is not an array:', hosts);
            hosts = [];
        }
        
        // 【关键】先构建 U 位映射，与 PHP 单机柜模式相同的处理方式
        var occupiedSlots = {};
        for (var i = 0; i < hosts.length; i++) {
            var host = hosts[i];
            var uStart = parseInt(host.u_start);
            var uEnd = parseInt(host.u_end);
            if (uStart > 0 && uEnd > 0) {
                for (var u = uStart; u <= uEnd; u++) {
                    occupiedSlots[u] = host;
                }
            }
        }
        
        console.log('viewRackDetail: occupiedSlots =', occupiedSlots);
        
        // 保存当前详情弹窗的机柜数据，供后续操作使用
        detailRackData = rackData;
        
        // 设置当前操作的机柜上下文
        currentRackId = rackData.id;
        currentRoomId = rackData.room_id;
        
        document.getElementById('detail-rack-name').textContent = rackData.name;
        
        // 生成详细的机柜可视化
        var rackHeight = parseInt(rackData.height) || 42;
        var html = '<div class="rack-visual" style="max-width:600px;margin:0 auto;">';
        html += '<div class="rack-header">🗄️ ' + escapeHtml(rackData.name) + ' (' + rackHeight + 'U)</div>';
        html += '<div class="rack-units">';
        
        // 从上到下渲染U位（与PHP代码完全一致）
        for (var u = rackHeight; u >= 1; u--) {
            html += '<div class="rack-unit">';
            html += '<div class="rack-unit-number">U' + u + '</div>';
            
            if (occupiedSlots[u]) {
                var host = occupiedSlots[u];
                var uStart = parseInt(host.u_start);
                var uEnd = parseInt(host.u_end);
                var isStart = (u == uEnd);   // 使用 == 而非 === 避免类型问题
                var isEnd = (u == uStart);
                var isMiddle = !isStart && !isEnd;
                var hostUHeight = uEnd - uStart + 1;
                
                // 获取主机名称
                var hostName = host.name || host.host || '未命名主机';
                var hostId = host.hostid || '';
                var problemCount = parseInt(host.problem_count) || 0;
                var maxSeverity = parseInt(host.max_severity);
                if (isNaN(maxSeverity)) maxSeverity = -1;
                
                var classes = ['rack-unit-slot', 'occupied'];
                
                // 根据告警严重程度和主机状态设置颜色
                var hostStatus = parseInt(host.status);
                if (hostStatus === 1) {
                    classes.push('status-disabled');
                } else if (problemCount > 0 && maxSeverity >= 0) {
                    switch (maxSeverity) {
                        case 5: classes.push('severity-disaster'); break;
                        case 4: classes.push('severity-high'); break;
                        case 3: classes.push('severity-average'); break;
                        case 2: classes.push('severity-warning'); break;
                        case 1: classes.push('severity-info'); break;
                        case 0: classes.push('severity-not-classified'); break;
                        default: classes.push('has-problem');
                    }
                } else {
                    classes.push('no-problem');
                }
                
                if (isStart) classes.push('occupied-start');
                if (isEnd) classes.push('occupied-end');
                if (isMiddle) classes.push('occupied-middle');
                
                // 构建主机数据JSON
                var hostDataJson = JSON.stringify({
                    hostid: hostId,
                    name: hostName,
                    host: host.host || hostName,
                    ip: host.ip || host.main_ip || '',
                    groups: host.groups || '',
                    u_start: uStart,
                    u_end: uEnd,
                    status: hostStatus === 0 ? '已启用' : '已禁用',
                    problem_count: problemCount
                }).replace(/'/g, "\\'").replace(/"/g, '&quot;');
                
                html += '<div class="' + classes.join(' ') + '" data-host="' + hostDataJson + '" onclick="openEditModalFromDetail(this)" style="cursor:pointer;">';
                
                // 只在顶部U位显示主机名（与PHP代码一致）
                if (isStart) {
                    html += '<div class="host-slot-content">';
                    var displayName = escapeHtml(hostName);
                    if (hostUHeight > 1) {
                        displayName += ' <span style="font-size:10px;opacity:0.8;">(' + hostUHeight + 'U)</span>';
                    }
                    html += '<span class="host-name">' + displayName + '</span>';
                    if (problemCount > 0) {
                        html += '<span class="problem-badge" onclick="event.stopPropagation();showProblemsFromDetail(\'' + hostId + '\',\'' + escapeHtml(hostName).replace(/'/g, "\\'") + '\')">' + problemCount + '</span>';
                    }
                    html += '</div>';
                }
                html += '</div>';
            } else {
                // 空槽位
                html += '<div class="rack-unit-slot" data-u="' + u + '" onclick="openAssignModalFromDetail(' + u + ')" style="cursor:pointer;"></div>';
            }
            
            html += '</div>';
        }
        
        html += '</div></div>';
        document.getElementById('detail-rack-visual').innerHTML = html;
        document.getElementById('rack-detail-modal').classList.add('visible');
    };
    
    // 从详情弹窗中打开分配主机对话框
    window.openAssignModalFromDetail = function(u) {
        // 先关闭详情弹窗
        closeRackDetail();
        // 然后打开分配对话框
        openAssignModal(u);
    };
    
    // 从详情弹窗中打开编辑主机对话框
    window.openEditModalFromDetail = function(elem) {
        // 先关闭详情弹窗
        closeRackDetail();
        // 解析主机数据（需要先还原HTML实体）
        var hostDataStr = elem.getAttribute('data-host').replace(/&quot;/g, '"');
        var hostData = JSON.parse(hostDataStr);
        
        // 设置编辑模式
        isEditMode = true;
        editingHostId = hostData.hostid;
        selectedHostId = hostData.hostid;
        
        document.getElementById('modal-title').textContent = '编辑主机位置';
        document.getElementById('edit-host-name').textContent = hostData.name;
        document.getElementById('edit-host-ip').textContent = (hostData.ip || '-') + ' | ' + (hostData.groups || '-');
        document.getElementById('modal-u-start').value = hostData.u_start;
        document.getElementById('modal-u-end').value = hostData.u_end;
        document.getElementById('host-select-section').style.display = 'none';
        document.getElementById('edit-host-info').style.display = 'block';
        document.getElementById('btn-remove-host').style.display = 'inline-block';
        document.getElementById('assign-modal').classList.add('visible');
    };
    
    // 从详情弹窗中显示主机问题
    window.showProblemsFromDetail = function(hostId, hostName) {
        // 如果存在showProblems函数则调用它
        if (typeof showProblems === 'function') {
            showProblems(hostId, hostName);
        }
    };
    
    window.closeRackDetail = function() {
        document.getElementById('rack-detail-modal').classList.remove('visible');
    };
    
    // 点击弹窗外部关闭
    document.getElementById('rack-detail-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeRackDetail();
        }
    });
    
    // ============ 单机柜管理相关函数 ============
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