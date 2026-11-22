<?php
namespace Modules\SwitchVisual;

use Modules\SwitchVisual\Includes\Translation;

require_once __DIR__.'/includes/Translation.php';

if (class_exists('Zabbix\Core\CModule')) {
    class ModuleBase extends \Zabbix\Core\CModule {}
} else {
    class ModuleBase extends \Core\CModule {}
}

// Zabbix 6.0 has no third-party dashboard widget API. Offer the same renderer
// as a regular module page; Zabbix 7/8 continue loading Widget.php.
class Module extends ModuleBase {
    public function init(): void {
        \APP::Component()->get('menu.main')->findOrAdd(_('Monitoring'))->getSubmenu()
            ->add((new \CMenuItem(Translation::t('Switch visual')))->setAction('switchvisual.view'));
    }
}
