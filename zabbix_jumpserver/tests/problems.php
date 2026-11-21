<?php
if (PHP_SAPI !== 'cli') { exit; }
class CController {}
class API {
    public static function Trigger() { return new TriggerFixture(); }
}
class TriggerFixture {
    public function get(array $options): array {
        if (isset($options['sortfield'])) { throw new RuntimeException('Database runtime sort must not be requested'); }
        return [
            ['priority' => '2', 'lastchange' => '10', 'description' => 'Old', 'hosts' => [['hostid' => '1']]],
            ['priority' => '4', 'lastchange' => '20', 'description' => 'New', 'hosts' => [['hostid' => '1'], ['hostid' => '2']]]
        ];
    }
}
require_once dirname(__DIR__).'/actions/Jumpserver.php';
$controller = new \Modules\ZabbixJumpserver\Actions\Jumpserver();
$method = new ReflectionMethod($controller, 'getHostProblems');
if (PHP_VERSION_ID < 80100) { $method->setAccessible(true); }
$result = $method->invoke($controller, ['1', '2']);
if ($result['1']['max'] !== 4 || $result['1']['counts'] !== [4 => 1, 2 => 1]
        || array_column($result['1']['list'], 'name') !== ['New', 'Old']
        || count($result['2']['list']) !== 1 || $method->invoke($controller, []) !== []) {
    throw new RuntimeException('Problem sorting/grouping regression failed');
}
echo "PASS: local newest-first sorting, multi-host grouping, severity totals, empty selection\n";
