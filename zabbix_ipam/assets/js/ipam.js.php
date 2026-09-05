<?php header('Content-Type: application/javascript; charset=UTF-8'); ?>
(function () {
    'use strict';

    const root = document.querySelector('.ipam');
    if (!root) {
        return;
    }

    const endpoint = 'zabbix.php?action=ip.ajax';
    const call = data => fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams(data)
    }).then(async response => {
        const body = await response.text();
        try {
            return JSON.parse(body);
        }
        catch (error) {
            throw new Error(root.dataset.requestFailed || 'Request failed.');
        }
    });

    const toast = (message, error = false) => {
        const element = document.getElementById('ipam-toast');
        if (!element) {
            return;
        }
        element.textContent = message;
        element.className = 'ipam-toast '+(error ? 'is-error' : 'is-ok');
        element.hidden = false;
        setTimeout(() => element.hidden = true, 3200);
    };

    const open = id => {
        const element = document.getElementById(id);
        if (element) {
            element.hidden = false;
            document.body.classList.add('ipam-modal-open');
        }
    };

    const close = element => {
        element.hidden = true;
        document.body.classList.remove('ipam-modal-open');
    };

    document.addEventListener('click', async event => {
        const close_button = event.target.closest('[data-close]');
        if (close_button) {
            close(close_button.closest('.ipam-modal'));
            return;
        }

        if (event.target.closest('.js-add')) {
            const form = document.getElementById('range-form');
            form.reset();
            form.elements.id.value = '';
            form.elements.scan_interval.value = '60';
            form.elements.enabled.checked = true;
            document.getElementById('range-modal-title').textContent = event.target.closest('.js-add').textContent;
            open('range-modal');
            return;
        }

        const edit = event.target.closest('.js-edit');
        if (edit) {
            const data = JSON.parse(edit.dataset.range);
            const form = document.getElementById('range-form');
            form.elements.id.value = data.id;
            form.elements.name.value = data.name;
            form.elements.range.value = data.range;
            form.elements.scan_interval.value = data.scan_interval;
            form.elements.enabled.checked = !!data.enabled;
            document.getElementById('range-modal-title').textContent = edit.textContent;
            open('range-modal');
            return;
        }

        const save = event.target.closest('.js-save-range');
        if (save) {
            event.preventDefault();
            const form = document.getElementById('range-form');
            if (!form.reportValidity()) {
                return;
            }
            save.disabled = true;
            const data = Object.fromEntries(new FormData(form));
            data.op = 'save_range';
            data.enabled = form.elements.enabled.checked ? '1' : '0';
            try {
                const response = await call(data);
                if (!response.ok) {
                    throw new Error(response.message);
                }
                location.href = 'zabbix.php?action=ip.manager';
            }
            catch (error) {
                toast(error.message, true);
                save.disabled = false;
            }
            return;
        }

        const scan = event.target.closest('.js-scan');
        if (scan) {
            scan.disabled = true;
            scan.textContent = root.dataset.loading || 'Loading...';
            try {
                const response = await call({op: 'start', rangeid: scan.dataset.rangeId});
                if (!response.ok) {
                    throw new Error(response.message);
                }
                location.href = 'zabbix.php?action=ip.scan&taskid='+encodeURIComponent(response.task.id);
            }
            catch (error) {
                toast(error.message, true);
                scan.disabled = false;
            }
            return;
        }

        const remove = event.target.closest('.js-delete');
        if (remove) {
            if (!confirm(root.dataset.confirmDelete || 'Delete?')) {
                return;
            }
            try {
                const response = await call({op: 'delete_range', rangeid: remove.dataset.rangeId});
                if (!response.ok) {
                    throw new Error(response.message);
                }
                location.reload();
            }
            catch (error) {
                toast(error.message, true);
            }
            return;
        }

        const count = event.target.closest('.ipam-count');
        if (count) {
            const grid = document.getElementById('ip-grid');
            grid.innerHTML = '<div class="ipam-grid-loading">'+(root.dataset.loading || 'Loading...')+'</div>';
            open('matrix-modal');
            try {
                const response = await call({op: 'range_ips', rangeid: count.dataset.rangeId});
                if (!response.ok) {
                    throw new Error(response.message);
                }
                document.getElementById('matrix-subtitle').textContent = response.range.name+' · '+response.range.range;
                const fragment = document.createDocumentFragment();
                response.ips.forEach(item => {
                    const linked = item.alive && item.host_url;
                    const cell = document.createElement(linked ? 'a' : 'div');
                    cell.className = 'ip-cell '+(item.alive ? 'is-alive' : 'is-dead')+(linked ? ' is-linked' : '');
                    cell.title = item.host_name ? item.ip+' · '+item.host_name : item.ip;
                    if (linked) {
                        cell.href = item.host_url;
                    }
                    const parts = item.ip.split('.');
                    cell.innerHTML = '<strong>'+parts[parts.length - 1]+'</strong><span>'+item.ip+'</span>';
                    fragment.appendChild(cell);
                });
                grid.innerHTML = '';
                grid.appendChild(fragment);
            }
            catch (error) {
                grid.innerHTML = '<div class="ipam-empty">'+error.message+'</div>';
            }
            return;
        }

        const stop = event.target.closest('.js-stop');
        if (stop) {
            stop.disabled = true;
            try {
                const response = await call({op: 'stop', taskid: stop.dataset.taskId});
                if (!response.ok) {
                    throw new Error(response.message);
                }
                location.reload();
            }
            catch (error) {
                toast(error.message, true);
                stop.disabled = false;
            }
        }
    });

    const range_form = document.getElementById('range-form');
    if (range_form) {
        range_form.addEventListener('submit', event => {
            event.preventDefault();
            range_form.querySelector('.js-save-range').click();
        });
    }

    const active_rows = [...document.querySelectorAll('[data-task-row]')]
        .filter(row => row.querySelector('.status-running,.status-pending'));

    if (active_rows.length) {
        const poll = async () => {
            let active = false;
            for (const row of active_rows) {
                try {
                    const response = await call({op: 'status', taskid: row.dataset.taskRow});
                    if (!response.ok) {
                        continue;
                    }
                    const task = response.task;
                    const percent = task.total ? Math.round(task.scanned / task.total * 100) : 0;
                    const badge = row.querySelector('.task-badge');
                    badge.className = 'task-badge status-'+task.status;
                    badge.textContent = root.getAttribute('data-label-'+task.status) || task.status;
                    row.querySelector('.task-progress i').style.width = percent+'%';
                    row.querySelector('[data-progress-text]').textContent = task.scanned+' / '+task.total+' · '+percent+'%';
                    row.querySelector('[data-alive]').textContent = task.alive;
                    row.querySelector('[data-shards]').textContent = task.completed_shards+' / '+task.shards.length;
                    if (['pending', 'running'].includes(task.status)) {
                        active = true;
                    }
                }
                catch (error) {
                }
            }
            if (active) {
                setTimeout(poll, 1800);
            }
        };
        poll();
    }
})();
