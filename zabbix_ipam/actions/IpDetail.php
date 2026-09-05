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
    private const PAGE_SIZE = 50;

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
            'page' => 'int32'
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
        $search = mb_strtolower(trim($this->getInput('search', '')));
        $page = max(1, (int) $this->getInput('page', 1));
        $offset = ($page - 1) * self::PAGE_SIZE;

        $latest = [];
        foreach ($storage->tasks() as $task) {
            $task_range_id = (string) ($task['range_id'] ?? '');
            if (!isset($latest[$task_range_id]) && in_array($task['status'] ?? '', ['completed', 'running'], true)) {
                $latest[$task_range_id] = $task;
            }
        }

        $host_map = HostMatcher::byIp();
        $rows = [];
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

                if ($total >= $offset && count($rows) < self::PAGE_SIZE) {
                    $rows[] = [
                        'ip' => $ip,
                        'range_id' => $range['id'],
                        'range_name' => $range['name'],
                        'range' => $range['range'],
                        'state' => $state,
                        'hosts' => $hosts,
                        'associated' => $is_associated
                    ];
                }
                $total++;
            }
        }

        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $pages);

        $this->setResponse(new CControllerResponseData([
            'title' => LanguageManager::t('IP Details'),
            'rows' => $rows,
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
                'page_size' => self::PAGE_SIZE
            ]
        ]));
    }
}
