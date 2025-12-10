# Zabbix 6.0 & 7.0 兼容性说明 / Compatibility Notes

## 概述 / Overview

本模块采用兼容层设计,同时支持 Zabbix 6.0 和 Zabbix 7.0+,无需维护两套代码。

This module uses a compatibility layer design to support both Zabbix 6.0 and Zabbix 7.0+ without maintaining two separate codebases.

## 核心兼容机制 / Core Compatibility Mechanisms

### 1. 自动版本检测 / Automatic Version Detection

**文件**: `lib/ZabbixVersion.php`

通过检测核心类的命名空间自动识别Zabbix版本:
- Zabbix 7.0+: `Zabbix\Core\CModule`
- Zabbix 6.0: `Core\CModule`

The module automatically detects the Zabbix version by checking core class namespaces.

```php
// 检测版本 / Detect version
$version = ZabbixVersion::detect();

// 版本判断 / Version check
if (ZabbixVersion::isVersion7()) {
    // Zabbix 7.0+ 特定逻辑
}
```

### 2. 动态基类选择 / Dynamic Base Class Selection

**文件**: `Module.php`

根据检测到的版本动态选择正确的基类:

```php
$baseClass = ZabbixVersion::getModuleBaseClass();
// Zabbix 7.0+: "Zabbix\Core\CModule"
// Zabbix 6.0: "Core\CModule"

if (class_exists('Core\CModule')) {
    class ModuleBase extends \Core\CModule {}
} else {
    class ModuleBase extends \Zabbix\Core\CModule {}
}
```

### 3. 统一视图渲染 / Unified View Rendering

**文件**: `lib/ViewRenderer.php`

提供统一的页面渲染接口,自动选择合适的渲染类:
- Zabbix 7.0+: 使用 `CHtmlPage`
- Zabbix 6.0: 使用 `CWidget`
- 降级处理: HTML 字符串

Provides a unified page rendering interface with automatic renderer selection.

```php
// 在视图中使用 / Usage in views
ViewRenderer::render($pageTitle, $styleTag, $content);
```

### 4. 方法兼容性检查 / Method Compatibility Checks

**文件**: 所有 `actions/*.php` 控制器文件

使用反射检查方法是否存在,调用正确的API:

```php
protected function init(): void {
    if (method_exists($this, 'disableCsrfValidation')) {
        $this->disableCsrfValidation();  // Zabbix 7.0+
    } elseif (method_exists($this, 'disableSIDvalidation')) {
        $this->disableSIDvalidation();   // Zabbix 6.0
    }
}
```

## 版本差异对照表 / Version Differences

| 功能 / Feature | Zabbix 6.0 | Zabbix 7.0+ |
|---------------|------------|-------------|
| 模块基类命名空间 / Module Base Class | `Core\CModule` | `Zabbix\Core\CModule` |
| CSRF保护方法 / CSRF Protection | `disableSIDvalidation()` | `disableCsrfValidation()` |
| 视图渲染类 / View Renderer | `CWidget` | `CHtmlPage` |
| 菜单API / Menu API | `APP::getMenu()` | `APP::Component()->get('menu')` |
| Manifest版本 / Manifest Version | 1.0 | 2.0 |

## 文件变更说明 / Modified Files

### 新增文件 / New Files

1. **`lib/ZabbixVersion.php`**
   - 版本检测核心类 / Core version detection class
   - 提供版本判断和API适配方法 / Provides version checks and API adaptation methods

2. **`lib/ViewRenderer.php`**
   - 统一视图渲染接口 / Unified view rendering interface
   - 自动选择正确的渲染类 / Automatic renderer selection

3. **`COMPATIBILITY.md`** (本文件)
   - 兼容性说明文档 / Compatibility documentation

### 修改的文件 / Modified Files

1. **`Module.php`**
   - 动态基类选择 / Dynamic base class selection
   - 容错的菜单注册 / Fault-tolerant menu registration

2. **所有控制器文件 / All Controller Files**
   - `actions/DailyReport.php`
   - `actions/WeeklyReport.php`
   - `actions/MonthlyReport.php`
   - `actions/CustomReport.php`
   - `actions/DailyReportExport.php`
   - `actions/WeeklyReportExport.php`
   - `actions/MonthlyReportExport.php`
   - `actions/CustomReportExport.php`
   - `actions/DailyReportSend.php`
   - `actions/WeeklyReportSend.php`
   - `actions/MonthlyReportSend.php`
   - `actions/DailyReportDebug.php`
   - `actions/DailyReportSimpleTest.php`
   - `actions/DailyReportKeyTest.php`
   - `actions/DailyReportItemScan.php`
   - 方法兼容性检查 / Method compatibility checks
   - 响应对象标题设置 / Response object title setting

3. **所有视图文件 / All View Files**
   - `views/reports.daily.php`
   - `views/reports.weekly.php`
   - `views/reports.monthly.php`
   - `views/reports.custom.php`
   - 使用ViewRenderer统一渲染 / Uses ViewRenderer for unified rendering

4. **`manifest.json`**
   - 版本设置为1.0 / Set version to 1.0
   - 添加兼容性说明 / Added compatibility description

## 安装说明 / Installation

### ⚠️ 重要：manifest_version 配置 / Important: manifest_version Configuration

**Zabbix 6.0 和 7.0 使用不同的 manifest_version：**

- **Zabbix 6.0**: 需要 `"manifest_version": 1.0`
- **Zabbix 7.0+**: 需要 `"manifest_version": 2.0`

**Zabbix 6.0 and 7.0 require different manifest_version:**

```bash
# 如果使用 Zabbix 6.0，修改 manifest.json
# For Zabbix 6.0, modify manifest.json
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_reports/manifest.json

# 如果使用 Zabbix 7.0+，保持默认值 2.0
# For Zabbix 7.0+, keep default value 2.0
```

### Zabbix 6.0

```bash
# 修改 manifest_version / Modify manifest_version
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_reports/manifest.json

# 复制模块文件 / Copy module files
cp -r zabbix_reports /usr/share/zabbix/modules/

# 设置正确的权限 / Set correct permissions
chmod -R 755 /usr/share/zabbix/modules/zabbix_reports
chown -R apache:apache /usr/share/zabbix/modules/zabbix_reports

# 重启Web服务 / Restart web service
systemctl restart php-fpm
systemctl restart httpd  # or nginx
```

### Zabbix 7.0+

```bash
# 无需修改 manifest_version (保持 2.0) / No need to modify manifest_version (keep 2.0)

# 复制模块文件 / Copy module files
cp -r zabbix_reports /usr/share/zabbix/modules/

# 设置正确的权限 / Set correct permissions
chmod -R 755 /usr/share/zabbix/modules/zabbix_reports
chown -R apache:apache /usr/share/zabbix/modules/zabbix_reports

# 重启Web服务 / Restart web service
systemctl restart php-fpm
systemctl restart httpd  # or nginx
```

## 测试验证 / Testing

### 验证版本检测 / Verify Version Detection

在Zabbix日志中应该能看到检测到的版本信息:

You should see the detected version in Zabbix logs:

```
[Zabbix Reports] Detected Zabbix version: 6.0 (or 7.0)
```

### 功能测试 / Function Testing

1. 登录Zabbix Web界面 / Login to Zabbix Web UI
2. 检查"报表" / "Reports"菜单下是否有"Zabbix Reports"子菜单
3. 测试每日报表生成和显示 / Test daily report generation and display
4. 测试周报表生成和显示 / Test weekly report generation and display
5. 测试月报表生成和显示 / Test monthly report generation and display
6. 测试自定义报表日期选择 / Test custom report date selection
7. 测试PDF导出功能 / Test PDF export functionality

## 报表内容 / Report Contents

### 每日/周/月报表包含 / Daily/Weekly/Monthly Reports Include

- 报警数量统计 / Problem count statistics
- 报警状态分布 / Problem status distribution
- 报警最多的主机（Top 10）/ Top problem hosts (Top 10)
- CPU使用率最高的主机（Top 10）/ Top CPU utilization hosts (Top 10)
- 内存使用率最高的主机（Top 10）/ Top memory utilization hosts (Top 10)

### 自定义报表 / Custom Report

支持自定义时间范围的报表生成,包含上述所有内容。

Supports custom date range report generation with all the above content.

## 故障排除 / Troubleshooting

### 模块未加载 / Module Not Loading

检查以下内容 / Check the following:

1. 确认manifest.json语法正确 / Verify manifest.json syntax
2. 检查PHP错误日志 / Check PHP error logs
3. 确认文件权限正确 / Verify file permissions

```bash
chmod -R 755 /usr/share/zabbix/modules/zabbix_reports
chown -R apache:apache /usr/share/zabbix/modules/zabbix_reports
```

### 菜单未显示 / Menu Not Showing

可能原因 / Possible causes:

1. 用户权限不足 / Insufficient user permissions
2. 版本检测失败 / Version detection failed
3. 菜单注册失败(查看日志) / Menu registration failed (check logs)

### 页面渲染错误 / Page Rendering Error

ViewRenderer已包含降级处理,如果出现问题:

ViewRenderer includes fallback handling, but if issues occur:

1. 检查是否缺少必要的Zabbix类 / Check for missing Zabbix classes
2. 查看PHP错误日志 / Review PHP error logs
3. 验证Zabbix版本是否为6.0或7.0+ / Verify Zabbix version is 6.0 or 7.0+

### PDF导出失败 / PDF Export Failed

1. 检查PHP PDF扩展是否已安装 / Check if PHP PDF extensions are installed
2. 验证临时目录写入权限 / Verify write permissions for temp directory
3. 查看PDF生成日志 / Check PDF generation logs

### 数据不准确 / Inaccurate Data

1. 确认监控项名称匹配 / Verify item names match
   - CPU utilization
   - Memory utilization
2. 检查历史数据保留时间 / Check history data retention period
3. 验证主机模板配置 / Verify host template configuration

## 性能优化 / Performance Optimization

### 大型环境建议 / Recommendations for Large Environments

1. **限制查询时间范围** / Limit query time range
   - 避免生成超过3个月的报表 / Avoid generating reports over 3 months

2. **调整Top N数量** / Adjust Top N count
   - 可以在代码中修改显示数量 / Can modify display count in code

3. **使用缓存** / Use caching
   - 对于相同时间范围的查询可以缓存结果 / Can cache results for same time range queries

4. **异步生成** / Asynchronous generation
   - 对于大型报表,建议异步生成PDF / For large reports, recommend async PDF generation

## 技术架构 / Technical Architecture

### 控制器层 / Controller Layer
- 处理用户请求和业务逻辑 / Handles user requests and business logic
- 数据采集和处理 / Data collection and processing
- 响应格式化 / Response formatting

### 视图层 / View Layer
- 统一使用ViewRenderer渲染 / Unified rendering with ViewRenderer
- 响应式布局设计 / Responsive layout design
- 现代化UI组件 / Modern UI components

### 工具类 / Utility Classes
- `ZabbixVersion`: 版本检测 / Version detection
- `ViewRenderer`: 视图渲染 / View rendering
- `LanguageManager`: 国际化 / Internationalization
- `PdfGenerator`: PDF生成 / PDF generation
- `ItemFinder`: 监控项查找 / Item finder

## 开发指南 / Development Guide

### 添加新的报表类型 / Adding New Report Types

1. 创建控制器 / Create controller
   ```php
   // actions/NewReport.php
   class CControllerNewReport extends CController {
       protected function init(): void {
           // 添加CSRF兼容性检查
       }
       
       protected function doAction(): void {
           // 实现业务逻辑
       }
   }
   ```

2. 创建视图 / Create view
   ```php
   // views/reports.new.php
   ViewRenderer::render($pageTitle, $styleTag, $content);
   ```

3. 注册路由 / Register route in Module.php

### 修改现有报表 / Modifying Existing Reports

1. 修改控制器数据采集逻辑 / Modify controller data collection logic
2. 调整视图显示样式 / Adjust view display style
3. 测试在两个版本中的兼容性 / Test compatibility in both versions

## 安全性 / Security

### CSRF保护 / CSRF Protection
- 自动检测并使用正确的CSRF方法 / Automatically detects and uses correct CSRF method
- Zabbix 6.0: disableSIDvalidation()
- Zabbix 7.0+: disableCsrfValidation()

### 权限控制 / Permission Control
- 继承Zabbix用户权限系统 / Inherits Zabbix user permission system
- 需要至少Zabbix User角色 / Requires at least Zabbix User role

### 数据安全 / Data Security
- 使用Zabbix API访问数据 / Uses Zabbix API for data access
- 不直接操作数据库 / No direct database operations
- 参数过滤和验证 / Parameter filtering and validation

## 更新日志 / Changelog

### Version 1.1.0 (2025-10-14)
- ✅ 添加Zabbix 6.0兼容性支持 / Added Zabbix 6.0 compatibility support
- ✅ 实现自动版本检测机制 / Implemented automatic version detection
- ✅ 统一视图渲染接口 / Unified view rendering interface
- ✅ 更新所有控制器和视图 / Updated all controllers and views
- ✅ 添加兼容性文档 / Added compatibility documentation

### Version 1.0.0
- 初始版本,支持Zabbix 7.0 / Initial version for Zabbix 7.0
- 每日、周、月报表功能 / Daily, weekly, monthly report features
- PDF导出功能 / PDF export functionality
- 邮件发送功能 / Email sending functionality

## 贡献指南 / Contributing

欢迎提交问题和改进建议! / Issues and PRs are welcome!

### 报告问题 / Reporting Issues
1. 描述问题和重现步骤 / Describe the issue and reproduction steps
2. 提供Zabbix版本信息 / Provide Zabbix version information
3. 附上错误日志 / Attach error logs

### 提交代码 / Submitting Code
1. Fork项目仓库 / Fork the repository
2. 创建特性分支 / Create feature branch
3. 确保在两个版本中测试 / Ensure testing in both versions
4. 提交Pull Request / Submit Pull Request

## 许可证 / License

本项目遵循Zabbix的许可证。详情请见[Zabbix许可证](https://www.zabbix.com/license)。

This project follows Zabbix license: [https://www.zabbix.com/license](https://www.zabbix.com/license)

## 支持 / Support

- GitHub Issues: [项目Issues页面](https://github.com/X-Mars/zabbix_modules/issues)
- Zabbix官方文档: [https://www.zabbix.com/documentation](https://www.zabbix.com/documentation)
- 模块开发文档: [Zabbix Module Development](https://www.zabbix.com/documentation/current/en/devel/modules)
