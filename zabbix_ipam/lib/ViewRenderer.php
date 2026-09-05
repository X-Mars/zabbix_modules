<?php
namespace Modules\ZabbixIpam\Lib;
class ViewRenderer {
    public static function render(string $title, $content): void {
        // Module action views are already rendered inside Zabbix's page layout. Passing a
        // literal HTML string to CHtmlPage escapes it, so only object-based callers use it.
        if (is_string($content)) { echo $content; return; }
        if (class_exists('CHtmlPage')) { $p = new \CHtmlPage(); $p->setTitle($title); $p->addItem($content); $p->show(); return; }
        echo '<html><head><title>'.htmlspecialchars($title).'</title></head><body>'.$content.'</body></html>';
    }
}
