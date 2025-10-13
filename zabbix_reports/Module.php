<?php

namespace Modules\ZabbixReports;

use Zabbix\Core\CModule,
    APP,
    CMenu,
    CMenuItem,
    Modules\ZabbixReports\Lib\LanguageManager;

class Module extends CModule {

    public function init(): void {
        $lm = new LanguageManager();
        
        APP::Component()->get('menu.main')
            ->findOrAdd(_('Reports'))
            ->getSubmenu()
            ->insertAfter(_('Availability report'),
                (new CMenuItem($lm->t('Zabbix Reports')))->setSubMenu(
                    new CMenu([
                        (new CMenuItem($lm->t('Daily Report')))->setAction('reports.daily'),
                        (new CMenuItem($lm->t('Weekly Report')))->setAction('reports.weekly'),
                        (new CMenuItem($lm->t('Monthly Report')))->setAction('reports.monthly'),
                        (new CMenuItem($lm->t('Custom Report')))->setAction('reports.custom')
                    ])
                )
            );
    }
}
