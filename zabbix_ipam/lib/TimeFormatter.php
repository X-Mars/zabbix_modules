<?php

namespace Modules\ZabbixIpam\Lib;

/** Formats stored UTC timestamps in the timezone selected by the current Zabbix user. */
class TimeFormatter {
    public static function display($value): string {
        if ($value === null || $value === '') {
            return '';
        }

        $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);
        if ($timestamp === false || $timestamp <= 0) {
            return '';
        }

        if (function_exists('zbx_date2str')) {
            $format = defined('DATE_TIME_FORMAT_SECONDS') ? DATE_TIME_FORMAT_SECONDS : 'Y-m-d H:i:s';
            return (string) zbx_date2str($format, $timestamp);
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}
