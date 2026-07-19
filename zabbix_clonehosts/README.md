# Zabbix Clonehosts 模块

[English](README_en.md)

## ✨ 版本兼容性

本模块兼容 Zabbix 6.0 / 7.0+ / 8.0+ 版本。

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x
- ✅ Zabbix 8.0.x

**兼容性说明**：模块内置智能版本检测机制（`CompatHelper`），自动适配不同版本的 Zabbix API 参数差异（如 6.0 的 `selectGroups` 与 6.4+ 的 `selectHostGroups`、`groups` 与 `hostgroups` 结果键差异、`Zabbix\Core\CModule` 与 `Core\CModule` 命名空间差异等），无需手动配置。语言助手 `LangHelper` 会自动跟随 Zabbix 系统语言设置切换中/英文界面。

## 描述

这是一个 Zabbix 前端模块，用于基于已有监控主机的配置批量克隆导入大量主机。模块在 Zabbix Web 的「数据采集（Data collection）」菜单下新增「主机批量导入」菜单项（位于「主机」之后），支持 CSV 文件导入和在线表格录入两种方式，并提供预览、冲突检测和实时导入进度反馈功能。

![1](images/image.png)
![2](images/image-1.png)
![3](images/image-2.png)
![4](images/image-3.png)

## 功能特性

- **源主机克隆**：选择任意已有监控主机作为克隆模板，其全部配置（接口、群组、模板、标签、宏、TLS、IPMI、资产模式等）均可被继承

- **双模式数据录入**：
  - CSV 文件上传：支持 UTF-8 和 GBK 编码，自动表头检测和编码识别，可下载 CSV 模板
  - 在线表格录入：支持添加/删除行、清空全部，数据实时校验

- **智能字段继承**：仅主机名称和接口 IP 为必填项，其他字段（可见名称、端口、主机群组、模板、标签、宏、描述）留空时自动继承源主机配置

- **主机群组自动创建**：导入时 CSV 中指定的主机群组若不存在，自动调用 API 创建后再关联

- **预览与冲突检测**：导入前全面预览，自动检测主机名冲突、必填字段缺失、批次内重复、与已有主机/模板同名冲突等问题；并以状态标识区分「已存在直接关联」「将新建」「未找到」「继承自源主机」

- **选择性导入**：预览页支持勾选/取消要导入的主机，冲突主机自动跳过，就绪主机可单独取消导入

- **返回编辑**：预览页可一键带着数据返回在线表格录入页，方便对非就绪主机进行编辑，不会清空已录入数据

- **导入进度反馈**：逐台 AJAX 创建主机，实时进度条和成功/失败计数

- **结果报告导出**：导入完成后可下载 CSV 格式的结果报告（含主机名、IP、主机ID、结果、错误信息）

- **中英文双语支持**：界面语言自动跟随 Zabbix 系统设置（`zh_CN` / `en_GB`），无需 gettext 依赖

- **响应式设计**：适配不同屏幕尺寸

- **现代化界面**：遵循 Zabbix 原生设计风格

## 安装步骤

### 安装模块

```bash
# Zabbix 6.0 / 7.0 部署方法
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/modules/

# Zabbix 7.2+ / 7.4 / 8.0 部署方法
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

### ⚠️ 修改 manifest.json 文件

```bash
# ⚠️ 如果使用Zabbix 6.0，修改manifest_version
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_clonehosts/manifest.json
```

### 启用模块

1. 转到 **Administration → General → Modules**。
2. 点击 **Scan directory** 按钮扫描新模块。
3. 找到「Host Batch Import / 主机批量导入」模块，点击启用模块。
4. 刷新页面，模块将在 **Data collection（数据采集）** 菜单下显示为「主机批量导入」，位于「Hosts（主机）」之后。

## CSV 数据格式

模块附带模板文件 `clonehosts_template.csv`，可在导入页面点击「下载 CSV 模板」获取。CSV 表头及字段说明：

| 字段 | 必填 | 说明 |
| --- | --- | --- |
| 主机名称(*) | 是 | 主机标识，需全局唯一（与模板共享命名空间） |
| 可见的名称 | 否 | 留空时使用主机名称 |
| 接口IP(*) | 是 | 主机接口 IP 地址 |
| 端口 | 否 | 留空时继承源主机端口 |
| 主机群组 | 否 | 多个群组用 `;` 分隔，不存在时自动创建 |
| 模板 | 否 | 多个模板用 `;` 分隔，按 host 或 name 匹配 |
| 标签 | 否 | 格式 `tag=value`，多个用 `;` 分隔 |
| 宏 | 否 | 格式 `{$MACRO}=value`，多个用 `;` 分隔 |
| 描述 | 否 | 主机描述信息 |

示例：

```csv
主机名称(*),可见的名称,接口IP(*),端口,主机群组,模板,标签,宏,描述
web-server-01,Web Server 01,192.168.1.10,10050,Servers;Web Servers,Linux by Zabbix Agent;Nginx by HTTP,env=prod;os=linux,{$SNMP_COMMUNITY}=public,Web server 01
db-server-01,DB Server 01,192.168.1.20,10050,Servers;Database Servers,MySQL by Zabbix Agent,env=prod;os=linux;role=db,{$MYSQL_PORT}=3306,Database server 01
```

## 注意事项

- **性能考虑**：导入采用逐台串行方式，大批量导入可能需要较长时间。建议单次导入不超过 500 台主机。
- **主机名唯一性**：主机名在 Zabbix 中与模板名共享命名空间，必须全局唯一。预览阶段会同时检测主机和模板冲突。
- **模板依赖**：CSV 中指定的模板必须在 Zabbix 中已存在，否则导入时仅关联已存在的模板（未找到的模板会被标记为「未找到」）。
- **CSV 编码**：建议使用 UTF-8 编码保存 CSV 文件。若中文显示乱码，可在页面切换编码为 GBK 后重新解析。
- **权限要求**：使用本模块需要 Zabbix 管理员（Zabbix Admin）及以上权限。
- **数据准确性**：创建的监控主机基于源主机的当前配置快照。如果源主机在导入过程中被修改，已创建的主机不会受影响。

## 开发

模块基于 Zabbix 模块框架开发。文件结构：

- `manifest.json`：模块配置、路由和静态资源声明
- `Module.php`：菜单注册（兼容 `Zabbix\Core\CModule` 与 `Core\CModule`）
- `CompatHelper.php`：Zabbix 6.0/6.4/7.x/8.x API 兼容性辅助类
- `LangHelper.php`：中英文国际化语言管理（纯 PHP 数组实现，无 gettext 依赖）
- `actions/Clonehosts.php`：主页面控制器（源主机选择、CSV 上传、表格录入）
- `actions/ClonehostsSource.php`：AJAX 源主机配置加载接口
- `actions/ClonehostsPreview.php`：预览页面控制器（冲突检测、字段状态）
- `actions/ClonehostsImport.php`：AJAX 导入接口（逐台创建主机）
- `views/`：页面视图（主页面、预览、JSON 响应）
- `assets/js/`：JavaScript（CSV 解析、表格管理、AJAX 导入进度）
- `assets/css/`：模块样式表
- `clonehosts_template.csv`：CSV 导入模板

如需扩展，可参考 [Zabbix 模块开发文档](https://www.zabbix.com/documentation/current/zh/devel/modules/file_structure)。

## 许可证

本项目遵循 GPL-2.0 许可证。
