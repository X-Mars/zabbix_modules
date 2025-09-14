# Zabbix Reports Module

[简体中文](#描述) | [English](#english)

## 描述

这是一个Zabbix 7.0的前端模块，用于生成每日、周、月报表。插件可以预览报表、导出PDF以及通过邮件推送报表。报表包含报警数量、报警状态、报警最多的主机，以及CPU和内存使用率最高的主机。

## 功能特性

- **报表类型**：支持每日、周、月报表
- **报表内容**：
  - 报警数量和状态
  - 报警最多的主机（前10名）
  - CPU使用率最高的主机（前10名）
  - 内存使用率最高的主机（前10名）
- **功能**：
  - 页面预览报表
  - 手动导出PDF
  - 邮件推送报表（HTML格式）
- **兼容性**：支持Linux Agent和Windows Agent模板

## 安装步骤

1. 下载或复制`zabbix_reports`目录到Zabbix前端的`ui/modules/`目录下。

   ```bash
   cp -r zabbix_reports /usr/share/zabbix/modules/
   ```

2. 在Zabbix Web界面中，转到 **Administration → General → Modules**。

3. 点击 **Scan directory** 按钮扫描新模块。

4. 找到 "Zabbix Reports" 模块，点击 "Disabled" 链接启用模块。

5. 刷新页面，模块将在 **Reports** 菜单下显示为 "Zabbix Reports" 子菜单。

## 使用方法

### 访问报表

1. 登录Zabbix Web界面。
2. 导航到 **Reports → Zabbix Reports**。
3. 选择 **Daily Report**、**Weekly Report** 或 **Monthly Report**。

### 预览报表

- 页面将显示报表数据，包括报警统计和主机列表。

### 导出PDF

- 在报表页面，点击 **Export PDF** 按钮。
- 浏览器将下载PDF文件。

### 发送邮件

- 在报表页面，点击 **Send Email** 按钮。
- 报表将以HTML邮件形式发送到配置的收件人。

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

- **权限**：用户需要Zabbix用户或更高权限才能访问报表。
- **数据准确性**：报表基于历史数据，确保Zabbix历史数据保留足够长的时间。
- **性能**：对于大型环境，生成报表可能需要时间。
- **邮件推送**：当前实现发送HTML邮件。如需PDF附件，可修改代码使用PHPMailer库附加PDF文件。
- **兼容性**：仅在Zabbix 7.0上测试。
- **自定义**：可以修改代码添加更多报表内容或改进UI。

## 开发

插件基于Zabbix模块框架开发。文件结构：

- `manifest.json`：模块配置
- `Module.php`：菜单注册
- `actions/`：业务逻辑
- `views/`：页面视图

如需扩展，可参考[Zabbix模块开发文档](https://www.zabbix.com/documentation/7.0/en/devel/modules)。

## 许可证

本项目遵循Zabbix的许可证。详情请见[Zabbix许可证](https://www.zabbix.com/license)。

## 贡献

欢迎提交问题和改进建议。

---

## English

## Description

This is a frontend module Module for Zabbix 7.0 that generates daily, weekly, and monthly reports. It supports in-page preview and PDF export. The report includes problem statistics, top problem hosts, and top hosts by CPU and memory utilization.

Note: The "Send Email" button is currently disabled and shows a tooltip "In Development" on hover.

## Features

- Report types: Daily / Weekly / Monthly
- Report content:
  - Problem counts and statuses
  - Top problem hosts (Top 10)
  - Top CPU utilization hosts (Top 10)
  - Top memory utilization hosts (Top 10)
- Actions:
  - Preview in page
  - Export PDF
  - Send Email (disabled for now; tooltip: In Development)
- Compatibility: Linux Agent and Windows Agent templates

## Installation

1. Copy the `zabbix_reports` directory to Zabbix frontend modules directory.

   ```bash
   cp -r zabbix_reports /usr/share/zabbix/modules/
   ```

2. In Zabbix Web UI, go to Administration → General → Modules.
3. Click Scan directory.
4. Find "Zabbix Reports" and enable it.
5. The module will appear under the Reports menu as "Zabbix Reports".

## Usage

### Open reports

1. Log in to Zabbix Web UI.
2. Navigate to Reports → Zabbix Reports.
3. Choose Daily, Weekly, or Monthly.

### Preview

- The page renders problem stats and host lists.

### Export PDF

- Click Export PDF on the report page to download a PDF.

### Send Email

- The button is disabled for now and shows a tooltip "In Development".

## Configuration

### Mail (WIP)

If you want to prototype email sending later, adjust the sender/receivers in action files (e.g. `actions/DailyReportSend.php`) and ensure PHP mail is configured (sendmail/SMTP). This feature is currently disabled in the UI.

### Items

The module expects items:

- CPU utilization (percentage)
- Memory utilization (percentage)

## Notes

- Permissions: Zabbix user or above is required.
- Data accuracy: relies on historical data and retention settings.
- Performance: large environments may take time to render/export.
- Send Email: currently disabled; PDF export works.
- Compatibility: tested on Zabbix 7.0.
- Customization: feel free to extend content and UI.

## Development

Structure:

- `manifest.json`: module config
- `Module.php`: menu registration
- `actions/`: business logic
- `views/`: page views

See also: [Zabbix module documentation](https://www.zabbix.com/documentation/7.0/en/devel/modules)

## License

Follows Zabbix license: [https://www.zabbix.com/license](https://www.zabbix.com/license)

## Contributing

Issues and PRs are welcome.
