<?php

namespace Modules\ZabbixBackupMysql;

use Zabbix\Core\CModule;
use Modules\ZabbixBackupMysql\Lib\Access;
use Modules\ZabbixBackupMysql\Lib\LanguageManager;

require_once __DIR__ . '/lib/Access.php';
require_once __DIR__ . '/lib/LanguageManager.php';

final class Module extends CModule {
    public function init(): void {
        if (Access::allowed()) {
            \APP::Component()->get('menu.main')->findOrAdd(_('Administration'))->getSubmenu()
                ->add((new \CMenuItem(LanguageManager::t('MySQL backup')))->setAction('backupmysql'));
        }
    }
}
