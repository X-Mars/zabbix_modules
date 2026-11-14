<?php

use Modules\ZabbixBackupMysql\Lib\{BackupFile, SqlExport};

require_once __DIR__ . '/../lib/BackupFile.php';
require_once __DIR__ . '/../lib/SqlExport.php';

function check($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function checkBackupFiles(): void {
    check(!BackupFile::shouldSave('download', 499999999), 'Small backups should download.');
    check(!BackupFile::shouldSave('download', 500000000), '500 MB should download.');
    check(BackupFile::shouldSave('download', 500000001), 'Large backups should save automatically.');
    check(BackupFile::shouldSave('server', 1), 'Explicit server destination should save.');
}

function checkSqlExport(mysqli $db, string $dir): void {
    $db->query("CREATE TABLE fixture (id BIGINT UNSIGNED PRIMARY KEY, message TEXT,
        bytes BLOB, flag BIT(9), geom POINT, stamp TIMESTAMP NULL,
        generated INT GENERATED ALWAYS AS (LENGTH(message)) STORED) ENGINE=InnoDB");
    $db->query("CREATE TABLE relation (id INT PRIMARY KEY, parent BIGINT UNSIGNED,
        FOREIGN KEY(parent) REFERENCES fixture(id)) ENGINE=InnoDB");
    $db->query("SET SESSION sql_mode='NO_BACKSLASH_ESCAPES', time_zone='+05:30'");
    $db->query("INSERT INTO fixture (id,message,bytes,flag,geom,stamp)
        VALUES (18446744073709551615, CONVERT(X'275C0A001AF09F9880' USING utf8mb4), X'AB00FF',
        257, ST_GeomFromText('POINT(1 2)',0), '2026-01-01 15:00:00')");
    $db->query('INSERT INTO relation VALUES (1,18446744073709551615)');
    $db->query('CREATE TABLE history_uint (id INT PRIMARY KEY) ENGINE=InnoDB');
    $db->query('INSERT INTO history_uint VALUES (1)');
    $exporter = new SqlExport($db);
    check(isset($exporter->tables()['fixture']['bytes']), 'Table sizes are required.');
    foreach (['both', 'schema', 'data', 'selected-both'] as $case) {
        $mode = $case === 'selected-both' ? 'both' : $case;
        $path = $dir . '/' . $case . '.sql';
        $file = fopen($path, 'wb');
        try {
            $exporter->run(static function (string $chunk) use ($file): void {
                BackupFile::write($file, $chunk);
            }, $case === 'both' ? null : ['fixture'], $mode);
        } finally {
            fclose($file);
        }
        $dump = file_get_contents($path);
        check((strpos($dump, 'CREATE TABLE `fixture`') !== false) === ($mode !== 'data'), 'Unexpected schema.');
        check((strpos($dump, 'INSERT INTO `fixture`') !== false) === ($mode !== 'schema'), 'Unexpected data.');
        check(strpos($dump, 'CREATE TABLE `relation`') !== false, 'Unselected table structure was omitted.');
        check(strpos($dump, 'FOREIGN KEY (`parent`) REFERENCES `fixture`') !== false, 'Foreign key was omitted.');
        check((strpos($dump, 'INSERT INTO `relation`') !== false) === ($case === 'both'), 'Unselected rows leaked.');
        check(strpos($dump, 'DROP TABLE') === false, 'Dump must preserve existing tables.');
    }

    foreach ([null, ['fixture', 'history_uint']] as $selected) {
        foreach (['both', 'schema', 'data'] as $mode) {
            $dump = '';
            $exporter->run(static function (string $chunk) use (&$dump): void {
                $dump .= $chunk;
            }, $selected, $mode, true);
            check(strpos($dump, 'CREATE TABLE `history_uint`') !== false, 'History structure was omitted.');
            check(strpos($dump, 'INSERT INTO `history_uint`') === false, 'History rows were not excluded.');
            check((strpos($dump, 'INSERT INTO `fixture`') !== false) === ($mode !== 'schema'),
                'Excluding history affected other tables.');
        }
    }

    $session = $db->query("SELECT @@sql_mode, @@time_zone")->fetch_row();
    check($session === ['NO_BACKSLASH_ESCAPES', '+05:30'], 'Original MySQL session settings changed.');
    try {
        $exporter->run(static function (): void {}, ['not_a_table'], 'both');
        throw new LogicException('Unknown table accepted.');
    } catch (RuntimeException $e) {
        check(strpos($e->getMessage(), 'selection') !== false, 'Wrong validation error.');
    }
}
