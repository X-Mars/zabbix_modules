<?php

namespace Modules\ZabbixCmdb;

// 动态导入版本兼容工具
require_once __DIR__ . '/lib/ZabbixVersion.php';
use Modules\ZabbixCmdb\Lib\ZabbixVersion;
use Modules\ZabbixCmdb\Lib\LanguageManager;
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
        $lm = new LanguageManager();
        
        // 兼容不同版本的菜单注册方式
        try {
            // 尝试使用APP类 (Zabbix 6和7都支持)
            if (class_exists('APP')) {
                $app = class_exists('APP') ? new \ReflectionClass('APP') : null;
                
                if ($app && $app->hasMethod('Component')) {
                    // Zabbix 7.0+ 方式
                    \APP::Component()->get('menu.main')
                        ->findOrAdd(_('Inventory'))
                        ->getSubmenu()
                        ->add(
                            (new CMenuItem($lm->t('CMDB')))->setSubMenu(
                                new CMenu([
                                    (new CMenuItem($lm->t('Host List')))->setAction('cmdb'),
                                    (new CMenuItem($lm->t('Host Groups')))->setAction('cmdb.groups')
                                ])
                            )
                        );
                }
            }
        } catch (\Exception $e) {
            // 记录错误但不中断执行
            error_log('CMDB Module: Failed to register menu - ' . $e->getMessage());
        }
    }
}
