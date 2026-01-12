<?php

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';

use Modules\ZabbixRack\Lib\LanguageManager;
use Modules\ZabbixRack\Lib\ViewRenderer;

$lm = new LanguageManager();
$renderer = new ViewRenderer();

?>

<script>
function deleteRoom(roomId, roomName) {
    if (confirm('<?php echo $lm->t('confirm_delete_room'); ?>: ' + roomName + '?')) {
        var form = document.createElement('form');
        form.method = 'post';
        form.action = 'zabbix.php?action=room.delete';

        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'room_id';
        input.value = roomId;
        form.appendChild(input);

        document.body.appendChild(form);
        form.submit();
    }
}

function deleteRack(rackId, rackName) {
    if (confirm('<?php echo $lm->t('confirm_delete_rack'); ?>: ' + rackName + '?')) {
        var form = document.createElement('form');
        form.method = 'post';
        form.action = 'zabbix.php?action=rack.delete';

        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'rack_id';
        input.value = rackId;
        form.appendChild(input);

        document.body.appendChild(form);
        form.submit();
    }
}

function editRoom(roomId) {
    // 打开编辑机房对话框
    openRoomDialog(roomId);
}

function editRack(rackId) {
    // 打开编辑机柜对话框
    openRackDialog(rackId);
}
</script>

<?php echo $renderer->renderHeader($lm->t('rack_manage')); ?>

<div class="tabfilter-container">
    <div class="tabfilter-tabs">
        <ul class="tabs">
            <li class="tab active" data-tab="rooms">
                <a href="#rooms"><?php echo $lm->t('rooms'); ?></a>
            </li>
            <li class="tab" data-tab="racks">
                <a href="#racks"><?php echo $lm->t('racks'); ?></a>
            </li>
        </ul>
    </div>
</div>

<div id="rooms" class="tab-content active">
    <div class="action-buttons">
        <button type="button" class="btn btn-primary" onclick="openRoomDialog()"><?php echo $lm->t('add_room'); ?></button>
    </div>

    <div class="table-container">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th><?php echo $lm->t('room_name'); ?></th>
                    <th><?php echo $lm->t('description'); ?></th>
                    <th><?php echo $lm->t('actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['rooms'] as $room): ?>
                <tr>
                    <td><?php echo htmlspecialchars($room['name']); ?></td>
                    <td><?php echo htmlspecialchars($room['description'] ?? ''); ?></td>
                    <td>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="editRoom(<?php echo $room['id']; ?>)"><?php echo $lm->t('edit'); ?></button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteRoom(<?php echo $room['id']; ?>, '<?php echo htmlspecialchars($room['name']); ?>')"><?php echo $lm->t('delete'); ?></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="racks" class="tab-content">
    <div class="action-buttons">
        <button type="button" class="btn btn-primary" onclick="openRackDialog()"><?php echo $lm->t('add_rack'); ?></button>
    </div>

    <div class="table-container">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th><?php echo $lm->t('rack_name'); ?></th>
                    <th><?php echo $lm->t('room'); ?></th>
                    <th><?php echo $lm->t('u_height'); ?></th>
                    <th><?php echo $lm->t('used_u'); ?></th>
                    <th><?php echo $lm->t('actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['racks'] as $rack): ?>
                <tr>
                    <td><?php echo htmlspecialchars($rack['name']); ?></td>
                    <td><?php echo htmlspecialchars($rack['room_name']); ?></td>
                    <td><?php echo $rack['u_height']; ?>U</td>
                    <td><?php echo $rack['used_u']; ?>U</td>
                    <td>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="editRack(<?php echo $rack['id']; ?>)"><?php echo $lm->t('edit'); ?></button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteRack(<?php echo $rack['id']; ?>, '<?php echo htmlspecialchars($rack['name']); ?>')"><?php echo $lm->t('delete'); ?></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Room Dialog -->
<div id="roomDialog" class="overlay-dialogue" style="display: none;">
    <div class="overlay-dialogue-body">
        <form id="roomForm" method="post" action="zabbix.php?action=room.save">
            <input type="hidden" name="room_id" id="roomId">
            <div class="form-group">
                <label for="roomName"><?php echo $lm->t('room_name'); ?>:</label>
                <input type="text" id="roomName" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="roomDescription"><?php echo $lm->t('description'); ?>:</label>
                <textarea id="roomDescription" name="description" class="form-control"></textarea>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?php echo $lm->t('save'); ?></button>
                <button type="button" class="btn btn-secondary" onclick="closeRoomDialog()"><?php echo $lm->t('cancel'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Rack Dialog -->
<div id="rackDialog" class="overlay-dialogue" style="display: none;">
    <div class="overlay-dialogue-body">
        <form id="rackForm" method="post" action="zabbix.php?action=rack.save">
            <input type="hidden" name="rack_id" id="rackId">
            <div class="form-group">
                <label for="rackName"><?php echo $lm->t('rack_name'); ?>:</label>
                <input type="text" id="rackName" name="name" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="rackRoom"><?php echo $lm->t('room'); ?>:</label>
                <select id="rackRoom" name="room_id" class="form-control" required>
                    <option value=""><?php echo $lm->t('select_room'); ?></option>
                    <?php foreach ($data['rooms'] as $room): ?>
                    <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="rackUHeight"><?php echo $lm->t('u_height'); ?>:</label>
                <input type="number" id="rackUHeight" name="u_height" class="form-control" min="1" max="60" value="42" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?php echo $lm->t('save'); ?></button>
                <button type="button" class="btn btn-secondary" onclick="closeRackDialog()"><?php echo $lm->t('cancel'); ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function openRoomDialog(roomId = null) {
    if (roomId) {
        // 编辑模式 - 需要从服务器获取数据
        // 这里简化处理，实际需要 AJAX 请求
        document.getElementById('roomId').value = roomId;
        document.getElementById('roomDialog').style.display = 'block';
    } else {
        // 添加模式
        document.getElementById('roomId').value = '';
        document.getElementById('roomName').value = '';
        document.getElementById('roomDescription').value = '';
        document.getElementById('roomDialog').style.display = 'block';
    }
}

function closeRoomDialog() {
    document.getElementById('roomDialog').style.display = 'none';
}

function openRackDialog(rackId = null) {
    if (rackId) {
        // 编辑模式
        document.getElementById('rackId').value = rackId;
        document.getElementById('rackDialog').style.display = 'block';
    } else {
        // 添加模式
        document.getElementById('rackId').value = '';
        document.getElementById('rackName').value = '';
        document.getElementById('rackUHeight').value = '42';
        document.getElementById('rackDialog').style.display = 'block';
    }
}

function closeRackDialog() {
    document.getElementById('rackDialog').style.display = 'none';
}

// Tab switching
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));
        this.classList.add('active');
        document.getElementById(this.dataset.tab).classList.add('active');
    });
});
</script>

<?php echo $renderer->renderFooter(); ?>
