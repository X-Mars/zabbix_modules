<?php
namespace Modules\ZabbixImportExport\Lib;
final class HostFilter
{
    public static function context(array $input): array
    {
        // Compact pages never revive invisible native-filter profile values.
        // Explicit legacy exports retain their supported advanced API filters.
        if (!isset($input["export"]) || ($input["ui"] ?? "") === "compact") {
            $input = array_intersect_key(
                $input,
                array_flip([
                    "filter_groups",
                    "filter_host",
                    "filter_item_name",
                    "filter_item_origin"
                ])
            );
            $input += ["filter_item_origin" => -1];
        }
        return self::read($input);
    }
    public static function read(array $input): array
    {
        $f = [];
        foreach (["host", "dns", "ip", "port", "item_name", "item_key", "item_delay"] as $key) {
            $v = $input["filter_" . $key] ?? "";
            if (!is_string($v) || strlen($v) > 255) {
                throw new \RuntimeException("Invalid filter.");
            }
            $f[$key] = $v;
        }
        foreach (
            [
                "status" => [-1, 0, 1],
                "monitored_by" => [-1, 0, 1, 2],
                "evaltype" => [0, 2],
                "item_type" => array_merge([-1], range(0, 22)),
                "item_status" => [-1, 0, 1],
                "item_state" => [-1, 0, 1],
                "item_value_type" => array_merge([-1], range(0, 5)),
                "item_origin" => [-1, 0, 1, 2],
                "item_evaltype" => [0, 2]
            ]
            as $key => $values
        ) {
            $v =
                $input["filter_" . $key] ??
                (in_array($key, ["evaltype", "item_evaltype"], true)
                    ? 0
                    : ($key === "item_origin"
                        ? 0
                        : -1));
            if (!is_scalar($v) || !in_array((string) $v, array_map("strval", $values), true)) {
                throw new \RuntimeException("Invalid filter: " . $key);
            }
            $f[$key] = (int) $v;
        }
        foreach (["groups", "templates", "proxyids", "proxy_groupids"] as $key) {
            $v = $input["filter_" . $key] ?? [];
            if (
                !is_array($v) ||
                count($v) > 100 ||
                array_filter($v, function ($id) {
                    return !is_scalar($id) || !ctype_digit((string) $id);
                })
            ) {
                throw new \RuntimeException("Invalid selection.");
            }
            $f[$key] = array_values(
                array_filter($v, function ($id) {
                    return (string) $id !== "0";
                })
            );
        }
        $f["tags"] = [];
        if (!is_array($input["filter_tags"] ?? []) || !is_array($input["filter_item_tags"] ?? [])) {
            throw new \RuntimeException("Invalid tag filter.");
        }
        foreach ($input["filter_tags"] ?? [] as $tag) {
            if (
                !is_array($tag) ||
                !is_string($tag["tag"] ?? null) ||
                !is_string($tag["value"] ?? null) ||
                !in_array((string) ($tag["operator"] ?? ""), ["0", "1", "2", "3", "4", "5"], true)
            ) {
                throw new \RuntimeException("Invalid tag filter.");
            }
            if (strlen($tag["tag"]) > 255 || strlen($tag["value"]) > 255) {
                throw new \RuntimeException("Invalid tag filter.");
            }
            if ($tag["tag"] !== "") {
                $f["tags"][] = [
                    "tag" => $tag["tag"],
                    "value" => $tag["value"],
                    "operator" => (int) $tag["operator"]
                ];
            }
            if (count($f["tags"]) > 50) {
                throw new \RuntimeException("Too many tags.");
            }
        }
        $f["item_tags"] = [];
        foreach ($input["filter_item_tags"] ?? [] as $tag) {
            if (
                !is_array($tag) ||
                !is_string($tag["tag"] ?? null) ||
                !is_string($tag["value"] ?? null) ||
                !in_array((string) ($tag["operator"] ?? ""), ["0", "1", "2", "3", "4", "5"], true)
            ) {
                throw new \RuntimeException("Invalid tag filter.");
            }
            if ($tag["tag"] !== "") {
                $f["item_tags"][] = [
                    "tag" => $tag["tag"],
                    "value" => $tag["value"],
                    "operator" => (int) $tag["operator"]
                ];
            }
            if (count($f["item_tags"]) > 50) {
                throw new \RuntimeException("Too many tags.");
            }
        }
        return $f;
    }
    public static function options(array $f): array
    {
        $o = [
            "output" => ["hostid", "host", "name"],
            "sortfield" => ["name", "hostid"],
            "sortorder" => "ASC",
            "limit" => 10001,
            "search" => array_filter(
                ["name" => $f["host"], "dns" => $f["dns"], "ip" => $f["ip"]],
                function ($v) {
                    return $v !== "";
                }
            ),
            "filter" => array_filter(
                ["port" => $f["port"], "status" => $f["status"] === -1 ? null : $f["status"]],
                function ($v) {
                    return $v !== null && $v !== "";
                }
            ),
            "tags" => $f["tags"],
            "evaltype" => $f["evaltype"]
        ];
        if ($f["groups"]) {
            if (!function_exists("getSubGroups")) {
                require_once \APP::getRootDir() . "/include/hostgroups.inc.php";
            }
            $o["groupids"] = getSubGroups($f["groups"]);
        }
        if ($f["templates"]) {
            $o["templateids"] = $f["templates"];
        }
        if ($f["monitored_by"] !== -1) {
            if (ZabbixVersion::modern()) {
                $o["filter"]["monitored_by"] = $f["monitored_by"];
                if ($f["monitored_by"] === 1 && $f["proxyids"]) {
                    $o["proxyids"] = $f["proxyids"];
                }
                if ($f["monitored_by"] === 2 && $f["proxy_groupids"]) {
                    $o["proxy_groupids"] = $f["proxy_groupids"];
                }
            } else {
                if ($f["monitored_by"] === 2) {
                    throw new \RuntimeException("Proxy groups require Zabbix 7.0.");
                }
                $o["proxyids"] =
                    $f["monitored_by"] === 0
                        ? ["0"]
                        : ($f["proxyids"] ?:
                        array_column(\API::Proxy()->get(["output" => ["proxyid"]]), "proxyid"));
            }
        }
        return $o;
    }
    public static function hosts(array $f): array
    {
        // Frontend API facades share a mutable wrapper. Resolve group/proxy calls before selecting Host().
        $options = self::options($f);
        $rows = \API::Host()->get($options);
        if (count($rows) > 10000) {
            throw new \RuntimeException("More than 10000 hosts; narrow the filter.");
        }
        return $rows;
    }
    public static function selections(array $f): array
    {
        $out = [];
        foreach (
            [
                "groups" => ["HostGroup", "groupid", "name", "groupids"],
                "templates" => ["Template", "templateid", "name", "templateids"],
                "proxyids" => [
                    "Proxy",
                    "proxyid",
                    ZabbixVersion::modern() ? "name" : "host",
                    "proxyids"
                ],
                "proxy_groupids" => ["ProxyGroup", "proxy_groupid", "name", "proxy_groupids"]
            ]
            as $key => $spec
        ) {
            $out[$key] = [];
            if (!$f[$key]) {
                continue;
            }
            if ($key === "proxy_groupids" && !ZabbixVersion::modern()) {
                continue;
            }
            $api = call_user_func(["API", $spec[0]]);
            foreach ($api->get(["output" => [$spec[1], $spec[2]], $spec[3] => $f[$key]]) as $r) {
                $out[$key][] = ["id" => $r[$spec[1]], "name" => $r[$spec[2]]];
            }
        }
        return $out;
    }
}
