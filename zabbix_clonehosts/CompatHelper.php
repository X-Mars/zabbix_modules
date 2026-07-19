<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** Compatibility helper for Zabbix 6.0 / 6.4 / 7.x API differences.
*/

namespace Modules\ZabbixClonehosts;

/**
 * Compatibility helper for Zabbix 6.0 / 6.4 / 7.x.
 *
 * Key differences handled:
 * - Host group select parameter: 'selectGroups' (6.0) vs 'selectHostGroups' (6.4+)
 * - Result key: 'groups' (6.0) vs 'hostgroups' (6.4+)
 * - Template API: API::Template() exists in all versions
 * - manifest_version: 1.0 (6.0) vs 2.0 (6.4+)
 */
class CompatHelper {

	/** @var int|null Cached major version */
	private static $major_version = null;

	/**
	 * Get the Zabbix major version (6, 7, etc.).
	 *
	 * @return int
	 */
	public static function getMajorVersion(): int {
		if (self::$major_version !== null) {
			return self::$major_version;
		}

		if (defined('ZABBIX_VERSION')) {
			$version = ZABBIX_VERSION;
		} else {
			// Fallback: try to get version from API
			try {
				$settings = \API::Settings()->get([
					'output' => []
				]);
				// If Settings API exists, it's 6.4+
				self::$major_version = 6;
				// Check if it's 7.x by looking for newer API methods
				if (method_exists(\API::Host(), 'get')) {
					// Try a lightweight call to detect version behavior
					// If selectHostGroups works, it's 6.4+
					self::$major_version = 7; // Default to 7 for safety
				}
				return self::$major_version;
			} catch (\Exception $e) {
				self::$major_version = 6;
				return self::$major_version;
			}
		}

		$parts = explode('.', $version);
		self::$major_version = (int) $parts[0];

		return self::$major_version;
	}

	/**
	 * Check if running on Zabbix 6.0 (which uses selectGroups).
	 *
	 * @return bool
	 */
	public static function isZabbix60(): bool {
		return self::getMajorVersion() === 6 && self::getMinorVersion() === 0;
	}

	/**
	 * Get the minor version.
	 *
	 * @return int
	 */
	public static function getMinorVersion(): int {
		if (defined('ZABBIX_VERSION')) {
			$parts = explode('.', ZABBIX_VERSION);
			return (int) ($parts[1] ?? 0);
		}
		return 4; // Default assumption
	}

	/**
	 * Get the correct select parameter for host groups.
	 * - Zabbix 6.0: 'selectGroups'
	 * - Zabbix 6.4+: 'selectHostGroups'
	 *
	 * @return string
	 */
	public static function getHostGroupSelectParam(): string {
		if (self::isZabbix60()) {
			return 'selectGroups';
		}
		return 'selectHostGroups';
	}

	/**
	 * Get the correct result key for host groups in host data.
	 * - Zabbix 6.0: 'groups'
	 * - Zabbix 6.4+: 'hostgroups'
	 *
	 * @return string
	 */
	public static function getHostGroupResultKey(): string {
		if (self::isZabbix60()) {
			return 'groups';
		}
		return 'hostgroups';
	}

	/**
	 * Normalize host data so that host groups are always under the 'hostgroups' key,
	 * regardless of Zabbix version.
	 *
	 * @param array $host Host data from API::Host()->get()
	 * @return array Normalized host data
	 */
	public static function normalizeHost(array $host): array {
		$key = self::getHostGroupResultKey();

		// If the key is 'groups' (Zabbix 6.0), rename to 'hostgroups' for consistency
		if ($key === 'groups' && isset($host['groups']) && !isset($host['hostgroups'])) {
			$host['hostgroups'] = $host['groups'];
			unset($host['groups']);
		}

		return $host;
	}

	/**
	 * Build the API::Host()->get() parameters with version-correct host group select.
	 *
	 * @param array $baseParams Base parameters (output, hostids, selectInterfaces, etc.)
	 * @param array $groupFields Fields to select for host groups (e.g., ['groupid', 'name'])
	 * @return array Complete parameters with correct host group select
	 */
	public static function buildHostGetParams(array $baseParams, array $groupFields = ['groupid', 'name']): array {
		$selectParam = self::getHostGroupSelectParam();
		$baseParams[$selectParam] = $groupFields;
		return $baseParams;
	}

	/**
	 * Get the correct manifest_version for the running Zabbix.
	 * - Zabbix 6.0: 1.0
	 * - Zabbix 6.4+: 2.0
	 *
	 * @return float
	 */
	public static function getManifestVersion(): float {
		if (self::isZabbix60()) {
			return 1.0;
		}
		return 2.0;
	}
}
