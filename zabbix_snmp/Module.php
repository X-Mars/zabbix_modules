<?php

namespace Modules\ZabbixSnmp;

require_once __DIR__ . '/lib/ZabbixVersion.php';
require_once __DIR__ . '/lib/LanguageManager.php';

use Modules\ZabbixSnmp\Lib\LanguageManager;
use Modules\ZabbixSnmp\Lib\ZabbixVersion;
use CMenu;
use CMenuItem;

if (class_exists('Zabbix\Core\CModule')) {
    class ModuleBase extends \Zabbix\Core\CModule {}
} elseif (class_exists('Core\CModule')) {
    class ModuleBase extends \Core\CModule {}
} else {
    class ModuleBase {
        public function init(): void {}
    }
}

class Module extends ModuleBase {

    public function init(): void {
        try {
            if (class_exists('APP')) {
                $app = new \ReflectionClass('APP');

                if ($app->hasMethod('Component')) {
                    \APP::Component()->get('menu.main')
                        ->findOrAdd(_('Data collection'))
                        ->getSubmenu()
                        ->add(
                            (new CMenuItem(LanguageManager::t('SNMP Assistant')))->setSubMenu(
                                new CMenu([
                                    (new CMenuItem(LanguageManager::t('Zabbix Mibs')))->setAction('snmp'),
                                    (new CMenuItem(LanguageManager::t('Zabbix Walk')))->setAction('snmp.walk')
                                ])
                            )
                        );
                }
            }
        } catch (\Exception $e) {
            error_log('Zabbix SNMP Module: Failed to register menu - ' . $e->getMessage());
        }
    }
}