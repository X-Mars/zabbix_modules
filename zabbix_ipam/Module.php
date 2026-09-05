<?php
namespace Modules\ZabbixIpam;

require_once __DIR__ . '/lib/LanguageManager.php';
use Modules\ZabbixIpam\Lib\LanguageManager;

if (class_exists('Zabbix\\Core\\CModule')) {
    class ModuleBase extends \Zabbix\Core\CModule {}
} elseif (class_exists('Core\\CModule')) {
    class ModuleBase extends \Core\CModule {}
} else {
    class ModuleBase { public function init(): void {} }
}

/** Registers one Inventory submenu on Zabbix 6.0 through 8.0. */
class Module extends ModuleBase {
    public function init(): void {
        try {
            if (class_exists('APP') && method_exists('APP', 'Component') && class_exists('CMenuItem')) {
                $menu = \APP::Component()->get('menu.main')->findOrAdd(_('Inventory'))->getSubmenu();
                $menu->add((new \CMenuItem(LanguageManager::t('IPAM')))->setSubMenu(new \CMenu([
                    (new \CMenuItem(LanguageManager::t('IP Management')))->setAction('ip.manager'),
                    (new \CMenuItem(LanguageManager::t('IP Details')))->setAction('ip.detail'),
                    (new \CMenuItem(LanguageManager::t('Task Management')))->setAction('ip.scan')
                ])));
            }
        } catch (\Throwable $e) { error_log('IPAM module menu: ' . $e->getMessage()); }
    }
}
