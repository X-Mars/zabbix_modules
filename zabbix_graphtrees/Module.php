<?php

namespace Modules\ZabbixGraphTrees;

// 动态导入版本兼容工具
require_once __DIR__ . '/lib/ZabbixVersion.php';
require_once __DIR__ . '/lib/LanguageManager.php';
use Modules\ZabbixGraphTrees\Lib\ZabbixVersion;
use Modules\ZabbixGraphTrees\Lib\LanguageManager;
use CMenu;
use CMenuItem;

// 根据实际存在的类来选择基类
// Zabbix 7.0+ 使用 Zabbix\Core\CModule
// Zabbix 6.0 使用 Core\CModule
if (class_exists('Zabbix\Core\CModule')) {
    class ModuleBase extends \Zabbix\Core\CModule {}
} elseif (class_exists('Core\CModule')) {
    class ModuleBase extends \Core\CModule {}
} else {
    // 降级处理：创建一个空的基类
    class ModuleBase {
        public function init(): void {}
    }
}

class Module extends ModuleBase {

    public function init(): void {
        // 兼容不同版本的菜单注册方式
        try {
            // 尝试使用APP类 (Zabbix 6和7都支持)
            if (class_exists('APP')) {
                $app = class_exists('APP') ? new \ReflectionClass('APP') : null;
                
                if ($app && $app->hasMethod('Component')) {
                    // Zabbix 7.0+ 方式
                    \APP::Component()->get('menu.main')
                        ->findOrAdd(_('Monitoring'))
                        ->getSubmenu()
                        ->add(
                            (new CMenuItem(LanguageManager::t('Graph Trees')))
                                ->setAction('graphtrees')
                        );
                }
            }
        } catch (\Exception $e) {
            // 记录错误但不中断执行
            error_log('Graph Trees Module: Failed to register menu - ' . $e->getMessage());
        }
    }
}
