<?php declare(strict_types=1);

namespace Modules\SwitchVisual;

use Zabbix\Core\CWidget;

require_once __DIR__ . '/includes/Translation.php';

class Widget extends CWidget {
    public function getDefaultName(): string {
        return \Modules\SwitchVisual\Includes\Translation::t('Switch Visual Panel');
    }
}
