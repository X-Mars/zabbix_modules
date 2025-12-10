<?php

namespace Modules\ZabbixCmdb\Lib;

/**
 * 视图渲染层 for Zabbix 7.0
 * 提供统一的页面渲染接口
 */
class ViewRenderer {
    
    /**
     * 创建并显示页面（Zabbix 7.0使用CHtmlPage）
     * 
     * @param string $title 页面标题
     * @param CTag $styleTag 样式标签（可选）
     * @param CDiv $content 内容
     */
    public static function render($title, $styleTag, $content) {
        // Zabbix 7.0 使用 CHtmlPage
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
        
        // Fallback: 直接输出HTML
        echo '<html><head><title>' . htmlspecialchars($title) . '</title></head><body>';
        if ($styleTag) {
            echo $styleTag->toString();
        }
        echo $content->toString();
        echo '</body></html>';
    }
}
