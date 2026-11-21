<?php

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// Run with: php tests/regression.php /path/to/zabbix/ui
require_once $argv[1].'/include/defines.inc.php';
require_once $argv[1].'/vendor/autoload.php';
require_once dirname(__DIR__).'/lib/bootstrap.php';

use Modules\ZabbixBackupRestore\Lib\{Api as BackupApi, BackupService, Bundle, BundleYaml, LanguageManager, References};
use Symfony\Component\Yaml\Yaml;

final class CWebUser {
	/** @var array */ public static $data = ['lang' => 'zh_CN', 'timezone' => 'Asia/Shanghai', 'roleid' => '1', 'userid' => '1'];
}

final class CSettingsHelper {
	public const DEFAULT_LANG = 'default_lang';
	public const DEFAULT_TIMEZONE = 'default_timezone';

	public static function getPublic(string $field): string {
		return $field === self::DEFAULT_LANG ? 'zh_CN' : 'America/New_York';
	}
}

final class API {
	/** @var array */ public static $tables = [];
	/** @var array */ public static $calls = [];
	/** @var array */ public static $ids = [
		'Configuration' => '', 'Host' => 'hostid', 'HostGroup' => 'groupid', 'UserMacro' => 'globalmacroid',
		'UserGroup' => 'usrgrpid', 'User' => 'userid', 'Action' => 'actionid', 'DRule' => 'druleid',
		'Service' => 'serviceid', 'Role' => 'roleid', 'MediaType' => 'mediatypeid', 'ProxyGroup' => 'proxy_groupid',
		'Proxy' => 'proxyid', 'Template' => 'templateid', 'Script' => 'scriptid', 'Regexp' => 'regexpid'
	];

	/** @var string */ public $name;

	public static function __callStatic(string $service, array $arguments): self {
		$api = new self();
		$api->name = $service;
		return $api;
	}

	public function __call(string $method, array $arguments) {
		$parameters = $arguments[0];
		self::$calls[] = [$this->name, $method, $parameters];
		if ($this->name === 'Configuration') {
			if ($method === 'export') {
				return ['zabbix_export' => self::$tables['Configuration']];
			}
			return true;
		}
		$id = self::$ids[$this->name];
		if ($method === 'get') {
			$rows = self::$tables[$this->name] ?? [];
			foreach ($parameters['filter'] ?? [] as $key => $values) {
				$rows = array_filter($rows, static function (array $row) use ($key, $values): bool  { return in_array($row[$key], (array) $values, true); });
			}
			return array_values($rows);
		}
		foreach ($parameters as $row) {
			if (str_starts_with($method, 'create')) {
				$row[$id] = (string) (100 + count(self::$tables[$this->name] ?? []));
				self::$tables[$this->name][$row[$id]] = $row;
			}
			else {
				$row_id = $row[$id];
				self::$tables[$this->name][$row_id] = $row + self::$tables[$this->name][$row_id];
			}
		}
		return [$id.'s' => array_column($parameters, $id)];
	}
}

$DB = ['TRANSACTIONS' => 0];
function DBstart(): bool {
	global $DB;
	$DB['TRANSACTIONS'] = 1;
	$DB['snapshot'] = API::$tables;
	return true;
}

function DBend(bool $commit): bool {
	global $DB;
	if (!$commit) {
		API::$tables = $DB['snapshot'];
	}
	$DB = ['TRANSACTIONS' => 0];
	return $commit;
}

function same($actual, $expected, string $description): void {
	if ($actual !== $expected) {
		throw new RuntimeException($description."\nExpected: ".var_export($expected, true)
			."\nActual: ".var_export($actual, true));
	}
}

function rejects(callable $run, string $description): void {
	try {
		$run();
	}
	catch (RuntimeException $e) {
		return;
	}
	throw new RuntimeException($description);
}

$version = ZABBIX_VERSION;
$family = substr($version, 0, 3);
$service = new BackupService(new BackupApi());
$secret_macro = '{$PASS}';
$host = [
	'host' => 'Host 北京',
	'tls_connect' => '2',
	'tls_accept' => '2',
	'tls_psk_identity' => 'zabbix-local-key',
	'tls_psk' => '00ac91605cd102bd9f16b77057bc67c2'
];
$checks = ['dcheckid' => '19', 'type' => '12', 'key_' => '', 'ports' => '0', 'allow_redirect' => '0'];
API::$tables = [
	'Configuration' => ['version' => $family, 'hosts' => [['host' => $host['host'], 'macros' => [[
		'macro' => '{$HOST_TOKEN}', 'type' => 'SECRET_TEXT', 'value' => 'hidden'
	]]]], 'media_types' => [['name' => 'Webhook']]],
	'Host' => ['7' => ['hostid' => '7', 'flags' => ZBX_FLAG_DISCOVERY_NORMAL] + $host],
	'HostGroup' => ['8' => ['groupid' => '8', 'name' => 'Servers']],
	'MediaType' => ['10' => ['mediatypeid' => '10', 'name' => 'Webhook']],
	'UserMacro' => ['20' => ['globalmacroid' => '20', 'macro' => $secret_macro, 'type' => '1', 'value' => 'secret']],
	'Regexp' => ['5' => ['regexpid' => '5', 'name' => 'Expression', 'expressions' => [
		['expression_type' => '0', 'expression' => 'success', 'exp_delimiter' => ',', 'case_sensitive' => '0']
	]]],
	'User' => ['12' => ['userid' => '12', 'username' => 'receiver', 'provisioned' => '0']],
	'Service' => [
		'21' => ['serviceid' => '21', 'uuid' => 'dddddddddddddddddddddddddddddddd', 'name' => 'Health',
			'children' => [['serviceid' => '22']]],
		'22' => ['serviceid' => '22', 'uuid' => 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee', 'name' => 'Health',
			'children' => []]
	],
	'DRule' => [
		'17' => ['druleid' => '17', 'name' => 'Network', 'proxyid' => '0', 'dchecks' => [$checks]]
	],
	'Action' => [
		'51' => [
			'actionid' => '51', 'name' => 'Signal', 'eventsource' => (string) EVENT_SOURCE_DISCOVERY,
			'esc_period' => '0', 'pause_suppressed' => '1',
			'filter' => ['evaltype' => (string) CONDITION_EVAL_TYPE_EXPRESSION, 'formula' => 'A',
				'eval_formula' => 'A', 'conditions' => [
					['conditionid' => '18', 'conditiontype' => (string) ZBX_CONDITION_TYPE_DCHECK, 'operator' => '0',
						'value' => '19', 'value2' => '', 'formulaid' => 'A']
				]],
			'operations' => [
				['operationid' => '13', 'operationtype' => (string) OPERATION_TYPE_MESSAGE, 'esc_period' => '0',
					'opmessage' => ['default_msg' => '1', 'subject' => 'ignored', 'mediatypeid' => '10'],
					'opmessage_usr' => [['userid' => '12']]],
				['operationtype' => (string) OPERATION_TYPE_GROUP_ADD, 'opgroup' => [['groupid' => '8']]]
			]
		]
	]
];

same(Bundle::filename(1735689600), 'zabbix_backup_2025-01-01_08-00-00.yaml', 'User timezone');
CWebUser::$data['timezone'] = 'default';
same(Bundle::filename(1735689600), 'zabbix_backup_2024-12-31_19-00-00.yaml', 'Frontend default timezone');
same(LanguageManager::t('Backup and restore'), '备份与还原', 'Chinese locale');
CWebUser::$data['lang'] = 'en_US';
same(LanguageManager::t('Backup and restore'), 'Backup and restore', 'English locale');

$backup = $service->export(['hosts', 'global_macros', 'actions', 'services', 'media_types', 'regular_expressions'],
	$version);
$backup['records']['global_macros'][] = ['macro' => '{$STRING}', 'value' => "echo \"text\" && echo '*word'\nnext"];
$stream = tmpfile();
BundleYaml::write($stream, $backup);
rewind($stream);
same(Bundle::read($stream, $version), $backup, 'Lossless streamed YAML');
rewind($stream);
$yaml = stream_get_contents($stream);
fclose($stream);

$read = static function (string $yaml, string $target): array {
	$stream = tmpfile();
	try {
		fwrite($stream, $yaml);
		rewind($stream);
		return Bundle::read($stream, $target);
	}
	finally {
		fclose($stream);
	}
};
$legacy = Yaml::dump([Bundle::FORMAT => $backup], 80, 2);
same($read($legacy, $version), $backup, 'Read YAML generated by previous releases');
same(Yaml::parse($yaml)[Bundle::FORMAT], $backup, 'Streamed output remains a single YAML document');
Bundle::checkSize(500 * 1024 * 1024);
rejects(static function () { return Bundle::checkSize(500 * 1024 * 1024 + 1); }, 'Reject a file 1 byte over the limit');
same($backup['omissions']['secret_macros'], 2, 'Masked macro count');
same(isset($backup['records']['regular_expressions'][0]['expressions'][0]['exp_delimiter']), false,
	'Remove unused regular expression delimiter');
same(isset($backup['configuration']['hosts'][0]['macros'][0]['value']), false, 'No value for native secret macro');
same($backup['records']['actions'][0]['filter']['conditions'][0]['value'],
	References::checkIdentity('Network', $checks), 'Portable discovery check identity');
same($backup['records']['actions'][0]['operations'][0]['opmessage'], ['default_msg' => '1', 'mediatypeid' => 'Webhook'],
	'Remove fields the action API rejects');
same($backup['records']['actions'][0]['operations'][0]['opmessage_usr'][0]['userid'], 'receiver', 'Portable recipient');

foreach ([
	"test: &loop [*loop]",
	"test: !php/object data",
	"test: [\"x\", &anchor \"y\", *anchor]",
	str_repeat(' ', 165)."node: true",
	"zabbix_export:\n  version: '7.0'\n"
] as $invalid) {
	rejects(static function () use ($invalid, $read, $version) { return $read($invalid, $version); }, 'Reject unsupported or malicious YAML');
}
foreach (['&anchor value', '*anchor', '!php/object data', '[1, 2]'] as $invalid_value) {
	$invalid = "zabbix_backup_restore:\n  records:\n    global_macros:\n      -\n        value: ".$invalid_value."\n";
	rejects(static function () use ($invalid, $read, $version) { return $read($invalid, $version); }, 'Reject unsupported syntax inside a streamed row');
}
$duplicate = $backup;
$duplicate['records']['actions'][] = $duplicate['records']['actions'][0];
rejects(static function () use ($duplicate, $read, $version) { return $read(Yaml::dump([Bundle::FORMAT => $duplicate], 80, 2), $version); }, 'Reject duplicate action');
if ($family === '8.0') {
	rejects(static function () use ($read, $yaml) { return $read($yaml, '7.0.33'); }, 'Reject downgrade');
}
else {
	same($read($yaml, '8.0.0'), $backup, 'Accept 7.0 backup on 8.0');
}

$target = API::$tables;
$target['Host'] = ['710' => ['hostid' => '710', 'host' => $host['host']]];
$target['HostGroup'] = ['810' => ['groupid' => '810', 'name' => 'Servers']];
$target['MediaType'] = ['210' => ['mediatypeid' => '210', 'name' => 'Webhook']];
$target['User'] = ['120' => ['userid' => '120', 'username' => 'receiver', 'provisioned' => '0']];
$target['DRule']['17']['dchecks'][0]['dcheckid'] = '190';
$target['UserMacro']['20']['value'] = 'target-secret';
$target['Action'] = [];
$target['Service'] = [];

API::$tables = $target;
$restorer = new BackupService(new BackupApi());
$restorer->restore($backup, true, $version);
$action = array_values(API::$tables['Action'])[0];
same($action['filter']['conditions'][0]['value'], '190', 'Remap discovery check ID');
same($action['operations'][0]['opmessage_usr'][0]['userid'], '120', 'Remap recipient ID');
same($action['operations'][0]['opmessage']['mediatypeid'], '210', 'Remap media type ID');
same($action['operations'][1]['opgroup'][0]['groupid'], '810', 'Remap group ID');
same(API::$tables['Service'][100]['children'], [['serviceid' => '101']], 'Restore DAG links by UUID after creating services');
same(API::$tables['Host'][710]['tls_psk'], $host['tls_psk'], 'Restore host encryption independently');
same(API::$tables['UserMacro'][20]['value'], 'target-secret', 'Preserve target global secret');
same($DB['TRANSACTIONS'], 0, 'Transaction committed');
$updated = API::$tables;

$restorer = new BackupService(new BackupApi());
$restorer->restore($backup, false, $version);
same(API::$tables, $updated, 'Create-only mode is safe to retry');
API::$tables = $target;
unset(API::$tables['HostGroup']);
$state = API::$tables;
$restorer = new BackupService(new BackupApi());
rejects(static function () use ($backup, $restorer, $version) { return $restorer->restore($backup, true, $version); }, 'Missing destination reference aborts restore');
same(API::$tables, $state, 'Roll back all phases on failure');
same($DB['TRANSACTIONS'], 0, 'Transaction closed after rollback');
echo "Backup restore regression passed for Zabbix ".$version."\n";
