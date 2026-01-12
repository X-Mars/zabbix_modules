<?php
/**
 * 视图渲染器
 * 兼容 Zabbix 6.0/7.0/7.4
 */

namespace Modules\ZabbixRack\Lib;

class ViewRenderer {
    
    /**
     * 渲染页面
     */
    public static function render($title, $styleTag, $content, $headScript = null) {
        // Zabbix 7.0+ 使用 CHtmlPage
        if (class_exists('CHtmlPage')) {
            $page = new \CHtmlPage();
            if ($title) {
                $page->setTitle($title);
            }
            if ($styleTag) {
                $page->addItem($styleTag);
            }
            if ($headScript) {
                $page->addItem($headScript);
            }
            $page->addItem($content);
            $page->show();
            return;
        }
        
        // Zabbix 6.0 fallback
        echo '<html><head><title>' . htmlspecialchars($title) . '</title></head><body>';
        if ($styleTag) {
            echo $styleTag->toString();
        }
        if ($headScript) {
            echo $headScript->toString();
        }
        echo $content->toString();
        echo '</body></html>';
    }
}
