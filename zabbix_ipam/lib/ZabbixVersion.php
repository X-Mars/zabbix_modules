<?php
namespace Modules\ZabbixIpam\Lib;
class ZabbixVersion {
    public static function isModern(): bool { return class_exists('Zabbix\\Core\\CModule') || (defined('ZABBIX_VERSION') && version_compare(ZABBIX_VERSION, '7.0', '>=')); }
    public static function csrfMethod(): string { return self::isModern() ? 'disableCsrfValidation' : 'disableSIDvalidation'; }
}
