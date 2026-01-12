<?php

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ViewRenderer.php';

use Modules\ZabbixRack\Lib\LanguageManager;
use Modules\ZabbixRack\Lib\ViewRenderer;

$lm = new LanguageManager();
$renderer = new ViewRenderer();

?>

<script>
function loadRack(roomId, rackId) {
    const url = 'zabbix.php?action=rack.view&room_id=' + encodeURIComponent(roomId) + '&rack_id=' + encodeURIComponent(rackId);
    window.location.href = url;
}

function searchHosts() {
    const searchTerm = document.getElementById('searchInput').value;
    const url = 'zabbix.php?action=rack.view&search=' + encodeURIComponent(searchTerm);
    window.location.href = url;
}

function assignHost(hostId, hostName) {
    // 打开主机分配对话框
    openAssignDialog(hostId, hostName);
}

function removeHost(hostId, hostName) {
    if (confirm('<?php echo $lm->t('confirm_remove_host'); ?>: ' + hostName + '?')) {
        const form = document.createElement('form');
        form.method = 'post';
        form.action = 'zabbix.php?action=host.remove';

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'host_id';
        input.value = hostId;
        form.appendChild(input);

        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php echo $renderer->renderHeader($lm->t('rack_view')); ?>

<div class="filter-container">
    <div class="filter-row">
        <div class="filter-item">
            <label for="roomSelect"><?php echo $lm->t('select_room'); ?>:</label>
            <select id="roomSelect" onchange="updateRackSelect()" class="form-control">
                <option value=""><?php echo $lm->t('all_rooms'); ?></option>
                <?php foreach ($data['rooms'] as $room): ?>
                <option value="<?php echo $room['id']; ?>" <?php echo ($data['selected_room_id'] == $room['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($room['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-item">
            <label for="rackSelect"><?php echo $lm->t('select_rack'); ?>:</label>
            <select id="rackSelect" onchange="loadSelectedRack()" class="form-control">
                <option value=""><?php echo $lm->t('all_racks'); ?></option>
                <?php foreach ($data['racks'] as $rack): ?>
                <option value="<?php echo $rack['id']; ?>" data-room-id="<?php echo $rack['room_id']; ?>"
                        <?php echo ($data['selected_rack_id'] == $rack['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($rack['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-item">
            <label for="searchInput"><?php echo $lm->t('search_host'); ?>:</label>
            <input type="text" id="searchInput" value="<?php echo htmlspecialchars($data['search'] ?? ''); ?>" class="form-control" placeholder="<?php echo $lm->t('enter_host_name'); ?>">
            <button type="button" onclick="searchHosts()" class="btn btn-primary"><?php echo $lm->t('search'); ?></button>
        </div>
    </div>
</div>

<?php if (!empty($data['selected_rack'])): ?>
<div class="rack-container">
    <div class="rack-header">
        <h3><?php echo htmlspecialchars($data['selected_rack']['room_name'] . ' - ' . $data['selected_rack']['name']); ?></h3>
        <div class="rack-info">
            <span><?php echo $lm->t('total_u'); ?>: <?php echo $data['selected_rack']['u_height']; ?>U</span>
            <span><?php echo $lm->t('used_u'); ?>: <?php echo $data['used_u']; ?>U</span>
            <span><?php echo $lm->t('free_u'); ?>: <?php echo $data['selected_rack']['u_height'] - $data['used_u']; ?>U</span>
        </div>
    </div>

    <div class="rack-visualization">
        <?php for ($u = $data['selected_rack']['u_height']; $u >= 1; $u--): ?>
        <div class="rack-unit <?php echo isset($data['hosts_by_u'][$u]) ? 'occupied' : 'empty'; ?>" data-u="<?php echo $u; ?>">
            <div class="u-label"><?php echo $u; ?>U</div>
            <div class="u-content">
                <?php if (isset($data['hosts_by_u'][$u])): ?>
                <div class="host-info" style="height: <?php echo $data['hosts_by_u'][$u]['u_height'] * 30; ?>px;">
                    <div class="host-name"><?php echo htmlspecialchars($data['hosts_by_u'][$u]['name']); ?></div>
                    <div class="host-details">
                        <small><?php echo $lm->t('u_position'); ?>: <?php echo $data['hosts_by_u'][$u]['u_position']; ?>-<?php echo $data['hosts_by_u'][$u]['u_position'] + $data['hosts_by_u'][$u]['u_height'] - 1; ?>U</small><br>
                        <small><?php echo $lm->t('height'); ?>: <?php echo $data['hosts_by_u'][$u]['u_height']; ?>U</small>
                    </div>
                    <div class="host-actions">
                        <button type="button" class="btn btn-sm btn-warning" onclick="removeHost(<?php echo $data['hosts_by_u'][$u]['hostid']; ?>, '<?php echo htmlspecialchars($data['hosts_by_u'][$u]['name']); ?>')"><?php echo $lm->t('remove'); ?></button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endfor; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($data['search_results'])): ?>
<div class="search-results">
    <h3><?php echo $lm->t('search_results'); ?></h3>
    <div class="table-container">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th><?php echo $lm->t('host_name'); ?></th>
                    <th><?php echo $lm->t('current_location'); ?></th>
                    <th><?php echo $lm->t('actions'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['search_results'] as $host): ?>
                <tr>
                    <td><?php echo htmlspecialchars($host['name']); ?></td>
                    <td>
                        <?php if (!empty($host['rack_location'])): ?>
                        <?php echo htmlspecialchars($host['rack_location']['room'] . ' - ' . $host['rack_location']['rack'] . ' (U' . $host['rack_location']['u_position'] . ')'); ?>
                        <?php else: ?>
                        <?php echo $lm->t('not_assigned'); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button type="button" class="btn btn-primary btn-sm" onclick="assignHost(<?php echo $host['hostid']; ?>, '<?php echo htmlspecialchars($host['name']); ?>')"><?php echo $lm->t('assign_to_rack'); ?></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Host Assignment Dialog -->
<div id="assignDialog" class="overlay-dialogue" style="display: none;">
    <div class="overlay-dialogue-body">
        <form id="assignForm" method="post" action="zabbix.php?action=host.assign">
            <input type="hidden" name="host_id" id="assignHostId">
            <h4 id="assignHostName"></h4>

            <div class="form-group">
                <label for="assignRoom"><?php echo $lm->t('select_room'); ?>:</label>
                <select id="assignRoom" name="room_id" onchange="updateAssignRackSelect()" class="form-control" required>
                    <option value=""><?php echo $lm->t('select_room'); ?></option>
                    <?php foreach ($data['rooms'] as $room): ?>
                    <option value="<?php echo $room['id']; ?>"><?php echo htmlspecialchars($room['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="assignRack"><?php echo $lm->t('select_rack'); ?>:</label>
                <select id="assignRack" name="rack_id" class="form-control" required>
                    <option value=""><?php echo $lm->t('select_rack'); ?></option>
                </select>
            </div>

            <div class="form-group">
                <label for="assignUPosition"><?php echo $lm->t('u_position'); ?>:</label>
                <input type="number" id="assignUPosition" name="u_position" class="form-control" min="1" required>
            </div>

            <div class="form-group">
                <label for="assignUHeight"><?php echo $lm->t('u_height'); ?>:</label>
                <input type="number" id="assignUHeight" name="u_height" class="form-control" min="1" value="1" required>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary"><?php echo $lm->t('assign'); ?></button>
                <button type="button" class="btn btn-secondary" onclick="closeAssignDialog()"><?php echo $lm->t('cancel'); ?></button>
            </div>
        </form>
    </div>
</div>

<style>
.rack-container {
    margin: 20px 0;
    border: 1px solid #ddd;
    border-radius: 5px;
    overflow: hidden;
}

.rack-header {
    background: #f8f9fa;
    padding: 15px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.rack-info {
    display: flex;
    gap: 15px;
}

.rack-visualization {
    display: flex;
    flex-direction: column-reverse;
    min-height: 600px;
    background: #f8f9fa;
}

.rack-unit {
    display: flex;
    border-bottom: 1px solid #ddd;
    min-height: 30px;
    position: relative;
}

.rack-unit.empty {
    background: #e9ecef;
}

.rack-unit.occupied {
    background: #d4edda;
}

.u-label {
    width: 40px;
    text-align: center;
    line-height: 30px;
    font-weight: bold;
    background: #6c757d;
    color: white;
    border-right: 1px solid #ddd;
}

.u-content {
    flex: 1;
    padding: 2px;
}

.host-info {
    background: #007bff;
    color: white;
    padding: 5px;
    border-radius: 3px;
    font-size: 12px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
}

.host-name {
    font-weight: bold;
    margin-bottom: 3px;
}

.host-details {
    flex: 1;
}

.host-actions {
    margin-top: 5px;
}

.filter-container {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.filter-row {
    display: flex;
    gap: 15px;
    align-items: end;
}

.filter-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-item label {
    font-weight: bold;
}

.search-results {
    margin-top: 20px;
}

.overlay-dialogue {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    display: flex;
    justify-content: center;
    align-items: center;
}

.overlay-dialogue-body {
    background: white;
    padding: 20px;
    border-radius: 5px;
    max-width: 500px;
    width: 90%;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 15px;
}
</style>

<script>
function updateRackSelect() {
    const roomSelect = document.getElementById('roomSelect');
    const rackSelect = document.getElementById('rackSelect');
    const selectedRoomId = roomSelect.value;

    // Show/hide rack options based on selected room
    Array.from(rackSelect.options).forEach(option => {
        if (option.value === '') return; // Keep "All Racks" option
        const roomId = option.getAttribute('data-room-id');
        option.style.display = (!selectedRoomId || roomId === selectedRoomId) ? '' : 'none';
    });

    // Reset rack selection if room changed
    if (rackSelect.value && selectedRoomId) {
        const selectedOption = rackSelect.querySelector(`option[value="${rackSelect.value}"]`);
        if (selectedOption && selectedOption.getAttribute('data-room-id') !== selectedRoomId) {
            rackSelect.value = '';
        }
    }
}

function loadSelectedRack() {
    const rackSelect = document.getElementById('rackSelect');
    const selectedOption = rackSelect.options[rackSelect.selectedIndex];
    if (selectedOption && selectedOption.value) {
        const roomId = selectedOption.getAttribute('data-room-id');
        loadRack(roomId, selectedOption.value);
    }
}

function openAssignDialog(hostId, hostName) {
    document.getElementById('assignHostId').value = hostId;
    document.getElementById('assignHostName').textContent = '<?php echo $lm->t('assign_host'); ?>: ' + hostName;
    document.getElementById('assignDialog').style.display = 'block';
}

function closeAssignDialog() {
    document.getElementById('assignDialog').style.display = 'none';
}

function updateAssignRackSelect() {
    const roomSelect = document.getElementById('assignRoom');
    const rackSelect = document.getElementById('assignRack');
    const selectedRoomId = roomSelect.value;

    // Clear existing options except first
    rackSelect.innerHTML = '<option value=""><?php echo $lm->t('select_rack'); ?></option>';

    // Add racks for selected room
    <?php foreach ($data['racks'] as $rack): ?>
    if ('<?php echo $rack['room_id']; ?>' === selectedRoomId) {
        const option = document.createElement('option');
        option.value = '<?php echo $rack['id']; ?>';
        option.textContent = '<?php echo htmlspecialchars($rack['name']); ?>';
        rackSelect.appendChild(option);
    }
    <?php endforeach; ?>
}

// Initialize
updateRackSelect();
</script>

<?php echo $renderer->renderFooter(); ?>
