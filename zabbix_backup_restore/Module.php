<?php

namespace Modules\ZabbixBackupRestore;

use Modules\ZabbixBackupRestore\Lib\LanguageManager;
if (class_exists('Zabbix\\Core\\CModule')) {
    class ModuleBase extends \Zabbix\Core\CModule {}
} else {
    class ModuleBase extends \Core\CModule {}
}

require_once __DIR__.'/lib/LanguageManager.php';

final class Module extends ModuleBase {

	public function init(): void {
		if (\CWebUser::isLoggedIn() && (int) \CWebUser::getType() === USER_TYPE_SUPER_ADMIN) {
			\APP::Component()->get('menu.main')->findOrAdd(_('Administration'))->getSubmenu()
				->add((new \CMenuItem(LanguageManager::t('Backup and restore')))->setAction('backuprestore'));
		}
	}
}
