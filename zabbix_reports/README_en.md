# Zabbix Reports Module

[中文](README.md)

## ✨ Version Compatibility

This module is compatible with Zabbix 6.0 / 7.0+ / 8.0+.

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x
- ✅ Zabbix 8.0.x

**Compatibility Note**: The module includes an automatic version detection layer that adapts to different Zabbix API versions and libraries, so no manual configuration is required.

## Description

Zabbix Reports is a frontend module for Zabbix that generates daily, weekly, and monthly reports. It adds a "Zabbix Reports" submenu under the Reports menu in Zabbix Web, supporting report preview, PDF export, and email delivery.

![1](images/1.png)
![2](images/2.png)

## Features

- Report types: daily, weekly, monthly, and custom time range reports
- Report content:
  - Problem count and status statistics
  - Top hosts by number of problems (Top 10)
  - Top hosts by CPU utilization (Top 10)
  - Top hosts by memory utilization (Top 10)
- Functions:
  - In-page report preview
  - Manual PDF export
  - Email delivery of reports (HTML format)
  - Custom date range selection
- Internationalization: supports Chinese and English
- Responsive design: adapts to different screen sizes
- Modern UI: gradient colors and animation effects
- Compatibility: supports Linux Agent and Windows Agent templates
- Statistics: displays total problems, active issues, etc.

## Installation

### Install the module

```bash
# For Zabbix 6.0 / 7.0 deployment
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/modules/

# For Zabbix 7.4 / 8.0 deployment
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

### ⚠️ Modify `manifest.json`

If you are using Zabbix 6.0, change the `manifest_version` in `zabbix_reports/manifest.json` to `1.0`:

```bash
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_reports/manifest.json
```

### Enable the module

1. Go to Administration → General → Modules.
2. Click **Scan directory** to detect new modules.
3. Find and enable the "Zabbix Reports" module.
4. Refresh the page — the module appears under Reports → Zabbix Reports.

## Notes

- Performance: for large environments, limit query result sizes to improve responsiveness.
- Data accuracy: displayed information reflects the current state of the Zabbix database.
- Email configuration: email delivery depends on Zabbix's mail settings being configured.

## Development

The module is built on the Zabbix module framework. Key files:

- `manifest.json` — module configuration
- `Module.php` — menu registration
- `actions/CustomReport.php` — custom report logic
- `actions/DailyReport.php` — daily report logic
- `views/reports.custom.php` — custom report view
- `views/reports.daily.php` — daily report view
- `lib/LanguageManager.php` — i18n helper
- `lib/ViewRenderer.php` — view rendering utilities
- `lib/ZabbixVersion.php` — version compatibility helper

See Zabbix module development docs for extension: https://www.zabbix.com/documentation/7.0/en/devel/modules

## License

This project follows the Zabbix license. See: https://www.zabbix.com/license
