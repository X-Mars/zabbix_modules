<?php

namespace Modules\ZabbixIm;

require_once __DIR__ . '/lib/ZabbixVersion.php';
require_once __DIR__ . '/lib/LanguageManager.php';

use Modules\ZabbixIm\Lib\LanguageManager;
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
                        ->findOrAdd(_('Users'))
                        ->getSubmenu()
                        ->add(
                            (new CMenuItem(LanguageManager::t('IM Sync Assistant')))->setSubMenu(
                                new CMenu([
                                    (new CMenuItem(LanguageManager::t('IM Sync')))->setAction('im'),
                                    (new CMenuItem(LanguageManager::t('IM Sync Settings')))->setAction('im.settings'),
                                ])
                            )
                        );
                }
            }
        } catch (\Exception $e) {
            error_log('Zabbix IM Module: Failed to register menu - ' . $e->getMessage());
        }
    }
}
