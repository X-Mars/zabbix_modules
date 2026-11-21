<?php

namespace Modules\ZabbixRebrandLogo;

use APP;
use CMenuItem;
use CWebUser;
use Modules\ZabbixRebrandLogo\Lib\ServerNameOverride;
use Modules\ZabbixRebrandLogo\Lib\LanguageManager;

if (class_exists('Zabbix\\Core\\CModule')) {
    class ModuleBase extends \Zabbix\Core\CModule {}
} else {
    class ModuleBase extends \Core\CModule {}
}

class Module extends ModuleBase {

	public function init(): void {
		(new ServerNameOverride())->apply();

		// Every Branding action requires Super Admin, so only Super Admins get the
		// menu item; for anyone else findOrAdd() would create an Administration
		// section whose only entry leads to an access-denied page.
		if (CWebUser::getType() != USER_TYPE_SUPER_ADMIN) {
			return;
		}

		APP::Component()->get('menu.main')
			->findOrAdd(_('Administration'))
				->getSubmenu()
					->add(
						(new CMenuItem(LanguageManager::t('Zabbix Rebrand Logo')))->setAction('rebrand.config')
					);
	}
}
