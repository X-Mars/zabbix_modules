<?php

namespace Modules\ZabbixRebrandLogo\Actions;

use CController;
use CControllerResponseData;
use Modules\ZabbixRebrandLogo\Lib\BrandingLimits;
use Modules\ZabbixRebrandLogo\Lib\ServerNameOverride;
use Modules\ZabbixRebrandLogo\Lib\LanguageManager;

class RebrandConfig extends CController {

	use RebrandStorageTrait;

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		return true;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() == USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction(): void {
		$data = [
			'logo_main' => null,
			'logo_main_width' => $this->getLoginLogoSliderWidth(null),
			'login_logo_sizing' => false,
			'login_logo_ratio' => BrandingLimits::LOGIN_LOGO_RATIO,
			'login_logo_max_width' => $this->getLoginLogoSliderMaxWidth(),
			'logo_upload_limit' => $this->getLogoUploadLimit(),
			'logo_sidebar' => null,
			'logo_compact' => null,
			'favicon' => null,
			'brand_footer' => '',
			'brand_help_url' => '',
			'server_name_mode' => ServerNameOverride::MODE_DEFAULT,
			'server_name' => '',
			'zabbix_server_name' => ServerNameOverride::getZabbixDefault(),
			'storage_url' => $this->getPrimaryStorageUrl(),
			'storage_dir' => '',
			'local_conf_dir' => '',
			'brand_conf_file' => '',
			'storage_writable' => false,
			'conf_writable' => false,
			'runtime_user' => $this->getProcessUser(),
			'runtime_error' => null
		];

		try {
			$module_dir = $this->getModuleDir();
			$frontend_root = $this->getFrontendRootFromModuleDir($module_dir);
			$local_conf_dir = $this->getLocalConfDir($frontend_root);
			$config_storage_dir = $this->getPrimaryStorageDir($frontend_root);
			$logo_storage_dir = $this->getLegacyStorageDir($module_dir);
			$logo_storage_url = $this->getLegacyStorageUrl($module_dir);
			$config = $this->loadStoredConfig($config_storage_dir, $logo_storage_dir);

			$this->healLegacyAssets($config, $config_storage_dir, $logo_storage_dir);

			$login_view = $frontend_root.'/local/app/views/general.login.php';
			$data = array_replace($data, [
				'logo_main' => $this->getExistingLogoName($config, 'logo_main', $logo_storage_dir),
				'logo_main_width' => $this->getLoginLogoSliderWidth($config['logo_main_width']),
				'login_logo_sizing' => is_readable($login_view)
					&& hash_file('sha256', $login_view) === hash_file('sha256', $module_dir.'/install/login-view.php'),
				'logo_sidebar' => $this->getExistingLogoName($config, 'logo_sidebar', $logo_storage_dir),
				'logo_compact' => $this->getExistingLogoName($config, 'logo_compact', $logo_storage_dir),
				'favicon' => $this->getExistingLogoName($config, 'favicon', $logo_storage_dir),
				'brand_footer' => $config['brand_footer'] ?? '',
				'brand_help_url' => $config['brand_help_url'] ?? '',
				'server_name_mode' => $config['server_name_mode'],
				'server_name' => $config['server_name'],
				'zabbix_server_name' => ServerNameOverride::getZabbixDefault(),
				'storage_url' => $logo_storage_url,
				'storage_dir' => $logo_storage_dir,
				'local_conf_dir' => $local_conf_dir,
				'brand_conf_file' => $this->getBrandConfFilePath($frontend_root),
				'storage_writable' => $this->canWritePath($logo_storage_dir),
				'conf_writable' => ($this->canWritePath($local_conf_dir) && $this->canWritePath($config_storage_dir)),
				'runtime_user' => $this->getProcessUser(),
				'runtime_error' => null
			]);
		}
		catch (\Throwable $exception) {
			$this->logRuntimeError('Failed to render Branding page', $exception);
			$data['runtime_error'] = 'Failed to initialize branding storage. Review the web server error log for details.';
		}

		$response = new CControllerResponseData($data);
		$response->setTitle(LanguageManager::t('Zabbix Rebrand Logo'));
		$this->setResponse($response);
	}
}
