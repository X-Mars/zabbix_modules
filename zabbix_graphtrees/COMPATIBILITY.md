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
if (class_exists('Zabbix\Core\CModule')) {
    class ModuleBase extends \Zabbix\Core\CModule {}
} elseif (class_exists('Core\CModule')) {
    class ModuleBase extends \Core\CModule {}
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

**文件**: `actions/GraphTrees.php`, `actions/GraphTreesData.php`

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

3. **`lib/LanguageManager.php`**
   - 国际化语言管理 / Internationalization language management
   - 支持中英文切换 / Support Chinese-English switching

### 核心文件 / Core Files

1. **`Module.php`**
   - 动态继承兼容基类 / Dynamic inheritance of compatible base class
   - 菜单注册适配 / Menu registration adaptation

2. **`actions/GraphTrees.php`**
   - 主控制器，处理资源树和图形展示 / Main controller for resource tree and graphs

3. **`actions/GraphTreesData.php`**
   - 数据API控制器，提供JSON格式的历史数据 / Data API controller for JSON history data

4. **`views/graphtrees.php`**
   - 主视图文件 / Main view file
   - 树形结构和图形展示界面 / Tree structure and graph display interface

## API兼容性 / API Compatibility

### 支持的API / Supported APIs

本模块使用以下Zabbix API,这些API在6.0和7.0中保持兼容:

The module uses the following Zabbix APIs, which remain compatible between 6.0 and 7.0:

- `API::HostGroup()->get()` - 获取主机分组
- `API::Host()->get()` - 获取主机信息
- `API::Item()->get()` - 获取监控项
- `API::History()->get()` - 获取历史数据

### API差异处理 / API Difference Handling

模块采用以下策略处理API差异:

The module handles API differences using the following strategies:

1. **参数兼容性检查** / Parameter compatibility checks
2. **多种查询策略** / Multiple query strategies
3. **优雅降级处理** / Graceful degradation
4. **错误日志记录** / Error logging

## 测试环境 / Test Environments

模块已在以下环境中测试通过:

The module has been tested in the following environments:

- ✅ Zabbix 6.0 LTS
- ✅ Zabbix 7.0

## 安装注意事项 / Installation Notes

### Manifest版本配置 / Manifest Version Configuration

**重要**: 根据Zabbix版本修改 `manifest.json`:

**Important**: Modify `manifest.json` based on your Zabbix version:

```bash
# Zabbix 6.0
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' manifest.json

# Zabbix 7.0+
# 保持默认值 / Keep default value
```

## 故障排除 / Troubleshooting

### 常见问题 / Common Issues

1. **模块未显示在菜单中**
   - 检查manifest.json的manifest_version是否正确
   - 确认已在管理界面启用模块
   - 检查浏览器控制台的JavaScript错误

2. **页面显示错误**
   - 检查PHP错误日志
   - 确认Zabbix版本与manifest_version匹配
   - 验证文件权限

3. **图形无法加载**
   - 检查监控项是否有历史数据
   - 确认标记配置正确
   - 验证API权限

## 升级指南 / Upgrade Guide

### 从Zabbix 6.0升级到7.0 / Upgrading from Zabbix 6.0 to 7.0

1. 升级Zabbix到7.0
2. 修改manifest.json的manifest_version为2.0
3. 重新扫描模块目录
4. 测试功能是否正常

### 从Zabbix 7.0降级到6.0 / Downgrading from Zabbix 7.0 to 6.0

1. 降级Zabbix到6.0
2. 修改manifest.json的manifest_version为1.0
3. 重新扫描模块目录
4. 测试功能是否正常

## 开发建议 / Development Recommendations

### 添加新功能时的兼容性考虑 / Compatibility Considerations When Adding Features

1. **使用版本检测**
   ```php
   if (ZabbixVersion::isVersion7()) {
       // Zabbix 7.0+ 特定代码
   } else {
       // Zabbix 6.0 特定代码
   }
   ```

2. **使用方法存在性检查**
   ```php
   if (method_exists($this, 'newMethod')) {
       $this->newMethod();
   }
   ```

3. **API调用封装**
   - 将API调用封装在try-catch中
   - 提供降级方案
   - 记录错误日志

4. **测试两个版本**
   - 在Zabbix 6.0环境测试
   - 在Zabbix 7.0环境测试
   - 验证功能一致性

## 技术支持 / Technical Support

如遇到兼容性问题,请提供以下信息:

If you encounter compatibility issues, please provide:

- Zabbix版本号 / Zabbix version
- PHP版本 / PHP version
- 错误日志 / Error logs
- 浏览器控制台错误 / Browser console errors

## 更新日志 / Changelog

### Version 1.0.0
- ✅ 初始版本 / Initial release
- ✅ 支持Zabbix 6.0和7.0 / Support for Zabbix 6.0 and 7.0
- ✅ 树形资源浏览 / Tree resource browser
- ✅ 监控图形展示 / Monitoring graph display
- ✅ 标记过滤功能 / Tag filtering
- ✅ 中英文支持 / Chinese-English support
