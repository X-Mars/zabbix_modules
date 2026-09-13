<?php
// Run with: php zabbix_cmdb/tests/item_rules.php. All writes use a temporary fixture.
$fixture = sys_get_temp_dir().'/cmdb-rules-'.bin2hex(random_bytes(6));
mkdir($fixture.'/lib', 0700, true);
mkdir($fixture.'/data', 0700);
foreach (['ItemConfig.php', 'ItemFinder.php', 'LanguageManager.php'] as $file) {
    copy(dirname(__DIR__).'/lib/'.$file, $fixture.'/lib/'.$file);
}
copy(dirname(__DIR__).'/data/item_rules.json', $fixture.'/data/item_rules.json');
require $fixture.'/lib/ItemFinder.php';
use Modules\ZabbixCmdb\Lib\ItemConfig;
use Modules\ZabbixCmdb\Lib\ItemFinder;
define('ITEM_STATUS_ACTIVE', 0);
class API {
    public static $items = [];
    public static $history = [];
    public static function Item() { return new FakeItemApi(); }
    public static function History() { return new FakeHistoryApi(); }
}
class FakeItemApi {
    public function get($params) {
        return array_values(array_filter(API::$items, function ($item) use ($params) {
            if (!in_array($item['hostid'], $params['hostids']) || $item['status'] !== 0) {
                return false;
            }
            foreach ($params['search'] as $field => $patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match('/^'.str_replace('\\*', '.*', preg_quote($pattern, '/')).'$/iu', $item[$field])) {
                        return true;
                    }
                }
            }
            return false;
        }));
    }
}
class FakeHistoryApi {
    public function get($params) {
        return isset(API::$history[$params['itemids'][0]])
            ? [['value' => API::$history[$params['itemids'][0]]]] : [];
    }
}
function check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}
function rule($field, $pattern, $match = 'fuzzy', $transform = 'none') {
    return compact('field', 'pattern', 'match', 'transform');
}
try {
    $rules = array_fill_keys(array_keys(ItemConfig::LABELS), []);
    $rules['cpu_count'] = [rule('name', 'CUSTOM*cores'), rule('key_', 'system.cpu.num', 'exact')];
    $rules['cpu_usage'] = [rule('key_', 'custom.idle', 'exact', 'subtract_from_100')];
    $rules['memory_total'] = [rule('key_', 'memory.total', 'fuzzy')];
    ItemConfig::save($rules);
    $before = file_get_contents(ItemConfig::path());
    check(json_decode($before, true)['cpu_count'] === $rules['cpu_count'], 'JSON persistence preserves order');
    $invalid = $rules;
    $invalid['cpu_count'][0]['pattern'] = ' ';
    try { ItemConfig::save($invalid); throw new LogicException('Invalid rule accepted'); }
    catch (InvalidArgumentException $expected) {}
    check(file_get_contents(ItemConfig::path()) === $before, 'Invalid save leaves configuration intact');
    $base = ['hostid' => '1', 'value_type' => '3', 'status' => 0];
    API::$items = [
        $base + ['itemid' => '12', 'name' => 'Custom physical cores count', 'key_' => 'custom.cores2', 'lastvalue' => '32'],
        $base + ['itemid' => '2', 'name' => 'Number of CPUs', 'key_' => 'system.cpu.num', 'lastvalue' => '4'],
        $base + ['itemid' => '10', 'name' => 'Custom CPU cores count', 'key_' => 'custom.cores', 'lastvalue' => '16'],
        $base + ['itemid' => '20', 'name' => 'Idle', 'key_' => 'custom.idle', 'lastvalue' => '75'],
        $base + ['itemid' => '30', 'name' => 'Memory', 'key_' => 'vendor.memory.total[bytes]', 'lastvalue' => '0']
    ];
    $found = ItemFinder::batchGetHostItems(['1', '2']);
    check($found['1']['cpu_count']['value'] === '16', 'Fuzzy name before exact key; deterministic itemid tie-break');
    check($found['1']['cpu_usage']['value'] === 25.0, 'Idle percentage conversion');
    check($found['1']['memory_total']['value'] === '0', 'Substring key matching and zero value');
    check($found['1']['memory_usage'] === null && $found['2']['cpu_count'] === null, 'Empty rules and missing host items');
    $rules['cpu_count'] = array_reverse($rules['cpu_count']);
    ItemConfig::save($rules);
    check(ItemFinder::findCpuCount('1')['value'] === '4', 'Reordering changes selected item for single-host lookup');
    API::$items[1]['lastvalue'] = '';
    API::$history['2'] = '8';
    check(ItemFinder::findCpuCount('1')['value'] === '8', 'History fallback preserves configured priority');
    API::$items[1]['status'] = 1;
    check(ItemFinder::findCpuCount('1')['value'] === '16', 'Disabled items are excluded');
    check(ItemFinder::selectItem([['key_' => 'SYSTEM.CPU.NUM']], [rule('key_', 'system.cpu.num', 'exact')]) === null, 'Exact key matching is case-sensitive');
    check(ItemFinder::selectItem([['key_' => 'vm.memory.sizeXtotalX']], [rule('key_', 'vm.memory.size[total]')]) === null, 'Key brackets are literal, not regex');
    echo "All CMDB rule tests passed.\n";
} finally {
    foreach (['lib/ItemConfig.php', 'lib/ItemFinder.php', 'lib/LanguageManager.php', 'data/item_rules.json'] as $file) { unlink($fixture.'/'.$file); }
    rmdir($fixture.'/lib'); rmdir($fixture.'/data'); rmdir($fixture);
}
