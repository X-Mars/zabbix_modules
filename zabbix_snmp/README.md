# Zabbix SNMP 模块

[English](README_en.md)

## ✨ 版本兼容性

本模块兼容 Zabbix 6.0 / 7.0+ / 8.0+ 版本。

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x
- ✅ Zabbix 8.0.x

**兼容性说明**：模块内置智能版本检测机制，自动适配不同版本的 Zabbix API 和类库，无需手动配置。

## 描述

这是一个 Zabbix 前端模块，提供 SNMP 辅助助手功能。模块在 Zabbix Web 的 **Monitoring（监控）** 菜单下新增 **SNMP Assistant** 菜单，包含 **MIB 浏览** 和 **SNMP Walk** 两个子页面，帮助运维人员快速浏览 MIB 文件、执行 SNMP Walk、解析 OID，并一键创建监控项或批量生成 SNMP 模板。

![1](images/1.png)
![2](images/2.png)
![3](images/3.png)

## 功能特性

### MIB 浏览（Zabbix Mibs）

- **自动扫描 MIB 目录**：自动识别 Linux / Unix / Windows 常见 SNMP MIB 目录，并优先读取 `MIBDIRS` 环境变量中的目录
- **目录与文件下拉选择**：通过下拉框选择目录和 MIB 文件
- **对象表格展示**：解析 MIB 文件中的对象，展示 OID、解析 OID、语法/权限/状态、描述等信息
- **查看源码**：一键查看 MIB 文件原始内容
- **全屏查看**：对象列表支持全屏对话框查看
- **复制与测试**：复制 snmpget 命令、对选中主机执行测试

### SNMP Walk（Zabbix Walk）

- **主机分组 + 主机选择**：自动读取主机的 SNMP 接口和连接参数（v1 / v2c / v3，支持宏解析）
- **结果表格化**：将 snmpwalk 结果解析为表格，展示序号、OID、解析 OID、MIB 文件、模块、数据类型、值
- **查看原数据**：弹窗查看原始 snmpwalk 输出
- **复制命令 / 复制 OID**：一键复制 snmpget 命令或数字 OID
- **客户端分页**：大数据量结果分页渲染（每页 50/100/200），保证页面流畅
- **创建监控项**：选中单条结果，一键在主机上创建 SNMP 监控项（自动映射数据类型、key 去重）
- **批量创建模板**：勾选多条结果，填写模板名称和模板群组，一键创建 SNMP 模板并批量添加监控项

### 通用

- **国际化支持**：支持中英文界面
- **现代化界面**：清晰的表格布局与交互
- **权限控制**：创建监控项 / 模板需要 Zabbix 管理员权限

## 安装步骤

### 安装模块

```bash
# Zabbix 6.0 / 7.0 部署方法
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/modules/

# Zabbix 7.4 / 8.0 部署方法
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

### ⚠️ 修改 manifest.json 文件

```bash
# ⚠️ 如果使用Zabbix 6.0，修改manifest_version
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_snmp/manifest.json
```

### 启用模块

1. 转到 **Administration → General → Modules**。
2. 点击 **Scan directory** 按钮扫描新模块。
3. 找到 "Zabbix SNMP" 模块，点击启用模块。
4. 刷新页面，模块将在 **Monitoring** 菜单下显示为 "SNMP Assistant" 子菜单，包含 "Zabbix Mibs" 和 "Zabbix Walk" 两个子项。

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

## 注意事项

- **依赖命令**：SNMP Walk 功能依赖 Zabbix Web 服务器上已安装 `net-snmp` 工具（`snmpwalk` / `snmpget` / `snmptranslate`）。
- **目录权限**：如果 Zabbix Web 运行用户没有目录读取权限，页面中会显示目录但无法读取文件。
- **模板命名限制**：Zabbix 模板技术名称仅支持英文字母、数字、点号(.)、下划线(_)和连字符(-)，不支持中文；模板群组名称可使用中文。
- **性能考虑**：对于超大 Walk 结果，结果表格采用客户端分页渲染以保证页面响应速度。

## 开发

插件基于 Zabbix 模块框架开发。文件结构：

- `manifest.json`：模块配置
- `Module.php`：菜单注册
- `actions/Snmp.php`：MIB 浏览业务逻辑处理
- `actions/SnmpWalk.php`：SNMP Walk 业务逻辑处理
- `actions/SnmpSource.php`：MIB 源码读取
- `actions/SnmpItemCreate.php`：创建单个监控项
- `actions/SnmpTemplateCreate.php`：批量创建模板及监控项
- `views/snmp.php`：MIB 浏览页面视图
- `views/snmp.walk.php`：SNMP Walk 页面视图
- `lib/MibRepository.php`：MIB 解析、Walk 执行与 OID 处理
- `lib/LanguageManager.php`：国际化语言管理
- `lib/ViewRenderer.php`：视图渲染工具
- `lib/ZabbixVersion.php`：版本兼容工具

如需扩展，可参考[Zabbix模块开发文档](https://www.zabbix.com/documentation/7.0/en/devel/modules)。

## 许可证

本项目遵循Zabbix的许可证。详情请见[Zabbix许可证](https://www.zabbix.com/license)。
