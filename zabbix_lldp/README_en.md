# Zabbix LLDP Topology Module

[中文](README.md)

## ✨ Version Compatibility

This module is compatible with Zabbix 6.0 / 7.0+ / 8.0+.

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x
- ✅ Zabbix 8.0.x

**Compatibility note**: Built-in version detection adapts to different Zabbix API and class libraries automatically.

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

## Template Dependency

The module expects a Zabbix template named **Lldp Discovery** (configurable). The template should discover items with key `lldp.remote.info[{#LOCAL_PORT}]` whose `lastvalue` looks like:

```
REMOTE_DEVICE(REMOTE_PORT)
```

Example: `YJZHZX-LouCengHuiJu-S7510E(GigabitEthernet2/2/0/1)`

Link the template to hosts you want in the topology and ensure LLD items have recent data.

## Configuration

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

## Installation

### Deploy the module

```bash
# Zabbix 6.0 / 7.0
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/modules/

# Zabbix 7.4 / 8.0
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

### ⚠️ Adjust manifest.json for Zabbix 6.0

```bash
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_lldp/manifest.json
```

### Enable in Zabbix Web

1. Go to **Administration → General → Modules**.
2. Click **Scan directory**.
3. Enable **Zabbix LLDP Topology**.
4. Refresh the UI; **LLDP Topology** appears under **Monitoring**.

## Usage

1. Open **Monitoring → LLDP Topology**.
2. Select host group and host (defaults: all groups, all hosts).
3. Click **Query** to load the topology.
4. Hover over links or nodes for port and device details.

## Notes

- If the template is not found, verify `template_name` in `data/config.json`.
- Items with empty `lastvalue` or values not matching `DEVICE(PORT)` are skipped and counted in statistics.
- Remote device names come from LLDP and may differ from Zabbix host names; identical names are merged into one node.
- Not in v1: device icons, force-directed layout, bidirectional link merge, or `REMOTE_SYS_DESC` in tooltips.

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

## License

This module follows the Zabbix license terms. See: https://www.zabbix.com/license
