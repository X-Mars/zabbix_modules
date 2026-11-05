<?php
foreach (
    [
        "ZabbixVersion",
        "LanguageManager",
        "Serializer",
        "ImportValidator",
        "Security",
        "UploadValidator",
        "HostFilter",
        "RelationResolver",
        "ItemTransferService",
        "HostTransferService",
        "TemplateTransferService",
        "TransferService",
        "ImportStore"
    ]
    as $class
) {
    require_once __DIR__ . "/" . $class . ".php";
}
