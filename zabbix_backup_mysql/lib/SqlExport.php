<?php

namespace Modules\ZabbixBackupMysql\Lib;

final class SqlExport {
    private $db;

    public function __construct(\mysqli $db) {
        $this->db = $db;
    }

    public static function frontend(): self {
        global $DB;
        if (!(($DB['DB'] ?? null) instanceof \mysqli)) {
            throw new \RuntimeException('MySQL or MariaDB is required.');
        }
        return new self($DB['DB']);
    }

    private static function identifier(string $name): string {
        return '`' . str_replace('`', '``', $name) . '`';
    }

    private function query(string $sql, int $mode = MYSQLI_STORE_RESULT) {
        $result = $this->db->query($sql, $mode);
        if ($result === false) {
            throw new \RuntimeException('The database export failed.', $this->db->errno);
        }
        return $result;
    }

    public function tables(): array {
        $result = $this->query("SELECT TABLE_NAME AS name, ENGINE AS engine,"
            . " COALESCE(DATA_LENGTH,0)+COALESCE(INDEX_LENGTH,0) AS bytes"
            . " FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()"
            . " AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME");
        $tables = [];
        while ($row = $result->fetch_assoc()) {
            $tables[$row['name']] = $row;
        }
        return $tables;
    }

    public static function isHistoryTable(string $name): bool {
        return strncasecmp($name, 'history', 7) === 0;
    }

    public function run(callable $write, ?array $selected, string $mode, bool $excludeHistory = false): void {
        if (!in_array($mode, ['both', 'schema', 'data'], true)) {
            throw new \RuntimeException('Choose an export mode.');
        }

        $session = $this->query("SELECT @@character_set_client, @@character_set_connection,"
            . " @@character_set_results, @@collation_connection, @@time_zone")->fetch_row();
        $started = false;
        try {
            if (!$this->db->set_charset('utf8mb4')) {
                throw new \RuntimeException('The database export failed.');
            }
            $this->query("SET SESSION time_zone='+00:00'");
            $this->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $this->query('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
            $started = true;

            $available = $this->tables();
            $names = $selected ?? array_map('strval', array_keys($available));
            if (!$names) {
                throw new \RuntimeException('Select at least one table.');
            }
            foreach ($names as $name) {
                if (!is_string($name) || !isset($available[$name])) {
                    throw new \RuntimeException('The table selection is invalid. Refresh the page and retry.');
                }
            }

            $write("-- Zabbix MySQL backup\n"
                . "SET @zbx_old_sql_mode=@@sql_mode, sql_mode='NO_AUTO_VALUE_ON_ZERO';\n"
                . "SET @zbx_old_time_zone=@@time_zone, time_zone='+00:00';\n"
                . "SET @zbx_old_fk_checks=@@foreign_key_checks, foreign_key_checks=0;\n"
                . "SET @zbx_old_cs_client=@@character_set_client, @zbx_old_cs_results=@@character_set_results,"
                . " @zbx_old_collation=@@collation_connection;\n"
                . "SET NAMES utf8mb4;\n\n");

            $selectedTables = array_fill_keys($names, true);
            foreach (array_keys($available) as $name) {
                $includeRows = isset($selectedTables[$name]) && !($excludeHistory && self::isHistoryTable($name));
                $table = self::identifier($name);
                if (!$includeRows || $mode !== 'data') {
                    $ddl = $this->query('SHOW CREATE TABLE ' . $table)->fetch_row();
                    $write($ddl[1] . ";\n\n");
                }
                if ($includeRows && $mode !== 'schema') {
                    $this->dumpRows($name, $table, $write);
                }
            }

            $write("SET foreign_key_checks=@zbx_old_fk_checks;\n"
                . "SET time_zone=@zbx_old_time_zone;\nSET sql_mode=@zbx_old_sql_mode;\n"
                . "SET character_set_client=@zbx_old_cs_client, character_set_results=@zbx_old_cs_results,"
                . " collation_connection=@zbx_old_collation;\n");
            $this->query('COMMIT');
            $started = false;
        } finally {
            if ($started) {
                $this->db->rollback();
            }
            $this->db->set_charset($session[0]);
            $parts = [];
            foreach (['character_set_client', 'character_set_connection', 'character_set_results',
                'collation_connection', 'time_zone'] as $index => $variable) {
                $value = $session[$index];
                $parts[] = $variable . '=' . ($value === null ? 'NULL' : "X'" . bin2hex($value) . "'");
            }
            $this->query('SET SESSION ' . implode(', ', $parts));
        }
    }

    private function dumpRows(string $name, string $table, callable $write): void {
        $result = $this->query("SELECT COLUMN_NAME AS name, DATA_TYPE AS type, EXTRA AS extra"
            . " FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="
            . "CONVERT(X'" . bin2hex($name) . "' USING utf8mb4) ORDER BY ORDINAL_POSITION");
        $columns = $fields = $types = [];
        while ($column = $result->fetch_assoc()) {
            if (preg_match('/\b(?:VIRTUAL|STORED) GENERATED\b/i', $column['extra'])) {
                continue;
            }

            $id = self::identifier($column['name']);
            $columns[] = $id;
            $type = $column['type'];
            if (in_array($type, ['geometry', 'point', 'linestring', 'polygon', 'multipoint',
                'multilinestring', 'multipolygon', 'geometrycollection'], true)) {
                $fields[] = 'ST_AsBinary(' . $id . ')';
                $fields[] = 'ST_SRID(' . $id . ')';
                $types[] = 'geometry';
            } else {
                $fields[] = $type === 'bit' ? 'CAST(' . $id . ' AS UNSIGNED)' : $id;
                $types[] = $type;
            }
        }
        if (!$columns) {
            return;
        }

        $header = 'INSERT INTO ' . $table . ' (' . implode(',', $columns) . ') VALUES ';
        $rows = $this->query('SELECT ' . implode(',', $fields) . ' FROM ' . $table, MYSQLI_USE_RESULT);
        $batch = '';
        try {
            while ($row = $rows->fetch_row()) {
                $literals = [];
                $index = 0;
                foreach ($types as $type) {
                    $value = $row[$index++];
                    $srid = $type === 'geometry' ? $row[$index++] : null;
                    $literals[] = $this->literal($value, $type, $srid);
                }
                $tuple = '(' . implode(',', $literals) . ')';
                if ($batch !== '' && strlen($header) + strlen($batch) + strlen($tuple) > 262144) {
                    $write($header . $batch . ";\n");
                    $batch = '';
                }
                $batch .= ($batch === '' ? '' : ',') . $tuple;
            }

            if ($this->db->errno) {
                throw new \RuntimeException('The database export failed.', $this->db->errno);
            }
            if ($batch !== '') {
                $write($header . $batch . ";\n");
            }
        } finally {
            $rows->free();
        }
    }

    private function literal($value, string $type, $srid): string {
        if ($value === null) {
            return 'NULL';
        }
        if ($type === 'geometry') {
            return "ST_GeomFromWKB(X'" . bin2hex($value) . "'," . (int) $srid . ')';
        }
        if ($type === 'bit') {
            return (string) $value;
        }
        if (in_array($type, ['binary', 'varbinary', 'blob', 'tinyblob', 'mediumblob', 'longblob'], true)) {
            return "X'" . bin2hex($value) . "'";
        }
        // Match the SQL mode in the dump header even when the source uses NO_BACKSLASH_ESCAPES.
        return "'" . strtr((string) $value, ["\\" => "\\\\", "'" => "\\'", "\0" => "\\0",
            "\n" => "\\n", "\r" => "\\r", "\x1a" => "\\Z"]) . "'";
    }
}
