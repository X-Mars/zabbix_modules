<?php

namespace Modules\ZabbixBackupRestore\Lib;

use RuntimeException;

final class Records {

	public const CHECK_FIELDS = 'type key_ ports snmp_community snmpv3_securityname snmpv3_securitylevel '
		.'snmpv3_authpassphrase snmpv3_privpassphrase uniq snmpv3_authprotocol snmpv3_privprotocol '
		.'snmpv3_contextname host_source name_source allow_redirect';

	private References $references;

	public function __construct(References $references) {
		$this->references = $references;
	}

	public static function fields(array $row, string $fields): array {
		return array_intersect_key($row, array_flip(explode(' ', $fields)));
	}

	private function rows(array $row, string $key, string $fields, ?callable $transform = null): array {
		$list = $row[$key] ?? [];
		if (!is_array($list) || count($list) !== count(array_filter($list, 'is_array'))) {
			throw new RuntimeException(LanguageManager::format('Invalid record in %s.', $key));
		}

		return array_map(static function (array $value) use ($fields, $transform): array {
			$value = self::fields($value, $fields);
			return $transform === null ? $value : $transform($value);
		}, array_values($list));
	}

	private function reference(array $row, string $field, string $type, bool $export, bool $optional = false): array {
		if (array_key_exists($field, $row)) {
			$row[$field] = $this->references->convert($type, $row[$field], $export, $optional);
		}

		return $row;
	}

	private function links(array $row, string $key, string $field, string $type, bool $export,
			string $extra = '', bool $optional = false): array {
		return $this->rows($row, $key, trim($field.' '.$extra), function (array $value) use ($field, $type, $export,
				$optional): array {
			return $this->reference($value, $field, $type, $export, $optional);
		});
	}

	public function normalize(string $type, array $row, bool $export, bool $upgrade = false): array {
		$data = self::fields($row, Catalog::RECORDS[$type][3]);

		switch ($type) {
			case 'regular_expressions':
				$substring_type = defined('REGEX_TYPE_CONTAINS_ANY_SUBSTRING')
					? REGEX_TYPE_CONTAINS_ANY_SUBSTRING : EXPRESSION_TYPE_ANY_INCLUDED;
				if (defined('REGEX_TYPE_CONTAINS_ANY_SUBSTRING')) {
					$data += self::fields($row, 'description');
				}
				$data['expressions'] = $this->rows($row, 'expressions',
					'expression expression_type exp_delimiter case_sensitive',
					static function (array $expression) use ($substring_type): array {
						if ((int) ($expression['expression_type'] ?? -1) !== $substring_type) {
							unset($expression['exp_delimiter']);
						}
						return $expression;
					});
				break;

			case 'proxies':
				$data = $this->reference($data, 'proxy_groupid', 'proxy_groups', $export, true);
				if (defined('PROXY_APM_DATA_COLLECTION_DISABLED')) {
					$data += self::fields($row, 'timeout_telemetry_query');
					if (isset($row['apm']) && is_array($row['apm'])) {
						$data['apm'] = self::fields($row['apm'], 'data_collection_status max_messages_per_second')
							+ ['additional_resource_attributes' => $this->rows($row['apm'],
								'additional_resource_attributes', 'key value signal_type')];
					}
				}
				break;

			case 'services':
				$data['tags'] = $this->rows($row, 'tags', 'tag value');
				$data['problem_tags'] = $this->rows($row, 'problem_tags', 'tag operator value');
				$data['status_rules'] = $this->rows($row, 'status_rules', 'type limit_value limit_status new_status');
				$data['children'] = $export ? $this->links($row, 'children', 'serviceid', 'services', true)
					: ($row['children'] ?? []);
				break;

			case 'slas':
				$data['service_tags'] = $this->rows($row, 'service_tags', 'tag operator value');
				$data['schedule'] = $this->rows($row, 'schedule', 'period_from period_to');
				$data['excluded_downtimes'] = $this->rows($row, 'excluded_downtimes', 'name period_from period_to');
				break;

			case 'roles':
				$data['rules'] = $this->roleRules($row['rules'] ?? [], $export, $upgrade, (int) ($data['type'] ?? -1));
				break;

			case 'user_groups':
				$data = $this->reference($data, 'userdirectoryid', 'user_directories', $export, true);
				$data = $this->reference($data, 'mfaid', 'mfa', $export, true);
				if (!in_array((int) ($data['gui_access'] ?? -1), [GROUP_GUI_ACCESS_SYSTEM, GROUP_GUI_ACCESS_LDAP],
						true)) {
					unset($data['userdirectoryid']);
				}
				$data['hostgroup_rights'] = $this->links($row, 'hostgroup_rights', 'id', 'host_groups',
					$export, 'permission');
				$data['templategroup_rights'] = $this->links($row, 'templategroup_rights', 'id', 'template_groups',
					$export, 'permission');
				$data['tag_filters'] = $this->links($row, 'tag_filters', 'groupid', 'host_groups', $export, 'tag value');
				if (defined('PROXY_MODE_ALLOW')) {
					$data += self::fields($row, 'proxy_mode proxy_group_mode');
					foreach (['proxies' => 'proxyid', 'proxy_groups' => 'proxy_groupid'] as $key => $id) {
						if (array_key_exists($key, $row)) {
							$data[$key] = $this->links($row, $key, $id, $key, $export);
						}
					}
				}
				break;

			case 'users':
				$data = $this->reference($data, 'roleid', 'roles', $export);
				$data['usrgrps'] = $this->links($row, 'usrgrps', 'usrgrpid', 'user_groups', $export);
				$data['medias'] = $this->rows($row, 'medias', 'mediatypeid sendto active severity period provisioned',
					function (array $media) use ($export): array {
						$media['sendto'] = is_string($media['sendto'] ?? null) ? [$media['sendto']]
							: ($media['sendto'] ?? []);
						unset($media['provisioned']);
						return $this->reference($media, 'mediatypeid', 'media_types', $export);
					});
				break;

			case 'scripts':
				$data = $this->reference($data, 'usrgrpid', 'user_groups', $export, true);
				$data = $this->reference($data, 'groupid', 'host_groups', $export, true);
				$data['parameters'] = $this->rows($row, 'parameters', 'name value');
				break;

			case 'network_discovery':
				$data = $this->reference($data, 'proxyid', 'proxies', $export, true);
				$data['dchecks'] = $this->rows($row, 'dchecks', self::CHECK_FIELDS);
				break;

			case 'maintenance':
				$data['groups'] = $this->links($row, $export ? 'hostgroups' : 'groups', 'groupid', 'host_groups',
					$export);
				$data['hosts'] = $this->links($row, 'hosts', 'hostid', 'hosts', $export);
				$data['timeperiods'] = $this->rows($row, 'timeperiods',
					'period timeperiod_type start_date start_time every day dayofweek month', [self::class, 'timePeriod']);
				if ((int) ($data['maintenance_type'] ?? -1) === MAINTENANCE_TYPE_NORMAL) {
					$data['tags'] = $this->rows($row, 'tags', 'tag operator value');
				}
				else {
					unset($data['tags_evaltype']);
				}
				break;

			case 'event_correlations':
				$data['filter'] = $this->filter($row['filter'] ?? [], $export, true);
				$data['operations'] = $this->rows($row, 'operations', 'type');
				break;

			case 'actions':
				$data = $this->action($row, $data, $export);
				break;
		}

		return $data;
	}

	private function roleRules(array $row, bool $export, bool $upgrade, int $type): array {
		$rules = self::fields($row, 'ui.default_access services.read.mode services.write.mode modules.default_access '
			.'api.access api.mode actions.default_access profile.redirect.enforce profile.redirect.url devices.access '
			.'devices.actions.default_access');
		foreach (['ui', 'actions', 'devices.actions'] as $key) {
			if (isset($row[$key])) {
				$rules[$key] = $this->rows($row, $key, 'name status');
			}
		}
		foreach (['services.read', 'services.write'] as $key) {
			if (isset($row[$key.'.list'])) {
				$rules[$key.'.list'] = $this->links($row, $key.'.list', 'serviceid', 'services', $export);
			}
			if (isset($row[$key.'.tag'])) {
				$rules[$key.'.tag'] = self::fields($row[$key.'.tag'], 'tag value');
			}
		}
		if (isset($row['modules'])) {
			if (!$export && is_array($row['modules'])) {
				$default = (int) ($row['modules.default_access'] ?? ZBX_ROLE_RULE_ENABLED);
				$row['modules'] = array_filter($row['modules'], function (array $module) use ($default, $upgrade): bool {
					if ($upgrade && ($module['moduleid'] ?? null) === 'dataover') {
						return false;
					}
					return (int) ($module['status'] ?? -1) !== $default
						|| $this->references->has('modules', $module['moduleid'] ?? null);
				});
			}
			$rules['modules'] = $this->links($row, 'modules', 'moduleid', 'modules', $export, 'status');
		}
		if (isset($row['api'])) {
			$rules['api'] = array_values($row['api']);
		}

		if ($upgrade) {
			$allowed = ['ui' => \CRoleHelper::getUiElementsByUserType($type),
				'actions' => \CRoleHelper::getActionsByUserType($type)];
			foreach ($allowed as $key => $rules_available) {
				if (isset($rules[$key])) {
					$rules[$key] = array_values(array_filter($rules[$key],
						static fn(array $rule): bool => in_array($key.'.'.$rule['name'], $rules_available, true)));
				}
			}
		}

		return $rules;
	}

	private static function timePeriod(array $period): array {
		$fields = match ((int) ($period['timeperiod_type'] ?? -1)) {
			TIMEPERIOD_TYPE_ONETIME => 'start_date',
			TIMEPERIOD_TYPE_DAILY => 'start_time every',
			TIMEPERIOD_TYPE_WEEKLY => 'start_time every dayofweek',
			TIMEPERIOD_TYPE_MONTHLY => 'start_time every day dayofweek month',
			default => ''
		};

		return self::fields($period, 'period timeperiod_type '.$fields);
	}

	private function filter(array $filter, bool $export, bool $correlation = false): array {
		$data = self::fields($filter, 'evaltype formula');
		$custom = (int) ($data['evaltype'] ?? -1) === CONDITION_EVAL_TYPE_EXPRESSION;
		if (!$custom) {
			unset($data['formula']);
		}

		$fields = $correlation ? 'type operator tag groupid oldtag newtag value' : 'conditiontype operator value value2';
		if ($custom) {
			$fields .= ' formulaid';
		}
		$data['conditions'] = $this->rows($filter, 'conditions', $fields,
			function (array $condition) use ($export, $correlation): array {
				if ($correlation) {
					return $this->reference($condition, 'groupid', 'host_groups', $export);
				}

				$type = (int) ($condition['conditiontype'] ?? -1);
				$references = [
					ZBX_CONDITION_TYPE_HOST_GROUP => 'host_groups',
					ZBX_CONDITION_TYPE_HOST => 'hosts',
					ZBX_CONDITION_TYPE_TEMPLATE => 'templates',
					ZBX_CONDITION_TYPE_TRIGGER => 'triggers',
					ZBX_CONDITION_TYPE_DRULE => 'network_discovery',
					ZBX_CONDITION_TYPE_DCHECK => 'discovery_checks',
					ZBX_CONDITION_TYPE_PROXY => 'proxies',
					ZBX_CONDITION_TYPE_SERVICE => 'services'
				];
				if (defined('ZBX_CONDITION_TYPE_PROXY_GROUP')) {
					$references[ZBX_CONDITION_TYPE_PROXY_GROUP] = 'proxy_groups';
				}
				$reference = $references[$type] ?? null;
				if ($type !== ZBX_CONDITION_TYPE_EVENT_TAG_VALUE) {
					unset($condition['value2']);
				}
				return $reference === null ? $condition : $this->reference($condition, 'value', $reference, $export);
			});

		return $data;
	}

	private function action(array $row, array $data, bool $export): array {
		$event = (int) ($data['eventsource'] ?? -1);
		$data['filter'] = $this->filter($row['filter'] ?? [], $export);
		if ($event !== EVENT_SOURCE_TRIGGERS) {
			unset($data['pause_symptoms'], $data['pause_suppressed'], $data['notify_if_canceled']);
		}
		$escalation = in_array($event, [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE], true);
		if (!$escalation) {
			unset($data['esc_period']);
		}

		$phases = ['operations'];
		if ($escalation) {
			$phases[] = 'recovery_operations';
		}
		if (in_array($event, [EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_SERVICE], true)) {
			$phases[] = 'update_operations';
		}

		$fields = 'operationtype opmessage opmessage_grp opmessage_usr opcommand opcommand_grp opcommand_hst '
			.'opgroup optemplate opinventory optag';
		foreach ($phases as $phase) {
			$normal = $phase === 'operations';
			$phase_fields = $fields;
			if ($normal && $escalation) {
				$phase_fields .= ' esc_period esc_step_from esc_step_to';
			}
			if ($normal && $event === EVENT_SOURCE_TRIGGERS) {
				$phase_fields .= ' evaltype opconditions';
			}
			$data[$phase] = $this->rows($row, $phase, $phase_fields,
				fn(array $operation): array => $this->operation($operation, $export));
		}

		return $data;
	}

	private function operation(array $operation, bool $export): array {
		foreach ([
			'opmessage_grp' => ['usrgrpid', 'user_groups'],
			'opmessage_usr' => ['userid', 'users'],
			'opcommand_grp' => ['groupid', 'host_groups'],
			'opcommand_hst' => ['hostid', 'hosts'],
			'opgroup' => ['groupid', 'host_groups'],
			'optemplate' => ['templateid', 'templates']
		] as $key => [$field, $type]) {
			if (isset($operation[$key])) {
				$operation[$key] = $this->links($operation, $key, $field, $type, $export, '', $key === 'opcommand_hst');
			}
		}

		if (isset($operation['opcommand'])) {
			$operation['opcommand'] = $this->reference(self::fields($operation['opcommand'], 'scriptid'),
				'scriptid', 'scripts', $export);
		}
		if (isset($operation['opmessage'])) {
			$message = self::fields($operation['opmessage'], 'default_msg subject message mediatypeid');
			if ((int) ($operation['operationtype'] ?? -1) === OPERATION_TYPE_RECOVERY_MESSAGE) {
				unset($message['mediatypeid']);
			}
			if ((int) ($message['default_msg'] ?? 1) === 1) {
				unset($message['subject'], $message['message']);
			}
			$operation['opmessage'] = $this->reference($message, 'mediatypeid', 'media_types', $export, true);
		}
		if (isset($operation['opinventory'])) {
			$operation['opinventory'] = self::fields($operation['opinventory'], 'inventory_mode');
		}
		foreach (['optag' => 'tag value', 'opconditions' => 'conditiontype value operator'] as $key => $fields) {
			if (isset($operation[$key])) {
				$operation[$key] = $this->rows($operation, $key, $fields);
			}
		}

		return $operation;
	}

	public function serviceChildren(array $row): array {
		return $this->links($row, 'children', 'serviceid', 'services', false);
	}

	public function matchDiscoveryChecks(array $data, array $existing): array {
		$checks = [];
		foreach ($existing['dchecks'] as $check) {
			$key = References::key(References::checkIdentity($data['name'], $check));
			$checks[$key][] = $check['dcheckid'];
		}
		foreach ($data['dchecks'] as &$check) {
			$key = References::key(References::checkIdentity($data['name'], $check));
			if (count($checks[$key] ?? []) === 1) {
				$check['dcheckid'] = $checks[$key][0];
			}
		}
		unset($check);
		return $data;
	}
}
