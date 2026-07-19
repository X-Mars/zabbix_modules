# Zabbix Clonehosts Module

[中文](README.md)

## ✨ Version Compatibility

This module is compatible with Zabbix 6.0 / 7.0+ / 8.0+.

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x
- ✅ Zabbix 8.0.x

**Compatibility Note**: The module includes an automatic version detection layer (`CompatHelper`) that adapts to different Zabbix API versions and library namespaces (e.g. `selectGroups` vs `selectHostGroups`, `groups` vs `hostgroups` result keys, `Zabbix\Core\CModule` vs `Core\CModule`), so no manual configuration is required. The `LangHelper` helper automatically follows the Zabbix system language to switch between Chinese and English.

## Description

Zabbix Clonehosts is a frontend module for Zabbix that batch-clones and imports a large number of hosts based on an existing monitored host's configuration. It adds a "Host Batch Import" menu item under the "Data collection" menu in Zabbix Web (after "Hosts"), supporting both CSV file import and online table entry, with preview, conflict detection, and real-time import progress feedback.

![1](images/image.png)
![2](images/image-1.png)
![3](images/image-2.png)
![4](images/image-3.png)

## Features

- **Source host cloning**: Pick any existing monitored host as the clone template. Its full configuration (interfaces, groups, templates, tags, macros, TLS, IPMI, inventory mode, etc.) can be inherited.
- **Dual-mode data entry**:
  - CSV upload: UTF-8 and GBK supported, automatic header detection and encoding recognition, CSV template download available.
  - Online table entry: add/remove rows, clear all, real-time validation.
- **Smart field inheritance**: Only host name and interface IP are required. Other fields (visible name, port, host groups, templates, tags, macros, description) fall back to the source host's config when left empty.
- **Host group auto-creation**: Host groups specified in the CSV that do not yet exist are created via the API before being linked.
- **Preview & conflict detection**: Full preview before import; auto-detects host name conflicts, missing required fields, in-batch duplicates, and name clashes with existing hosts/templates. Status badges distinguish "exists / direct link", "will create", "not found", and "inherited from source".
- **Selective import**: The preview page lets you check/uncheck the hosts you want to import. Conflicting hosts are skipped automatically; ready hosts can also be excluded individually.
- **Back to edit**: From the preview page you can return to the online table page with all data preserved, so non-ready hosts can be edited without losing what was already entered.
- **Import progress feedback**: Hosts are created one by one via AJAX, with a real-time progress bar and success/fail counters.
- **Result report export**: After import finishes, you can download a CSV report (host name, IP, host ID, result, error message).
- **Bilingual UI**: The interface follows the Zabbix system language (`zh_CN` / `en_GB`), no gettext dependency.
- **Responsive design**: Adapts to different screen sizes.
- **Modern UI**: Follows the native Zabbix design language.

## Installation

### Install the module

```bash
# For Zabbix 6.0 / 7.0 deployment
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/modules/

# For Zabbix 7.2+ / 7.4 / 8.0 deployment
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

### ⚠️ Modify `manifest.json`

If you are using Zabbix 6.0, change the `manifest_version` in `zabbix_clonehosts/manifest.json` to `1.0`:

```bash
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_clonehosts/manifest.json
```

### Enable the module

1. Go to Administration → General → Modules.
2. Click **Scan directory** to detect new modules.
3. Find and enable the "Host Batch Import / 主机批量导入" module.
4. Refresh the page — the module appears under **Data collection → Host Batch Import**, right after "Hosts".

## CSV format

The module ships with a template file `clonehosts_template.csv`. You can download it from the import page via the "Download CSV template" button. CSV header and field descriptions:

| Field | Required | Description |
| --- | --- | --- |
| 主机名称(*) | Yes | Host identifier, must be globally unique (shares namespace with templates) |
| 可见的名称 | No | Falls back to host name when empty |
| 接口IP(*) | Yes | Host interface IP address |
| 端口 | No | Falls back to source host's port when empty |
| 主机群组 | No | Multiple groups separated by `;`; auto-created if missing |
| 模板 | No | Multiple templates separated by `;`; matched by host or name |
| 标签 | No | Format `tag=value`, multiple pairs separated by `;` |
| 宏 | No | Format `{$MACRO}=value`, multiple pairs separated by `;` |
| 描述 | No | Host description |

Example:

```csv
主机名称(*),可见的名称,接口IP(*),端口,主机群组,模板,标签,宏,描述
web-server-01,Web Server 01,192.168.1.10,10050,Servers;Web Servers,Linux by Zabbix Agent;Nginx by HTTP,env=prod;os=linux,{$SNMP_COMMUNITY}=public,Web server 01
db-server-01,DB Server 01,192.168.1.20,10050,Servers;Database Servers,MySQL by Zabbix Agent,env=prod;os=linux;role=db,{$MYSQL_PORT}=3306,Database server 01
```

## Notes

- **Performance**: Import is sequential. Large batches may take a while. Recommended upper bound: ~500 hosts per run.
- **Host name uniqueness**: Host names share the namespace with templates in Zabbix and must be globally unique. The preview stage checks both host and template conflicts.
- **Template dependency**: Templates referenced in the CSV must already exist in Zabbix. Missing templates are marked as "not found" and skipped during import.
- **CSV encoding**: UTF-8 is recommended. If Chinese characters look garbled, switch the page encoding to GBK and re-parse.
- **Permissions**: Using this module requires Zabbix Admin or higher.
- **Data accuracy**: Created hosts are based on a snapshot of the source host's current configuration. Changes to the source host during import do not affect already-created hosts.

## Development

The module is built on the Zabbix module framework. Key files:

- `manifest.json` — module configuration, routes and asset declarations
- `Module.php` — menu registration (compatible with both `Zabbix\Core\CModule` and `Core\CModule`)
- `CompatHelper.php` — Zabbix 6.0/6.4/7.x/8.x API compatibility helper
- `LangHelper.php` — Chinese/English i18n (pure PHP array, no gettext)
- `actions/Clonehosts.php` — main page controller (source host selection, CSV upload, table entry)
- `actions/ClonehostsSource.php` — AJAX endpoint for loading source host config
- `actions/ClonehostsPreview.php` — preview page controller (conflict detection, field status)
- `actions/ClonehostsImport.php` — AJAX import endpoint (creates hosts one by one)
- `views/` — page views (main page, preview, JSON response)
- `assets/js/` — JavaScript (CSV parsing, table management, AJAX import progress)
- `assets/css/` — module styles
- `clonehosts_template.csv` — CSV import template

See Zabbix module development docs for extension: https://www.zabbix.com/documentation/current/en/devel/modules/file_structure

## License

This project is licensed under GPL-2.0.
