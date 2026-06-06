<?php

namespace Modules\ZabbixSnmp\Lib;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';

class MibRepository {
    private const PREVIEW_LINES = 500;

    private const COMMON_DIRS = [
        '/usr/share/snmp/mibs',
        '/usr/local/share/snmp/mibs',
        '/usr/share/mibs',
        '/usr/local/share/mibs',
        '/var/lib/mibs',
        '/usr/share/net-snmp/mibs',
        '/opt/share/snmp/mibs',
        '/opt/local/share/snmp/mibs',
        'C:\\usr\\share\\snmp\\mibs',
        'C:\\usr\\local\\share\\snmp\\mibs',
        'C:\\net-snmp\\share\\snmp\\mibs',
        'C:\\Program Files\\Net-SNMP\\share\\snmp\\mibs',
        'C:\\Program Files (x86)\\Net-SNMP\\share\\snmp\\mibs'
    ];

    public static function getDirectories(string $search = ''): array {
        $directories = [];

        foreach (self::getCandidateDirectories() as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $files = self::scanDirectory($directory, $search);
            if (empty($files)) {
                continue;
            }

            $directories[] = [
                'path' => $directory,
                'file_count' => count($files),
                'files' => $files
            ];
        }

        return $directories;
    }

    public static function resolveSelectedDirectory(string $requestedDirectory, array $directories): string {
        if ($requestedDirectory !== '') {
            foreach ($directories as $directory) {
                if ($directory['path'] === $requestedDirectory) {
                    return $requestedDirectory;
                }
            }
        }

        return !empty($directories) ? $directories[0]['path'] : '';
    }

    public static function getFilesInDirectory(string $selectedDirectory, array $directories): array {
        if ($selectedDirectory === '') {
            return [];
        }

        foreach ($directories as $directory) {
            if ($directory['path'] === $selectedDirectory) {
                return $directory['files'];
            }
        }

        return [];
    }

    public static function getStats(array $directories, array $files): array {
        $totalFiles = 0;
        foreach ($directories as $directory) {
            $totalFiles += $directory['file_count'] ?? 0;
        }

        return [
            'directories' => count($directories),
            'total_files' => $totalFiles,
            'current_directory_files' => count($files)
        ];
    }

    public static function getFileDetails(
        string $requestedFile,
        array $files,
        bool $includeSource = false,
        string $symbol = ''
    ): ?array {
        if ($requestedFile === '') {
            return null;
        }

        $realFile = realpath($requestedFile);
        if ($realFile === false) {
            return null;
        }

        $allowedFiles = self::getAllowedFiles($files);
        if (!isset($allowedFiles[$realFile])) {
            return null;
        }

        $content = @file_get_contents($realFile);
        if ($content === false) {
            return [
                'path' => $realFile,
                'name' => basename($realFile),
                'relative_path' => $allowedFiles[$realFile]['relative_path'],
                'directory' => $allowedFiles[$realFile]['directory'],
                'extension' => $allowedFiles[$realFile]['extension'],
                'size' => self::formatBytes((int) @filesize($realFile)),
                'modified_at' => self::formatTimestamp(@filemtime($realFile)),
                'line_count' => 0,
                'preview_lines' => 0,
                'preview' => '',
                'truncated' => false,
                'read_error' => true,
                'snmp_objects' => []
            ];
        }

        $normalizedContent = str_replace(["\r\n", "\r"], "\n", $content);
        $lines = explode("\n", $normalizedContent);
        $lineCount = count($lines);
        $previewLines = array_slice($lines, 0, self::PREVIEW_LINES);
        $snmpObjects = self::parseSnmpObjects($lines);

        $sourceView = null;
        if ($includeSource) {
            $targetLine = self::resolveSymbolLine($snmpObjects, $symbol);
            $sourceView = self::buildSourceView($lines, $targetLine);
        }

        return [
            'path' => $realFile,
            'name' => basename($realFile),
            'relative_path' => $allowedFiles[$realFile]['relative_path'],
            'directory' => $allowedFiles[$realFile]['directory'],
            'extension' => $allowedFiles[$realFile]['extension'],
            'size' => self::formatBytes((int) @filesize($realFile)),
            'modified_at' => self::formatTimestamp(@filemtime($realFile)),
            'line_count' => $lineCount,
            'preview_lines' => min($lineCount, self::PREVIEW_LINES),
            'preview' => implode("\n", $previewLines),
            'truncated' => $lineCount > self::PREVIEW_LINES,
            'read_error' => false,
            'snmp_objects' => $snmpObjects,
            'source_view' => $sourceView,
            'selected_symbol' => $symbol
        ];
    }

    private static function getAllowedFiles(array $files): array {
        $allowedFiles = [];

        foreach ($files as $file) {
            $allowedFiles[$file['path']] = $file;
        }

        return $allowedFiles;
    }

    private static function getCandidateDirectories(): array {
        $directories = [];
        $envDirs = getenv('MIBDIRS');

        if ($envDirs !== false && $envDirs !== '') {
            foreach (explode(PATH_SEPARATOR, $envDirs) as $dir) {
                $dir = trim($dir);
                if ($dir !== '') {
                    $directories[] = $dir;
                }
            }
        }

        $directories = array_merge($directories, self::COMMON_DIRS);

        $uniqueDirectories = [];
        $seen = [];

        foreach ($directories as $directory) {
            $normalized = rtrim(trim($directory), '\\/');
            if ($normalized === '') {
                continue;
            }

            $key = strtoupper(str_replace('\\', '/', $normalized));
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $uniqueDirectories[] = $normalized;
        }

        return $uniqueDirectories;
    }

    private static function scanDirectory(string $directory, string $search = ''): array {
        $files = [];
        $root = realpath($directory);
        if ($root === false) {
            return [];
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile() || !$fileInfo->isReadable()) {
                    continue;
                }

                if (!self::isMibFile($fileInfo)) {
                    continue;
                }

                $path = $fileInfo->getRealPath();
                if ($path === false) {
                    continue;
                }

                if (strpos($path, $root) !== 0) {
                    continue;
                }

                $name = $fileInfo->getFilename();
                $relativePath = ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
                $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                if ($search !== '' && stripos($name, $search) === false && stripos($relativePath, $search) === false) {
                    continue;
                }

                $files[] = [
                    'name' => $name,
                    'path' => $path,
                    'relative_path' => $relativePath !== '' ? $relativePath : $name,
                    'directory' => $directory,
                    'extension' => $extension !== '' ? $extension : '-',
                    'size' => self::formatBytes((int) $fileInfo->getSize()),
                    'modified_at' => self::formatTimestamp($fileInfo->getMTime())
                ];
            }
        } catch (\Throwable $e) {
            return [];
        }

        usort($files, function (array $left, array $right): int {
            return strcasecmp($left['relative_path'], $right['relative_path']);
        });

        return $files;
    }

    private static function parseSnmpObjects(array $lines): array {
        $objects = [];
        $lineCount = count($lines);

        $blockStartRegex = '/^\s*([A-Za-z][A-Za-z0-9-]*)\s+'
            . '(OBJECT-TYPE|MODULE-IDENTITY|OBJECT-IDENTITY|NOTIFICATION-TYPE|TRAP-TYPE|'
            . 'TEXTUAL-CONVENTION|OBJECT-GROUP|NOTIFICATION-GROUP|MODULE-COMPLIANCE|AGENT-CAPABILITIES)\b/';
        $oidOnlyRegex = '/^\s*([A-Za-z][A-Za-z0-9-]*)\s+OBJECT\s+IDENTIFIER\s+::=\s*\{\s*([^}]+)\s*\}/';

        for ($index = 0; $index < $lineCount; $index++) {
            $line = $lines[$index];

            if (preg_match($blockStartRegex, $line, $startMatch) === 1) {
                $name = $startMatch[1];
                $kind = $startMatch[2];
                $startLine = $index + 1;

                $blockLines = [$line];
                $endLine = $startLine;

                for ($cursor = $index + 1; $cursor < $lineCount; $cursor++) {
                    $blockLines[] = $lines[$cursor];
                    $endLine = $cursor + 1;
                    if (strpos($lines[$cursor], '::=') !== false) {
                        $index = $cursor;
                        break;
                    }
                }

                $block = implode("\n", $blockLines);
                $oid = self::extractValue($block, '/::=\s*\{\s*([^}]+)\s*\}/');
                $syntax = self::extractValue($block, '/\bSYNTAX\s+([^\n\r]+)/');
                $access = self::extractValue($block, '/\b(?:MAX-ACCESS|ACCESS)\s+([^\n\r]+)/');
                $status = self::extractValue($block, '/\bSTATUS\s+([^\n\r]+)/');
                $description = self::extractDescription($block);

                $objects[] = [
                    'name' => $name,
                    'kind' => $kind,
                    'oid' => $oid !== '' ? $oid : '-',
                    'syntax' => $syntax !== '' ? trim($syntax) : '-',
                    'access' => $access !== '' ? trim($access) : '-',
                    'status' => $status !== '' ? trim($status) : '-',
                    'description' => $description,
                    'start_line' => $startLine,
                    'end_line' => $endLine
                ];

                continue;
            }

            if (preg_match($oidOnlyRegex, $line, $oidMatch) === 1) {
                $objects[] = [
                    'name' => $oidMatch[1],
                    'kind' => 'OBJECT IDENTIFIER',
                    'oid' => trim($oidMatch[2]),
                    'syntax' => '-',
                    'access' => '-',
                    'status' => '-',
                    'description' => '-',
                    'start_line' => $index + 1,
                    'end_line' => $index + 1
                ];
            }
        }

        usort($objects, function (array $left, array $right): int {
            return $left['start_line'] <=> $right['start_line'];
        });

        return self::resolveParsedObjectOids($objects);
    }

    private static function resolveParsedObjectOids(array $objects): array {
        $objectMap = self::buildObjectMap($objects);

        foreach ($objects as $index => $object) {
            $resolved = self::resolveOidValue((string) ($object['oid'] ?? ''), $objectMap, []);
            $objects[$index]['oid_numeric'] = $resolved ?? '-';
        }

        return $objects;
    }

    private static function buildObjectMap(array $objects): array {
        $map = [
            'iso' => '1',
            'org' => '1.3',
            'dod' => '1.3.6',
            'internet' => '1.3.6.1',
            'directory' => '1.3.6.1.1',
            'mgmt' => '1.3.6.1.2',
            'mib-2' => '1.3.6.1.2.1',
            'transmission' => '1.3.6.1.2.1.10',
            'experimental' => '1.3.6.1.3',
            'private' => '1.3.6.1.4',
            'enterprises' => '1.3.6.1.4.1'
        ];

        foreach ($objects as $object) {
            $name = (string) ($object['name'] ?? '');
            $oid = trim((string) ($object['oid'] ?? ''));
            if ($name !== '' && $oid !== '' && $oid !== '-') {
                $map[$name] = $oid;
            }
        }

        return $map;
    }

    private static function resolveOidValue(string $oid, array $objectMap, array $stack): ?string {
        $oid = trim($oid);
        if ($oid === '') {
            return null;
        }

        if (preg_match('/^\.?\d+(?:\.\d+)*$/', $oid) === 1) {
            return ltrim($oid, '.');
        }

        $parts = preg_split('/\s+/', $oid, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts) || empty($parts)) {
            return null;
        }

        $head = array_shift($parts);
        $base = null;

        if (preg_match('/^\.?\d+(?:\.\d+)*$/', $head) === 1) {
            $base = ltrim($head, '.');
        } elseif (isset($objectMap[$head])) {
            if (in_array($head, $stack, true)) {
                return null;
            }

            $nextStack = $stack;
            $nextStack[] = $head;
            $base = self::resolveOidValue($objectMap[$head], $objectMap, $nextStack);
        } else {
            return null;
        }

        if ($base === null || $base === '') {
            return null;
        }

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (preg_match('/^\d+(?:\.\d+)*$/', $part) === 1) {
                $base .= '.' . $part;
                continue;
            }

            if (isset($objectMap[$part])) {
                $resolved = self::resolveOidValue($objectMap[$part], $objectMap, $stack);
                if ($resolved === null) {
                    return null;
                }
                $base .= '.' . $resolved;
                continue;
            }

            return null;
        }

        return $base;
    }

    private static function extractValue(string $text, string $pattern): string {
        if (preg_match($pattern, $text, $match) === 1) {
            return trim((string) $match[1]);
        }

        return '';
    }

    private static function extractDescription(string $text): string {
        if (preg_match('/\bDESCRIPTION\s+"([\s\S]*?)"/m', $text, $match) === 1) {
            $value = trim(preg_replace('/\s+/', ' ', (string) $match[1]));
            if ($value !== '') {
                return mb_substr($value, 0, 180);
            }
        }

        return '-';
    }

    private static function resolveSymbolLine(array $objects, string $symbol): int {
        if ($symbol === '') {
            return 1;
        }

        foreach ($objects as $object) {
            if (strcasecmp($object['name'], $symbol) === 0) {
                return (int) $object['start_line'];
            }
        }

        return 1;
    }

    private static function buildSourceView(array $lines, int $targetLine): array {
        $lineCount = count($lines);
        if ($lineCount === 0) {
            return [
                'start_line' => 0,
                'end_line' => 0,
                'content' => '',
                'truncated' => false
            ];
        }

        $start = max(1, $targetLine - 120);
        $end = min($lineCount, $start + self::PREVIEW_LINES - 1);
        if ($end - $start + 1 < self::PREVIEW_LINES) {
            $start = max(1, $end - self::PREVIEW_LINES + 1);
        }

        $slice = array_slice($lines, $start - 1, $end - $start + 1);
        $content = [];
        foreach ($slice as $offset => $line) {
            $lineNo = $start + $offset;
            $content[] = str_pad((string) $lineNo, 6, ' ', STR_PAD_LEFT) . ' | ' . $line;
        }

        return [
            'start_line' => $start,
            'end_line' => $end,
            'content' => implode("\n", $content),
            'truncated' => ($start > 1 || $end < $lineCount)
        ];
    }

    private static function isMibFile(\SplFileInfo $fileInfo): bool {
        $name = $fileInfo->getFilename();
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (in_array($extension, ['mib', 'txt', 'my', 'defs'], true)) {
            return true;
        }

        if ($extension === '') {
            return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name) === 1;
        }

        return false;
    }

    private static function formatBytes(int $bytes): string {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        foreach ($units as $unit) {
            if ($value < 1024 || $unit === 'TB') {
                return number_format($value, 2) . ' ' . $unit;
            }
            $value /= 1024;
        }

        return number_format($value, 2) . ' TB';
    }

    private static function formatTimestamp($timestamp): string {
        if (!$timestamp) {
            return '-';
        }

        return date('Y-m-d H:i:s', (int) $timestamp);
    }

    public static function testOid(string $oid, array $connection): array {
        $oid = trim($oid);
        if ($oid === '') {
            return ['ok' => false, 'message' => LanguageManager::t('OID is empty')];
        }

        $normalizedOid = self::normalizeOid($oid);
        if ($normalizedOid === null) {
            return ['ok' => false, 'message' => LanguageManager::t('OID format is invalid')];
        }

        $address = trim((string) ($connection['address'] ?? ''));
        $port = trim((string) ($connection['port'] ?? '161'));
        $version = strtolower(trim((string) ($connection['version'] ?? '2c')));

        if ($address === '') {
            return ['ok' => false, 'message' => LanguageManager::t('SNMP target address is empty')];
        }

        $target = $address . ':' . ($port !== '' ? $port : '161');

        $funcResult = self::testOidViaPhpSnmp($target, $normalizedOid, $version, $connection);
        if ($funcResult !== null) {
            return $funcResult;
        }

        $cmdResult = self::testOidViaCommand($target, $normalizedOid, $version, $connection);
        if ($cmdResult !== null) {
            return $cmdResult;
        }

        return [
            'ok' => false,
            'message' => LanguageManager::t('No SNMP runtime is available (PHP SNMP extension and snmpget command unavailable).')
        ];
    }

    public static function walkOid(string $oid, array $connection): array {
        $oid = trim($oid);
        if ($oid === '') {
            return ['ok' => false, 'message' => LanguageManager::t('OID is empty'), 'lines' => []];
        }

        $normalizedOid = self::normalizeOid($oid);
        if ($normalizedOid === null) {
            return ['ok' => false, 'message' => LanguageManager::t('OID format is invalid'), 'lines' => []];
        }

        $address = trim((string) ($connection['address'] ?? ''));
        $port = trim((string) ($connection['port'] ?? '161'));
        $version = strtolower(trim((string) ($connection['version'] ?? '2c')));

        if ($address === '') {
            return ['ok' => false, 'message' => LanguageManager::t('SNMP target address is empty'), 'lines' => []];
        }

        $target = $address . ':' . ($port !== '' ? $port : '161');

        $cmdResult = self::walkOidViaCommand($target, $normalizedOid, $version, $connection);
        if ($cmdResult !== null) {
            return $cmdResult;
        }

        return [
            'ok' => false,
            'message' => LanguageManager::t('SNMP walk failed'),
            'lines' => []
        ];
    }

    public static function resolveTestOid(string $oid, string $symbol, array $objects): ?string {
        $objectMap = self::buildObjectMap($objects);
        $objectMeta = self::buildObjectMeta($objects);

        if ($symbol !== '' && isset($objectMeta[$symbol])) {
            $resolved = self::resolveOidValue((string) ($objectMeta[$symbol]['oid'] ?? ''), $objectMap, []);
            if ($resolved === null) {
                return null;
            }

            if (self::shouldAppendInstance($objectMeta[$symbol])) {
                return $resolved . '.0';
            }

            return $resolved;
        }

        return self::resolveOidValue($oid, $objectMap, []);
    }

    private static function buildObjectMeta(array $objects): array {
        $meta = [];

        foreach ($objects as $object) {
            $name = (string) ($object['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $meta[$name] = [
                'oid' => trim((string) ($object['oid'] ?? '')),
                'kind' => trim((string) ($object['kind'] ?? '')),
                'syntax' => trim((string) ($object['syntax'] ?? ''))
            ];
        }

        return $meta;
    }

    private static function shouldAppendInstance(array $objectMeta): bool {
        $kind = strtoupper((string) ($objectMeta['kind'] ?? ''));
        $syntax = strtoupper((string) ($objectMeta['syntax'] ?? ''));

        if ($kind !== 'OBJECT-TYPE') {
            return false;
        }

        if ($syntax !== '' && stripos($syntax, 'SEQUENCE OF') !== false) {
            return false;
        }

        return true;
    }

    private static function normalizeOid(string $oid): ?string {
        $oid = trim($oid);
        if ($oid === '') {
            return null;
        }

        if (preg_match('/^\.?\d+(?:\.\d+)*$/', $oid) === 1) {
            return ltrim($oid, '.');
        }

        $translated = self::translateOid($oid);
        if ($translated !== null) {
            return $translated;
        }

        return null;
    }

    private static function translateOid(string $oid): ?string {
        if (!function_exists('shell_exec')) {
            return null;
        }

        $cmd = 'snmptranslate -On -IR -m ALL ' . escapeshellarg($oid);
        $output = @shell_exec($cmd . ' 2>&1');
        if ($output === null) {
            return null;
        }

        $translated = trim($output);
        if ($translated === '') {
            return null;
        }

        if (preg_match('/^\.?\d+(?:\.\d+)*$/', $translated) === 1) {
            return ltrim($translated, '.');
        }

        return null;
    }

    private static function testOidViaPhpSnmp(string $target, string $oid, string $version, array $connection): ?array {
        $timeout = 1000000;
        $retries = 1;

        if (($version === '2c' || $version === '2') && function_exists('snmp2_get')) {
            $community = (string) ($connection['community'] ?? 'public');
            $value = @snmp2_get($target, $community, $oid, $timeout, $retries);
            if ($value !== false) {
                return ['ok' => true, 'message' => trim((string) $value)];
            }
            return ['ok' => false, 'message' => LanguageManager::t('SNMP v2c query failed')];
        }

        if ($version === '1' && function_exists('snmpget')) {
            $community = (string) ($connection['community'] ?? 'public');
            $value = @snmpget($target, $community, $oid, $timeout, $retries);
            if ($value !== false) {
                return ['ok' => true, 'message' => trim((string) $value)];
            }
            return ['ok' => false, 'message' => LanguageManager::t('SNMP v1 query failed')];
        }

        if ($version === '3' && function_exists('snmp3_get')) {
            $securityName = (string) ($connection['securityname'] ?? '');
            $securityLevel = (string) ($connection['securitylevel'] ?? 'noAuthNoPriv');
            $authProtocol = (string) ($connection['authprotocol'] ?? 'SHA');
            $authPass = (string) ($connection['authpassphrase'] ?? '');
            $privProtocol = (string) ($connection['privprotocol'] ?? 'AES');
            $privPass = (string) ($connection['privpassphrase'] ?? '');

            $value = @snmp3_get(
                $target,
                $securityName,
                $securityLevel,
                $authProtocol,
                $authPass,
                $privProtocol,
                $privPass,
                $oid,
                $timeout,
                $retries
            );

            if ($value !== false) {
                return ['ok' => true, 'message' => trim((string) $value)];
            }
            return ['ok' => false, 'message' => LanguageManager::t('SNMP v3 query failed')];
        }

        return null;
    }

    private static function testOidViaCommand(string $target, string $oid, string $version, array $connection): ?array {
        if (!function_exists('shell_exec')) {
            return null;
        }

        $base = 'snmpget -Oqv -t 1 -r 1';

        if ($version === '1' || $version === '2c' || $version === '2') {
            $community = (string) ($connection['community'] ?? 'public');
            $cmd = $base
                . ' -v ' . escapeshellarg($version === '1' ? '1' : '2c')
                . ' -c ' . escapeshellarg($community)
                . ' ' . escapeshellarg($target)
                . ' ' . escapeshellarg($oid);

            $output = @shell_exec($cmd . ' 2>&1');
            if ($output === null) {
                return ['ok' => false, 'message' => LanguageManager::t('snmpget command failed to execute')];
            }

            $text = trim($output);
            if ($text !== '' && stripos($text, 'Timeout') === false && stripos($text, 'No Such') === false && stripos($text, 'Unknown') === false) {
                return ['ok' => true, 'message' => $text];
            }

            return ['ok' => false, 'message' => $text !== '' ? $text : LanguageManager::t('SNMP query failed')];
        }

        if ($version === '3') {
            $securityName = (string) ($connection['securityname'] ?? '');
            $securityLevel = (string) ($connection['securitylevel'] ?? 'noAuthNoPriv');
            $authProtocol = strtoupper((string) ($connection['authprotocol'] ?? 'SHA'));
            $authPass = (string) ($connection['authpassphrase'] ?? '');
            $privProtocol = strtoupper((string) ($connection['privprotocol'] ?? 'AES'));
            $privPass = (string) ($connection['privpassphrase'] ?? '');

            if ($securityName === '') {
                return ['ok' => false, 'message' => LanguageManager::t('SNMPv3 security name is required')];
            }

            $cmd = $base
                . ' -v 3'
                . ' -u ' . escapeshellarg($securityName)
                . ' -l ' . escapeshellarg($securityLevel);

            if ($securityLevel === 'authNoPriv' || $securityLevel === 'authPriv') {
                $cmd .= ' -a ' . escapeshellarg($authProtocol)
                    . ' -A ' . escapeshellarg($authPass);
            }

            if ($securityLevel === 'authPriv') {
                $cmd .= ' -x ' . escapeshellarg($privProtocol)
                    . ' -X ' . escapeshellarg($privPass);
            }

            $cmd .= ' ' . escapeshellarg($target) . ' ' . escapeshellarg($oid);

            $output = @shell_exec($cmd . ' 2>&1');
            if ($output === null) {
                return ['ok' => false, 'message' => LanguageManager::t('snmpget command failed to execute')];
            }

            $text = trim($output);
            if ($text !== '' && stripos($text, 'Timeout') === false && stripos($text, 'No Such') === false && stripos($text, 'Unknown') === false) {
                return ['ok' => true, 'message' => $text];
            }

            return ['ok' => false, 'message' => $text !== '' ? $text : LanguageManager::t('SNMP query failed')];
        }

        return null;
    }

    private static function walkOidViaCommand(string $target, string $oid, string $version, array $connection): ?array {
        if (!function_exists('shell_exec')) {
            return null;
        }

        $base = 'snmpwalk -On -t 2 -r 1';

        if ($version === '1' || $version === '2c' || $version === '2') {
            $community = (string) ($connection['community'] ?? 'public');
            $cmd = $base
                . ' -v ' . escapeshellarg($version === '1' ? '1' : '2c')
                . ' -c ' . escapeshellarg($community)
                . ' ' . escapeshellarg($target)
                . ' ' . escapeshellarg($oid);

            $output = @shell_exec($cmd . ' 2>&1');
            if ($output === null) {
                return ['ok' => false, 'message' => LanguageManager::t('SNMP walk command failed to execute'), 'lines' => []];
            }

            return self::formatWalkCommandOutput($output);
        }

        if ($version === '3') {
            $securityName = (string) ($connection['securityname'] ?? '');
            $securityLevel = (string) ($connection['securitylevel'] ?? 'noAuthNoPriv');
            $authProtocol = strtoupper((string) ($connection['authprotocol'] ?? 'SHA'));
            $authPass = (string) ($connection['authpassphrase'] ?? '');
            $privProtocol = strtoupper((string) ($connection['privprotocol'] ?? 'AES'));
            $privPass = (string) ($connection['privpassphrase'] ?? '');

            if ($securityName === '') {
                return ['ok' => false, 'message' => LanguageManager::t('SNMPv3 security name is required'), 'lines' => []];
            }

            $cmd = $base
                . ' -v 3'
                . ' -u ' . escapeshellarg($securityName)
                . ' -l ' . escapeshellarg($securityLevel);

            if ($securityLevel === 'authNoPriv' || $securityLevel === 'authPriv') {
                $cmd .= ' -a ' . escapeshellarg($authProtocol)
                    . ' -A ' . escapeshellarg($authPass);
            }

            if ($securityLevel === 'authPriv') {
                $cmd .= ' -x ' . escapeshellarg($privProtocol)
                    . ' -X ' . escapeshellarg($privPass);
            }

            $cmd .= ' ' . escapeshellarg($target) . ' ' . escapeshellarg($oid);

            $output = @shell_exec($cmd . ' 2>&1');
            if ($output === null) {
                return ['ok' => false, 'message' => LanguageManager::t('SNMP walk command failed to execute'), 'lines' => []];
            }

            return self::formatWalkCommandOutput($output);
        }

        return null;
    }

    private static function formatWalkCommandOutput(string $output): array {
        $text = trim($output);
        if ($text === '') {
            return ['ok' => false, 'message' => LanguageManager::t('SNMP walk returned no data'), 'lines' => []];
        }

        $errorHints = ['Timeout', 'Unknown', 'No Such', 'Authentication failure', 'USM'];
        foreach ($errorHints as $hint) {
            if (stripos($text, $hint) !== false) {
                return ['ok' => false, 'message' => $text, 'lines' => []];
            }
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);
        if (!is_array($lines)) {
            $lines = [$text];
        }

        $lines = array_values(array_filter(array_map('trim', $lines), static function (string $line): bool {
            return $line !== '';
        }));

        if (empty($lines)) {
            return ['ok' => false, 'message' => LanguageManager::t('SNMP walk returned no data'), 'lines' => []];
        }

        return ['ok' => true, 'message' => '', 'lines' => $lines];
    }
}