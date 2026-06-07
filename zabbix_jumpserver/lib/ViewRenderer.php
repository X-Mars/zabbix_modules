<?php

namespace Modules\ZabbixJumpserver\Lib;

/**
 * 视图渲染层
 * 提供统一的页面渲染接口，兼容 Zabbix 6.0 / 7.0+
 */
class ViewRenderer {

    public static function render($title, $styleTag, $content) {
        if (class_exists('CHtmlPage')) {
            $page = new \CHtmlPage();
            if ($title) {
                $page->setTitle($title);
            }
            if ($styleTag) {
                $page->addItem($styleTag);
            }
            $page->addItem($content);
            $page->show();
            return;
        }

        echo '<html><head><title>' . htmlspecialchars($title) . '</title></head><body>';
        if ($styleTag) {
            echo $styleTag->toString();
        }
        echo $content->toString();
        echo '</body></html>';
    }
}
