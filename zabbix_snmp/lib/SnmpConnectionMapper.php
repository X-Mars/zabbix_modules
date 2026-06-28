<?php

namespace Modules\ZabbixSnmp\Lib;

/**
 * 将 Zabbix Host Interface SNMPv3 字段规范化为 net-snmp 可识别的字符串。
 *
 * Zabbix 7.x API 返回 securitylevel / authprotocol / privprotocol 为整型枚举；
 * Zabbix 6.x 或手动连接模式可能已是字符串。本类兼容两种输入。
 *
 * @see https://www.zabbix.com/documentation/7.0/en/manual/api/reference/hostinterface/object
 */
class SnmpConnectionMapper {

    private const SECURITY_LEVELS = [
        0 => 'noAuthNoPriv',
        1 => 'authNoPriv',
        2 => 'authPriv',
    ];

    private const AUTH_PROTOCOLS = [
        0 => 'MD5',
        1 => 'SHA1',
        2 => 'SHA224',
        3 => 'SHA256',
        4 => 'SHA384',
        5 => 'SHA512',
    ];

    private const PRIV_PROTOCOLS = [
        0 => 'DES',
        1 => 'AES128',
        2 => 'AES192',
        3 => 'AES256',
        4 => 'AES192C',
        5 => 'AES256C',
    ];

    /** net-snmp -a 常用名（Zabbix API 为 SHA1） */
    private const AUTH_NETSNMP = [
        'SHA1' => 'SHA',
    ];

    /** net-snmp -x 常用名（Zabbix API 为 AES128） */
    private const PRIV_NETSNMP = [
        'AES128' => 'AES',
    ];

    public static function normalizeSecurityLevel($value): string {
        if (is_int($value) || (is_string($value) && $value !== '' && ctype_digit($value))) {
            $key = (int) $value;
            if (isset(self::SECURITY_LEVELS[$key])) {
                return self::SECURITY_LEVELS[$key];
            }
        }

        $normalized = self::normalizeString($value);
        if ($normalized === '') {
            return 'noAuthNoPriv';
        }

        $lower = strtolower($normalized);
        foreach (self::SECURITY_LEVELS as $name) {
            if (strtolower($name) === $lower) {
                return $name;
            }
        }

        return $normalized;
    }

    public static function normalizeAuthProtocol($value): string {
        if (is_int($value) || (is_string($value) && $value !== '' && ctype_digit($value))) {
            $key = (int) $value;
            if (isset(self::AUTH_PROTOCOLS[$key])) {
                return self::toNetSnmpAuth(self::AUTH_PROTOCOLS[$key]);
            }
        }

        $normalized = strtoupper(self::normalizeString($value));
        if ($normalized === '') {
            return 'SHA';
        }

        foreach (self::AUTH_PROTOCOLS as $name) {
            if (strtoupper($name) === $normalized) {
                return self::toNetSnmpAuth($name);
            }
        }

        return self::toNetSnmpAuth($normalized);
    }

    public static function normalizePrivProtocol($value): string {
        if (is_int($value) || (is_string($value) && $value !== '' && ctype_digit($value))) {
            $key = (int) $value;
            if (isset(self::PRIV_PROTOCOLS[$key])) {
                return self::toNetSnmpPriv(self::PRIV_PROTOCOLS[$key]);
            }
        }

        $normalized = strtoupper(self::normalizeString($value));
        if ($normalized === '') {
            return 'AES';
        }

        foreach (self::PRIV_PROTOCOLS as $name) {
            if (strtoupper($name) === $normalized) {
                return self::toNetSnmpPriv($name);
            }
        }

        // 手动模式常见简写 DES / AES
        if ($normalized === 'AES') {
            return 'AES';
        }

        return self::toNetSnmpPriv($normalized);
    }

    private static function normalizeString($value): string {
        return trim((string) $value);
    }

    private static function toNetSnmpAuth(string $protocol): string {
        $upper = strtoupper($protocol);
        return self::AUTH_NETSNMP[$upper] ?? $upper;
    }

    private static function toNetSnmpPriv(string $protocol): string {
        $upper = strtoupper($protocol);
        return self::PRIV_NETSNMP[$upper] ?? $upper;
    }
}
