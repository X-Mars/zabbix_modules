# Zabbix Rack 模块

[English](README_en.md)

## ✨ 版本兼容性

本模块兼容 Zabbix 6.0 和 7.0+ 版本。

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x

**兼容性说明**：模块内置智能版本检测机制，自动适配不同版本的 Zabbix API 和类库，无需手动配置。

## 描述

这是一个 Zabbix 前端模块，用于数据中心机柜可视化和主机位置管理。模块在 Zabbix Web 的资产记录菜单下新增机柜管理功能，支持机房和机柜的配置，以及主机的可视化分配。

## 功能特性

- **机房管理**：
  - 创建、编辑、删除机房
  - 机房描述信息管理
- **机柜管理**：
  - 创建、编辑、删除机柜
  - 配置机柜高度（支持 1-60U）
  - 关联机房
- **机柜可视化**：
  - 42U 机柜垂直布局展示
  - U 位占用状态实时显示
  - 主机信息悬停提示
  - 空闲 U 位点击分配
- **主机分配**：
  - 将 Zabbix 主机分配到机柜指定 U 位
  - 支持按主机组过滤
  - 支持主机名搜索
  - U 位冲突检测
- **搜索功能**：
  - 按机柜名称搜索
  - 按主机名称搜索
  - 快速定位主机位置
- **国际化支持**：支持中英文界面
- **响应式设计**：适配不同屏幕尺寸

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
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_rack/manifest.json
```

### 启用模块

1. 转到 **Administration → General → Modules**。
2. 点击 **Scan directory** 按钮扫描新模块。
3. 找到 "Zabbix Rack" 模块，点击启用模块。
4. 刷新页面，模块将在 **Inventory** 菜单下显示为 "机柜管理" 子菜单。

## 注意事项

- **性能考虑**：对于大型环境，建议适当限制查询结果数量。
- **数据准确性**：显示的信息基于Zabbix数据库的当前状态。
- **权限要求**：用户需要适当权限访问机柜管理功能。

## 开发

插件基于Zabbix模块框架开发。文件结构：

- `manifest.json`：模块配置
- `Module.php`：菜单注册
- `actions/RackManage.php`：机柜管理业务逻辑处理
- `actions/RackView.php`：机柜视图业务逻辑处理
- `views/rack.manage.php`：机柜管理页面视图
- `views/rack.view.php`：机柜视图页面视图
- `lib/LanguageManager.php`：国际化语言管理
- `lib/ViewRenderer.php`：视图渲染工具
- `lib/ZabbixVersion.php`：版本兼容工具

如需扩展，可参考[Zabbix模块开发文档](https://www.zabbix.com/documentation/7.0/en/devel/modules)。

## 许可证

本项目遵循Zabbix的许可证。详情请见[Zabbix许可证](https://www.zabbix.com/license)。

## 贡献

欢迎提交问题和改进建议。

### 机柜视图

1. 进入 **资产记录** → **机柜管理**
2. 选择机房和机柜
3. 查看机柜 U 位使用情况
4. 点击空闲 U 位可添加主机
5. 悬停在已占用 U 位上可查看主机详情

### 分配主机

1. 点击空闲的 U 位
2. 在弹窗中选择主机组（可选）
3. 搜索要添加的主机
4. 选择主机并设置 U 位范围
5. 点击确认完成分配

## 数据存储

- **机房/机柜配置**：存储在 `data/config.json` 文件中
- **主机位置信息**：通过 Zabbix 主机标签存储
  - `rack_room`: 机房名称
  - `rack_name`: 机柜名称
  - `rack_u_start`: 起始 U 位
  - `rack_u_end`: 结束 U 位

## 兼容性

- Zabbix 6.0.x
- Zabbix 7.0.x
- Zabbix 7.4.x

## 国际化支持

- 简体中文
- English

根据 Zabbix 用户界面语言自动切换。

## 目录结构

```
zabbix_rack/
├── manifest.json       # 模块清单
├── Module.php          # 模块入口
├── README.md           # 说明文档
├── actions/            # 控制器
│   ├── RackView.php    # 机柜视图
│   ├── RackManage.php  # 机柜管理
│   ├── RoomSave.php    # 保存机房
│   ├── RoomDelete.php  # 删除机房
│   ├── RackSave.php    # 保存机柜
│   ├── RackDelete.php  # 删除机柜
│   ├── HostAssign.php  # 分配主机
│   ├── HostRemove.php  # 移除主机
│   └── HostsGet.php    # 获取主机列表
├── lib/                # 库文件
│   ├── LanguageManager.php    # 多语言支持
│   ├── ViewRenderer.php       # 视图渲染
│   ├── ZabbixVersion.php      # 版本兼容
│   ├── RackConfig.php         # 机柜配置管理
│   └── HostRackManager.php    # 主机机柜关联
├── views/              # 视图文件
│   ├── rack.view.php   # 机柜视图页面
│   └── rack.manage.php # 机柜管理页面
└── data/               # 数据存储
    └── config.json     # 配置数据
```

## 权限要求

用户需要具有 **监控 → 主机** 的访问权限才能使用本模块。

## 注意事项

1. 删除机房会同时删除该机房下的所有机柜配置
2. 从机柜移除主机只会删除主机上的机柜相关标签，不会删除主机本身
3. 分配主机时会自动检测 U 位冲突
4. 建议定期备份 `data/config.json` 文件

## 更新日志

### v1.0.0
- 初始版本
- 机房/机柜管理功能
- 机柜可视化展示
- 主机分配功能
- 搜索功能
- 中英文支持
