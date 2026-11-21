<?php
if (PHP_SAPI !== 'cli') { exit; }
require_once dirname(__DIR__).'/lib/RackConfig.php';
use Modules\ZabbixRack\Lib\RackConfig;

$dir = sys_get_temp_dir().'/rack-storage-'.bin2hex(random_bytes(8));
mkdir($dir, 0700);
$path = new ReflectionProperty(RackConfig::class, 'configFile');
$cache = new ReflectionProperty(RackConfig::class, 'config');
if (PHP_VERSION_ID < 80100) { $path->setAccessible(true); $cache->setAccessible(true); }
$path->setValue(null, $dir.'/config.json');
function verifyStorage(bool $ok): void { if (!$ok) { throw new RuntimeException('Storage regression failed'); } }
try {
    $before = ['rooms' => [['id' => '1', 'name' => 'Fixture']], 'racks' => []];
    verifyStorage(RackConfig::save($before));
    verifyStorage(json_decode(file_get_contents($dir.'/config.json'), true) === $before);
    $bytes = file_get_contents($dir.'/config.json');
    verifyStorage(!RackConfig::save(['invalid' => INF]));
    verifyStorage(file_get_contents($dir.'/config.json') === $bytes && RackConfig::load() === $before);
    mkdir($dir.'/blocked');
    $path->setValue(null, $dir.'/blocked');
    verifyStorage(!RackConfig::save(['rooms' => []]));
    verifyStorage(RackConfig::getLastError() !== '' && RackConfig::load() === $before);
    verifyStorage(glob($dir.'/.rack-*') === []);
    echo "PASS: atomic save, invalid JSON rejection, failed replacement preserves cache/file, temporary cleanup\n";
} finally {
    unlink($dir.'/config.json'); rmdir($dir.'/blocked'); rmdir($dir);
}
