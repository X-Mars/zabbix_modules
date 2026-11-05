<?php
namespace Modules\ZabbixImportExport\Actions;
require_once dirname(__DIR__) . "/lib/bootstrap.php";
use Modules\ZabbixImportExport\Lib\{
    LanguageManager as L,
    HostFilter,
    HostTransferService,
    ItemTransferService,
    TemplateTransferService,
    Serializer,
    ImportValidator,
    TransferService,
    ImportStore,
    ZabbixVersion,
    Security,
    UploadValidator
};
class Transfer extends \CController
{
    protected function init(): void
    {
        if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "GET") {
            $method = ZabbixVersion::getDisableCsrfMethod($this);
            $this->{$method}();
        }
    }
    protected function checkInput(): bool
    {
        if (!in_array($_SERVER["REQUEST_METHOD"] ?? "GET", ["GET", "POST"], true)) {
            $this->setResponse(new \CControllerResponseFatal());
            return false;
        }
        return true;
    }
    protected function checkPermissions(): bool
    {
        if ($this->getUserType() < USER_TYPE_ZABBIX_USER) {
            return false;
        }
        if (
            ($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST" &&
            $this->getUserType() < USER_TYPE_ZABBIX_ADMIN
        ) {
            return false;
        }
        return in_array(
            $this->getAction(),
            ["importexport.hosts", "importexport.items", "importexport.templates"],
            true
        );
    }
    private function options(array $p): array
    {
        $o = [];
        foreach (
            [
                "strategy" => ["create", "update", "skip", "error"],
                "item_strategy" => ["create", "update", "skip", "error"],
                "groups" => ["stop", "skip", "create"],
                "templates" => ["stop", "skip"]
            ]
            as $key => $values
        ) {
            $o[$key] = $p[$key] ?? $values[0];
            if (!is_string($o[$key]) || !in_array($o[$key], $values, true)) {
                throw new \RuntimeException("Invalid import strategy.");
            }
        }
        foreach (
            [
                "interfaces",
                "tags",
                "inventory",
                "templates_replace",
                "restore_items",
                "allow_executable"
            ]
            as $key
        ) {
            $o[$key] = isset($p[$key]) && $p[$key] === "1";
        }
        if ($o["allow_executable"] && !Security::isSuperAdmin()) {
            throw new \RuntimeException(
                "Only Super admin can approve executable monitoring configuration."
            );
        }
        foreach (["host_map", "template_map"] as $key) {
            $text = $p[$key] ?? "{}";
            if (!is_string($text) || strlen($text) > 65536) {
                throw new \RuntimeException("Invalid mapping.");
            }
            $o[$key] = json_decode($text, true, 4, JSON_THROW_ON_ERROR);
            if (!is_array($o[$key])) {
                throw new \RuntimeException("Invalid mapping.");
            }
            foreach ($o[$key] as $source => $target) {
                if (!is_string($target) || $target === "") {
                    throw new \RuntimeException("Invalid mapping.");
                }
            }
        }
        return $o;
    }
    private function download(string $type, array $records, string $format): void
    {
        if (!in_array($format, ["json", "csv"], true)) {
            throw new \RuntimeException("Unsupported file extension.");
        }
        $text = Serializer::encode($type, $records, $format, ZabbixVersion::get());
        if (strlen($text) > Serializer::LIMIT) {
            throw new \RuntimeException("Export exceeds 10 MiB; narrow the filter.");
        }
        $timezone = \CWebUser::$data["timezone"] ?? "default";
        if ($timezone === "" || $timezone === "default") {
            $timezone = \CSettingsHelper::get("default_timezone");
        }
        if ($timezone === "" || $timezone === "system") {
            $timezone = date_default_timezone_get();
        }
        $stamp = (new \DateTimeImmutable("now", new \DateTimeZone($timezone)))->format("Ymd-His");
        header(
            "Content-Type: " .
                ($format === "csv" ? "text/csv" : "application/json") .
                "; charset=UTF-8"
        );
        header(
            'Content-Disposition: attachment; filename="' .
                $type .
                "-" .
                $stamp .
                "." .
                $format .
                '"'
        );
        header("Cache-Control: no-store");
        header("X-Content-Type-Options: nosniff");
        echo $text;
        exit();
    }
    protected function doAction(): void
    {
        $type = substr($this->getAction(), 13);
        $titles = [
            "hosts" => "Host transfer",
            "items" => "Item transfer",
            "templates" => "Template transfer"
        ];
        $title = L::t($titles[$type]);
        $rows = [];
        $total = 0;
        $error = "";
        $preview = [];
        $token = "";
        $selections = [];
        $group_choices = [];
        $filter = [];
        $page = 1;
        $pages = 1;
        $sort = "name";
        $order = "ASC";
        $results = [];
        $execution_approved = false;
        try {
            $filter = HostFilter::context($_GET);
            $groupApi =
                $type === "templates" ? TemplateTransferService::groupApi() : \API::HostGroup();
            $group_choices = $groupApi->get([
                "output" => ["groupid", "name"],
                "sortfield" => "name"
            ]);
            $op = $_POST["operation"] ?? "";
            if ($op === "preview") {
                $records = UploadValidator::read($_FILES["upload"] ?? [], $type);
                $options = $this->options($_POST);
                $execution_approved = !empty($options["allow_executable"]);
                if ($type === "items") {
                    $records = ImportValidator::order($records);
                }
                if ($type === "templates") {
                    $records = TemplateTransferService::order($records);
                }
                $pending = [];
                foreach ($records as $r) {
                    if ($type === "items") {
                        $pending[$r["host"] . "\0" . $r["key_"]] = true;
                    }
                }
                foreach ($records as $i => $r) {
                    try {
                        $plan = TransferService::plan($type, $r, $options, $pending);
                        $changes = $type === "templates" ? $plan["diff"] ?? [] : [];
                        if ($type === "templates") {
                            $removed = [];
                            $changes = Security::redact($changes, $removed);
                        }
                        foreach (
                            $type === "templates" ? [] : $plan["payload"]
                            as $field => $after
                        ) {
                            if (
                                !array_key_exists($field, $plan["before"] ?? []) ||
                                ($plan["before"][$field] ?? null) != $after
                            ) {
                                $removed = [];
                                $safe = Security::redact(
                                    [
                                        $field => [
                                            "before" => $plan["before"][$field] ?? null,
                                            "after" => $after
                                        ]
                                    ],
                                    $removed
                                );
                                if (isset($safe[$field])) {
                                    $changes[$field] = $safe[$field];
                                }
                            }
                        }
                        if ($type === "hosts" && !empty($options["restore_items"])) {
                            $removed = [];
                            $changes["local_items"] = Security::redact(
                                array_values(
                                    array_filter($r["item_references"] ?? [], function ($item) {
                                        return ($item["origin"] ?? "") === "local";
                                    })
                                ),
                                $removed
                            );
                        }
                        $preview[] = [
                            "row" => $i + 1,
                            "identity" =>
                                TransferService::identity($type, $r) .
                                (isset($r["key_"]) ? " / " . $r["key_"] : ""),
                            "operation" => $plan["operation"],
                            "diff" => $changes,
                            "message" => "",
                            "risks" => $type === "items" ? Security::executionRisks($r) : []
                        ];
                    } catch (\Throwable $e) {
                        $preview[] = [
                            "row" => $i + 1,
                            "identity" =>
                                TransferService::identity($type, $r) .
                                (isset($r["key_"]) ? " / " . $r["key_"] : ""),
                            "operation" => "error",
                            "message" => $e->getMessage()
                        ];
                    }
                }
                $token = ImportStore::create([
                    "type" => $type,
                    "records" => array_values($records),
                    "options" => $options,
                    "preview" => $preview
                ]);
            } elseif (in_array($op, ["confirm", "report"], true)) {
                $token = $_POST["preview_token"] ?? "";
                $state = ImportStore::transaction($token, function (&$state) use ($type, $op) {
                    if ($state["type"] !== $type) {
                        throw new \RuntimeException("Wrong preview type.");
                    }
                    if ($op === "report") {
                        return;
                    }
                    $end = min(count($state["records"]), $state["offset"] + 25);
                    for ($i = $state["offset"]; $i < $end; $i++) {
                        $r = $state["records"][$i];
                        $result = [
                            "row" => $state["preview"][$i]["row"],
                            "identity" =>
                                TransferService::identity($type, $r) .
                                (isset($r["key_"]) ? " / " . $r["key_"] : ""),
                            "operation" => "error"
                        ];
                        try {
                            if ($state["preview"][$i]["operation"] === "error") {
                                throw new \RuntimeException($state["preview"][$i]["message"]);
                            }
                            $done = TransferService::execute($type, $r, $state["options"]);
                            $result["operation"] = $done["operation"];
                            $result["children"] = $done["children"] ?? [];
                        } catch (\Throwable $e) {
                            $result["message"] = $e->getMessage();
                        }
                        $state["results"][] = $result;
                        $state["offset"] = $i + 1;
                        error_log(
                            "ZabbixImportExport " .
                                json_encode(
                                    [
                                        "user" => \CWebUser::$data["userid"],
                                        "time" => gmdate("c"),
                                        "type" => $type,
                                        "row" => $result["row"],
                                        "object_hash" => hash("sha256", $result["identity"]),
                                        "result" => $result["operation"]
                                    ],
                                    JSON_UNESCAPED_UNICODE
                                )
                        );
                    }
                });
                $results = $state["results"];
                $preview = $state["preview"];
                $execution_approved = !empty($state["options"]["allow_executable"]);
                if ($op === "report") {
                    $this->download($type, $results, "json");
                }
                if ($state["offset"] >= count($state["records"])) {
                    $preview = [];
                }
            } elseif ($op !== "") {
                throw new \RuntimeException("Unknown operation.");
            }
            $hosts = $type === "templates" ? [] : HostFilter::hosts($filter);
            if ($type === "templates") {
                $rows = TemplateTransferService::listing($filter, $group_choices);
            } elseif ($type === "items") {
                $rows = ItemTransferService::export($hosts, $filter);
            } else {
                $rows = HostTransferService::export(array_column($hosts, "hostid"));
            }
            $sort =
                isset($_GET["sort"]) &&
                in_array($_GET["sort"], ["name", "host", "key_", "status", "template"], true)
                    ? $_GET["sort"]
                    : "name";
            $order = ($_GET["sortorder"] ?? "ASC") === "DESC" ? "DESC" : "ASC";
            usort($rows, function ($a, $b) use ($sort, $order) {
                $cmp = strnatcasecmp((string) ($a[$sort] ?? ""), (string) ($b[$sort] ?? ""));
                return $order === "DESC" ? -$cmp : $cmp;
            });
            foreach ($rows as $key => $row) {
                $rows[$key]["_selection"] = hash(
                    "sha256",
                    TransferService::identity($type, $row) . "\0" . ($row["key_"] ?? "")
                );
            }
            if (isset($_GET["export"])) {
                // Keep display rows intact if serialization, size checks or headers fail.
                $exportRows = $rows;
                if (
                    !is_string($_GET["export"]) ||
                    !in_array($_GET["export"], ["csv", "json"], true)
                ) {
                    throw new \RuntimeException("Unsupported file extension.");
                }
                if (!in_array($_GET["scope"] ?? "all", ["all", "selected"], true)) {
                    throw new \RuntimeException("Invalid export scope.");
                }
                if (($_GET["scope"] ?? "all") === "selected") {
                    $mode = $_GET["selection_mode"] ?? "explicit";
                    if (!is_string($mode) || !in_array($mode, ["explicit", "all"], true)) {
                        throw new \RuntimeException("Invalid selection.");
                    }
                    $selected = $_GET[$mode === "all" ? "excluded" : "selected"] ?? [];
                    if (!is_array($selected) || count($selected) > 10000) {
                        throw new \RuntimeException("Invalid selection.");
                    }
                    foreach ($selected as $id) {
                        if (!is_string($id) || !preg_match('/^[a-f0-9]{64}$/D', $id)) {
                            throw new \RuntimeException("Invalid selection.");
                        }
                    }
                    $exportRows = array_values(
                        array_filter($exportRows, function ($r) use ($selected, $mode) {
                            return $mode === "all"
                                ? !in_array($r["_selection"], $selected, true)
                                : in_array($r["_selection"], $selected, true);
                        })
                    );
                    if (!$exportRows) {
                        throw new \RuntimeException("Select at least one record.");
                    }
                }
                if ($type === "templates") {
                    $exportRows = TemplateTransferService::export($exportRows);
                }
                $downloadRows = [];
                foreach ($exportRows as $row) {
                    unset($row["_selection"]);
                    $downloadRows[] = $row;
                }
                $this->download($type, $downloadRows, strtolower((string) $_GET["export"]));
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        $total = count($rows);
        $pages = max(1, (int) ceil($total / 50));
        $page = min($pages, max(1, (int) ($_GET["page"] ?? 1)));
        $rows = array_slice($rows, ($page - 1) * 50, 50);
        $response = new \CControllerResponseData(
            compact(
                "title",
                "type",
                "rows",
                "total",
                "error",
                "preview",
                "token",
                "selections",
                "group_choices",
                "filter",
                "page",
                "pages",
                "sort",
                "order",
                "results",
                "execution_approved"
            ) + [
                "action" => $this->getAction(),
                "can_import_executable" => Security::isSuperAdmin(),
                "can_import" => $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN
            ]
        );
        $response->setTitle($title);
        $this->setResponse($response);
    }
}
