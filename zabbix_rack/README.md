# Zabbix Rack 模块

[English](README_en.md)

## ✨ 版本兼容性

本模块兼容 Zabbix 6.0 / 7.0+ / 8.0+ 版本。

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x
- ✅ Zabbix 8.0.x

**兼容性说明**：模块内置智能版本检测机制，自动适配不同版本的 Zabbix API 和类库，无需手动配置。

## 描述

这是一个 Zabbix 前端模块，用于数据中心机柜可视化和主机位置管理。模块在 Zabbix Web 的资产记录菜单下新增机柜管理功能，支持机房和机柜的配置，以及主机的可视化分配。

![1](images/1.png)
![2](images/2.png)
![3](images/3.png)

## 功能特性

- **机房管理**：
  - 创建、编辑、删除机房
  - 机房描述信息管理
  - 为机房分配 Zabbix 用户组和用户（访问控制）
- **访问权限**：
  - 机房可关联 Zabbix 用户组与具体用户
  - 配置权限时默认选中全部用户组与用户（表示全员可见）
  - 机柜视图仅展示当前用户有权限的机房/机柜
  - 未配置用户组与用户的机房对所有用户可见
  - **Super Admin** 在机柜视图中可查看全部机房，不受机房权限限制
- **机柜配置（管理页）**：
  - 仅 **Super Admin** 可访问机柜配置页面及相关保存/删除接口
  - 普通用户仅可使用机柜视图
- **机柜管理**：
  - 创建、编辑、删除机柜
  - 配置机柜高度（支持 1-60U）
  - 关联机房
- **机柜可视化**：
  - 42U 机柜垂直布局展示
  - 支持**正面 / 背面**视图切换（同一 U 位正反面可独立放置主机）
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

# Zabbix 7.4 / 8.0 部署方法
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
- **权限要求**：用户需要适当权限访问机柜视图。机柜视图按机房配置的 Zabbix 用户组/用户过滤可见范围；**Super Admin** 可查看全部机房。**机柜配置**页面仅 **Super Admin** 可访问。

### 机房访问权限（config.json）

在 `data/config.json` 的机房条目中，可**可选**增加 `user_groups`、`users` 字段（字符串 ID 数组）。未配置时与旧版完全兼容，机房对所有用户可见：

```json
{
    "id": "room1",
    "name": "Room 1",
    "description": "Test Room",
    "user_groups": ["7"],
    "users": ["1"]
}
```

- `user_groups`：允许访问的 Zabbix 用户组 ID 列表
- `users`：允许访问的 Zabbix 用户 ID 列表
- 两者均为空或未设置：全员可见
- 任一匹配即可访问（用户组 **或** 用户）
- 配置页保存时若选中全部用户组 **且** 全部用户，将自动规范化为全员可见（不写权限字段）

### 机柜正/背面（主机标签）

主机分配到机柜时，可通过 Zabbix 主机标签区分正/背面：

| 标签名 | 值 | 说明 |
|--------|-----|------|
| `rack_side` | `back` | 机柜**背面**；未设置或非 `back` 均视为**正面** |

- 机柜视图顶部可切换「正面 / 背面」，同一 U 位在正反面可分别放置不同主机
- 分配到正面时不写入 `rack_side` 标签（兼容旧数据）
- 分配到背面时写入 `rack_side=back`

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
- `lib/RackConfig.php`：机柜配置管理
- `lib/RackPermission.php`：机房访问权限（Zabbix 用户组/用户）
- `lib/HostRackManager.php`：主机机柜关联管理

如需扩展，可参考[Zabbix模块开发文档](https://www.zabbix.com/documentation/7.0/en/devel/modules)。

## 许可证

本项目遵循Zabbix的许可证。详情请见[Zabbix许可证](https://www.zabbix.com/license)。

## 注意事项

1. 删除机房会同时删除该机房下的所有机柜配置
2. 从机柜移除主机只会删除主机上的机柜相关标签，不会删除主机本身
3. 分配主机时会自动检测 U 位冲突
4. 建议定期备份 `data/config.json` 文件
