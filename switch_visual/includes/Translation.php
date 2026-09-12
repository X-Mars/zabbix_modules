<?php declare(strict_types=1);

namespace Modules\SwitchVisual\Includes;

/**
 * Module-local EN → zh_CN translator.
 *
 * Zabbix core locale files cannot translate strings shipped inside custom
 * modules, so every user-visible string of this widget is routed through
 * Translation::t(). Translations activate automatically when the current
 * frontend user's language starts with "zh" (e.g. zh_CN / zh_TW).
 */
class Translation {

	private static ?bool $zh = null;

	/** English source string → 简体中文 */
	private static array $dict = [
		// Widget / general
		'Switch Visual Panel' => '交换机可视化面板',
		'Switch Visual'       => '交换机可视化',

		// Fieldset titles
		'Ports'      => '端口',
		'PoE'        => 'PoE',
		'System'     => '系统',
		'Appearance' => '外观',
		'Display'    => '显示',

		// Widget form labels
		'Host'                                             => '主机',
		'Host groups'                                      => '主机群组',
		'BW In item pattern'                               => '入带宽监控项模式',
		'BW Out item pattern'                              => '出带宽监控项模式',
		'Bandwidth items deliver bits/sec (not bytes/sec)' => '流量监控项单位为比特/秒（非字节/秒）',
		'Status item pattern'                              => '状态监控项模式',
		'Speed item pattern'                               => '速率监控项模式',
		'Errors In item pattern'                           => '入错误包监控项模式',
		'Errors Out item pattern'                          => '出错误包监控项模式',
		'Interface alias pattern (optional)'               => '接口别名监控项模式（可选）',
		'PoE status pattern (optional, e.g. pethPsePortDetectionStatus[*])'
		                                                   => 'PoE 状态监控项模式（可选，如 pethPsePortDetectionStatus[*]）',
		'PoE port power pattern (optional, e.g. pethPsePortPowerConsumption[*], milliwatts)'
		                                                   => 'PoE 端口功率监控项模式（可选，如 pethPsePortPowerConsumption[*]，单位毫瓦）',
		'Duplex mode pattern (optional, e.g. dot3StatsDuplexStatus[*])'
		                                                   => '双工模式监控项模式（可选，如 dot3StatsDuplexStatus[*]）',
		'SFP optical RX power pattern (optional)'          => 'SFP 收光功率监控项模式（可选）',
		'SFP optical TX power pattern (optional)'          => 'SFP 发光功率监控项模式（可选）',
		'Port shape'                                       => '端口形状',
		'Style 1 (stepped)'                                => '样式1（台阶式）',
		'Style 2 (metal)'                                  => '样式2（金属外观）',
		'Style 3 (classic)'                                => '样式3（经典）',
		'RJ45 ports (per stack member; the rest are SFP)'  => 'RJ45 端口数（每个堆叠成员；其余端口为 SFP）',
		'SFP ports (manual mode only — with auto-detect the ports beyond RJ45 count are SFP)'
		                                                   => 'SFP 端口数（仅手动模式生效 — 自动检测时超出 RJ45 数量的端口均为 SFP）',
		'Port rows (1 or 2)'                               => '端口行数（1 或 2）',
		'Invert row order (even ports top — e.g. Huawei)'  => '反转行序（偶数端口在上 — 如华为）',
		'Sequential rows (first half top, second half bottom — e.g. 1–24 / 25–48)'
		                                                   => '顺序排列（前半在上，后半在下 — 如 1–24 / 25–48）',
		'Zoom (50–300%)'                                   => '缩放（50–300%）',
		'Port aliases (e.g. 1=Uplink, 3=ESX01, 4-6=VLAN1)' => '端口别名（如 1=上联, 3=ESX01, 4-6=VLAN1）',
		'Uptime item key (optional)'                       => '运行时间监控项键值（可选）',
		'Serial number item key (optional)'                => '序列号监控项键值（可选）',
		'Model item key (optional)'                        => '设备型号监控项键值（可选）',
		'CPU % item pattern (optional, e.g. system.cpu.util[*])'
		                                                   => 'CPU 使用率监控项键值（可选，支持通配符，如 system.cpu.util[*]）',
		'Memory % item pattern (optional, e.g. vm.memory.pused[*])'
		                                                   => '内存使用率监控项键值（可选，支持通配符，如 vm.memory.pused[*]）',
		'Temperature item pattern (optional, e.g. sensor.temp.value[*]; multiple sensors: panel shows max, hover shows all)'
		                                                   => '温度监控项键值（可选，支持通配符，如 sensor.temp.value[*]；多个温度时面板显示最高，悬浮显示全部）',
		'Temperature alarm threshold (°C, 0 = disabled)'   => '温度告警阈值（°C，0=不启用）',
		'RJ45 position'                                    => 'RJ45 位置',
		'Front (ports 1..N are RJ45)'                      => '前面（端口 1..N 为 RJ45）',
		'Back (last N ports are RJ45 — SFP first)'         => '后面（最后 N 个端口为 RJ45 — 前面为 SFP）',
		'Total PoE consumed item key (optional, e.g. pethMainPseConsumptionPower.1, watts)'
		                                                   => 'PoE 总功耗监控项键值（可选，如 pethMainPseConsumptionPower.1，单位瓦）',
		'Max PoE capacity item key (optional, e.g. pethMainPsePower.1, watts)'
		                                                   => 'PoE 最大容量监控项键值（可选，如 pethMainPsePower.1，单位瓦）',
		'Fan status pattern (optional, e.g. hpicfFanState[*])'
		                                                   => '风扇状态监控项模式（可选，如 hpicfFanState[*]）',
		'Fan OK value (3=HP good, 1=some vendors — check MIB)'
		                                                   => '风扇正常值（3=HP 正常，1=部分厂商 — 请查 MIB）',
		'Chassis color'                                    => '机箱颜色',
		'1 Gbps port color'                                => '1 Gbps 端口颜色',
		'100 Mbps port color'                              => '100 Mbps 端口颜色',
		'10 Gbps port color'                               => '10 Gbps 端口颜色',
		'Alert / warning port color'                       => '告警端口颜色',
		'Error / down port color'                          => '错误端口颜色',
		'Port index start (SNMP offset)'                   => '端口索引起始（SNMP 偏移）',
		'Auto-detect port count (may include VLANs/tunnels)'
		                                                   => '自动检测端口数量（可能包含 VLAN/隧道接口）',
		'Excluded ports (auto-detect only; comma/space separated; name substring match, digits match index, * wildcard, e.g. vlan, Bridge-Aggregation, mgmt*)'
		                                                   => '排除端口（自动检测时生效，逗号/空格分隔；接口名包含即排除，纯数字匹配索引，* 为通配符，如：vlan、Bridge-Aggregation、mgmt*）',
		'Show summary bar'                                 => '显示摘要栏',
		'Show port numbers'                                => '显示端口号',
		'Show port labels / aliases'                       => '显示端口标签/别名',
		'Show global traffic sparkline'                    => '显示全局流量迷你图',
		'Sparkline window (minutes)'                       => '迷你图时间窗口（分钟）',
		'Utilization warning threshold (%)'                => '利用率告警阈值（%）',

		// View / empty states
		'Select a host in widget settings.'                        => '请在组件设置中选择主机。',
		'Select a host or host group in widget settings.'          => '请在组件设置中选择主机或主机群组。',
		'No ports found — verify item key patterns match your Zabbix items.'
		                                                           => '未找到端口 — 请检查监控项键值模式是否与 Zabbix 监控项匹配。',
		'Error: '                                                  => '错误：',
		'Active Problems'                                          => '活动告警',

		// Tooltip
		'Type'        => '类型',
		'Status'      => '状态',
		'Speed'       => '速率',
		'RX'          => '接收',
		'TX'          => '发送',
		'Util'        => '利用率',
		'Down for'    => '离线时长',
		'Duplex'      => '双工',
		'Half (!)'    => '半双工 (!)',
		'Opt RX'      => '收光功率',
		'Opt TX'      => '发光功率',
		'Delivering'  => '供电中',
		'Traffic'     => '流量',
		'green'       => '在线',
		'amber'       => '告警',
		'red'         => '故障',
		'gray'        => '离线',
		'Error rate %.2f%%'   => '错误率 %.2f%%',
		'Utilization > %.0f%%' => '利用率 > %.0f%%',

		// Duration / time units
		'd'   => '天',
		'h'   => '小时',
		'm'   => '分',
		'min' => '分钟',

		// Summary bar
		'up'      => '在线',
		'dn'      => '离线',
		'total'   => '共',
		'Member'  => '成员',
		'Err'     => '错误',
		'ports'   => '个端口',
		'Up'      => '运行',
		'Memory'  => '内存',
		'Temperature sensors' => '温度传感器',
		'Sensor'  => '传感器',
		'S/N'     => '序列号',
		'Temp'    => '温度',
		'PoE cap' => 'PoE 容量',
		'Fans'    => '风扇',
		'FAIL'    => '故障',
		'At'      => '时间',
	];

	/**
	 * Translate a source string. Returns the English input unchanged when the
	 * current user's language is not Chinese or no translation exists.
	 */
	public static function t(string $en): string {
		if (self::$zh === null) {
			$lang = '';
			if (class_exists('CWebUser') && \CWebUser::$data !== null && isset(\CWebUser::$data['lang'])) {
				$lang = (string) \CWebUser::$data['lang'];
			}
			self::$zh = (strcasecmp(substr($lang, 0, 2), 'zh') === 0);
		}
		return self::$zh ? (self::$dict[$en] ?? $en) : $en;
	}
}
