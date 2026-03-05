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
- **Docs**: [zabbix_reports/README.md](./zabbix_reports/README.md)

### 2. Zabbix CMDB

- **Purpose**: Provide a lightweight CMDB view in Zabbix for centralized host information and management.
- **Features**: search by hostname/IP, filter by host groups, display host details (interfaces, CPU, memory, groups), bilingual UI, responsive layout.
- **Docs**: [zabbix_cmdb/README.md](./zabbix_cmdb/README.md)

### 3. Zabbix Graph Trees

- **Purpose**: Browse monitoring data using a tree structure, select items and view real-time SVG charts.
- **Features**: host/hostgroup tree, tag-based filtering, multi-select items, synchronized tooltips, fullscreen charts, auto-refresh intervals, multiple time ranges.
- **Docs**: [zabbix_graphtrees/README.md](./zabbix_graphtrees/README.md)

### 4. Zabbix Rack

- **Purpose**: Data center rack visualization and host placement management within Zabbix Web.
- **Features**: room and rack management, 1–60U rack support, 42U visualization, host assignment to U positions, search by rack/host, bilingual UI.
- **Docs**: [zabbix_rack/README.md](./zabbix_rack/README.md)

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

Each module contains its own README with specific installation and usage details.

## Contributing

Contributions, bug reports and feature requests are welcome. Please open issues in the relevant module folder.

## License

All modules follow the Zabbix license terms. See: https://www.zabbix.com/license
