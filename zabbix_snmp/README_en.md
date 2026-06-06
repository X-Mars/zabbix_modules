# Zabbix SNMP Module

[中文](README.md)

## ✨ Version Compatibility

This module is compatible with Zabbix 6.0 / 7.0+ / 8.0+.

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x
- ✅ Zabbix 8.0.x

**Compatibility Note**: The module includes intelligent version detection that automatically adapts to different Zabbix API versions and class libraries, requiring no manual configuration.

## Description

This is a Zabbix frontend module that provides an SNMP assistant. It adds an **SNMP Assistant** menu under the **Data collection** section of Zabbix Web, containing two sub-pages: **MIB Browser** and **SNMP Walk**. It helps operators browse MIB files, run SNMP walks, resolve OIDs, and create monitoring items or generate SNMP templates with a single click.

![1](images/1.png)
![2](images/2.png)
![3](images/3.png)

## Features

### MIB Browser (Zabbix Mibs)

- **Automatic MIB directory scan**: Detects common Linux / Unix / Windows SNMP MIB directories and prioritizes directories from the `MIBDIRS` environment variable
- **Directory and file dropdowns**: Select a directory and MIB file via dropdowns
- **Object table view**: Parses objects from a MIB file and shows OID, resolved OID, syntax/access/status, description, etc.
- **View source**: View the raw content of a MIB file with one click
- **Fullscreen view**: The object list supports a fullscreen dialog
- **Copy and test**: Copy the snmpget command or run a test against the selected host

### SNMP Walk (Zabbix Walk)

- **Host group + host selection**: Automatically reads the host's SNMP interface and connection parameters (v1 / v2c / v3, with macro resolution)
- **Tabular results**: Parses snmpwalk output into a table showing index, OID, resolved OID, MIB file, module, data type, and value
- **View raw data**: View the original snmpwalk output in a popup
- **Copy command / copy OID**: Copy the snmpget command or numeric OID with one click
- **Client-side pagination**: Renders large result sets page by page (50/100/200 per page) to keep the page responsive
- **Create item**: Select a single result to create an SNMP item on the host (automatic value-type mapping and key de-duplication)
- **Create template in bulk**: Check multiple results, enter a template name and template group, and create an SNMP template with the items added in one click

### General

- **Internationalization**: Supports Chinese and English interfaces
- **Modern interface**: Clean table layout and interactions
- **Permission control**: Creating items / templates requires Zabbix admin privileges

## Installation

### Install Module

```bash
# Zabbix 6.0 / 7.0 deployment
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/modules/

# Zabbix 7.4 / 8.0 deployment
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

### ⚠️ Modify manifest.json

```bash
# ⚠️ For Zabbix 6.0, modify manifest_version
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_snmp/manifest.json
```

### Enable Module

1. Go to **Administration → General → Modules**.
2. Click **Scan directory** to scan for new modules.
3. Find the "Zabbix SNMP" module and enable it.
4. Refresh the page. The module will appear under the **Data collection** menu as "SNMP Assistant" submenu, containing "Zabbix Mibs" and "Zabbix Walk" subitems.

## Default directories

- `/usr/share/snmp/mibs`
- `/usr/local/share/snmp/mibs`
- `/usr/share/mibs`
- `/usr/local/share/mibs`
- `/var/lib/mibs`
- `/usr/share/net-snmp/mibs`
- `/opt/share/snmp/mibs`
- `/opt/local/share/snmp/mibs`
- `C:\usr\share\snmp\mibs`
- `C:\usr\local\share\snmp\mibs`
- `C:\net-snmp\share\snmp\mibs`
- `C:\Program Files\Net-SNMP\share\snmp\mibs`
- `C:\Program Files (x86)\Net-SNMP\share\snmp\mibs`

## Notes

- **Required commands**: The SNMP Walk feature relies on the `net-snmp` tools (`snmpwalk` / `snmpget` / `snmptranslate`) being installed on the Zabbix web server.
- **Directory permissions**: If the Zabbix web user cannot read a directory, the page can still list the directory entry but cannot load file content.
- **Template naming limitation**: Zabbix template technical names only allow English letters, numbers, dots (.), underscores (_) and hyphens (-); Chinese is not supported. Template group names may use Chinese.
- **Performance**: For very large walk results, the result table uses client-side pagination to keep the page responsive.

## Development

The module is built on the Zabbix module framework. File structure:

- `manifest.json`: Module configuration
- `Module.php`: Menu registration
- `actions/Snmp.php`: MIB browser business logic
- `actions/SnmpWalk.php`: SNMP Walk business logic
- `actions/SnmpSource.php`: MIB source reading
- `actions/SnmpItemCreate.php`: Create a single item
- `actions/SnmpTemplateCreate.php`: Create a template with items in bulk
- `views/snmp.php`: MIB browser page view
- `views/snmp.walk.php`: SNMP Walk page view
- `lib/MibRepository.php`: MIB parsing, walk execution and OID handling
- `lib/LanguageManager.php`: Internationalization language management
- `lib/ViewRenderer.php`: View rendering utilities
- `lib/ZabbixVersion.php`: Version compatibility utilities

For extensions, refer to [Zabbix module documentation](https://www.zabbix.com/documentation/7.0/en/devel/modules).

## License

This project follows the Zabbix license. For details, see [Zabbix License](https://www.zabbix.com/license).
