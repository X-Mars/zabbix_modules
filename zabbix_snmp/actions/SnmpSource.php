<?php

namespace Modules\ZabbixSnmp\Actions;

use CController;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/MibRepository.php';

use Modules\ZabbixSnmp\Lib\LanguageManager;
use Modules\ZabbixSnmp\Lib\MibRepository;

class SnmpSource extends CController {

    public function init(): void {
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation();
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation();
        }
    }

    protected function checkInput(): bool {
        return $this->validateInput([
            'directory' => 'string',
            'file' => 'string',
            'symbol' => 'string',
            'search' => 'string'
        ]);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $directory = trim((string) $this->getInput('directory', ''));
        $file = trim((string) $this->getInput('file', ''));
        $symbol = trim((string) $this->getInput('symbol', ''));
        $search = trim((string) $this->getInput('search', ''));

        $directories = MibRepository::getDirectories($search);
        $selectedDirectory = MibRepository::resolveSelectedDirectory($directory, $directories);
        $files = MibRepository::getFilesInDirectory($selectedDirectory, $directories);
        $details = MibRepository::getFileDetails($file, $files, true, $symbol);

        $result = [
            'ok' => false,
            'message' => LanguageManager::t('No source available'),
            'source' => ''
        ];

        if ($details !== null && !empty($details['source_view']['content'])) {
            $result = [
                'ok' => true,
                'message' => '',
                'source' => (string) $details['source_view']['content'],
                'start_line' => (int) ($details['source_view']['start_line'] ?? 0),
                'end_line' => (int) ($details['source_view']['end_line'] ?? 0),
                'truncated' => !empty($details['source_view']['truncated'])
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
}