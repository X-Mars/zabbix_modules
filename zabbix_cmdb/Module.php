<?php

namespace Modules\ZabbixCmdb;

use Zabbix\Core\CModule,
    APP,
    CMenu,
    CMenuItem,
    Modules\ZabbixCmdb\Lib\LanguageManager;

class Module extends CModule {

    public function init(): void {
        $lm = new LanguageManager();
        
        APP::Component()->get('menu.main')
            ->findOrAdd(_('Inventory'))
            ->getSubmenu()
            ->add(
                (new CMenuItem($lm->t('CMDB')))->setSubMenu(
                    new CMenu([
                        (new CMenuItem($lm->t('Host List')))->setAction('cmdb'),
                        (new CMenuItem($lm->t('Host Groups')))->setAction('cmdb.groups')
                    ])
                )
            );
    }
}
