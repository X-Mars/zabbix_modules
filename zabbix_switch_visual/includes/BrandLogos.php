<?php declare(strict_types=1);

namespace Modules\SwitchVisual\Includes;

require_once __DIR__ . '/Translation.php';

/**
 * Vendor logo registry.
 *
 * SVG files live in assets/brands/ and are served by Zabbix as static module
 * assets. Zabbix select widget fields only accept integer values, so the field
 * stores an index into this list; the index is resolved back to a file name
 * before any path is built (unknown indexes are ignored).
 */
class BrandLogos {

	/** SVG file => English display name (translated at render time). */
	private const NAMES = [
		'360.svg'            => '360',
		'a10.svg'            => 'A10 Networks',
		'arista.svg'         => 'Arista',
		'ARUBa.svg'          => 'Aruba',
		'brocade.svg'        => 'Brocade',
		'checkpoints.svg'    => 'Check Point',
		'Cisco.svg'          => 'Cisco',
		'citrix.svg'         => 'Citrix',
		'cnzxsoft.svg'       => 'CNZXSoft',
		'dbappsecurity.svg'  => 'DBAPPSecurity',
		'dlink.svg'          => 'D-Link',
		'dptech.svg'         => 'DPtech',
		'emc2.svg'           => 'EMC',
		'fiberhome.svg'      => 'FiberHome',
		'fortinet.svg'       => 'Fortinet',
		'H3C.svg'            => 'H3C',
		'hillstone.svg'      => 'Hillstone',
		'hp.svg'             => 'HP',
		'htsj.svg'           => 'HTSJ',
		'huawei.svg'         => 'Huawei',
		'ibm.svg'            => 'IBM',
		'iKuai.svg'          => 'iKuai',
		'infogo.svg'         => 'Infogo',
		'infosec.svg'        => 'Infosec',
		'inspur.svg'         => 'Inspur',
		'juniper.svg'        => 'Juniper',
		'knownsec.svg'       => 'Knownsec',
		'leadsec.svg'        => 'Leadsec',
		'Mellanox.svg'       => 'Mellanox',
		'Mikrotik.svg'       => 'MikroTik',
		'netgear.svg'        => 'Netgear',
		'network.svg'        => 'Network',
		'nsfocus.svg'        => 'NSFOCUS',
		'OneSIG.svg'         => 'OneSIG',
		'paloalto.svg'       => 'Palo Alto',
		'qianxin.svg'        => 'Qianxin',
		'qnap.svg'           => 'QNAP',
		'radware.svg'        => 'Radware',
		'Ruijie.svg'         => 'Ruijie',
		'sangfor.svg'        => 'Sangfor',
		'siemens.svg'        => 'Siemens',
		'sundray.svg'        => 'Sundray',
		'Topsec.svg'         => 'Topsec',
		'tplink.svg'         => 'TP-Link',
		'unisyue.svg'        => 'Unisyue',
		'venustech.svg'      => 'Venustech',
		'warom.svg'          => 'Warom',
		'YC.svg'             => 'YC',
		'zte.svg'            => 'ZTE',
		'F5.svg'             => 'F5',
		'Maipu.svg'          => 'Maipu',
	];

	/** Display name => file name, sorted alphabetically (0-based list). */
	private static function ordered(): array {
		$names = self::NAMES;
		uasort($names, static fn(string $a, string $b): int => strcasecmp($a, $b));

		$ordered = [];
		foreach ($names as $file => $name) {
			$ordered[] = $file;
		}

		return $ordered;
	}

	/**
	 * Select options: 0 = no logo, 1..N = brand, sorted by display name.
	 *
	 * @return array<int, string>
	 */
	public static function options(): array {
		$options = [0 => Translation::t('None')];

		$index = 1;
		foreach (self::ordered() as $file) {
			$options[$index++] = Translation::t(self::NAMES[$file]);
		}

		return $options;
	}

	/** Resolve a stored select index to a brand file name (null = no logo). */
	public static function fileForIndex(int $index): ?string {
		$files = self::ordered();

		return $index > 0 && $index <= count($files) ? $files[$index - 1] : null;
	}

	/** Whether $file is a known brand shipped with the module. */
	public static function exists(string $file): bool {
		return $file !== '' && isset(self::NAMES[$file]) && is_file(self::path($file));
	}

	public static function path(string $file): string {
		return __DIR__ . '/../assets/brands/' . $file;
	}

	/** Public module asset URL (relative to the Zabbix frontend root). */
	public static function url(string $file): string {
		return 'modules/zabbix_switch_visual/assets/brands/' . rawurlencode($file);
	}

	public static function label(string $file): string {
		return Translation::t(self::NAMES[$file] ?? '');
	}
}
