<style>
.ixe-wrapper > .header-title,
.ixe-wrapper > footer,
.wrapper:has(#ixe-app) > .header-title,
.wrapper:has(#ixe-app) > footer {
  min-width: 0;
  width: 100%;
  box-sizing: border-box;
}
main.ixe-host-page,
main:has(#ixe-app) {
  min-width: 0;
  width: 100%;
  box-sizing: border-box;
  flex: 1 1 0;
}
#ixe-app {
  --ixe-primary: #409eff;
  --ixe-success: #67c23a;
  --ixe-warning: #e6a23c;
  --ixe-danger: #f56c6c;
  --ixe-text: #303133;
  --ixe-muted: #909399;
  --ixe-secondary: #606266;
  --ixe-border: #dcdfe6;
  --ixe-soft-border: #ebeef5;
  --ixe-bg: #fff;
  --ixe-soft: #f5f7fa;
  --ixe-blue-bg: #ecf5ff;
  color: var(--ixe-text);
  font-size: 14px;
  line-height: 1.5;
  max-width: 1920px;
  margin: 0 auto;
  padding: 0 4px 24px;
}
#ixe-app,
#ixe-app * {
  box-sizing: border-box;
}
#ixe-app [hidden] {
  display: none !important;
}
#ixe-app h2,
#ixe-app h3,
#ixe-app h4,
#ixe-app p {
  margin: 0;
}
#ixe-app h2 {
  font-size: 24px;
  line-height: 1.4;
  font-weight: 600;
}
#ixe-app h3 {
  font-size: 18px;
  font-weight: 600;
}
#ixe-app h4 {
  font-size: 15px;
  font-weight: 600;
}
#ixe-app a {
  color: var(--ixe-primary);
  text-decoration: none;
}
#ixe-app button,
#ixe-app input,
#ixe-app select,
#ixe-app textarea {
  font: inherit;
  text-transform: none;
  letter-spacing: normal;
}
#ixe-app form {
  margin: 0;
  padding: 0;
}
#ixe-app .ixe-muted {
  color: var(--ixe-secondary);
  font-weight: 400;
}
#ixe-app .ixe-page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  margin-bottom: 22px;
}
#ixe-app .ixe-page-header p {
  margin-top: 7px;
}
#ixe-app .ixe-card {
  background: var(--ixe-bg);
  border: 1px solid var(--ixe-soft-border);
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.025);
  overflow: hidden;
  margin-bottom: 16px;
}
#ixe-app .ixe-filter-card {
  padding: 20px;
}
#ixe-app .ixe-filter {
  display: flex;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: 16px;
}
#ixe-app .ixe-field {
  display: flex;
  flex-direction: column;
  gap: 7px;
  min-width: 0;
  text-align: left;
  margin: 0;
  position: static;
  color: var(--ixe-secondary);
}
#ixe-app .ixe-filter > .ixe-field {
  flex: 1 1 180px;
  max-width: 340px;
}
#ixe-app .ixe-label {
  font-size: 13px;
  font-weight: 500;
  display: block;
}
#ixe-app .ixe-input,
#ixe-app .ixe-select,
#ixe-app .ixe-textarea {
  display: block;
  width: 100%;
  min-width: 0;
  height: 36px;
  padding: 7px 11px;
  background: var(--ixe-bg);
  color: var(--ixe-text);
  border: 1px solid var(--ixe-border);
  border-radius: 4px;
  box-shadow: none;
  outline: none;
  transition: border-color 0.15s;
}
#ixe-app .ixe-select {
  appearance: auto;
  padding-right: 8px;
}
#ixe-app .ixe-input:hover,
#ixe-app .ixe-select:hover,
#ixe-app .ixe-textarea:hover {
  border-color: #c0c4cc;
}
#ixe-app .ixe-input:focus,
#ixe-app .ixe-select:focus,
#ixe-app .ixe-textarea:focus {
  border-color: var(--ixe-primary);
  box-shadow: 0 0 0 2px rgba(64, 158, 255, 0.12);
}
#ixe-app .ixe-textarea {
  height: auto;
  resize: vertical;
  font-family: monospace;
}
#ixe-app .ixe-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 36px;
  min-width: 64px;
  padding: 0 15px;
  border: 1px solid var(--ixe-border);
  border-radius: 4px;
  background: var(--ixe-bg);
  color: var(--ixe-secondary);
  white-space: nowrap;
  font-weight: 500;
  cursor: pointer;
  line-height: 1;
  box-shadow: none;
  text-decoration: none;
  vertical-align: middle;
  margin: 0;
}
#ixe-app .ixe-button:hover {
  color: var(--ixe-primary);
  border-color: #a0cfff;
  background: var(--ixe-blue-bg);
}
#ixe-app .ixe-button--primary {
  background: var(--ixe-primary);
  border-color: var(--ixe-primary);
  color: #fff;
}
#ixe-app .ixe-button--primary:hover {
  background: #79bbff;
  border-color: #79bbff;
  color: #fff;
}
#ixe-app .ixe-button:focus-visible,
#ixe-app summary:focus-visible,
#ixe-app a:focus-visible {
  outline: 2px solid var(--ixe-primary);
  outline-offset: 3px;
}
#ixe-app .ixe-button.is-disabled {
  opacity: 0.45;
  pointer-events: none;
}
#ixe-app .ixe-filter-actions,
#ixe-app .ixe-toolbar-actions,
#ixe-app .ixe-toolbar-title,
#ixe-app .ixe-pagination {
  display: flex;
  align-items: center;
  gap: 10px;
}
#ixe-app .ixe-scope-note {
  font-size: 12px;
  color: var(--ixe-muted);
  margin: 12px 0 0;
  line-height: 1.6;
}
#ixe-app > .ixe-scope-note {
  margin: 0 4px 16px;
}
#ixe-app .ixe-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding: 16px 20px;
  border-bottom: 1px solid var(--ixe-soft-border);
}
#ixe-app .ixe-toolbar-title {
  flex-wrap: wrap;
}
#ixe-app .ixe-toolbar-actions .ixe-select {
  width: 190px;
}
#ixe-app .ixe-toolbar-title > .ixe-muted {
  font-size: 12px;
  padding-left: 6px;
}
#ixe-app .ixe-table-scroll {
  overflow-x: auto;
  width: 100%;
}
#ixe-app .ixe-table {
  border-collapse: collapse;
  border-spacing: 0;
  width: 100%;
  min-width: 850px;
  margin: 0;
  background: var(--ixe-bg);
  table-layout: auto;
  font-size: 13px;
}
#ixe-app .ixe-table th {
  background: var(--ixe-soft);
  font-weight: 600;
  color: var(--ixe-secondary);
  white-space: nowrap;
  text-align: left;
  padding: 12px 16px;
  border-bottom: 1px solid var(--ixe-soft-border);
}
#ixe-app .ixe-table td {
  padding: 13px 16px;
  border-bottom: 1px solid var(--ixe-soft-border);
  vertical-align: middle;
  text-align: left;
  line-height: 1.65;
  max-width: 380px;
  overflow-wrap: anywhere;
}
#ixe-app .ixe-table tbody tr:last-child td {
  border-bottom: 0;
}
#ixe-app .ixe-table tbody tr:hover,
#ixe-app .ixe-table tbody tr.is-selected {
  background: var(--ixe-blue-bg);
}
#ixe-app .ixe-table .ixe-checkbox-cell {
  width: 44px;
  min-width: 44px;
  padding-left: 18px;
  padding-right: 8px;
}
#ixe-app .ixe-check {
  appearance: auto;
  position: static;
  opacity: 1;
  display: inline-block;
  width: 15px;
  height: 15px;
  margin: 0;
  vertical-align: middle;
  accent-color: var(--ixe-primary);
  cursor: pointer;
  clip: auto;
}
#ixe-app .ixe-record {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 160px;
}
#ixe-app .ixe-record-name {
  font-weight: 500;
  font-size: 14px;
}
#ixe-app .ixe-record small {
  font-size: 12px;
}
#ixe-app .ixe-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}
#ixe-app .ixe-badge {
  display: inline-flex;
  align-items: center;
  white-space: nowrap;
  font-size: 12px;
  line-height: 22px;
  border-radius: 4px;
  padding: 0 7px;
  background: var(--ixe-soft);
  color: var(--ixe-secondary);
  border: 1px solid var(--ixe-soft-border);
  font-weight: 400;
}
#ixe-app .ixe-badge--primary {
  color: var(--ixe-primary);
  background: var(--ixe-blue-bg);
  border-color: #d9ecff;
}
#ixe-app .ixe-badge--success {
  color: #529b2e;
  background: #f0f9eb;
  border-color: #e1f3d8;
}
#ixe-app .ixe-badge--danger {
  color: #f56c6c;
  background: #fef0f0;
  border-color: #fde2e2;
}
#ixe-app .ixe-badge--warning {
  color: #b88230;
  background: #fdf6ec;
  border-color: #faecd8;
}
#ixe-app .ixe-mono,
#ixe-app .ixe-key {
  font-family: Consolas, monospace;
  font-size: 12px;
}
#ixe-app .ixe-key {
  white-space: normal;
  overflow-wrap: anywhere;
  background: var(--ixe-soft);
  border-radius: 3px;
  padding: 3px 6px;
  color: var(--ixe-secondary);
}
#ixe-app .ixe-table .ixe-empty {
  height: 200px;
  text-align: center;
  color: var(--ixe-muted);
}
#ixe-app .ixe-empty > * {
  display: block;
  margin: 0 auto 8px;
}
#ixe-app .ixe-empty strong {
  font-weight: 500;
  color: var(--ixe-secondary);
}
#ixe-app .ixe-empty p {
  font-size: 12px;
}
#ixe-app .ixe-empty .ixe-icon {
  width: 40px;
  height: 40px;
  color: #a8abb2;
  margin-bottom: 12px;
}
#ixe-app .ixe-table-footer {
  padding: 14px 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 12px;
  border-top: 1px solid var(--ixe-soft-border);
  font-size: 12px;
}
#ixe-app .ixe-pagination .ixe-button {
  min-width: 56px;
  height: 30px;
  padding: 0 10px;
}
#ixe-app .ixe-section {
  padding: 20px;
}
#ixe-app .ixe-section > .ixe-toolbar {
  padding: 0 0 16px;
}
#ixe-app .ixe-metrics {
  display: flex;
  gap: 16px;
  margin: 16px 0;
}
#ixe-app .ixe-metric {
  display: flex;
  align-items: center;
  gap: 10px;
  background: var(--ixe-soft);
  border-radius: 6px;
  padding: 12px 20px;
}
#ixe-app .ixe-metric > strong {
  font-size: 22px;
}
#ixe-app .ixe-alert {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 4px;
  margin-bottom: 16px;
  font-size: 13px;
  overflow-wrap: anywhere;
  line-height: 1.7;
}
#ixe-app .ixe-alert--danger {
  color: #c45656;
  background: #fef0f0;
}
#ixe-app .ixe-alert--warning {
  color: #b88230;
  background: #fdf6ec;
}
#ixe-app .ixe-alert--info {
  color: var(--ixe-secondary);
  background: var(--ixe-soft);
}
#ixe-app .ixe-preview-row {
  border-bottom: 1px solid var(--ixe-soft-border);
  padding: 12px 0;
}
#ixe-app .ixe-preview-row summary {
  cursor: pointer;
  display: list-item;
  line-height: 2;
  overflow-wrap: anywhere;
}
#ixe-app .ixe-preview-row summary > * {
  margin-right: 10px;
}
#ixe-app .ixe-diff {
  padding: 16px;
  background: var(--ixe-soft);
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  max-height: 350px;
  overflow: auto;
  font-size: 12px;
  color: var(--ixe-secondary);
}
#ixe-app .ixe-confirm-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  margin-top: 20px;
}
#ixe-app .ixe-overlay {
  position: fixed;
  inset: 0;
  z-index: 10000;
  background: rgba(0, 0, 0, 0.35);
  display: flex;
  justify-content: flex-end;
}
#ixe-app .ixe-drawer {
  width: 640px;
  max-width: 100%;
  height: 100%;
  background: var(--ixe-bg);
  display: flex;
  flex-direction: column;
  box-shadow: -8px 0 32px rgba(0, 0, 0, 0.12);
  color: var(--ixe-text);
  outline: none;
}
#ixe-app .ixe-drawer-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 24px;
  border-bottom: 1px solid var(--ixe-soft-border);
  gap: 16px;
  flex-shrink: 0;
}
#ixe-app .ixe-drawer-header p {
  font-size: 12px;
  margin-top: 5px;
}
#ixe-app .ixe-drawer-header button {
  min-width: 32px;
  font-size: 24px;
  padding: 0;
  border: 0;
}
#ixe-app #ixe-upload-form {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
}
#ixe-app .ixe-drawer-body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: 24px;
}
#ixe-app .ixe-drawer-footer {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  border-top: 1px solid var(--ixe-soft-border);
  flex-shrink: 0;
  background: var(--ixe-bg);
}
#ixe-app .ixe-step-heading {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0 0 16px;
}
#ixe-app .ixe-step-heading .ixe-badge {
  border-radius: 50%;
  width: 24px;
  justify-content: center;
  padding: 0;
}
#ixe-app .ixe-upload-zone {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 10px;
  border: 1px dashed var(--ixe-border);
  border-radius: 6px;
  min-height: 162px;
  padding: 24px;
  text-align: center;
  cursor: pointer;
  background: var(--ixe-soft);
  margin: 0;
}
#ixe-app .ixe-upload-zone:hover,
#ixe-app .ixe-upload-zone:focus-within {
  border-color: var(--ixe-primary);
  background: var(--ixe-blue-bg);
}
#ixe-app .ixe-upload-zone input[type='file'] {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  cursor: pointer;
}
#ixe-app .ixe-upload-zone .ixe-icon {
  width: 36px;
  height: 36px;
  color: var(--ixe-primary);
}
#ixe-app .ixe-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}
#ixe-app .ixe-file-name {
  font-size: 12px;
  color: var(--ixe-primary);
  overflow-wrap: anywhere;
  min-height: 24px;
  margin: 8px 0 16px;
}
#ixe-app .ixe-form-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 18px;
  margin-bottom: 20px;
}
#ixe-app .ixe-check-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
  margin: 0 0 24px;
}
#ixe-app .ixe-check-label {
  display: flex;
  align-items: center;
  gap: 9px;
  margin: 0;
  cursor: pointer;
  font-size: 13px;
  color: var(--ixe-secondary);
  text-align: left;
}
#ixe-app .ixe-check-label input {
  flex-shrink: 0;
}
#ixe-app .ixe-advanced {
  border-top: 1px solid var(--ixe-soft-border);
  padding: 16px 0;
}
#ixe-app .ixe-advanced summary {
  cursor: pointer;
  font-weight: 500;
  font-size: 13px;
  color: var(--ixe-secondary);
}
#ixe-app .ixe-advanced[open] summary {
  margin-bottom: 16px;
}
#ixe-app .ixe-advanced .ixe-form-grid {
  margin-bottom: 0;
}
#ixe-app .ixe-drawer-body > .ixe-alert:last-child {
  margin: 12px 0 0;
}
html[color-scheme='dark'] #ixe-app {
  --ixe-text: #e5eaf3;
  --ixe-muted: #8d9095;
  --ixe-secondary: #cfd3dc;
  --ixe-border: #4c4d4f;
  --ixe-soft-border: #363637;
  --ixe-bg: #1d1e1f;
  --ixe-soft: #262727;
  --ixe-blue-bg: #182b3d;
}
html[color-scheme='dark'] #ixe-app .ixe-badge--success {
  background: #1c2c1a;
  border-color: #2a401f;
  color: #95d475;
}
html[color-scheme='dark'] #ixe-app .ixe-badge--primary {
  border-color: #21466b;
}
html[color-scheme='dark'] #ixe-app .ixe-badge--danger,
html[color-scheme='dark'] #ixe-app .ixe-alert--danger {
  background: #3b1d1d;
  border-color: #582828;
  color: #fab6b6;
}
html[color-scheme='dark'] #ixe-app .ixe-alert--warning {
  background: #362b1e;
  color: #eebe77;
}
@media (max-width: 1100px) {
  #ixe-app .ixe-toolbar {
    flex-wrap: wrap;
  }
  #ixe-app .ixe-filter > .ixe-field {
    max-width: none;
  }
  #ixe-app .ixe-toolbar-actions {
    flex-wrap: wrap;
  }
  #ixe-app .ixe-page-header {
    align-items: flex-start;
  }
}
@media (max-width: 640px) {
  #ixe-app {
    padding: 0 0 16px;
  }
  #ixe-app .ixe-page-header {
    flex-direction: column;
    gap: 14px;
  }
  #ixe-app h2 {
    font-size: 21px;
  }
  #ixe-app .ixe-filter-card,
  #ixe-app .ixe-section {
    padding: 16px;
  }
  #ixe-app .ixe-toolbar {
    padding: 14px 16px;
  }
  #ixe-app .ixe-toolbar-actions {
    width: 100%;
  }
  #ixe-app .ixe-toolbar-actions .ixe-select {
    flex: 1;
    min-width: 150px;
  }
  #ixe-app .ixe-table-footer {
    flex-wrap: wrap;
    padding: 12px 16px;
  }
  #ixe-app .ixe-drawer-header,
  #ixe-app .ixe-drawer-body {
    padding: 20px;
  }
  #ixe-app .ixe-form-grid,
  #ixe-app .ixe-check-grid {
    grid-template-columns: 1fr;
  }
  #ixe-app .ixe-metrics {
    flex-wrap: wrap;
    gap: 8px;
  }
  #ixe-app .ixe-metric {
    padding: 8px 12px;
  }
  #ixe-app .ixe-confirm-bar {
    align-items: flex-start;
    flex-direction: column;
  }
}
#ixe-app .ixe-button:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
#ixe-app #ixe-select-filtered,
#ixe-app #ixe-clear-selection {
  height: 30px;
  font-size: 12px;
  padding: 0 10px;
}
#ixe-app .ixe-toolbar-title {
  row-gap: 8px;
}
</style>
