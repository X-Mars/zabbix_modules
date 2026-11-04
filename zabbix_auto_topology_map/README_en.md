# Zabbix Auto Topology Map Module

[中文](README.md)

## ✨ Version Compatibility

This module is developed for Zabbix 7.0 and uses `manifest_version: 2.0`.

- ✅ Zabbix 7.0.28: verified against the source code and tested in a live environment
- ⚠️ Zabbix 6.x: includes partial compatibility handling, but has not been tested
- ⚠️ Other Zabbix 7.x releases and 8.x: require verification in the target environment

**Compatibility notes**: The module includes compatibility handling for older and newer module base classes and menu components. This does not mean every version has been tested. Before upgrading, verify menu registration, map reading and writing, and automatic discovery in a test environment.

## Description

This Zabbix frontend module automatically discovers connections between network devices and provides a central interface for viewing and editing topology maps. It adds **Auto Topology Map** under **Monitoring** in the Zabbix web interface and reads LLDP, CDP, and EDP neighbor information over SNMP to generate network topology maps.

The module reads and writes maps through native Zabbix APIs and shares the same data with **Monitoring → Maps**. The canvas displays host status, problem information, and link triggers. Bandwidth, inbound traffic, and outbound traffic can be displayed by manually associating monitoring items with links.

## Features

- **Map management**: Create, search by name, sort, rename, bulk delete, and export maps as JSON.
- **Automatic discovery**:
  - Read LLDP / CDP / EDP neighbors starting from hosts with SNMP interfaces.
  - Configure discovery depth from 1 to 10 levels, with a default of 3.
  - Optionally include unmanaged devices and automatically select icons by device type.
  - Generate maps in a tree layout, with support for multiple port connections between the same pair of devices.
- **Rediscovery**: Update devices and links in the current map, reusing existing elements and links where possible to preserve link metrics and routing settings. Rearrange the entire layout or retain existing coordinates.
- **Canvas editing**: Add, drag, and remove map elements, shapes, and links. Edit image and host elements, as well as rectangles, ellipses, and lines.
- **Link metrics**: Select multiple numeric items separately for bandwidth, inbound traffic, and outbound traffic. Their latest values are summed and converted to appropriate units.
- **Link status**: Automatically match port status triggers or select them manually. When a trigger reports a problem, the corresponding member link changes color.
- **Link routing**: Supports automatic curves, automatic orthogonal routing with obstacle avoidance, and manual waypoints. Aggregated links share labels and routing settings.
- **Host status**: Displays normal, problem, maintenance, and disabled states, with problem highlighting and status icons.
- **Automatic refresh**: Refreshes status and metrics every 60 seconds in preview mode. Refreshing pauses while editing or when the page is hidden.
- **Native host menu**: Click a host in preview mode to open the native Zabbix host menu and access problems, latest data, graphs, and other pages.
- **View persistence**: Remembers the user's last list or map view and supports zooming, panning, and fitting the map to the window.
- **Localization and themes**: Displays Chinese or English according to the Zabbix user's language and supports light and dark themes.

## Installation

### Install the Module

Deploy this repository to the module directory for your Zabbix frontend installation. Common paths are shown below:

```bash
# Common deployment path for Zabbix 6.0 / 7.0
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/modules/

# Common deployment path for Zabbix 7.2+ / 7.4 / 8.0 (compatibility requires separate verification)
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

If the repository is already deployed, update the existing files. Alternatively, copy only the `zabbix_auto_topology_map` directory into your actual module directory. Ensure that `manifest.json` is directly inside that directory, without an extra nested directory. The web server user must have read access to the module files.

### ⚠️ Modify manifest.json

If you attempt to deploy the module on Zabbix 6.0, change its `manifest_version` to `1.0`:

```bash
# Run from the repository root; this change does not imply verified Zabbix 6.0 compatibility
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_auto_topology_map/manifest.json
```

### Configure the Automatic Discovery Environment

- Install and enable the `snmp` extension in **the PHP environment used by the Zabbix web frontend**. The module does not require the `snmpwalk` command-line tool.
- Ensure that the frontend server can reach the target devices over SNMP and that the relevant LLDP / CDP / EDP protocols are enabled on those devices.
- Configure SNMP interfaces and authentication details on Zabbix hosts. The discovery host selector only allows hosts with SNMP interfaces.
- The module creates the `topomap_link_metrics`, `topomap_link_paths`, and `topomap_settings` tables on demand. The database account used by the frontend needs the corresponding table creation, read, and write permissions.

Install the PHP SNMP extension using the command for your operating system:

```bash
# Debian / Ubuntu
sudo apt update
sudo apt install php-snmp

# Systems using dnf (such as Rocky Linux / AlmaLinux / Fedora)
sudo dnf install php-snmp

# Systems using yum (such as older CentOS / RHEL releases)
sudo yum install php-snmp
```

### Enable the Module

1. Go to **Administration → General → Modules**.
2. Click **Scan directory** to scan for new modules.
3. Find **Zabbix Auto Topology Map** and enable it.
4. Refresh the page and open **Monitoring → Auto Topology Map**.

You can also access the module directly at `zabbix.php?action=topomap.view`.

## Notes

- **Permissions**: Map and host visibility follows Zabbix permissions. Creating, editing, and deleting maps also requires the **Create and edit maps** role permission (`actions.edit_maps`).
- **Shared data**: The module shares data with native Zabbix maps. Editing or deleting a map through either interface affects the other. Export a backup before making changes. The module provides JSON export but does not provide a corresponding import interface.
- **Metric configuration**: Bandwidth and traffic items must be selected manually; they are not matched automatically. By default, the selector is limited to the hosts at both ends of the link and only displays numeric items. A missing latest value is displayed as `-`.
- **Trigger matching**: The discovery dialog allows you to edit port status keywords, one per line. The default Chinese keyword is “处于关闭状态”; the English default is `link down`. Adjust these keywords to match your actual trigger names.
- **Rediscovery**: Devices and links that are not rediscovered are retained by default. You can choose to remove automatic links that are no longer discovered; manual links are always retained. Review the layout and retention options before updating.
- **Discovery performance**: For large networks, start with a small number of seed hosts and a shallow discovery depth. SNMP timeouts, access restrictions, or disabled neighbor discovery protocols can cause devices to be skipped or produce incomplete results.
- **Canvas dimensions**: Dimensions are calculated automatically from the content, and the canvas can expand in all four directions. Manual width and height entry is not available.
- **Native element limitations**: Host group, trigger, and nested map elements can be previewed, dragged, and deleted, but their properties must be edited in the native editor. Status calculations currently apply only to host elements.
- **Icon and macro limitations**: The module preview does not resolve icon mappings or expand custom macro labels. Use the native Zabbix map page when full native rendering is required.
- **Upgrades and backups**: Back up native map data and the module's custom tables to preserve link metrics, routing, and settings. After upgrading, use `Ctrl+F5` to refresh the browser cache. If the menu does not update, check the PHP OPcache cache.

## Development

The plugin is built on the Zabbix module framework. File structure:

- `manifest.json`: Module configuration, routes, and static asset declarations
- `Module.php`: Registers the Monitoring → Auto Topology Map menu entry
- `actions/TopomapView.php`: Page controller and initial view state
- `actions/TopomapData.php`: Data endpoints for map management, saving discovery results, metrics, and routing settings
- `views/topomap.view.php`: Page view and dialog structure
- `services/LldpDiscovery.php`: SNMP neighbor discovery, port parsing, and device matching
- `lib/LanguageManager.php`: Chinese and English localization
- `assets/js/topomap.js`: List, canvas, editing interactions, automatic discovery, and status refresh
- `assets/css/topomap.css`: Page styles and theme support

Map content is saved through the native Zabbix `map` API. Link metrics, routing, and module settings are stored in custom module tables. When extending the save logic, account for element and link ID reuse and cleanup of associated configuration.

For more information, see the [Zabbix module development documentation](https://www.zabbix.com/documentation/7.0/en/devel/modules).

## License

This project follows the Zabbix license. See the [Zabbix license](https://www.zabbix.com/license) for details.
