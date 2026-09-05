# Zabbix IPAM

面向 Zabbix 6.0、7.x 与 8.0 的前端 IP 地址管理模块。它管理 CIDR/IPv4 起止地址段，通过 ICMP 异步扫描存活主机，并把结果与 Zabbix 主机接口自动匹配。

## 安装

1. 将整个 `zabbix_ipam` 目录复制至模块目录：Zabbix 6.0/7.0 通常为 `/usr/share/zabbix/modules`；7.2+/8.0 请以 `zabbix.conf.php` 的 `$ZBX_MODULES_DIR` 为准。
2. 让 Web 服务器用户可写 `zabbix_ipam/data`（包括 `scan_tasks` 与 `results`）。例如：`chown -R www-data:www-data zabbix_ipam/data`。
3. 在 **Administration → General → Modules** 启用 IPAM。入口位于 **Inventory → IP 管理 / IPAM**。

## 定时扫描

每个 IP 段可设置独立扫描间隔（分钟）；启用后由唯一 cron 入口检查到期任务：

```cron
*/5 * * * * www-data /usr/bin/php /usr/share/zabbix/modules/zabbix_ipam/cli/scan_cron.php >> /var/log/zabbix/ipam-cron.log 2>&1
```

扫描任务使用状态文件避免同一 IP 段重复提交。Web 页面启动扫描时会后台执行同一 CLI；前端每 1.5 秒轮询任务进度。若后台启动在受限 PHP 环境中被禁止，可仅使用以上 cron 调度。

## 扫描与限制

CIDR 会默认按 64 个地址分片（可通过 AJAX 参数 `shard_size` 指定）。CLI 上有 `pcntl` 时最多同时运行 4 个 fork 子进程，每个分片以独立临时 JSON 结果文件回传；没有 `pcntl` 时安全地串行回退。扫描仅使用 `fping` ICMP 探测，不会连接目标 TCP 端口。后台任务及 cron 需要 PHP CLI；多进程需要 `php-process`/`pcntl`。

仅接受 IPv4，单个范围最多 65,536 个地址。请只扫描已获授权的网络；生产中建议将 `max_execution_time`、防火墙速率和 cron 的运行用户纳入运维策略。模块操作限制为 Zabbix 管理员。

JSON 存储封装在 `lib/IpStorage.php`，可在后续替换为数据库实现而不改变控制器和扫描器。

## 页面使用

模块提供三个业务页面：**IP 管理**、**IP 详情**与**任务管理**。IP 管理页上方可按名称/CIDR、IP 段和状态筛选；“添加 IP 段”和“修改”共用同一弹窗，只需填写名称、IPv4 CIDR（或起止地址）、扫描间隔并选择是否启用定时扫描。每行均可启动扫描、查看扫描任务、修改或删除。

点击“存活 IP / IP 总数”会打开 IP 使用矩阵。绿色方块表示最近一次扫描中可通过 ICMP 到达，灰色方块表示不可达或尚未扫描；绿色地址已匹配 Zabbix 主机时可直接点击进入主机详情。IP 详情页按 IP、IP 段、存活状态和主机关联状态筛选所有地址，并自动按照 Zabbix 主机接口 IP 建立关联。任务管理页集中显示后台任务状态、扫描进度、存活数及已完成分片数；页面会自动轮询运行中的任务，无需手动刷新。
