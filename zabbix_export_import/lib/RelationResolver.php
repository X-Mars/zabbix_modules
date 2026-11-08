<?php
namespace Modules\ZabbixImportExport\Lib;
final class RelationResolver
{
    public static function one(
        string $service,
        string $field,
        string $value,
        bool $editable = false,
        array $extra = []
    ): array {
        $api = call_user_func(["API", $service]);
        $options = $extra + ["output" => "extend", "filter" => [$field => $value]];
        if ($editable && in_array($service, ["Host", "Template", "HostGroup", "Item"], true)) {
            $options["editable"] = true;
        }
        $rows = $api->get($options);
        if (count($rows) !== 1) {
            throw new \RuntimeException(
                "Object missing, inaccessible or ambiguous: " . $service . " / " . $value
            );
        }
        return $rows[0];
    }
    public static function host(string $name, bool $editable = true): array
    {
        return self::one("Host", "host", $name, $editable);
    }
    public static function interfaces(array $interfaces): array
    {
        $out = [];
        foreach ($interfaces as $i) {
            $r = array_intersect_key(
                $i,
                array_flip(["type", "main", "useip", "ip", "dns", "port", "details"])
            );
            if (!isset($r["type"], $r["main"], $r["useip"], $r["port"])) {
                throw new \RuntimeException("Incomplete interface.");
            }
            if (
                !in_array((string) $r["type"], ["1", "2", "3", "4"], true) ||
                !in_array((string) $r["main"], ["0", "1"], true) ||
                !in_array((string) $r["useip"], ["0", "1"], true) ||
                $r["port"] === ""
            ) {
                throw new \RuntimeException("Invalid interface.");
            }
            if (empty($r[$r["useip"] == 1 ? "ip" : "dns"])) {
                throw new \RuntimeException("Interface IP or DNS required.");
            }
            if (isset($r["details"])) {
                $r["details"] = array_intersect_key(
                    $r["details"],
                    array_flip([
                        "version",
                        "bulk",
                        "community",
                        "securityname",
                        "securitylevel",
                        "authpassphrase",
                        "privpassphrase",
                        "authprotocol",
                        "privprotocol",
                        "contextname",
                        "max_repetitions"
                    ])
                );
            }
            if (
                $r["type"] == 2 &&
                (empty($r["details"]["version"]) ||
                    ($r["details"]["version"] != 3 && empty($r["details"]["community"])))
            ) {
                throw new \RuntimeException("SNMP interface credentials required.");
            }
            $out[] = $r;
        }
        return $out;
    }
    public static function interfaceId(string $hostid, array $ref): string
    {
        $rows = \API::HostInterface()->get([
            "output" => "extend",
            "hostids" => [$hostid],
            "filter" => ["type" => $ref["type"]]
        ]);
        $exact = array_values(
            array_filter($rows, function ($r) use ($ref) {
                foreach (["type", "useip", "ip", "dns", "port", "main"] as $k) {
                    if (isset($ref[$k]) && (string) $r[$k] !== (string) $ref[$k]) {
                        return false;
                    }
                }
                return true;
            })
        );
        if (count($exact) === 1) {
            return $exact[0]["interfaceid"];
        }
        $main = array_values(
            array_filter($rows, function ($r) {
                return $r["main"] == 1;
            })
        );
        if (count($main) !== 1) {
            throw new \RuntimeException("Matching interface missing or ambiguous.");
        }
        return $main[0]["interfaceid"];
    }
}
