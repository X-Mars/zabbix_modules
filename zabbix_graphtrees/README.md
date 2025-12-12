# Zabbix Graph Trees Module

[简体中文](#描述) | [English](#description)

## ✨ 版本兼容性 / Version Compatibility

### 本模块同时兼容 Zabbix 6.0 和 Zabbix 7.0+ / Compatible with both Zabbix 6.0 and Zabbix 7.0+

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x

### 自动版本检测,无需手动配置 / Automatic version detection, no manual configuration needed

模块内置智能版本检测机制,自动适配不同版本的Zabbix API和类库,无需任何手动配置。

The module includes intelligent version detection that automatically adapts to different Zabbix API versions and class libraries, requiring no manual configuration.

## 描述

这是一个Zabbix前端模块，提供树形资源浏览和监控图形可视化功能。

**主要功能**：

- **左侧资源树**：
  - 按主机分组-主机层级展示
  - 支持展开/收起分组
  - 支持搜索过滤
  - 点击主机查看监控图形

- **右侧图形展示**：
  - 标记（Tag）过滤
  - 标记值（Tag Value）过滤
  - 时间范围选择（1小时到30天）
  - 监控值折线图展示
  - 支持多个监控项同时展示

**兼容性说明**: 模块采用智能版本检测机制，可在Zabbix 6.0和7.0+环境中无缝运行。

## 项目截图

待添加

## 功能特性

- **树形资源浏览**：
  - 主机分组层级展示
  - 主机列表展示
  - 快速搜索定位
  - 展开/收起控制

- **灵活的过滤**：
  - 按标记过滤监控项
  - 按标记值精确过滤
  - 多种预设时间范围
  - 自定义时间范围（开发中）

- **图形展示**：
  - 实时数据加载
  - 响应式布局
  - 支持多监控项
  - 自动刷新功能

- **国际化支持**：支持中英文界面
- **响应式设计**：适配不同屏幕尺寸
- **现代化界面**：采用清晰简洁的设计风格

## 安装步骤

### ⚠️ 重要提示：根据Zabbix版本修改manifest.json

**在安装前，请根据您的Zabbix版本修改 `manifest.json` 文件：**

- **Zabbix 6.0**: 将 `"manifest_version": 2.0` 改为 `"manifest_version": 1.0`
- **Zabbix 7.0+**: 保持 `"manifest_version": 2.0` 不变

```bash
# 对于Zabbix 6.0用户
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_graphtrees/manifest.json

# 对于Zabbix 7.0+用户
# 无需修改，默认即可
```

### 方法1：直接复制到模块目录

1. 将 `zabbix_graphtrees` 文件夹复制到Zabbix模块目录：

```bash
cp -r zabbix_graphtrees /usr/share/zabbix/modules/
```

2. 在Zabbix Web界面中启用模块：
   - 转到 **Administration → General → Modules**
   - 点击 **Scan directory** 按钮扫描新模块
   - 找到 "Zabbix Graph Trees" 模块，点击启用
   - 刷新页面

### 方法2：从完整项目安装

如果你克隆了整个 zabbix_modules 项目：

```bash
cd /usr/share/zabbix/modules/
git clone https://github.com/X-Mars/zabbix_modules.git .
```

```bash
# ⚠️ 如果使用Zabbix 6.0，修改manifest_version
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_graphtrees/manifest.json
```

然后按照上述步骤启用模块。

## 使用方法

### 访问模块

1. 登录Zabbix Web界面
2. 导航到 **Monitoring → Graph Trees**

### 浏览资源树

1. 左侧显示按主机分组组织的资源树
2. 点击分组名称可展开/收起主机列表
3. 使用顶部搜索框快速过滤主机
4. 使用"展开全部"/"收起全部"按钮控制树的显示

### 查看监控图形

1. 点击左侧树中的主机名称
2. 右侧将显示该主机的监控项
3. 使用顶部过滤器：
   - **标记**：选择特定的标记进行过滤
   - **标记值**：进一步按标记值过滤
   - **时间范围**：选择查看的时间范围
4. 点击"刷新"按钮更新图形数据

### 时间范围选项

- 最近1小时
- 最近3小时
- 最近6小时
- 最近12小时
- 最近24小时
- 最近7天
- 最近30天
- 自定义（开发中）

## 配置

### 权限要求

- 用户需要Zabbix用户或更高权限才能访问模块功能

### 标记配置

模块通过Zabbix的标记（Tags）系统来组织和过滤监控项。建议：

1. 为主机配置有意义的标记
2. 为监控项配置相应的标记
3. 使用一致的标记命名规范

### 性能考虑

- 默认限制每个主机显示100个监控项
- 历史数据查询限制为1000个数据点
- 对于大型环境，建议使用标记过滤减少数据量

## 注意事项

- **数据准确性**：图形基于历史数据，确保Zabbix历史数据保留足够长的时间
- **兼容性**：已在Zabbix 6.0和7.0环境中测试通过
- **浏览器要求**：建议使用现代浏览器（Chrome、Firefox、Edge等）
- **图表库**：当前版本使用简单的数据展示，可集成Chart.js等图表库实现更丰富的可视化

## 开发

模块基于Zabbix模块框架开发。文件结构：

- `manifest.json`：模块配置
- `Module.php`：菜单注册和版本适配
- `actions/`：业务逻辑处理
  - `GraphTrees.php`：主页面控制器
  - `GraphTreesData.php`：数据API控制器
- `views/`：页面视图
  - `graphtrees.php`：主页面视图
- `lib/`：工具类库
  - `ZabbixVersion.php`：版本检测
  - `ViewRenderer.php`：视图渲染
  - `LanguageManager.php`：国际化

## 许可证

MIT License

## 作者

火星小刘

## 贡献

欢迎提交问题和拉取请求！

---

## Description

This is a Zabbix frontend module that provides tree-based resource browsing and monitoring graph visualization.

**Main Features**:

- **Left Resource Tree**:
  - Display by host group - host hierarchy
  - Support expand/collapse groups
  - Support search filtering
  - Click host to view monitoring graphs

- **Right Graph Display**:
  - Tag filtering
  - Tag value filtering
  - Time range selection (1 hour to 30 days)
  - Monitoring value line graphs
  - Support multiple items simultaneously

**Compatibility Note**: The module uses intelligent version detection and runs seamlessly on both Zabbix 6.0 and Zabbix 7.0+ environments.

## Features

- **Tree Resource Browser**:
  - Host group hierarchical display
  - Host list display
  - Quick search and locate
  - Expand/collapse control

- **Flexible Filtering**:
  - Filter by tags
  - Filter by tag values
  - Multiple preset time ranges
  - Custom time range (in development)

- **Graph Display**:
  - Real-time data loading
  - Responsive layout
  - Support multiple items
  - Auto-refresh functionality

- **Internationalization**: Chinese and English interface support
- **Responsive Design**: Adapts to different screen sizes
- **Modern Interface**: Clean and simple design style

## Installation

### ⚠️ Important: Modify manifest.json Based on Your Zabbix Version

**Before installation, please modify the `manifest.json` file according to your Zabbix version:**

- **Zabbix 6.0**: Change `"manifest_version": 2.0` to `"manifest_version": 1.0`
- **Zabbix 7.0+**: Keep `"manifest_version": 2.0` as default

```bash
# For Zabbix 6.0 users
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_graphtrees/manifest.json

# For Zabbix 7.0+ users
# No modification needed
```

### Method 1: Copy Directly to Modules Directory

1. Copy the `zabbix_graphtrees` folder to the Zabbix modules directory:

```bash
cp -r zabbix_graphtrees /usr/share/zabbix/modules/
```

2. Enable the module in Zabbix Web UI:
   - Go to **Administration → General → Modules**
   - Click **Scan directory** button to scan new modules
   - Find "Zabbix Graph Trees" module and enable it
   - Refresh the page

### Method 2: Install from Complete Project

If you cloned the entire zabbix_modules project:

```bash
cd /usr/share/zabbix/modules/
git clone https://github.com/X-Mars/zabbix_modules.git .
```

```bash
# ⚠️ If using Zabbix 6.0, modify manifest_version
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_graphtrees/manifest.json
```

Then follow the steps above to enable the module.

## Usage

### Access Module

1. Log in to Zabbix Web UI
2. Navigate to **Monitoring → Graph Trees**

### Browse Resource Tree

1. The left side displays the resource tree organized by host groups
2. Click on group names to expand/collapse host lists
3. Use the search box at the top to quickly filter hosts
4. Use "Expand All"/"Collapse All" buttons to control tree display

### View Monitoring Graphs

1. Click on a host name in the left tree
2. The right side will display monitoring items for that host
3. Use the top filters:
   - **Tags**: Select specific tags to filter
   - **Tag Value**: Further filter by tag value
   - **Time Range**: Select the time range to view
4. Click "Refresh" button to update graph data

### Time Range Options

- Last Hour
- Last 3 Hours
- Last 6 Hours
- Last 12 Hours
- Last 24 Hours
- Last 7 Days
- Last 30 Days
- Custom (in development)

## Configuration

### Permission Requirements

- Users need Zabbix user or higher permissions to access module functionality

### Tag Configuration

The module organizes and filters items through Zabbix's Tags system. Recommendations:

1. Configure meaningful tags for hosts
2. Configure corresponding tags for items
3. Use consistent tag naming conventions

### Performance Considerations

- Default limit of 100 items per host
- History data query limited to 1000 data points
- For large environments, recommend using tag filtering to reduce data volume

## Notes

- **Data Accuracy**: Graphs are based on historical data, ensure Zabbix retains history long enough
- **Compatibility**: Tested on both Zabbix 6.0 and 7.0 environments
- **Browser Requirements**: Recommend using modern browsers (Chrome, Firefox, Edge, etc.)
- **Chart Library**: Current version uses simple data display, can integrate Chart.js or other chart libraries for richer visualization

## Development

The module is developed based on the Zabbix module framework. File structure:

- `manifest.json`: Module configuration
- `Module.php`: Menu registration and version adaptation
- `actions/`: Business logic handlers
  - `GraphTrees.php`: Main page controller
  - `GraphTreesData.php`: Data API controller
- `views/`: Page views
  - `graphtrees.php`: Main page view
- `lib/`: Utility classes
  - `ZabbixVersion.php`: Version detection
  - `ViewRenderer.php`: View rendering
  - `LanguageManager.php`: Internationalization

## License

MIT License

## Author

火星小刘 (Mars Liu)

## Contributing

Issues and pull requests are welcome!
