<?php

namespace Modules\ZabbixGraphTrees\Actions;

use CController,
    CControllerResponseData,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
use Modules\ZabbixGraphTrees\Lib\LanguageManager;

class GraphTrees extends CController {

    public function init(): void {
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation();
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation();
        }
    }

    protected function checkInput(): bool {
        return $this->validateInput([]);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        // 仅获取树形数据（主机分组+主机），分类和图表通过AJAX异步加载
        $treeData = [];
        try {
            $hostGroups = API::HostGroup()->get([
                'output' => ['groupid', 'name'],
                'with_hosts' => true,
                'sortfield' => 'name',
                'sortorder' => 'ASC'
            ]);

            foreach ($hostGroups as $group) {
                $hosts = API::Host()->get([
                    'output' => ['hostid', 'host', 'name', 'status'],
                    'groupids' => [$group['groupid']],
                    'filter' => ['status' => HOST_STATUS_MONITORED],
                    'sortfield' => 'name',
                    'sortorder' => 'ASC'
                ]);

                if (!empty($hosts)) {
                    $treeData[] = [
                        'groupid' => $group['groupid'],
                        'groupname' => $group['name'],
                        'hosts' => $hosts
                    ];
                }
            }
        } catch (\Exception $e) {
            error_log("GraphTrees: Failed to get tree data - " . $e->getMessage());
        }

        $response = new CControllerResponseData([
            'title' => LanguageManager::t('Graph Trees'),
            'tree_data' => $treeData,
            'language' => LanguageManager::getCurrentLanguage(),
            'is_chinese' => LanguageManager::isChinese()
        ]);
        $response->setTitle(LanguageManager::t('Graph Trees'));
        $this->setResponse($response);
    }
}
