<?php

declare(strict_types=1);

// Defense in depth: this public-source test is non-web-only and must never run
// if an operator accidentally deploys tests/ below a web-served path.
if (!in_array(PHP_SAPI, ['cli', 'phpdbg', 'wasm'], true)) {
	http_response_code(404);
	exit;
}

use Modules\ZabbixRebrandLogo\Lib\ServerNameOverride;

/**
 * Regression tests for the server name override. ServerNameOverride locates
 * config.json relative to its own module directory (<frontend>/modules/zabbix_rebrand_logo
 * -> <frontend>/local/conf/rebrand), so the classes are copied into a throwaway
 * frontend tree and loaded from there.
 */
final class ServerNameOverrideTest {
	private int $assertions = 0;
	private string $root = '';

	public function run(): void {
		// Settings are read on every Zabbix page, so any warning is a failure.
		set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
			throw new ErrorException($message, 0, $severity, $file, $line);
		});

		$this->root = $this->makeFrontendTree();

		try {
			require_once $this->root.'/modules/zabbix_rebrand_logo/lib/BrandingLimits.php';
			require_once $this->root.'/modules/zabbix_rebrand_logo/actions/RebrandStorageTrait.php';
			require_once $this->root.'/modules/zabbix_rebrand_logo/lib/ServerNameOverride.php';

			$this->testResolve();
			$this->testNormalizeConfig();
			$this->testApplyKeepsDefaultWithoutConfig();
			$this->testApplyCustomName();
			$this->testApplyHidden();
			$this->testApplyDefaultModeIgnoresStoredName();
			$this->testApplyReadsLegacyConfig();
			$this->testApplyIgnoresBrokenConfig();
			$this->testApplyWithoutZabbixServerName();
		}
		finally {
			$this->removeTree($this->root);
		}

		echo "ServerNameOverrideTest: {$this->assertions} assertions passed.\n";
	}

	private function testResolve(): void {
		$this->same(null, ServerNameOverride::resolve(ServerNameOverride::MODE_DEFAULT, 'Acme'));
		$this->same('Acme', ServerNameOverride::resolve(ServerNameOverride::MODE_CUSTOM, 'Acme'));
		$this->same('', ServerNameOverride::resolve(ServerNameOverride::MODE_HIDDEN, 'Acme'));
		// A custom mode without a name keeps the Zabbix default rather than hiding it.
		$this->same(null, ServerNameOverride::resolve(ServerNameOverride::MODE_CUSTOM, ''));
		$this->same(null, ServerNameOverride::resolve('unknown', 'Acme'));
	}

	private function testNormalizeConfig(): void {
		$override = new ServerNameOverride();
		$normalize = static fn(array $config): array =>
			(new ReflectionMethod($override, 'normalizeConfig'))->invoke($override, $config);

		$config = $normalize([]);
		$this->same(ServerNameOverride::MODE_DEFAULT, $config['server_name_mode']);
		$this->same('', $config['server_name']);

		$config = $normalize(['server_name_mode' => 'custom', 'server_name' => "  Acme Monitoring \t"]);
		$this->same(ServerNameOverride::MODE_CUSTOM, $config['server_name_mode']);
		$this->same('Acme Monitoring', $config['server_name']);

		// Hand-edited config.json: unknown modes and wrong types fall back quietly.
		foreach ([5, 'HIDDEN', 'none', null, true, ['hidden']] as $mode) {
			$this->same(ServerNameOverride::MODE_DEFAULT, $normalize(['server_name_mode' => $mode])['server_name_mode']);
		}

		$config = $normalize([
			'server_name_mode' => 'custom',
			'server_name' => ['Acme'],
			'brand_footer' => ['footer'],
			'brand_help_url' => ['url' => 'https://example.com']
		]);
		$this->same('', $config['server_name']);
		$this->same('', $config['brand_footer']);
		$this->same('', $config['brand_help_url']);
		$this->same('1', $normalize(['server_name' => 1])['server_name']);

		// Capped by characters, not bytes, so a multibyte name is never split.
		$config = $normalize(['server_name_mode' => 'custom', 'server_name' => str_repeat('é', 200)]);
		$this->same(ServerNameOverride::MAX_LENGTH, mb_strlen($config['server_name']));
		$this->same(true, mb_check_encoding($config['server_name'], 'UTF-8'));
	}

	private function testApplyKeepsDefaultWithoutConfig(): void {
		$this->writeConfig(null);

		$this->same('Zabbix docker', $this->apply('Zabbix docker'));
		$this->same('Zabbix docker', ServerNameOverride::getZabbixDefault());
	}

	private function testApplyCustomName(): void {
		$this->writeConfig(['server_name_mode' => 'custom', 'server_name' => 'Acme Monitoring']);

		$this->same('Acme Monitoring', $this->apply('Zabbix docker'));
		// The Branding page still needs the configured default after the override.
		$this->same('Zabbix docker', ServerNameOverride::getZabbixDefault());
	}

	private function testApplyHidden(): void {
		$this->writeConfig(['server_name_mode' => 'hidden', 'server_name' => 'Acme Monitoring']);

		$this->same('', $this->apply('Zabbix docker'));
		$this->same('Zabbix docker', ServerNameOverride::getZabbixDefault());
	}

	private function testApplyDefaultModeIgnoresStoredName(): void {
		$this->writeConfig(['server_name_mode' => 'default', 'server_name' => 'Acme Monitoring']);

		$this->same('Zabbix docker', $this->apply('Zabbix docker'));
	}

	private function testApplyReadsLegacyConfig(): void {
		// Installs that predate local/conf keep config.json in assets/logos.
		$this->writeConfig(['server_name_mode' => 'hidden'], 'modules/zabbix_rebrand_logo/assets/logos');

		$this->same('', $this->apply('Zabbix docker'));
	}

	private function testApplyIgnoresBrokenConfig(): void {
		$this->writeConfig(null);
		file_put_contents($this->root.'/local/conf/rebrand/config.json', '{"server_name_mode": "hidden",');

		$this->same('Zabbix docker', $this->apply('Zabbix docker'));
	}

	private function testApplyWithoutZabbixServerName(): void {
		$this->writeConfig(['server_name_mode' => 'default']);
		$this->resetCapturedDefault();
		unset($GLOBALS['ZBX_SERVER_NAME']);

		(new ServerNameOverride())->apply();
		// Zabbix tests the global with isset(), so it must still read as "not set".
		$this->same(false, isset($GLOBALS['ZBX_SERVER_NAME']));
		$this->same('', ServerNameOverride::getZabbixDefault());

		$this->writeConfig(['server_name_mode' => 'custom', 'server_name' => 'Acme Monitoring']);
		$this->resetCapturedDefault();
		unset($GLOBALS['ZBX_SERVER_NAME']);

		(new ServerNameOverride())->apply();
		$this->same('Acme Monitoring', $GLOBALS['ZBX_SERVER_NAME']);
	}

	/**
	 * Run the override as Module::init() does on a fresh request and return the
	 * resulting $ZBX_SERVER_NAME.
	 */
	private function apply(string $zabbix_server_name): ?string {
		$this->resetCapturedDefault();
		$GLOBALS['ZBX_SERVER_NAME'] = $zabbix_server_name;

		(new ServerNameOverride())->apply();

		return $GLOBALS['ZBX_SERVER_NAME'];
	}

	private function resetCapturedDefault(): void {
		(new ReflectionProperty(ServerNameOverride::class, 'zabbix_default'))->setValue(null, null);
	}

	/**
	 * Write config.json into $dir (relative to the frontend root), removing every
	 * other copy first; null removes them all.
	 */
	private function writeConfig(?array $config, string $dir = 'local/conf/rebrand'): void {
		foreach (['local/conf/rebrand', 'modules/zabbix_rebrand_logo/assets/logos'] as $config_dir) {
			$file = $this->root.'/'.$config_dir.'/config.json';

			if (is_file($file)) {
				unlink($file);
			}
		}

		if ($config !== null) {
			file_put_contents($this->root.'/'.$dir.'/config.json', json_encode($config, JSON_THROW_ON_ERROR));
		}

		clearstatcache();
	}

	private function makeFrontendTree(): string {
		$root = sys_get_temp_dir().'/rebrand-test-'.bin2hex(random_bytes(6));
		$module = $root.'/modules/zabbix_rebrand_logo';

		foreach (['actions', 'lib', 'assets/logos'] as $dir) {
			mkdir($module.'/'.$dir, 0700, true);
		}

		mkdir($root.'/local/conf/rebrand', 0700, true);

		$source = dirname(__DIR__);

		foreach (['actions/RebrandStorageTrait.php', 'lib/BrandingLimits.php', 'lib/ServerNameOverride.php'] as $file) {
			if (!copy($source.'/'.$file, $module.'/'.$file)) {
				throw new RuntimeException('Cannot copy '.$file.' into the test tree.');
			}
		}

		return realpath($root);
	}

	private function removeTree(string $root): void {
		if ($root === '' || !str_contains(basename($root), 'rebrand-test-') || !is_dir($root)) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($items as $item) {
			$item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
		}

		rmdir($root);
	}

	private function same($expected, $actual): void {
		$this->assertions++;

		if ($expected !== $actual) {
			throw new RuntimeException('Expected '.var_export($expected, true).', got '.var_export($actual, true));
		}
	}
}

(new ServerNameOverrideTest())->run();
