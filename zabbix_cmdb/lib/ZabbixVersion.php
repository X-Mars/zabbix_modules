<?php

namespace Modules\ZabbixCmdb\Lib;

/**
 * Zabbix版本检测和兼容性工具类
 * 自动检测Zabbix版本并提供统一的API接口
 */
class ZabbixVersion {
    
    private static $version = null;
    private static $isVersion6 = null;
    private static $isVersion7 = null;
    
    /**
     * 检测Zabbix版本
     */
    public static function detect(): string {
        if (self::$version !== null) {
            return self::$version;
        }
        
        // 方法1: 检查命名空间是否存在
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
        
        // 方法2: 检查常量
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
        
        // 默认假设为6.0
        self::$version = '6.0';
        self::$isVersion6 = true;
        self::$isVersion7 = false;
        return self::$version;
    }
    
    /**
     * 判断是否为Zabbix 6.0
     */
    public static function isVersion6(): bool {
        if (self::$isVersion6 === null) {
            self::detect();
        }
        return self::$isVersion6;
    }
    
    /**
     * 判断是否为Zabbix 7.0+
     */
    public static function isVersion7(): bool {
        if (self::$isVersion7 === null) {
            self::detect();
        }
        return self::$isVersion7;
    }
    
    /**
     * 获取CModule基类名称
     */
    public static function getModuleBaseClass(): string {
        if (self::isVersion7()) {
            return '\Zabbix\Core\CModule';
        }
        return '\Core\CModule';
    }
    
    /**
     * 获取禁用CSRF验证的方法名
     */
    public static function getDisableCsrfMethod(): string {
        if (self::isVersion7()) {
            return 'disableCsrfValidation';
        }
        return 'disableSIDvalidation';
    }
}
