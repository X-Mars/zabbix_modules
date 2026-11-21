<?php

namespace Modules\ZabbixBackupRestore\Lib;

final class Catalog {

	// Selection key => API service, object ID, native export option, native YAML field.
	public const NATIVE = [
		'host_groups' => ['HostGroup', 'groupid', 'host_groups', 'host_groups'],
		'template_groups' => ['TemplateGroup', 'groupid', 'template_groups', 'template_groups'],
		'templates' => ['Template', 'templateid', 'templates', 'templates'],
		'hosts' => ['Host', 'hostid', 'hosts', 'hosts'],
		'media_types' => ['MediaType', 'mediatypeid', 'mediaTypes', 'media_types'],
		'images' => ['Image', 'imageid', 'images', 'images'],
		'maps' => ['Map', 'sysmapid', 'maps', 'maps'],
		'dashboards' => ['Dashboard', 'dashboardid', 'dashboards', 'dashboards']
	];

	// Object identity is independent of the database ID. Phases are dependency ordered.
	public const RECORDS = [
		'global_macros' => ['UserMacro', 'globalmacroid', 'macro', 'macro type value description', ['globalmacro' => true]],
		'regular_expressions' => ['Regexp', 'regexpid', 'name', 'name test_string', ['selectExpressions' => 'extend']],
		'proxy_groups' => ['ProxyGroup', 'proxy_groupid', 'name', 'name failover_delay min_online description', []],
		'proxies' => ['Proxy', 'proxyid', 'name',
			'name proxy_groupid local_address local_port operating_mode allowed_addresses address port description '
			.'tls_connect tls_accept tls_issuer tls_subject tls_psk_identity tls_psk custom_timeouts timeout_zabbix_agent '
			.'timeout_simple_check timeout_snmp_agent timeout_external_check timeout_db_monitor timeout_http_agent '
			.'timeout_ssh_agent timeout_telnet_agent timeout_script timeout_browser', []],
		'services' => ['Service', 'serviceid', 'uuid',
			'uuid name algorithm sortorder weight propagation_rule propagation_value description',
			['selectChildren' => ['serviceid'], 'selectTags' => 'extend', 'selectProblemTags' => 'extend',
				'selectStatusRules' => 'extend']],
		'slas' => ['Sla', 'slaid', 'name', 'name period slo effective_date timezone status description',
			['selectServiceTags' => 'extend', 'selectSchedule' => 'extend', 'selectExcludedDowntimes' => 'extend']],
		'roles' => ['Role', 'roleid', 'name', 'name type', ['selectRules' => 'extend']],
		'user_groups' => ['UserGroup', 'usrgrpid', 'name',
			'name gui_access users_status debug_mode userdirectoryid mfa_status mfaid',
			['selectHostGroupRights' => 'extend', 'selectTemplateGroupRights' => 'extend', 'selectTagFilters' => 'extend']],
		'users' => ['User', 'userid', 'username',
			'username name surname url autologin autologout lang refresh theme rows_per_page timezone roleid',
			['selectUsrgrps' => ['usrgrpid'], 'selectMedias' => 'extend']],
		'scripts' => ['Script', 'scriptid', 'name',
			'name command host_access usrgrpid groupid description confirmation type execute_on timeout scope menu_path '
			.'port authtype username password publickey privatekey url new_window manualinput manualinput_prompt '
			.'manualinput_validator_type manualinput_validator manualinput_default_value', []],
		'network_discovery' => ['DRule', 'druleid', 'name', 'name iprange delay status concurrency_max proxyid',
			['selectDChecks' => 'extend']],
		'maintenance' => ['Maintenance', 'maintenanceid', 'name',
			'name maintenance_type description active_since active_till tags_evaltype',
			['selectHosts' => ['hostid'], 'selectHostGroups' => ['groupid'], 'selectTags' => 'extend',
				'selectTimeperiods' => 'extend']],
		'event_correlations' => ['Correlation', 'correlationid', 'name', 'name description status',
			['selectFilter' => 'extend', 'selectOperations' => 'extend']],
		'actions' => ['Action', 'actionid', 'name',
			'name eventsource status esc_period pause_symptoms pause_suppressed notify_if_canceled',
			['selectFilter' => 'extend', 'selectOperations' => 'extend', 'selectRecoveryOperations' => 'extend',
				'selectUpdateOperations' => 'extend']]
	];

	public const GROUPS = [
		'Monitoring' => [
			'hosts' => 'Hosts',
			'templates' => 'Templates',
			'host_groups' => 'Host groups',
			'template_groups' => 'Template groups',
			'network_discovery' => 'Network discovery rules',
			'maintenance' => 'Maintenance periods',
			'proxies' => 'Proxies',
			'proxy_groups' => 'Proxy groups',
			'maps' => 'Maps',
			'images' => 'Images',
			'dashboards' => 'Dashboards'
		],
		'Access and notifications' => [
			'users' => 'Users',
			'user_groups' => 'User groups',
			'roles' => 'User roles',
			'media_types' => 'Media types',
			'actions' => 'Actions',
			'scripts' => 'Scripts'
		],
		'Services and shared configuration' => [
			'services' => 'Services and dependencies',
			'slas' => 'Service-level agreements',
			'global_macros' => 'Global macros',
			'regular_expressions' => 'Global regular expressions',
			'event_correlations' => 'Event correlations'
		]
	];

	public const EARLY = ['global_macros', 'regular_expressions', 'proxy_groups', 'proxies'];
	public const LATE_NATIVE = ['images', 'maps', 'dashboards'];

	public static function groups(string $version): array {
		if (version_compare($version, '6.4', '<')) {
			return ['Monitoring' => [
				'host_groups' => 'Host groups', 'templates' => 'Templates', 'hosts' => 'Hosts',
				'media_types' => 'Media types', 'images' => 'Images', 'maps' => 'Maps'
			]];
		}
		$groups = self::GROUPS;
		if (substr($version, 0, 3) !== '8.0') {
			unset($groups['Monitoring']['dashboards']);
		}

		return $groups;
	}

	public static function labels(string $version): array {
		return array_merge(...array_values(self::groups($version)));
	}

	public static function native(string $version): array {
		$native = self::NATIVE;
		if (version_compare($version, '6.4', '<')) {
			$native['host_groups'][2] = 'groups';
			$native['host_groups'][3] = 'groups';
		}
		return $native;
	}
}
