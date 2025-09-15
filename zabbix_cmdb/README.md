# Zabbix CMDB Module

[简体中文](#描述) | [English](#english)

## 描述

这是一个Zabbix 7.0的前端模块，用于配置管理数据库（CMDB），提供主机信息的集中查看和管理功能。模块在Zabbix Web的数据采集菜单下新增CMDB菜单，支持主机搜索和分组筛选。

## 功能特性

- **主机搜索**：支持通过主机名或IP地址进行搜索
- **分组筛选**：支持按主机分组进行筛选
- **主机信息展示**：
  - 主机名（可点击跳转到主机详情）
  - IP地址
  - 接口方式（Agent、SNMP、IPMI、JMX）
  - CPU总量
  - 内存总量
  - 主机分组
  - 主机状态
- **国际化支持**：支持中英文界面
- **响应式设计**：适配不同屏幕尺寸

## 安装步骤

1. 下载或复制`zabbix_cmdb`目录到Zabbix前端的`ui/modules/`目录下。

   ```bash
   cp -r zabbix_cmdb /usr/share/zabbix/modules/
   ```

2. 在Zabbix Web界面中，转到 **Administration → General → Modules**。

3. 点击 **Scan directory** 按钮扫描新模块。

4. 找到 "Zabbix CMDB" 模块，点击 "Disabled" 链接启用模块。

5. 刷新页面，模块将在 **Data collection** 菜单下显示为 "CMDB" 子菜单。

## 使用方法

### 访问CMDB

1. 登录Zabbix Web界面。
2. 导航到 **Data collection → CMDB**。

### 搜索主机

- 在搜索框中输入主机名或IP地址关键词。
- 从下拉框中选择特定的主机分组进行筛选。
- 点击"搜索"按钮应用筛选条件。
- 点击"清除"按钮重置所有筛选条件。

### 查看主机信息

- 表格中显示所有符合条件的主机信息。
- 点击主机名链接可跳转到该主机的详细页面。
- 接口方式用彩色标签显示，便于识别。
- 主机状态用不同颜色显示（绿色=启用，红色=禁用）。

## 配置

### 权限要求

- 用户需要Zabbix用户或更高权限才能访问CMDB功能。

### 数据来源

- 主机信息来自Zabbix的host表。
- CPU和内存信息来自相关的监控项最新值。
- 接口信息来自host_interface表。

## 注意事项

- **性能考虑**：默认限制查询结果为100条，适用于大型环境。
- **数据准确性**：显示的信息基于Zabbix数据库的当前状态。
- **兼容性**：仅在Zabbix 7.0上测试。
- **监控项依赖**：CPU和内存信息的显示依赖于相应的监控项配置。

## 开发

插件基于Zabbix模块框架开发。文件结构：

- `manifest.json`：模块配置
- `Module.php`：菜单注册
- `actions/Cmdb.php`：业务逻辑处理
- `views/cmdb.php`：页面视图
- `lib/LanguageManager.php`：国际化语言管理

如需扩展，可参考[Zabbix模块开发文档](https://www.zabbix.com/documentation/7.0/en/devel/modules)。

## 许可证

本项目遵循Zabbix的许可证。详情请见[Zabbix许可证](https://www.zabbix.com/license)。

## 贡献

欢迎提交问题和改进建议。

---

## English

## Description

This is a frontend module for Zabbix 7.0 that provides Configuration Management Database (CMDB) functionality, offering centralized viewing and management of host information. The module adds a CMDB menu under the Data collection section of Zabbix Web, supporting host search and group filtering.

## Features

- **Host Search**: Support searching by hostname or IP address
- **Group Filtering**: Support filtering by host groups
- **Host Information Display**:
  - Host name (clickable link to host details)
  - IP address
  - Interface type (Agent, SNMP, IPMI, JMX)
  - CPU total
  - Memory total
  - Host groups
  - Host status
- **Internationalization**: Support for Chinese and English interfaces
- **Responsive Design**: Adapts to different screen sizes

## Installation

1. Copy the `zabbix_cmdb` directory to Zabbix frontend modules directory.

   ```bash
   cp -r zabbix_cmdb /usr/share/zabbix/modules/
   ```

2. In Zabbix Web UI, go to Administration → General → Modules.
3. Click Scan directory.
4. Find "Zabbix CMDB" and enable it.
5. The module will appear under the Data collection menu as "CMDB".

## Usage

### Access CMDB

1. Log in to Zabbix Web UI.
2. Navigate to Data collection → CMDB.

### Search Hosts

- Enter hostname or IP address keywords in the search box.
- Select a specific host group from the dropdown to filter.
- Click "Search" to apply filters.
- Click "Clear" to reset all filters.

### View Host Information

- The table displays all hosts matching the criteria.
- Click on hostname links to jump to the host's detail page.
- Interface types are displayed with colored labels for easy identification.
- Host status is displayed in different colors (green=enabled, red=disabled).

## Configuration

### Permission Requirements

- Users need Zabbix user or higher permissions to access CMDB functionality.

### Data Sources

- Host information comes from Zabbix's host table.
- CPU and memory information comes from related item latest values.
- Interface information comes from the host_interface table.

## Notes

- **Performance Considerations**: Default limit of 100 query results, suitable for large environments.
- **Data Accuracy**: Displayed information is based on the current state of the Zabbix database.
- **Compatibility**: Tested only on Zabbix 7.0.
- **Item Dependencies**: Display of CPU and memory information depends on corresponding item configuration.

## Development

Structure:

- `manifest.json`: module config
- `Module.php`: menu registration
- `actions/Cmdb.php`: business logic processing
- `views/cmdb.php`: page view
- `lib/LanguageManager.php`: internationalization language management

See also: [Zabbix module documentation](https://www.zabbix.com/documentation/7.0/en/devel/modules)

## License

Follows Zabbix license: [https://www.zabbix.com/license](https://www.zabbix.com/license)

## Contributing

Issues and PRs are welcome.
