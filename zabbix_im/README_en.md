# IM Sync Assistant

[中文](README.md)

## ✨ Version Compatibility

This module is compatible with Zabbix 6.0 / 7.0+ / 8.0+.

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x
- ✅ Zabbix 8.0.x

**Compatibility Note**: The module includes intelligent version detection that automatically adapts to different Zabbix API versions and class libraries, requiring no manual configuration.

![1](images/1.png)
![2](images/2.png)
![3](images/3.png)

## Description

This is a Zabbix frontend module that syncs departments from **WeCom**, **Feishu**, or **DingTalk** into Zabbix **user groups**, and assigns department members to the corresponding groups.

The module adds an **IM Sync Assistant** submenu under the **Users** menu in Zabbix Web.

## Features

- **Multi-platform support**: WeCom, Feishu, DingTalk
- **Multiple sync settings**: Manage IM credentials in the UI (**Users → Sync Settings**); only one setting enabled at a time
- **Department sync**: Create/update Zabbix user groups from IM departments
- **User sync**: Match or create Zabbix users from IM members and assign them to groups
- **DingTalk username policy**: Prefer mobile; convert `name` to pinyin when mobile is absent (e.g. 张三 → `zhangsan`); fall back to `userid`
- **Flexible matching**: Match by username, email, or alias (currently fixed to username)
- **Preview mode**: Preview departments and user matching before sync
- **Path mode**: Optionally use full department path as group name
- **Internationalization**: Chinese and English UI

## Installation

### Install Module

```bash
# Zabbix 6.0 / 7.0 deployment
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/modules/

# Zabbix 7.2+ / 7.4 / 8.0 deployment
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

### ⚠️ Modify manifest.json

```bash
# ⚠️ For Zabbix 6.0, modify manifest_version
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_im/manifest.json
```

### Configure IM Provider

Credentials are managed in the UI: go to **Users → Sync Settings** (**Super Admin** required).

- Click **Add sync setting**, choose a provider (WeCom / Feishu / DingTalk), fill in the keys and root department ID, then save. All settings are listed in a table.
- **Only one setting can be enabled at a time**: enabling one automatically disables the others. The **IM Sync** page always uses the enabled setting.
- When editing, secret fields are left blank by default, meaning **keep the existing value**; only a new value overwrites it.
- Everything is persisted to the `settings` list in `zabbix_im/data/config.json`. No manual file editing and no service restart needed.

`config.json` shape (the `settings` list is managed by the UI; global options can be edited by hand):

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
            "name": "WeCom",
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

> Legacy compatibility: if `config.json` still uses the old format (top-level `provider` plus `wecom`/`feishu`/`dingtalk` sections), the module migrates it into the `settings` list on load, enabling the provider that matches the old `provider` value.

#### Configuration Reference

> `provider` and per-provider credentials are entered via the **Sync Settings** UI; the global options below can still be adjusted at the top level of `config.json`.

**Global options**

| Field | Type | Description |
|-------|------|-------------|
| `settings[].provider` | string | Data source: `wecom` / `feishu` / `dingtalk`. Enter per setting in Sync Settings; only the enabled setting is used. |
| `use_full_path` | bool | Use full IM department path as Zabbix user group name. `false` = current dept name only (e.g. "R&D Team A"); `true` = full path (e.g. "R&D Center/R&D Team A"), separator from `path_separator`. |
| `path_separator` | string | Path separator, default `/`. Only applies when `use_full_path` is `true`. |
| `username_lowercase` | bool | Lowercase usernames when matching/creating (WeCom userid often contains mixed case). |
| `remove_orphans` | bool | Delete **module-managed** user groups when departments disappear from IM. Manual or built-in groups are never deleted. |
| `remove_orphan_users` | bool | Delete **module-created** Zabbix users when they disappear from IM. Only `origin=created` accounts; `linked` users are never deleted. |
| `auto_create_users` | bool | Auto-create Zabbix users for IM members not found. Requires **Super Admin** for "Sync all users"; Zabbix 7+ needs `default_roleid`. |
| `auto_update_users` | bool | Update name/email of module-created users. `linked` users are not modified. |
| `default_roleid` | string | Role ID for auto-created users on Zabbix 7.0+ (see **Users → User roles**). If empty, module tries to pick a non-admin role whose name contains `user`; explicit value recommended. |
| `default_user_type` | int | User type for auto-created users on Zabbix 6.x: `1` User / `2` Admin / `3` Super Admin (usually `1` or `2`). |
| `verify_ssl` | bool | Verify HTTPS certificates when calling IM APIs. Set `false` for internal proxies or self-signed certs. |

#### Zabbix username rules by platform

The module always matches/creates users by **username**. Source per platform:

| Platform | Zabbix username source | Notes |
|----------|------------------------|-------|
| WeCom | `userid` | Same as admin console contact account |
| Feishu | `user_id` | Requires `contact:user.employee_id:readonly`; falls back to `open_id` |
| DingTalk | `mobile` → name pinyin → `userid` | Converts `name` to lowercase pinyin when mobile is absent (built-in GB2312 map); ASCII names used as-is; rare/unmapped chars fall back to `userid` |

> DingTalk pinyin conversion requires PHP `iconv` or `mbstring` (usually available on Zabbix). Identical names without mobile may produce duplicate pinyin usernames.

**WeCom `wecom` (`provider = wecom`)**

This module calls WeCom contact APIs via an **enterprise self-built app**, using `access_token` (obtained from `corp_id` + app `corp_secret`). Official docs: [WeCom Developer Center](https://developer.work.weixin.qq.com/document/).

| Field | Description |
|-------|-------------|
| `corp_id` | Corp ID. Admin console → **My Company → Company Info → Corp ID** |
| `corp_secret` | **Secret** of the self-built app. Admin console → **App Management → Apps → Built-in** → open target app → Secret |
| `root_department_id` | Root department ID. `1` usually means sync from enterprise root and all sub-departments; or a specific dept ID to sync that subtree only |

> **Important**: Use a **self-built app Secret**, not the contact sync Secret from **Admin Tools → Contact Sync**. Since 2022-08-15, contact sync assistant restricts new IPs on member/dept detail APIs (error `48009`). See [Contact sync API changes](https://developer.work.weixin.qq.com/document/path/90193).

#### WeCom app setup

1. **Create a self-built app**  
   Log in to [WeCom Admin Console](https://work.weixin.qq.com/wework_admin/frame) → **App Management → Apps → Built-in → Create app**. This module only needs server-side contact APIs; no web OAuth or callback URL required (unless you later need OAuth for sensitive fields).

2. **Get credentials**  
   - `corp_id`: **My Company → Company Info → Corp ID**  
   - `corp_secret`: **App Management → Built-in → target app → Secret**  
   Enter both in Zabbix **Users → Sync Settings**.

3. **Configure app visible range (critical)**  
   Self-built apps can **only read contacts within their visible range**, which also limits API access. See [Basic concepts – App visible range](https://developer.work.weixin.qq.com/document/path/90665).

   Path: **App Management → Built-in → target app → Visible range**

   | Sync goal | Visible range recommendation |
   |-----------|------------------------------|
   | Full company org | Set to **root department** (or range covering all depts to sync) |
   | Single business line | Set to that department (sub-depts and members included automatically) |

   > **Typical setup**: Workbench visible range can be IT/ops only, but for full sync the visible range must cover **all departments to sync**. Otherwise APIs return `60011` (no permission for specified member/dept/tag).

4. **APIs used by this module**

   | Purpose | WeCom API | Docs |
   |---------|-----------|------|
   | Access token | `GET /cgi-bin/gettoken` | [Get access_token](https://developer.work.weixin.qq.com/document/path/91039) |
   | Department list (incl. sub-depts) | `GET /cgi-bin/department/list?id={root_department_id}` | [Get department list](https://developer.work.weixin.qq.com/document/path/90208) |
   | Direct dept members | `GET /cgi-bin/user/list?department_id={id}&fetch_child=0` | [Get department member details](https://developer.work.weixin.qq.com/document/path/90201) |

   Sync flow: fetch dept tree from `root_department_id`, then call `user/list` per department with `fetch_child=0` for **direct members only**, matching WeCom's recursive guidance.

5. **Permissions and returned fields**

   - **API permission**: App must have **view permission** for target depts/members (within visible range). See [Contact management overview](https://developer.work.weixin.qq.com/document/path/90193).
   - **Zabbix username**: Module uses WeCom **`userid`** (same as admin console account).
   - **Name**: Usually returned by `user/list`; may be empty in sync results if missing.
   - **Mobile / email (sensitive fields)**: Since **2022-06-20**, new self-built apps no longer get mobile, email, biz_mail via regular contact APIs; OAuth2 manual auth (`snsapi_privateinfo`) is required per user. See [Read member](https://developer.work.weixin.qq.com/document/path/90196) and [Get department member details](https://developer.work.weixin.qq.com/document/path/90201).
     - This module **does not implement OAuth**; sync still works (userid, depts, group membership) but **mobile/email may be empty**.
     - Older apps may still return `mobile`, `email`, `biz_mail`; module prefers `email`, then `biz_mail`.

6. **(Optional) Trusted enterprise IP**  
   If using contact sync Secret or IP whitelist is enabled, add Zabbix Web server egress IP under **Admin Tools → Contact Sync → Trusted IP**. Usually not needed with self-built app Secret.

7. **Enable in Zabbix**  
   **Users → Sync Settings** → add WeCom setting → fill Corp ID, Corp Secret, `root_department_id` → save and enable → preview/sync on **IM Sync**.

#### WeCom config example

```json
{
    "id": "set_wecom_prod",
    "name": "WeCom Production",
    "provider": "wecom",
    "enabled": true,
    "root_department_id": "1",
    "corp_id": "wwxxxxxxxxxxxxxxxx",
    "corp_secret": "your_app_secret"
}
```

#### WeCom troubleshooting

| Symptom / error | Likely cause | Fix |
|-----------------|--------------|-----|
| `40001` invalid secret | `corp_id` / `corp_secret` mismatch, or Secret reset / app disabled | Verify credentials; re-copy Secret from admin console |
| `40014` invalid access_token | Expired token or wrong app Secret | Module auto-refreshes; confirm target self-built app Secret |
| `60011` no permission for dept/member/tag | Target not in app **visible range** | Expand visible range or adjust `root_department_id` |
| `48009` API no permission | Contact sync Secret + IP not whitelisted | Use **self-built app Secret** or configure trusted IP |
| `48002` API no permission | Wrong Secret type for API | Use self-built app Secret; check API permission notes |
| `40066` invalid department list | `root_department_id` missing or out of range | Confirm dept ID under **Contacts → Organization** |
| Fewer depts/users than expected | Visible range too narrow | Expand to root or all required depts |
| Mobile / email always empty | New app sensitive-field policy | Expected; OAuth required (not supported by this module) |
| Username case mismatch | Zabbix username case differs from userid | Set `"username_lowercase": true` in config (default on) |

> Terms: [Basic concepts](https://developer.work.weixin.qq.com/document/path/90665). Error codes: [Global error codes](https://developer.work.weixin.qq.com/document/path/90313).

**Feishu `feishu` (`provider = feishu`)**

This module calls Feishu contact APIs via an **enterprise self-built app**, using `tenant_access_token` (app identity). Official docs: [Feishu Open Platform](https://open.feishu.cn/document/home/index).

| Field | Description |
|-------|-------------|
| `app_id` | Self-built app **App ID** (Developer console → App details → Credentials & basic info) |
| `app_secret` | Self-built app **App Secret** |
| `root_department_id` | Root department ID. `"0"` = sync from enterprise root recursively; or a specific `open_department_id` (e.g. `od-xxxxxxxx`) for that subtree only |

#### Feishu app setup

1. **Create a self-built app**  
   Log in to [Feishu Developer Console](https://open.feishu.cn/app) and follow the [self-built app development process](https://open.feishu.cn/document/home/introduction-to-custom-app-development/self-built-application-development-process). Server-side APIs only; no bot or web app required.

2. **Get credentials**  
   Copy **App ID** and **App Secret** from **Credentials & basic info** into Zabbix **Users → Sync Settings**.

3. **Enable API permissions (Permission management → API permissions)**

   | Purpose | Feishu API | Docs |
   |---------|------------|------|
   | Access token | `POST /auth/v3/tenant_access_token/internal` | [Get tenant_access_token](https://open.feishu.cn/document/server-docs/authentication-management/access-token/tenant_access_token_internal) |
   | Root/single dept | `GET /contact/v3/departments/{department_id}` | [Get department](https://open.feishu.cn/document/server-docs/contact-v3/department/get) |
   | Child departments | `GET /contact/v3/departments` | [List child departments](https://open.feishu.cn/document/server-docs/contact-v3/department/children) |
   | Direct dept members | `GET /contact/v3/users/find_by_department` | [Find users by department](https://open.feishu.cn/document/server-docs/contact-v3/user/find_by_department) |

   **Recommended contact API permissions (any one enables the above APIs):**

   | Scope | Name | Notes |
   |-------|------|-------|
   | `contact:contact:readonly_as_app` | Read contacts as app | **Recommended**; matches `tenant_access_token` usage |
   | `contact:contact:readonly` | Read contacts | Also works |
   | `contact:contact:access_as_app` | Access contacts as app | Also works |
   | `contact:contact.base:readonly` | Read basic contact info | Also works |
   | `contact:department.organize:readonly` | Read dept org structure | Also works for child dept list |

4. **Configure contact scope (critical)**  
   Besides API permissions, you must set **contact permission scope** (which depts/users the app can access), or APIs return `40004 no dept authority error`. See [Scope authority](https://open.feishu.cn/document/server-docs/contact-v3/scope/scope_authority).

   Configuration paths:
   - Developer console → App → **Development config → Permission management** → contact permissions → **Accessible data scope → Configure**
   - Feishu admin → **Workplace → App management** → app → **Contact settings**

   | `root_department_id` | Contact scope requirement |
   |----------------------|---------------------------|
   | `"0"` (enterprise root) | Must be **All members** (full-company scope). Root dept APIs verify full-company permission |
   | Specific dept (e.g. `od-xxx`) | Scope must **include that dept and all sub-depts** |

   > **Typical setup**: App availability can be IT-only, but contact scope should be **All members** for full org sync. See [official example case](https://open.feishu.cn/document/server-docs/contact-v3/scope/scope_authority#典型案例).

5. **Field permissions (as needed)**  
   Sensitive fields are omitted unless the matching scope is granted:

   | Module use | Recommended scope | Identifier |
   |------------|-------------------|------------|
   | Prefer Feishu `user_id` as Zabbix username | Read user ID | `contact:user.employee_id:readonly` |
   | Sync email | Read user email | `contact:user.email:readonly` |
   | Sync mobile | Read user phone | `contact:user.phone:readonly` |
   | Sync display name | Read basic user info | `contact:user.base:readonly` |

   Without `contact:user.employee_id:readonly`, module falls back to `open_id` (differs per app; enabling `user_id` is recommended).

6. **Publish app version**  
   After changing permissions or scope, create and publish a new version; enterprise admin approval required. See [Publish self-built app](https://open.feishu.cn/document/home/introduction-to-custom-app-development/self-built-application-development-process#发布应用).

7. **Enable in Zabbix**  
   **Users → Sync Settings** → add Feishu setting → App ID, App Secret, `root_department_id` → save and enable → preview/sync on **IM Sync**.

#### Feishu config example

```json
{
    "id": "set_feishu_prod",
    "name": "Feishu Production",
    "provider": "feishu",
    "enabled": true,
    "root_department_id": "0",
    "app_id": "cli_xxxxxxxxxx",
    "app_secret": "your_app_secret"
}
```

#### Feishu troubleshooting

| Symptom / error | Likely cause | Fix |
|-----------------|--------------|-----|
| `40004 no dept authority error` | Target dept outside contact scope | Widen scope or use in-scope `root_department_id` |
| Root dept users/sub-depts fail | `root_department_id = 0` without full-company scope | Set contact scope to **All members** |
| Username is `ou_xxx` not user_id | Missing `contact:user.employee_id:readonly` | Grant scope and republish app |
| Empty email / mobile | Missing field scopes | Grant `contact:user.email:readonly`, `contact:user.phone:readonly` |
| Permissions changed but not effective | No new version / admin not approved | Publish version → admin approval |
| `tenant_access_token` failure | Wrong `app_id`/`app_secret` or app disabled | Verify credentials and app status |

> Full scope list: [App permission list](https://open.feishu.cn/document/ukTMukTMukTM/uYTM5UjL2ETO14iNxkTN/scope-list). User ID types: [How to get different user IDs](https://open.feishu.cn/document/home/user-identity-introduction/open-id).

**DingTalk `dingtalk` (`provider = dingtalk`)**

This module calls DingTalk contact APIs via an **enterprise internal app**, using `access_token` (from `app_key` + `app_secret`). Official docs: [DingTalk Open Platform](https://open.dingtalk.com/document/).

| Field | Description |
|-------|-------------|
| `app_key` | Internal app **AppKey** (Client ID). Developer console → App → **Credentials & basic info** |
| `app_secret` | Internal app **AppSecret** (Client Secret) |
| `root_department_id` | Root department ID. `1` = sync from enterprise root recursively; or a specific dept ID for that subtree only |

> **Important**: Use an **enterprise internal app**, not a third-party enterprise app. Third-party apps typically **do not return** mobile, email, etc.; internal apps are required for this module.

#### DingTalk app setup

1. **Create an internal app**  
   Admin login to [DingTalk Open Platform](https://open.dingtalk.com/) → **App development → Internal development → Create app**. Server-side contact APIs only. Tutorial: [Get all employees of an enterprise](https://open.dingtalk.com/document/orgapp/obtains-information-about-all-employees-of-an-enterprise).

2. **Get credentials**  
   Copy **AppKey** and **AppSecret** from **Credentials & basic info** into Zabbix **Users → Sync Settings**.

3. **Apply API permissions (Development config → Permission management)**  
   DingTalk server APIs are authorized **per app**. Search and apply:

   | Permission code | Permission name | Module use |
   |-----------------|-----------------|------------|
   | `qyapi_get_department_list` | Contact dept info read | Dept detail, child dept list |
   | `qyapi_get_department_member` | Contact dept member read | Dept user list (paginated) |

   Path: **App details → Development config → Permission management → search codes → Apply**. Some permissions require admin approval.

4. **(Recommended) Personal info permissions**  
   For mobile/email in sync results, also grant contact **personal info / mobile / email** read permissions (exact names vary in console).
   - **Internal app**: Usually returns `mobile`, `email`, `org_email` (module prefers `email`, then `org_email`).
   - **Third-party app**: Official docs state mobile/email are **not returned** — not suitable for this module.

5. **(Optional) Server egress IP**  
   Under **Development config → Development management**, set **Server egress IP** (Zabbix Web public IP) if IP whitelist is enforced.

6. **APIs used by this module**

   | Purpose | DingTalk API | Docs |
   |---------|--------------|------|
   | Access token | `GET /gettoken?appkey=&appsecret=` | [Get internal app access_token](https://open.dingtalk.com/document/orgapp/obtain-orgapp-token) |
   | Root/single dept | `POST /topapi/v2/department/get` | [Get department detail](https://open.dingtalk.com/document/orgapp/query-department-details0-v2) |
   | Next-level sub-depts | `POST /topapi/v2/department/listsub` | [List sub-departments](https://open.dingtalk.com/document/orgapp/obtain-the-department-list) |
   | Direct dept members | `POST /topapi/v2/user/list` | [Get department user details](https://open.dingtalk.com/document/orgapp/queries-the-complete-information-of-a-department-user) |

   Sync flow (matches [official full sync guidance](https://open.dingtalk.com/document/orgapp/obtains-information-about-all-employees-of-an-enterprise)):
   - Add root dept first (`listsub` does not include root);
   - Recursively fetch sub-depts via `listsub` from `root_department_id`;
   - Per dept, call `user/list` with `cursor` pagination (`size` max 100), **direct members only**;
   - Automatically exclude school contact dept **`-7`**.

7. **Field mapping to Zabbix**
   - **Username**: Prefer **`mobile` (phone number)**; if absent, convert **`name` to pinyin** (e.g. 张三 → `zhangsan`); falls back to **`userid`** when conversion fails. Uses built-in GB2312 mapping; requires `iconv` or `mbstring`.
   - **Name**: `name`.
   - **Email**: `email`, fallback `org_email`.
   - **Mobile**: `mobile` (requires read permission; used as username when present; otherwise name pinyin is used).

8. **Enable in Zabbix**  
   **Users → Sync Settings** → add DingTalk setting → App Key, App Secret, `root_department_id` → save and enable → preview/sync on **IM Sync**.

#### DingTalk config example

```json
{
    "id": "set_dingtalk_prod",
    "name": "DingTalk Production",
    "provider": "dingtalk",
    "enabled": true,
    "root_department_id": "1",
    "app_key": "dingxxxxxxxxxx",
    "app_secret": "your_app_secret"
}
```

#### DingTalk troubleshooting

| Symptom / error | Likely cause | Fix |
|-----------------|--------------|-----|
| `88` / `sub_code=60011` no API permission | Missing `qyapi_get_department_list` / `qyapi_get_department_member` | Apply scopes in Permission management; wait for approval |
| `50004` dept out of permission scope | App contact scope doesn't cover target dept | Expand scope to root org or required depts |
| `60003` dept not found | Wrong `root_department_id` | Confirm ID in admin **Contacts → Departments**; root is `1` |
| `40009` invalid dept id | Non-numeric or ≤ 0 dept ID | Use positive integer for `root_department_id` |
| `40001` invalid AppKey/AppSecret | Wrong credentials or app removed | Re-copy from developer console |
| `43007` authorization required | Invalid token or insufficient permission | Verify credentials and granted scopes |
| Few depts/users | Wrong root or narrow scope | Use `root_department_id=1` and full-org scope |
| `-7` related depts appear | School contact enabled | Module auto-excludes; no action needed |
| Empty mobile/email | Third-party app or missing personal info scope | Use **internal app** and grant read permissions |
| Username still `userid` (expected pinyin) | Outdated `PinyinHelper.php`; empty `name`; missing `iconv`/`mbstring` | Update module files; confirm API returns `name`; check PHP extensions |
| Duplicate pinyin / create failure | Same name without mobile → same pinyin (e.g. `zhangsan`) | Add mobile in DingTalk, or create/match users manually in Zabbix |
| API rejected | Server IP not whitelisted | Set egress IP under **Development management** |

> Error codes: [Legacy server API error codes](https://open.dingtalk.com/document/orgapp/server-api-error-codes-1). Dept operations overview: [Contact dept operations](https://open.dingtalk.com/document/orgapp/operations-related-to-address-book-departments).

**Fixed behavior (not configurable in config.json)**

- User group names have no prefix; they match IM department names.
- User matching is fixed to **username** (WeCom/Feishu: IM account ID; **DingTalk: mobile first**, then name pinyin, then `userid`).

### Enable Module

1. Go to **Administration → General → Modules**.
2. Click **Scan directory** to scan for new modules.
3. Find the **IM Sync Assistant** module and enable it.
4. Refresh the page. **IM Sync Assistant** will appear under the **Users** menu.

## Usage

1. Open **Users → Sync Settings** and add/enable a sync setting (WeCom / Feishu / DingTalk).
2. Open **Users → IM Sync Assistant → IM Sync**; the enabled setting is shown at the top (use the **Sync settings** button to jump to the management page).
3. Click **Preview departments** to review matching results.
4. Click **Sync all departments** to sync IM departments into Zabbix user groups.
5. Click **Sync all users** to create/match IM members as Zabbix users.

Recommended order: **Configure and enable sync setting → Preview departments → Sync all departments → Sync all users**.

## Permissions

- Viewing the page and syncing departments requires **Zabbix Admin** privileges.
- **Sync Settings** (CRUD credentials, enable/disable) requires **Super Admin** (involves app secrets).
- **Sync all users** (with auto user creation) requires **Super Admin** (Zabbix 7 `user.create` is Super Admin only).
- IM apps must have contact/address book API permissions enabled.

## Notes

- **Protection policy**: Built-in or manually created users/groups are **never deleted or modified**; the module only manages objects recorded in `data/sync_registry.json`.
- **User types**:
  - `created`: Auto-created by the module; can be added/updated/removed with IM changes.
  - `linked`: Matched to existing Zabbix users; only group membership is synced; **accounts are not deleted**.
- **Group conflicts**: If a group name already exists but was not created by the module, that department is skipped and logged.
- **Manual members**: Non-module users manually added to module-managed groups are preserved on sync.
- **Auto-created users**: Passwords are auto-generated (12 chars, upper/lower/digits), shown **once** in "Sync all users" results — save promptly; Zabbix 7+ also needs `default_roleid`; run sync as Super Admin.
- **Manual user deletion**: If you delete a synced user in Zabbix, the next sync clears registry entry and recreates the user.
- **Multi-department users**: Users in multiple IM departments are added to multiple Zabbix user groups.
- **Network access**: Zabbix Web must reach the IM platform APIs.
- **DingTalk username changes**: If users were previously synced by `userid`, switching to mobile/pinyin may create new accounts instead of matching old ones; preview before sync.
- **PHP extensions**: DingTalk name-to-pinyin requires `iconv` or `mbstring` (either is sufficient).

## Development

The module is built on the Zabbix module framework:

- `manifest.json`: Module configuration
- `Module.php`: Menu registration (IM Sync / Sync Settings)
- `actions/Im.php`: Main page logic
- `actions/ImSync.php` / `ImSyncUsers.php`: Sync APIs
- `actions/ImPreview.php`: Preview API
- `actions/ImSettings.php` / `ImSettingsSave.php` / `ImSettingsDelete.php` / `ImSettingsEnable.php`: Sync settings CRUD
- `views/im.php` / `im.settings.php`: Page views
- `lib/ImSyncService.php`: Core sync logic
- `lib/WeComClient.php` / `FeishuClient.php` / `DingTalkClient.php`: IM platform clients
- `lib/PinyinHelper.php`: Chinese name to pinyin (DingTalk when mobile is absent)
- `lib/ConfigManager.php`: Configuration (multi `settings`, single enable, legacy migration)
- `lib/SyncRegistry.php`: Registry of module-created objects
- `lib/LanguageManager.php`: Internationalization
- `data/config.json`: Connection settings (`settings` list)
- `data/sync_registry.json`: Sync registry (generated at runtime)

### Internationalization

All UI strings are managed in `lib/LanguageManager.php` for **Chinese (zh_CN)** and **English (en_US)**. Language detection follows Zabbix: user language → system default → `en_US`. Menus, pages, dialogs, API error messages, and IM client exceptions are translated; raw error codes/messages from third-party APIs may still appear in English.

## License

This project follows the Zabbix license. For details, see [Zabbix License](https://www.zabbix.com/license).
