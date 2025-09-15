<?php

namespace Modules\ZabbixCmdb;

use Zabbix\Core\CModule,
    APP,
    CMenu,
    CMenuItem;

class Module extends CModule {

    public function init(): void {
        APP::Component()->get('menu.main')
            ->findOrAdd(_('Inventory'))
            ->getSubmenu()
            ->add(
                (new CMenuItem(_('CMDB')))->setAction('cmdb')
            );
    }
}
