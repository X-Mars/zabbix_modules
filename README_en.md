# Zabbix Modules Collection

[中文](README.md)

## ✨ Version Compatibility

All modules are compatible with Zabbix 6.0 / 7.0+ / 8.0+ versions.

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x
- ✅ Zabbix 8.0.x

## Description

This repository contains a collection of independent Zabbix frontend modules that extend Zabbix Web with additional functionality.

## Modules

### 1. Zabbix Reports

- **Purpose**: Generate daily, weekly and monthly reports in Zabbix, with preview, PDF export and email delivery features.
- **Features**: report generation (daily/weekly/monthly), problem counts and status stats, top problem hosts, top CPU/memory hosts, in-page preview, PDF export, email push.

![1](zabbix_reports/images/1.png)
![2](zabbix_reports/images/2.png)

- **Docs**: [zabbix_reports/README.md](./zabbix_reports/README.md)

### 2. Zabbix CMDB

- **Purpose**: Provide a lightweight CMDB view in Zabbix for centralized host information and management.
- **Features**: search by hostname/IP, filter by host groups, display host details (interfaces, CPU, memory, groups), bilingual UI, responsive layout.

![1](zabbix_cmdb/images/1.jpg)
![2](zabbix_cmdb/images/2.png)
![3](zabbix_cmdb/images/3.jpg)

- **Docs**: [zabbix_cmdb/README.md](./zabbix_cmdb/README.md)

### 3. Zabbix Graph Trees

- **Purpose**: Browse monitoring data using a tree structure, select items and view real-time SVG charts.
- **Features**: host/hostgroup tree, tag-based filtering, multi-select items, synchronized tooltips, fullscreen charts, auto-refresh intervals, multiple time ranges.

![1](zabbix_graphtrees/images/1.png)
![2](zabbix_graphtrees/images/2.png)

- **Docs**: [zabbix_graphtrees/README.md](./zabbix_graphtrees/README.md)

### 4. Zabbix Rack

- **Purpose**: Data center rack visualization and host placement management within Zabbix Web.
- **Features**: room and rack management, 1–60U rack support, 42U visualization, host assignment to U positions, search by rack/host, bilingual UI.

![1](zabbix_rack/images/1.png)
![2](zabbix_rack/images/2.png)
![3](zabbix_rack/images/3.png)

- **Docs**: [zabbix_rack/README.md](./zabbix_rack/README.md)

### 5. Zabbix SNMP

- **Purpose**: An SNMP assistant Zabbix frontend module providing a MIB browser and SNMP Walk, with OID resolution, one-click item creation and bulk SNMP template creation.
- **Features**: MIB browser with object table (OID, resolved OID, syntax/access/status, view source); SNMP Walk reading host SNMP connection parameters (v1/v2c/v3) into a paginated table; view raw data; copy snmpget command/OID; create a single item with automatic value-type mapping; check multiple results to create an SNMP template with items in bulk; bilingual UI.

![1](zabbix_snmp/images/1.png)
![2](zabbix_snmp/images/2.png)
![3](zabbix_snmp/images/3.png)

- **Docs**: [zabbix_snmp/README.md](./zabbix_snmp/README.md)

### 6. Zabbix JumpServer

- **Purpose**: A Zabbix frontend module that synchronizes Zabbix hosts and host groups to a JumpServer bastion, with bulk push, automatic node creation, and one-click connect to assets from Zabbix.
- **Features**: host group, host, and alarm status dropdowns plus IP/hostname search (all groups/hosts by default); one-click "Push all host groups" / "Push all hosts" with auto node creation; "Fetch JumpServer asset IDs" to match by IP and write tags back; platform detection (Linux/Windows) and asset create/update with optional account template on create only; alarm status counts per severity with expandable row details; asset ID written back as a Zabbix host tag; re-push on hover for synced hosts; "Connect" button linking to the JumpServer connect page; bilingual UI.

![1](zabbix_jumpserver/images/1.png)

- **Docs**: [zabbix_jumpserver/README.md](./zabbix_jumpserver/README.md)

### 7. IM Sync Assistant

- **Purpose**: Sync **WeCom**, **Feishu**, and **DingTalk** org structures into Zabbix **user groups**, and match or create Zabbix users with group membership.
- **Features**: multi-platform support; web UI for sync credentials (**Users → Sync Settings**, one active setting at a time); department sync with optional full-path group names; user sync with match/auto-create (12-char password shown once in results); DingTalk users without mobile get pinyin usernames from display name; preview before sync; bilingual UI.

![1](images/1.png)
![2](images/2.png)
![3](images/3.png)

- **Docs**: [zabbix_im/README_en.md](./zabbix_im/README_en.md)（[中文](./zabbix_im/README.md)）

### 8. Zabbix Clonehosts

- **Purpose**: Batch-clone and import a large number of hosts based on an existing monitored host's configuration, with CSV upload and online table entry, plus preview, conflict detection, selective import and real-time progress feedback.
- **Features**: source host cloning (interfaces, groups, templates, tags, macros, TLS, IPMI, inventory_mode inheritable); dual-mode data entry (CSV upload with UTF-8/GBK auto-detection and template download / online table with add-remove rows and live validation); smart field inheritance (only host name and IP required, others fall back to source); auto-create missing host groups; full preview with conflict detection (host name conflicts, missing required fields, in-batch duplicates, name clashes with hosts/templates) and status badges (exists / new / not found / inherited); selective import via per-row checkboxes; back-to-edit from preview with all data preserved; sequential AJAX import with real-time progress bar and counters; CSV result report download (host name, IP, host ID, result, error); bilingual UI.

![1](zabbix_clonehosts/images/image.png)
![2](zabbix_clonehosts/images/image-1.png)
![3](zabbix_clonehosts/images/image-2.png)
![4](zabbix_clonehosts/images/image-3.png)

- **Docs**: [zabbix_clonehosts/README_en.md](./zabbix_clonehosts/README_en.md)（[中文](./zabbix_clonehosts/README.md)）

## Installation

### Deploy all modules (recommended)

1. For Zabbix 6.0 / 7.0:

```bash
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/modules/
```

2. For Zabbix 7.4 / 8.0:

```bash
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

3. If you run Zabbix 6.0, change `manifest_version` for each module:

```bash
cd /usr/share/zabbix/modules/
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_reports/manifest.json
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_cmdb/manifest.json
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_graphtrees/manifest.json
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_rack/manifest.json
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_snmp/manifest.json
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_jumpserver/manifest.json
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_im/manifest.json
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_clonehosts/manifest.json
```

For Zabbix 7.0+ / 8.0+, no change is required.

### Enable modules in Zabbix Web

1. Open Zabbix Web UI → Administration → General → Modules
2. Click **Scan directory** to detect new modules
3. Enable the modules you need

After enabling and refreshing the UI, the modules appear under the following menus:

- **Reports → Zabbix Reports** (Daily/Weekly/Monthly)
- **Inventory → CMDB**
- **Monitoring → Graph Trees**
- **Inventory → Rack Management**
- **Data collection → SNMP Assistant** (Zabbix Mibs / Zabbix Walk)
- **Inventory → JumpServer**
- **Users → IM Sync Assistant** (IM Sync / Sync Settings)
- **Data collection → Host Batch Import** (batch clone hosts from a source host)

Each module contains its own README with specific installation and usage details.

## Contributing

Contributions, bug reports and feature requests are welcome. Please open issues in the relevant module folder.

## License

All modules follow the Zabbix license terms. See: https://www.zabbix.com/license
