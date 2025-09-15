<?php

// 模拟Zabbix环境的基本类
class CControllerResponseData {
    private $data;

    public function __construct($data) {
        $this->data = $data;
    }

    public function getData() {
        return $this->data;
    }
}

// 模拟API类
class API {
    public static function HostGroup() {
        return new class {
            public function get($params) {
                // 模拟返回主机分组数据
                return [
                    ['groupid' => 2, 'name' => 'Linux servers'],
                    ['groupid' => 4, 'name' => 'Network'],
                    ['groupid' => 5, 'name' => 'Zabbix servers']
                ];
            }
        };
    }

    public static function Host() {
        return new class {
            public function get($params) {
                // 模拟返回主机数据
                return [
                    [
                        'hostid' => 10001,
                        'host' => 'test-host-1',
                        'name' => 'Test Host 1',
                        'status' => 0,
                        'groups' => [['groupid' => 2, 'name' => 'Linux servers']],
                        'interfaces' => [['interfaceid' => 1, 'ip' => '192.168.1.1', 'type' => 1, 'main' => 1]]
                    ]
                ];
            }
        };
    }

    public static function HostInterface() {
        return new class {
            public function get($params) {
                return [];
            }
        };
    }

    public static function Item() {
        return new class {
            public function get($params) {
                return [];
            }
        };
    }

    public static function History() {
        return new class {
            public function get($params) {
                return [];
            }
        };
    }
}

// 模拟用户类型常量
define('USER_TYPE_ZABBIX_USER', 1);

// 模拟控制器基类
class CController {
    protected $response;
    protected $input = [];

    public function getInput($key, $default = null) {
        return isset($this->input[$key]) ? $this->input[$key] : $default;
    }

    public function validateInput($fields) {
        return true;
    }

    public function getUserType() {
        return USER_TYPE_ZABBIX_USER;
    }

    public function setResponse($response) {
        $this->response = $response;
    }

    public function getResponse() {
        return $this->response;
    }
}

// 包含实际的控制器代码
require_once 'zabbix_cmdb/actions/Cmdb.php';

// 测试控制器
$controller = new \Modules\ZabbixCmdb\Actions\Cmdb();
$controller->init();

// 模拟输入
$controller->input = [
    'search' => '',
    'groupid' => 0
];

// 执行动作
$controller->doAction();

// 获取响应
$response = $controller->getResponse();
$data = $response->getData();

echo "Controller Test Results:\n";
echo "Host groups count: " . count($data['host_groups']) . "\n";
echo "Hosts count: " . count($data['hosts']) . "\n";
echo "Search: '" . $data['search'] . "'\n";
echo "Selected groupid: " . $data['selected_groupid'] . "\n";

echo "\nHost groups:\n";
foreach ($data['host_groups'] as $group) {
    echo "  - ID: {$group['groupid']}, Name: {$group['name']}\n";
}

echo "\nHosts:\n";
foreach ($data['hosts'] as $host) {
    echo "  - {$host['host_name']} ({$host['ip_address']})\n";
}

?>
