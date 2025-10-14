<?php

namespace Modules\ZabbixCmdb\Lib;

require_once __DIR__ . '/ZabbixVersion.php';

/**
 * 视图渲染兼容层
 * 提供统一的页面渲染接口，兼容Zabbix 6和7
 */
class ViewRenderer {
    
    /**
     * 创建并显示页面（模块视图需要直接输出）
     * 
     * Zabbix 6.0 模块视图的特点：
     * - 使用 CWidget 并调用 show() 直接输出
     * - 通过 $data['title'] 获取标题（需要在控制器中设置）
     * - 不能返回对象，必须直接显示
     * 
     * @param string $title 页面标题
     * @param CTag $styleTag 样式标签（可选）
     * @param CDiv $content 内容
     */
    public static function render($title, $styleTag, $content) {
        // Zabbix 6 使用 CWidget
        if (class_exists('CWidget')) {
            $widget = new \CWidget();
            if ($title) {
                $widget->setTitle($title);
            }
            if ($styleTag) {
                $widget->addItem($styleTag);
            }
            $widget->addItem($content);
            $widget->show();
            return;
        }
        
        // Zabbix 7 使用 CHtmlPage (如果没有CWidget)
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
        
        // 最后的fallback：直接输出HTML
        echo '<html><head><title>' . htmlspecialchars($title) . '</title></head><body>';
        if ($styleTag) {
            echo $styleTag->toString();
        }
        echo $content->toString();
        echo '</body></html>';
    }
    
    /**
     * 检测当前环境应该使用哪个渲染类
     */
    public static function getRendererClass(): string {
        if (ZabbixVersion::isVersion7() && class_exists('CHtmlPage')) {
            return 'CHtmlPage';
        }
        if (class_exists('CWidget')) {
            return 'CWidget';
        }
        return 'fallback';
    }
}
