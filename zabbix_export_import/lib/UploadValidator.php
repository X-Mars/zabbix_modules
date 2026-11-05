<?php
namespace Modules\ZabbixImportExport\Lib;

/** Only PHP's actual upload temporary file is read; original names are never used as server paths. */
final class UploadValidator
{
    public static function metadata(array $file): string
    {
        if (
            ($file["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
            !is_string($file["name"] ?? null) ||
            !is_string($file["tmp_name"] ?? null)
        ) {
            throw new \RuntimeException("Upload failed.");
        }
        $name = $file["name"];
        if (
            strlen($name) > 255 ||
            !preg_match("//u", $name) ||
            preg_match('~[\x00-\x1f\x7f/\\\\]~', $name)
        ) {
            throw new \RuntimeException("Invalid upload filename.");
        }
        if (
            preg_match(
                '/(?:^|\.)(?:php\d*|phtml|pht|phar|exe|dll|sh|bat|cmd|ps1|zip|gz|tar)(?:\.|$)/i',
                $name
            )
        ) {
            throw new \RuntimeException("Executable files and archives are not allowed.");
        }
        $format = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($format, ["csv", "json"], true)) {
            throw new \RuntimeException("Unsupported file extension.");
        }
        return $format;
    }
    public static function content(string $text, string $format, string $mime): void
    {
        if (
            $text === "" ||
            strlen($text) > Serializer::LIMIT ||
            !preg_match("//u", $text) ||
            strpos($text, "\0") !== false
        ) {
            throw new \RuntimeException("Invalid encoding or file too large.");
        }
        if (
            !in_array(
                $mime,
                [
                    "application/json",
                    "text/plain",
                    "text/csv",
                    "text/x-csv",
                    "application/csv",
                    "application/vnd.ms-excel"
                ],
                true
            )
        ) {
            throw new \RuntimeException("Upload is not a supported text data file.");
        }
        // Reject PHP/HTML/polyglot containers. Strings *inside* valid JSON remain inert data.
        $start = preg_replace('/^\xEF\xBB\xBF/', "", $text);
        if ($format === "json" && substr(ltrim($start), 0, 1) !== "{") {
            throw new \RuntimeException("Invalid JSON container.");
        }
        if ($format === "csv") {
            $stream = fopen("php://temp", "w+");
            fwrite($stream, $start);
            rewind($stream);
            try {
                $header = fgetcsv($stream, 0, ",", '"', "");
            } finally {
                fclose($stream);
            }
            if (
                $header !== [
                    "schema_version",
                    "record_type",
                    "source_zabbix_version",
                    "exported_at",
                    "record"
                ]
            ) {
                throw new \RuntimeException("Invalid CSV columns.");
            }
        }
    }
    public static function read(array $file, string $type): array
    {
        $format = self::metadata($file);
        $path = $file["tmp_name"];
        if (!is_uploaded_file($path) || !is_file($path) || is_link($path)) {
            throw new \RuntimeException("Upload failed.");
        }
        $size = filesize($path);
        if ($size === false || $size < 1 || $size > Serializer::LIMIT) {
            throw new \RuntimeException("File too large.");
        }
        if (!class_exists("finfo")) {
            throw new \RuntimeException("PHP fileinfo is required for secure uploads.");
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        $stream = fopen($path, "rb");
        if (!$stream) {
            throw new \RuntimeException("Upload failed.");
        }
        try {
            $text = stream_get_contents($stream, Serializer::LIMIT + 1);
        } finally {
            fclose($stream);
        }
        if ($text === false || strlen($text) !== $size) {
            throw new \RuntimeException("Upload size mismatch.");
        }
        self::content($text, $format, (string) $mime);
        return Serializer::decode($text, $format, $type);
    }
}
