<?php

namespace Modules\ZabbixReports;

// 动态导入版本兼容工具
require_once __DIR__ . '/lib/ZabbixVersion.php';
use Modules\ZabbixReports\Lib\ZabbixVersion;
use Modules\ZabbixReports\Lib\LanguageManager;
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
                        ->findOrAdd(_('Reports'))
                        ->getSubmenu()
                        ->insertAfter(_('Availability report'),
                            (new CMenuItem($lm->t('Zabbix Reports')))->setSubMenu(
                                new CMenu([
                                    (new CMenuItem($lm->t('Daily Report')))->setAction('reports.daily'),
                                    (new CMenuItem($lm->t('Weekly Report')))->setAction('reports.weekly'),
                                    (new CMenuItem($lm->t('Monthly Report')))->setAction('reports.monthly'),
                                    (new CMenuItem($lm->t('Custom Report')))->setAction('reports.custom')
                                ])
                            )
                        );
                }
            }
        } catch (\Exception $e) {
            // 记录错误但不中断执行
            error_log('Zabbix Reports Module: Failed to register menu - ' . $e->getMessage());
        }
    }
}
