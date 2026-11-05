# 导入安全审查（1.1.0）

## 审查结论与边界

导入文件仅作为受限的 CSV/JSON 数据解析，不执行上传内容，不调用 `eval`、`unserialize`、Shell 或解压工具，也不按上传文件名写入 Web 目录。前端模块仅注册**预先部署的代码**，不能通过数据文件安装 PHP/JS 文件。

需要特别区分两类执行：上传内容不会在本模块作为 PHP 执行；但合法 Zabbix 配置中的脚本、命令、HTTP 请求、JavaScript 预处理等，在创建或启用监控项后可能由 Zabbix 执行。因此 1.1.0 增加执行类配置的默认拦截，不能把“JSON 可解析”当作“配置安全”。

## 加固内容

- 新增 `UploadValidator`，同时校验实际 PHP 上传临时文件、扩展名、文件名、真实大小、`fileinfo` 内容类型、UTF-8、数据容器及模式。拒绝 PHP/PHAR/PHTML、可执行文件、压缩包、双扩展名、伪装的非文本内容和超限数据。不会信任客户端声明的 MIME 类型。
- 仅读取 `is_uploaded_file()` 为真的临时文件；不接受任意服务器文件路径；使用实际文件大小和有上限的流读取。保留合法的带引号 CSV 表头。
- 默认拦截外部检查、数据库、SSH/Telnet、HTTP、Script/Browser、`system.run` 及 JavaScript 预处理。更新时也检查保留的目标预处理，避免通过状态更新激活被忽略的代码。
- 执行类配置仅可由 **Super admin** 在预览时勾选授权。授权绑定到已预览的数据；确认时重新检查权限和配置。预览显示代码差异及风险提示。该授权不表示代码已被判定无害，只应给予可信来源。
- 模块配置中的 PHP 标签和常见代码/命令相关键也默认拦截；普通配置的语义仍由已部署的扩展决定，应逐项审查，不应依赖通用关键字检查代替扩展自身的安全性。
- 恢复主机本地项时，项的 `host` 必须与所属主机记录一致，禁止在主机文件中夹带其他主机的写入；预览包含待恢复的本地项内容。
- 模块目录及 manifest 均限制在规范路径内，拒绝目录/manifest 符号链接、路径穿越和超大清单。
- 预览存储必须位于 Zabbix 和已知 Web 根目录之外，目录 0700、文件 0600，检查所有者和符号链接，绑定用户/会话，原生 CSRF/SID 始终保护 POST。
- 预览 JSON 使用 `JSON_HEX_TAG`：保留业务值，但在磁盘文件中把 `<`、`>` 编为 Unicode 转义，不保留原始 PHP/HTML 标签。显示仍使用原生 HTML 转义。
- 每个会话最多保留 5 份预览，淘汰旧预览；创建数据上限 16 MiB、持久状态上限 32 MiB；全局最多 100 份、128 MiB，使用配额锁减少并发绕过，清理过期数据。

## 验证

```sh
php tests/uploads.php
php tests/storage.php
for v in 6.0 7.0 7.4 8.0 fallback6 fallback7 unknown; do php tests/compatibility.php "$v"; done
python tests/upload_e2e.py --url http://YOUR-ZABBIX/zabbix/ --username Admin
```

- 上传/执行策略单元检查：51 项通过。
- 预览存储/模块路径检查：15 项通过。
- 版本与接口分支检查：87 项通过；这是隔离的模拟接口测试，不代表其他版本实机验收。
- 既有安全/筛选检查：50 项通过；格式检查：26 项通过。
- 目标环境 Zabbix 7.0.32 / PHP 8.3.36 上，恶意上传/执行策略实测 36 项通过，原业务回归 58 项通过。
- 所有 26 个 PHP 文件语法检查通过。

真实测试上传 PHP、改名 PHP、双扩展名、PHP/PHTML 双扩展名、ZIP/路径穿越包、XML、PHP 序列化字符串、HTML、NUL 及超大文件；证明不生成代码执行标记。合法 JSON 内的 PHP 样式说明文字保持为数据且被转义显示。授权路径只创建了一条**禁用**的无副作用脚本监控项，随后删除。未在服务器或 Agent 上执行不可信上传脚本。

本次未提供独立的 Zabbix 6.0 / 7.4 实机环境。兼容层采用精确版本常量、CMDB 风格的原生类探测以及接口能力探测；保持 6.0 的 manifest 1/SID/旧模块路径与 7.x 的 manifest 2/CSRF/新模块管理器分支。未知环境明确拒绝，不使用空模块基类或伪造令牌。8.0 的分支测试只验证版本分类，不声明 8.0 运行兼容。

## 1.3.0 template path

The former module-registration route is removed from the manifest and navigation. Template uploads accept bounded JSON/CSV data, including native Zabbix JSON, and never upload PHP/module files. Each record is restricted to one named template; unrelated top-level hosts/maps/media/images and nested numeric source IDs are rejected. Native import comparison is read-only. Confirmation repeats policy, identity and write-permission checks before native configuration import. Native template UUIDs may not resolve to another technical name. Scripts, commands, active requests, JavaScript preprocessing and Web scenarios require explicit Super admin approval. Native secret macros are redacted, and preview differences are redacted again. Template updates never enable `deleteMissing`.

All-filtered selection is represented as a mode plus exclusions. Export recomputes the current user's filtered API result before applying exclusions; client selection cannot expand permissions. The module reads locale only from Zabbix's user/default settings, validates locale identifiers before loading a local catalog and escapes translated text through native HTML components.
