# 开发与交付记录

实现目录遵循用户指定的 `zabbix_export_import`。唯一 ID 为 `zabbix-import-export`，命名空间 `Modules\ZabbixImportExport`。

实际环境：Zabbix 7.0.32 / PHP 8.3.36。已安装并启用到 `/usr/share/zabbix/modules/zabbix_export_import`；原 CMDB 模块未修改。

实现内容、安装及使用说明见 [README.md](README.md) 和 [README_en.md](README_en.md)。格式定义与六份示例分别位于 `schemas/`、`examples/`。测试与实际验证范围见 [tests/integration.md](tests/integration.md)。

已核对目标源码：CModule、CController、CModuleManager、CModule API 服务、CHost、CItem/CItemGeneral、CControllerHostList、configuration.host.list.php 及其 JavaScript、CTagFilterFieldHelper、hostgroups.inc.php。主机组过滤中特别处理了 Zabbix 前端共享可变 API 包装器：必须先解析子组，再选择 Host 服务。

官方参考：
- https://www.zabbix.com/documentation/7.4/en/devel/modules
- https://www.zabbix.com/documentation/7.4/en/manual/api/reference/host/get
- https://www.zabbix.com/documentation/7.4/en/manual/api/reference/item/object
- https://www.zabbix.com/documentation/7.4/en/manual/api/reference/module/create
- https://raw.githubusercontent.com/zabbix/zabbix/release/6.0/ui/include/classes/core/CModule.php
- https://raw.githubusercontent.com/zabbix/zabbix/release/6.0/ui/include/classes/core/CModuleManager.php
- https://raw.githubusercontent.com/zabbix/zabbix/release/6.0/ui/include/classes/mvc/CController.php

版本边界：真实运行验证为 7.0.32；6.0/7.4 的独立运行环境尚未验收，8.0 不声明兼容。模块注册迁移始终要求先部署代码文件。

1.1.0：完成导入安全审查与 CMDB 风格兼容层重构，详见 [SECURITY.md](SECURITY.md)。不修改 CMDB；执行类配置改为默认拦截、Super admin 显式授权。

1.2.0：按用户最新要求移除三页原生大型过滤表单，默认全部授权主机；采用 Element Plus 配色的隔离样式、表格顶部导出、导入抽屉、响应式布局与焦点管理。监控项默认全部来源，旧隐含 Profile 条件不再参与页面查询。

1.3.0：按用户纠正，以模板迁移替换前端模块注册迁移。模板调用原生 configuration export/importcompare/import；模板分组、依赖拓扑、UUID 身份检查、禁止顶层对象夹带、执行类审批；全选改为 all + exclusions 并始终由服务器重算授权集合；i18n 使用用户语言/系统默认与独立词条目录。

1.3.1：导出使用独立数据副本，禁止改写列表记录或提前移除 `_selection`；异常后的分页仍正常执行。序列化和时区计算成功后才发送下载头。模板列表不读取未使用的 description 大文本。统一文件上限 10 MiB，预览创建上限 32 MiB、状态上限 64 MiB；全局配额不变。
