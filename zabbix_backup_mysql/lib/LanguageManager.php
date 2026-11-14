<?php

namespace Modules\ZabbixBackupMysql\Lib;

final class LanguageManager {
    private const DEFAULT_LANGUAGE = 'en_US';

    private const TRANSLATIONS = [
        'zh_CN' => [
            'MySQL backup' => 'MySQL 备份',
            'Database tables' => '数据库表',
            'Table name' => '表名',
            'Engine' => '存储引擎',
            'Estimated size' => '预估大小',
            'Search tables' => '搜索表名',
            'Exclude history records' => '排除历史记录',
            'Select all filtered tables' => '选择筛选出的全部表',
            'Selected tables: %d / %d' => '已选 %d / %d 张表',
            'Table sizes include data and indexes.' => '表大小包含数据和索引。',
            'No matching tables.' => '没有匹配的表。',
            'No tables are available to export.' => '没有可导出的表。',
            'Export options' => '导出选项',
            'Table scope' => '导出范围',
            'All tables' => '全部表',
            'Selected tables' => '选中的表',
            'Unselected tables are exported as structure only.' => '未选中的表仅导出表结构。',
            'Contents' => '导出内容',
            'Structure and data' => '表结构和数据',
            'Structure only' => '仅表结构',
            'Data only' => '仅数据',
            'Destination' => '输出位置',
            'Download in browser' => '浏览器下载',
            'Save on server' => '保存到服务器',
            'Server directory' => '服务器目录',
            'SQL files over 500 MB are saved to this directory automatically.' => '大于 500 MB 的 SQL 文件会自动保存到此目录。',
            'Export SQL' => '导出 SQL',
            'Backup saved on the server' => '备份已保存到服务器',
            'Backup exceeded 500 MB and was saved on the server' => '备份大于 500 MB，已自动保存到服务器',
            'MySQL or MariaDB is required.' => '本模块需要 MySQL 或 MariaDB 数据库。',
            'The database export failed.' => '数据库导出失败。',
            'The database export failed (MySQL error %d).' => '数据库导出失败（MySQL 错误 %d）。',
            'Choose an export mode.' => '请选择导出模式。',
            'Select at least one table.' => '请至少选择一张表。',
            'The table selection is invalid. Refresh the page and retry.' => '所选数据表无效，请刷新页面后重试。',
            'Could not write the backup. Check available disk space.' => '无法写入备份，请检查可用磁盘空间。',
            'Could not create a private backup file.' => '无法创建私有备份文件。',
            'Could not save the backup. Check permissions or retry in a second.' => '无法保存备份，请检查目录权限或稍后重试。',
            'The server backup directory configuration is invalid.' => '服务器备份目录配置无效。',
            'Choose a private backup directory outside the web root.' => '请选择 Web 根目录以外的私有备份目录。',
            'Choose a private, writable directory configured for backups.' => '请选择已配置且可写的私有备份目录。',
            'The backup options are invalid.' => '备份选项无效。',
            'Could not create a temporary backup file.' => '无法创建备份临时文件。',
        ],
        'en_US' => [
            'MySQL backup' => 'MySQL backup',
            'Database tables' => 'Database tables',
            'Table name' => 'Table name',
            'Engine' => 'Engine',
            'Estimated size' => 'Estimated size',
            'Search tables' => 'Search tables',
            'Exclude history records' => 'Exclude history records',
            'Select all filtered tables' => 'Select all filtered tables',
            'Selected tables: %d / %d' => 'Selected tables: %d / %d',
            'Table sizes include data and indexes.' => 'Table sizes include data and indexes.',
            'No matching tables.' => 'No matching tables.',
            'No tables are available to export.' => 'No tables are available to export.',
            'Export options' => 'Export options',
            'Table scope' => 'Table scope',
            'All tables' => 'All tables',
            'Selected tables' => 'Selected tables',
            'Unselected tables are exported as structure only.' => 'Unselected tables are exported as structure only.',
            'Contents' => 'Contents',
            'Structure and data' => 'Structure and data',
            'Structure only' => 'Structure only',
            'Data only' => 'Data only',
            'Destination' => 'Destination',
            'Download in browser' => 'Download in browser',
            'Save on server' => 'Save on server',
            'Server directory' => 'Server directory',
            'SQL files over 500 MB are saved to this directory automatically.' => 'SQL files over 500 MB are saved to this directory automatically.',
            'Export SQL' => 'Export SQL',
            'Backup saved on the server' => 'Backup saved on the server',
            'Backup exceeded 500 MB and was saved on the server' => 'Backup exceeded 500 MB and was saved on the server',
            'MySQL or MariaDB is required.' => 'MySQL or MariaDB is required.',
            'The database export failed.' => 'The database export failed.',
            'The database export failed (MySQL error %d).' => 'The database export failed (MySQL error %d).',
            'Choose an export mode.' => 'Choose an export mode.',
            'Select at least one table.' => 'Select at least one table.',
            'The table selection is invalid. Refresh the page and retry.' => 'The table selection is invalid. Refresh the page and retry.',
            'Could not write the backup. Check available disk space.' => 'Could not write the backup. Check available disk space.',
            'Could not create a private backup file.' => 'Could not create a private backup file.',
            'Could not save the backup. Check permissions or retry in a second.' => 'Could not save the backup. Check permissions or retry in a second.',
            'The server backup directory configuration is invalid.' => 'The server backup directory configuration is invalid.',
            'Choose a private backup directory outside the web root.' => 'Choose a private backup directory outside the web root.',
            'Choose a private, writable directory configured for backups.' => 'Choose a private, writable directory configured for backups.',
            'The backup options are invalid.' => 'The backup options are invalid.',
            'Could not create a temporary backup file.' => 'Could not create a temporary backup file.',
        ],
    ];

    private static $currentLanguage;

    public static function detectLanguage(): string {
        if (self::$currentLanguage === null) {
            $locale = self::normalize(\CWebUser::$data['lang'] ?? '');
            if ($locale === '' || $locale === 'default') {
                $locale = self::normalize(\CSettingsHelper::getPublic(\CSettingsHelper::DEFAULT_LANG));
            }

            self::$currentLanguage = preg_match('/^zh(?:_|$)/', $locale) ? 'zh_CN' : self::DEFAULT_LANGUAGE;
        }

        return self::$currentLanguage;
    }

    private static function normalize(string $locale): string {
        return str_replace('-', '_', strtolower(trim($locale)));
    }

    public static function t(string $key): string {
        return self::TRANSLATIONS[self::detectLanguage()][$key]
            ?? self::TRANSLATIONS[self::DEFAULT_LANGUAGE][$key]
            ?? $key;
    }

    public static function tf(string $key, ...$args): string {
        return sprintf(self::t($key), ...$args);
    }
}
