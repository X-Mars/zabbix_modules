# Zabbix Rock - 数据中心机柜管理模块

## 功能介绍

Zabbix Rock 是一个用于数据中心机柜管理的 Zabbix 前端模块，提供以下功能：

### 核心功能

1. **机房管理**
   - 创建、编辑、删除机房
   - 机房描述信息

2. **机柜管理**
   - 创建、编辑、删除机柜
   - 配置机柜高度（支持 1-60U）
   - 关联机房

3. **机柜可视化**
   - 42U 机柜垂直布局展示
   - U 位占用状态实时显示
   - 主机信息悬停提示
   - 空闲 U 位点击分配

4. **主机分配**
   - 将 Zabbix 主机分配到机柜指定 U 位
   - 支持按主机组过滤
   - 支持主机名搜索
   - U 位冲突检测

5. **搜索功能**
   - 按机柜名称搜索
   - 按主机名称搜索
   - 快速定位主机位置

## 安装方法

1. 将 `zabbix_rock` 文件夹复制到 Zabbix 前端的 `modules` 目录
2. 确保 `data` 目录有写入权限
3. 登录 Zabbix 管理界面
4. 进入 **管理** → **通用** → **模块**
5. 点击 **扫描目录**
6. 找到 "Zabbix Rock" 模块并启用

## 使用说明

### 机柜配置

1. 进入 **资产记录** → **机柜配置**
2. 首先创建机房
3. 然后在机房下创建机柜

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
zabbix_rock/
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
