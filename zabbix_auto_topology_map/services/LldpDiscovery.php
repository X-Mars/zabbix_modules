<?php declare(strict_types = 1);

namespace Modules\Topomap\Services;

use Modules\Topomap\Lib\LanguageManager;

/**
 * 通过 SNMP 读取交换机的 LLDP / CDP / EDP 邻居表，供「自动发现」使用。
 *
 * 为什么用 SNMP 而不是 Zabbix API：Zabbix 本身不存邻居关系，LLDP 邻居只在设备上。
 * 服务器的 PHP 已带 snmp 扩展（`php -m | grep snmp`），实测 20ms 级别返回，
 * 不需要额外装 net-snmp 命令行工具。
 *
 * 所有方法都不抛异常：单台设备失败只记在 error 里，不能因为一台交换机不通就让整轮发现挂掉。
 */
class LldpDiscovery {

	/**
	 * lldpRemTable 根。
	 *
	 * 注意：**永远不要 walk 它的祖先 `1.0.8802.1.1.2.1`（整棵 lldpMIB）**。
	 * 该子树里的 lldpExtensions（…2.1.5，即 LLDP-MED）在 H3C Comware 上会让 net-snmp 的
	 * GETBULK 永久挂死 —— 实测 `snmp2_real_walk('1.0.8802.1.1.2.1')` 连 `timeout -s KILL 30`
	 * 都杀不出结果，整个发现请求就被这一台设备拖死（表现：日志停在「扫描 xxx …」再也不动）。
	 * 而下面这几张窄表单独 walk 全是几十毫秒稳定返回（remTable 144 行 / 0.53s）。
	 */
	private const OID_LLDP_REM = '1.0.8802.1.1.2.1.4.1.1';

	/** lldpLocPortTable 的 lldpLocPortId（本地端口名，如 GigabitEthernet1/0/1） */
	private const OID_LLDP_LOC_PORT_ID = '1.0.8802.1.1.2.1.3.7.1.3';

	/** lldpLocSysName（标量，本机名） */
	private const OID_LLDP_LOC_SYSNAME = '1.0.8802.1.1.2.1.3.3.0';

	/** lldpRemManAddrTable（邻居管理地址藏在索引里，值只是接口子类型） */
	private const OID_LLDP_REM_MAN_ADDR = '1.0.8802.1.1.2.1.4.2.1.3';

	/** CISCO-CDP-MIB cdpCacheTable 根 */
	private const OID_CDP_CACHE = '1.3.6.1.4.1.9.9.23.1.2.1.1';

	/** EXTREME-EDP-MIB extremeEdpNeighborTable：邻居名 / 邻居端口 */
	private const OID_EDP_NEIGHBOR_NAME = '1.3.6.1.4.1.1916.1.13.2.1.3';
	private const OID_EDP_NEIGHBOR_PORT = '1.3.6.1.4.1.1916.1.13.2.1.6';

	/** IF-MIB ifName：EDP 只给本地端口 ifIndex，得拿它换可读接口名 */
	private const OID_IF_NAME = '1.3.6.1.2.1.31.1.1.1.1';

	/**
	 * lldpRemTable 的列号 → 字段名（按 RFC 1213 之后的标准 lldpRemEntry 定义）。
	 *
	 * 邻居端口优先用 lldpRemPortId（column 7）：交换机 / 路由器上报的是
	 * 「GigabitEthernet1/0/1」这类接口名，比 lldpRemPortDesc 有用；
	 * 只有当 PortId 是 MAC（终端网卡）时才回落到 PortDesc。
	 */
	private const LLDP_COLUMNS = [
		5 => 'chassis',
		7 => 'port_id',
		8 => 'port_desc',
		9 => 'sysname',
		10 => 'sysdesc'
	];

	/** cdpCacheTable 的列号 → 字段名 */
	private const CDP_COLUMNS = [
		3 => 'address',
		6 => 'device_id',
		7 => 'device_port',
		8 => 'platform'
	];

	/** 单次 SNMP 超时（微秒）。设备不通时以 1.2s 收场，避免把整轮发现拖住。 */
	private int $timeout_us;

	/** 重试次数：0，交换机不回就是真不回，多试只是浪费时间 */
	private int $retries = 0;

	/**
	 * 同一台设备两次 SNMP 请求之间的最小间隔（微秒）。
	 * 华为 / H3C 的 agent 有请求限流：连着发 5、6 个 walk 就直接
	 * 「No response from x.x.x.x」，表现是「有时能发现、有时一个邻居都没有」。
	 */
	private const WALK_GAP_US = 70000;

	private float $last_request_at = 0.0;

	/**
	 * 本轮 scanHost 里 SNMP 成功应答过几次。
	 * 用来区分「设备回了空表（没开 LLDP）」和「设备根本没响应（超时/限流）」——
	 * 前者是设备配置问题，后者该提示用户稍后重试，不能混为一谈。
	 */
	private int $walk_answered = 0;

	public function __construct(int $timeout_ms = 1200) {
		$this->timeout_us = max(200, $timeout_ms) * 1000;
	}

	/**
	 * 扫描一台 snmp 设备，返回邻居列表。
	 *
	 * @param array $host  Zabbix 主机（含 interfaces 与解析后的社区串）
	 *
	 * @return array{ok: bool, error: string, sysname: string, ifnames: array, neighbors: array}
	 */
	public function scanHost(array $host): array {
		$empty = ['ok' => false, 'error' => '', 'sysname' => '', 'neighbors' => []];

		if (empty($host['snmp_ip'])) {
			return array_merge($empty, ['error' => LanguageManager::t('没有 SNMP 接口')]);
		}

		if (!extension_loaded('snmp') && !function_exists('snmp2_real_walk')) {
			return array_merge($empty, ['error' => LanguageManager::t('PHP 缺少 snmp 扩展')]);
		}

		$protocols = isset($host['protocols']) && is_array($host['protocols']) ? $host['protocols'] : ['LLDP'];
		$sysname = '';
		$neighbors = [];

		$this->walk_answered = 0;
		$this->useNumericOids();

		// 每台设备只按一个协议的优先级往下试：LLDP → CDP → EDP，命中就停。
		// 这不是省事，是必须的：agent 对连续请求限流，多打几次整台设备就没响应了，
		// 而且三种协议同时开会得到重复邻居。
		if (in_array('LLDP', $protocols, true)) {
			$lldp = $this->scanLldp($host);
			$neighbors = $lldp['neighbors'];
			$sysname = $lldp['sysname'];
		}

		// LLDP 没结果时才看 CDP：老思科设备往往只开 CDP。
		if (!$neighbors && in_array('CDP', $protocols, true)) {
			$neighbors = $this->scanCdp($host);
		}

		// 最后才是 EDP：只有 Extreme 系设备用（走 1916 私有 MIB，别的设备秒回空表）。
		if (!$neighbors && in_array('EDP', $protocols, true)) {
			$neighbors = $this->scanEdp($host);
		}

		// 一次都没应答 = 设备不通 / 被限流，不是「没开 LLDP」，提示要能给对方向
		if (!$neighbors && $this->walk_answered === 0) {
			return [
				'ok' => false,
				'error' => LanguageManager::t('SNMP 没有响应（超时或设备限流），请稍后重试'),
				'sysname' => $sysname,
				'neighbors' => []
			];
		}

		return [
			'ok' => true,
			'error' => '',
			'sysname' => $sysname,
			'neighbors' => $neighbors
		];
	}

	// ------------------------------------------------------------------
	// LLDP
	// ------------------------------------------------------------------

	/**
	 * 分表 walk 拿邻居关系：lldpRemTable（邻居）+ lldpLocPortTable（本地端口名）
	 * + lldpRemManAddrTable（邻居管理地址）+ lldpLocSysName（本机名）。
	 *
	 * **绝不 walk `1.0.8802.1.1.2.1` 整棵子树**，原因见 OID_LLDP_REM 的注释：
	 * 子树里的 LLDP-MED 会把 GETBULK 拖死。分表后每台设备 4 次请求，
	 * 有邻居时约 1s（含请求间隔），比一次走死强。
	 *
	 * @return array{neighbors: array, sysname: string}
	 */
	private function scanLldp(array $host): array {
		// 空表要复测一次：限流时设备会回空表（见 walk() 里的注释）
		$tree = $this->walkMap(self::OID_LLDP_REM, $host, true);

		if (!$tree) {
			return ['neighbors' => [], 'sysname' => $this->readSysname($host)];
		}

		// 空表要复测：设备限流时 lldpLocPortId 会「正常回空表」（见 walk() 注释）。
		// 实测最忙的核心交换机（29 个邻居，前面几次 walk 把 agent 打到限流）这一步
		// 全部查不到本地端口名，于是整机本地端口退化成 65542 这种 Comware 原始数字。
		$locports = $this->walkMap(self::OID_LLDP_LOC_PORT_ID, $host, true);
		$sysname = $this->readSysname($host);
		$manaddrs = [];
		$manaddrs_by_port = [];
		// IF-MIB ifName 只在 lldpLocPortId 缺失时才按需 walk 一次（多一次 SNMP 请求）
		$ifnames = null;

		// lldpRemManAddrTable 的索引里藏着地址：
		// <timeMark>.<本地端口>.<remIndex>.<subtype>.<len>.<addr...>
		// 值只是「接口子类型」，地址只能从索引里解析。
		//
		// 优先按「rem 条目的完整索引」关联（这才是 RFC 里该有的对应关系）；
		// 华为等设备两张表的 lldpRemIndex 对不上，再回退到「按本地端口」。
		// 之前只按端口建键，同一端口下有多个 rem 条目时会互相覆盖，地址张冠李戴。
		foreach ($this->walkMap(self::OID_LLDP_REM_MAN_ADDR, $host) as $suffix => $value) {
			$parsed = $this->parseManAddr((string) $suffix);

			if (!$parsed) {
				continue;
			}

			if (!isset($manaddrs[$parsed['key']])) {
				$manaddrs[$parsed['key']] = $parsed['ip'];
			}

			if (!isset($manaddrs_by_port[$parsed['port']])) {
				$manaddrs_by_port[$parsed['port']] = $parsed['ip'];
			}
		}

		$rows = [];

		foreach ($tree as $suffix => $value) {
			// lldpRemTable：<列>.<timeMark>.<本地端口>.<remIndex>
			$parts = explode('.', (string) $suffix);

			if (count($parts) < 4) {
				continue;
			}

			$field = self::LLDP_COLUMNS[(int) $parts[0]] ?? '';

			if ($field === '') {
				continue;
			}

			$rows[implode('.', array_slice($parts, 1))][$field] = $value;
		}

		if (!$rows) {
			return ['neighbors' => [], 'sysname' => $sysname];
		}

		$result = [];
		$seen = [];

		foreach ($rows as $key => $row) {
			$sysname_of_neighbor = isset($row['sysname']) ? trim((string) $row['sysname']) : '';

			if ($sysname_of_neighbor === '' && empty($row['chassis'])) {
				continue;
			}

			$parts = explode('.', $key);
			$port_num = count($parts) >= 2 ? (int) $parts[count($parts) - 2] : 0;

			// 本地端口名：lldpLocPortId 最准；查不到时回退 IF-MIB ifName / Comware 解码，
			// 详见 resolveLocalPortName()。
			$local_port = $this->resolveLocalPortName($port_num, $locports, $host, $ifnames);

			// 同一台交换机可能上报两套 localPortNum（堆叠 / 双主控：65542 与 131078 都是 ge1_0_6），
			// 端口名相同就视为同一条邻居，否则拓扑里会出现两条重复连线。
			//
			// 去重键里必须有「邻居自己的标识」：终端 / AP 常常只上报 chassisId（MAC），
			// sysName 为空，一个口下挂多台终端（1/0/12 三台、1/0/22 五台）时
			// 只用「空名字 + 端口」会把它们合并成一条，导致「包含未纳管设备」少一大半。
			$ident = $sysname_of_neighbor;

			if ($ident === '') {
				$ident = isset($row['chassis']) ? trim((string) $row['chassis']) : '';
			}

			if ($ident === '') {
				$ident = isset($row['port_id']) ? trim((string) $row['port_id']) : '';
			}

			$uniq = mb_strtolower($ident).'|'.$local_port;

			if (isset($seen[$uniq])) {
				continue;
			}

			$seen[$uniq] = true;

			// 邻居端口名优先用 lldpRemPortId；它是 MAC（终端网卡）时才回落到 PortDesc，
			// 否则连线上会显示一串 MAC。
			$raw_port_id = isset($row['port_id']) ? trim((string) $row['port_id']) : '';
			$port_id = $raw_port_id;
			$port_desc = isset($row['port_desc']) ? trim((string) $row['port_desc']) : '';

			// 对端端口是 MAC ⇒ 对面是终端网卡（PC / 打印机 / AP），不是交换机端口。
			// aiops 的 is_distribution 判断的就是这件事，后面据此选 Workstation 图标。
			$port_mac = self::normalizeMac($raw_port_id) !== '';

			if ($port_id === '' || $port_mac) {
				$port_id = $port_desc;
			}

			// 邻居管理地址：先按 rem 条目的完整索引取，取不到再按本地端口回退
			// （见上面的注释：有些设备两张表的 lldpRemIndex 对不上）。
			$neighbor_ip = isset($manaddrs[$key])
				? $manaddrs[$key]
				: (isset($manaddrs_by_port[(string) $port_num]) ? $manaddrs_by_port[(string) $port_num] : '');

			$result[] = [
				'protocol' => 'LLDP',
				'sysname' => $sysname_of_neighbor,
				'chassis' => isset($row['chassis']) ? (string) $row['chassis'] : '',
				'port_id' => $port_id,
				'port_desc' => $port_desc,
				'port_mac' => $port_mac,
				'sysdesc' => isset($row['sysdesc']) ? (string) $row['sysdesc'] : '',
				'local_port' => $local_port,
				'local_port_num' => $port_num,
				'ip' => $neighbor_ip
			];
		}

		return ['neighbors' => $result, 'sysname' => $sysname];
	}

	/**
	 * 解析本地端口名，取值优先级：
	 *   1) lldpLocPortId（最准，华为 / H3C 给的是 ge0_0_21 这类名字）
	 *   2) IF-MIB ifName：把 Comware 编码端口号直接当 ifIndex 去查（按需 walk，结果跨邻居复用）
	 *   3) Comware 解码：lldpRemLocalPortNum 是「槽位<<16 | 端口号」，还原成 GigabitEthernet{槽}/0/{端口}
	 *   4) 都不行才回退原始数字（旧行为）
	 *
	 * 为什么非要还原成可读名：本地端口名既画在连线上，又是「双向发现去重」和
	 * 「按端口匹配触发器」的键。原始数字（65547）跟对端报的 Gi1/0/11 对不上，
	 * 会导致双向重复线合不掉、源端触发器匹配不到（表现就是两端都绑上触发器、只看到一条线）。
	 *
	 * @param array|null $ifnames 引用传入，同一台设备只 walk 一次 ifName
	 */
	private function resolveLocalPortName(int $port_num, array $locports, array $host, ?array &$ifnames): string {
		if ($port_num <= 0) {
			return '';
		}

		$key = (string) $port_num;
		$name = isset($locports[$key]) ? trim((string) $locports[$key]) : '';

		// H3C 部分端口在 lldpLocPortId 里报的是**端口 MAC**（70:81:85:2C:05:E1），不是接口名。
		// 这种必须当作「没查到」继续回退 IF-MIB ifName，否则连线上显示一串 MAC，
		// 而且按端口匹配触发器永远命中不了（与 aiops 的 readableName 判断一致）。
		if ($name !== '' && self::normalizeMac($name) === '') {
			return $name;
		}

		if ($ifnames === null) {
			$ifnames = $this->walkMap(self::OID_IF_NAME, $host);
		}

		$name = isset($ifnames[$key]) ? trim((string) $ifnames[$key]) : '';

		if ($name !== '') {
			return $name;
		}

		$decoded = self::decodeComwarePortName($port_num);

		return $decoded !== '' ? $decoded : $key;
	}

	/**
	 * Comware 的 lldpRemLocalPortNum = 槽位<<16 | 端口号（65547 = 1 槽 11 口、131083 = 2 槽 11 口）。
	 * 还原成 GigabitEthernet{槽}/0/{端口}；不是该编码（小于 65536）返回空串。
	 */
	private static function decodeComwarePortName(int $port_num): string {
		if ($port_num < 65536) {
			return '';
		}

		$slot = intdiv($port_num, 65536);
		$port = $port_num % 65536;

		if ($slot <= 0 || $port <= 0) {
			return '';
		}

		return 'GigabitEthernet'.$slot.'/0/'.$port;
	}

	/** lldpLocSysName 是标量，walk 一次只回一行，取第一个非空值即可。 */
	private function readSysname(array $host): string {
		foreach ($this->walkMap(self::OID_LLDP_LOC_SYSNAME, $host) as $value) {
			$value = trim((string) $value);

			if ($value !== '') {
				return $value;
			}
		}

		return '';
	}

	/**
	 * lldpRemManAddrTable 索引：timeMark.localPortNum.remIndex.addrSubtype.len.addr...
	 *
	 * 地址是变长 octet string：华为等设备直接把 "192.168.1.1" 按 ASCII 塞进来（len=11），
	 * 也有设备给的是 4 字节二进制（len=4）。两种都要认。
	 */
	private function parseManAddr(string $suffix): array {
		$parts = explode('.', $suffix);

		if (count($parts) < 6) {
			return [];
		}

		$subtype = (int) $parts[3];
		$len = (int) $parts[4];
		$bytes = array_slice($parts, 5, $len);

		if (count($bytes) !== $len || $len === 0) {
			return [];
		}

		$key = implode('.', array_slice($parts, 0, 3));
		$port = (string) $parts[1];

		if ($subtype === 1) {
			// 4 字节二进制
			if ($len === 4) {
				return ['key' => $key, 'port' => $port, 'ip' => implode('.', array_map('intval', $bytes))];
			}

			// ASCII 点分十进制
			$text = '';

			foreach ($bytes as $byte) {
				$text .= chr((int) $byte);
			}

			if (filter_var($text, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
				return ['key' => $key, 'port' => $port, 'ip' => $text];
			}
		}

		return [];
	}

	// ------------------------------------------------------------------
	// CDP
	// ------------------------------------------------------------------

	private function scanCdp(array $host): array {
		// 同样一次 walk 整张 cdpCacheTable，不要按列发请求（见 scanLldp 的注释）
		$cache = $this->walkMap(self::OID_CDP_CACHE, $host);

		if (!$cache) {
			return [];
		}

		$rows = [];

		foreach ($cache as $suffix => $value) {
			$parts = explode('.', $suffix);

			if (count($parts) < 3) {
				continue;
			}

			$column = (int) array_shift($parts);
			$field = self::CDP_COLUMNS[$column] ?? '';

			if ($field === '') {
				continue;
			}

			$rows[implode('.', $parts)][$field] = $value;
		}

		$result = [];

		foreach ($rows as $suffix => $row) {
			$device_id = isset($row['device_id']) ? trim((string) $row['device_id']) : '';

			if ($device_id === '') {
				continue;
			}

			$parts = explode('.', $suffix);
			$ifindex = (int) $parts[0];

			$result[] = [
				'protocol' => 'CDP',
				'sysname' => $device_id,
				'chassis' => '',
				'port_id' => isset($row['device_port']) ? (string) $row['device_port'] : '',
				'port_desc' => '',
				'sysdesc' => isset($row['platform']) ? (string) $row['platform'] : '',
				'local_port' => (string) $ifindex,
				'local_port_num' => $ifindex,
				'ip' => isset($row['address']) ? $this->ipFromCdpAddress((string) $row['address']) : ''
			];
		}

		return $result;
	}

	/**
	 * cdpCacheAddress 是 4 字节的 IPv4 octet string，SNMP 扩展通常给成
	 * 「Hex-STRING: 0A 00 00 01」或「0.0.0.0」两种形态。
	 */
	private function ipFromCdpAddress(string $raw): string {
		$raw = trim($raw);

		if (preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', $raw)) {
			return $raw;
		}

		if (preg_match('/^([0-9a-f]{2}[\s:]){3,15}[0-9a-f]{2}$/i', $raw)) {
			$bytes = preg_split('/[\s:]+/', $raw);

			if (count($bytes) === 4) {
				return implode('.', array_map(function ($b) { return (string) hexdec($b); }, $bytes));
			}
		}

		return '';
	}

	// ------------------------------------------------------------------
	// EDP（Extreme Discovery Protocol）
	// ------------------------------------------------------------------

	/**
	 * EXTREME-EDP-MIB `extremeEdpNeighborTable`（1.3.6.1.4.1.1916.1.13.2.1）：
	 *   .1 extremeEdpPortIfIndex（本地端口 ifIndex，也是索引的第一段）
	 *   .3 extremeEdpNeighborName  ← 邻居名
	 *   .6 extremeEdpNeighborPort  ← 邻居端口
	 *
	 * 索引是 `<本地端口 ifIndex>.<邻居 id>`，两张表用同一个索引；表里没有邻居 IP
	 * （EDP 不上报地址），本地端口只能拿 ifIndex 去 IF-MIB 的 ifName 换接口名。
	 */
	private function scanEdp(array $host): array {
		$names = $this->walkMap(self::OID_EDP_NEIGHBOR_NAME, $host);

		if (!$names) {
			return [];
		}

		$ports = $this->walkMap(self::OID_EDP_NEIGHBOR_PORT, $host);
		$ifnames = $this->walkMap(self::OID_IF_NAME, $host);
		$result = [];

		foreach ($names as $suffix => $name) {
			$name = trim((string) $name);
			$ifindex = explode('.', (string) $suffix)[0] ?? '';

			if ($name === '' || $ifindex === '') {
				continue;
			}

			$result[] = [
				'protocol' => 'EDP',
				'sysname' => $name,
				'chassis' => '',
				'port_id' => isset($ports[$suffix]) ? (string) $ports[$suffix] : '',
				'port_desc' => '',
				'sysdesc' => '',
				'local_port' => isset($ifnames[$ifindex]) ? (string) $ifnames[$ifindex] : (string) $ifindex,
				'local_port_num' => (int) $ifindex,
				'ip' => ''
			];
		}

		return $result;
	}

	// ------------------------------------------------------------------
	// SNMP 基础
	// ------------------------------------------------------------------

	/**
	 * real_walk 一次，返回「索引后缀 => 清洗后的值」。
	 * 失败（超时、设备不认这个 OID）统一返回空数组。
	 *
	 * 注意 key 的形态因设备而已：`1.0.8802...8.0.65542.1`、`iso.0.8802...`、
	 * `IF-MIB::ifName.4` 都见过，不能假定一定以纯数字 OID 开头。
	 */
	private function walkMap(string $oid, array $host, bool $retry_empty = false): array {
		$raw = $this->walk($oid, $host, $retry_empty);
		$result = [];

		foreach ($raw as $key => $value) {
			$suffix = $this->suffixOf((string) $key, $oid);

			if ($suffix === '') {
				continue;
			}

			$result[$suffix] = $this->clean($value);
		}

		return $result;
	}

	/**
	 * 从 real_walk 的 key 里取出属于 $oid 的那段索引。
	 * 先按数字 OID 定位；名字形态（IF-MIB::ifName.4）退化成「取最后一段」，
	 * 因为这类表（ifName / ifDescr）的索引本来就只有一个数字。
	 */
	private function suffixOf(string $key, string $oid): string {
		$candidates = [$key];

		if (strpos($key, 'iso.') === 0) {
			// iso.0.8802... 与 1.0.8802... 是同一个 OID
			$candidates[] = '1.'.substr($key, 4);
		}

		foreach ($candidates as $candidate) {
			$pos = strpos($candidate, $oid.'.');

			if ($pos !== false) {
				return substr($candidate, $pos + strlen($oid) + 1);
			}
		}

		if (preg_match('/([0-9]+)$/', $key, $match)) {
			return $match[1];
		}

		return '';
	}

	/**
	 * @param bool $retry_empty 空表是否也复测（限流时设备会「正常回空表」，只有 LLDP 子树需要）
	 */
	private function walk(string $oid, array $host, bool $retry_empty = false): array {
		$this->throttle();

		$result = $this->walkOnce($oid, $host);

		// 两种失败都要退避重试：
		//   1) null —— 超时/丢包。实测 php-fpm 刚重启时，新 worker 里的第一次 SNMP 请求
		//      经常收不到回应，紧接着再发就正常。
		//   2) 空表 —— 限流时华为 snmpd 会「正常回一个空表」而不是报错，
		//      表现就是同一台交换机「有时 24 个邻居、有时 0 个」。
		// 只重试 1 次不够，冷进程 + 限流叠在一起时仍然会空手而归，所以退避两档。
		foreach ([350000, 900000] as $gap) {
			if (is_array($result) && ($result || !$retry_empty)) {
				break;
			}

			usleep($gap);
			$this->last_request_at = microtime(true);
			$result = $this->walkOnce($oid, $host);
		}

		return is_array($result) ? $result : [];
	}

	/**
	 * 同一台设备两次请求之间拉开 70ms。
	 * 华为 / H3C 的 snmpd 对连续请求很敏感，连发 5、6 个 walk 就开始不响应，
	 * 结果是「同一台交换机，有时能发现 24 个邻居，有时 0 个」。
	 */
	private function throttle(): void {
		$gap = self::WALK_GAP_US - (int) ((microtime(true) - $this->last_request_at) * 1000000);

		if ($gap > 0) {
			usleep($gap);
		}

		$this->last_request_at = microtime(true);
	}

	/**
	 * 让 SNMP 扩展把 key 输出成纯数字 OID（`.1.3.6.1.2.1.31.1.1.1.1.7`）。
	 *
	 * 默认输出是 `IF-MIB::ifName.7` / `iso.0.8802...` 这种「MIB 名 + 索引」混排，
	 * 索引超过一段时（EDP 的 `<ifIndex>.<邻居id>`、lldpRem 的
	 * `<列>.<timeMark>.<端口>.<索引>`）只能靠猜，解析必错。
	 * 数字形态下 suffixOf() 能精确切出 OID 后面那一段。
	 */
	private function useNumericOids(): void {
		if (function_exists('snmp_set_oid_output_format') && defined('SNMP_OID_OUTPUT_NUMERIC')) {
			@snmp_set_oid_output_format(SNMP_OID_OUTPUT_NUMERIC);
		}
	}

	/**
	 * @return array|null 成功返回数组，失败返回 null（交给 walk() 决定是否重试）
	 */
	private function walkOnce(string $oid, array $host): ?array {
		$version = (int) ($host['snmp_version'] ?? 2);
		$ip = (string) ($host['snmp_ip'] ?? '');

		try {
			if ($version === 3) {
				$v3 = $host['snmp_v3'] ?? [];

				$result = @snmp3_real_walk(
					$ip,
					(string) ($v3['sec_name'] ?? ''),
					(string) ($v3['sec_level'] ?? 'authNoPriv'),
					(string) ($v3['auth_protocol'] ?? 'SHA'),
					(string) ($v3['auth_passphrase'] ?? ''),
					(string) ($v3['priv_protocol'] ?? 'AES'),
					(string) ($v3['priv_passphrase'] ?? ''),
					$oid,
					$this->timeout_us,
					$this->retries
				);
			}
			elseif ($version === 1) {
				$result = @snmprealwalk($ip, (string) ($host['community'] ?? 'public'), $oid,
					$this->timeout_us, $this->retries);
			}
			else {
				// 用 @ 抑制 PHP Warning：设备不通时扩展会刷「No response from x.x.x.x」，
				// 这类告警每秒几十条会把 nginx error.log 冲爆。失败由返回值 false 判断。
				$result = @snmp2_real_walk($ip, (string) ($host['community'] ?? 'public'), $oid,
					$this->timeout_us, $this->retries);
			}
		}
		catch (\Throwable $e) {
			// 设备不通时扩展会抛异常，按「没有数据」处理
			return null;
		}

		if (is_array($result)) {
			$this->walk_answered++;

			return $result;
		}

		return null;
	}

	/**
	 * 去掉 snmp 扩展给值加的类型前缀，并把 Hex-STRING 还原成可读文本。
	 * 中文设备名（华为 / H3C 常见）返回的就是 Hex-STRING，不转码会是乱码。
	 */
	private function clean($raw): string {
		$raw = is_string($raw) ? $raw : (string) $raw;
		$raw = trim($raw);

		if (preg_match('/^Hex-STRING:\s*(.+)$/is', $raw, $match)) {
			return $this->hexToString(trim($match[1]));
		}

		if (preg_match('/^(STRING|INTEGER|OID|Gauge32|Counter32|Counter64|Timeticks|IpAddress|Opaque|BITS|Network Address):\s*(.*)$/is', $raw, $match)) {
			$raw = trim($match[2]);
		}

		return trim($raw, " \"\r\n\t");
	}

	/**
	 * Hex-STRING → 文本：能按 UTF-8 解就直接解（中文名），否则按 MAC 风格显示。
	 */
	private function hexToString(string $hex): string {
		$hex = preg_replace('/[^0-9a-fA-F]/', '', $hex);

		if ($hex === '' || strlen($hex) % 2 !== 0) {
			return '';
		}

		$bytes = hex2bin($hex);

		if ($bytes === false) {
			return '';
		}

		// 可打印的 ASCII / UTF-8 才当文本
		if (preg_match('//u', $bytes)) {
			$text = trim($bytes, "\x00");

			if ($text !== '' && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $text)) {
				return $text;
			}
		}

		$pairs = str_split($hex, 2);

		return implode(':', $pairs);
	}

	/**
	 * 解析 LLDP/CDP 里出现的 MAC，用来做兜底匹配（设备名和 IP 都没有时）。
	 */
	public static function normalizeMac(string $value): string {
		$hex = preg_replace('/[^0-9a-fA-F]/', '', $value);

		return strlen($hex) === 12 ? strtolower($hex) : '';
	}
}
