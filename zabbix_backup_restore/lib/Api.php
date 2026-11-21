<?php

namespace Modules\ZabbixBackupRestore\Lib;

use RuntimeException;

final class Api {

	public function call(string $service, string $method, array $parameters) {
		$result = \API::{$service}()->{$method}($parameters);
		if ($result === false || $result === null) {
			$messages = array_filter(\get_and_clear_messages(),
				static function (array $message): bool  { return empty($message['is_technical_error']); });
			$details = implode(' ', array_column($messages, 'message'));
			throw new RuntimeException(LanguageManager::format('Zabbix API call %s failed.', $service.'.'.$method)
				.($details !== '' ? ' '.$details : ''));
		}

		return $result;
	}
}
