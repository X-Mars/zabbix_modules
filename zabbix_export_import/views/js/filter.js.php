<script>
(() => {
  'use strict';
  const app = document.getElementById('ixe-app');
  if (!app) return;
  const wrapper = app.closest('.wrapper');
  if (wrapper) wrapper.classList.add('ixe-wrapper');
  const page = app.closest('main');
  if (page) page.classList.add('ixe-host-page');
  const overlay = app.querySelector('#ixe-import-overlay');
  let opener = null;
  function close() {
    if (!overlay) return;
    overlay.hidden = true;
    if (opener) opener.focus();
  }
  app.querySelectorAll('[data-ixe-open]').forEach((b) =>
    b.addEventListener('click', () => {
      opener = b;
      overlay.hidden = false;
      overlay.querySelector('[data-ixe-close]').focus();
    }),
  );
  app.querySelectorAll('[data-ixe-close]').forEach((b) => b.addEventListener('click', close));
  if (overlay) {
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) close();
    });
    overlay.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        close();
      }
      if (e.key === 'Tab') {
        const list = [
          ...overlay.querySelectorAll('button,input,select,textarea,a[href],summary'),
        ].filter((x) => x.getClientRects().length && !x.disabled);
        const first = list[0],
          last = list[list.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    });
  }
  const file = app.querySelector('#ixe-upload-file');
  if (file)
    file.addEventListener('change', () => {
      app.querySelector('#ixe-file-name').textContent = file.files.length ? file.files[0].name : '';
    });
  const form = app.querySelector('#ixe-list'),
    filter = app.querySelector('#ixe-filter');
  if (!form || !filter) return;
  filter
    .querySelectorAll('select')
    .forEach((select) => select.addEventListener('change', () => filter.requestSubmit()));
  const key = 'ixe.selection.v2.' + new URLSearchParams(new FormData(filter)).toString();
  const total = Number(app.dataset.total) || 0;
  let selected = new Set(),
    excluded = new Set(),
    allFiltered = false,
    exportScope = 'all';
  const valid = (x) => typeof x === 'string' && /^[a-f0-9]{64}$/.test(x);
  try {
    const saved = JSON.parse(sessionStorage.getItem(key) || '{}');
    allFiltered = saved.mode === 'all';
    selected = new Set(
      (Array.isArray(saved.selected) ? saved.selected : []).filter(valid).slice(0, 10000),
    );
    excluded = new Set(
      (Array.isArray(saved.excluded) ? saved.excluded : []).filter(valid).slice(0, 10000),
    );
    exportScope = ['all', 'selected'].includes(saved.scope)
      ? saved.scope
      : allFiltered || selected.size
        ? 'selected'
        : 'all';
  } catch (e) {}
  const boxes = [...form.querySelectorAll('input[name^="selected["]')],
    all = app.querySelector('#ixe-all'),
    scope = form.querySelector('[name=scope]');
  scope.value = exportScope;
  const selectedLabel = <?= json_encode(\Modules\ZabbixImportExport\Lib\LanguageManager::t('Selected'),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
  function isSelected(value) {
    return allFiltered ? !excluded.has(value) : selected.has(value);
  }
  function change(value, checked) {
    if (allFiltered) {
      if (checked) excluded.delete(value);
      else excluded.add(value);
    } else {
      if (checked) selected.add(value);
      else selected.delete(value);
    }
  }
  function count() {
    return allFiltered ? Math.max(0, total - excluded.size) : selected.size;
  }
  function render() {
    scope.setCustomValidity('');
    boxes.forEach((b) => {
      b.checked = isSelected(b.value);
      b.closest('tr').classList.toggle('is-selected', b.checked);
    });
    all.checked = boxes.length > 0 && boxes.every((b) => b.checked);
    all.indeterminate = boxes.some((b) => b.checked) && !all.checked;
    app.querySelector('#ixe-selection-count').textContent =
      selectedLabel + ': ' + count() + ' / ' + total;
    app.querySelector('#ixe-select-filtered').disabled = total === 0;
    app.querySelector('#ixe-clear-selection').disabled = count() === 0;
    try {
      sessionStorage.setItem(
        key,
        JSON.stringify({
          mode: allFiltered ? 'all' : 'explicit',
          scope: scope.value,
          selected: [...selected],
          excluded: [...excluded],
        }),
      );
    } catch (e) {}
  }
  boxes.forEach((b) =>
    b.addEventListener('change', () => {
      change(b.value, b.checked);
      scope.value = 'selected';
      render();
    }),
  );
  all.addEventListener('change', () => {
    boxes.forEach((b) => change(b.value, all.checked));
    scope.value = 'selected';
    render();
  });
  app.querySelector('#ixe-select-filtered').addEventListener('click', () => {
    allFiltered = true;
    selected.clear();
    excluded.clear();
    scope.value = 'selected';
    render();
  });
  app.querySelector('#ixe-clear-selection').addEventListener('click', () => {
    allFiltered = false;
    selected.clear();
    excluded.clear();
    scope.value = 'selected';
    render();
  });
  form.addEventListener('submit', (e) => {
    if (scope.value === 'selected' && !count()) {
      e.preventDefault();
      scope.setCustomValidity(<?= json_encode(\Modules\ZabbixImportExport\Lib\LanguageManager::t('Select at least one record.'),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>);
      scope.reportValidity();
      return;
    }
    scope.setCustomValidity('');
    form.querySelectorAll('[data-ixe-selection]').forEach((x) => x.remove());
    boxes.forEach((b) => (b.disabled = true));
    const hidden = (name, value) => {
      const h = document.createElement('input');
      h.type = 'hidden';
      h.name = name;
      h.value = value;
      h.dataset.ixeSelection = '1';
      form.append(h);
    };
    if (scope.value === 'selected') {
      hidden('selection_mode', allFiltered ? 'all' : 'explicit');
      (allFiltered ? excluded : selected).forEach((value) =>
        hidden(allFiltered ? 'excluded[]' : 'selected[]', value),
      );
    }
    setTimeout(() => boxes.forEach((b) => (b.disabled = false)), 0);
  });
  scope.addEventListener('change', render);
  render();
  const preview = app.querySelector('#ixe-preview');
  if (preview) preview.scrollIntoView({ block: 'start' });
})();
</script>
