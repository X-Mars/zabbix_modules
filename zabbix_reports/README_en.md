# Zabbix Reports Module

[中文](README.md)

## ✨ Version Compatibility

This module is compatible with Zabbix 6.0 and 7.0+ versions.

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x

**Compatibility Note**: The module includes intelligent version detection that automatically adapts to different Zabbix API versions and class libraries, requiring no manual configuration.

## Description

This is a frontend module for Zabbix that generates daily, weekly, and monthly reports. The module adds a Zabbix Reports menu under the Reports section of Zabbix Web, supporting report preview, PDF export, and email push functionality.

![1](images/1.png)

## Features

- **Report Types**: Support for daily, weekly, monthly reports, and custom time range reports
- **Report Content**:
  - Problem count and status statistics
  - Top problem hosts (Top 10)
  - Top CPU utilization hosts (Top 10)
  - Top memory utilization hosts (Top 10)
- **Functions**:
  - In-page report preview
  - Manual PDF export
  - Email push reports (HTML format)
  - Custom date range selection
- **Internationalization**: Support for Chinese and English interfaces
- **Responsive Design**: Adapts to different screen sizes
- **Modern Interface**: Modern design with gradient colors and animation effects
- **Compatibility**: Supports Linux Agent and Windows Agent templates
- **Statistics**: Display statistics such as total problems, active issues, etc.

## Installation Steps

### Install Module

```bash
# Zabbix 6.0 / 7.0 deployment method
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/modules/

# Zabbix 7.4 deployment method
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

### ⚠️ Modify manifest.json File

```bash
# ⚠️ For Zabbix 6.0, modify manifest_version
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_reports/manifest.json
```

### Enable Module

1. Go to **Administration → General → Modules**.
2. Click **Scan directory** to scan for new modules.
3. Find "Zabbix Reports" module and enable it.
4. Refresh the page, the module will appear under the **Reports** menu as "Zabbix Reports" submenu.

## Notes

- **Performance Considerations**: For large environments, consider limiting query result quantities appropriately.
- **Data Accuracy**: Displayed information is based on the current state of the Zabbix database.
- **Email Configuration**: Email push functionality depends on Zabbix's email configuration.

## Development

The plugin is developed based on the Zabbix module framework. File structure:

- `manifest.json`: Module configuration
- `Module.php`: Menu registration
- `actions/CustomReport.php`: Custom report business logic processing
- `actions/DailyReport.php`: Daily report business logic processing
- `views/reports.custom.php`: Custom report page view
- `views/reports.daily.php`: Daily report page view
- `lib/LanguageManager.php`: Internationalization language management
- `lib/ViewRenderer.php`: View rendering utilities
- `lib/ZabbixVersion.php`: Version compatibility tools

For extensions, refer to [Zabbix module documentation](https://www.zabbix.com/documentation/7.0/en/devel/modules).

## License

This project follows the Zabbix license. For details, see [Zabbix License](https://www.zabbix.com/license).

## Contributing

Issues and improvement suggestions are welcome.