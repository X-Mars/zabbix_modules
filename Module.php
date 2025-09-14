<?php

namespace Modules\ZabbixReports;

use Zabbix\Core\CModule,
    APP,
    CMenu,
    CMenuItem;

class Module extends CModule {

    public function init(): void {
        APP::Component()->get('menu.main')
            ->findOrAdd(_('Reports'))
            ->getSubmenu()
            ->insertAfter(_('Availability report'),
                (new CMenuItem(_('Zabbix Reports')))->setSubMenu(
                    new CMenu([
                        (new CMenuItem(_('Daily Report')))->setAction('reports.daily'),
                        (new CMenuItem(_('Weekly Report')))->setAction('reports.weekly'),
                        (new CMenuItem(_('Monthly Report')))->setAction('reports.monthly')
                    ])
                )
            );
    }
}
