<?php declare(strict_types=1);

namespace Modules\SwitchVisual\Includes;

/**
 * Module-local EN → zh_CN translator.
 *
 * Zabbix core locale files cannot translate strings shipped inside custom
 * modules, so widget strings are routed through Translation::t(). Simplified
 * Chinese is used for zh_CN/zh_SG; other languages retain the English source.
 */
class Translation {

	/** @var ?string */ private static $language = null;

	/** English source string → 简体中文 */
	/** @var array */ private static $dict = [
		// Widget / general
		'Zabbix Switch Visual' => 'Zabbix 交换机可视化',

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
		'Style 1 (stepped)'                                => '样式 1（台阶式）',
		'Style 2 (metal)'                                  => '样式 2（金属外观）',
		'Style 3 (classic)'                                => '样式 3（经典）',
		'RJ45 ports (per stack member; 0 = all-optical, all SFP)' => 'RJ45 端口数（每个堆叠成员；0=全光交换机，全部为 SFP）',
		'SFP ports (manual mode only — total ports = RJ45 + SFP; auto-detect uses all ports beyond RJ45)'
		                                                   => 'SFP 端口数（仅手动模式 — 总端口数 = RJ45 + SFP；自动检测时超出 RJ45 数量的端口均为 SFP）',
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
		                                                   => 'CPU 使用率监控项键值模式（可选，支持通配符，如 system.cpu.util[*]）',
		'Memory % item pattern (optional, e.g. vm.memory.pused[*])'
		                                                   => '内存使用率监控项键值模式（可选，支持通配符，如 vm.memory.pused[*]）',
		'Temperature item pattern (optional, e.g. sensor.temp.value[*]; multiple sensors: panel shows max, hover shows all)'
		                                                   => '温度监控项键值模式（可选，支持通配符，如 sensor.temp.value[*]；多个温度时面板显示最高，悬浮显示全部）',
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
		'Brand logo'                                       => '品牌 Logo',
		'None'                                             => '无',
		'1 Gbps port color'                                => '1 Gbps 端口颜色',
		'100 Mbps port color'                              => '100 Mbps 端口颜色',
		'10 Gbps port color'                               => '10 Gbps 端口颜色',
		'40 Gbps port color'                               => '40 Gbps 端口颜色',
		'100 Gbps port color'                              => '100 Gbps 端口颜色',
		'Warning port color'                               => '告警端口颜色',
		'Error port color'                                 => '故障端口颜色',
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
		'Select a host or host group in widget settings.'          => '请在组件设置中选择主机或主机群组。',
		'No ports found — verify item key patterns match your Zabbix items.'
		                                                           => '未找到端口 — 请检查监控项键值模式是否与 Zabbix 监控项匹配。',
		'Error: '                                                  => '错误：',
		'Invalid host.'                                            => '无效的主机。',
		'API error.'                                               => 'API 错误。',
		'Active Problems'                                          => '活动告警',

		// Tooltip
		'Type'        => '类型',
		'Status'      => '状态',
		'Speed'       => '速率',
		'N/A'         => '未知',
		'Port'        => '端口',
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
		'Error value: %.2f'  => '错误值：%.2f',
		'Utilization > %.0f%%' => '利用率 > %.0f%%',

		// Duration / time units
		'd'   => '天',
		'h'   => '小时',
		'm'   => '分',
		'min' => '分钟',

		// Brand names — domestic vendors use their Chinese names, international
		// vendors keep the original English brand (translation returns the input).
		'Huawei'       => '华为',
		'H3C'          => '华三',
		'Ruijie'       => '锐捷',
		'ZTE'          => '中兴',
		'Inspur'       => '浪潮',
		'FiberHome'    => '烽火',
		'Maipu'        => '迈普',
		'DPtech'       => '迪普科技',
		'Hillstone'    => '山石网科',
		'Topsec'       => '天融信',
		'Venustech'    => '启明星辰',
		'NSFOCUS'      => '绿盟科技',
		'Sangfor'      => '深信服',
		'Qianxin'      => '奇安信',
		'DBAPPSecurity' => '安恒信息',
		'Leadsec'      => '网御星云',
		'Knownsec'     => '知道创宇',
		'iKuai'        => '爱快',
		'Sundray'      => '信锐',
		'HP'           => '惠普',
		'Siemens'      => '西门子',
		'Warom'        => '华荣',

		// Summary bar
		'up'      => '在线',
		'dn'      => '离线',
		'total'   => '共',
		'Member'  => '成员',
		'Err'     => '错误',
		'ports'   => '个端口',
		'Uptime'  => '运行时间',
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

	private const RUSSIAN = [
		'Zabbix Switch Visual' => 'Панель коммутатора Zabbix',
		'Ports' => 'Порты',
		'PoE' => 'PoE',
		'System' => 'Система',
		'Appearance' => 'Внешний вид',
		'Display' => 'Отображение',
		'Host' => 'Узел сети',
		'Host groups' => 'Группы узлов сети',
		'BW In item pattern' => 'Шаблон ключа входящего трафика',
		'BW Out item pattern' => 'Шаблон ключа исходящего трафика',
		'Bandwidth items deliver bits/sec (not bytes/sec)' => 'Элементы пропускной способности возвращают значения в бит/с',
		'Status item pattern' => 'Шаблон ключа состояния порта',
		'Speed item pattern' => 'Шаблон ключа скорости порта',
		'Errors In item pattern' => 'Шаблон ключа ошибок приёма',
		'Errors Out item pattern' => 'Шаблон ключа ошибок передачи',
		'Interface alias pattern (optional)' => 'Шаблон ключа псевдонима интерфейса (необязательно)',
		'PoE status pattern (optional, e.g. pethPsePortDetectionStatus[*])' => 'Шаблон ключа состояния PoE (необязательно, например pethPsePortDetectionStatus[*])',
		'PoE port power pattern (optional, e.g. pethPsePortPowerConsumption[*], milliwatts)' => 'Шаблон ключа мощности PoE на порту (необязательно, например pethPsePortPowerConsumption[*], мВт)',
		'Duplex mode pattern (optional, e.g. dot3StatsDuplexStatus[*])' => 'Шаблон ключа режима дуплекса (необязательно, например dot3StatsDuplexStatus[*])',
		'SFP optical RX power pattern (optional)' => 'Шаблон ключа оптической мощности приёма SFP (необязательно)',
		'SFP optical TX power pattern (optional)' => 'Шаблон ключа оптической мощности передачи SFP (необязательно)',
		'Port shape' => 'Форма порта',
		'Style 1 (stepped)' => 'Стиль 1 (ступенчатый)',
		'Style 2 (metal)' => 'Стиль 2 (металлический)',
		'Style 3 (classic)' => 'Стиль 3 (классический)',
		'RJ45 ports (per stack member; 0 = all-optical, all SFP)' => 'Порты RJ45 на каждом устройстве стека; 0 — оптический коммутатор, все порты SFP.',
		'SFP ports (manual mode only — total ports = RJ45 + SFP; auto-detect uses all ports beyond RJ45)' => 'Порты SFP только в ручном режиме: всего портов = RJ45 + SFP. При автоопределении SFP считаются все порты сверх числа RJ45.',
		'Port rows (1 or 2)' => 'Ряды портов (1 или 2)',
		'Invert row order (even ports top — e.g. Huawei)' => 'Поменять ряды местами (чётные порты сверху, например у Huawei)',
		'Sequential rows (first half top, second half bottom — e.g. 1–24 / 25–48)' => 'Последовательные ряды (первая половина сверху, вторая снизу, например 1–24 / 25–48)',
		'Zoom (50–300%)' => 'Масштаб (50–300 %)',
		'Port aliases (e.g. 1=Uplink, 3=ESX01, 4-6=VLAN1)' => 'Псевдонимы портов (например 1=Uplink, 3=ESX01, 4-6=VLAN1)',
		'Uptime item key (optional)' => 'Ключ элемента времени работы (необязательно)',
		'Serial number item key (optional)' => 'Ключ элемента серийного номера (необязательно)',
		'Model item key (optional)' => 'Ключ элемента модели (необязательно)',
		'CPU % item pattern (optional, e.g. system.cpu.util[*])' => 'Шаблон ключа загрузки ЦП в % (необязательно, например system.cpu.util[*])',
		'Memory % item pattern (optional, e.g. vm.memory.pused[*])' => 'Шаблон ключа использования памяти в % (необязательно, например vm.memory.pused[*])',
		'Temperature item pattern (optional, e.g. sensor.temp.value[*]; multiple sensors: panel shows max, hover shows all)' => 'Шаблон ключа температуры (необязательно, например sensor.temp.value[*]). На панели виден максимум по датчикам, при наведении — все значения.',
		'Temperature alarm threshold (°C, 0 = disabled)' => 'Порог предупреждения о температуре (°C, 0 — отключено)',
		'RJ45 position' => 'Расположение RJ45',
		'Front (ports 1..N are RJ45)' => 'Сначала RJ45 (порты 1…N)',
		'Back (last N ports are RJ45 — SFP first)' => 'Сначала SFP (последние N портов — RJ45)',
		'Total PoE consumed item key (optional, e.g. pethMainPseConsumptionPower.1, watts)' => 'Ключ элемента общей потребляемой мощности PoE (необязательно, например pethMainPseConsumptionPower.1, Вт)',
		'Max PoE capacity item key (optional, e.g. pethMainPsePower.1, watts)' => 'Ключ элемента максимальной мощности PoE (необязательно, например pethMainPsePower.1, Вт)',
		'Fan status pattern (optional, e.g. hpicfFanState[*])' => 'Шаблон ключа состояния вентилятора (необязательно, например hpicfFanState[*])',
		'Fan OK value (3=HP good, 1=some vendors — check MIB)' => 'Код исправного вентилятора (3 у HP, 1 у некоторых производителей, см. MIB)',
		'Chassis color' => 'Цвет корпуса',
		'Brand logo' => 'Логотип производителя',
		'None' => 'Нет',
		'1 Gbps port color' => 'Цвет порта 1 Гбит/с',
		'100 Mbps port color' => 'Цвет порта 100 Мбит/с',
		'10 Gbps port color' => 'Цвет порта 10 Гбит/с',
		'40 Gbps port color' => 'Цвет порта 40 Гбит/с',
		'100 Gbps port color' => 'Цвет порта 100 Гбит/с',
		'Warning port color' => 'Цвет предупреждения порта',
		'Error port color' => 'Цвет ошибки порта',
		'Port index start (SNMP offset)' => 'Начальный индекс порта (смещение SNMP)',
		'Auto-detect port count (may include VLANs/tunnels)' => 'Автоопределение числа портов (может включать VLAN и туннели)',
		'Excluded ports (auto-detect only; comma/space separated; name substring match, digits match index, * wildcard, e.g. vlan, Bridge-Aggregation, mgmt*)' => 'Исключённые порты при автоопределении. Разделители — запятые или пробелы. Текст соответствует части имени, число — индексу, * — любой последовательности символов. Например: vlan, Bridge-Aggregation, mgmt*.',
		'Show summary bar' => 'Показывать сводную панель',
		'Show port numbers' => 'Показывать номера портов',
		'Show port labels / aliases' => 'Показывать подписи или псевдонимы портов',
		'Show global traffic sparkline' => 'Показывать мини-график общего трафика',
		'Sparkline window (minutes)' => 'Период мини-графика (минуты)',
		'Utilization warning threshold (%)' => 'Порог предупреждения об использовании порта (%)',
		'Select a host or host group in widget settings.' => 'Выберите узел сети или группу узлов в настройках виджета.',
		'No ports found — verify item key patterns match your Zabbix items.' => 'Порты не найдены. Проверьте, что шаблоны ключей совпадают с элементами данных Zabbix.',
		'Error: ' => 'Ошибка: ',
		'Invalid host.' => 'Недопустимый узел сети.',
		'API error.' => 'Ошибка API.',
		'Active Problems' => 'Активные проблемы',
		'Type' => 'Тип',
		'Status' => 'Состояние',
		'Speed' => 'Скорость',
		'N/A' => 'Н/Д',
		'Port' => 'Порт',
		'RX' => 'RX',
		'TX' => 'TX',
		'Util' => 'Загрузка',
		'Down for' => 'Недоступен',
		'Duplex' => 'Дуплекс',
		'Half (!)' => 'Половинный (!)',
		'Opt RX' => 'Опт. RX',
		'Opt TX' => 'Опт. TX',
		'Delivering' => 'Подача питания',
		'Traffic' => 'Трафик',
		'green' => 'зелёный',
		'amber' => 'жёлтый',
		'red' => 'красный',
		'gray' => 'серый',
		'Error value: %.2f' => 'Значение ошибки: %.2f',
		'Utilization > %.0f%%' => 'Загрузка > %.0f%%',
		'd' => 'д',
		'h' => 'ч',
		'm' => 'м',
		'min' => 'мин',
		'Huawei' => 'Huawei',
		'H3C' => 'H3C',
		'Ruijie' => 'Ruijie',
		'ZTE' => 'ZTE',
		'Inspur' => 'Inspur',
		'FiberHome' => 'FiberHome',
		'Maipu' => 'Maipu',
		'DPtech' => 'DPtech',
		'Hillstone' => 'Hillstone',
		'Topsec' => 'Topsec',
		'Venustech' => 'Venustech',
		'NSFOCUS' => 'NSFOCUS',
		'Sangfor' => 'Sangfor',
		'Qianxin' => 'Qianxin',
		'DBAPPSecurity' => 'DBAPPSecurity',
		'Leadsec' => 'Leadsec',
		'Knownsec' => 'Knownsec',
		'iKuai' => 'iKuai',
		'Sundray' => 'Sundray',
		'HP' => 'HP',
		'Siemens' => 'Siemens',
		'Warom' => 'Warom',
		'up' => 'вкл.',
		'dn' => 'выкл.',
		'total' => 'всего',
		'Member' => 'Устройство стека',
		'Err' => 'Ош.',
		'ports' => 'портов',
		'Uptime' => 'Время работы',
		'Memory' => 'Память',
		'Temperature sensors' => 'Датчики температуры',
		'Sensor' => 'Датчик',
		'S/N' => 'Сер. №',
		'Temp' => 'Темп.',
		'PoE cap' => 'Макс. PoE',
		'Fans' => 'Вентиляторы',
		'FAIL' => 'СБОЙ',
		'At' => 'Время',
		'Select a host' => 'Выберите узел сети',
		'Switch visual' => 'Панель коммутатора',
	];

	/**
	 * Translate a source string using the current Zabbix language.
	 */
	public static function t(string $en): string {
		if (self::$language === null) {
			$lang = '';
			if (class_exists('CWebUser') && \CWebUser::$data !== null && isset(\CWebUser::$data['lang'])) {
				$lang = (string) \CWebUser::$data['lang'];
			}
			if ($lang === '' || strcasecmp($lang, 'default') === 0) {
				try {
					if (class_exists('CSettingsHelper') && method_exists('CSettingsHelper', 'get')) {
						$lang = (string) \CSettingsHelper::get('default_lang');
					}
				}
				catch (\Throwable $e) {
					$lang = '';
				}
				if (($lang === '' || strcasecmp($lang, 'default') === 0)
						&& isset($GLOBALS['ZBX_LOCALES']['selected'])) {
					$lang = (string) $GLOBALS['ZBX_LOCALES']['selected'];
				}
			}
			$lang = strtolower(str_replace('-', '_', trim($lang)));
			if (preg_match('/^ru(?:_|$)/', $lang)) {
				self::$language = 'ru_RU';
			} else {
				self::$language = in_array($lang, ['zh_cn', 'zh_sg'], true) ? 'zh_CN' : 'en_US';
			}
		}
		if (self::$language === 'ru_RU') {
			return self::RUSSIAN[$en] ?? $en;
		}

		return self::$language === 'zh_CN' ? (self::$dict[$en] ?? $en) : $en;
	}
}
