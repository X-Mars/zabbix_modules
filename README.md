# Zabbix Modules 集合

[简体中文](#描述) | [English](#english)

## ✨ 版本兼容性 / Version Compatibility

### 所有模块均兼容 Zabbix 6.0 和 Zabbix 7.0+ / All modules compatible with both Zabbix 6.0 and Zabbix 7.0+

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x

## 描述

这是一个包含多个Zabbix模块的项目集合，每个模块都是独立的Zabbix扩展，为Zabbix监控系统提供额外的功能。

## 模块列表

### 1. Zabbix Reports

**简介**：用于生成每日、周、月报表的Zabbix 7.0 前端模块，支持报表预览、PDF导出和邮件推送功能。

**功能特性**：

- 支持每日、周、月报表生成
- 报警数量和状态统计
- 显示报警最多的主机（前10名）
- 显示CPU和内存使用率最高的主机（前10名）
- 页面预览报表
- 手动导出PDF
- 邮件推送报表（HTML格式）

**文档链接**：[zabbix_reports/README.md](./zabbix_reports/README.md)

![项目截图](zabbix_reports/images/1.png)

**兼容性**：Zabbix 6.0.x, 7.0.x（自动适配）

### 2. Zabbix CMDB

**简介**：用于配置管理数据库（CMDB）的Zabbix 7.0前端模块，提供主机信息的集中查看和管理功能。

**功能特性**：

- 支持通过主机名或IP地址进行搜索
- 支持按主机分组进行筛选
- 显示主机名、IP地址、接口方式、CPU总量、内存总量、主机分组等信息
- 支持中英文界面国际化
- 响应式设计，适配不同屏幕尺寸

**文档链接**：[zabbix_cmdb/README.md](./zabbix_cmdb/README.md)

![项目截图](zabbix_cmdb/images/1.jpg)
![项目截图](zabbix_cmdb/images/2.jpg)

**兼容性**：Zabbix 6.0.x, 7.0.x（自动适配）

## 安装说明 / Installation

### ⚠️ 重要提示：根据Zabbix版本修改manifest.json

**在安装前，请根据您的Zabbix版本修改各模块的 `manifest.json` 文件：**

- **Zabbix 6.0**: 将 `"manifest_version": 2.0` 改为 `"manifest_version": 1.0`
- **Zabbix 7.0+**: 保持 `"manifest_version": 2.0` 不变

### 推荐方法：使用Git克隆安装所有模块（首选）

这是最简单快捷的安装方式，一次性部署所有模块：

1. **克隆项目到Zabbix模块目录**：

   ```bash
   cd /usr/share/zabbix/modules/
   git clone https://github.com/X-Mars/zabbix_modules.git .
   ```

   注意：命令末尾的 `.` 表示克隆到当前目录。

2. **如果使用Zabbix 6.0，修改manifest_version**：

   ```bash
   cd /usr/share/zabbix/modules/
   # 修改 zabbix_reports 模块
   sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_reports/manifest.json
   
   # 修改 zabbix_cmdb 模块
   sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_cmdb/manifest.json
   ```

   如果使用 Zabbix 7.0+，则无需修改，保持默认值即可。

### 启用模块

完成文件部署后，在Zabbix Web界面中启用模块：

1. 进入Zabbix Web界面
2. 导航到 **Administration → General → Modules**
3. 点击 **Scan directory** 扫描新模块
4. 找到并启用 "Zabbix Reports" 和 "Zabbix CMDB" 模块

![项目截图](zabbix_reports/images/setting-1.png)

### 验证安装

刷新页面后，您将在相应菜单中看到新模块：
- **Reports → Zabbix Reports** (每日/周/月报表)
- **Inventory → CMDB** (主机列表/主机分组)

### 单独安装模块

每个模块都有独立的安装说明，请参考各模块的README.md文件获取详细的安装和配置步骤。

Each module has independent installation instructions. Please refer to the README.md file of each module for detailed installation and configuration steps.

---

## English

## Description

This is a collection of Zabbix modules, where each module is an independent Zabbix extension that provides additional functionality to the Zabbix monitoring system.

## Module List

### 1. Zabbix Reports

**Description**: A frontend module for Zabbix 7.0 that generates daily, weekly, and monthly reports. It supports report preview, PDF export, and email push functionality.

**Features**:

- Support for daily, weekly, and monthly report generation
- Problem count and status statistics
- Display top problem hosts (Top 10)
- Display top CPU and memory utilization hosts (Top 10)
- In-page report preview
- Manual PDF export
- Email push reports (HTML format)

**Documentation**: [zabbix_reports/README.md](./zabbix_reports/README.md)

**Author**: 火星小刘  
**Version**: 1.1.0  
**Compatibility**: Zabbix 6.0.x, 7.0.x (Auto-adaptive)

### 2. Zabbix CMDB

**Description**: A frontend module for Zabbix 7.0 that provides Configuration Management Database (CMDB) functionality, offering centralized viewing and management of host information.

**Features**:

- Support searching by hostname or IP address
- Support filtering by host groups
- Display host name, IP address, interface type, CPU total, memory total, host groups
- Support for Chinese and English interfaces
- Responsive design that adapts to different screen sizes

**Documentation**: [zabbix_cmdb/README.md](./zabbix_cmdb/README.md)

**Author**: 火星小刘  
**Version**: 1.1.0  
**Compatibility**: Zabbix 6.0.x, 7.0.x (Auto-adaptive)

## Installation Instructions

### ⚠️ Important: Modify manifest.json Based on Your Zabbix Version

**Before installation, please modify the `manifest.json` file of each module according to your Zabbix version:**

- **Zabbix 6.0**: Change `"manifest_version": 2.0` to `"manifest_version": 1.0`
- **Zabbix 7.0+**: Keep `"manifest_version": 2.0` as default

### Recommended Method: Install All Modules Using Git Clone (Preferred)

This is the simplest and fastest way to deploy all modules at once:

1. **Clone the project to Zabbix modules directory**:

   ```bash
   cd /usr/share/zabbix/modules/
   git clone https://github.com/X-Mars/zabbix_modules.git .
   ```

   Note: The `.` at the end means clone to the current directory.

2. **For Zabbix 6.0, modify manifest_version**:

   ```bash
   cd /usr/share/zabbix/modules/
   # Modify zabbix_reports module
   sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_reports/manifest.json
   
   # Modify zabbix_cmdb module
   sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_cmdb/manifest.json
   ```

   For Zabbix 7.0+, no modification needed, keep the default value.

### Enable Modules

After deploying the files, enable the modules in Zabbix Web UI:

1. Go to Zabbix Web UI
2. Navigate to **Administration → General → Modules**
3. Click **Scan directory** to scan for new modules
4. Find and enable "Zabbix Reports" and "Zabbix CMDB" modules

### Verify Installation

After refreshing the page, you will see the new modules in the respective menus:
- **Reports → Zabbix Reports** (Daily/Weekly/Monthly reports)
- **Inventory → CMDB** (Host List/Host Groups)

### Install Individual Modules

Each module has independent installation instructions. Please refer to the README.md file of each module for detailed installation and configuration steps.

## 贡献 / Contributing

欢迎提交问题报告和功能改进建议。请在相应模块的目录下提交问题。

Issues and feature improvement suggestions are welcome. Please submit issues in the appropriate module directory.

## 许可证 / License

所有模块遵循Zabbix的许可证条款。详情请见 [Zabbix许可证](https://www.zabbix.com/license)。

All modules follow the Zabbix license terms. For details, see [Zabbix License](https://www.zabbix.com/license).
