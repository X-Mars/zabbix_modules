<?php
namespace Modules\ZabbixIpam\Lib;
class ViewRenderer {
    public static function render(string $title, $content): void {
        // Preserve trusted view markup while providing the platform header and
        // sidebar toggle required by Zabbix 8's sidebar/banner initialization.
        if (is_string($content)) {
            $content = (new \CObject())->addItem($content);
        }
        $page = class_exists('CHtmlPage') ? new \CHtmlPage() : new \CWidget();
        $page->setTitle($title)->addItem($content)->show();
    }
}
