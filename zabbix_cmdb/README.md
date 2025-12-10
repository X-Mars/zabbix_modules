# Zabbix CMDB Module

[简体中文](#描述) | [English](#english)

## ✨ 版本兼容性 / Version Compatibility

### 本模块同时兼容 Zabbix 6.0 和 Zabbix 7.0+ / Compatible with both Zabbix 6.0 and Zabbix 7.0+

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x

### 自动版本检测,无需手动配置 / Automatic version detection, no manual configuration needed

模块内置智能版本检测机制,自动适配不同版本的Zabbix API和类库,无需任何手动配置。

The module includes intelligent version detection that automatically adapts to different Zabbix API versions and class libraries, requiring no manual configuration.

## 描述

这是一个Zabbix前端模块，用于配置管理数据库（CMDB），提供主机信息的集中查看和管理功能。模块在Zabbix Web的资产记录菜单下新增CMDB菜单，支持主机搜索和分组筛选。

**兼容性说明**: 模块采用智能版本检测机制，可在Zabbix 6.0和7.0+环境中无缝运行。

## 项目截图

## 功能特性

- **主机搜索**：支持通过主机名或IP地址进行搜索
- **分组筛选**：支持按主机分组进行筛选
- **主机信息展示**：
  - 主机名（可点击跳转到主机详情）
  - IP地址
  - 接口方式（Agent、SNMP、IPMI、JMX）
  - CPU总量
  - 内存总量
  - 内核版本
  - 主机分组
  - 主机状态（活跃/禁用）
- **主机分组管理**：查看所有主机分组的统计信息
- **分组搜索**：支持按分组名称搜索
- **分组统计**：显示分组中的主机数量、CPU总量、内存总量
- **国际化支持**：支持中英文界面
- **响应式设计**：适配不同屏幕尺寸
- **现代化界面**：采用渐变色彩和动画效果的现代化设计
- **统计信息**：显示主机总数、分组总数和活跃主机数统计

## 安装步骤

![安装步骤](images/setting-1.png)

### ⚠️ 重要提示：根据Zabbix版本修改manifest.json

**在安装前，请根据您的Zabbix版本修改 `manifest.json` 文件：**

- **Zabbix 6.0**: 将 `"manifest_version": 2.0` 改为 `"manifest_version": 1.0`
- **Zabbix 7.0+**: 保持 `"manifest_version": 2.0` 不变

```bash
# 对于Zabbix 6.0用户
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_cmdb/manifest.json

# 对于Zabbix 7.0+用户
# 无需修改，默认即可
```

### 推荐方法：使用Git克隆安装（首选）

直接克隆项目到Zabbix的modules目录，这是最简单快捷的方式：

```bash
cd /usr/share/zabbix/modules/
git clone https://github.com/X-Mars/zabbix_modules.git .
```

```bash
# ⚠️ 如果使用Zabbix 6.0，修改manifest_version
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_cmdb/manifest.json
```

然后在Zabbix Web界面中启用模块：

1. 转到 **Administration → General → Modules**。
2. 点击 **Scan directory** 按钮扫描新模块。
3. 找到 "Zabbix CMDB" 模块，点击 "Disabled" 链接启用模块。
4. 刷新页面，模块将在 **Inventory** 菜单下显示为 "CMDB" 子菜单，包含 "Host List" 和 "Host Groups" 两个子项。

然后按照上述步骤启用模块。

## 使用方法

### 访问CMDB

1. 登录Zabbix Web界面。
2. 导航到 **Inventory → CMDB**。

### 主机列表页面

#### 搜索主机

- 在搜索框中输入主机名或IP地址关键词。
- 从下拉框中选择特定的主机分组进行筛选。
- 点击"搜索"按钮应用筛选条件。
- 点击"清除"按钮重置所有筛选条件。

#### 查看主机信息

- 表格中显示所有符合条件的主机信息。
- 点击主机名链接可跳转到该主机的详细页面。
- 接口方式用彩色标签显示，便于识别。

### 主机分组页面

#### 搜索分组

- 在搜索框中输入分组名称关键词。
- 点击"搜索"按钮应用筛选条件。
- 点击"清除"按钮重置搜索条件。

#### 查看分组信息

- 表格中显示所有符合条件的主机分组信息。
- 点击分组名称链接可跳转到该分组的编辑页面。
- 显示分组中的主机数量、CPU总量、内存总量。
- 分组状态用图标和文字显示，便于识别分组类型。

## 配置

### 权限要求

- 用户需要Zabbix用户或更高权限才能访问CMDB功能。

### 数据来源

- 主机信息来自Zabbix的host表。
- CPU和内存信息来自相关的监控项历史数据。
- 接口信息来自host_interface表。

## 注意事项

- **性能考虑**：对于大型环境，建议适当限制查询结果数量。
- **数据准确性**：显示的信息基于Zabbix数据库的当前状态。
- **兼容性**：仅在Zabbix 7.0上测试。
- **监控项依赖**：CPU和内存信息的显示依赖于相应的监控项配置。

## 开发

插件基于Zabbix模块框架开发。文件结构：

- `manifest.json`：模块配置
- `Module.php`：菜单注册
- `actions/Cmdb.php`：主机列表业务逻辑处理
- `actions/CmdbGroups.php`：主机分组业务逻辑处理
- `views/cmdb.php`：主机列表页面视图
- `views/cmdb_groups.php`：主机分组页面视图
- `lib/LanguageManager.php`：国际化语言管理
- `lib/ItemFinder.php`：监控项查找工具

如需扩展，可参考[Zabbix模块开发文档](https://www.zabbix.com/documentation/7.0/en/devel/modules)。

## 许可证

本项目遵循Zabbix的许可证。详情请见[Zabbix许可证](https://www.zabbix.com/license)。

## 贡献

欢迎提交问题和改进建议。

---

## English

## Description

This is a frontend module for Zabbix 7.0 that provides Configuration Management Database (CMDB) functionality, offering centralized viewing and management of host information. The module adds a CMDB menu under the Inventory section of Zabbix Web, supporting host search and group filtering.

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

## Installation

### ⚠️ Important: Modify manifest.json Based on Your Zabbix Version

**Before installation, please modify the `manifest.json` file according to your Zabbix version:**

- **Zabbix 6.0**: Change `"manifest_version": 2.0` to `"manifest_version": 1.0`
- **Zabbix 7.0+**: Keep `"manifest_version": 2.0` as default

```bash
# For Zabbix 6.0 users
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_cmdb/manifest.json

# For Zabbix 7.0+ users
# No modification needed, use default
```

### Recommended Method: Git Clone Installation (Preferred)

Clone the project directly to Zabbix modules directory - this is the simplest and fastest way:

```bash
cd /usr/share/zabbix/modules/
git clone https://github.com/X-Mars/zabbix_modules.git .
```

```bash
# ⚠️ For Zabbix 6.0, modify manifest_version
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_cmdb/manifest.json
```

Then enable the module in Zabbix Web UI:

1. Go to **Administration → General → Modules**.
2. Click **Scan directory**.
3. Find "Zabbix CMDB" and enable it.
4. The module will appear under the Inventory menu as "CMDB" submenu with "Host List" and "Host Groups" subitems.

## Usage

### Access CMDB

1. Log in to Zabbix Web UI.
2. Navigate to Inventory → CMDB.

### Host List Page

#### Search Hosts

- Enter hostname or IP address keywords in the search box.
- Select a specific host group from the dropdown to filter.
- Click "Search" to apply filters.
- Click "Clear" to reset all filters.

#### View Host Information

- The table displays all hosts matching the criteria.
- Click on hostname links to jump to the host's detail page.
- Interface types are displayed with colored labels for easy identification.

### Host Groups Page

#### Search Groups

- Enter group name keywords in the search box.
- Click "Search" to apply filters.
- Click "Clear" to reset search conditions.

#### View Group Information

- The table displays all host groups matching the criteria.
- Click on group name links to jump to the group's edit page.
- Display host count, CPU total, memory total per group.
- Group status is displayed with icons and text for easy identification.

## Configuration

### Permission Requirements

- Users need Zabbix user or higher permissions to access CMDB functionality.

### Data Sources

- Host information comes from Zabbix's host table.
- CPU and memory information comes from related item history data.
- Interface information comes from the host_interface table.

## Notes

- **Performance Considerations**: For large environments, consider limiting query result quantities appropriately.
- **Data Accuracy**: Displayed information is based on the current state of the Zabbix database.
- **Compatibility**: Tested only on Zabbix 7.0.
- **Item Dependencies**: Display of CPU and memory information depends on corresponding item configuration.

## Development

Structure:

- `manifest.json`: module config
- `Module.php`: menu registration
- `actions/Cmdb.php`: host list business logic processing
- `actions/CmdbGroups.php`: host groups business logic processing
- `views/cmdb.php`: host list page view
- `views/cmdb_groups.php`: host groups page view
- `lib/LanguageManager.php`: internationalization language management
- `lib/ItemFinder.php`: item finder utilities

See also: [Zabbix module documentation](https://www.zabbix.com/documentation/7.0/en/devel/modules)

## License

Follows Zabbix license: [https://www.zabbix.com/license](https://www.zabbix.com/license)

## Contributing

Issues and PRs are welcome.
