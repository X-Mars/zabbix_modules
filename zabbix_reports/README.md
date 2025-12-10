# Zabbix Reports Module

[简体中文](#描述) | [English](#english)

## ✨ 版本兼容性 / Version Compatibility

### 本模块同时兼容 Zabbix 6.0 和 Zabbix 7.0+ / Compatible with both Zabbix 6.0 and Zabbix 7.0+

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x

### 自动版本检测,无需手动配置 / Automatic version detection, no manual configuration needed

模块内置智能版本检测机制,自动适配不同版本的Zabbix API和类库,无需任何手动配置。

The module includes intelligent version detection that automatically adapts to different Zabbix API versions and class libraries, requiring no manual configuration.

**详细兼容性说明**: 请参阅 [COMPATIBILITY.md](COMPATIBILITY.md) / For detailed compatibility information, see [COMPATIBILITY.md](COMPATIBILITY.md)

## 描述

这是一个Zabbix前端模块，用于生成每日、周、月报表。插件可以预览报表、导出PDF以及通过邮件推送报表。报表包含报警数量、报警状态、报警最多的主机，以及CPU和内存使用率最高的主机。

**兼容性说明**: 模块采用智能版本检测机制，可在Zabbix 6.0和7.0+环境中无缝运行。

## 项目截图

![项目截图](images/1.png)

## 功能特性

- **报表类型**：支持每日、周、月报表，以及自定义时间范围报表
- **报表内容**：
  - 报警数量和状态统计
  - 报警最多的主机（前10名）
  - CPU使用率最高的主机（前10名）
  - 内存使用率最高的主机（前10名）
- **功能**：
  - 页面预览报表
  - 手动导出PDF
  - 邮件推送报表（HTML格式）
  - 自定义日期范围选择
- **国际化支持**：支持中英文界面
- **响应式设计**：适配不同屏幕尺寸
- **现代化界面**：采用渐变色彩和动画效果的现代化设计
- **兼容性**：支持Linux Agent和Windows Agent模板
- **统计信息**：显示报警总数、活跃问题数等统计数据

## 安装步骤

![安装步骤](images/setting-1.png)

### ⚠️ 重要提示：根据Zabbix版本修改manifest.json

**在安装前，请根据您的Zabbix版本修改 `manifest.json` 文件：**

- **Zabbix 6.0**: 将 `"manifest_version": 2.0` 改为 `"manifest_version": 1.0`
- **Zabbix 7.0+**: 保持 `"manifest_version": 2.0` 不变

```bash
# 对于Zabbix 6.0用户
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_reports/manifest.json

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
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_reports/manifest.json
```

然后在Zabbix Web界面中启用模块：

1. 转到 **Administration → General → Modules**。
2. 点击 **Scan directory** 按钮扫描新模块。
3. 找到 "Zabbix Reports" 模块，点击 "Disabled" 链接启用模块。
4. 刷新页面，模块将在 **Reports** 菜单下显示为 "Zabbix Reports" 子菜单，包含 "Daily Report"、"Weekly Report"、"Monthly Report" 和 "Custom Report" 四个子项。

## 使用方法

### 访问报表

1. 登录Zabbix Web界面。
2. 导航到 **Reports → Zabbix Reports**。
3. 选择 **Daily Report**、**Weekly Report**、**Monthly Report** 或 **Custom Report**。

### 每日/周/月报表

#### 预览报表

- 页面将自动显示对应时间范围的报表数据。
- 包括报警统计图表和主机排行列表。
- 支持实时数据刷新。

#### 导出PDF

- 在报表页面，点击 **Export PDF** 按钮。
- 浏览器将自动下载生成的PDF文件。
- PDF文件包含完整的报表内容和图表。

#### 发送邮件

- 在报表页面，点击 **Send Email** 按钮。
- 报表将以HTML邮件形式发送到配置的收件人。
- 邮件包含所有统计信息和图表。

### 自定义报表

#### 选择时间范围

- 在日期选择器中选择开始日期和结束日期。
- 点击"生成报表"按钮。
- 系统将生成指定时间范围内的统计报表。

#### 查看报表内容

- 报警数量统计和趋势图表。
- 报警最多的主机排行（Top 10）。
- CPU使用率最高的主机排行（Top 10）。
- 内存使用率最高的主机排行（Top 10）。

#### 导出和分享

- 支持导出PDF格式。
- 支持通过邮件发送报表。
- PDF文件名包含选定的日期范围。

## 配置

### 邮件配置（开发中）

在发送邮件的动作文件中（`actions/DailyReportSend.php` 等），修改以下变量：

```php
$to = 'admin@example.com'; // 收件人邮箱
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= 'From: zabbix@example.com' . "\r\n"; // 发件人邮箱
```

确保Zabbix服务器的PHP环境配置了邮件发送功能（例如，通过sendmail或SMTP）。

### 监控项

插件使用以下监控项名称：

- **CPU utilization**：CPU使用率百分比
- **Memory utilization**：内存使用率百分比

确保主机应用了相应的模板，并且监控项名称匹配。

## 注意事项

- **权限要求**：用户需要Zabbix用户或更高权限才能访问报表功能。
- **数据准确性**：报表基于历史数据，确保Zabbix历史数据保留足够长的时间。显示的信息基于Zabbix数据库的当前状态。
- **性能考虑**：对于大型环境，建议适当限制查询时间范围。生成报表可能需要一定时间。
- **邮件配置**：需要配置邮件服务器才能使用邮件发送功能。当前实现发送HTML邮件。如需PDF附件，可修改代码使用PHPMailer库附加PDF文件。
- **兼容性**：已在Zabbix 6.0和7.0环境中测试通过。模块采用智能版本检测，自动适配不同版本。
- **监控项依赖**：CPU和内存信息的显示依赖于相应的监控项配置。
- **自定义扩展**：可以修改代码添加更多报表内容或改进UI。支持Linux Agent和Windows Agent模板。

## 开发

插件基于Zabbix模块框架开发。文件结构：

- `manifest.json`：模块配置
- `Module.php`：菜单注册和版本适配
- `actions/`：业务逻辑处理
  - `DailyReport.php`, `WeeklyReport.php`, `MonthlyReport.php`：报表生成
  - `CustomReport.php`：自定义时间范围报表
  - `*Export.php`：PDF导出功能
  - `*Send.php`：邮件发送功能
  - `*Debug.php`, `*Test.php`：调试和测试工具
- `views/`：页面视图
  - `reports.daily.php`, `reports.weekly.php`, `reports.monthly.php`：报表页面
  - `reports.custom.php`：自定义报表页面
- `lib/`：工具类库
  - `ZabbixVersion.php`：版本检测和适配
  - `ViewRenderer.php`：统一视图渲染
  - `LanguageManager.php`：国际化语言管理
  - `PdfGenerator.php`：PDF生成工具
  - `ItemFinder.php`：监控项查找工具

如需扩展，可参考[Zabbix模块开发文档](https://www.zabbix.com/documentation/current/en/devel/modules)。

## 许可证

本项目遵循Zabbix的许可证。详情请见[Zabbix许可证](https://www.zabbix.com/license)。

## 贡献

欢迎提交问题和改进建议！

### 报告问题
- 描述问题和重现步骤
- 提供Zabbix版本信息
- 附上错误日志

### 提交代码
- Fork项目仓库
- 创建特性分支
- 确保在Zabbix 6.0和7.0中测试
- 提交Pull Request

---

## English

## Description

This is a frontend module for Zabbix that generates daily, weekly, monthly, and custom range reports. It supports in-page preview, PDF export, and email delivery. The report includes problem statistics, top problem hosts, and top hosts by CPU and memory utilization.

**Compatibility Note**: The module uses intelligent version detection and runs seamlessly on both Zabbix 6.0 and Zabbix 7.0+ environments.

## Features

- **Report Types**: Daily / Weekly / Monthly / Custom date range
- **Report Content**:
  - Problem counts and status statistics
  - Top problem hosts (Top 10)
  - Top CPU utilization hosts (Top 10)
  - Top memory utilization hosts (Top 10)
- **Actions**:
  - Preview in page with real-time data
  - Export PDF with complete charts
  - Send Email in HTML format
  - Custom date range selection
- **Internationalization**: Chinese and English interface support
- **Responsive Design**: Adapts to different screen sizes
- **Modern Interface**: Modern design with gradient colors and animation effects
- **Compatibility**: Linux Agent and Windows Agent templates
- **Statistics**: Display total problems, active issues and other statistics

## Installation

### ⚠️ Important: Modify manifest.json Based on Your Zabbix Version

**Before installation, please modify the `manifest.json` file according to your Zabbix version:**

- **Zabbix 6.0**: Change `"manifest_version": 2.0` to `"manifest_version": 1.0`
- **Zabbix 7.0+**: Keep `"manifest_version": 2.0` as default

```bash
# For Zabbix 6.0 users
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_reports/manifest.json

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
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_reports/manifest.json
```

Then enable the module in Zabbix Web UI:

1. Go to **Administration → General → Modules**.
2. Click **Scan directory**.
3. Find "Zabbix Reports" and enable it.
4. The module will appear under the Reports menu as "Zabbix Reports" submenu with "Daily Report", "Weekly Report", "Monthly Report" and "Custom Report" subitems.

## Usage

### Access Reports

1. Log in to Zabbix Web UI.
2. Navigate to Reports → Zabbix Reports.
3. Choose Daily Report, Weekly Report, Monthly Report, or Custom Report.

### Daily/Weekly/Monthly Reports

#### Preview Report

- The page automatically displays report data for the corresponding time range.
- Includes problem statistics charts and host ranking lists.
- Supports real-time data refresh.

#### Export PDF

- Click the **Export PDF** button on the report page.
- The browser will automatically download the generated PDF file.
- The PDF file contains complete report content and charts.

#### Send Email

- Click the **Send Email** button on the report page.
- The report will be sent as an HTML email to configured recipients.
- The email includes all statistics and charts.

### Custom Report

#### Select Date Range

- Use the date picker to select start and end dates.
- Click the "Generate Report" button.
- The system will generate statistics for the specified time range.

#### View Report Content

- Problem count statistics and trend charts.
- Top problem hosts ranking (Top 10).
- Top CPU utilization hosts ranking (Top 10).
- Top memory utilization hosts ranking (Top 10).

#### Export and Share

- Support PDF format export.
- Support sending reports via email.
- PDF filename includes the selected date range.

## Configuration

### Permission Requirements

- Users need Zabbix user or higher permissions to access report functionality.

### Email Configuration (Under Development)

To configure email sending, modify the following variables in the action files (`actions/DailyReportSend.php`, etc.):

```php
$to = 'admin@example.com'; // Recipient email
$headers = "MIME-Version: 1.0" . "\r\n";
$headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
$headers .= 'From: zabbix@example.com' . "\r\n"; // Sender email
```

Ensure that the Zabbix server's PHP environment is configured for email sending (e.g., via sendmail or SMTP).

### Data Sources

- Problem information comes from Zabbix's problem table.
- CPU and memory information comes from related item history data.
- Host information comes from the host table.

### Item Requirements

The module expects the following item names:

- **CPU utilization**: CPU usage percentage
- **Memory utilization**: Memory usage percentage

Ensure hosts have the appropriate templates applied and item names match.

## Notes

- **Permission Requirements**: Zabbix user or higher permissions are required to access report functionality.
- **Data Accuracy**: Reports are based on historical data. Ensure Zabbix retains history data long enough. Displayed information is based on the current state of the Zabbix database.
- **Performance Considerations**: For large environments, consider limiting query time ranges appropriately. Report generation may take some time.
- **Email Configuration**: Mail server configuration is required to use email sending functionality. Current implementation sends HTML emails. For PDF attachments, modify code to use PHPMailer library.
- **Compatibility**: Tested on both Zabbix 6.0 and 7.0 environments. The module uses intelligent version detection and automatically adapts to different versions.
- **Item Dependencies**: Display of CPU and memory information depends on corresponding item configuration.
- **Customization**: Feel free to modify code to add more report content or improve UI. Supports Linux Agent and Windows Agent templates.

## Development

Structure:

- `manifest.json`: module config
- `Module.php`: menu registration and version adaptation
- `actions/`: business logic processing
  - `DailyReport.php`, `WeeklyReport.php`, `MonthlyReport.php`: report generation
  - `CustomReport.php`: custom date range reports
  - `*Export.php`: PDF export functionality
  - `*Send.php`: email sending functionality
  - `*Debug.php`, `*Test.php`: debugging and testing tools
- `views/`: page views
  - `reports.daily.php`, `reports.weekly.php`, `reports.monthly.php`: report pages
  - `reports.custom.php`: custom report page
- `lib/`: utility classes
  - `ZabbixVersion.php`: version detection and adaptation
  - `ViewRenderer.php`: unified view rendering
  - `LanguageManager.php`: internationalization language management
  - `PdfGenerator.php`: PDF generation utilities
  - `ItemFinder.php`: item finder utilities

See also: [Zabbix module documentation](https://www.zabbix.com/documentation/current/en/devel/modules)

## License

Follows Zabbix license: [https://www.zabbix.com/license](https://www.zabbix.com/license)

## Contributing

Issues and PRs are welcome!

### Reporting Issues
- Describe the issue and reproduction steps
- Provide Zabbix version information
- Attach error logs

### Submitting Code
- Fork the repository
- Create feature branch
- Ensure testing in both Zabbix 6.0 and 7.0
- Submit Pull Request
