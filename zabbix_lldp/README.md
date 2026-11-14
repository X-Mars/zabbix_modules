# Zabbix LLDP 拓扑模块

[English](README_en.md)

## ✨ 版本兼容性

本模块兼容 Zabbix 6.0 / 7.0+ / 8.0+ 版本。

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x
- ✅ Zabbix 8.0.x

**兼容性说明**：模块内置智能版本检测机制，自动适配不同版本的 Zabbix API 和类库，无需手动配置。

## 描述

这是一个 Zabbix 前端模块，用于可视化 LLDP 邻居拓扑。模块读取关联 **Lldp Discovery** 模板的主机及其 `lldp.remote.info[*]` 监控项最新值，以 SVG 圆点与线段展示本地设备与对端设备的连接关系，悬停可查看端口详情。

模块在 Zabbix Web 的 **监测 (Monitoring)** 菜单下新增 **LLDP 拓扑** 子菜单。

## 功能特性

- **模板过滤**：仅展示关联 LLDP 模板的主机及其所在主机分组
- **分组 / 主机筛选**：支持「所有分组」「所有主机」或指定范围
- **按需查询**：页面初次加载不拉取拓扑，点击「查询」后加载
- **拓扑可视化**：自动分隔互不连通的网络，按根节点的汇聚分支划块，默认显示三层
- **悬停详情**：显示本地/对端设备名、端口及监控项更新时间
- **统计信息**：展示主机数、链路数、设备数及跳过项计数
- **可扩展设计**：节点 JSON 预留 `image` 字段，便于后续接入设备图标
- **国际化支持**：支持中英文界面

## 模板依赖

本模块依赖名为 **Lldp Discovery** 的 Zabbix 模板（可在配置中修改）。模板应通过 LLD 发现 `lldp.remote.info[{#LOCAL_PORT}]` 监控项，其 `lastvalue` 格式为：

```
对端设备名(对端端口)
```

例如：`YJZHZX-LouCengHuiJu-S7510E(GigabitEthernet2/2/0/1)`

请将模板关联到需要展示 LLDP 拓扑的主机，并确保 LLD 已发现且监控项有最新数据。

## 配置

模块配置保存在 `data/config.json`：

```json
{
    "template_name": "Lldp Discovery",
    "item_key_prefix": "lldp.remote.info",
    "discovery_key": "lldp_discovery"
}
```

| 字段 | 说明 |
|------|------|
| `template_name` | LLDP 模板可见名称（也支持按 technical name 匹配） |
| `item_key_prefix` | 监控项 key 前缀，用于查询 LLDP 邻居项 |
| `root_hostid` | 可选，指定全网拓扑的根设备 Zabbix 主机 ID。未配置或该设备不在查询结果中时自动选择。 |
| `discovery_key` | LLD 规则 key，预留供后续版本使用 |

## 安装步骤

### 安装模块

```bash
# Zabbix 6.0 / 7.0 部署方法
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/modules/

# Zabbix 7.4 / 8.0 部署方法
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

### ⚠️ 修改 manifest.json 文件

```bash
# ⚠️ 如果使用 Zabbix 6.0，修改 manifest_version
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_lldp/manifest.json
```

### 启用模块

1. 转到 **Administration → General → Modules**。
2. 点击 **Scan directory** 按钮扫描新模块。
3. 找到 "Zabbix LLDP Topology" 模块，点击启用。
4. 刷新页面，模块将在 **Monitoring** 菜单下显示为 **LLDP 拓扑**（或 **LLDP Topology**）。

## 使用方法

1. 进入 **Monitoring → LLDP 拓扑**。
2. 选择主机分组和主机（默认「所有分组」「所有主机」）。
3. 点击 **查询**，等待拓扑加载。
4. 点击节点旁的 `+N` 展开下一层，点击 `−` 收起。虚线表示布局主路径之外的链路。
5. 将鼠标悬停在链路或节点上，查看端口与设备详情。

## 注意事项

- 若未找到 LLDP 模板，请检查 `data/config.json` 中的 `template_name` 是否与 Zabbix 中模板名称一致。
- 空 `lastvalue` 或格式不符合 `设备(端口)` 的监控项会被跳过，并在统计中计入「跳过项」。
- 对端设备为 LLDP 上报的系统名，可能与 Zabbix 主机名不一致；同名对端会自动合并为一个节点。
- 当前版本不包含设备图标、力导向布局、双向链路合并及 `REMOTE_SYS_DESC` 展示。

## 开发

插件基于 Zabbix 模块框架开发。文件结构：

```
zabbix_lldp/
├── manifest.json
├── Module.php
├── data/config.json
├── actions/
│   ├── Lldp.php
│   └── LldpData.php
├── views/
│   └── lldp.php
└── lib/
    ├── LanguageManager.php
    ├── ViewRenderer.php
    ├── ZabbixVersion.php
    ├── ConfigManager.php
    ├── TemplateHostResolver.php
    └── LldpTopologyBuilder.php
```

## 许可证

本模块遵循 Zabbix 的许可证条款。详情请见 [Zabbix 许可证](https://www.zabbix.com/license)。
