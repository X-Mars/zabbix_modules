/* Shared feedback for the Rack module; server messages are rendered as text. */
(function () {
    'use strict';
    const root = document.querySelector('.rack-ui');
    if (!root) return;
    const zh = document.documentElement.lang.toLowerCase().startsWith('zh');
    const labels = {confirm: zh ? '确认' : 'Confirm', cancel: zh ? '取消' : 'Cancel', close: zh ? '关闭' : 'Close'};
    let messageTimer;
    window.RackUI = {
        message(text) {
            const previous = root.querySelector('.rack-message');
            if (previous) previous.remove();
            const box = document.createElement('div');
            box.className = 'rack-message';
            box.setAttribute('role', 'alert');
            box.appendChild(document.createTextNode(String(text)));
            const close = document.createElement('button');
            close.type = 'button';
            close.textContent = '×';
            close.setAttribute('aria-label', labels.close);
            close.onclick = () => box.remove();
            box.appendChild(close);
            root.appendChild(box);
            clearTimeout(messageTimer);
            messageTimer = setTimeout(() => box.remove(), 6000);
        },
        confirm(text) {
            return new Promise(resolve => {
                const previous = document.activeElement;
                const overlay = document.createElement('div');
                overlay.className = 'modal-overlay visible rack-confirm';
                const dialog = document.createElement('section');
                dialog.className = 'modal-content';
                dialog.setAttribute('role', 'dialog');
                dialog.setAttribute('aria-modal', 'true');
                dialog.setAttribute('aria-label', labels.confirm);
                const header = document.createElement('div');
                header.className = 'modal-header';
                header.textContent = labels.confirm;
                const body = document.createElement('div');
                body.className = 'modal-body';
                body.textContent = String(text);
                const footer = document.createElement('div');
                footer.className = 'modal-footer';
                const cancel = document.createElement('button');
                cancel.type = 'button';
                cancel.className = 'btn btn-secondary';
                cancel.textContent = labels.cancel;
                const confirm = document.createElement('button');
                confirm.type = 'button';
                confirm.className = 'btn btn-primary';
                confirm.textContent = labels.confirm;
                const finish = result => {
                    overlay.remove();
                    document.removeEventListener('keydown', onKey, true);
                    if (previous && previous.isConnected) previous.focus();
                    resolve(result);
                };
                const onKey = event => {
                    if (event.key === 'Escape') {
                        event.stopImmediatePropagation();
                        event.preventDefault();
                        finish(false);
                    }
                    if (event.key === 'Tab') {
                        event.preventDefault();
                        (document.activeElement === cancel ? confirm : cancel).focus();
                    }
                };
                cancel.onclick = () => finish(false);
                confirm.onclick = () => finish(true);
                overlay.onclick = event => { if (event.target === overlay) finish(false); };
                footer.append(cancel, confirm);
                dialog.append(header, body, footer);
                overlay.appendChild(dialog);
                root.appendChild(overlay);
                document.addEventListener('keydown', onKey, true);
                cancel.focus();
            });
        }
    };
})();
