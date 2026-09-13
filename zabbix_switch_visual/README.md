# Zabbix Switch Visual

[English](README_en.md)

## 版本兼容性

当前代码使用 `manifest_version: 2.0` 的 Zabbix 自定义仪表盘组件框架，主要面向 **Zabbix 7.x**。代码包含对 7.4 颜色选择器变化的处理，但尚未提供逐版本的安装和交互测试记录。

| Zabbix 版本 | 当前状态 |
| --- | --- |
| 6.0 / 6.2 | 尚不兼容。旧版仪表盘组件的注册方式不同，仅将 `manifest_version` 改为 `1.0` 不足以安装本组件。 |
| 6.4 | 尚未完成适配和验证；当前编辑表单使用的折叠字段组及前端生命周期方法可能需要调整。 |
| 7.0–7.4 | 当前代码的目标版本范围；部署前建议在对应小版本验证表单、端口点击和数据显示。 |
| 8.0 | 尚未验证；8.0 仍处于开发阶段时，其前端接口可能变化。 |

如需 6.0–8.0 全版本兼容，需要为 6.0/6.2 单独实现旧版组件入口，并对 6.4、8.0 做实际适配；不能仅复制 [Zabbix CMDB](../zabbix_cmdb/README.md) 的 `manifest_version` 修改步骤。

## 描述

Zabbix Switch Visual 是一个交换机端口监控仪表盘组件。它根据 Zabbix 监控项生成交换机机箱、RJ45/SFP 端口、状态、流量和汇总信息的可视化面板。界面支持中文和英文。

![Zabbix Switch Visual 仪表盘示例](images/1.png)

## 功能特性

- **主机选择**：可选择单台主机，或按主机群组展示多台交换机。
- **端口可视化**：展示 RJ45/SFP 端口、在线状态、速率、入/出流量、错误及告警；点击端口可跳转到相关监控数据。
- **端口布局**：支持手动配置或自动检测端口数量、堆叠成员、端口行序、SNMP 索引偏移、排除规则和手动别名。
- **趋势与汇总**：展示端口和整机流量微型趋势图，以及运行时间、型号、序列号、CPU、内存、温度、PoE 和风扇信息；可选指标仅在配置了对应监控项后显示。
- **外观设置**：可调整端口样式、颜色、缩放比例和汇总栏显示。
- **中英文界面**：根据 Zabbix 用户语言显示简体中文或英文。

## 安装步骤

1. 将本仓库的 `zabbix_switch_visual` **整个目录**复制到 Zabbix 前端的 `modules` 目录。前端安装位置因发行版而异，例如 `/usr/share/zabbix/ui/modules/`；以实际安装目录为准。
2. 在 Zabbix 前端进入 **管理 → 常规 → 模块**（Administration → General → Modules），点击 **扫描目录**并启用 **Zabbix Switch Visual**。
3. 打开仪表盘的编辑模式，添加 **Zabbix Switch Visual** 组件，选择主机或主机群组后保存。

请保持目录名为 `zabbix_switch_visual`，并保留目录内的 `manifest.json`、`Widget.php`、`actions/`、`includes/`、`views/` 和 `assets/`。当前版本不要将 `manifest_version` 改为 `1.0` 后用于 6.0/6.2。

## 监控项配置

组件通过监控项 Key 模式匹配端口数据。默认值如下；`*` 代表端口索引部分，应按实际模板调整。

| 数据 | 默认 Key 模式 |
| --- | --- |
| 入流量 | `ifInOctets[*]` |
| 出流量 | `ifOutOctets[*]` |
| 端口状态 | `ifOperStatus[*]` |
| 端口速率 | `ifHighSpeed[*]` |
| 入错误 | `ifInErrors[*]` |
| 出错误 | `ifOutErrors[*]` |

默认勾选“流量监控项单位为比特/秒”。如果模板的监控项实际提供字节/秒，请取消该选项；如果监控项是原始累计计数器，需先在 Zabbix 中配置速率换算。PoE、双工、SFP 光功率及系统信息的 Key 均为可选项，空值不会显示相应数据。

自动检测可能匹配到 VLAN、隧道或管理接口，可使用“排除端口”规则过滤。大型主机群组会产生更多 Zabbix API 查询，建议先用单台交换机验证监控项模式和端口布局。

## 项目结构

- `manifest.json`：组件标识、动作和前端资源注册。
- `Widget.php`、`includes/WidgetForm.php`：组件名称及配置字段。
- `actions/WidgetView.php`、`includes/DataFetcher.php`：主机与监控项数据获取。
- `views/widget.edit.php`、`views/widget.view.php`：配置表单和端口可视化视图。
- `assets/js/class.widget.js`、`assets/css/switch_monitor.css`：前端交互和样式。
- `includes/Translation.php`：中英文文案。

组件开发接口可参考 [Zabbix 官方文档](https://www.zabbix.com/documentation/7.0/en/devel/modules/widgets)。
