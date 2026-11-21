<?php

namespace Modules\ZabbixBackupRestore\Actions;

require_once dirname(__DIR__).'/lib/SecurityToken.php';

use Modules\ZabbixBackupRestore\Lib\{Api, BackupService, Bundle, BundleYaml, Catalog, LanguageManager};
use RuntimeException;
use function Modules\ZabbixBackupRestore\Lib\str_contains;

require_once dirname(__DIR__).'/lib/bootstrap.php';

final class BackupRestore extends \CController {

	private const EXPORT_MEMORY = '512M';
	private const RESTORE_MEMORY = '1024M';
	private const PREVIEW_VALID_SECONDS = 3600;

	protected function init(): void {
		if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
			if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation();
        } else {
            $this->disableSIDvalidation();
        }
		}
	}

	protected function checkPermissions(): bool {
		return \CWebUser::isLoggedIn() && (int) \CWebUser::getType() === USER_TYPE_SUPER_ADMIN;
	}

	protected function checkInput(): bool {
		if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'POST'], true)) {
			$this->setResponse(new \CControllerResponseFatal());
			return false;
		}

		return true;
	}

	protected function doAction(): void {
		$version = ZABBIX_VERSION;
		if ($_SERVER['REQUEST_METHOD'] === 'GET') {
			$response = new \CControllerResponseData([
				'token' => \Modules\ZabbixBackupRestore\Lib\SecurityToken::get($this->getAction()),
				'version' => $version,
				'groups' => Bundle::supports($version) ? Catalog::groups($version) : [],
				'upload_limit' => $this->uploadLimit()
			]);
			$response->setTitle(LanguageManager::t('Backup and restore'));
			$this->setResponse($response);
			return;
		}

		try {
			if (!Bundle::supports($version)) {
				throw new RuntimeException(LanguageManager::t('This module supports Zabbix 6.0, 7.0 and 8.0.'));
			}

			$service = new BackupService(new Api());
			$operation = $_POST['operation'] ?? '';
			$update = ($_POST['update_existing'] ?? '') === '1';

			if ($operation === 'export') {
				$selection = $_POST['types'] ?? [];
				if (!is_array($selection) || count($selection) > count(Catalog::labels($version))) {
					throw new RuntimeException(LanguageManager::t('The selected configuration is invalid.'));
				}
				$this->prepareBackupMemory(self::EXPORT_MEMORY);
				$this->export($service, $selection, $version);
			}

			if (!in_array($operation, ['preview', 'restore'], true)) {
				throw new RuntimeException(LanguageManager::t('The selected configuration is invalid.'));
			}

			$this->prepareBackupMemory(self::RESTORE_MEMORY);
			$stream = $this->upload($_FILES['yaml'] ?? []);
			try {
				$hash = hash_init('sha256');
				hash_update_stream($hash, $stream);
				$digest = hash_final($hash);
				if ($operation === 'restore') {
					$this->checkPreview($_POST['proof'] ?? null, $digest, $update);
				}
				rewind($stream);
				$bundle = Bundle::read($stream, $version);
			}
			finally {
				fclose($stream);
			}

			if ($operation === 'preview') {
				$expires = time() + self::PREVIEW_VALID_SECONDS;
				$this->json([
					'proof' => $expires.'.'.\Modules\ZabbixBackupRestore\Lib\SecurityToken::get(self::previewScope($digest, $update, $expires)),
					'source' => $bundle['source_version'],
					'target' => $version,
					'counts' => $this->translatedCounts($bundle),
					'notes' => $this->notes($bundle)
				]);
			}

			ignore_user_abort(true);
			$service->restore($bundle, $update, $version);
			$this->json(['message' => LanguageManager::t('Restore complete.'), 'notes' => $this->notes($bundle)]);
		}
		catch (RuntimeException $exception) {
			$this->json(['error' => $exception->getMessage()], 422);
		}
		catch (\Throwable $exception) {
			error_log('Zabbix backup and restore: '.get_class($exception).' in '.$exception->getFile()
				.':'.$exception->getLine());
			$this->json(['error' => LanguageManager::t('An unexpected error occurred.')], 500);
		}
	}

	private function export(BackupService $service, array $selection, string $version): void {
		$stream = tmpfile();
		if ($stream === false) {
			throw new RuntimeException(LanguageManager::t('Could not open a temporary backup file.'));
		}
		try {
			BundleYaml::write($stream, $service->export($selection, $version));
			rewind($stream);
			$this->sendHeaders('application/yaml; charset=UTF-8', 200);
			header('Content-Disposition: attachment; filename="'.Bundle::filename().'"');
			fpassthru($stream);
		}
		finally {
			fclose($stream);
		}
		exit;
	}

	private function prepareBackupMemory(string $minimum): void {
		// Zabbix's exporter and YAML parser materialize the entire configuration in memory.
		$limit = (string) ini_get('memory_limit');
		if ($limit !== '-1' && \str2mem($limit) < \str2mem($minimum)) {
			ini_set('memory_limit', $minimum);
		}
		$response = json_encode([
			'error' => LanguageManager::t('PHP ran out of memory while processing the backup.')
		], JSON_THROW_ON_ERROR);
		$reserve = str_repeat(' ', 256 * 1024);
		register_shutdown_function(static function () use (&$reserve, $response): void {
			$reserve = '';
			$error = error_get_last();
			if (($error['type'] ?? null) !== E_ERROR
					|| !str_contains($error['message'], 'Allowed memory size')
					|| !str_contains($error['message'], 'exhausted') || headers_sent()) {
				return;
			}

			http_response_code(500);
			header('Content-Type: application/json; charset=UTF-8');
			header('Cache-Control: no-store');
			echo $response;
		});
	}

	private function uploadLimit(): int {
		$limit = Bundle::MAX_BYTES;
		$file_limit = \str2mem((string) ini_get('upload_max_filesize'));
		$post_limit = \str2mem((string) ini_get('post_max_size'));
		if ($file_limit > 0) {
			$limit = min($limit, $file_limit);
		}
		if ($post_limit > 0) {
			// Reserve space for the multipart fields and headers in the browser's POST.
			$limit = min($limit, max(0, $post_limit - 65536));
		}
		return $limit;
	}

	private function upload(array $upload) {
		$error = $upload['error'] ?? UPLOAD_ERR_NO_FILE;
		if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
				|| ($upload['size'] ?? 0) > Bundle::MAX_BYTES) {
			throw new RuntimeException(LanguageManager::t('The backup exceeds the upload size limit.'));
		}

		if ($error !== UPLOAD_ERR_OK || !is_string($upload['name'] ?? null)
				|| !preg_match('/\.ya?ml$/iD', $upload['name'])
				|| !is_string($upload['tmp_name'] ?? null) || !is_uploaded_file($upload['tmp_name'])) {
			throw new RuntimeException(LanguageManager::t('Upload a valid YAML backup file.'));
		}

		if (empty($upload['size'])) {
			throw new RuntimeException(LanguageManager::t('Upload a valid YAML backup file.'));
		}
		$stream = fopen($upload['tmp_name'], 'rb');
		if ($stream === false) {
			throw new RuntimeException(LanguageManager::t('Could not open a temporary backup file.'));
		}
		return $stream;
	}

	private static function previewScope(string $digest, bool $update, int $expires): string {
		return 'backuprestore.preview.'.$digest.'.'.(int) $update.'.'.$expires;
	}

	private function checkPreview($proof, string $digest, bool $update): void {
		if (!is_string($proof) || !preg_match('/^(\d{10})\.(.{1,128})$/D', $proof, $parts)
				|| (int) $parts[1] <= time() || (int) $parts[1] > time() + self::PREVIEW_VALID_SECONDS
				|| !\Modules\ZabbixBackupRestore\Lib\SecurityToken::check($parts[2], self::previewScope($digest, $update, (int) $parts[1]))) {
			throw new RuntimeException(LanguageManager::t('Preview this file and restore option again before restoring.'));
		}
	}

	private function translatedCounts(array $bundle): array {
		$labels = Catalog::labels(ZABBIX_VERSION) + ['triggers' => 'Triggers', 'graphs' => 'Graphs'];
		$result = [];
		foreach (Bundle::counts($bundle) as $type => $count) {
			$result[] = ['name' => LanguageManager::t($labels[$type]), 'count' => $count];
		}
		return $result;
	}

	private function notes(array $bundle): array {
		$notes = [];
		foreach ([
			'secret_macros' => '%d secret macro values must be configured on the destination.',
			'provisioned_users' => '%d externally provisioned users must be provisioned on the destination.'
		] as $key => $message) {
			if ($bundle['omissions'][$key]) {
				$notes[] = LanguageManager::format($message, $bundle['omissions'][$key]);
			}
		}
		if (!empty($bundle['records']['users'])) {
			$notes[] = LanguageManager::t('New local accounts require a password reset on the destination.');
		}
		return $notes;
	}

	private function json(array $data, int $status = 200): void {
		$body = json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
		$this->sendHeaders('application/json; charset=UTF-8', $status);
		echo $body;
		exit;
	}

	private function sendHeaders(string $type, int $status): void {
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_write_close();
		}

		http_response_code($status);
		header('Content-Type: '.$type);
		header('Cache-Control: no-store');
		header('X-Content-Type-Options: nosniff');
	}
}
