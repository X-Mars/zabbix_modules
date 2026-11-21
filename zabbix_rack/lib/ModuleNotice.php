<?php
/** Vendored into each module so modules remain independently installable. PHP 7.3+. */
if (!class_exists('ZabbixModuleNotice', false)) {
    final class ZabbixModuleNotice {
        public static function language(): string {
            $locale = '';
            try {
                if (class_exists('CWebUser')) {
                    $locale = (string) (\CWebUser::$data['lang'] ?? '');
                    if ($locale === '' && method_exists('CWebUser', 'get')) {
                        $locale = (string) \CWebUser::get('lang');
                    }
                }
            } catch (\Throwable $e) {}
            if ($locale === '' || strtolower($locale) === 'default') {
                try {
                    if (class_exists('CSettingsHelper')) {
                        $locale = method_exists('CSettingsHelper', 'getPublic')
                            ? (string) \CSettingsHelper::getPublic('default_lang')
                            : (string) \CSettingsHelper::get('default_lang');
                    } else {
                        $locale = (string) ($GLOBALS['ZBX_DEFAULT_LANG'] ?? 'en_US');
                    }
                } catch (\Throwable $e) { $locale = 'en_US'; }
            }
            return preg_match('/^zh(?:[-_]|$)/i', $locale) ? 'zh_CN' : 'en_US';
        }
        public static function text(string $english, string $chinese): string {
            return self::language() === 'zh_CN' ? $chinese : $english;
        }
        public static function escape(string $text): string {
            return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        public static function administrator(): bool {
            try {
                return class_exists('CWebUser') && defined('USER_TYPE_SUPER_ADMIN')
                    && (method_exists('CWebUser', 'getType') ? \CWebUser::getType()
                        : (\CWebUser::$data['type'] ?? 0)) >= USER_TYPE_SUPER_ADMIN;
            } catch (\Throwable $e) { return false; }
        }
        public static function runtime(): array {
            $uid = function_exists('posix_geteuid') ? posix_geteuid() : null;
            $gid = function_exists('posix_getegid') ? posix_getegid() : null;
            if ($uid === null && is_readable('/proc/self/status')) {
                $status = (string) @file_get_contents('/proc/self/status');
                if (preg_match('/^Uid:\s+\d+\s+(\d+)/m', $status, $m)) { $uid = (int) $m[1]; }
                if (preg_match('/^Gid:\s+\d+\s+(\d+)/m', $status, $m)) { $gid = (int) $m[1]; }
            }
            $user = $uid !== null && function_exists('posix_getpwuid') ? posix_getpwuid($uid) : false;
            $group = $gid !== null && function_exists('posix_getgrgid') ? posix_getgrgid($gid) : false;
            // Minimal PHP installations may omit POSIX; resolve only the effective
            // process IDs through the public account databases, never the script owner.
            if (!$user && $uid !== null && is_readable('/etc/passwd')) {
                foreach (@file('/etc/passwd', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                    $fields = explode(':', $line);
                    if (isset($fields[2]) && $fields[2] === (string) $uid) { $user = ['name' => $fields[0]]; break; }
                }
            }
            if (!$group && $gid !== null && is_readable('/etc/group')) {
                foreach (@file('/etc/group', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                    $fields = explode(':', $line);
                    if (isset($fields[2]) && $fields[2] === (string) $gid) { $group = ['name' => $fields[0]]; break; }
                }
            }
            return ['uid' => $uid, 'gid' => $gid,
                'user' => $user['name'] ?? ($uid === null ? '<PHP_USER>' : (string) $uid),
                'group' => $group['name'] ?? ($gid === null ? '<PHP_GROUP>' : (string) $gid)];
        }
        public static function ownershipCommand(string $module): string {
            $account = self::runtime();
            return 'chown -R -- '.escapeshellarg($account['user'].':'.$account['group'])
                .' '.escapeshellarg(realpath($module) ?: $module);
        }
        public static function saveHint(string $module): string {
            return self::text('If saving fails, change the directory ownership and check SELinux: ',
                '保存报错时，请修改目录归属并检查 SELinux：')
                .self::ownershipCommand($module).'; getenforce';
        }
        public static function reminder(string $module): string {
            // Save errors still need their hint when the ownership reminder is absent.
            $hint = '<span class="module-save-hint" hidden data-save-hint="'
                .self::escape(self::saveHint($module)).'"></span>';
            $account = self::runtime();
            clearstatcache(true, $module);
            $owner = @fileowner($module);
            $group = @filegroup($module);
            if (!is_dir($module) || $owner === false || $group === false
                    || $account['uid'] === null || $account['gid'] === null
                    || ($owner === $account['uid'] && $group === $account['gid'])) {
                return $hint;
            }
            return $hint.'<p class="module-ownership-reminder" role="note" style="margin:12px 0;overflow-wrap:anywhere">'
                .self::escape(self::text('Directory ownership reminder: ', '目录归属提醒：'))
                .'<code>'.self::escape(self::ownershipCommand($module)).'</code></p>';
        }
        public static function storageText(string $module, array $targets): string {
            $commands = [];
            $account = self::runtime();
            foreach ($targets as $target) {
                $spec = is_array($target) ? $target : ['path' => $target];
                $path = $spec['path'];
                // Do not recommend recursive ownership changes to shared system directories.
                if ($path === '' || $path[0] !== '/' || in_array(rtrim($path, '/') ?: '/',
                    ['/', '/tmp', '/var/tmp', '/var', '/usr', '/usr/share', '/usr/share/zabbix', '/usr/share/zabbix/ui', '/etc', '/opt', '/home', '/root'], true)) {
                    continue;
                }
                $commands[] = 'chown '.(isset($spec['recursive']) && !$spec['recursive'] ? '' : '-R ')
                    .'-- '.escapeshellarg($account['user'].':'.$account['group']).' '.escapeshellarg($path);
            }
            return self::text('Please change the directory ownership and check SELinux.', '请修改目录归属并检查 SELinux。')
                ."\n".implode("\n", $commands ?: [self::ownershipCommand($module)])."\ngetenforce";
        }
        public static function failure(\Throwable $error, string $module, array $targets): string {
            return self::translate($error->getMessage())."\n".self::storageText($module, $targets);
        }
        public static function translate(string $message): string {
            $translations = [
                'Invalid JSON' => 'JSON 格式无效', 'Invalid config.json' => 'config.json 格式无效',
                'Invalid provider' => '不支持的服务商', 'Invalid region' => '区域参数无效',
                'Account not found' => '未找到账号', 'Name is required.' => '请输入名称。',
                'Access key is required.' => '请输入访问密钥。', 'Secret key is required.' => '请输入密钥。',
                'Cannot lock config file' => '无法锁定配置文件',
                'Failed to initialize branding storage. Review the web server error log for details.' => '品牌存储初始化失败，请查看 Web 服务器错误日志。',
                'Failed to encode branding settings as JSON.' => '品牌配置无法编码为 JSON。',
                'Please select dates' => '请选择日期',
                "Help URL: only HTTP and HTTPS URLs are allowed." => "帮助链接：仅允许 HTTP 和 HTTPS 地址。",
                "Server name: enter a name, or choose Default or Hidden." => "服务器名称：请输入名称，或选择默认/隐藏。",
                "file too large." => "文件过大。",
                "upload was only partially completed." => "文件仅上传了一部分。",
                "no file was uploaded." => "未上传文件。",
                "missing temporary upload directory." => "缺少上传临时目录。",
                "could not write file to disk." => "无法将文件写入磁盘。",
                "upload blocked by a PHP extension." => "上传被 PHP 扩展阻止。",
                "unknown upload error." => "未知上传错误。",
                "invalid upload payload." => "上传内容无效。",
                "invalid file type. Allowed: PNG, JPG, GIF, ICO." => "文件类型无效，支持 PNG、JPG、GIF、ICO。",
                "file too large. Maximum: 2 MB." => "文件过大，上限为 2 MB。",
                "file content does not match the selected image format." => "文件内容与所选图片格式不符。"
            ];
            if (self::language() === 'zh_CN') {
                if (preg_match('/^(cpu_usage|memory_usage) rules are required$/', $message, $match)) {
                    return ($match[1] === 'cpu_usage' ? 'CPU 使用率' : '内存使用率').'至少需要一条匹配规则。';
                }
                return $translations[$message] ?? $message;
            }
            $reverse = array_flip($translations);
            return $reverse[$message] ?? $message;
        }
    }
}
