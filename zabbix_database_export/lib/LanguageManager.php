<?php

namespace Modules\ZabbixDatabaseExport\Lib;

final class LanguageManager {
    private const DEFAULT_LANGUAGE = 'en_US';

    private const TRANSLATIONS = [
        'zh_CN' => [
            'Install a pg_dump client compatible with the PostgreSQL server version.' => '请安装与 PostgreSQL 服务器版本兼容的 pg_dump 客户端。',
            'Database export' => '数据库导出',
            'Database type' => '数据库类型',
            'Database version' => '数据库版本',
            'MySQL, MariaDB or PostgreSQL is required.' => '本模块需要 MySQL、MariaDB 或 PostgreSQL 数据库。',
            'The PostgreSQL export failed.' => 'PostgreSQL 导出失败。',
            'PostgreSQL export requires proc_open and a compatible pg_dump client.' => 'PostgreSQL 导出需要启用 proc_open 并安装兼容的 pg_dump 客户端。',
            'PostgreSQL password files cannot contain line breaks.' => 'PostgreSQL 密码文件不支持换行字符。',
            'pg_dump failed. Check its version, database access and server logs.' => 'pg_dump 执行失败，请检查客户端版本、数据库访问权限及服务器日志。',
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
            'Install a pg_dump client compatible with the PostgreSQL server version.' => 'Install a pg_dump client compatible with the PostgreSQL server version.',
            'Database export' => 'Database export',
            'Database type' => 'Database type',
            'Database version' => 'Database version',
            'MySQL, MariaDB or PostgreSQL is required.' => 'MySQL, MariaDB or PostgreSQL is required.',
            'The PostgreSQL export failed.' => 'The PostgreSQL export failed.',
            'PostgreSQL export requires proc_open and a compatible pg_dump client.' => 'PostgreSQL export requires proc_open and a compatible pg_dump client.',
            'PostgreSQL password files cannot contain line breaks.' => 'PostgreSQL password files cannot contain line breaks.',
            'pg_dump failed. Check its version, database access and server logs.' => 'pg_dump failed. Check its version, database access and server logs.',
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
        'ru_RU' => [
            'Install a pg_dump client compatible with the PostgreSQL server version.' => 'Установите клиент pg_dump, совместимый с версией сервера PostgreSQL.',
            'Database export' => 'Экспорт базы данных',
            'Database type' => 'Тип базы данных',
            'Database version' => 'Версия базы данных',
            'MySQL, MariaDB or PostgreSQL is required.' => 'Требуется MySQL, MariaDB или PostgreSQL.',
            'The PostgreSQL export failed.' => 'Не удалось выполнить экспорт PostgreSQL.',
            'PostgreSQL export requires proc_open and a compatible pg_dump client.' => 'Для экспорта PostgreSQL требуются proc_open и совместимый клиент pg_dump.',
            'PostgreSQL password files cannot contain line breaks.' => 'Файлы паролей PostgreSQL не могут содержать переводы строк.',
            'pg_dump failed. Check its version, database access and server logs.' => 'Ошибка pg_dump. Проверьте его версию, доступ к базе данных и журнал сервера.',
            'Database tables' => 'Таблицы базы данных',
            'Table name' => 'Имя таблицы',
            'Engine' => 'Движок',
            'Estimated size' => 'Приблизительный размер',
            'Search tables' => 'Поиск таблиц',
            'Exclude history records' => 'Исключить данные истории',
            'Select all filtered tables' => 'Выбрать все таблицы по фильтру',
            'Selected tables: %d / %d' => 'Выбрано таблиц: %d из %d',
            'Table sizes include data and indexes.' => 'Размеры таблиц включают данные и индексы.',
            'No matching tables.' => 'Подходящие таблицы не найдены.',
            'No tables are available to export.' => 'Нет таблиц для экспорта.',
            'Export options' => 'Параметры экспорта',
            'Table scope' => 'Область экспорта',
            'All tables' => 'Все таблицы',
            'Selected tables' => 'Выбранные таблицы',
            'Unselected tables are exported as structure only.' => 'Для остальных таблиц экспортируется только структура.',
            'Contents' => 'Содержимое',
            'Structure and data' => 'Структура и данные',
            'Structure only' => 'Только структура',
            'Data only' => 'Только данные',
            'Destination' => 'Назначение',
            'Download in browser' => 'Скачать через браузер',
            'Save on server' => 'Сохранить на сервере',
            'Server directory' => 'Каталог на сервере',
            'SQL files over 500 MB are saved to this directory automatically.' => 'Файлы SQL размером более 500 МБ автоматически сохраняются в этом каталоге.',
            'Export SQL' => 'Экспортировать SQL',
            'Backup saved on the server' => 'Резервная копия сохранена на сервере',
            'Backup exceeded 500 MB and was saved on the server' => 'Размер копии превысил 500 МБ, и она сохранена на сервере',
            'The database export failed.' => 'Не удалось экспортировать базу данных.',
            'The database export failed (MySQL error %d).' => 'Не удалось экспортировать базу данных (ошибка MySQL %d).',
            'Choose an export mode.' => 'Выберите режим экспорта.',
            'Select at least one table.' => 'Выберите хотя бы одну таблицу.',
            'The table selection is invalid. Refresh the page and retry.' => 'Недопустимый выбор таблиц. Обновите страницу и повторите попытку.',
            'Could not write the backup. Check available disk space.' => 'Не удалось записать резервную копию. Проверьте свободное место на диске.',
            'Could not create a private backup file.' => 'Не удалось создать закрытый файл резервной копии.',
            'Could not save the backup. Check permissions or retry in a second.' => 'Не удалось сохранить резервную копию. Проверьте права доступа или повторите попытку через секунду.',
            'The server backup directory configuration is invalid.' => 'Недопустимая настройка каталога резервных копий на сервере.',
            'Choose a private backup directory outside the web root.' => 'Выберите закрытый каталог резервных копий вне корневого каталога сайта.',
            'Choose a private, writable directory configured for backups.' => 'Выберите закрытый каталог резервных копий с правом записи.',
            'The backup options are invalid.' => 'Недопустимые параметры резервного копирования.',
            'Could not create a temporary backup file.' => 'Не удалось создать временный файл резервной копии.',
        ],
    ];

    private static $currentLanguage;

    public static function detectLanguage(): string {
        if (self::$currentLanguage === null) {
            $locale = self::normalize(\CWebUser::$data['lang'] ?? '');
            if ($locale === '' || $locale === 'default') {
                $locale = self::normalize(\CSettingsHelper::getPublic(\CSettingsHelper::DEFAULT_LANG));
            }

            if (preg_match('/^ru(?:_|$)/', $locale)) {
                self::$currentLanguage = 'ru_RU';
            } else {
                self::$currentLanguage = preg_match('/^zh(?:_|$)/', $locale) ? 'zh_CN' : self::DEFAULT_LANGUAGE;
            }
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
