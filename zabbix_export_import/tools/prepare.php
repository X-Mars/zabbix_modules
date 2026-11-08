<?php
// Use the real target frontend version to choose its supported manifest format.
if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit();
}
require_once dirname(__DIR__) . "/lib/ZabbixVersion.php";
$root = $argv[1] ?? "";
$defines = @file_get_contents(rtrim($root, "/") . "/include/defines.inc.php");
if (!$defines || !preg_match("/define\('ZABBIX_VERSION',\s*'([^']+)'/", $defines, $m)) {
    fwrite(STDERR, "Usage: php tools/prepare.php /path/to/zabbix/frontend\n");
    exit(1);
}
$path = dirname(__DIR__) . "/manifest.json";
$manifest = json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
$manifest["manifest_version"] = \Modules\ZabbixImportExport\Lib\ZabbixVersion::manifestVersion(
    $m[1]
);
file_put_contents(
    $path,
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n"
);
echo "Prepared for Zabbix " . $m[1] . "\n";
