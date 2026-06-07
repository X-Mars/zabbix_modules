# Zabbix JumpServer 模块

[English](README_en.md)

## ✨ 版本兼容性

本模块兼容 Zabbix 6.0 / 7.0+ / 8.0+ 版本。

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x
- ✅ Zabbix 8.0.x

**兼容性说明**：模块内置智能版本检测机制，自动适配不同版本的 Zabbix API 和类库，无需手动配置。

## 描述

这是一个 Zabbix 前端模块，用于将 Zabbix 中的主机和主机分组同步（推送）到 JumpServer 堡垒机，并支持从 Zabbix 页面一键跳转到 JumpServer 连接对应主机。模块在 Zabbix Web 的 **资产记录** 菜单下新增 **JumpServer** 菜单。

推送主机时会自动：

- 在 JumpServer 中按 Zabbix 主机分组创建对应节点（不存在则自动创建）
- 根据 Zabbix「Operating system」监控项识别主机平台（Linux / Windows）
- 创建或更新 JumpServer 资产，并把 JumpServer 资产 ID 以标记（Tag）的形式写回 Zabbix 主机，供「连接」按钮使用

![1](zabbix_jumpserver/images/1.png)

## 功能特性

- **分组 / 主机下拉筛选**：页面顶部提供主机分组下拉框和主机下拉框，默认展示所有主机分组和所有主机
- **告警状态筛选**：按严重度（灾难、严重、一般严重、警告、信息、未分类、正常）过滤主机，默认展示所有状态
- **IP / 主机名搜索**：支持按 IP 地址或主机名快速搜索
- **推送所有主机组**：一键把 Zabbix 所有主机分组推送为 JumpServer 节点，缺失的自动创建
- **推送所有主机**：一键把 Zabbix 所有主机推送为 JumpServer 资产（自动归属节点、识别平台）
- **获取 JumpServer 资产 ID**：从 JumpServer 拉取全部资产，按 IP 匹配 Zabbix 本地主机，并将资产 ID 写回主机标记
- **单主机推送 / 重新推送**：未推送主机可单独推送；已推送主机悬停状态徽章可重新推送
- **告警状态展示**：表格「告警状态」列按严重度分别显示数量（如「严重 2」「警告 1」），无告警显示「正常」
- **告警明细展开**：点击行首展开按钮，查看该主机当前所有告警（严重度、告警名称、时间）
- **一键连接**：已推送的主机在表格最后一列显示「连接」按钮，点击跳转到 JumpServer 该主机的连接页面
- **资产 ID 持久化**：JumpServer 资产 ID 以 Zabbix 主机标记（`jumpserver_asset_id`）形式保存
- **国际化支持**：支持中英文界面

## 配置

模块凭据保存在 `data/config.json` 中：

```json
{
    "jumpserver_url": "http://192.168.3.29",
    "access_key_id": "<AccessKeyID>",
    "access_key_secret": "<AccessKeySecret>",
    "org_id": "00000000-0000-0000-0000-000000000002",
    "connect_url_template": "{base_url}/luna/connect?asset={asset_id}",
    "account_template_id": "",
    "verify_ssl": false
}
```

| 字段 | 说明 |
|------|------|
| `jumpserver_url` | JumpServer 访问地址 |
| `access_key_id` | JumpServer 用户 AccessKey ID |
| `access_key_secret` | JumpServer 用户 AccessKey Secret |
| `org_id` | 组织 ID，默认 DEFAULT 组织 |
| `connect_url_template` | 连接地址模板，`{base_url}` 与 `{asset_id}` 会被替换 |
| `account_template_id` | 账号模板 ID，留空则不自动关联账号 |
| `verify_ssl` | 是否校验 HTTPS 证书 |

**获取 AccessKey**：登录 JumpServer → 用户界面 → 个人信息 → API Key（AccessKey），创建后获得 ID 和 Secret。模块使用 HTTP Signature（hmac-sha256）方式认证，参考 [JumpServer REST API 文档](https://docs.jumpserver.org/zh/v4/dev/rest_api/)。

### 自动关联登录账号

配置 `account_template_id` 后，模块会在**创建** JumpServer 资产时，通过账号模板自动关联登录账号（更新已有资产时不会改动账号）：

1. 在 JumpServer 中预先创建**账号模板**（账号管理 → 账号模板），填写用户名与密码或 SSH 密钥。
2. 复制该账号模板的 ID，填入 `data/config.json` 的 `account_template_id`。
3. 推送主机时，模块仅在**新建资产**的请求（`POST /api/v1/assets/hosts/`）的 `accounts` 字段中内嵌该模板（`[{"template": "<模板ID>"}]`），JumpServer 会按模板自动填充用户名/密钥并关联账号。

说明：

- **仅创建时关联**：更新已有资产时不携带 `accounts` 字段，避免影响 JumpServer 上已有关联账号。
- AccessKey 对应的用户需要具备账号管理（`accounts.add_account`）权限。
- 账号凭据由模板决定；如需 JumpServer 真正到目标主机上创建/改密系统账号，请在账号模板中开启自动推送并配置特权账号。

### 反向同步资产 ID

点击「获取 JumpServer 资产 ID」按钮，模块会：

1. 从 JumpServer 拉取全部主机资产（`/api/v1/assets/hosts/`）。
2. 按资产 `address`（IP）与 Zabbix 主机主接口 IP 进行匹配。
3. 将匹配到的资产 ID 写入对应 Zabbix 主机的 `jumpserver_asset_id` 标记（已有相同 ID 则跳过）。

适用于 JumpServer 上已有资产、但 Zabbix 主机尚未写入资产 ID 标记的场景。

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
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_jumpserver/manifest.json
```

### 配置凭据

编辑 `zabbix_jumpserver/data/config.json`，填写 JumpServer 地址与 AccessKey。请确保 Zabbix Web 运行用户对该文件有读取权限。

### 启用模块

1. 转到 **Administration → General → Modules**。
2. 点击 **Scan directory** 按钮扫描新模块。
3. 找到 "Zabbix JumpServer" 模块，点击启用模块。
4. 刷新页面，模块将在 **资产记录** 菜单下显示为 "JumpServer"。

## 注意事项

- **依赖网络**：Zabbix Web 服务器需能访问 JumpServer 地址。
- **权限要求**：查看页面需要普通用户权限；执行推送、获取资产 ID 需要 Zabbix 管理员（Admin）权限。
- **平台识别**：主机平台依赖 Zabbix「Operating system」监控项的值，缺失时默认按 Linux 处理。
- **标记写回**：推送主机后会向 Zabbix 主机写入 `jumpserver_asset_id` 标记，已有的其它标记会被保留。
- **凭据安全**：`data/config.json` 含敏感凭据，请妥善控制文件权限，避免提交到公开仓库。

## 开发

插件基于 Zabbix 模块框架开发。文件结构：

- `manifest.json`：模块配置
- `Module.php`：菜单注册（资产记录 → JumpServer）
- `actions/Jumpserver.php`：主页面业务逻辑（筛选、搜索、告警统计、主机表格）
- `actions/JumpserverPush.php`：推送主机/分组并回写资产 ID
- `actions/JumpserverFetchIds.php`：从 JumpServer 拉取资产 ID 并按 IP 写回标记
- `views/jumpserver.php`：页面视图
- `lib/JumpserverClient.php`：JumpServer API 客户端（HTTP Signature 认证）
- `lib/ConfigManager.php`：读取 data/config.json 凭据
- `lib/LanguageManager.php`：国际化语言管理
- `lib/ViewRenderer.php`：视图渲染工具
- `lib/ZabbixVersion.php`：版本兼容工具
- `data/config.json`：JumpServer 连接凭据

如需扩展，可参考[Zabbix模块开发文档](https://www.zabbix.com/documentation/7.0/en/devel/modules)。

## 许可证

本项目遵循Zabbix的许可证。详情请见[Zabbix许可证](https://www.zabbix.com/license)。
