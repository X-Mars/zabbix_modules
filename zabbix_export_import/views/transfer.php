<?php
require_once dirname(__DIR__) . "/lib/ViewGrid.php";
require_once dirname(__DIR__) . "/lib/Ui.php";
use Modules\ZabbixImportExport\Lib\{LanguageManager as L, ViewGrid, Ui as U};
$this->includeJsFile("styles.php");
$page = ViewGrid::page($data["title"]);
$app = U::div(null, "ixe-app")
    ->setId("ixe-app")
    ->setAttribute("lang", str_replace("_", "-", L::locale()))
    ->setAttribute("data-total", (string) $data["total"]);
$f = $data["filter"];
if (!$f) {
    $page->addItem(U::div(L::t($data["error"]), "ixe-alert ixe-alert--danger"))->show();
    return;
}
$type = $data["type"];
$base = [
    "action" => $data["action"],
    "ui" => "compact",
    "filter_groups" => $f["groups"],
    "filter_host" => $f["host"]
];
if ($type === "items") {
    $base += ["filter_item_name" => $f["item_name"], "filter_item_origin" => $f["item_origin"]];
}
$link = function (array $args = []) use ($base) {
    return "zabbix.php?" . http_build_query(array_merge($base, $args));
};
$url = $link();
$descriptions = [
    "hosts" => "Export host configuration and rebuild portable relationships.",
    "items" => "Browse every host and distinguish local, inherited and discovered items.",
    "templates" => "Transfer complete monitoring templates using native Zabbix configuration."
];
$header = U::div(
    [
        U::div([
            U::tag("h2", $data["title"]),
            U::tag("p", L::t($descriptions[$type]), "ixe-muted")
        ]),
        $data["can_import"]
            ? U::button(L::t("Import configuration"), "primary")->setAttribute(
                "data-ixe-open",
                "import"
            )
            : null
    ],
    "ixe-page-header"
);
$app->addItem($header);
if ($data["error"]) {
    $app->addItem(
        U::div(
            [U::badge(L::t("error"), "danger"), L::t($data["error"])],
            "ixe-alert ixe-alert--danger"
        )
    );
}
$filters = (new CForm("get"))
    ->setName("ixe-filter")
    ->setId("ixe-filter")
    ->setAction("zabbix.php")
    ->addClass("ixe-filter");
$filters->addVar("action", $data["action"])->addVar("ui", "compact");
$groups = ["0" => L::t($type === "templates" ? "All templates" : "All hosts")];
foreach ($data["group_choices"] as $g) {
    $groups[$g["groupid"]] = $g["name"];
}
$selected = $f["groups"][0] ?? "0";
$filters->addItem(
    U::field(
        L::t($type === "templates" ? "Template groups" : "Host groups"),
        U::select("filter_groups[]", $groups, $selected)->setId("ixe-group")
    )
);
$filters->addItem(
    U::field(
        L::t($type === "templates" ? "Template name" : "Host name"),
        U::input("filter_host", $f["host"])->setAttribute(
            "placeholder",
            L::t($type === "templates" ? "Search template name" : "Search visible host name")
        )
    )
);
if ($type === "items") {
    $filters->addItem(
        U::field(
            L::t("Name"),
            U::input("filter_item_name", $f["item_name"])->setAttribute(
                "placeholder",
                L::t("Search item name")
            )
        )
    );
    $filters->addItem(
        U::field(
            L::t("Origin"),
            U::select(
                "filter_item_origin",
                [
                    -1 => L::t("All origins"),
                    0 => L::t("Local"),
                    1 => L::t("Inherited"),
                    2 => L::t("Discovered")
                ],
                $f["item_origin"]
            )
        )
    );
}
$filters->addItem(
    U::div(
        [
            U::button(L::t("Apply"), "primary", "submit"),
            U::tag("a", L::t("Reset"), "ixe-button")->setAttribute(
                "href",
                "zabbix.php?action=" . $data["action"]
            )
        ],
        "ixe-filter-actions"
    )
);
$app->addItem(
    U::div(
        [
            $filters,
            U::div(
                L::t(
                    $type === "templates"
                        ? "All templates includes every accessible template; parent groups include nested groups."
                        : "All hosts includes every authorized host, including nested groups and disabled hosts."
                ),
                "ixe-scope-note"
            )
        ],
        "ixe-card ixe-filter-card"
    )
);
$form = (new CForm("get"))
    ->setName("ixe-list")
    ->setId("ixe-list")
    ->setAction("zabbix.php")
    ->addClass("ixe-list");
foreach ($base as $key => $value) {
    $form->addVar($key, $value);
}
$form->addVar("sort", $data["sort"])->addVar("sortorder", $data["order"]);
$toolbar = U::div(
    [
        U::div(
            [
                U::tag("strong", L::t("Configuration records")),
                U::badge((string) $data["total"]),
                U::tag("span", L::t("Selected") . ": 0", "ixe-muted")->setId("ixe-selection-count"),
                U::button(L::t("Select all filtered records"))->setId("ixe-select-filtered"),
                U::button(L::t("Clear selection"))->setId("ixe-clear-selection")
            ],
            "ixe-toolbar-title"
        ),
        U::div(
            [
                U::select(
                    "scope",
                    ["selected" => L::t("Selected records"), "all" => L::t("All filtered records")],
                    "all"
                )->setAttribute("aria-label", L::t("Export scope")),
                U::button(L::t("Export CSV"), "default", "submit", "export", "csv"),
                U::button(L::t("Export JSON"), "primary", "submit", "export", "json")
            ],
            "ixe-toolbar-actions"
        )
    ],
    "ixe-toolbar"
);
$form->addItem($toolbar);
$all = U::input("ixe-all", "1", "checkbox")
    ->setId("ixe-all")
    ->setAttribute("aria-label", L::t("Select current page"))
    ->setAttribute("title", L::t("Select current page"));
$headers =
    $type === "hosts"
        ? [
            $all,
            L::t("Name"),
            L::t("Host groups"),
            L::t("Interface"),
            L::t("Templates"),
            L::t("Items"),
            L::t("Status")
        ]
        : ($type === "items"
            ? [
                $all,
                L::t("Name"),
                L::t("Host"),
                L::t("Key"),
                L::t("Type"),
                L::t("Origin"),
                L::t("Status")
            ]
            : [
                $all,
                L::t("Name"),
                L::t("Template groups"),
                L::t("Linked templates"),
                L::t("Items"),
                L::t("Triggers")
            ]);
$head = U::tag("tr");
foreach ($headers as $i => $h) {
    $head->addItem(
        U::tag(
            "th",
            $i === 1
                ? U::tag("a", $h)->setAttribute(
                    "href",
                    $link([
                        "sort" => "name",
                        "sortorder" => $data["order"] === "ASC" ? "DESC" : "ASC"
                    ])
                )
                : $h,
            $i === 0 ? "ixe-checkbox-cell" : ""
        )
    );
}
$body = U::tag("tbody");
foreach ($data["rows"] as $r) {
    $check = U::input("selected[" . $r["_selection"] . "]", $r["_selection"], "checkbox")
        ->setId("selected_" . $r["_selection"])
        ->setAttribute("aria-label", $r["host"] ?? $r["template"]);
    $name = U::div(
        [
            U::tag("strong", $r["name"] ?? $r["template"], "ixe-record-name"),
            U::tag("small", $r["host"] ?? $r["template"], "ixe-muted")
        ],
        "ixe-record"
    );
    $state = U::badge(
        ($r["status"] ?? 0) == 0 ? L::t("Enabled") : L::t("Disabled"),
        ($r["status"] ?? 0) == 0 ? "success" : "info"
    );
    if ($type === "hosts") {
        $groupTags = [];
        foreach ($r["host_groups"] as $g) {
            $groupTags[] = U::badge($g["name"]);
        }
        $interfaces = [];
        foreach ($r["interfaces"] as $i) {
            $interfaces[] = U::div(
                ($i["useip"] ? $i["ip"] : $i["dns"]) . ":" . $i["port"],
                "ixe-mono"
            );
        }
        $templates = [];
        foreach ($r["templates"] as $t) {
            $templates[] = U::div($t["name"] ?? $t["host"], "ixe-cell-line");
        }
        $cells = [
            $check,
            $name,
            U::div($groupTags, "ixe-tags"),
            $interfaces ?: "—",
            $templates ?: "—",
            count($r["item_references"]),
            $state
        ];
    } elseif ($type === "items") {
        $cells = [
            $check,
            $name,
            $r["host"],
            U::tag("code", $r["key_"], "ixe-key"),
            function_exists("item_type2str") ? item_type2str($r["type"]) : $r["type"],
            U::badge(L::t($r["origin"]), $r["origin"] === "local" ? "primary" : "info"),
            $state
        ];
    } else {
        $tags = [];
        foreach ($r["groups"] as $g) {
            $tags[] = U::badge($g["name"]);
        }
        $parents = [];
        foreach ($r["parents"] as $t) {
            $parents[] = U::div($t["name"] ?? $t["host"]);
        }
        $cells = [
            $check,
            $name,
            U::div($tags, "ixe-tags"),
            $parents ?: "—",
            $r["items_count"],
            $r["triggers_count"]
        ];
    }
    $row = U::tag("tr");
    foreach ($cells as $i => $cell) {
        $row->addItem(U::tag("td", $cell, $i === 0 ? "ixe-checkbox-cell" : ""));
    }
    $body->addItem($row);
}
if (!$data["rows"]) {
    $body->addItem(
        U::tag(
            "tr",
            U::tag(
                "td",
                [
                    U::icon("search"),
                    U::tag("strong", L::t("No matching records")),
                    U::tag(
                        "p",
                        L::t(
                            $type === "templates"
                                ? "Try All templates or change your search."
                                : "Try All hosts or change your search."
                        )
                    )
                ],
                "ixe-empty"
            )->setAttribute("colspan", count($headers))
        )
    );
}
$table = U::tag("table", [U::tag("thead", $head), $body], "ixe-table")->setAttribute(
    "aria-label",
    $data["title"]
);
$form->addItem(U::div($table, "ixe-table-scroll"));
$form->addItem(
    U::div(
        [
            U::tag(
                "span",
                L::t("Total") .
                    ": " .
                    $data["total"] .
                    " · " .
                    L::t("Page") .
                    " " .
                    $data["page"] .
                    " / " .
                    $data["pages"],
                "ixe-muted"
            ),
            U::div(
                [
                    U::tag(
                        "a",
                        L::t("Previous"),
                        "ixe-button" . ($data["page"] <= 1 ? " is-disabled" : "")
                    )->setAttribute(
                        "href",
                        $link([
                            "page" => max(1, $data["page"] - 1),
                            "sort" => $data["sort"],
                            "sortorder" => $data["order"]
                        ])
                    ),
                    U::badge((string) $data["page"], "primary"),
                    U::tag(
                        "a",
                        L::t("Next"),
                        "ixe-button" . ($data["page"] >= $data["pages"] ? " is-disabled" : "")
                    )->setAttribute(
                        "href",
                        $link([
                            "page" => min($data["pages"], $data["page"] + 1),
                            "sort" => $data["sort"],
                            "sortorder" => $data["order"]
                        ])
                    )
                ],
                "ixe-pagination"
            )
        ],
        "ixe-table-footer"
    )
);
$app->addItem(U::div($form, "ixe-card"));
if ($type === "items") {
    $app->addItem(
        U::div(
            L::t(
                "Inherited and discovered items are shown as references and cannot be imported directly."
            ),
            "ixe-scope-note"
        )
    );
}
if ($data["results"]) {
    $counts = array_count_values(array_column($data["results"], "operation"));
    $childErrors = 0;
    foreach ($data["results"] as $result) {
        foreach ($result["children"] ?? [] as $child) {
            if (($child["operation"] ?? "") === "error") {
                $childErrors++;
            }
        }
    }
    $counts["error"] = ($counts["error"] ?? 0) + $childErrors;
    $metrics = [];
    foreach (
        ["create" => "success", "update" => "primary", "skip" => "info", "error" => "danger"]
        as $key => $kind
    ) {
        $metrics[] = U::div(
            [U::tag("strong", (string) ($counts[$key] ?? 0)), U::badge(L::t($key), $kind)],
            "ixe-metric"
        );
    }
    $results = U::div(
        [U::tag("h3", L::t("Import results")), U::div($metrics, "ixe-metrics")],
        "ixe-card ixe-section"
    );
    $report = U::tag("tbody");
    foreach ($data["results"] as $r) {
        $report->addItem(
            U::tag("tr", [
                U::tag("td", (string) $r["row"]),
                U::tag("td", $r["identity"]),
                U::tag(
                    "td",
                    U::badge(
                        L::t($r["operation"]),
                        $r["operation"] === "error" ? "danger" : "success"
                    )
                ),
                U::tag(
                    "td",
                    L::t($r["message"] ?? "") .
                        (!empty($r["children"])
                            ? " / " . json_encode($r["children"], JSON_UNESCAPED_UNICODE)
                            : "")
                )
            ])
        );
    }
    $results->addItem(U::div(U::tag("table", $report, "ixe-table"), "ixe-table-scroll"));
    $download = (new CForm("post"))
        ->setAction($url)
        ->addVar(ViewGrid::csrfName(), ViewGrid::csrf($data["action"]))
        ->addVar("preview_token", $data["token"])
        ->addVar("operation", "report");
    $download->addItem(U::button(L::t("Download results"), "default", "submit"));
    $results->addItem($download);
    $app->addItem($results);
}
if ($data["preview"]) {
    $preview = U::div(
        [
            U::div(
                [
                    U::tag("h3", L::t("Review before importing")),
                    U::badge((string) count($data["preview"]) . " " . L::t("records"), "primary")
                ],
                "ixe-toolbar"
            )
        ],
        "ixe-card ixe-section"
    )->setId("ixe-preview");
    if ($data["execution_approved"]) {
        $preview->addItem(
            U::div(
                L::t(
                    "Executable monitoring configuration is approved. Confirming may activate scripts, commands or remote requests in Zabbix."
                ),
                "ixe-alert ixe-alert--warning"
            )
        );
    }
    foreach ($data["preview"] as $r) {
        $preview->addItem(
            U::tag(
                "details",
                [
                    U::tag("summary", [
                        U::badge(
                            L::t($r["operation"]),
                            $r["operation"] === "error" ? "danger" : "primary"
                        ),
                        U::tag("strong", $r["identity"]),
                        U::tag("span", L::t($r["message"]), "ixe-muted")
                    ]),
                    U::tag(
                        "pre",
                        json_encode($r["diff"] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                        "ixe-diff"
                    )
                ],
                "ixe-preview-row"
            )
        );
    }
    $confirm = (new CForm("post"))
        ->setAction($url)
        ->addVar(ViewGrid::csrfName(), ViewGrid::csrf($data["action"]))
        ->addVar("preview_token", $data["token"])
        ->addVar("operation", "confirm");
    $confirm->addItem(
        U::div(
            [
                U::tag(
                    "span",
                    L::t("Confirm writes up to 25 records. Continue until all batches finish."),
                    "ixe-muted"
                ),
                U::button(L::t("Confirm"), "primary", "submit", "confirm", "1")
            ],
            "ixe-confirm-bar"
        )
    );
    $preview->addItem($confirm);
    $app->addItem($preview);
}
if ($data["can_import"]) {
    $drawer = U::div(null, "ixe-overlay")
        ->setId("ixe-import-overlay")
        ->setAttribute("hidden", "hidden");
    $panel = U::tag("section", null, "ixe-drawer")
        ->setAttribute("role", "dialog")
        ->setAttribute("aria-modal", "true")
        ->setAttribute("aria-labelledby", "ixe-import-title")
        ->setAttribute("tabindex", "-1");
    $panel->addItem(
        U::div(
            [
                U::div([
                    U::tag("h3", L::t("Import configuration"))->setId("ixe-import-title"),
                    U::tag("p", L::t("Upload → Preview → Confirm"), "ixe-muted")
                ]),
                U::button("×")
                    ->setAttribute("data-ixe-close", "import")
                    ->setAttribute("aria-label", L::t("Close"))
            ],
            "ixe-drawer-header"
        )
    );
    $upload = (new CForm("post"))
        ->setId("ixe-upload-form")
        ->setAttribute("enctype", "multipart/form-data")
        ->setAction($url)
        ->addVar(ViewGrid::csrfName(), ViewGrid::csrf($data["action"]))
        ->addVar("operation", "preview");
    $file = U::input("upload", "", "file")
        ->setAttribute("accept", ".csv,.json")
        ->setAttribute("required", "required")
        ->setId("ixe-upload-file");
    $drop = U::tag(
        "label",
        [
            U::icon("upload"),
            U::tag("strong", L::t("Choose or drop a file")),
            U::tag("span", "CSV / JSON · UTF-8 · ≤ 10 MiB", "ixe-muted"),
            $file
        ],
        "ixe-upload-zone"
    );
    $body = U::div(
        [
            U::div(
                [U::badge("1", "primary"), U::tag("h4", L::t("Select file"))],
                "ixe-step-heading"
            ),
            $drop,
            U::div("", "ixe-file-name")->setId("ixe-file-name"),
            U::div(
                [U::badge("2", "primary"), U::tag("h4", L::t("Import policy"))],
                "ixe-step-heading"
            )
        ],
        "ixe-drawer-body"
    );
    $policies = [
        "strategy" => [
            "create" => "Create missing only",
            "update" => "Update existing only",
            "skip" => "Skip duplicates",
            "error" => "Error on duplicates"
        ]
    ];
    if ($type === "templates") {
        $policies += [
            "groups" => [
                "stop" => "Stop on missing template groups",
                "create" => "Create missing template groups"
            ]
        ];
    }
    if ($type === "hosts") {
        $policies += [
            "item_strategy" => $policies["strategy"],
            "groups" => [
                "stop" => "Stop on missing groups",
                "skip" => "Skip missing groups",
                "create" => "Create missing groups"
            ],
            "templates" => [
                "stop" => "Stop on missing templates",
                "skip" => "Skip missing template links"
            ]
        ];
    }
    $fields = U::div(null, "ixe-form-grid");
    foreach ($policies as $key => $choices) {
        $translated = [];
        foreach ($choices as $value => $label) {
            $translated[$value] = L::t($label);
        }
        $fields->addItem(
            U::field(
                L::t(
                    $type === "templates" && $key === "groups" ? "Template groups" : ucfirst($key)
                ),
                U::select($key, $translated, array_key_first($choices))
            )
        );
    }
    $body->addItem($fields);
    $options =
        $type === "hosts"
            ? [
                "interfaces" => "Replace interfaces",
                "tags" => "Replace tags",
                "inventory" => "Replace inventory",
                "templates_replace" => "Replace template links",
                "restore_items" => "Restore local items"
            ]
            : [];
    if ($options) {
        $checks = U::div(null, "ixe-check-grid");
        foreach ($options as $key => $label) {
            $checks->addItem(
                U::tag(
                    "label",
                    [U::input($key, "1", "checkbox"), U::tag("span", L::t($label))],
                    "ixe-check-label"
                )
            );
        }
        $body->addItem($checks);
    }
    if ($type !== "templates") {
        $advanced = U::tag(
            "details",
            U::tag("summary", L::t("Advanced name mapping")),
            "ixe-advanced"
        );
        $maps = U::div(null, "ixe-form-grid");
        foreach (
            $type === "hosts"
                ? [
                    "host_map" => "Host name mapping (JSON)",
                    "template_map" => "Template name mapping (JSON)"
                ]
                : ["host_map" => "Host name mapping (JSON)"]
            as $key => $label
        ) {
            $maps->addItem(
                U::field(
                    L::t($label),
                    U::tag("textarea", "{}", "ixe-textarea")
                        ->setAttribute("name", $key)
                        ->setAttribute("rows", "3")
                )
            );
        }
        $advanced->addItem($maps);
        $body->addItem($advanced);
    }
    if ($data["can_import_executable"]) {
        $body->addItem(
            U::tag(
                "details",
                [
                    U::tag("summary", L::t("Executable configuration permission")),
                    U::div(
                        L::t(
                            "Executable configurations are blocked by default. Review trusted code before allowing it."
                        ),
                        "ixe-alert ixe-alert--warning"
                    ),
                    U::tag(
                        "label",
                        [
                            U::input("allow_executable", "1", "checkbox"),
                            U::tag(
                                "span",
                                L::t(
                                    "Allow executable/active monitoring configuration (Super admin review required)"
                                )
                            )
                        ],
                        "ixe-check-label"
                    )
                ],
                "ixe-advanced"
            )
        );
    }
    $body->addItem(
        U::div(
            L::t(
                $type === "templates"
                    ? "Template JSON may use the native Zabbix export format. Updates preserve target objects missing from the file."
                    : "Upload CSV or JSON, maximum 10 MiB. Preview never writes configuration."
            ),
            "ixe-alert ixe-alert--info"
        )
    );
    $upload
        ->addItem($body)
        ->addItem(
            U::div(
                [
                    U::button(L::t("Cancel"))->setAttribute("data-ixe-close", "import"),
                    U::button(L::t("Preview"), "primary", "submit", "preview", "1")
                ],
                "ixe-drawer-footer"
            )
        );
    $panel->addItem($upload);
    $drawer->addItem($panel);
    $app->addItem($drawer);
}
$page->addItem($app)->show();
$this->includeJsFile("filter.js.php");
