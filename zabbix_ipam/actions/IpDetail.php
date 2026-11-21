<?php

namespace Modules\ZabbixIpam\Actions;

use CController;
use CControllerResponseData;
use Modules\ZabbixIpam\Lib\HostMatcher;
use Modules\ZabbixIpam\Lib\IpScanner;
use Modules\ZabbixIpam\Lib\IpStorage;
use Modules\ZabbixIpam\Lib\LanguageManager;

require_once dirname(__DIR__).'/lib/HostMatcher.php';
require_once dirname(__DIR__).'/lib/IpScanner.php';
require_once dirname(__DIR__).'/lib/IpStorage.php';
require_once dirname(__DIR__).'/lib/LanguageManager.php';

class IpDetail extends CController {
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
            'search' => 'string',
            'rangeid' => 'string',
            'status' => 'in all,alive,down,unscanned',
            'associated' => 'in all,yes,no',
            'view' => 'in matrix,report',
            'page' => 'int32',
            'per_page' => 'in 20,50,100,200'
        ]);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
    }

    protected function doAction(): void {
        $storage = new IpStorage();
        $ranges = $storage->ranges();
        $range_id = $this->getInput('rangeid', '');
        $status = $this->getInput('status', 'all');
        $associated = $this->getInput('associated', 'all');
        $view = $this->getInput('view', 'matrix');
        $search = mb_strtolower(trim($this->getInput('search', '')));
        $per_page = (int) $this->getInput('per_page', 50);
        $page = max(1, (int) $this->getInput('page', 1));
        $offset = ($page - 1) * $per_page;

        $latest = [];
        foreach ($storage->tasks() as $task) {
            $task_range_id = (string) ($task['range_id'] ?? '');
            if (!isset($latest[$task_range_id]) && in_array($task['status'] ?? '', ['completed', 'running'], true)) {
                $latest[$task_range_id] = $task;
            }
        }

        $host_map = HostMatcher::byIp();
        $rows = [];
        $matrix_ranges = [];
        $total = 0;

        foreach ($ranges as $range) {
            if ($range_id !== '' && $range['id'] !== $range_id) {
                continue;
            }

            try {
                $parsed = IpScanner::parseRange($range['range']);
            }
            catch (\Throwable $exception) {
                continue;
            }

            $known = [];
            if (isset($latest[$range['id']])) {
                foreach ($storage->results($latest[$range['id']]['id']) as $result) {
                    $known[$result['ip']] = $result;
                }
            }

            for ($number = $parsed['start']; $number <= $parsed['end']; $number++) {
                $ip = long2ip($number);
                $state = !isset($known[$ip]) ? 'unscanned' : (!empty($known[$ip]['alive']) ? 'alive' : 'down');
                $hosts = $host_map[$ip] ?? [];
                $is_associated = $hosts !== [];

                if ($status !== 'all' && $state !== $status) {
                    continue;
                }
                if (($associated === 'yes' && !$is_associated) || ($associated === 'no' && $is_associated)) {
                    continue;
                }

                $host_names = implode(' ', array_column($hosts, 'name'));
                $haystack = mb_strtolower($ip.' '.$range['name'].' '.$range['range'].' '.$host_names);
                if ($search !== '' && strpos($haystack, $search) === false) {
                    continue;
                }

                if ($view === 'matrix') {
                    if (!isset($matrix_ranges[$range['id']])) {
                        $matrix_ranges[$range['id']] = [
                            'name' => $range['name'],
                            'range' => $range['range'],
                            'ips' => []
                        ];
                    }
                    $matrix_ranges[$range['id']]['ips'][] = [
                        'ip' => $ip,
                        'state' => $state,
                        'host_url' => $hosts[0]['url'] ?? ''
                    ];
                }

                if ($total >= $offset && count($rows) < $per_page) {
                    $rows[] = [
                        'ip' => $ip,
                        'range_id' => $range['id'],
                        'range_name' => $range['name'],
                        'range' => $range['range'],
                        'state' => $state,
                        'status_updated_at' => $known[$ip]['scanned_at'] ?? '',
                        'hosts' => $hosts,
                        'associated' => $is_associated
                    ];
                }
                $total++;
            }
        }

        $pages = max(1, (int) ceil($total / $per_page));
        $page = min($page, $pages);

        $response = new CControllerResponseData([
            'title' => LanguageManager::t('IP Details'),
            'rows' => $rows,
            'matrix_ranges' => $matrix_ranges,
            'view' => $view,
            'ranges' => $ranges,
            'filters' => [
                'search' => $search,
                'rangeid' => $range_id,
                'status' => $status,
                'associated' => $associated
            ],
            'pagination' => [
                'page' => $page,
                'pages' => $pages,
                'total' => $total,
                'per_page' => $per_page
            ]
        ]);
        $response->setTitle(LanguageManager::t('IP Details'));
        $this->setResponse($response);
    }
}
