<?php

namespace Modules\ZabbixSnmp\Lib;

class ZabbixVersion {

    private static $version = null;
    private static $isVersion6 = null;
    private static $isVersion7 = null;

    public static function detect(): string {
        if (self::$version !== null) {
            return self::$version;
        }

        if (class_exists('Zabbix\Core\CModule')) {
            self::$version = '7.0';
            self::$isVersion7 = true;
            self::$isVersion6 = false;
            return self::$version;
        }

        if (class_exists('Core\CModule')) {
            self::$version = '6.0';
            self::$isVersion6 = true;
            self::$isVersion7 = false;
            return self::$version;
        }

        if (defined('ZABBIX_VERSION')) {
            $version = ZABBIX_VERSION;
            if (version_compare($version, '7.0', '>=')) {
                self::$version = '7.0';
                self::$isVersion7 = true;
                self::$isVersion6 = false;
            } else {
                self::$version = '6.0';
                self::$isVersion6 = true;
                self::$isVersion7 = false;
            }

            return self::$version;
        }

        self::$version = '6.0';
        self::$isVersion6 = true;
        self::$isVersion7 = false;
        return self::$version;
    }

    public static function isVersion6(): bool {
        if (self::$isVersion6 === null) {
            self::detect();
        }

        return self::$isVersion6;
    }

    public static function isVersion7(): bool {
        if (self::$isVersion7 === null) {
            self::detect();
        }

        return self::$isVersion7;
    }
}