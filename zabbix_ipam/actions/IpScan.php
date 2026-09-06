<?php

namespace Modules\ZabbixIpam\Actions;

use CController;
use CControllerResponseData;
use Modules\ZabbixIpam\Lib\IpStorage;
use Modules\ZabbixIpam\Lib\LanguageManager;

require_once dirname(__DIR__).'/lib/IpStorage.php';
require_once dirname(__DIR__).'/lib/LanguageManager.php';

class IpScan extends CController {
    public function init(): void {
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation();
        }
        elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation();
        }
    }

    protected function checkInput(): bool {
        return $this->validateInput([
            'taskid' => 'string',
            'status' => 'in all,pending,running,completed,failed,stopped',
            'search' => 'string',
            'page' => 'int32',
            'per_page' => 'in 20,50,100,200'
        ]);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
    }

    protected function doAction(): void {
        $storage = new IpStorage();
        $task_id = $this->getInput('taskid', '');
        $status = $this->getInput('status', 'all');
        $search = mb_strtolower(trim($this->getInput('search', '')));
        $per_page = (int) $this->getInput('per_page', 20);
        $page = max(1, (int) $this->getInput('page', 1));

        $range_names = [];
        foreach ($storage->ranges() as $range) {
            $range_names[$range['id']] = $range['name'];
        }

        $tasks = [];
        $summary = ['pending' => 0, 'running' => 0, 'completed' => 0, 'failed' => 0];
        foreach ($storage->tasks() as $task) {
            if (isset($summary[$task['status']])) {
                $summary[$task['status']]++;
            }
            $task['range_name'] = $range_names[$task['range_id']] ?? $task['range_id'];
            if ($task_id !== '' && $task['id'] !== $task_id) {
                continue;
            }
            if ($status !== 'all' && $task['status'] !== $status) {
                continue;
            }
            if ($search !== '' && strpos(mb_strtolower($task['id'].' '.$task['range_name']), $search) === false) {
                continue;
            }
            $tasks[] = $task;
        }

        $total_rows = count($tasks);
        $pages = max(1, (int) ceil($total_rows / $per_page));
        $page = min($page, $pages);
        $tasks = array_slice($tasks, ($page - 1) * $per_page, $per_page);

        $this->setResponse(new CControllerResponseData([
            'title' => LanguageManager::t('Task Management'),
            'tasks' => $tasks,
            'selected' => $task_id,
            'summary' => $summary,
            'filters' => ['status' => $status, 'search' => $search],
            'pagination' => ['page' => $page, 'pages' => $pages, 'total' => $total_rows, 'per_page' => $per_page]
        ]));
    }
}
