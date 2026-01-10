# Zabbix Rock - 兼容性说明

## 支持的 Zabbix 版本

| Zabbix 版本 | 支持状态 | 备注 |
|------------|---------|------|
| 6.0.x LTS  | ✅ 支持  | 完全兼容 |
| 6.2.x      | ✅ 支持  | 完全兼容 |
| 6.4.x      | ✅ 支持  | 完全兼容 |
| 7.0.x LTS  | ✅ 支持  | 完全兼容 |
| 7.2.x      | ✅ 支持  | 完全兼容 |
| 7.4.x      | ✅ 支持  | 完全兼容 |

## API 兼容性处理

模块使用 `ZabbixVersion` 类自动检测当前 Zabbix 版本，并根据版本差异调整：

### 主机组 API

- **Zabbix 6.x**: 使用 `HostGroup` API
- **Zabbix 7.x**: 使用 `HostGroup` API（向后兼容）

### 页面渲染

- **Zabbix 6.x**: 使用 `CWidget` 类
- **Zabbix 7.x**: 使用 `CHtmlPage` 类

### 主机标签

所有版本均支持主机标签功能，模块使用以下标签存储机柜位置信息：

- `rack_room` - 机房名称
- `rack_name` - 机柜名称
- `rack_u_start` - 起始 U 位
- `rack_u_end` - 结束 U 位

## PHP 版本要求

- PHP 7.2.5 或更高版本
- 推荐 PHP 8.0 或更高版本

## 浏览器兼容性

- Chrome 80+
- Firefox 75+
- Edge 80+
- Safari 13+

## 文件系统要求

模块的 `data` 目录需要写入权限：

```bash
chmod 755 modules/zabbix_rock/data
```

## 已知问题

### Zabbix 6.0

无已知问题

### Zabbix 7.0+

无已知问题

## 故障排除

### 模块无法加载

1. 检查 `manifest.json` 语法是否正确
2. 确认模块目录位于 Zabbix 前端的 `modules` 目录下
3. 检查 PHP 错误日志

### 无法保存配置

1. 检查 `data` 目录权限
2. 确认 `config.json` 文件可写

### 主机标签未更新

1. 确认用户具有主机编辑权限
2. 检查 Zabbix API 权限设置

## 获取帮助

如果遇到问题，请检查：

1. Zabbix 前端日志
2. PHP 错误日志
3. 浏览器控制台日志
