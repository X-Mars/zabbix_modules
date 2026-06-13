# IM同步助手

[English](README_en.md)

## ✨ 版本兼容性

本模块兼容 Zabbix 6.0 / 7.0+ / 8.0+ 版本。

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x
- ✅ Zabbix 8.0.x

**兼容性说明**：模块内置智能版本检测机制，自动适配不同版本的 Zabbix API 和类库，无需手动配置。

## 描述

这是一个 Zabbix 前端模块，用于将**企业微信**、**飞书**、**钉钉**的部门架构同步到 Zabbix **用户组（User groups）**，并将各部门下的成员匹配到对应用户组中。

模块在 Zabbix Web 的 **Users** 菜单下新增 **IM同步助手** 子菜单。

![1](images/1.png)
![2](images/2.png)
![3](images/3.png)

## 功能特性

- **多平台支持**：企业微信（WeCom）、飞书（Feishu）、钉钉（DingTalk）
- **多套同步设置**：Web 界面管理多套 IM 凭据（**Users → 同步设置**），同一时间仅启用一套
- **部门同步**：按 IM 部门名称创建/更新 Zabbix 用户组
- **用户同步**：将部门成员匹配到 Zabbix 已有用户并加入对应用户组；支持自动创建用户
- **钉钉用户名策略**：优先手机号；无手机号时将姓名转为拼音（如「张三」→ `zhangsan`）；仍无法生成时回退 `userid`
- **灵活匹配**：支持按用户名、邮箱、别名匹配 Zabbix 用户（当前固定为 username）
- **预览模式**：同步前预览部门与用户匹配情况
- **路径模式**：可选使用部门完整路径作为用户组名称
- **国际化支持**：支持中英文界面

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
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_im/manifest.json
```

### 配置 IM 数据源

推荐在 Web 界面管理凭据：进入 **Users → 同步设置**（需 **Super Admin** 权限）。

- 点击「添加同步设置」，选择平台（企业微信 / 飞书 / 钉钉），填写对应的密钥、根部门 ID 后保存；列表以表格形式展示所有设置。
- **同一时间只能启用一个设置**：启用某个设置会自动停用其它设置；**IM 同步** 页面始终使用当前已启用的设置。
- 编辑时机密字段（Secret）默认留空，表示**保持原值不变**；只有填写新值才会覆盖。
- 全部凭据持久化保存在 `zabbix_im/data/config.json` 的 `settings` 列表中，无需手动编辑文件，也无需重启服务。

`config.json` 结构示意（`settings` 由界面维护，全局选项可手工调整）：

```json
{
    "use_full_path": false,
    "path_separator": "/",
    "username_lowercase": true,
    "remove_orphans": true,
    "remove_orphan_users": true,
    "auto_create_users": true,
    "auto_update_users": true,
    "default_roleid": "",
    "default_user_type": 2,
    "verify_ssl": true,
    "settings": [
        {
            "id": "set_default_wecom",
            "name": "企业微信",
            "provider": "wecom",
            "enabled": true,
            "root_department_id": "1",
            "corp_id": "your_corp_id",
            "corp_secret": "your_corp_secret",
            "app_id": "",
            "app_secret": "",
            "app_key": ""
        }
    ]
}
```

> 兼容旧版：若 `config.json` 仍是旧格式（顶层 `provider` + `wecom`/`feishu`/`dingtalk` 段落），模块会在加载时自动迁移为 `settings` 列表，原 `provider` 对应的平台默认设为启用。

#### 配置说明

> 下表中 `provider` 与各平台凭据现已通过「同步设置」界面填写，无需手工编辑；其余全局选项仍可在 `config.json` 顶层调整。

**通用选项**

| 字段 | 类型 | 说明 |
|------|------|------|
| `settings[].provider` | string | 数据源：`wecom`（企业微信）/ `feishu`（飞书）/ `dingtalk`（钉钉）。在「同步设置」界面按平台填写，仅启用项生效。 |
| `use_full_path` | bool | 是否用 IM 部门完整路径作为 Zabbix 用户组名称。`false` 时仅用当前部门名（如「研发一部」）；`true` 时用路径（如「研发中心/研发一部」），分隔符见 `path_separator`。 |
| `path_separator` | string | 部门路径分隔符，默认 `/`，仅 `use_full_path` 为 `true` 时生效。 |
| `username_lowercase` | bool | 匹配/创建 Zabbix 用户时是否将用户名转为小写（企业微信 userid 常含大小写）。 |
| `remove_orphans` | bool | IM 中已消失的部门，是否在同步时删除**模块创建/管理**的用户组。手工或系统自带用户组不会被删除。 |
| `remove_orphan_users` | bool | IM 中已消失的用户，是否在同步时删除**模块自动创建**的 Zabbix 用户。仅删除 `origin=created` 的账号；匹配到已有用户（`linked`）不会删除。 |
| `auto_create_users` | bool | IM 用户在 Zabbix 不存在时是否自动创建。需 **Super Admin** 执行「同步所有用户」；Zabbix 7+ 需配置 `default_roleid`。 |
| `auto_update_users` | bool | 是否更新模块自动创建用户的姓名、邮箱等信息。`linked` 用户不会被修改。 |
| `default_roleid` | string | Zabbix 7.0+ 自动创建用户时的角色 ID（在 **Users → User roles** 查看 `roleid`）。留空时模块会尝试自动选择名称含 `user` 的非管理员角色，建议显式填写。 |
| `default_user_type` | int | Zabbix 6.x 自动创建用户时的用户类型：`1` 用户 / `2` 管理员 / `3` 超级管理员（一般填 `1` 或 `2`）。 |
| `verify_ssl` | bool | 访问 IM 开放平台 API 时是否校验 HTTPS 证书。内网代理或自签证书环境可设为 `false`。 |

#### 各平台 Zabbix 用户名规则

模块固定按 **username** 匹配/创建 Zabbix 用户，各平台 `username` 来源如下：

| 平台 | Zabbix 用户名来源 | 说明 |
|------|------------------|------|
| 企业微信 | `userid` | 与企业管理后台通讯录账号一致 |
| 飞书 | `user_id` | 需开通 `contact:user.employee_id:readonly`；未开通时回退 `open_id` |
| 钉钉 | `mobile` → 姓名拼音 → `userid` | 无手机号时将 `name` 转为小写拼音（内置 GB2312 映射）；英文姓名直接使用；生僻字或转换失败时回退 `userid` |

> 钉钉中文转拼音依赖 PHP 的 `iconv` 或 `mbstring` 扩展（Zabbix 环境通常已具备）。若多人同名且无手机号，可能产生相同拼音用户名，需留意 Zabbix 侧冲突。

**企业微信 `wecom`（`provider = wecom` 时填写）**

本模块通过**企业自建应用**调用企业微信通讯录 API，使用 `access_token`（由 `corp_id` + 应用 `corp_secret` 换取）拉取部门与用户。官方文档入口：[企业微信开发者中心](https://developer.work.weixin.qq.com/document/)。

| 字段 | 说明 |
|------|------|
| `corp_id` | 企业 ID（CorpID）。管理后台 → **我的企业 → 企业信息 → 企业 ID** |
| `corp_secret` | 自建应用的 **Secret**（应用凭证密钥）。管理后台 → **应用管理 → 应用 → 自建** → 进入目标应用 → 查看 Secret |
| `root_department_id` | 同步根部门 ID。填 `1` 通常表示从企业根部门开始同步该部门及其全部子部门；也可填写具体部门 ID，仅同步该部门子树 |

> **重要**：请使用**自建应用 Secret**，不要使用「管理工具 → 通讯录同步」的通讯录同步 Secret。自 2022 年 8 月 15 日起，通讯录同步助手对新 IP 调用成员/部门详情接口已受限（错误码 `48009`），详见[通讯录同步接口调整说明](https://developer.work.weixin.qq.com/document/path/90193)。

#### 企业微信应用创建与配置步骤

1. **创建企业自建应用**  
   登录 [企业微信管理后台](https://work.weixin.qq.com/wework_admin/frame)，进入 **应用管理 → 应用 → 自建 → 创建应用**。本模块只需服务端 API 拉取通讯录，无需配置网页授权、回调 URL 等客户端能力（除非后续需要 OAuth 获取敏感字段）。

2. **获取凭据**  
   - `corp_id`：**我的企业 → 企业信息 → 企业 ID**  
   - `corp_secret`：**应用管理 → 自建 → 目标应用 → Secret**  
   将两者填入 Zabbix **Users → 同步设置** 对应字段。

3. **配置应用可见范围（关键）**  
   自建应用**仅能读取可见范围内的通讯录**，可见范围同时决定 API 可访问的部门与成员范围。详见[基本概念介绍 - 应用可见范围](https://developer.work.weixin.qq.com/document/path/90665)。

   配置路径：**应用管理 → 自建 → 目标应用 → 可见范围**

   | 同步需求 | 可见范围建议 |
   |---------|-------------|
   | 同步全公司组织架构 | 设置为 **根部门**（或包含全部需同步部门的范围） |
   | 仅同步某条业务线 | 设置为对应部门（该部门及其子部门、成员自动纳入可见范围） |

   > **典型配置**：工作台可见范围可仅面向 IT/运维人员，但若需全量同步，可见范围应覆盖**全部待同步部门**。否则调用部门/成员接口时会返回 `60011`（指定的成员/部门/标签参数无权限）。

4. **模块调用的 API 接口**  

   | 模块用途 | 企业微信 API | 官方文档 |
   |---------|-------------|---------|
   | 获取访问凭证 | `GET /cgi-bin/gettoken` | [获取 access_token](https://developer.work.weixin.qq.com/document/path/91039) |
   | 获取部门列表（含子部门） | `GET /cgi-bin/department/list?id={root_department_id}` | [获取部门列表](https://developer.work.weixin.qq.com/document/path/90208) |
   | 获取部门直属成员详情 | `GET /cgi-bin/user/list?department_id={id}&fetch_child=0` | [获取部门成员详情](https://developer.work.weixin.qq.com/document/path/90201) |

   模块同步逻辑：先按 `root_department_id` 拉取部门树，再对每个部门分别调用 `user/list`（`fetch_child=0`）获取**直属成员**，与官方「需逐层递归获取子部门成员」的建议一致。

5. **权限与返回字段说明**  

   - **API 调用权限**：应用须对目标部门/成员拥有**查看权限**（即在应用可见范围内）。参见[通讯录管理概述](https://developer.work.weixin.qq.com/document/path/90193)。  
   - **Zabbix 用户名**：模块使用企业微信 **`userid`** 作为 Zabbix 用户名（与企业管理后台通讯录账号一致）。  
   - **姓名**：`user/list` 接口一般返回 `name`；若为空，同步结果中姓名可能缺失。  
   - **手机号 / 邮箱（敏感字段限制）**：自 **2022 年 6 月 20 日**起，新创建的自建应用通过 `user/list` 等常规通讯录接口**不再直接返回**手机号、邮箱、企业邮箱等敏感字段，需通过 **OAuth2 手工授权**（`snsapi_privateinfo`）逐用户获取。详见[读取成员](https://developer.work.weixin.qq.com/document/path/90196)及[获取部门成员详情](https://developer.work.weixin.qq.com/document/path/90201)中的「应用获取敏感字段的说明」。  
     - 本模块当前**不实现 OAuth 授权流程**；同步仍可正常完成（userid、部门、用户组关系），但**手机号/邮箱可能为空**。  
     - 若企业使用**较早创建且未升级权限策略**的自建应用/通讯录同步应用，仍可能返回 `mobile`、`email`、`biz_mail`，模块会自动优先使用 `email`，其次 `biz_mail`。

6. **（可选）企业可信 IP**  
   若使用通讯录同步 Secret 或企业在安全策略中启用了 IP 白名单，需在 **管理工具 → 通讯录同步 → 企业可信 IP** 中添加 Zabbix Web 服务器出口 IP。使用自建应用 Secret 的一般场景下通常无需单独开启通讯录同步功能。

7. **在 Zabbix 中启用**  
   进入 **Users → 同步设置** → 添加企业微信设置 → 填写 `Corp ID`、`Corp Secret`、`root_department_id` → 保存并启用 → 在 **IM 同步** 页面预览/同步。

#### 企业微信配置示例

```json
{
    "id": "set_wecom_prod",
    "name": "企业微信生产环境",
    "provider": "wecom",
    "enabled": true,
    "root_department_id": "1",
    "corp_id": "wwxxxxxxxxxxxxxxxx",
    "corp_secret": "your_app_secret"
}
```

#### 企业微信常见问题排查

| 现象 / 错误 | 可能原因 | 处理建议 |
|------------|---------|---------|
| `40001` 不合法的 secret | `corp_id` 与 `corp_secret` 不匹配，或 Secret 已重置/应用已停用 | 核对凭据，在管理后台重新复制 Secret |
| `40014` 不合法的 access_token | Token 过期或混用了不同应用的 Secret | 模块会自动刷新；确认使用的是**目标自建应用**的 Secret |
| `60011` 指定的成员/部门/标签参数无权限 | 目标部门/成员不在应用**可见范围**内 | 扩大应用可见范围，或调整 `root_department_id` 为可见范围内的部门 |
| `48009` API 接口无权限调用 | 使用了通讯录同步 Secret，且服务器 IP 不在白名单 | 改用**自建应用 Secret**；或配置企业可信 IP |
| `48002` API 接口无权限调用 | 应用类型与接口不匹配（如用错 Secret 类型） | 确认使用自建应用 Secret，查阅接口「权限说明」 |
| `40066` 不合法的部门列表 | `root_department_id` 不存在或不在可见范围 | 在 **通讯录 → 组织架构** 中确认部门 ID |
| 部门/用户数量明显偏少 | 可见范围仅覆盖部分部门 | 将可见范围扩大到根部门或所需全部部门 |
| 手机号 / 邮箱始终为空 | 新自建应用敏感字段策略限制 | 属预期行为；如需敏感字段需 OAuth 授权（本模块暂不支持） |
| `userid` 含大小写导致匹配失败 | Zabbix 用户名大小写不一致 | 可在 `config.json` 顶层设置 `"username_lowercase": true`（默认已开启） |

> 术语说明参见[基本概念介绍](https://developer.work.weixin.qq.com/document/path/90665)；全局错误码参见[全局错误码](https://developer.work.weixin.qq.com/document/path/90313)。

**飞书 `feishu`（`provider = feishu` 时填写）**

本模块通过飞书**企业自建应用**调用通讯录 API，使用 `tenant_access_token`（应用身份）拉取部门与用户。官方文档入口：[飞书开放平台](https://open.feishu.cn/document/home/index)。

| 字段 | 说明 |
|------|------|
| `app_id` | 飞书自建应用的 **App ID**（开发者后台 → 应用详情 → 凭证与基础信息） |
| `app_secret` | 飞书自建应用的 **App Secret** |
| `root_department_id` | 同步根部门的部门 ID。填 `"0"` 表示从企业根部门开始递归同步；也可填写具体部门的 `open_department_id`（如 `od-xxxxxxxx`），仅同步该部门及其子部门 |

#### 飞书应用创建与配置步骤

1. **创建企业自建应用**  
   登录 [飞书开发者后台](https://open.feishu.cn/app)，按[自建应用开发流程](https://open.feishu.cn/document/home/introduction-to-custom-app-development/self-built-application-development-process)创建应用。本模块只需服务端 API，无需开启机器人、网页应用等客户端能力。

2. **获取凭据**  
   在应用详情 → **凭证与基础信息** 中复制 `App ID`、`App Secret`，填入 Zabbix **Users → 同步设置** 对应字段。

3. **开通 API 权限（权限管理 → API 权限）**  
   模块调用的接口及所需权限如下：

   | 模块用途 | 飞书 API | 官方文档 |
   |---------|---------|---------|
   | 获取访问凭证 | `POST /auth/v3/tenant_access_token/internal` | [获取 tenant_access_token](https://open.feishu.cn/document/server-docs/authentication-management/access-token/tenant_access_token_internal) |
   | 获取根/单个部门信息 | `GET /contact/v3/departments/{department_id}` | [获取单个部门信息](https://open.feishu.cn/document/server-docs/contact-v3/department/get) |
   | 递归获取子部门 | `GET /contact/v3/departments` | [获取子部门列表](https://open.feishu.cn/document/server-docs/contact-v3/department/children) |
   | 获取部门直属成员 | `GET /contact/v3/users/find_by_department` | [获取部门直属用户列表](https://open.feishu.cn/document/server-docs/contact-v3/user/find_by_department) |

   **推荐开通的通讯录 API 权限（满足其一即可调用上述接口）：**

   | 权限标识 | 权限名称 | 说明 |
   |---------|---------|------|
   | `contact:contact:readonly_as_app` | 以应用身份读取通讯录 | **推荐**，与本模块使用 `tenant_access_token` 的方式一致 |
   | `contact:contact:readonly` | 读取通讯录 | 亦可 |
   | `contact:contact:access_as_app` | 以应用身份访问通讯录 | 亦可 |
   | `contact:contact.base:readonly` | 获取通讯录基本信息 | 亦可 |
   | `contact:department.organize:readonly` | 获取通讯录部门组织架构信息 | 亦可（获取子部门列表时） |

4. **配置通讯录权限范围（关键）**  
   除 API 权限外，还必须配置**通讯录权限范围**（应用可访问的部门/用户数据范围），否则接口会返回 `40004 no dept authority error`。详见[权限范围资源介绍](https://open.feishu.cn/document/server-docs/contact-v3/scope/scope_authority)。

   配置路径（二选一）：
   - 开发者后台 → 应用详情 → **开发配置 → 权限管理** → 通讯录相关权限 → **可访问的数据范围 → 配置**
   - 飞书管理后台 → **工作台 → 应用管理** → 进入该应用 → **通讯录设置**

   | `root_department_id` 取值 | 通讯录权限范围要求 |
   |---------------------------|-------------------|
   | `"0"`（企业根部门） | 必须设置为 **全部成员**（全员权限）。根部门 ID 为 `0` 时，[获取子部门列表](https://open.feishu.cn/document/server-docs/contact-v3/department/children)、[获取单个部门信息](https://open.feishu.cn/document/server-docs/contact-v3/department/get)、[获取部门直属用户列表](https://open.feishu.cn/document/server-docs/contact-v3/user/find_by_department) 均会校验应用是否具备全员通讯录权限 |
   | 具体部门 ID（如 `od-xxx`） | 权限范围需**包含该部门及其所有子部门**。应用只要拥有某部门的通讯录范围权限，即拥有其下所有子部门权限 |

   > **典型配置**：应用可用范围可仅面向 IT/运维人员，但通讯录权限范围建议设为 **全部成员**，以便从根部门完整同步组织架构。参见官方[典型案例](https://open.feishu.cn/document/server-docs/contact-v3/scope/scope_authority#典型案例)。

5. **开通字段权限（按需）**  
   以下字段为**敏感字段**，未开通对应权限时 API 仍可调通，但响应中**不会返回**该字段：

   | 模块用途 | 建议开通的字段权限 | 权限标识 |
   |---------|-------------------|---------|
   | Zabbix 用户名优先使用飞书 `user_id` | 获取用户 user ID | `contact:user.employee_id:readonly` |
   | 同步用户邮箱 | 获取用户邮箱信息 | `contact:user.email:readonly` |
   | 同步用户手机号 | 获取用户手机号 | `contact:user.phone:readonly` |
   | 同步用户姓名 | 获取用户基本信息 | `contact:user.base:readonly` |

   未开通 `contact:user.employee_id:readonly` 时，模块会使用 `open_id` 作为 Zabbix 用户名（同一用户在不同应用中 `open_id` 不同，一般建议开通 `user_id` 权限）。

6. **发布应用版本**  
   修改权限或通讯录范围后，需在开发者后台**创建并发布新版本**，由企业管理员审核通过后权限才正式生效。参见[发布与审核自建应用](https://open.feishu.cn/document/home/introduction-to-custom-app-development/self-built-application-development-process#发布应用)。

7. **在 Zabbix 中启用**  
   进入 **Users → 同步设置** → 添加飞书设置 → 填写 `App ID`、`App Secret`、`root_department_id` → 保存并启用 → 在 **IM 同步** 页面预览/同步。

#### 飞书配置示例

```json
{
    "id": "set_feishu_prod",
    "name": "飞书生产环境",
    "provider": "feishu",
    "enabled": true,
    "root_department_id": "0",
    "app_id": "cli_xxxxxxxxxx",
    "app_secret": "your_app_secret"
}
```

#### 飞书常见问题排查

| 现象 / 错误 | 可能原因 | 处理建议 |
|------------|---------|---------|
| `40004 no dept authority error` | 目标部门不在应用通讯录权限范围内 | 扩大通讯录权限范围，或改用权限范围内的部门 ID 作为 `root_department_id` |
| 根部门用户/子部门拉取失败 | `root_department_id = 0` 但未配置全员权限 | 将通讯录权限范围设为 **全部成员** |
| 用户名为 `ou_xxx` 而非工号/user_id | 未开通 `contact:user.employee_id:readonly` | 在 API 权限中开通该字段权限并重新发布应用 |
| 邮箱 / 手机号为空 | 未开通对应字段权限 | 开通 `contact:user.email:readonly`、`contact:user.phone:readonly` |
| 权限已改但未生效 | 未发布新版本或未通过管理员审核 | 创建应用版本 → 提交审核 → 管理员在管理后台批准 |
| `tenant_access_token` 失败 | `app_id` / `app_secret` 错误或应用被停用 | 核对凭据，确认应用在开发者后台处于可用状态 |

> 完整权限列表参见飞书[应用权限列表](https://open.feishu.cn/document/ukTMukTMukTM/uYTM5UjL2ETO14iNxkTN/scope-list)。用户 ID 类型说明参见[如何获取不同的用户 ID](https://open.feishu.cn/document/home/user-identity-introduction/open-id)。

**钉钉 `dingtalk`（`provider = dingtalk` 时填写）**

本模块通过**钉钉企业内部应用**调用通讯录 API，使用 `access_token`（由 `app_key` + `app_secret` 换取）拉取部门与用户。官方文档入口：[钉钉开放平台](https://open.dingtalk.com/document/)。

| 字段 | 说明 |
|------|------|
| `app_key` | 企业内部应用的 **AppKey**（Client ID）。开发者后台 → 应用开发 → 目标应用 → **凭证与基础信息** |
| `app_secret` | 企业内部应用的 **AppSecret**（Client Secret） |
| `root_department_id` | 同步根部门 ID。填 `1` 表示从企业根部门开始递归同步；也可填写具体部门 ID，仅同步该部门及其子部门 |

> **重要**：请使用**企业内部应用**，不要使用第三方企业应用。第三方应用通常**不返回**手机号、邮箱等个人信息字段；本模块需读取部门与成员详情，企业内部应用更合适。

#### 钉钉应用创建与配置步骤

1. **创建企业内部应用**  
   使用管理员账号登录 [钉钉开放平台](https://open.dingtalk.com/) → **应用开发 → 企业内部开发 → 创建应用**。本模块只需服务端 API 拉取通讯录，无需配置机器人、网页应用等客户端能力。参见[获取企业下所有员工信息（教程）](https://open.dingtalk.com/document/orgapp/obtains-information-about-all-employees-of-an-enterprise)。

2. **获取凭据**  
   在应用详情 → **凭证与基础信息**（或基础信息）中复制 **AppKey**、**AppSecret**，填入 Zabbix **Users → 同步设置** 对应字段。

3. **申请接口权限（开发配置 → 权限管理）**  
   钉钉服务端 API 以**应用维度**授权，调用前必须为应用添加对应接口权限。在权限搜索框中搜索并申请：

   | 权限点 Code | 权限名称 | 模块用途 |
   |------------|---------|---------|
   | `qyapi_get_department_list` | 通讯录部门信息读权限 | 获取部门详情、获取子部门列表 |
   | `qyapi_get_department_member` | 通讯录部门成员读权限 | 获取部门用户详情（分页） |

   配置路径：**应用详情 → 开发配置 → 权限管理 → 搜索上述 Code → 申请权限**。部分权限需管理员审批，审批通过后状态变为「已开通」。

4. **（建议）配置通讯录相关个人信息权限**  
   若需在同步结果中获取手机号、邮箱，请在权限管理中一并开通通讯录相关的**个人信息/手机号/邮箱**读取权限（具体权限名称以开发者后台搜索结果为准）。  
   - **企业内部应用**：通常可返回 `mobile`、`email`、`org_email`（模块优先使用 `email`，其次 `org_email`）。  
   - **第三方企业应用**：官方文档明确**不返回**手机号、邮箱等字段，不适合本模块。

5. **（可选）配置服务器出口 IP**  
   在 **开发配置 → 开发管理** 中配置**服务器出口 IP**（Zabbix Web 服务器公网 IP）。若企业安全策略启用了 IP 白名单，未配置可能导致 API 调用失败。

6. **模块调用的 API 接口**  

   | 模块用途 | 钉钉 API | 官方文档 |
   |---------|---------|---------|
   | 获取访问凭证 | `GET /gettoken?appkey=&appsecret=` | [获取企业内部应用的 access_token](https://open.dingtalk.com/document/orgapp/obtain-orgapp-token) |
   | 获取根/单个部门详情 | `POST /topapi/v2/department/get` | [获取部门详情](https://open.dingtalk.com/document/orgapp/query-department-details0-v2) |
   | 递归获取下一级子部门 | `POST /topapi/v2/department/listsub` | [获取部门列表（listsub）](https://open.dingtalk.com/document/orgapp/obtain-the-department-list) |
   | 获取部门直属成员（分页） | `POST /topapi/v2/user/list` | [获取部门用户详情](https://open.dingtalk.com/document/orgapp/queries-the-complete-information-of-a-department-user) |

   模块同步逻辑（与[官方全量同步建议](https://open.dingtalk.com/document/orgapp/obtains-information-about-all-employees-of-an-enterprise)一致）：
   - 先补齐根部门信息（`listsub` 不含根部门本身）；
   - 从 `root_department_id` 起通过 `listsub` **逐层递归**获取子部门；
   - 对每个部门调用 `user/list`（`cursor` 分页，`size` 最大 100），仅获取**直属成员**（不含子部门成员）；
   - 自动剔除家校通讯录部门 **`-7`**（不属于内部通讯录范围）。

7. **字段与 Zabbix 映射**  
   - **Zabbix 用户名**：优先使用 **`mobile`（手机号）**；若无手机号，则将 **`name`（姓名）转为拼音**（如「张三」→ `zhangsan`）；若仍无法生成则回退为 **`userid`**。中文转拼音使用内置 GB2312 映射，仅需 PHP 常见扩展 `iconv` 或 `mbstring`。  
   - **姓名**：`name`。  
   - **邮箱**：`email`，为空时使用 `org_email`。  
   - **手机号**：`mobile`（需应用具备相应读取权限；有值时优先作为用户名，无值时使用姓名拼音）。

8. **在 Zabbix 中启用**  
   进入 **Users → 同步设置** → 添加钉钉设置 → 填写 `App Key`、`App Secret`、`root_department_id` → 保存并启用 → 在 **IM 同步** 页面预览/同步。

#### 钉钉配置示例

```json
{
    "id": "set_dingtalk_prod",
    "name": "钉钉生产环境",
    "provider": "dingtalk",
    "enabled": true,
    "root_department_id": "1",
    "app_key": "dingxxxxxxxxxx",
    "app_secret": "your_app_secret"
}
```

#### 钉钉常见问题排查

| 现象 / 错误 | 可能原因 | 处理建议 |
|------------|---------|---------|
| `88` / `sub_code=60011` 没有调用该接口的权限 | 未申请或未开通 `qyapi_get_department_list` / `qyapi_get_department_member` | 在 **权限管理** 中搜索权限点 Code 并申请，等待审批通过 |
| `50004` 部门不在权限范围内 | 应用通讯录授权范围未覆盖目标部门 | 扩大应用通讯录授权范围至根组织或所需部门 |
| `60003` 未找到对应部门 / 部门不存在 | `root_department_id` 填写错误 | 在钉钉管理后台 **通讯录 → 部门管理** 确认部门 ID；根部门为 `1` |
| `40009` 不合法的部门 id | 部门 ID 非数字或 ≤ 0 | 确认 `root_department_id` 为正整数 |
| `40001` / 不合法的 AppKey 或 AppSecret | 凭据错误或应用已删除/停用 | 在开发者后台重新复制 AppKey / AppSecret |
| `43007` 需要授权 | `access_token` 无效或权限不足 | 核对凭据与权限开通状态 |
| 部门/用户数量偏少 | 未从根部门递归，或权限范围不足 | 确认 `root_department_id=1` 且权限已覆盖全组织 |
| 出现 `-7` 相关部门 | 企业开通了家校通讯录 | 模块已自动剔除，无需处理 |
| 手机号 / 邮箱为空 | 使用了第三方应用，或未开通个人信息读取权限 | 改用**企业内部应用**并申请相应权限 |
| 用户名仍为 `userid`（期望拼音） | 未部署最新 `PinyinHelper.php`；姓名为空；服务器缺少 `iconv`/`mbstring` | 更新模块文件；确认 API 返回 `name`；检查 PHP 扩展 |
| 拼音用户名重复 / 创建失败 | 多人同名且无手机号，生成相同拼音（如 `zhangsan`） | 在钉钉侧补充手机号，或在 Zabbix 中手工创建并匹配已有用户 |
| API 调用被拒绝 | 服务器 IP 不在白名单 | 在 **开发管理** 中配置 Zabbix 服务器出口 IP |

> 错误码说明参见[错误码（旧版服务端 API）](https://open.dingtalk.com/document/orgapp/server-api-error-codes-1)。通讯录部门操作总览参见[通讯录部门相关操作](https://open.dingtalk.com/document/orgapp/operations-related-to-address-book-departments)。

**固定行为（无需在 config.json 中配置）**

- 用户组名称不加前缀，与 IM 部门名一致。
- 用户匹配固定为 **username**（企业微信/飞书使用 IM 账号 ID；**钉钉优先使用手机号**，无手机号时使用姓名拼音，仍无法生成时使用 `userid`）。

### 启用模块

1. 转到 **Administration → General → Modules**。
2. 点击 **Scan directory** 扫描新模块。
3. 找到 **IM Sync Assistant** 模块并启用。
4. 刷新页面，在 **Users** 菜单下会出现 **IM同步助手**。

## 使用说明

1. 进入 **Users → 同步设置**，添加并启用一个同步设置（企业微信 / 飞书 / 钉钉）。
2. 进入 **Users → IM同步助手 → IM 同步** 页面，顶部会显示当前启用的设置；可点击 **同步设置** 快速跳转管理页。
3. 点击 **预览部门** 查看部门与用户匹配情况。
4. 点击 **同步全部部门**，将 IM 部门同步为 Zabbix 用户组。
5. 点击 **同步所有用户**，将 IM 成员创建/匹配为 Zabbix 用户并加入对应用户组。

建议操作顺序：**配置并启用同步设置 → 预览部门 → 同步全部部门 → 同步所有用户**。

## 权限要求

- 查看页面与同步部门需要 **Zabbix Admin** 权限。
- **同步设置**（增删改、启用/停用凭据）需要 **Super Admin** 权限，因其涉及应用密钥管理。
- **同步所有用户**（含自动创建用户）需要 **Super Admin** 权限（Zabbix 7 的 `user.create` 仅 Super Admin 可调用）。
- IM 应用需开通通讯录读取相关 API 权限。

## 注意事项

- **保护策略**：系统自带或手工创建的用户/用户组**不会被删除或修改**；模块仅管理记录在 `data/sync_registry.json` 中的对象。
- **用户类型**：
  - `created`：模块自动创建的用户，可随 IM 变更增删改。
  - `linked`：匹配到已有 Zabbix 用户的 IM 成员，仅同步用户组成员关系，**不删除账号**。
- **用户组冲突**：若 IM 部门名称对应的用户组已存在但非模块创建，将跳过该部门并记录日志。
- **手工成员**：模块管理用户组中手工加入的非模块用户，同步时会被保留。
- **自动创建用户**：新用户密码由模块自动生成（12 位大小写字母 + 数字），并在「同步所有用户」结果中**仅展示一次**，请及时保存；Zabbix 7+ 还需配置 `default_roleid`；须用 Super Admin 执行同步。
- **手动删除用户**：若在 Zabbix 中手动删除了模块同步的用户，再次同步时会自动清理注册表记录并重新创建。
- **多部门用户**：同一用户可属于多个 IM 部门，会加入多个 Zabbix 用户组。
- **网络访问**：Zabbix Web 需能访问对应 IM 开放平台 API。
- **钉钉用户名变更**：若此前已用 `userid` 同步，启用手机号/拼音策略后可能创建新账号而非匹配旧账号；调整策略前建议先预览。
- **PHP 扩展**：钉钉姓名转拼音需要 `iconv` 或 `mbstring`（二者有其一即可）。

## 开发

模块基于 Zabbix 模块框架，文件结构：

- `manifest.json`：模块配置
- `Module.php`：菜单注册（IM 同步 / 同步设置）
- `actions/Im.php`：主页面业务逻辑
- `actions/ImSync.php` / `ImSyncUsers.php`：同步 API
- `actions/ImPreview.php`：预览 API
- `actions/ImSettings.php` / `ImSettingsSave.php` / `ImSettingsDelete.php` / `ImSettingsEnable.php`：同步设置 CRUD
- `views/im.php` / `im.settings.php`：页面视图
- `lib/ImSyncService.php`：同步核心逻辑
- `lib/WeComClient.php` / `FeishuClient.php` / `DingTalkClient.php`：IM 平台客户端
- `lib/PinyinHelper.php`：中文姓名转拼音（钉钉无手机号时使用）
- `lib/ConfigManager.php`：配置管理（多套 settings、单启用、旧格式迁移）
- `lib/SyncRegistry.php`：模块创建对象的注册表
- `lib/LanguageManager.php`：国际化
- `data/config.json`：连接配置（`settings` 列表）
- `data/sync_registry.json`：同步注册表（运行时生成）

### 国际化

界面文案统一由 `lib/LanguageManager.php` 管理，支持 **中文（zh_CN）** 与 **英文（en_US）**。语言检测顺序与 Zabbix 一致：当前用户语言 → 系统默认语言 → `en_US`。菜单、页面、弹窗、API 错误提示及 IM 客户端异常信息均已接入翻译；第三方 API 返回的原始错误码/消息仍可能为英文。

## License

本项目遵循 Zabbix 许可。详见 [Zabbix License](https://www.zabbix.com/license)。
