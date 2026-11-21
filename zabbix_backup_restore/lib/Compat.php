<?php
namespace Modules\ZabbixBackupRestore\Lib;

// Namespace-local shims preserve PHP 8 string semantics on PHP 7.3.
function str_starts_with(string $haystack, string $needle): bool {
    return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
}
function str_contains(string $haystack, string $needle): bool {
    return $needle === '' || strpos($haystack, $needle) !== false;
}
