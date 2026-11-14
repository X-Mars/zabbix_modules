# Zabbix MySQL 备份模块

[English](README_en.md)

## 版本兼容性

- Zabbix 7.0.x（已在 7.0.32 验证）
- Zabbix 8.0.x（已在 8.0.0 验证）

支持使用 MySQL 或 MariaDB 的 Zabbix 前端。

## 描述

这是一个从 Zabbix Web 导出数据库备份的前端模块。它复用前端当前的数据库连接，将数据库基础表的结构和数据导出为 SQL 文件。模块入口位于 **管理 → MySQL 备份**，仅 Zabbix 超级管理员可使用。

## 功能特性

- **选表**。查看表名、存储引擎和预估大小（数据与索引合计），按表名筛选并批量选择。
- **导出模式**。支持表结构和数据、仅表结构、仅数据。未选中的表始终保留 `CREATE TABLE`，包括字段、索引和约束。
- **输出位置**。可在浏览器下载或保存到服务器。选择浏览器下载时，实际大小不超过 500 MB（500,000,000 字节）的文件直接下载，更大的文件自动保存到指定目录。
- **时区与语言**。文件名格式为 `zabbix_backup_yyyy-mm-dd_hh-mm-ss.sql`，时间使用当前用户的时区。页面跟随前端语言设置，支持中英文。
- **界面**。使用本地 CSS 和 JavaScript 实现响应式 Element Plus 风格。
  
![1](images/1.png)

## 安装步骤

### 安装模块

将本目录下的 `manifest.json`、`Module.php`、`actions/`、`assets/`、`lib/` 和 `views/` 复制到前端的 `modules/zabbix_backup_mysql/`。常见部署路径如下。

| Zabbix 版本 | 模块目录 |
| --- | --- |
| 7.0 | `/usr/share/zabbix/modules/zabbix_backup_mysql/` |
| 8.0 | `/usr/share/zabbix/ui/modules/zabbix_backup_mysql/` |

### 启用模块

1. 使用超级管理员账号进入 **管理 → 一般 → 模块**（**Administration → General → Modules**）。
2. 选择 **扫描目录**（**Scan directory**），启用 **Zabbix MySQL Backup**。
3. 刷新页面并打开 **管理 → MySQL 备份**。

## 使用方法

1. 在 **导出范围** 中选择全部表或选中的表。可用搜索框缩小范围，用表头复选框选择筛选出的全部表。
   勾选 **排除历史记录** 后，所有以 `history` 开头的表均仅导出结构。
2. 选择 **导出内容**。

   | 模式 | 选中的表 | 未选中的表 |
   | --- | --- | --- |
   | 表结构和数据 | `CREATE TABLE` 和 `INSERT` | `CREATE TABLE` |
   | 仅表结构 | `CREATE TABLE` | `CREATE TABLE` |
   | 仅数据 | `INSERT` | `CREATE TABLE` |

3. 选择 **浏览器下载** 或 **保存到服务器**，填写服务器目录并点击 **导出 SQL**。保存到服务器后，页面会显示绝对路径和文件大小。

### 配置服务器备份目录

默认目录为 `/tmp/zabbix`。该目录不存在时，模块会尝试创建。要允许其他位置，可在模块部署目录添加 `config.local.php`：

```php
<?php
return ['/var/lib/zabbix/mysql-backups'];
```

页面可填写配置的目录或其下已有的子目录。备份目录须位于 Web 根目录之外，归 Web 服务账号所有，权限为 `0700`。导出的文件权限为 `0600`，同名文件会保留。容器部署时，目录位于前端容器内，可挂载持久卷。

## 注意事项

- **备份内容**。完整备份包含用户和凭据。SQL 使用普通 `CREATE TABLE` 和带显式列名的 `INSERT`。导入「仅数据」模式生成的文件时，选中的表需要已有兼容结构，未选中的表需要能在目标库创建。
- **数据一致性**。InnoDB 数据在同一事务快照下读取。
- **大文件**。模块先在 PHP 临时目录生成完整文件。保存到服务器还需要目标目录有足够的磁盘空间。导出耗时受 Web 服务器、代理和 PHP-FPM 的超时设置影响。

## 开发

- `manifest.json`、`Module.php` — 模块注册和菜单入口
- `actions/BackupMysql.php`、`views/backupmysql.php` — 请求处理与页面渲染
- `lib/SqlExport.php`、`lib/BackupFile.php` — SQL 导出与文件保存
- `lib/LanguageManager.php`、`assets/` — 界面翻译与前端资源
- `tests/checks.php` — SQL 导出与 500 MB 边界回归检查
