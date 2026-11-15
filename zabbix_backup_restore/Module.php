<?php

namespace Modules\ZabbixBackupRestore;

use Modules\ZabbixBackupRestore\Lib\LanguageManager;
use Zabbix\Core\CModule;

require_once __DIR__.'/lib/LanguageManager.php';

final class Module extends CModule {

	public function init(): void {
		if (\CWebUser::isLoggedIn() && (int) \CWebUser::getType() === USER_TYPE_SUPER_ADMIN) {
			\APP::Component()->get('menu.main')->findOrAdd(_('Administration'))->getSubmenu()
				->add((new \CMenuItem(LanguageManager::t('Backup and restore')))->setAction('backuprestore'));
		}
	}
}
