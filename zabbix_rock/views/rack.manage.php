<?php
/**
 * 机柜管理页面
 */

// 引入语言管理器和兼容层
require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';

use Modules\ZabbixRock\Lib\LanguageManager;
use Modules\ZabbixRock\Lib\ViewRenderer;

$lang = $data['lang'];
$rooms = $data['rooms'];
$racks = $data['racks'];

$pageTitle = LanguageManager::t('rack_manage');

// 添加CSS样式 - 与机柜视图保持一致的现代化风格
$styleTag = new CTag('style', true, '
/* 页面容器 */
.manage-container {
    padding: 20px;
    width: 100%;
    margin: 0 auto;
}

/* 统计卡片行 */
.stats-row {
    display: flex;
    gap: 20px;
    margin-bottom: 25px;
}
.stat-card {
    flex: 1;
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 18px 22px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}
.stat-card:hover {
    box-shadow: 0 6px 16px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}
.stat-icon {
    font-size: 32px;
}
.stat-content {
    flex: 1;
}
.stat-number {
    font-size: 28px;
    font-weight: 700;
    color: #212529;
    display: block;
}
.stat-label {
    font-size: 13px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* 管理区域卡片 */
.manage-section {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    margin-bottom: 25px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,0.06);
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

/* 数据表格 */
.data-table {
    width: 100%;
    border-collapse: collapse;
}
.data-table th {
    padding: 14px 20px;
    text-align: left;
    background: linear-gradient(135deg, #f8f9fa 0%, #f0f0f0 100%);
    font-weight: 600;
    font-size: 13px;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}
.data-table td {
    padding: 14px 20px;
    text-align: left;
    border-bottom: 1px solid #f0f0f0;
    font-size: 14px;
    color: #212529;
}
.data-table tbody tr {
    transition: all 0.2s ease;
}
.data-table tbody tr:hover {
    background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
}
.data-table tbody tr:last-child td {
    border-bottom: none;
}

/* 统一按钮样式 */
.btn {
    padding: 10px 18px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    text-align: center;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.btn-primary {
    color: #fff;
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    box-shadow: 0 2px 6px rgba(0, 123, 255, 0.3);
}
.btn-primary:hover {
    background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.4);
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
    box-shadow: 0 2px 6px rgba(40, 167, 69, 0.3);
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
.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

/* 操作按钮组 */
.action-btns {
    display: flex;
    gap: 8px;
}

/* 无数据提示 */
.no-data {
    text-align: center;
    padding: 50px 20px;
    color: #6c757d;
    font-size: 15px;
    background: #f8f9fa;
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
    width: 450px;
    max-height: 85vh;
    overflow: hidden;
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
    font-weight: 600;
    color: #212529;
    display: flex;
    align-items: center;
    gap: 10px;
}
.modal-close {
    background: none;
    border: none;
    font-size: 26px;
    cursor: pointer;
    color: #6c757d;
    padding: 4px 8px;
    border-radius: 6px;
    transition: all 0.2s ease;
    line-height: 1;
}
.modal-close:hover {
    background: #f8f9fa;
    color: #212529;
}
.modal-body {
    padding: 24px;
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
    margin-bottom: 8px;
    font-weight: 600;
    color: #495057;
    font-size: 13px;
}
.form-group input, 
.form-group select, 
.form-group textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 14px;
    box-sizing: border-box;
    transition: all 0.2s ease;
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
.form-group input:focus, 
.form-group select:focus, 
.form-group textarea:focus {
    outline: none;
    border-color: #80bdff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15);
}
.form-group input:hover, 
.form-group select:hover, 
.form-group textarea:hover {
    border-color: #adb5bd;
}
.form-group textarea {
    height: 90px;
    resize: vertical;
}

/* 表格内徽章 */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 500;
}
.badge-info {
    background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
    color: #1565c0;
}
.badge-success {
    background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
    color: #2e7d32;
}
');

// 主容器
$html = '<div class="manage-container">';

// 统计卡片
$totalRooms = count($rooms);
$totalRacks = count($racks);
$totalU = 0;
foreach ($racks as $rack) {
    $totalU += ($rack['height'] ?? 42);
}

$html .= '<div class="stats-row">';
$html .= '<div class="stat-card"><span class="stat-icon">🏢</span><div class="stat-content"><span class="stat-number">' . $totalRooms . '</span><span class="stat-label">' . LanguageManager::t('total_rooms') . '</span></div></div>';
$html .= '<div class="stat-card"><span class="stat-icon">🗄️</span><div class="stat-content"><span class="stat-number">' . $totalRacks . '</span><span class="stat-label">' . LanguageManager::t('total_racks') . '</span></div></div>';
$html .= '<div class="stat-card"><span class="stat-icon">📐</span><div class="stat-content"><span class="stat-number">' . $totalU . 'U</span><span class="stat-label">' . LanguageManager::t('total_capacity') . '</span></div></div>';
$html .= '</div>';

// 机房管理区域
$html .= '<div class="manage-section">';
$html .= '<div class="section-header">';
$html .= '<h2>🏢 ' . LanguageManager::t('room_management') . '</h2>';
$html .= '<button class="btn btn-primary" onclick="openRoomModal()">➕ ' . LanguageManager::t('add_room') . '</button>';
$html .= '</div>';
$html .= '<div class="section-body">';

if (empty($rooms)) {
    $html .= '<div class="no-data">📭 ' . LanguageManager::t('no_rooms') . '</div>';
} else {
    $html .= '<table class="data-table">';
    $html .= '<thead><tr>';
    $html .= '<th>🏷️ ' . LanguageManager::t('room_name') . '</th>';
    $html .= '<th>📝 ' . LanguageManager::t('description') . '</th>';
    $html .= '<th>🗄️ ' . LanguageManager::t('rack_count') . '</th>';
    $html .= '<th>📅 ' . LanguageManager::t('created_at') . '</th>';
    $html .= '<th>⚙️ ' . LanguageManager::t('actions') . '</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';
    
    foreach ($rooms as $room) {
        // 统计机柜数量
        $rackCount = 0;
        foreach ($racks as $rack) {
            if ($rack['room_id'] === $room['id']) {
                $rackCount++;
            }
        }
        
        $roomJson = htmlspecialchars(json_encode($room), ENT_QUOTES);
        
        $html .= '<tr>';
        $html .= '<td><strong>' . htmlspecialchars($room['name']) . '</strong></td>';
        $html .= '<td>' . htmlspecialchars($room['description'] ?? '-') . '</td>';
        $html .= '<td><span class="badge badge-info">🗄️ ' . $rackCount . '</span></td>';
        $html .= '<td>' . htmlspecialchars($room['created_at'] ?? '-') . '</td>';
        $html .= '<td><div class="action-btns">';
        $html .= '<button class="btn btn-sm btn-secondary" onclick=\'editRoom(' . $roomJson . ')\'>✏️ ' . LanguageManager::t('edit') . '</button>';
        $html .= '<button class="btn btn-sm btn-danger" onclick="deleteRoom(\'' . htmlspecialchars($room['id']) . '\')">🗑️ ' . LanguageManager::t('delete') . '</button>';
        $html .= '</div></td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';
}

$html .= '</div>';
$html .= '</div>';

// 机柜管理区域
$html .= '<div class="manage-section">';
$html .= '<div class="section-header">';
$html .= '<h2>🗄️ ' . LanguageManager::t('rack_management') . '</h2>';
$html .= '<button class="btn btn-primary" onclick="openRackModal()">➕ ' . LanguageManager::t('add_rack') . '</button>';
$html .= '</div>';
$html .= '<div class="section-body">';

if (empty($racks)) {
    $html .= '<div class="no-data">📭 ' . LanguageManager::t('no_racks') . '</div>';
} else {
    $html .= '<table class="data-table">';
    $html .= '<thead><tr>';
    $html .= '<th>🏷️ ' . LanguageManager::t('rack_name') . '</th>';
    $html .= '<th>🏢 ' . LanguageManager::t('room') . '</th>';
    $html .= '<th>📐 ' . LanguageManager::t('height') . '</th>';
    $html .= '<th>📝 ' . LanguageManager::t('description') . '</th>';
    $html .= '<th>📅 ' . LanguageManager::t('created_at') . '</th>';
    $html .= '<th>⚙️ ' . LanguageManager::t('actions') . '</th>';
    $html .= '</tr></thead>';
    $html .= '<tbody>';
    
    foreach ($racks as $rack) {
        $rackJson = htmlspecialchars(json_encode($rack), ENT_QUOTES);
        
        $html .= '<tr>';
        $html .= '<td><strong>' . htmlspecialchars($rack['name']) . '</strong></td>';
        $html .= '<td>' . htmlspecialchars($rack['room_name'] ?? '-') . '</td>';
        $html .= '<td><span class="badge badge-success">📐 ' . ($rack['height'] ?? 42) . 'U</span></td>';
        $html .= '<td>' . htmlspecialchars($rack['description'] ?? '-') . '</td>';
        $html .= '<td>' . htmlspecialchars($rack['created_at'] ?? '-') . '</td>';
        $html .= '<td><div class="action-btns">';
        $html .= '<button class="btn btn-sm btn-secondary" onclick=\'editRack(' . $rackJson . ')\'>✏️ ' . LanguageManager::t('edit') . '</button>';
        $html .= '<button class="btn btn-sm btn-danger" onclick="deleteRack(\'' . htmlspecialchars($rack['id']) . '\')">🗑️ ' . LanguageManager::t('delete') . '</button>';
        $html .= '</div></td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody></table>';
}

$html .= '</div>';
$html .= '</div>';

$html .= '</div>'; // manage-container

// 机房编辑弹窗
$html .= '<div id="room-modal" class="modal-overlay">';
$html .= '<div class="modal-content">';
$html .= '<div class="modal-header">';
$html .= '<h3 id="room-modal-title">🏢 ' . LanguageManager::t('add_room') . '</h3>';
$html .= '<button class="modal-close" onclick="closeRoomModal()">&times;</button>';
$html .= '</div>';
$html .= '<div class="modal-body">';
$html .= '<input type="hidden" id="room-id">';
$html .= '<div class="form-group">';
$html .= '<label>🏷️ ' . LanguageManager::t('room_name') . ' *</label>';
$html .= '<input type="text" id="room-name" placeholder="' . LanguageManager::t('enter_room_name') . '" required>';
$html .= '</div>';
$html .= '<div class="form-group">';
$html .= '<label>📝 ' . LanguageManager::t('description') . '</label>';
$html .= '<textarea id="room-description" placeholder="' . LanguageManager::t('enter_room_description') . '"></textarea>';
$html .= '</div>';
$html .= '</div>';
$html .= '<div class="modal-footer">';
$html .= '<button class="btn btn-secondary" onclick="closeRoomModal()">❌ ' . LanguageManager::t('cancel') . '</button>';
$html .= '<button class="btn btn-success" onclick="saveRoom()">✅ ' . LanguageManager::t('save') . '</button>';
$html .= '</div>';
$html .= '</div>';
$html .= '</div>';

// 机柜编辑弹窗
$html .= '<div id="rack-modal" class="modal-overlay">';
$html .= '<div class="modal-content">';
$html .= '<div class="modal-header">';
$html .= '<h3 id="rack-modal-title">🗄️ ' . LanguageManager::t('add_rack') . '</h3>';
$html .= '<button class="modal-close" onclick="closeRackModal()">&times;</button>';
$html .= '</div>';
$html .= '<div class="modal-body">';
$html .= '<input type="hidden" id="rack-id">';
$html .= '<div class="form-group">';
$html .= '<label>🏷️ ' . LanguageManager::t('rack_name') . ' *</label>';
$html .= '<input type="text" id="rack-name" placeholder="' . LanguageManager::t('enter_rack_name') . '" required>';
$html .= '</div>';
$html .= '<div class="form-group">';
$html .= '<label>🏢 ' . LanguageManager::t('room') . ' *</label>';
$html .= '<select id="rack-room-id">';
$html .= '<option value="">-- ' . LanguageManager::t('select_room') . ' --</option>';

foreach ($rooms as $room) {
    $html .= '<option value="' . htmlspecialchars($room['id']) . '">' . htmlspecialchars($room['name']) . '</option>';
}

$html .= '</select>';
$html .= '</div>';
$html .= '<div class="form-group">';
$html .= '<label>📐 ' . LanguageManager::t('height') . ' (U)</label>';
$html .= '<input type="number" id="rack-height" value="42" min="1" max="60">';
$html .= '</div>';
$html .= '<div class="form-group">';
$html .= '<label>📝 ' . LanguageManager::t('description') . '</label>';
$html .= '<textarea id="rack-description" placeholder="' . LanguageManager::t('enter_rack_description') . '"></textarea>';
$html .= '</div>';
$html .= '</div>';
$html .= '<div class="modal-footer">';
$html .= '<button class="btn btn-secondary" onclick="closeRackModal()">❌ ' . LanguageManager::t('cancel') . '</button>';
$html .= '<button class="btn btn-success" onclick="saveRack()">✅ ' . LanguageManager::t('save') . '</button>';
$html .= '</div>';
$html .= '</div>';
$html .= '</div>';

// JavaScript
$addRoomTitle = LanguageManager::t('add_room');
$editRoomTitle = LanguageManager::t('edit_room');
$addRackTitle = LanguageManager::t('add_rack');
$editRackTitle = LanguageManager::t('edit_rack');
$confirmDeleteRoom = LanguageManager::t('confirm_delete_room');
$confirmDeleteRack = LanguageManager::t('confirm_delete_rack');

$js = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 机房弹窗
    window.openRoomModal = function() {
        document.getElementById('room-modal-title').textContent = '{$addRoomTitle}';
        document.getElementById('room-id').value = '';
        document.getElementById('room-name').value = '';
        document.getElementById('room-description').value = '';
        document.getElementById('room-modal').classList.add('visible');
    };
    
    window.closeRoomModal = function() {
        document.getElementById('room-modal').classList.remove('visible');
    };
    
    window.editRoom = function(room) {
        document.getElementById('room-modal-title').textContent = '{$editRoomTitle}';
        document.getElementById('room-id').value = room.id;
        document.getElementById('room-name').value = room.name || '';
        document.getElementById('room-description').value = room.description || '';
        document.getElementById('room-modal').classList.add('visible');
    };
    
    window.saveRoom = function() {
        var name = document.getElementById('room-name').value.trim();
        if (!name) {
            alert('请输入机房名称');
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'room.save');
        formData.append('id', document.getElementById('room-id').value);
        formData.append('name', name);
        formData.append('description', document.getElementById('room-description').value);
        
        fetch('zabbix.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || data.message || '保存失败');
            }
        });
    };
    
    window.deleteRoom = function(roomId) {
        if (!confirm('{$confirmDeleteRoom}')) {
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'room.delete');
        formData.append('id', roomId);
        
        fetch('zabbix.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || data.message || '删除失败');
            }
        });
    };
    
    // 机柜弹窗
    window.openRackModal = function() {
        document.getElementById('rack-modal-title').textContent = '{$addRackTitle}';
        document.getElementById('rack-id').value = '';
        document.getElementById('rack-name').value = '';
        document.getElementById('rack-room-id').value = '';
        document.getElementById('rack-height').value = '42';
        document.getElementById('rack-description').value = '';
        document.getElementById('rack-modal').classList.add('visible');
    };
    
    window.closeRackModal = function() {
        document.getElementById('rack-modal').classList.remove('visible');
    };
    
    window.editRack = function(rack) {
        document.getElementById('rack-modal-title').textContent = '{$editRackTitle}';
        document.getElementById('rack-id').value = rack.id;
        document.getElementById('rack-name').value = rack.name || '';
        document.getElementById('rack-room-id').value = rack.room_id || '';
        document.getElementById('rack-height').value = rack.height || 42;
        document.getElementById('rack-description').value = rack.description || '';
        document.getElementById('rack-modal').classList.add('visible');
    };
    
    window.saveRack = function() {
        var name = document.getElementById('rack-name').value.trim();
        var roomId = document.getElementById('rack-room-id').value;
        
        if (!name) {
            alert('请输入机柜名称');
            return;
        }
        
        if (!roomId) {
            alert('请选择机房');
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'rack.save');
        formData.append('id', document.getElementById('rack-id').value);
        formData.append('name', name);
        formData.append('room_id', roomId);
        formData.append('height', document.getElementById('rack-height').value || 42);
        formData.append('description', document.getElementById('rack-description').value);
        
        fetch('zabbix.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || data.message || '保存失败');
            }
        });
    };
    
    window.deleteRack = function(rackId) {
        if (!confirm('{$confirmDeleteRack}')) {
            return;
        }
        
        var formData = new FormData();
        formData.append('action', 'rack.delete');
        formData.append('id', rackId);
        
        fetch('zabbix.php', {
            method: 'POST',
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || data.message || '删除失败');
            }
        });
    };
});
</script>
JS;

$html .= $js;

// 使用兼容渲染器显示页面
$content = new CDiv();
$content->addItem(new CJsScript($html));

ViewRenderer::render($pageTitle, $styleTag, $content);
