<?php
namespace Modules\ZabbixDatabaseExport\Lib;

final class SecurityToken {
    public static function name(): string {
        return defined('CSRF_TOKEN_NAME') ? CSRF_TOKEN_NAME : 'sid';
    }
    public static function get(string $scope): string {
        if (class_exists('CCsrfTokenHelper')) {
            return \CCsrfTokenHelper::get($scope);
        }
        $session = (string) \CSessionHelper::getId();
        if (strpos($scope, '.preview.') === false) {
            return substr($session, 16, 16);
        }
        return hash_hmac('sha256', $scope, $session);
    }
    public static function check(string $token, string $scope): bool {
        return class_exists('CCsrfTokenHelper')
            ? \CCsrfTokenHelper::check($token, $scope)
            : hash_equals(self::get($scope), $token);
    }
}
