<?php
namespace Modules\ZabbixImportExport\Lib;
final class ViewGrid
{
    public static function create()
    {
        if (!class_exists("CFormGrid")) {
            return new LegacyGrid();
        }
        $grid = new \CFormGrid();
        if (defined("CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE")) {
            $grid->addClass(\CFormGrid::ZBX_STYLE_FORM_GRID_LABEL_WIDTH_TRUE);
        }
        return $grid;
    }
    public static function field($value)
    {
        return class_exists("CFormField") ? new \CFormField($value) : new \CDiv($value);
    }
    public static function page(string $title)
    {
        if (class_exists("CHtmlPage")) {
            return (new \CHtmlPage())->setTitle($title);
        }
        if (class_exists("CWidget")) {
            return (new \CWidget())->setTitle($title);
        }
        throw new \RuntimeException("The native Zabbix page components are unavailable.");
    }
    public static function csrfName(): string
    {
        return ZabbixVersion::csrfName();
    }
    public static function csrf(string $action): string
    {
        return ZabbixVersion::csrfToken($action);
    }
}
final class LegacyGrid extends \CDiv
{
    private $list;
    public function __construct()
    {
        parent::__construct();
        $this->list = new \CFormList();
        parent::addItem($this->list);
    }
    public function addItem($value)
    {
        if ($this->list && is_array($value) && count($value) === 2) {
            $this->list->addRow($value[0], $value[1]);
        } else {
            parent::addItem($value);
        }
        return $this;
    }
}
