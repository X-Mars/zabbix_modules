<?php
if (PHP_SAPI !== 'cli') { exit; }
require_once $argv[1].'/include/defines.inc.php';
require_once $argv[1].'/vendor/autoload.php';
require_once dirname(__DIR__).'/lib/bootstrap.php';

use Modules\ZabbixBackupRestore\Lib\{Api as BackupApi, BackupService, Bundle, Catalog};

class API {
    public static function HostGroup() { return new LegacyService(); }
    public static function Configuration() { return new LegacyService(); }
}
class LegacyService {
    public function get(array $parameters) { return [['groupid' => '1']]; }
    public function export(array $parameters) {
        if ($parameters['options'] !== ['groups' => ['1']] || $parameters['format'] !== 'json') {
            throw new RuntimeException('Legacy export options are incorrect.');
        }
        return json_encode(['zabbix_export' => ['version' => '6.0', 'date' => '2026-11-21T00:00:00Z', 'groups' => [['name' => 'Fixture']]]]);
    }
    public function import(array $parameters) {
        if (!isset($parameters['rules']['groups']) || isset($parameters['rules']['host_groups'])) {
            throw new RuntimeException('Legacy import rules are incorrect.');
        }
        return true;
    }
}
$DB = ['TRANSACTIONS' => 0];
function DBstart() { $GLOBALS['DB']['TRANSACTIONS'] = 1; return true; }
function DBend($commit) { $GLOBALS['DB']['TRANSACTIONS'] = 0; return true; }
$api = new BackupApi();
$backup = (new BackupService($api))->export(['host_groups'], ZABBIX_VERSION);
if (!Bundle::supports(ZABBIX_VERSION) || Bundle::counts($backup) !== ['host_groups' => 1]
        || isset(Catalog::labels(ZABBIX_VERSION)['template_groups'])) {
    throw new RuntimeException('Legacy bundle or category mapping failed.');
}
$stream = fopen('php://temp', 'w+');
fwrite($stream, \Symfony\Component\Yaml\Yaml::dump([Bundle::FORMAT => $backup], 80, 2));
rewind($stream);
if (Bundle::read($stream, ZABBIX_VERSION)['configuration'] !== $backup['configuration']) {
    throw new RuntimeException('Legacy bundle did not round-trip.');
}
fclose($stream);
(new BackupService($api))->restore($backup, false, ZABBIX_VERSION);
echo "Legacy native export, category mapping and YAML preview passed.\n";
