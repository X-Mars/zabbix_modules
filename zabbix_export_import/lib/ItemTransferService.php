<?php
namespace Modules\ZabbixImportExport\Lib;
final class ItemTransferService
{
    public static function fields(): array
    {
        return explode(
            " ",
            "name key_ type value_type status delay history trends units description params parameters snmp_oid url query_fields posts status_codes follow_redirects post_type http_proxy headers retrieve_mode request_method output_format allow_traps jmx_endpoint timeout ipmi_sensor logtimefmt inventory_link authtype username password publickey privatekey ssl_cert_file ssl_key_file ssl_key_password verify_peer verify_host trapper_hosts"
        );
    }
    public static function export(array $hosts, array $filter = []): array
    {
        $result = [];
        $hostmap = array_column($hosts, null, "hostid");
        foreach (array_chunk(array_keys($hostmap), 100) as $ids) {
            $rows = \API::Item()->get([
                "output" => "extend",
                "hostids" => $ids,
                "selectTags" => "extend",
                "selectPreprocessing" => "extend",
                "selectDiscoveryRule" => ["name", "key_"],
                "limit" => 10001,
                "tags" => $filter["item_tags"] ?? [],
                "evaltype" => $filter["item_evaltype"] ?? 0
            ]);
            if (count($rows) > 10000) {
                throw new \RuntimeException(
                    "More than 10000 items in batch; narrow the host filter."
                );
            }
            $ifmap = array_column(
                \API::HostInterface()->get(["output" => "extend", "hostids" => $ids]),
                null,
                "interfaceid"
            );
            $parentIds = [];
            foreach ($rows as $r) {
                if ($r["templateid"] != 0) {
                    $parentIds[] = $r["templateid"];
                }
            }
            $parents = $parentIds
                ? \API::Item()->get([
                    "output" => ["itemid", "hostid", "key_", "templateid"],
                    "itemids" => array_values(array_unique($parentIds)),
                    "selectHosts" => ["host", "name"]
                ])
                : [];
            $parentmap = array_column($parents, null, "itemid");
            $itemmap = array_column($rows, null, "itemid");
            $missingMasters = [];
            foreach ($rows as $r) {
                if ($r["master_itemid"] != 0 && !isset($itemmap[$r["master_itemid"]])) {
                    $missingMasters[] = $r["master_itemid"];
                }
            }
            if ($missingMasters) {
                foreach (
                    \API::Item()->get([
                        "output" => ["itemid", "key_"],
                        "itemids" => array_values(array_unique($missingMasters))
                    ])
                    as $master
                ) {
                    $itemmap[$master["itemid"]] = $master;
                }
            }
            $vmids = array_values(array_unique(array_filter(array_column($rows, "valuemapid"))));
            $vmmap = $vmids
                ? array_column(
                    \API::ValueMap()->get([
                        "output" => ["valuemapid", "name"],
                        "valuemapids" => $vmids
                    ]),
                    null,
                    "valuemapid"
                )
                : [];
            foreach ($rows as $r) {
                $origin =
                    $r["flags"] == 4 ? "discovery" : ($r["templateid"] != 0 ? "template" : "local");
                $record = array_intersect_key($r, array_flip(self::fields()));
                $record += [
                    "host" => $hostmap[$r["hostid"]]["host"],
                    "origin" => $origin,
                    "tags" => $r["tags"],
                    "preprocessing" => $r["preprocessing"]
                ];
                $record["tags"] = array_map(function ($t) {
                    return array_intersect_key($t, array_flip(["tag", "value"]));
                }, $record["tags"]);
                if ($r["interfaceid"] != 0) {
                    $record["interface"] = array_intersect_key(
                        $ifmap[$r["interfaceid"]] ?? [],
                        array_flip(["type", "main", "useip", "ip", "dns", "port"])
                    );
                }
                if ($r["valuemapid"] != 0) {
                    if (!isset($vmmap[$r["valuemapid"]])) {
                        throw new \RuntimeException("Value map inaccessible.");
                    }
                    $record["valuemap"] = ["name" => $vmmap[$r["valuemapid"]]["name"]];
                }
                if ($r["master_itemid"] != 0) {
                    if (!isset($itemmap[$r["master_itemid"]])) {
                        throw new \RuntimeException("Master item inaccessible.");
                    }
                    $record["master_item"] = ["key_" => $itemmap[$r["master_itemid"]]["key_"]];
                }
                if ($origin === "template") {
                    $parent = $parentmap[$r["templateid"]] ?? [];
                    $template = $parent["hosts"][0] ?? [];
                    $record["template"] = [
                        "host" => $template["host"] ?? "",
                        "name" => $template["name"] ?? "",
                        "key_" => $parent["key_"] ?? $r["key_"]
                    ];
                }
                if ($origin === "discovery") {
                    $record["discovery"] = $r["discoveryRule"] ?? [];
                }
                $removed = [];
                $record = Security::redact($record, $removed);
                foreach (["params", "posts"] as $key) {
                    if (
                        !empty($record[$key]) &&
                        preg_match(
                            "/password|token|secret|authorization|BEGIN .*PRIVATE KEY/i",
                            $record[$key]
                        )
                    ) {
                        unset($record[$key]);
                        $removed[] = $key;
                    }
                }
                if ($removed) {
                    $record["_redacted"] = $removed;
                }
                if (self::matches($record, $r, $filter)) {
                    $result[] = $record;
                }
                if (count($result) > 10000) {
                    throw new \RuntimeException("More than 10000 records; narrow the filter.");
                }
            }
        }
        return $result;
    }
    public static function matches(array $r, array $raw, array $f): bool
    {
        foreach (
            ["name" => "item_name", "key_" => "item_key", "delay" => "item_delay"]
            as $key => $filter
        ) {
            if (!empty($f[$filter]) && stripos($r[$key] ?? "", $f[$filter]) === false) {
                return false;
            }
        }
        foreach (
            [
                "type" => "item_type",
                "status" => "item_status",
                "value_type" => "item_value_type",
                "state" => "item_state"
            ]
            as $key => $filter
        ) {
            if (
                isset($f[$filter]) &&
                $f[$filter] !== -1 &&
                (int) ($raw[$key] ?? -1) !== $f[$filter]
            ) {
                return false;
            }
        }
        if (
            isset($f["item_origin"]) &&
            $f["item_origin"] !== -1 &&
            $r["origin"] !== [0 => "local", 1 => "template", 2 => "discovery"][$f["item_origin"]]
        ) {
            return false;
        }
        return true;
    }
    public static function plan(array $r, array $options, array $pending = []): array
    {
        ImportValidator::record("items", $r);
        Security::assertExecutableApproval($r, $options);
        $host = RelationResolver::host($options["host_map"][$r["host"]] ?? $r["host"]);
        $existing = \API::Item()->get([
            "output" => "extend",
            "hostids" => [$host["hostid"]],
            "filter" => ["key_" => $r["key_"]],
            "editable" => true,
            "selectPreprocessing" => "extend",
            "selectTags" => "extend"
        ]);
        if ($existing) {
            Security::assertExecutableApproval(array_replace($existing[0], $r), $options);
        }
        if ($existing && ($existing[0]["templateid"] != 0 || $existing[0]["flags"] != 0)) {
            throw new \RuntimeException("Target item is inherited or discovered.");
        }
        $op = TransferService::operation((bool) $existing, $options);
        if ($op === "skip") {
            return ["operation" => "skip", "payload" => [], "before" => $existing[0] ?? []];
        }
        $payload = array_intersect_key($r, array_flip(self::fields()));
        foreach (["tags", "preprocessing"] as $k) {
            if (isset($r[$k])) {
                $payload[$k] = $r[$k];
            }
        }
        if (isset($payload["tags"])) {
            $payload["tags"] = array_map(function ($t) {
                return array_intersect_key($t, array_flip(["tag", "value"]));
            }, $payload["tags"]);
        }
        if (
            !ZabbixVersion::modern() &&
            isset($payload["timeout"]) &&
            !in_array((int) $r["type"], [19, 21], true)
        ) {
            unset($payload["timeout"]);
        }
        if (isset($r["interface"])) {
            $payload["interfaceid"] = RelationResolver::interfaceId(
                $host["hostid"],
                $r["interface"]
            );
        } elseif (in_array((int) $r["type"], [0, 1, 4, 6, 12, 16, 20], true)) {
            throw new \RuntimeException("Interface reference required.");
        }
        if (isset($r["valuemap"])) {
            $maps = \API::ValueMap()->get([
                "output" => ["valuemapid"],
                "hostids" => [$host["hostid"]],
                "filter" => ["name" => $r["valuemap"]["name"]]
            ]);
            if (count($maps) !== 1) {
                throw new \RuntimeException("Value map missing or ambiguous.");
            }
            $payload["valuemapid"] = $maps[0]["valuemapid"];
        }
        if (isset($r["master_item"])) {
            $masters = \API::Item()->get([
                "output" => ["itemid"],
                "hostids" => [$host["hostid"]],
                "filter" => ["key_" => $r["master_item"]["key_"]]
            ]);
            if (count($masters) === 1) {
                $payload["master_itemid"] = $masters[0]["itemid"];
            } elseif (!isset($pending[$r["host"] . "\0" . $r["master_item"]["key_"]])) {
                throw new \RuntimeException("Master item missing.");
            } else {
                $payload["_pending_master"] = $r["master_item"]["key_"];
            }
        }
        if ($existing) {
            $payload["itemid"] = $existing[0]["itemid"];
        } else {
            $payload["hostid"] = $host["hostid"];
        }
        return ["operation" => $op, "payload" => $payload, "before" => $existing[0] ?? []];
    }
}
