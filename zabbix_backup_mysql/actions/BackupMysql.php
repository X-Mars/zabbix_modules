<?php

namespace Modules\ZabbixBackupMysql\Actions;

use Modules\ZabbixBackupMysql\Lib\{Access, BackupFile, SqlExport, LanguageManager};

foreach (['Access', 'BackupFile', 'SqlExport', 'LanguageManager'] as $class) {
    require_once dirname(__DIR__) . '/lib/' . $class . '.php';
}

final class BackupMysql extends \CController {
    protected function init(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
            $this->disableCsrfValidation();
        }
    }

    protected function checkPermissions(): bool {
        return Access::allowed();
    }

    protected function checkInput(): bool {
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST'], true)) {
            $this->setResponse(new \CControllerResponseFatal());
            return false;
        }
        return true;
    }

    protected function doAction(): void {
        $data = [
            'tables' => [], 'error' => '', 'saved' => null, 'can_export' => false,
            'token' => \CCsrfTokenHelper::get($this->getAction()),
            'scope' => 'all', 'mode' => 'both', 'destination' => 'download',
            'selected' => [], 'exclude_history' => false, 'directory' => BackupFile::DEFAULT_DIRECTORY
        ];

        try {
            $database = SqlExport::frontend();
            $data['tables'] = $database->tables();
            $data['can_export'] = $data['tables'] !== [];
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $data = $this->input($data);
                $data['saved'] = $this->export($database, $data);
            }
        } catch (\mysqli_sql_exception $error) {
            $data['error'] = LanguageManager::tf('The database export failed (MySQL error %d).', $error->getCode());
        } catch (\RuntimeException $error) {
            $data['error'] = LanguageManager::t($error->getMessage());
        }

        $response = new \CControllerResponseData($data);
        $response->setTitle(LanguageManager::t('MySQL backup'));
        $this->setResponse($response);
    }

    private function input(array $data): array {
        foreach (['scope' => ['all', 'selected'], 'mode' => ['both', 'schema', 'data'],
            'destination' => ['download', 'server']] as $key => $values) {
            if (!in_array($_POST[$key] ?? null, $values, true)) {
                throw new \RuntimeException('The backup options are invalid.');
            }
            $data[$key] = $_POST[$key];
        }

        $path = $_POST['directory'] ?? BackupFile::DEFAULT_DIRECTORY;
        if (!is_string($path) || strlen($path) > 4096 || preg_match('/[[:cntrl:]]/', $path)) {
            throw new \RuntimeException('The backup options are invalid.');
        }
        $data['directory'] = trim($path) === '' ? BackupFile::DEFAULT_DIRECTORY : trim($path);
        $selected = $_POST['tables'] ?? [];
        if (!is_array($selected) || count($selected) > count($data['tables'])
                || $selected !== array_filter($selected, 'is_string')) {
            throw new \RuntimeException('The table selection is invalid. Refresh the page and retry.');
        }
        $data['selected'] = $data['scope'] === 'all' ? [] : $selected;
        $data['exclude_history'] = ($_POST['exclude_history'] ?? null) === '1';
        return $data;
    }

    private function export(SqlExport $database, array $data): ?array {
        @set_time_limit(0);
        $filename = BackupFile::filename();
        $file = tmpfile();
        if ($file === false) {
            throw new \RuntimeException('Could not create a temporary backup file.');
        }

        try {
            $selected = $data['scope'] === 'all' ? null : $data['selected'];
            $database->run(static function (string $chunk) use ($file): void {
                BackupFile::write($file, $chunk);
            }, $selected, $data['mode'], $data['exclude_history']);

            $size = fstat($file)['size'];
            if (BackupFile::shouldSave($data['destination'], $size)) {
                return [
                    'path' => BackupFile::save($file, $filename, $data['directory']),
                    'bytes' => $size,
                    'automatic' => $data['destination'] === 'download'
                ];
            }

            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            while (ob_get_level() > 0 && @ob_end_clean()) {
            }
            rewind($file);
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . $size);
            header('Cache-Control: no-store');
            header('X-Content-Type-Options: nosniff');
            fpassthru($file);
        } finally {
            fclose($file);
        }
        exit;
    }
}
