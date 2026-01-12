# Zabbix Rack Module

[中文](README.md)

## ✨ Version Compatibility

This module is compatible with Zabbix 6.0 and 7.0+ versions.

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x

**Compatibility Note**: The module includes intelligent version detection that automatically adapts to different Zabbix API versions and class libraries, requiring no manual configuration.

## Description

This is a frontend module for Zabbix that provides data center rack visualization and host placement management. The module adds rack management functionality under the Inventory section of Zabbix Web, supporting room and rack configuration, and visual host assignment.

## Features

- **Room Management**:
  - Create, edit, delete rooms
  - Room description information management
- **Rack Management**:
  - Create, edit, delete racks
  - Configure rack height (supports 1-60U)
  - Associate with rooms
- **Rack Visualization**:
  - 42U rack vertical layout display
  - Real-time U position occupancy status
  - Host information hover tips
  - Click on free U positions for assignment
- **Host Assignment**:
  - Assign Zabbix hosts to specific U positions in racks
  - Support filtering by host groups
  - Support hostname search
  - U position conflict detection
- **Search Functionality**:
  - Search by rack name
  - Search by host name
  - Quick host location positioning
- **Internationalization**: Support for Chinese and English interfaces
- **Responsive Design**: Adapts to different screen sizes

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
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_rack/manifest.json
```

### Enable Module

1. Go to **Administration → General → Modules**.
2. Click **Scan directory** to scan for new modules.
3. Find "Zabbix Rack" module and enable it.
4. Refresh the page, the module will appear under the **Inventory** menu as "Rack Management" submenu.

## Notes

- **Performance Considerations**: For large environments, consider limiting query result quantities appropriately.
- **Data Accuracy**: Displayed information is based on the current state of the Zabbix database.
- **Permission Requirements**: Users need appropriate permissions to access rack management functionality.

## Development

The plugin is developed based on the Zabbix module framework. File structure:

- `manifest.json`: Module configuration
- `Module.php`: Menu registration
- `actions/RackManage.php`: Rack management business logic processing
- `actions/RackView.php`: Rack view business logic processing
- `views/rack.manage.php`: Rack management page view
- `views/rack.view.php`: Rack view page view
- `lib/LanguageManager.php`: Internationalization language management
- `lib/ViewRenderer.php`: View rendering utilities
- `lib/ZabbixVersion.php`: Version compatibility tools

For extensions, refer to [Zabbix module documentation](https://www.zabbix.com/documentation/7.0/en/devel/modules).

## License

This project follows the Zabbix license. For details, see [Zabbix License](https://www.zabbix.com/license).

## Contributing

Issues and improvement suggestions are welcome.