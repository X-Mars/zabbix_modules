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

eval("class ModuleBase extends {$baseClass} {}");
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

**文件**: `actions/Cmdb.php`, `actions/CmdbGroups.php`

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

2. **`actions/Cmdb.php`**
   - 方法兼容性检查 / Method compatibility checks

3. **`actions/CmdbGroups.php`**
   - 方法兼容性检查 / Method compatibility checks

4. **`views/cmdb.php`**
   - 使用ViewRenderer统一渲染 / Uses ViewRenderer for unified rendering

5. **`views/cmdb.groups.php`**
   - 使用ViewRenderer统一渲染 / Uses ViewRenderer for unified rendering

6. **`manifest.json`**
   - 版本更新至2.0 / Updated to version 2.0
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
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_cmdb/manifest.json

# 如果使用 Zabbix 7.0+，保持默认值 2.0
# For Zabbix 7.0+, keep default value 2.0
```

### Zabbix 6.0

```bash
# 修改 manifest_version / Modify manifest_version
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_cmdb/manifest.json

# 复制模块文件 / Copy module files
cp -r zabbix_cmdb /usr/share/zabbix/modules/

# 设置正确的权限 / Set correct permissions
chmod -R 755 /usr/share/zabbix/modules/zabbix_cmdb
chown -R apache:apache /usr/share/zabbix/modules/zabbix_cmdb

# 重启Web服务 / Restart web service
systemctl restart php-fpm
systemctl restart httpd  # or nginx
```

### Zabbix 7.0+

```bash
# 无需修改 manifest_version (保持 2.0) / No need to modify manifest_version (keep 2.0)

# 复制模块文件 / Copy module files
cp -r zabbix_cmdb /usr/share/zabbix/modules/

# 设置正确的权限 / Set correct permissions
chmod -R 755 /usr/share/zabbix/modules/zabbix_cmdb
chown -R apache:apache /usr/share/zabbix/modules/zabbix_cmdb

# 重启Web服务 / Restart web service
systemctl restart php-fpm
systemctl restart httpd  # or nginx
```

## 测试验证 / Testing

### 验证版本检测 / Verify Version Detection

在Zabbix日志中应该能看到检测到的版本信息:

You should see the detected version in Zabbix logs:

```
[Zabbix CMDB] Detected Zabbix version: 6.0 (or 7.0)
```

### 功能测试 / Function Testing

1. 登录Zabbix Web界面 / Login to Zabbix Web UI
2. 检查"资产记录" / "Inventory"菜单下是否有"CMDB"子菜单
3. 点击进入,验证主机列表显示正常
4. 测试搜索和分组筛选功能

## 故障排除 / Troubleshooting

### 模块未加载 / Module Not Loading

检查以下内容 / Check the following:

1. 确认manifest.json语法正确 / Verify manifest.json syntax
2. 检查PHP错误日志 / Check PHP error logs
3. 确认文件权限正确 / Verify file permissions

```bash
chmod -R 755 /usr/share/zabbix/modules/zabbix_cmdb
chown -R apache:apache /usr/share/zabbix/modules/zabbix_cmdb
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

## 开发说明 / Development Notes

### 添加新功能时 / When Adding New Features

确保遵循兼容性原则 / Ensure following compatibility principles:

1. **使用 ViewRenderer 进行页面渲染 / Use ViewRenderer for page rendering**
   ```php
   ViewRenderer::render($title, $styleTag, $content);
   ```

2. **使用 ZabbixVersion 检查版本 / Use ZabbixVersion for version checks**
   ```php
   if (ZabbixVersion::isVersion7()) {
       // 7.0+ specific code
   } else {
       // 6.0 specific code
   }
   ```

3. **使用 method_exists 检查方法 / Use method_exists for method checks**
   ```php
   if (method_exists($this, 'newMethod')) {
       $this->newMethod();
   }
   ```

### 代码审查清单 / Code Review Checklist

- [ ] 避免直接使用版本特定的类 / Avoid directly using version-specific classes
- [ ] 所有视图使用ViewRenderer / All views use ViewRenderer
- [ ] CSRF保护使用method_exists检查 / CSRF protection uses method_exists check
- [ ] 在两个版本上测试 / Test on both versions
- [ ] 更新COMPATIBILITY.md / Update COMPATIBILITY.md

## 技术细节 / Technical Details

### 为什么使用 eval() / Why Use eval()

在 `Module.php` 中使用 `eval()` 是因为PHP不支持动态基类:

The use of `eval()` in `Module.php` is necessary because PHP doesn't support dynamic base classes:

```php
// ❌ 不能这样做 / Cannot do this:
class Module extends $dynamicBaseClass {}

// ✅ 只能这样做 / Must do this:
eval("class ModuleBase extends {$baseClass} {}");
class Module extends ModuleBase {}
```

这是唯一的解决方案,且代码完全可控,不存在安全风险。

This is the only solution, and the code is fully controlled with no security risks.

### 性能影响 / Performance Impact

版本检测和兼容性检查的性能开销极小:
- 版本检测仅在模块加载时执行一次
- 方法检查使用PHP内置的反射,非常快速
- 没有引入额外的数据库查询或网络请求

The performance overhead of version detection and compatibility checks is minimal:
- Version detection runs only once during module loading
- Method checks use PHP's built-in reflection, which is very fast
- No additional database queries or network requests

## 未来维护 / Future Maintenance

如果Zabbix发布新的主要版本(如8.0),只需:

If Zabbix releases a new major version (e.g., 8.0), only need to:

1. 在 `ZabbixVersion.php` 中添加新版本检测
2. 在 `ViewRenderer.php` 中添加新的渲染类支持
3. 更新 `manifest.json` 的兼容性说明
4. 更新本文档

无需修改业务逻辑代码! / No need to modify business logic code!

## 许可证 / License

与主项目相同 / Same as main project

## 贡献 / Contributing

欢迎提交兼容性相关的问题和改进建议!

Contributions for compatibility improvements are welcome!
