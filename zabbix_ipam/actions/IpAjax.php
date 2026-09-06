<?php

namespace Modules\ZabbixIpam\Actions;

use CController;
use Modules\ZabbixIpam\Lib\HostMatcher;
use Modules\ZabbixIpam\Lib\IpScanner;
use Modules\ZabbixIpam\Lib\IpStorage;
use Modules\ZabbixIpam\Lib\LanguageManager;
use Modules\ZabbixIpam\Lib\TaskManager;

require_once dirname(__DIR__).'/lib/HostMatcher.php';
require_once dirname(__DIR__).'/lib/IpScanner.php';
require_once dirname(__DIR__).'/lib/IpStorage.php';
require_once dirname(__DIR__).'/lib/LanguageManager.php';
require_once dirname(__DIR__).'/lib/TaskManager.php';

class IpAjax extends CController {
    public function init(): void {
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation();
        }
        elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation();
        }
        if (method_exists($this, 'disableView')) {
            $this->disableView();
        }
    }

    protected function checkInput(): bool {
        return $this->validateInput([
            'op' => 'in status,start,stop,save_range,delete_range,range_ips',
            'rangeid' => 'string',
            'taskid' => 'string',
            'id' => 'string',
            'name' => 'string',
            'range' => 'string',
            'enabled' => 'in 0,1',
            'shard_size' => 'int32'
        ]);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_ADMIN;
    }

    protected function doAction(): void {
        $storage = new IpStorage();
        $operation = $this->getInput('op', 'status');

        try {
            switch ($operation) {
                case 'save_range':
                    $value = trim($this->getInput('range', ''));
                    IpScanner::parseRange($value);
                    $range = $storage->saveRange([
                        'id' => $this->getInput('id', '') ?: null,
                        'name' => trim($this->getInput('name', '')) ?: $value,
                        'range' => $value,
                        'enabled' => $this->getInput('enabled', '1') === '1',
                        'ports' => ''
                    ]);
                    $output = ['ok' => true, 'message' => LanguageManager::t('Range saved.'), 'range' => $range];
                    break;

                case 'delete_range':
                    $range_id = $this->getInput('rangeid', '');
                    foreach ($storage->tasks() as $task) {
                        if ($task['range_id'] === $range_id && in_array($task['status'], ['pending', 'running'], true)) {
                            throw new \RuntimeException('Stop active tasks before deleting this range.');
                        }
                    }
                    $storage->deleteRange($range_id);
                    $output = ['ok' => true, 'message' => LanguageManager::t('Range deleted.')];
                    break;

                case 'start':
                    $manager = new TaskManager($storage);
                    $task = $manager->start($this->getInput('rangeid', ''), max(1, $this->getInput('shard_size', 64)));
                    if (!$manager->dispatch($task['id'])) {
                        throw new \RuntimeException('Failed to start task.');
                    }
                    $output = ['ok' => true, 'task' => $task, 'message' => LanguageManager::t('Task started.')];
                    break;

                case 'stop':
                    (new TaskManager($storage))->stop($this->getInput('taskid', ''));
                    $output = ['ok' => true, 'message' => LanguageManager::t('Task stopped.')];
                    break;

                case 'range_ips':
                    $output = $this->rangeIps($storage, $this->getInput('rangeid', ''));
                    break;

                default:
                    $task = $storage->task($this->getInput('taskid', ''));
                    if (!$task) {
                        throw new \InvalidArgumentException('Task not found.');
                    }
                    $output = ['ok' => true, 'task' => $task, 'results' => $storage->results($task['id'])];
            }
        }
        catch (\Throwable $exception) {
            $output = ['ok' => false, 'message' => LanguageManager::t($exception->getMessage())];
        }

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($output, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function rangeIps(IpStorage $storage, string $range_id): array {
        $range = $storage->range($range_id);
        if (!$range) {
            throw new \InvalidArgumentException('Range not found.');
        }

        $parsed = IpScanner::parseRange($range['range']);
        $latest = null;
        foreach ($storage->tasks() as $task) {
            if ($task['range_id'] === $range_id && in_array($task['status'], ['completed', 'running'], true)) {
                $latest = $task;
                break;
            }
        }

        $known = [];
        if ($latest) {
            foreach ($storage->results($latest['id']) as $result) {
                $known[$result['ip']] = $result;
            }
        }

        $host_map = HostMatcher::byIp();
        $ips = [];
        for ($number = $parsed['start']; $number <= $parsed['end']; $number++) {
            $ip = long2ip($number);
            $hosts = $host_map[$ip] ?? [];
            $ips[] = [
                'ip' => $ip,
                'alive' => (bool) ($known[$ip]['alive'] ?? false),
                'scanned' => isset($known[$ip]),
                'host_name' => $hosts[0]['name'] ?? null,
                'host_url' => $hosts[0]['url'] ?? null
            ];
        }

        return ['ok' => true, 'range' => $range, 'task' => $latest, 'ips' => $ips];
    }
}
