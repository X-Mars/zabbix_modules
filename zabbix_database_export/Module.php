<?php

namespace Modules\ZabbixDatabaseExport;

use Zabbix\Core\CModule;
use Modules\ZabbixDatabaseExport\Lib\Access;
use Modules\ZabbixDatabaseExport\Lib\LanguageManager;

require_once __DIR__ . '/lib/Access.php';
require_once __DIR__ . '/lib/LanguageManager.php';

final class Module extends CModule {
    public function init(): void {
        if (Access::allowed()) {
            \APP::Component()->get('menu.main')->findOrAdd(_('Administration'))->getSubmenu()
                ->add((new \CMenuItem(LanguageManager::t('Database export')))->setAction('databaseexport'));
        }
    }
}
