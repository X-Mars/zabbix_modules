document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('zb-export');
    if (!form) {
        return;
    }

    const search = document.getElementById('zb-search');
    const excludeHistory = document.getElementById('zb-exclude-history');
    const selectVisible = document.getElementById('zb-select-visible');
    const noMatches = document.getElementById('zb-no-tables');
    const count = form.querySelector('.zb-count');
    const scopeAll = form.querySelector('[name="scope"][value="all"]');
    const scopeSelected = form.querySelector('[name="scope"][value="selected"]');
    const tables = Array.from(form.querySelectorAll('[data-table]'), row => ({
        row, checkbox: row.querySelector('input')
    }));

    function update() {
        const term = search.value.trim().toLowerCase();
        for (const {row, checkbox} of tables) {
            checkbox.disabled = excludeHistory.checked && row.hasAttribute('data-history');
            row.hidden = checkbox.disabled || !row.dataset.table.toLowerCase().includes(term);
        }
        const visible = tables.filter(table => !table.row.hidden);
        const eligible = tables.filter(table => !table.checkbox.disabled);
        const selection = eligible.filter(table => table.checkbox.checked);
        const selectedVisible = visible.filter(table => table.checkbox.checked).length;
        selectVisible.checked = visible.length > 0 && selectedVisible === visible.length;
        selectVisible.indeterminate = selectedVisible > 0 && !selectVisible.checked;
        selectVisible.disabled = visible.length === 0;
        noMatches.hidden = visible.length > 0;
        count.textContent = count.dataset.format.replace('%d', scopeAll.checked ? eligible.length : selection.length)
            .replace('%d', tables.length);
    }

    search.addEventListener('input', update);
    selectVisible.addEventListener('change', () => {
        scopeSelected.checked = true;
        for (const {row, checkbox} of tables) {
            if (!row.hidden) {
                checkbox.checked = selectVisible.checked;
            }
        }
        update();
    });
    form.addEventListener('change', event => {
        if (event.target.name === 'tables[]') {
            scopeSelected.checked = true;
        }
        update();
    });
    update();
});
