# Zabbix JumpServer Module

[中文](README.md)

## ✨ Version Compatibility

This module is compatible with Zabbix 6.0 / 7.0+ / 8.0+.

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x
- ✅ Zabbix 8.0.x

**Compatibility Note**: The module includes intelligent version detection that automatically adapts to different Zabbix API versions and class libraries, requiring no manual configuration.

## Description

This is a Zabbix frontend module that synchronizes (pushes) hosts and host groups from Zabbix to a JumpServer bastion host, and lets you jump from Zabbix to the JumpServer connect page for a host with one click. It adds a **JumpServer** menu under the **Inventory** section of Zabbix Web.

When pushing hosts, it automatically:

- Creates JumpServer nodes for Zabbix host groups (auto-created if missing)
- Detects the host platform (Linux / Windows) from the Zabbix "Operating system" item
- Creates or updates JumpServer assets, and writes the JumpServer asset ID back to the Zabbix host as a tag for the "Connect" button to use

![1](images/1.png)

## Features

- **Group / host dropdown filters**: The top of the page provides a host group dropdown and a host dropdown, showing all host groups and all hosts by default
- **Alarm status filter**: Filter hosts by severity (Disaster, High, Average, Warning, Information, Not classified, OK); all states shown by default
- **IP / hostname search**: Quickly search hosts by IP address or hostname
- **Push all host groups**: Push all Zabbix host groups as JumpServer nodes with one click; missing ones are created automatically
- **Push all hosts**: Push all Zabbix hosts as JumpServer assets with one click (auto node assignment and platform detection)
- **Fetch JumpServer asset IDs**: Pull all assets from JumpServer, match Zabbix hosts by IP, and write asset IDs back to host tags
- **Single host push / re-push**: Unpushed hosts can be pushed individually; hover the status badge on pushed hosts to re-push
- **Alarm status display**: The "Alarm Status" column shows counts per severity (e.g. "High 2", "Warning 1"); shows "OK" when there are no alarms
- **Expandable alarm details**: Click the expand toggle at the start of each row to view all active alarms for that host (severity, name, time)
- **One-click connect**: Pushed hosts show a "Connect" button in the last column linking to the JumpServer connect page for that host
- **Asset ID persistence**: The JumpServer asset ID is stored as a Zabbix host tag (`jumpserver_asset_id`)
- **Internationalization**: Supports Chinese and English interfaces

## Configuration

Credentials are stored in `data/config.json`:

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

| Field | Description |
|-------|-------------|
| `jumpserver_url` | JumpServer base URL |
| `access_key_id` | JumpServer user AccessKey ID |
| `access_key_secret` | JumpServer user AccessKey Secret |
| `org_id` | Organization ID, defaults to the DEFAULT org |
| `connect_url_template` | Connect URL template; `{base_url}` and `{asset_id}` are substituted |
| `account_template_id` | Account template ID; leave empty to skip automatic account linking |
| `verify_ssl` | Whether to verify the HTTPS certificate |

**Getting an AccessKey**: Log in to JumpServer → User interface → Profile → API Key (AccessKey); create one to get the ID and Secret. The module authenticates using HTTP Signature (hmac-sha256). See the [JumpServer REST API docs](https://docs.jumpserver.org/zh/v4/dev/rest_api/).

### Automatic account linking

When `account_template_id` is set, the module automatically links a login account via the account template **when creating** a JumpServer asset (existing assets are not modified):

1. Create an **account template** in JumpServer (Accounts → Account templates) with a username and password or SSH key.
2. Copy the account template ID and set it as `account_template_id` in `data/config.json`.
3. When pushing hosts, the module embeds the template in the `accounts` field **only on new asset creation** (`POST /api/v1/assets/hosts/`, as `[{"template": "<template-id>"}]`); JumpServer fills in the username/secret from the template and links the account.

Notes:

- **Create only**: Updates to existing assets do not include the `accounts` field, so linked accounts on JumpServer are not affected.
- The user behind the AccessKey must have account management (`accounts.add_account`) permission.
- The credentials come from the template; if you need JumpServer to actually create/change the system account on the target host, enable auto-push in the account template and configure a privileged account.

### Reverse sync asset IDs

Click **Fetch JumpServer asset IDs** to:

1. Pull all host assets from JumpServer (`/api/v1/assets/hosts/`).
2. Match asset `address` (IP) to each Zabbix host's main interface IP.
3. Write the matched asset ID to the host's `jumpserver_asset_id` tag (skip if the same ID is already set).

Useful when assets already exist in JumpServer but Zabbix hosts do not yet have the asset ID tag.

## Installation

### Install Module

```bash
# Zabbix 6.0 / 7.0 deployment
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/modules/

# Zabbix 7.4 / 8.0 deployment
git clone https://github.com/X-Mars/zabbix_modules.git /usr/share/zabbix/ui/modules/
```

### ⚠️ Modify manifest.json

```bash
# ⚠️ For Zabbix 6.0, modify manifest_version
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_jumpserver/manifest.json
```

### Configure credentials

Edit `zabbix_jumpserver/data/config.json` and fill in the JumpServer URL and AccessKey. Make sure the Zabbix web user can read this file.

### Enable Module

1. Go to **Administration → General → Modules**.
2. Click **Scan directory** to scan for new modules.
3. Find the "Zabbix JumpServer" module and enable it.
4. Refresh the page. The module will appear under the **Inventory** menu as "JumpServer".

## Notes

- **Network**: The Zabbix web server must be able to reach the JumpServer URL.
- **Permissions**: Viewing the page requires regular user privileges; pushing and fetching asset IDs require Zabbix Admin privileges.
- **Platform detection**: Host platform relies on the Zabbix "Operating system" item value; defaults to Linux when missing.
- **Tag write-back**: After pushing, the `jumpserver_asset_id` tag is written to the Zabbix host; existing tags are preserved.
- **Credential security**: `data/config.json` contains sensitive credentials; control file permissions and avoid committing it to public repositories.

## Development

The module is built on the Zabbix module framework. File structure:

- `manifest.json`: Module configuration
- `Module.php`: Menu registration (Inventory → JumpServer)
- `actions/Jumpserver.php`: Main page business logic (filters, search, alarm stats, host table)
- `actions/JumpserverPush.php`: Push hosts/groups and write back the asset ID
- `actions/JumpserverFetchIds.php`: Fetch asset IDs from JumpServer and write back tags by IP
- `views/jumpserver.php`: Page view
- `lib/JumpserverClient.php`: JumpServer API client (HTTP Signature auth)
- `lib/ConfigManager.php`: Reads credentials from data/config.json
- `lib/LanguageManager.php`: Internationalization language management
- `lib/ViewRenderer.php`: View rendering utilities
- `lib/ZabbixVersion.php`: Version compatibility utilities
- `data/config.json`: JumpServer connection credentials

For extensions, refer to [Zabbix module documentation](https://www.zabbix.com/documentation/7.0/en/devel/modules).

## License

This project follows the Zabbix license. For details, see [Zabbix License](https://www.zabbix.com/license).
