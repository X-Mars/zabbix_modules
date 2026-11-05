<?php
namespace Modules\ZabbixImportExport\Lib;
final class TagFilter
{
    public static function row(string $prefix, $index, array $tag = [])
    {
        $tag += ["tag" => "", "value" => "", "operator" => 0];
        return (new \CRow([
            new \CTextBox($prefix . "[" . $index . "][tag]", $tag["tag"]),
            new \CComboBox($prefix . "[" . $index . "][operator]", $tag["operator"], null, [
                0 => _("Contains"),
                1 => _("Equals"),
                2 => _("Does not contain"),
                3 => _("Does not equal"),
                4 => _("Exists"),
                5 => _("Does not exist")
            ]),
            new \CTextBox($prefix . "[" . $index . "][value]", $tag["value"]),
            (new \CButton("remove", _("Remove")))
                ->addClass("element-table-remove")
                ->addClass("btn-link")
                ->removeId()
        ]))->addClass("form_row");
    }
    public static function field(
        array $tags,
        int $evaltype,
        string $prefix = "filter_tags",
        string $evalname = "filter_evaltype",
        string $id = "filter-tags"
    ) {
        if (class_exists("CTagFilterFieldHelper")) {
            return \CTagFilterFieldHelper::getTagFilterField(
                [
                    "tags" => $tags ?: [["tag" => "", "value" => "", "operator" => 0]],
                    "evaltype" => $evaltype
                ],
                ["tag_field_name" => $prefix, "evaltype_field_name" => $evalname]
            )->setId($id);
        }
        $table = (new \CTable())->setId($id);
        $table->addRow(
            (new \CRadioButtonList($evalname, $evaltype))
                ->addValue(_("And/Or"), 0)
                ->addValue(_("Or"), 2)
                ->setModern(true)
        );
        foreach ($tags ?: [["tag" => "", "value" => "", "operator" => 0]] as $i => $tag) {
            $table->addItem(self::row($prefix, $i, $tag));
        }
        $table->addRow(
            (new \CButton("add", _("Add")))
                ->addClass("element-table-add")
                ->addClass("btn-link")
                ->removeId()
        );
        return $table;
    }
    public static function template(string $prefix = "filter_tags"): string
    {
        return class_exists("CTagFilterFieldHelper")
            ? \CTagFilterFieldHelper::getTemplate(["tag_field_name" => $prefix])
            : self::row($prefix, "#{rowNum}")->toString();
    }
}
