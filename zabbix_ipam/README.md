# Zabbix IPAM 模块

[English](README_en.md)

## ✨ 版本兼容性

本模块兼容 Zabbix 6.0 / 7.0+ / 8.0+ 版本。

- ✅ Zabbix 6.0.x
- ✅ Zabbix 7.0.x
- ✅ Zabbix 7.4.x
- ✅ Zabbix 8.0.x

**兼容性说明**：模块内置 Zabbix 版本检测与自定义 `LanguageManager`，自动适配不同命名空间、菜单接口及用户语言设置，无需手动选择中英文。

## 描述

这是一个 Zabbix 前端 IP 地址管理模块，用于集中维护 IPv4 地址段、执行异步 ICMP 存活扫描，并根据 Zabbix 主机接口 IP 自动建立主机关联。模块在 Zabbix Web 的 **Inventory（资产记录）→ IPAM** 下提供“IP 管理”“IP 详情”和“任务管理”三个页面。

扫描仅使用 ICMP，不会连接目标 TCP 端口。大网段会自动分片，并由后台 PHP CLI 任务执行，避免阻塞 Zabbix Web 请求。

![1](images/1.png)
![2](images/2.png)
![3](images/3.png)

## 功能特性

- **IP 段管理**：支持 CIDR 和 IPv4 起止地址格式，可添加、修改和删除 IP 段
- **自动筛选**：搜索框按 Enter 自动筛选，下拉框选择后自动提交
- **ICMP 存活扫描**：使用 `fping` 检测地址是否可达，不进行 TCP 端口探测
- **异步任务**：Web 页面创建任务后由 PHP CLI 在后台执行
- **分片与多进程**：默认每 64 个地址一个分片，支持 `pcntl_fork` 并行扫描
- **实时进度**：显示已扫描数量、存活数量、完成分片数和任务状态
- **定时扫描**：扫描间隔由 crontab 表达式统一设置，每次执行时扫描所有已启用的 IP 段
- **IP 使用矩阵**：以绿色和灰色方块展示地址存活状态
- **IP 详情列表**：展示 IP、IP 段名称、IP 段、存活状态及主机关联信息
- **主机自动关联**：根据 Zabbix 主机接口 IPv4 地址自动匹配主机
- **最新数据跳转**：列表主机名称和已关联的矩阵存活 IP 均可进入对应主机的最新数据页面
- **列表分页**：所有列表均支持分页和每页显示数量选择
- **国际化支持**：自动跟随 Zabbix 用户语言，支持简体中文和英文
- **响应式设计**：取消固定最大宽度，适配普通、宽屏和窄屏浏览器
- **安全限制**：仅 Zabbix 管理员可访问，输入均经过 IPv4 和数值校验

## 工作方式

```text
IP 管理页面 / crontab
        ↓
创建地址段扫描任务
        ↓
PHP CLI 后台进程
        ↓
按 64 个地址分片 → fping ICMP 探测
        ↓
JSON 结果与任务进度 → IP 矩阵 / IP 详情 / 任务管理
```

- 在 Web 页面点击“开始扫描”时，模块通过 PHP `exec()` 和 `nohup` 启动独立的 PHP CLI 进程。
- 定时扫描由 `cli/scan_cron.php` 直接执行；每次 cron 运行都会为所有已启用且没有待处理/运行中任务的地址段创建扫描任务。
- CLI 环境支持 `pcntl_fork()` 时最多并行运行 4 个工作进程；不支持时自动串行执行。
- 扫描状态和结果以 JSON 文件保存，写入时使用文件锁及临时文件原子替换，便于 Web 与 cron 同时访问。

## 安装步骤

### 安装模块

```bash
# Zabbix 6.0 / 7.0 常用模块路径
cp -a zabbix_ipam /usr/share/zabbix/modules/

# Zabbix 7.2+ / 7.4 / 8.0 常用模块路径
cp -a zabbix_ipam /usr/share/zabbix/ui/modules/
```

实际路径请以 Zabbix 前端配置中的 `$ZBX_MODULES_DIR` 为准。

### ⚠️ 修改 manifest.json 文件

```bash
# ⚠️ 仅在 Zabbix 6.0 无法识别 manifest_version 2.0 时执行
sed -i 's/"manifest_version": 2.0/"manifest_version": 1.0/' zabbix_ipam/manifest.json
```

### 配置目录权限

运行 Web 服务和 cron 的用户都必须能够读写 `data/`、`data/scan_tasks/` 和 `data/results/`。建议使用同一用户；如使用不同用户，请通过共同用户组授予读写权限。以下命令中的路径应替换为模块的实际安装路径。

```bash
# Debian / Ubuntu 示例
chown -R www-data:www-data zabbix_ipam/data
chmod -R 770 zabbix_ipam/data

# RHEL / Rocky Linux 示例
chown -R apache:apache zabbix_ipam/data
chmod -R 770 zabbix_ipam/data
```

### 安装扫描依赖

```bash
# Debian / Ubuntu
apt install fping php-cli php-pcntl

# RHEL / Rocky Linux
dnf install fping php-cli php-process
```

`php-pcntl` 或 `php-process` 用于多进程扫描；未安装 `pcntl` 时会自动降级为后台串行扫描。PHP CLI 和 `fping` 是扫描的必要依赖。

安装后建议使用 Web 服务用户检查运行环境：

```bash
# Debian / Ubuntu
sudo -u www-data /usr/bin/php -r 'echo PHP_SAPI, PHP_EOL;'
sudo -u www-data /usr/bin/fping -c 1 -t 350 127.0.0.1

# RHEL / Rocky Linux
sudo -u apache /usr/bin/php -r 'echo PHP_SAPI, PHP_EOL;'
sudo -u apache /usr/sbin/fping -c 1 -t 350 127.0.0.1
```

如需从 Web 页面手动启动扫描，请确认 PHP 未禁用 `exec`，并允许 Web 服务用户运行 PHP CLI。若安全策略禁止 `exec`，仍可仅使用下文的 cron 定时扫描入口。

### 启用模块

1. 转到 **Administration → General → Modules**。
2. 点击 **Scan directory** 按钮扫描新模块。
3. 找到 “IPAM” 模块并启用。
4. 刷新页面，模块将在 **Inventory** 菜单下显示为“IP 管理”，包含“IP 管理”“IP 详情”和“任务管理”三个子项。

## 定时扫描

`cli/scan_cron.php` 是唯一定时扫描入口，crontab 的执行频率就是扫描间隔。以下示例每 5 分钟扫描一次所有已启用的 IP 段；已有待处理或运行中的任务不会被重复创建。请勿使用 `root` 运行扫描。

写入 `/etc/cron.d/zabbix-ipam` 时需要包含运行用户：

```cron
*/5 * * * * apache /usr/bin/php /usr/share/zabbix/modules/zabbix_ipam/cli/scan_cron.php >> /var/log/zabbix/ipam-cron.log 2>&1
```

Debian / Ubuntu 通常将运行用户改为 `www-data`。如果使用 `crontab -u apache -e` 或 `crontab -u www-data -e`，则不要在表达式中再次写用户名：

```cron
*/5 * * * * /usr/bin/php /usr/share/zabbix/modules/zabbix_ipam/cli/scan_cron.php >> /var/log/zabbix/ipam-cron.log 2>&1
```

Zabbix 7.2+ / 7.4 / 8.0 的脚本路径通常是 `/usr/share/zabbix/ui/modules/zabbix_ipam/cli/scan_cron.php`。请确保日志目录也允许对应用户写入；任务状态文件和工作锁会避免同一 IP 段被重复提交。

### 手动验证 cron 入口

```bash
# 按实际环境选择 apache 或 www-data，并替换模块路径
sudo -u apache /usr/bin/php /usr/share/zabbix/modules/zabbix_ipam/cli/scan_cron.php
```

命令无输出且任务页出现新任务通常表示执行成功；错误信息会输出到标准错误流。

## 页面说明

- **IP 管理**：添加和维护 IP 段、启动扫描、查看扫描任务和打开 IP 使用矩阵
- **IP 详情**：按关键词、IP 段、IP 状态和主机关联状态筛选所有地址
- **任务管理**：按 IP 段名称、任务状态或关键词筛选并查看待处理、运行中、已完成、失败或已停止的后台扫描任务

点击“存活 IP / IP 总数”可打开 IP 使用矩阵。绿色表示最近一次 ICMP 扫描存活，灰色表示不可达或尚未扫描；绿色地址已匹配 Zabbix 主机时可以直接点击进入该主机的最新数据页面。

## 注意事项

- **授权范围**：请仅扫描已获授权的网络。
- **地址限制**：仅支持 IPv4，单个 IP 段最多 65,536 个地址。
- **系统权限**：Zabbix Web 用户和 cron 用户必须能够写入模块 `data/` 目录。
- **ICMP 权限**：请确保服务器防火墙允许 ICMP，并允许 `fping` 正常运行。
- **命令执行**：Web 手动扫描依赖 PHP `exec()`；使用 cron 时不依赖 Web 请求启动后台进程。
- **性能考虑**：大网段会分片处理；并行工作进程默认最多为 4 个。
- **主机匹配**：关联结果来自当前用户有权访问的 Zabbix 主机接口数据。
- **数据存储**：当前使用 JSON 文件，生产环境请定期备份 `data/` 目录。

## 故障排查

- **点击开始扫描后立即失败**：检查 PHP CLI 是否存在、PHP `exec()` 是否被禁用，以及 `data/scan_tasks/` 是否可写。
- **所有地址均显示不可达**：以 Web/cron 用户直接运行 `fping`，确认安装路径、执行权限、防火墙和 ICMP 策略。模块会依次查找 `/usr/sbin/fping`、`/usr/bin/fping` 和 `/sbin/fping`。
- **任务一直处于待处理状态**：检查 `data/scan_tasks/<任务 ID>.log`、PHP CLI 路径和 SELinux/AppArmor 限制。
- **cron 没有创建任务**：确认地址段已启用，并检查是否已经存在同一地址段的 `pending` 或 `running` 任务。
- **页面无法保存地址段**：检查 `data/` 所有权与权限；模块需要创建锁文件和临时 JSON 文件。
- **没有关联到主机**：确认 Zabbix 主机接口使用的是相同 IPv4 地址，并确认当前登录用户有权查看该主机。

## 开发

插件基于 Zabbix 模块框架开发。主要文件结构：

- `manifest.json`：模块信息与页面路由
- `Module.php`：Inventory 菜单注册
- `actions/IpManager.php`：IP 段列表业务逻辑
- `actions/IpDetail.php`：IP 详情、主机关联筛选与分页
- `actions/IpScan.php`：扫描任务列表业务逻辑
- `actions/IpAjax.php`：保存、删除、扫描及矩阵 AJAX 接口
- `views/ip.manager.php`：IP 管理页面
- `views/ip.detail.php`：IP 详情页面
- `views/ip.scan.php`：任务管理页面
- `lib/HostMatcher.php`：Zabbix 主机接口 IP 匹配
- `lib/IpScanner.php`：ICMP、分片和多进程扫描核心
- `lib/IpStorage.php`：JSON 数据读写
- `lib/TaskManager.php`：后台任务和定时调度
- `lib/LanguageManager.php`：中英文语言管理
- `lib/ViewRenderer.php`：视图渲染
- `lib/ZabbixVersion.php`：版本兼容检测
- `assets/`：页面样式与交互脚本
- `cli/scan_cron.php`：cron 扫描入口

如需扩展，可参考 [Zabbix 模块开发文档](https://www.zabbix.com/documentation/7.0/en/devel/modules)。

## 许可证

本项目遵循 Zabbix 的许可证。详情请见 [Zabbix 许可证](https://www.zabbix.com/license)。
