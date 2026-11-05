<?php
namespace Modules\ZabbixImportExport\Lib;
final class ImportValidator
{
    public static function record(string $type, array $r): void
    {
        if ($type === "templates") {
            TemplateTransferService::validate($r);
            return;
        }
        if (!in_array($type, ["hosts", "items"], true)) {
            throw new \RuntimeException("Invalid record type.");
        }
        foreach (
            $type === "items"
                ? ["host", "name", "key_", "type", "value_type"]
                : ["host", "host_groups"]
            as $field
        ) {
            if (!array_key_exists($field, $r) || $r[$field] === "" || $r[$field] === []) {
                throw new \RuntimeException("Missing required field: " . $field);
            }
        }
        if (!empty($r["_redacted"])) {
            throw new \RuntimeException(
                "Supply redacted credentials securely and remove _redacted before importing."
            );
        }
        self::walk($r);
        foreach (["host", "name", "key_"] as $field) {
            if (isset($r[$field]) && !is_string($r[$field])) {
                throw new \RuntimeException("Invalid field type: " . $field);
            }
        }
        $allowed =
            $type === "hosts"
                ? explode(
                    " ",
                    "host name description status host_groups interfaces monitored_by proxy_name proxy_group_name tags inventory inventory_mode templates item_references source_hostid _debug _redacted"
                )
                : array_merge(
                    ItemTransferService::fields(),
                    explode(
                        " ",
                        "host origin tags preprocessing interface master_item valuemap template discovery _redacted source_itemid _debug"
                    )
                );
        if (array_diff(array_keys($r), $allowed)) {
            throw new \RuntimeException(
                "Unsupported fields: " . implode(", ", array_diff(array_keys($r), $allowed))
            );
        }
        foreach (
            [
                "host_groups",
                "interfaces",
                "templates",
                "tags",
                "inventory",
                "item_references",
                "preprocessing",
                "interface",
                "master_item",
                "valuemap",
                "config"
            ]
            as $field
        ) {
            if (isset($r[$field]) && !is_array($r[$field])) {
                throw new \RuntimeException("Invalid field type: " . $field);
            }
        }
        if ($type === "items") {
            if (
                !is_scalar($r["type"]) ||
                !is_scalar($r["value_type"]) ||
                !ctype_digit((string) $r["type"]) ||
                !ctype_digit((string) $r["value_type"])
            ) {
                throw new \RuntimeException("Invalid item type or value type.");
            }
            if (
                !in_array(
                    (string) $r["type"],
                    array_map("strval", [
                        0,
                        2,
                        3,
                        5,
                        7,
                        10,
                        11,
                        12,
                        13,
                        14,
                        15,
                        16,
                        17,
                        18,
                        19,
                        20,
                        21,
                        22
                    ]),
                    true
                )
            ) {
                throw new \RuntimeException("Unsupported item type.");
            }
            if ((int) $r["type"] === 22 && !ZabbixVersion::modern()) {
                throw new \RuntimeException("Browser items require Zabbix 7.0.");
            }
            if ((int) $r["value_type"] > 5 || (int) $r["value_type"] < 0) {
                throw new \RuntimeException("Unsupported value type.");
            }
            if ((int) $r["value_type"] === 5 && !ZabbixVersion::modern()) {
                throw new \RuntimeException("Binary value type requires Zabbix 7.0.");
            }
            if ((int) $r["value_type"] === 5 && (int) $r["type"] !== 18) {
                throw new \RuntimeException("Binary values require a dependent item.");
            }
            if (($r["origin"] ?? "local") !== "local") {
                throw new \RuntimeException("Inherited and discovery items are read-only.");
            }
            $required = [];
            $itemType = (int) $r["type"];
            if (
                !in_array($itemType, [2, 17, 18], true) &&
                !($itemType === 7 && strpos($r["key_"], "mqtt.get") !== false)
            ) {
                $required[] = "delay";
            }
            if (in_array($itemType, [11, 13, 14, 15, 21, 22], true)) {
                $required[] = "params";
            }
            if ($itemType === 20) {
                $required[] = "snmp_oid";
            }
            if ($itemType === 19) {
                $required[] = "url";
                if (in_array((int) ($r["post_type"] ?? 0), [2, 3], true)) {
                    $required[] = "posts";
                }
            }
            if ($itemType === 12 && $r["key_"] !== "ipmi.get") {
                $required[] = "ipmi_sensor";
            }
            if ($itemType === 13 && ($r["authtype"] ?? 0) == 1) {
                $required = array_merge($required, ["privatekey", "publickey"]);
            }
            if ($itemType === 16 && !empty($r["username"])) {
                $required[] = "password";
            }
            foreach ($required as $field) {
                if (!isset($r[$field]) || $r[$field] === "") {
                    throw new \RuntimeException("Missing required field: " . $field);
                }
            }
            if ($itemType === 18 && empty($r["master_item"]["key_"])) {
                throw new \RuntimeException("Master item reference required.");
            }
        }
        if (
            isset($r["status"]) &&
            (!is_scalar($r["status"]) || !in_array((string) $r["status"], ["0", "1"], true))
        ) {
            throw new \RuntimeException("Invalid status.");
        }
        foreach (
            [
                "hostid",
                "itemid",
                "interfaceid",
                "templateid",
                "master_itemid",
                "proxyid",
                "proxy_groupid",
                "groupid",
                "moduleid",
                "valuemapid"
            ]
            as $field
        ) {
            if (array_key_exists($field, $r)) {
                throw new \RuntimeException("Source IDs are forbidden: " . $field);
            }
        }
    }
    private static function walk(array $data, int $depth = 0): void
    {
        if ($depth > 24) {
            throw new \RuntimeException("Record nesting too deep.");
        }
        foreach ($data as $v) {
            if (is_string($v) && strlen($v) > 262144) {
                throw new \RuntimeException("Field too long.");
            }
            if (is_array($v)) {
                self::walk($v, $depth + 1);
            } elseif (!is_scalar($v) && $v !== null) {
                throw new \RuntimeException("Invalid field type.");
            }
        }
    }
    /** Stable topological order. Missing external masters are resolved against the target later. */
    public static function order(array $records): array
    {
        $map = [];
        foreach ($records as $i => $r) {
            if (!is_array($r) || !is_string($r["host"] ?? null) || !is_string($r["key_"] ?? null)) {
                throw new \RuntimeException("Invalid item identity.");
            }
            $key = $r["host"] . "\0" . $r["key_"];
            if (isset($map[$key])) {
                throw new \RuntimeException("Duplicate item key.");
            }
            $map[$key] = $i;
        }
        $state = [];
        $out = [];
        $visit = function ($i) use (&$visit, &$state, &$out, $records, $map) {
            if (($state[$i] ?? 0) === 1) {
                throw new \RuntimeException("Cyclic master item dependency.");
            }
            if (($state[$i] ?? 0) === 2) {
                return;
            }
            $state[$i] = 1;
            $r = $records[$i];
            $key = $r["host"] . "\0" . ($r["master_item"]["key_"] ?? "");
            if (isset($map[$key])) {
                $visit($map[$key]);
            }
            $state[$i] = 2;
            $out[$i] = $r;
        };
        foreach (array_keys($records) as $i) {
            $visit($i);
        }
        return $out;
    }
}
