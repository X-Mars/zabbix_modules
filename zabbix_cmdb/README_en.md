# Zabbix CMDB Module

[中文](README.md)

## ✨ Version Compatibility

This module is compatible with Zabbix 6.0 and 7.0+ versions.

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x

**Compatibility Note**: The module includes intelligent version detection that automatically adapts to different Zabbix API versions and class libraries, requiring no manual configuration.

## Description

This is a frontend module for Zabbix that provides Configuration Management Database (CMDB) functionality, offering centralized viewing and management of host information. The module adds a CMDB menu under the Inventory section of Zabbix Web, supporting host search and group filtering.

![1](images/1.jpg)
![2](images/2.jpg)

## Features

- **Host Search**: Support searching by hostname or IP address
- **Group Filtering**: Support filtering by host groups
- **Host Information Display**:
  - Host name (clickable link to host details)
  - IP address
  - Interface type (Agent, SNMP, IPMI, JMX)
  - CPU total
  - Memory total
  - Kernel version
  - Host groups
  - Host status (Active/Disabled)
- **Host Group Management**: View statistics for all host groups
- **Group Search**: Support searching by group name
- **Group Statistics**: Display host count, CPU total, memory total per group
- **Internationalization**: Support for Chinese and English interfaces
- **Responsive Design**: Adapts to different screen sizes
- **Modern Interface**: Modern design with gradient colors and animation effects
- **Statistics**: Display statistics for total hosts, total groups, and active hosts

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
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_cmdb/manifest.json
```

### Enable Module

1. Go to **Administration → General → Modules**.
2. Click **Scan directory** to scan for new modules.
3. Find "Zabbix CMDB" module and enable it.
4. Refresh the page, the module will appear under the **Inventory** menu as "CMDB" submenu, containing "Host List" and "Host Groups" subitems.

## Notes

- **Performance Considerations**: For large environments, consider limiting query result quantities appropriately.
- **Data Accuracy**: Displayed information is based on the current state of the Zabbix database.
- **Item Dependencies**: Display of CPU and memory information depends on corresponding item configuration.

## Development

The plugin is developed based on the Zabbix module framework. File structure:

- `manifest.json`: Module configuration
- `Module.php`: Menu registration
- `actions/Cmdb.php`: Host list business logic processing
- `actions/CmdbGroups.php`: Host groups business logic processing
- `views/cmdb.php`: Host list page view
- `views/cmdb_groups.php`: Host groups page view
- `lib/LanguageManager.php`: Internationalization language management
- `lib/ItemFinder.php`: Item finder utilities

For extensions, refer to [Zabbix module documentation](https://www.zabbix.com/documentation/7.0/en/devel/modules).

## License

This project follows the Zabbix license. For details, see [Zabbix License](https://www.zabbix.com/license).

## Contributing

Issues and improvement suggestions are welcome.