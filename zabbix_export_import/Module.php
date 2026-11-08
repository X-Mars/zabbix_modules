<?php
namespace Modules\ZabbixImportExport;
require_once __DIR__ . "/lib/LanguageManager.php";
require_once __DIR__ . "/lib/ZabbixVersion.php";
use Modules\ZabbixImportExport\Lib\LanguageManager as L;
use Modules\ZabbixImportExport\Lib\ZabbixVersion;
class_alias(ZabbixVersion::getModuleBaseClass(), __NAMESPACE__ . "\\ModuleBase");
class Module extends ModuleBase
{
    public function init(): void
    {
        if (\CWebUser::$data["type"] < USER_TYPE_ZABBIX_USER) {
            return;
        }
        $menu = new \CMenu();
        foreach (
            [
                "hosts" => "Host transfer",
                "items" => "Item transfer",
                "templates" => "Template transfer"
            ]
            as $key => $label
        ) {
            $menu->add((new \CMenuItem(L::t($label)))->setAction("importexport." . $key));
        }
        \APP::Component()
            ->get("menu.main")
            ->findOrAdd(_("Inventory"))
            ->getSubmenu()
            ->add((new \CMenuItem(L::t("Zabbix Import/Export")))->setSubMenu($menu));
    }
}
