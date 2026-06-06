# Zabbix SNMP

## Overview

`zabbix_snmp` is a Zabbix frontend module template that reads MIB files directly from common operating system SNMP MIB directories and displays both file lists and file details in the UI.

## Features

- Automatically scans common Linux / Unix / Windows MIB directories
- Prioritizes directories from the `MIBDIRS` environment variable
- Shows directories and MIB files in the left panel
- Lets users click any MIB file to view path, size, modified time, total lines, and content preview
- Supports filename search
- Supports Chinese and English UI

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

## Usage

After enabling the module, open:

- `Monitoring -> SNMP MIB Browser`

The page automatically lists detected directories. Click any MIB file to view its details.

## Notes

- The module reads files only and does not modify any system MIB files
- The details panel shows the first 400 lines by default to keep the page responsive for large files
- If the Zabbix web user cannot read a directory, the page can still list the directory entry but cannot load file content