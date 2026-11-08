<?php
namespace Modules\ZabbixImportExport\Lib;

/** Versioned, bounded transfer format. CSV payload is JSON, never executable spreadsheet text. */
final class Serializer
{
    const LIMIT = 10485760;
    private static function csvRow($stream, array $row): void
    {
        fputcsv($stream, $row, ",", '"', "");
        fseek($stream, -1, SEEK_CUR);
        fwrite($stream, "\r\n");
    }
    public static function encode(
        string $type,
        array $records,
        string $format,
        string $version
    ): string {
        $data = [
            "schema_version" => "1.0",
            "record_type" => $type,
            "source_zabbix_version" => $version,
            "exported_at" => gmdate("c"),
            "records" => array_values($records)
        ];
        if ($format === "json") {
            return json_encode(
                $data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        }
        $f = fopen("php://temp", "w+");
        self::csvRow($f, [
            "schema_version",
            "record_type",
            "source_zabbix_version",
            "exported_at",
            "record"
        ]);
        foreach ($records as $record) {
            self::csvRow($f, [
                "1.0",
                $type,
                $version,
                $data["exported_at"],
                json_encode($record, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            ]);
        }
        rewind($f);
        $text = stream_get_contents($f);
        fclose($f);
        return $text;
    }
    public static function decode(string $text, string $format, string $type): array
    {
        if (strlen($text) > self::LIMIT || !preg_match("//u", $text)) {
            throw new \RuntimeException("Invalid encoding or file too large.");
        }
        $text = preg_replace('/^\xEF\xBB\xBF/', "", $text);
        if ($format === "json") {
            $data = json_decode($text, true, 32, JSON_THROW_ON_ERROR);
        } elseif ($format === "csv") {
            $f = fopen("php://temp", "w+");
            fwrite($f, $text);
            rewind($f);
            if (
                fgetcsv($f, 0, ",", '"', "") !== [
                    "schema_version",
                    "record_type",
                    "source_zabbix_version",
                    "exported_at",
                    "record"
                ]
            ) {
                throw new \RuntimeException("Invalid CSV columns.");
            }
            $data = ["schema_version" => "1.0", "record_type" => $type, "records" => []];
            while (($row = fgetcsv($f, 0, ",", '"', "")) !== false) {
                if (
                    count($row) !== 5 ||
                    $row[0] !== "1.0" ||
                    $row[1] !== $type ||
                    !$row[2] ||
                    !strtotime($row[3])
                ) {
                    throw new \RuntimeException("Invalid CSV metadata.");
                }
                $data["records"][] = json_decode($row[4], true, 32, JSON_THROW_ON_ERROR);
            }
            fclose($f);
        } else {
            throw new \RuntimeException("Unsupported file extension.");
        }
        if (
            $type === "templates" &&
            $format === "json" &&
            is_array($data) &&
            isset($data["zabbix_export"])
        ) {
            $records = TemplateTransferService::fromNative($data);
            $data = [
                "schema_version" => "1.0",
                "record_type" => "templates",
                "source_zabbix_version" => $data["zabbix_export"]["version"] ?? "",
                "exported_at" => gmdate("c"),
                "records" => $records
            ];
        }
        if (
            !is_array($data) ||
            ($data["schema_version"] ?? "") !== "1.0" ||
            ($data["record_type"] ?? "") !== $type ||
            !in_array($type, ["hosts", "items", "templates"], true) ||
            !is_array($data["records"] ?? null) ||
            count($data["records"]) > 10000
        ) {
            throw new \RuntimeException("Invalid schema or record count.");
        }
        if (
            $format === "json" &&
            (empty($data["source_zabbix_version"]) || !strtotime($data["exported_at"] ?? ""))
        ) {
            throw new \RuntimeException("Missing metadata.");
        }
        if (array_values($data["records"]) !== $data["records"]) {
            throw new \RuntimeException("Records must be a list.");
        }
        $seen = [];
        foreach ($data["records"] as $record) {
            if (!is_array($record) || !$record) {
                throw new \RuntimeException("Invalid record.");
            }
            $key = $type === "templates" ? $record["template"] ?? "" : $record["host"] ?? "";
            if (!is_string($key) || $key === "") {
                throw new \RuntimeException("Missing identity or duplicate record.");
            }
            if ($type === "items") {
                if (!is_string($record["key_"] ?? null) || $record["key_"] === "") {
                    throw new \RuntimeException("Missing identity or duplicate record.");
                }
                $key = json_encode([$key, $record["key_"]]);
            }
            if (!$key || isset($seen[$key])) {
                throw new \RuntimeException("Missing identity or duplicate record.");
            }
            $seen[$key] = true;
        }
        return $data["records"];
    }
}
