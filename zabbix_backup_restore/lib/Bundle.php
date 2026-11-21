<?php

namespace Modules\ZabbixBackupRestore\Lib;

use RuntimeException;

final class Bundle {

	public const MAX_BYTES = 500 * 1024 * 1024;
	public const FORMAT = 'zabbix_backup_restore';
	public const TLS_FIELDS = 'tls_connect tls_accept tls_issuer tls_subject tls_psk_identity tls_psk';

	public static function supports(string $version): bool {
		return preg_match('/^(?:6|7|8)\.0(?:\.\d+)?(?:[a-z][a-z0-9.-]*)?$/iD', $version) === 1;
	}

	public static function read($stream, string $target_version): array {
		$stat = fstat($stream);
		if ($stat === false) {
			throw self::invalid();
		}
		$data = BundleYaml::read($stream, $stat['size']);
		if (($data['schema_version'] ?? null) !== 1 || !is_string($data['source_version'] ?? null)
				|| !self::supports($data['source_version']) || !is_array($data['configuration'] ?? null)
				|| !is_array($data['records'] ?? null) || !is_array($data['omissions'] ?? null)
				|| !is_array($data['host_encryption'] ?? null)
				|| array_diff(array_keys($data['records']), array_keys(Catalog::RECORDS))) {
			throw self::invalid();
		}
		if ((int) $data['source_version'] > (int) $target_version) {
			throw new RuntimeException(LanguageManager::t('This backup requires a newer Zabbix version.'));
		}

		$native = $data['configuration'];
		if ($native !== []) {
			$allowed = array_merge(['version', 'graphs', 'triggers'],
				array_column(array_intersect_key(Catalog::native($data['source_version']), Catalog::labels($data['source_version'])), 3));
			if (($native['version'] ?? null) !== substr($data['source_version'], 0, 3)
					|| array_diff(array_keys($native), $allowed)) {
				throw self::invalid();
			}
			foreach (array_diff_key($native, ['version' => true]) as $rows) {
				self::checkRows($rows);
			}
		}
		foreach ($data['records'] as $type => $rows) {
			if (version_compare($data['source_version'], '6.4', '<') && $rows) {
				throw self::invalid();
			}
			self::checkRows($rows);
			$seen = [];
			foreach ($rows as $row) {
				$identity = References::identity($type, $row);
				if (!References::validIdentity($identity)) {
					throw new RuntimeException(LanguageManager::format('Invalid record in %s.', $type));
				}

				$key = References::key($identity);
				if (isset($seen[$key])) {
					throw new RuntimeException(LanguageManager::format('Duplicate record in %s (%s).', $type,
						is_string($identity) ? $identity : implode(' / ', $identity)));
				}
				$seen[$key] = true;
			}
		}
		foreach (['secret_macros', 'provisioned_users'] as $key) {
			if (!is_int($data['omissions'][$key] ?? null) || $data['omissions'][$key] < 0) {
				throw self::invalid();
			}
		}
		$hosts = array_fill_keys(array_column($native['hosts'] ?? [], 'host'), true);
		self::checkRows($data['host_encryption']);
		foreach ($data['host_encryption'] as $row) {
			if (!is_string($row['host'] ?? null) || !isset($hosts[$row['host']])) {
				throw self::invalid();
			}
			unset($hosts[$row['host']]);
		}
		if (!self::counts($data)) {
			throw new RuntimeException(LanguageManager::t('The selection contains no exportable records.'));
		}

		return $data;
	}

	public static function counts(array $data): array {
		$counts = [];
		foreach (Catalog::native($data['source_version']) as $type => [, , , $field]) {
			if (isset($data['configuration'][$field]) && $data['configuration'][$field] !== []) {
				$counts[$type] = count($data['configuration'][$field]);
			}
		}
		foreach ($data['records'] as $type => $rows) {
			if ($rows) {
				$counts[$type] = count($rows);
			}
		}
		foreach (['triggers' => 'triggers', 'graphs' => 'graphs'] as $type => $field) {
			if (!empty($data['configuration'][$field])) {
				$counts[$type] = count($data['configuration'][$field]);
			}
		}
		return $counts;
	}

	public static function filename(?int $timestamp = null): string {
		$zone = \CWebUser::$data['timezone'] ?? 'default';
		if ($zone === 'default') {
			$zone = \CSettingsHelper::getPublic(\CSettingsHelper::DEFAULT_TIMEZONE);
		}
		$zone = $zone === ZBX_DEFAULT_TIMEZONE ? date_default_timezone_get() : $zone;
		$local = (new \DateTimeImmutable('@'.($timestamp ?? time())))->setTimezone(new \DateTimeZone($zone));
		return 'zabbix_backup_'.$local->format('Y-m-d_H-i-s').'.yaml';
	}

	private static function checkRows($rows): void {
		if (!is_array($rows) || array_values($rows) !== $rows || count($rows) !== count(array_filter($rows, 'is_array'))) {
			throw self::invalid();
		}
	}

	public static function checkSize(int $bytes): void {
		if ($bytes > self::MAX_BYTES) {
			throw new RuntimeException(LanguageManager::t('The YAML backup exceeds the 500 MiB file limit.'));
		}
	}

	private static function invalid(): RuntimeException {
		return new RuntimeException(LanguageManager::t('This file is not a supported module backup.'));
	}
}
