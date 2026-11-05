<?php
namespace Modules\ZabbixImportExport\Lib;
final class TransferService
{
    public static function identity(string $type, array $record): string
    {
        return (string) ($type === "templates" ? $record["template"] ?? "" : $record["host"] ?? "");
    }
    public static function operation(bool $exists, array $options): string
    {
        $strategy = $options["strategy"] ?? "create";
        if (!$exists) {
            return $strategy === "update" ? "skip" : "create";
        }
        if ($strategy === "update") {
            return "update";
        }
        if ($strategy === "error") {
            throw new \RuntimeException("Duplicate object.");
        }
        return "skip";
    }
    public static function plan(
        string $type,
        array $record,
        array $options,
        array $pending = []
    ): array {
        if ($type === "hosts") {
            return HostTransferService::plan($record, $options);
        }
        if ($type === "templates") {
            return TemplateTransferService::plan($record, $options);
        }
        return ItemTransferService::plan($record, $options, $pending);
    }
    public static function execute(string $type, array $record, array $options): array
    {
        if ($type === "templates") {
            return TemplateTransferService::execute($record, $options);
        }
        $plan = self::plan($type, $record, $options);
        if ($plan["operation"] === "skip") {
            return $plan;
        }
        $p = $plan["payload"];
        if ($type === "hosts") {
            foreach ($plan["missing_groups"] as $name) {
                $group = \API::HostGroup()->create(["name" => $name]);
                if (!$group) {
                    throw new \RuntimeException("Host group creation failed.");
                }
                $p["groups"][] = ["groupid" => $group["groupids"][0]];
            }
        }
        if (isset($p["_pending_master"])) {
            throw new \RuntimeException("Master item not created.");
        }
        $api = $type === "hosts" ? \API::Host() : \API::Item();
        if (!$api->{$plan["operation"]}($p)) {
            $messages = get_and_clear_messages();
            throw new \RuntimeException(
                $messages
                    ? implode("; ", array_column($messages, "message"))
                    : "API rejected operation."
            );
        }
        $plan["children"] = [];
        if ($type === "hosts") {
            $target = RelationResolver::host(
                $options["host_map"][$record["host"]] ?? $record["host"]
            );
            if (!empty($options["restore_items"])) {
                $local = array_values(
                    array_filter($record["item_references"] ?? [], function ($i) {
                        return ($i["origin"] ?? "") === "local";
                    })
                );
                $itemOptions = $options;
                $itemOptions["strategy"] = $options["item_strategy"] ?? "create";
                foreach (ImportValidator::order($local) as $item) {
                    try {
                        $child = self::execute("items", $item, $itemOptions);
                        $plan["children"][] = [
                            "key" => $item["key_"],
                            "operation" => $child["operation"]
                        ];
                    } catch (\Throwable $e) {
                        $plan["children"][] = [
                            "key" => $item["key_"],
                            "operation" => "error",
                            "message" => $e->getMessage()
                        ];
                    }
                }
            }
            $plan["verified_host"] = $target["host"];
        }
        return $plan;
    }
}
