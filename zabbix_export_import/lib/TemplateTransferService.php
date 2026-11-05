<?php
namespace Modules\ZabbixImportExport\Lib;
/** Native configuration format preserves template entities and lets Zabbix validate version semantics. */
final class TemplateTransferService
{
    public static function groupApi()
    {
        return ZabbixVersion::atLeast("6.2") ? \API::TemplateGroup() : \API::HostGroup();
    }
    public static function listing(array $filter, array $groups): array
    {
        $options = [
            "output" => ["templateid", "host", "name"],
            "selectParentTemplates" => ["host", "name"],
            "selectItems" => "count",
            "selectTriggers" => "count",
            "sortfield" => "name",
            "limit" => 10001
        ];
        $options[ZabbixVersion::atLeast("6.2") ? "selectTemplateGroups" : "selectGroups"] = [
            "groupid",
            "name"
        ];
        if ($filter["host"] !== "") {
            $options["search"] = ["name" => $filter["host"]];
        }
        if ($filter["groups"]) {
            $ids = [];
            $names = [];
            foreach ($groups as $g) {
                if (in_array($g["groupid"], $filter["groups"])) {
                    $names[] = $g["name"];
                }
            }
            foreach ($groups as $g) {
                foreach ($names as $name) {
                    if ($g["name"] === $name || strpos($g["name"], $name . "/") === 0) {
                        $ids[] = $g["groupid"];
                    }
                }
            }
            if (!$ids) {
                return [];
            }
            $options["groupids"] = array_values(array_unique($ids));
        }
        $templates = \API::Template()->get($options);
        if ($templates === false) {
            throw new \RuntimeException("API rejected operation.");
        }
        if (count($templates) > 10000) {
            throw new \RuntimeException("More than 10000 records; narrow the filter.");
        }
        $out = [];
        foreach ($templates as $t) {
            $out[] = [
                "template" => $t["host"],
                "name" => $t["name"],
                "groups" => $t["templategroups"] ?? ($t["groups"] ?? []),
                "parents" => $t["parentTemplates"] ?? [],
                "items_count" => (int) ($t["items"] ?? 0),
                "triggers_count" => (int) ($t["triggers"] ?? 0),
                "_templateid" => $t["templateid"]
            ];
        }
        return $out;
    }
    public static function export(array $rows): array
    {
        $out = [];
        $size = 0;
        foreach ($rows as $row) {
            $text = \API::Configuration()->export([
                "format" => "json",
                "options" => ["templates" => [$row["_templateid"]]]
            ]);
            if (!is_string($text)) {
                throw new \RuntimeException("API rejected operation.");
            }
            $size += strlen($text);
            if ($size > Serializer::LIMIT) {
                throw new \RuntimeException("Export exceeds 10 MiB; narrow the filter.");
            }
            $data = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
            $record = [
                "template" => $row["template"],
                "name" => $row["name"],
                "configuration" => $data
            ];
            $removed = [];
            $record = Security::redact($record, $removed);
            if ($removed) {
                $record["_redacted"] = $removed;
            }
            $out[] = $record;
        }
        return $out;
    }
    public static function fromNative(array $data): array
    {
        $root = $data["zabbix_export"] ?? null;
        if (
            !is_array($root) ||
            !is_array($root["templates"] ?? null) ||
            !$root["templates"] ||
            count($root["templates"]) > 10000
        ) {
            throw new \RuntimeException("Invalid template configuration.");
        }
        $out = [];
        foreach ($root["templates"] as $template) {
            if (!is_array($template) || !is_string($template["template"] ?? null)) {
                throw new \RuntimeException("Invalid template configuration.");
            }
            $part = $root;
            $part["templates"] = [$template];
            // Root-level graphs/triggers are assigned to their first referenced template, without duplication.
            foreach (["graphs", "triggers"] as $field) {
                if (isset($part[$field])) {
                    $part[$field] = array_values(
                        array_filter($part[$field], function ($object) use ($template, $root) {
                            $text = json_encode(
                                $object,
                                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                            );
                            foreach ($root["templates"] as $candidate) {
                                if (
                                    strpos(
                                        $text,
                                        substr(
                                            json_encode(
                                                "/" . $candidate["template"] . "/",
                                                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                                            ),
                                            1,
                                            -1
                                        )
                                    ) !== false ||
                                    strpos(
                                        $text,
                                        '"host":' .
                                            json_encode(
                                                $candidate["template"],
                                                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                                            )
                                    ) !== false
                                ) {
                                    return $candidate["template"] === $template["template"];
                                }
                            }
                            throw new \RuntimeException(
                                "Cannot assign shared template graphs or triggers."
                            );
                        })
                    );
                }
            }
            $out[] = [
                "template" => $template["template"],
                "name" => $template["name"] ?? $template["template"],
                "configuration" => ["zabbix_export" => $part]
            ];
        }
        return $out;
    }
    public static function validate(array $r): void
    {
        if (
            array_diff(array_keys($r), ["template", "name", "configuration", "_redacted", "_debug"])
        ) {
            throw new \RuntimeException("Invalid template configuration.");
        }
        if (
            !is_string($r["template"] ?? null) ||
            $r["template"] === "" ||
            !is_array($r["configuration"] ?? null)
        ) {
            throw new \RuntimeException("Invalid template configuration.");
        }
        if (!empty($r["_redacted"])) {
            throw new \RuntimeException(
                "Supply redacted credentials securely and remove _redacted before importing."
            );
        }
        if (array_keys($r["configuration"]) !== ["zabbix_export"]) {
            throw new \RuntimeException("Template import cannot contain other object types.");
        }
        $root = $r["configuration"]["zabbix_export"];
        if (
            !is_array($root) ||
            array_diff(array_keys($root), [
                "version",
                "date",
                "template_groups",
                "host_groups",
                "groups",
                "templates",
                "triggers",
                "graphs"
            ]) ||
            !is_array($root["templates"] ?? null) ||
            count($root["templates"]) !== 1 ||
            ($root["templates"][0]["template"] ?? null) !== $r["template"]
        ) {
            throw new \RuntimeException("Template import cannot contain other object types.");
        }
        self::bounded($r);
    }
    private static function bounded(array $data, int $depth = 0): void
    {
        if ($depth > 28) {
            throw new \RuntimeException("Record nesting too deep.");
        }
        foreach ($data as $key => $value) {
            if (
                in_array(
                    (string) $key,
                    [
                        "hostid",
                        "templateid",
                        "itemid",
                        "triggerid",
                        "graphid",
                        "groupid",
                        "interfaceid",
                        "master_itemid",
                        "valuemapid",
                        "moduleid"
                    ],
                    true
                )
            ) {
                throw new \RuntimeException("Source IDs are forbidden: " . $key);
            }
            if (is_array($value)) {
                self::bounded($value, $depth + 1);
            } elseif (is_string($value) && strlen($value) > 262144) {
                throw new \RuntimeException("Field too long.");
            } elseif (!is_scalar($value) && $value !== null) {
                throw new \RuntimeException("Invalid template configuration.");
            }
        }
    }
    public static function assertSafe(array $data, array $options): void
    {
        $danger = false;
        $types = [
            "EXTERNAL" => 10,
            "ODBC" => 11,
            "SSH" => 13,
            "TELNET" => 14,
            "HTTP_AGENT" => 19,
            "SCRIPT" => 21,
            "ITEM_TYPE_SCRIPT" => 21,
            "BROWSER" => 22,
            "ITEM_TYPE_BROWSER" => 22
        ];
        if (isset($data["key"]) || isset($data["key_"])) {
            $item = [
                "type" => $types[$data["type"] ?? ""] ?? ($data["type"] ?? -1),
                "key_" => $data["key"] ?? $data["key_"]
            ];
            if (Security::executionRisks($item)) {
                $danger = true;
            }
        }
        if (
            in_array((string) ($data["type"] ?? ""), ["JAVASCRIPT", "21"], true) &&
            (isset($data["parameters"]) || isset($data["params"]))
        ) {
            $danger = true;
        }
        if (!empty($data["httptests"])) {
            $danger = true;
        }
        if ($danger && (empty($options["allow_executable"]) || !Security::isSuperAdmin())) {
            throw new \RuntimeException(
                "Executable or active monitoring configuration requires explicit Super admin approval."
            );
        }
        foreach ($data as $value) {
            if (is_array($value)) {
                self::assertSafe($value, $options);
            }
        }
    }
    public static function rules(string $operation, array $options): array
    {
        $update = $operation === "update";
        $createGroup = ($options["groups"] ?? "stop") === "create";
        if ($createGroup && !Security::isSuperAdmin()) {
            throw new \RuntimeException("Only Super admin can create missing template groups.");
        }
        $rules = [
            "templates" => ["createMissing" => !$update, "updateExisting" => $update],
            "templateLinkage" => ["createMissing" => true, "deleteMissing" => false]
        ];
        foreach (
            [
                "items",
                "triggers",
                "graphs",
                "discoveryRules",
                "httptests",
                "valueMaps",
                "templateDashboards"
            ]
            as $field
        ) {
            $rules[$field] = [
                "createMissing" => true,
                "updateExisting" => $update,
                "deleteMissing" => false
            ];
        }
        $rules[ZabbixVersion::atLeast("6.2") ? "template_groups" : "groups"] = [
            "createMissing" => $createGroup,
            "updateExisting" => false
        ];
        if (ZabbixVersion::atLeast("6.2")) {
            $rules["host_groups"] = ["createMissing" => $createGroup, "updateExisting" => false];
        }
        return $rules;
    }
    public static function plan(array $r, array $options): array
    {
        self::validate($r);
        self::assertSafe($r["configuration"], $options);
        $root = $r["configuration"]["zabbix_export"];
        $template = $root["templates"][0];
        $existing = \API::Template()->get([
            "output" => ["templateid", "host", "name", "uuid"],
            "filter" => ["host" => $r["template"]]
        ]);
        if ($existing === false) {
            throw new \RuntimeException("API rejected operation.");
        }
        if ($existing) {
            $writable = \API::Template()->get([
                "output" => ["templateid"],
                "templateids" => [$existing[0]["templateid"]],
                "editable" => true
            ]);
            if (!$writable) {
                throw new \RuntimeException("Template is not writable.");
            }
            if (!empty($template["uuid"]) && $template["uuid"] !== $existing[0]["uuid"]) {
                throw new \RuntimeException("Template UUID conflicts with its technical name.");
            }
        }
        if (!empty($template["uuid"])) {
            $uuidMatch = \API::Template()->get([
                "output" => ["host"],
                "filter" => ["uuid" => $template["uuid"]]
            ]);
            foreach ($uuidMatch ?: [] as $match) {
                if ($match["host"] !== $r["template"]) {
                    throw new \RuntimeException("Template UUID conflicts with its technical name.");
                }
            }
        }
        $operation = TransferService::operation((bool) $existing, $options);
        if ($operation === "skip") {
            return ["operation" => "skip", "payload" => [], "before" => []];
        }
        $groups = $root["template_groups"] ?? ($root["groups"] ?? []);
        foreach ($groups as $group) {
            if (!is_string($group["name"] ?? null)) {
                throw new \RuntimeException("Invalid template configuration.");
            }
            $found = self::groupApi()->get([
                "output" => ["groupid"],
                "filter" => ["name" => $group["name"]]
            ]);
            if (!$found && ($options["groups"] ?? "stop") !== "create") {
                throw new \RuntimeException("Missing template group: " . $group["name"]);
            }
        }
        $payload = [
            "format" => "json",
            "source" => json_encode(
                $r["configuration"],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ),
            "rules" => self::rules($operation, $options)
        ];
        $diff = \API::Configuration()->importcompare($payload);
        if ($diff === false) {
            self::apiError();
        }
        return ["operation" => $operation, "payload" => $payload, "before" => [], "diff" => $diff];
    }
    public static function execute(array $r, array $options): array
    {
        $plan = self::plan($r, $options);
        if ($plan["operation"] === "skip") {
            return $plan;
        }
        if (!\API::Configuration()->import($plan["payload"])) {
            self::apiError();
        }
        return $plan;
    }
    private static function apiError(): void
    {
        $messages = get_and_clear_messages();
        throw new \RuntimeException(
            $messages
                ? implode("; ", array_column($messages, "message"))
                : "API rejected operation."
        );
    }
    public static function order(array $records): array
    {
        $map = [];
        foreach ($records as $i => $r) {
            self::validate($r);
            $map[$r["template"]] = $i;
        }
        $out = [];
        $state = [];
        $visit = function ($i) use (&$visit, &$out, &$state, $records, $map) {
            if (($state[$i] ?? 0) === 1) {
                throw new \RuntimeException("Cyclic template dependency.");
            }
            if (($state[$i] ?? 0) === 2) {
                return;
            }
            $state[$i] = 1;
            foreach (
                $records[$i]["configuration"]["zabbix_export"]["templates"][0]["templates"] ?? []
                as $parent
            ) {
                if (isset($map[$parent["name"] ?? ""])) {
                    $visit($map[$parent["name"]]);
                }
            }
            $state[$i] = 2;
            $out[] = $records[$i];
        };
        foreach (array_keys($records) as $i) {
            $visit($i);
        }
        return $out;
    }
}
