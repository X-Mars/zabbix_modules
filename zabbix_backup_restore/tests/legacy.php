<?php
if (PHP_SAPI !== 'cli') { exit; }
require_once $argv[1].'/include/defines.inc.php';
require_once $argv[1].'/vendor/autoload.php';
require_once dirname(__DIR__).'/lib/bootstrap.php';

use Modules\ZabbixBackupRestore\Lib\{Api as BackupApi, BackupService, Bundle, Catalog, Records, References};

class API {
    public static function HostGroup() { return new LegacyService(); }
    public static function Configuration() { return new LegacyService(); }
    public static function UserGroup() { return new LegacyUserGroups(); }
}
class LegacyService {
    public function get(array $parameters) { return [['groupid' => '1', 'name' => 'Fixture']]; }
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
class LegacyUserGroups {
    public function get(array $parameters) {
        if (isset($parameters['selectHostGroupRights']) || isset($parameters['selectTemplateGroupRights'])
                || in_array('mfaid', $parameters['output'], true)) {
            throw new RuntimeException('Modern user group fields sent to legacy API.');
        }
        return [['usrgrpid' => '5', 'name' => 'Operators', 'gui_access' => '1',
            'rights' => [['id' => '1', 'permission' => '2']],
            'tag_filters' => [['groupid' => '1', 'tag' => 'team', 'value' => 'ops']]]];
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
$groups = Catalog::groups(ZABBIX_VERSION);
if (count($groups['Access and notifications'] ?? []) !== 6
        || count($groups['Services and shared configuration'] ?? []) !== 5) {
    throw new RuntimeException('Legacy access and shared categories are missing.');
}
$backup = (new BackupService($api))->export(['user_groups'], ZABBIX_VERSION);
$row = $backup['records']['user_groups'][0];
if ($row['rights'] !== [['id' => 'Fixture', 'permission' => '2']]
        || $row['tag_filters'][0]['groupid'] !== 'Fixture') {
    throw new RuntimeException('Legacy rights did not use portable group references.');
}
$stream = tmpfile();
fwrite($stream, \Symfony\Component\Yaml\Yaml::dump([Bundle::FORMAT => $backup], 80, 2));
rewind($stream);
$parsed = Bundle::read($stream, ZABBIX_VERSION);
fclose($stream);
$normalized = (new Records(new References($api)))->normalize('user_groups', $parsed['records']['user_groups'][0], false);
if ($normalized['rights'] !== [['id' => '1', 'permission' => '2']]
        || isset($normalized['hostgroup_rights']) || isset($normalized['templategroup_rights'])) {
    throw new RuntimeException('Legacy rights were not translated back to legacy API fields.');
}
if (Catalog::conditionType('SERVICE') !== CONDITION_TYPE_SERVICE
        || strpos(Catalog::records(ZABBIX_VERSION)['scripts'][3], 'manualinput') !== false) {
    throw new RuntimeException('Legacy action or script fields were not adapted.');
}
echo "Legacy native export, access/shared categories, rights mapping and YAML round trip passed.\n";
