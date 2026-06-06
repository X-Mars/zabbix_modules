# Zabbix SNMP

## 简介

`zabbix_snmp` 是一个 Zabbix 前端模块模版，用于直接读取操作系统常见 SNMP MIB 目录中的 MIB 文件，并在页面中浏览文件列表和详情。

## 功能

- 自动扫描常见 Linux / Unix / Windows MIB 目录
- 优先识别 `MIBDIRS` 环境变量中的目录
- 页面左侧展示目录和 MIB 文件列表
- 点击任意 MIB 文件即可查看路径、大小、修改时间、总行数和内容预览
- 支持按文件名搜索
- 支持中英文界面国际化

## 默认扫描目录

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

## 使用方式

启用模块后，进入：

- `Monitoring -> SNMP MIB Browser`

页面会自动展示检测到的目录。点击任意 MIB 文件即可查看详情。

## 说明

- 模块只读取文件，不修改系统中的 MIB 文件
- 详情页默认展示文件前 400 行，避免超大文件导致页面过重
- 如果 Zabbix Web 运行用户没有目录读取权限，页面中会显示目录但无法读取文件