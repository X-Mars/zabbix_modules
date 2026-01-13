# Zabbix Reports 模块

[English](README_en.md)

## ✨ 版本兼容性

本模块兼容 Zabbix 6.0 和 7.0+ 版本。

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x

**兼容性说明**：模块内置智能版本检测机制，自动适配不同版本的 Zabbix API 和类库，无需手动配置。

## 描述

这是一个 Zabbix 前端模块，用于生成每日、周、月报表。模块在 Zabbix Web 的报表菜单下新增 Zabbix Reports 菜单，支持报表预览、PDF导出和邮件推送功能。

![1](images/1.png)

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

### 安装模块

```bash
# Zabbix 6.0 / 7.0 部署方法
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/modules/

# Zabbix 7.4 部署方法
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

### ⚠️ 修改 manifest.json 文件

```bash
# ⚠️ 如果使用Zabbix 6.0，修改manifest_version
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_reports/manifest.json
```

### 启用模块

1. 转到 **Administration → General → Modules**。
2. 点击 **Scan directory** 按钮扫描新模块。
3. 找到 "Zabbix Reports" 模块，点击启用模块。
4. 刷新页面，模块将在 **Reports** 菜单下显示为 "Zabbix Reports" 子菜单。

## 注意事项

- **性能考虑**：对于大型环境，建议适当限制查询结果数量。
- **数据准确性**：显示的信息基于Zabbix数据库的当前状态。
- **邮件配置**：邮件推送功能依赖于Zabbix的邮件配置。

## 开发

插件基于Zabbix模块框架开发。文件结构：

- `manifest.json`：模块配置
- `Module.php`：菜单注册
- `actions/CustomReport.php`：自定义报表业务逻辑处理
- `actions/DailyReport.php`：日报表业务逻辑处理
- `views/reports.custom.php`：自定义报表页面视图
- `views/reports.daily.php`：日报表页面视图
- `lib/LanguageManager.php`：国际化语言管理
- `lib/ViewRenderer.php`：视图渲染工具
- `lib/ZabbixVersion.php`：版本兼容工具

如需扩展，可参考[Zabbix模块开发文档](https://www.zabbix.com/documentation/7.0/en/devel/modules)。

## 许可证

本项目遵循Zabbix的许可证。详情请见[Zabbix许可证](https://www.zabbix.com/license)。

## 贡献

欢迎提交问题和改进建议。

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
