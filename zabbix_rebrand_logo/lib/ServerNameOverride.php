<?php

namespace Modules\ZabbixRebrandLogo\Lib;

use Modules\ZabbixRebrandLogo\Actions\RebrandStorageTrait;

/**
 * Server name override
 * --------------------
 * Zabbix shows its server name ($ZBX_SERVER_NAME in zabbix.conf.php, set from
 * the ZBX_SERVER_NAME variable in the official Docker images) under the sidebar
 * logo and in front of every browser tab title. Both read the global while the
 * page is rendered, which happens after Module::init(), so replacing the global
 * there changes every page without patching core files.
 *
 * Zabbix loads modules only for signed-in users whose role allows the module, so
 * the sign-in page always shows the server name from zabbix.conf.php.
 */
final class ServerNameOverride {

	use RebrandStorageTrait;

	public const MODE_DEFAULT = 'default';
	public const MODE_CUSTOM = 'custom';
	public const MODE_HIDDEN = 'hidden';

	public const MODES = [self::MODE_DEFAULT, self::MODE_CUSTOM, self::MODE_HIDDEN];

	public const MAX_LENGTH = 128;

	/**
	 * Server name from the Zabbix configuration, captured before it is replaced.
	 */
	private static ?string $zabbix_default = null;

	/**
	 * Replace $ZBX_SERVER_NAME for the current request according to the saved
	 * Branding settings.
	 */
	public function apply(): void {
		global $ZBX_SERVER_NAME;

		self::$zabbix_default = self::getZabbixDefault();

		try {
			$module_dir = $this->getModuleDir();
			$frontend_root = $this->getFrontendRootFromModuleDir($module_dir);
			$config = $this->loadStoredConfig($this->getPrimaryStorageDir($frontend_root),
				$this->getLegacyStorageDir($module_dir)
			);
		}
		catch (\Throwable $exception) {
			// Runs on every request, so stay quiet and keep the default; the Branding
			// page hits the same storage failure and logs it there.
			return;
		}

		$server_name = self::resolve($config['server_name_mode'], $config['server_name']);

		if ($server_name !== null) {
			$ZBX_SERVER_NAME = $server_name;
		}
	}

	/**
	 * Name to show for the given mode; null keeps the Zabbix default. A custom mode
	 * without a name also keeps the default rather than showing nothing.
	 */
	public static function resolve(string $mode, string $name): ?string {
		return match ($mode) {
			self::MODE_HIDDEN => '',
			self::MODE_CUSTOM => ($name !== '') ? $name : null,
			default => null
		};
	}

	/**
	 * Server name configured in Zabbix itself, even after apply() replaced it.
	 */
	public static function getZabbixDefault(): string {
		global $ZBX_SERVER_NAME;

		if (self::$zabbix_default !== null) {
			return self::$zabbix_default;
		}

		return is_string($ZBX_SERVER_NAME ?? null) ? $ZBX_SERVER_NAME : '';
	}
}
