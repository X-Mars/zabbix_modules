<?php

namespace Modules\ZabbixIpam\Actions;

use CController;
use CControllerResponseData;
use Modules\ZabbixIpam\Lib\IpScanner;
use Modules\ZabbixIpam\Lib\IpStorage;
use Modules\ZabbixIpam\Lib\LanguageManager;

require_once dirname(__DIR__).'/lib/IpScanner.php';
require_once dirname(__DIR__).'/lib/IpStorage.php';
require_once dirname(__DIR__).'/lib/LanguageManager.php';

class IpManager extends CController {
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
            'rangeid' => 'string',
            'enabled_status' => 'in all,enabled,disabled',
            'scan_status' => 'in all,never,pending,running,completed,failed,stopped',
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
        $all_ranges = $storage->ranges();
        $latest = [];
        foreach ($storage->tasks() as $task) {
            if (!isset($latest[$task['range_id']])) {
                $latest[$task['range_id']] = $task;
            }
        }

        $selected = $this->getInput('rangeid', '');
        $enabled_status = $this->getInput('enabled_status', 'all');
        $scan_status = $this->getInput('scan_status', 'all');
        $search = mb_strtolower(trim($this->getInput('search', '')));
        $per_page = (int) $this->getInput('per_page', 20);
        $page = max(1, (int) $this->getInput('page', 1));
        $rows = [];

        foreach ($all_ranges as $range) {
            try {
                $total = IpScanner::parseRange($range['range'])['count'];
            }
            catch (\Throwable $exception) {
                $total = 0;
            }

            $task = $latest[$range['id']] ?? null;
            $row = [
                'range' => $range,
                'task' => $task,
                'total' => $task['total'] ?? $total,
                'alive' => $task['alive'] ?? 0,
                'scanned' => $task['scanned'] ?? 0,
                'task_status' => $task['status'] ?? 'never',
                'last_scan' => $range['last_scan'] ?? ($task['finished_at'] ?? '')
            ];

            $matches_enabled = $enabled_status === 'all'
                || ($enabled_status === 'enabled' && !empty($range['enabled']))
                || ($enabled_status === 'disabled' && empty($range['enabled']));
            $matches_scan = $scan_status === 'all' || $row['task_status'] === $scan_status;
            $matches_search = $search === ''
                || strpos(mb_strtolower(($range['name'] ?? '').' '.($range['range'] ?? '')), $search) !== false;

            if (($selected === '' || $range['id'] === $selected) && $matches_enabled && $matches_scan && $matches_search) {
                $rows[] = $row;
            }
        }

        $total_rows = count($rows);
        $pages = max(1, (int) ceil($total_rows / $per_page));
        $page = min($page, $pages);
        $rows = array_slice($rows, ($page - 1) * $per_page, $per_page);

        $this->setResponse(new CControllerResponseData([
            'title' => LanguageManager::t('IPAM'),
            'rows' => $rows,
            'all_ranges' => $all_ranges,
            'filters' => [
                'rangeid' => $selected,
                'enabled_status' => $enabled_status,
                'scan_status' => $scan_status,
                'search' => $search
            ],
            'pagination' => ['page' => $page, 'pages' => $pages, 'total' => $total_rows, 'per_page' => $per_page]
        ]));
    }
}
