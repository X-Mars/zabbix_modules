# Zabbix LLDP Topology Module

[中文](README.md)

## ✨ Version Compatibility

| Zabbix | PHP | manifest_version | Result |
|---|---|---|---|
| 6.0.49 | 7.3.33 | 1.0 | Recognized, enabled; pages pass |
| 7.0.32 | 8.0.30 | 2.0 | Recognized, enabled; pages pass |
| 8.0.0 | 8.4.27 | 2.0 | Recognized, enabled; pages pass |

## Description

A Zabbix frontend module that visualizes LLDP neighbor topology. It reads hosts linked to the **Lldp Discovery** template and the latest values of `lldp.remote.info[*]` items, then renders local and remote devices as SVG circles connected by lines. Hover to see port details.

The module adds **LLDP Topology** under the **Monitoring** menu in Zabbix Web.

## Features

- **Template filtering**: Only hosts linked to the LLDP template and their host groups appear in dropdowns
- **Group / host filters**: All groups, all hosts, or a specific scope
- **On-demand query**: Topology is not loaded on first page visit; click **Query** to fetch data
- **Topology view**: Local devices on the left, remote peers on the right, circles and lines
- **Hover details**: Local/remote device names, ports, and last update time
- **Statistics**: Host, link, and device counts plus skipped item count
- **Extensible**: Node JSON includes a reserved `image` field for future device icons
- **Bilingual UI**: Chinese and English

## Installation

### Install Module

```bash
# Zabbix 6.0 / 7.0
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/modules/

# Zabbix 7.4 / 8.0
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

### ⚠️ Modify manifest.json

```bash
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_lldp/manifest.json
```

### Enable Module

1. Go to **Administration → General → Modules**.
2. Click **Scan directory**.
3. Enable **Zabbix LLDP Topology**.
4. Refresh the UI; **LLDP Topology** appears under **Monitoring**.

## Notes

- If the template is not found, verify `template_name` in `data/config.json`.
- Items with empty `lastvalue` or values not matching `DEVICE(PORT)` are skipped and counted in statistics.
- Remote device names come from LLDP and may differ from Zabbix host names; identical names are merged into one node.
- Not in v1: device icons, force-directed layout, bidirectional link merge, or `REMOTE_SYS_DESC` in tooltips.

### Directory ownership and save errors

Module pages show an ownership command only when the directory owner or group differs from the PHP runtime account. Correct ownership produces no reminder. Save errors retain their original message and add directory ownership and SELinux (`getenforce`) reminders. Text follows the Zabbix Web user language. No extra dialogs or command panels are added.

## Development

```
zabbix_lldp/
├── manifest.json
├── Module.php
├── data/config.json
├── actions/
│   ├── Lldp.php
│   └── LldpData.php
├── views/
│   └── lldp.php
└── lib/
    ├── LanguageManager.php
    ├── ViewRenderer.php
    ├── ZabbixVersion.php
    ├── ConfigManager.php
    ├── TemplateHostResolver.php
    └── LldpTopologyBuilder.php
```

## Module Configuration

### Template Dependency

The module expects a Zabbix template named **Lldp Discovery** (configurable). The template should discover items with key `lldp.remote.info[{#LOCAL_PORT}]` whose `lastvalue` looks like:

```
REMOTE_DEVICE(REMOTE_PORT)
```

Example: `YJZHZX-LouCengHuiJu-S7510E(GigabitEthernet2/2/0/1)`

Link the template to hosts you want in the topology and ensure LLD items have recent data.

### Configuration

Settings are stored in `data/config.json`:

```json
{
    "template_name": "Lldp Discovery",
    "item_key_prefix": "lldp.remote.info",
    "discovery_key": "lldp_discovery"
}
```

| Field | Description |
|-------|-------------|
| `template_name` | Visible template name (technical name is also tried) |
| `item_key_prefix` | Item key prefix used to fetch LLDP neighbor items |
| `root_hostid` | Optional Zabbix host ID to use as the root when present in the graph. |
| `discovery_key` | LLD rule key, reserved for future use |

### Usage

1. Open **Monitoring → LLDP Topology**.
2. Select host group and host (defaults: all groups, all hosts).
3. Click **Query** to load the topology.
4. Hover over links or nodes for port and device details.

## License

This module follows the Zabbix license terms. See: https://www.zabbix.com/license
