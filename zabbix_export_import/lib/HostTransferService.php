<?php
namespace Modules\ZabbixImportExport\Lib;
final class HostTransferService
{
    public static function export(array $ids): array
    {
        $out = [];
        foreach (array_chunk($ids, 100) as $batch) {
            $fields = [
                "hostid",
                "host",
                "name",
                "description",
                "status",
                "inventory_mode",
                ZabbixVersion::modern() ? "monitored_by" : "proxy_hostid"
            ];
            if (ZabbixVersion::modern()) {
                $fields = array_merge($fields, ["proxyid", "proxy_groupid"]);
            }
            $hosts = \API::Host()->get([
                "output" => $fields,
                "hostids" => $batch,
                ZabbixVersion::groups() => ["name"],
                "selectParentTemplates" => ["host", "name"],
                "selectInterfaces" => "extend",
                "selectTags" => "extend",
                "selectInventory" => "extend"
            ]);
            $items = ItemTransferService::export($hosts);
            $itemmap = [];
            foreach ($items as $item) {
                $itemmap[$item["host"]][] = $item;
            }
            $proxyids = [];
            $pgids = [];
            foreach ($hosts as $h) {
                $proxyids[] = $h["proxyid"] ?? ($h["proxy_hostid"] ?? 0);
                $pgids[] = $h["proxy_groupid"] ?? 0;
            }
            $proxyids = array_values(array_unique(array_filter($proxyids)));
            $pgids = array_values(array_unique(array_filter($pgids)));
            $pmap = $proxyids
                ? array_column(
                    \API::Proxy()->get(["output" => "extend", "proxyids" => $proxyids]),
                    null,
                    "proxyid"
                )
                : [];
            $pgmap = $pgids
                ? array_column(
                    \API::ProxyGroup()->get([
                        "output" => ["proxy_groupid", "name"],
                        "proxy_groupids" => $pgids
                    ]),
                    null,
                    "proxy_groupid"
                )
                : [];
            foreach ($hosts as $h) {
                $r = array_intersect_key(
                    $h,
                    array_flip([
                        "host",
                        "name",
                        "description",
                        "status",
                        "inventory_mode",
                        "inventory",
                        "tags"
                    ])
                );
                $r["host_groups"] = array_map(function ($g) {
                    return ["name" => $g["name"]];
                }, $h["hostgroups"] ?? $h["groups"]);
                $r["templates"] = array_map(function ($t) {
                    return ["host" => $t["host"], "name" => $t["name"]];
                }, $h["parentTemplates"]);
                $r["interfaces"] = RelationResolver::interfaces($h["interfaces"]);
                $r["monitored_by"] =
                    (int) ($h["monitored_by"] ?? (($h["proxy_hostid"] ?? 0) != 0 ? 1 : 0));
                if ($r["monitored_by"] === 1) {
                    $p = $pmap[$h["proxyid"] ?? $h["proxy_hostid"]] ?? null;
                    if (!$p) {
                        throw new \RuntimeException("Proxy inaccessible.");
                    }
                    $r["proxy_name"] = $p["name"] ?? $p["host"];
                }
                if ($r["monitored_by"] === 2) {
                    $p = $pgmap[$h["proxy_groupid"]] ?? null;
                    if (!$p) {
                        throw new \RuntimeException("Proxy group inaccessible.");
                    }
                    $r["proxy_group_name"] = $p["name"];
                }
                $r["tags"] = array_map(function ($t) {
                    return array_intersect_key($t, array_flip(["tag", "value"]));
                }, $r["tags"]);
                $r["item_references"] = $itemmap[$h["host"]] ?? [];
                $removed = [];
                $r = Security::redact($r, $removed);
                if ($removed) {
                    $r["_redacted"] = $removed;
                }
                $out[] = $r;
            }
        }
        return $out;
    }
    public static function plan(array $r, array $options): array
    {
        ImportValidator::record("hosts", $r);
        if (!empty($options["restore_items"])) {
            $local = array_values(
                array_filter($r["item_references"] ?? [], function ($i) {
                    return ($i["origin"] ?? "") === "local";
                })
            );
            foreach (ImportValidator::order($local) as $item) {
                if (($item["host"] ?? null) !== $r["host"]) {
                    throw new \RuntimeException(
                        "Local item host must match its enclosing host record."
                    );
                }
                ImportValidator::record("items", $item);
                Security::assertExecutableApproval($item, $options);
            }
        }
        $r["host"] = $options["host_map"][$r["host"]] ?? $r["host"];
        $existing = \API::Host()->get([
            "output" => "extend",
            "filter" => ["host" => $r["host"]],
            "editable" => true,
            ZabbixVersion::groups() => ["groupid"],
            "selectParentTemplates" => ["templateid"],
            "selectTags" => "extend",
            "selectInventory" => "extend",
            "selectInterfaces" => "extend"
        ]);
        $visible = \API::Host()->get(["output" => ["hostid"], "filter" => ["host" => $r["host"]]]);
        if ($visible && !$existing) {
            throw new \RuntimeException("Host is not writable.");
        }
        $op = TransferService::operation((bool) $existing, $options);
        if ($op === "skip") {
            return ["operation" => "skip", "payload" => [], "before" => $existing[0] ?? []];
        }
        if (!empty($r["name"])) {
            $sameName = \API::Host()->get([
                "output" => ["hostid"],
                "filter" => ["name" => $r["name"]]
            ]);
            foreach ($sameName as $same) {
                if (!$existing || $same["hostid"] !== $existing[0]["hostid"]) {
                    throw new \RuntimeException(
                        "Visible name conflicts with another host. Change name in the import file."
                    );
                }
            }
        }
        $p = array_intersect_key(
            $r,
            array_flip(["host", "name", "description", "status", "inventory_mode"])
        );
        $p["groups"] = [];
        $missing = [];
        foreach ($r["host_groups"] as $g) {
            $rows = \API::HostGroup()->get([
                "output" => ["groupid"],
                "filter" => ["name" => $g["name"]]
            ]);
            if ($rows) {
                $p["groups"][] = ["groupid" => $rows[0]["groupid"]];
            } elseif (
                ($options["groups"] ?? "stop") === "create" &&
                \CWebUser::$data["type"] >= USER_TYPE_SUPER_ADMIN
            ) {
                $missing[] = $g["name"];
            } elseif (($options["groups"] ?? "stop") !== "skip") {
                throw new \RuntimeException("Missing host group: " . $g["name"]);
            }
        }
        if (!$p["groups"] && !$missing) {
            throw new \RuntimeException("Host group required.");
        }
        $p["templates"] = [];
        foreach ($r["templates"] ?? [] as $t) {
            $name = $options["template_map"][$t["host"]] ?? $t["host"];
            $rows = \API::Template()->get([
                "output" => ["templateid"],
                "filter" => ["host" => $name]
            ]);
            if ($rows) {
                $p["templates"][] = ["templateid" => $rows[0]["templateid"]];
            } elseif (($options["templates"] ?? "stop") !== "skip") {
                throw new \RuntimeException("Missing template: " . $name);
            }
        }
        $mode = (int) ($r["monitored_by"] ?? 0);
        if ($mode === 2 && !ZabbixVersion::modern()) {
            throw new \RuntimeException("Proxy groups require Zabbix 7.0.");
        }
        if (!in_array($mode, [0, 1, 2], true)) {
            throw new \RuntimeException("Invalid monitoring mode.");
        }
        if (ZabbixVersion::modern()) {
            $p["monitored_by"] = $mode;
            if ($mode === 1) {
                $p["proxyid"] = RelationResolver::one("Proxy", "name", $r["proxy_name"] ?? "")[
                    "proxyid"
                ];
            }
            if ($mode === 2) {
                $p["proxy_groupid"] = RelationResolver::one(
                    "ProxyGroup",
                    "name",
                    $r["proxy_group_name"] ?? ""
                )["proxy_groupid"];
            }
        } else {
            $p["proxy_hostid"] =
                $mode === 1
                    ? RelationResolver::one("Proxy", "host", $r["proxy_name"] ?? "")["proxyid"]
                    : "0";
        }
        if (!$existing || !empty($options["interfaces"])) {
            $p["interfaces"] = RelationResolver::interfaces($r["interfaces"] ?? []);
        }
        foreach (["tags", "inventory"] as $field) {
            if (isset($r[$field]) && (!$existing || !empty($options[$field]))) {
                $p[$field] = $r[$field];
            }
        }
        if (isset($p["tags"])) {
            $p["tags"] = array_map(function ($t) {
                return array_intersect_key($t, array_flip(["tag", "value"]));
            }, $p["tags"]);
        }
        if ($existing) {
            $h = $existing[0];
            $p["hostid"] = $h["hostid"];
            $p["groups"] = array_values(
                array_unique(
                    array_merge($p["groups"], $h["hostgroups"] ?? $h["groups"]),
                    SORT_REGULAR
                )
            );
            if (empty($options["templates_replace"])) {
                $p["templates"] = array_values(
                    array_unique(array_merge($p["templates"], $h["parentTemplates"]), SORT_REGULAR)
                );
            }
            if (empty($options["inventory"])) {
                unset($p["inventory_mode"]);
            }
            if (isset($p["interfaces"])) {
                foreach ($p["interfaces"] as &$interface) {
                    foreach ($h["interfaces"] as $target) {
                        if (
                            (string) $interface["type"] === (string) $target["type"] &&
                            (string) $interface["main"] === (string) $target["main"]
                        ) {
                            $interface["interfaceid"] = $target["interfaceid"];
                            break;
                        }
                    }
                }
                unset($interface);
            }
        }
        return [
            "operation" => $op,
            "payload" => $p,
            "missing_groups" => $missing,
            "before" => $existing[0] ?? []
        ];
    }
}
