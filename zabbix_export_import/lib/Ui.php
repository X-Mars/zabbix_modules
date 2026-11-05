<?php
namespace Modules\ZabbixImportExport\Lib;
/** Scoped semantic HTML; values are escaped by Zabbix's native CTag. */
final class Ui
{
    public static function tag(string $tag, $content = null, string $class = "")
    {
        $el = new \CTag($tag, !in_array($tag, ["input", "hr", "br"], true), $content);
        if ($class !== "") {
            $el->addClass($class);
        }
        return $el;
    }
    public static function div($content = null, string $class = "")
    {
        return self::tag("div", $content, $class);
    }
    public static function input(string $name, string $value = "", string $type = "text")
    {
        return self::tag("input")
            ->setAttribute("type", $type)
            ->setAttribute("name", $name)
            ->setAttribute("value", $value)
            ->addClass($type === "checkbox" ? "ixe-check" : "ixe-input");
    }
    public static function select(string $name, array $options, $value)
    {
        $el = self::tag("select", null, "ixe-select")->setAttribute("name", $name);
        foreach ($options as $key => $label) {
            $o = self::tag("option", $label)->setAttribute("value", (string) $key);
            if ((string) $key === (string) $value) {
                $o->setAttribute("selected", "selected");
            }
            $el->addItem($o);
        }
        return $el;
    }
    public static function button(
        string $label,
        string $variant = "default",
        string $type = "button",
        string $name = "",
        string $value = ""
    ) {
        $b = self::tag("button", $label, "ixe-button ixe-button--" . $variant)->setAttribute(
            "type",
            $type
        );
        if ($name !== "") {
            $b->setAttribute("name", $name)->setAttribute("value", $value);
        }
        return $b;
    }
    public static function field(string $label, $control, string $hint = "")
    {
        return self::tag(
            "label",
            [
                self::tag("span", $label, "ixe-label"),
                $control,
                $hint !== "" ? self::tag("small", $hint, "ixe-muted") : null
            ],
            "ixe-field"
        );
    }
    public static function badge(string $text, string $kind = "info")
    {
        return self::tag("span", $text, "ixe-badge ixe-badge--" . $kind);
    }
    public static function icon(string $name)
    {
        $paths = [
            "upload" => "M12 16V3m-5 5 5-5 5 5M4 16v5h16v-5",
            "file" => "M14 2H6v20h12V6l-4-4Zm0 0v5h5M8 12h8m-8 4h6",
            "search" => "m21 21-5-5M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0",
            "close" => "m6 6 12 12M6 18 18 6"
        ];
        return self::tag(
            "svg",
            self::tag("path")->setAttribute("d", $paths[$name] ?? $paths["file"])
        )
            ->setAttribute("viewBox", "0 0 24 24")
            ->setAttribute("fill", "none")
            ->setAttribute("stroke", "currentColor")
            ->setAttribute("stroke-width", "1.6")
            ->setAttribute("aria-hidden", "true")
            ->addClass("ixe-icon");
    }
}
