<?php

namespace Modules\ZabbixDatabaseExport\Lib;

final class PostgresqlExport {
    private $db;
    private $config;
    private $schema;

    public function __construct($db, array $config) {
        $this->db = $db;
        $this->config = $config;
        $this->schema = ($config['SCHEMA'] ?? '') ?: 'public';
    }

    public function info(): array {
        return ['type' => 'PostgreSQL', 'version' => pg_parameter_status($this->db, 'server_version')];
    }

    private function query(string $sql) {
        $result = @pg_query($this->db, $sql);
        if ($result === false) {
            throw new \RuntimeException('The PostgreSQL export failed.');
        }
        return $result;
    }

    private static function identifier(string $name): string {
        return '"' . str_replace('"', '""', $name) . '"';
    }

    private function pattern(string $table): string {
        // pg_dump accepts patterns. Quoted identifiers make wildcard characters literal.
        return self::identifier($this->schema) . '.' . self::identifier($table);
    }

    public function tables(): array {
        $schema = pg_escape_literal($this->db, $this->schema);
        $result = $this->query("SELECT c.relname AS name, 'PostgreSQL' AS engine,"
            . " pg_total_relation_size(c.oid)::text AS bytes"
            . " FROM pg_catalog.pg_class c JOIN pg_catalog.pg_namespace n ON n.oid=c.relnamespace"
            . " WHERE n.nspname=" . $schema . " AND c.relkind IN ('r','p') ORDER BY c.relname");
        $tables = [];
        while ($row = pg_fetch_assoc($result)) {
            $tables[$row['name']] = $row;
        }
        return $tables;
    }

    public function run(callable $write, ?array $selected, string $mode, bool $excludeHistory = false): void {
        if (!in_array($mode, ['both', 'schema', 'data'], true)) {
            throw new \RuntimeException('Choose an export mode.');
        }
        if (!function_exists('proc_open')) {
            throw new \RuntimeException('PostgreSQL export requires proc_open and a compatible pg_dump client.');
        }

        $binary = $this->binary();
        $this->query('BEGIN ISOLATION LEVEL REPEATABLE READ READ ONLY');
        $passfile = null;
        try {
            $this->query("SET LOCAL lock_timeout='10s'");
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
            // Keep table definitions stable across the three pg_dump sections.
            foreach (array_keys($available) as $name) {
                $this->query('LOCK TABLE ' . $this->pattern($name) . ' IN ACCESS SHARE MODE');
            }
            $snapshot = pg_fetch_result($this->query('SELECT pg_export_snapshot()'), 0, 0);
            $dataTables = array_fill_keys($names, true);
            foreach (array_keys($available) as $name) {
                if ($excludeHistory && SqlExport::isHistoryTable($name)) {
                    unset($dataTables[$name]);
                }
            }
            $passfile = tmpfile();
            if ($passfile === false) {
                throw new \RuntimeException('Could not create a temporary backup file.');
            }
            $path = stream_get_meta_data($passfile)['uri'];
            if (!chmod($path, 0600)) {
                throw new \RuntimeException('Could not create a private backup file.');
            }
            $password = (string) ($this->config['PASSWORD'] ?? '');
            if (strpbrk($password, "\r\n") !== false) {
                throw new \RuntimeException('PostgreSQL password files cannot contain line breaks.');
            }
            BackupFile::write($passfile, '*:*:*:*:' . strtr($password, ['\\' => '\\\\', ':' => '\\:']) . "\n");
            fflush($passfile);
            $environment = $this->environment($path);
            $common = [$binary, '--format=plain', '--no-owner', '--no-privileges', '--no-password',
                '--encoding=UTF8', '--lock-wait-timeout=10000', '--schema=' . self::identifier($this->schema),
                '--snapshot=' . $snapshot];
            $structure = [];
            $includeStructure = $mode !== 'data';
            if ($mode === 'data') {
                foreach (array_keys($available) as $name) {
                    if (!isset($dataTables[$name])) {
                        $structure[] = '--table=' . $this->pattern($name);
                    }
                }
                $includeStructure = $structure !== [];
            }

            $write("-- Zabbix PostgreSQL database export\n");
            if ($includeStructure) {
                $this->dump(array_merge($common, ['--section=pre-data'], $structure), $environment, $write);
            }
            if ($mode !== 'schema' && $dataTables) {
                $dataOptions = [];
                foreach (array_keys($available) as $name) {
                    if (!isset($dataTables[$name])) {
                        $dataOptions[] = '--exclude-table-data=' . $this->pattern($name);
                    }
                }
                $this->dump(array_merge($common, ['--section=data'], $dataOptions), $environment, $write);
            }
            if ($includeStructure) {
                $this->dump(array_merge($common, ['--section=post-data'], $structure), $environment, $write);
            }
            $this->query('COMMIT');
        } catch (\Throwable $error) {
            @pg_query($this->db, 'ROLLBACK');
            throw $error;
        } finally {
            if (is_resource($passfile)) {
                fclose($passfile);
            }
        }
    }

    private function binary(): string {
        $major = (int) pg_parameter_status($this->db, 'server_version');
        foreach (['/usr/pgsql-' . $major . '/bin/pg_dump', '/usr/lib/postgresql/' . $major . '/bin/pg_dump',
            '/usr/bin/pg_dump', '/usr/local/bin/pg_dump'] as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }
        throw new \RuntimeException('PostgreSQL export requires proc_open and a compatible pg_dump client.');
    }

    private function environment(string $passfile): array {
        $env = [
            'PATH' => '/usr/bin:/bin', 'LC_ALL' => 'C', 'PGPASSFILE' => $passfile,
            'PGHOST' => pg_host($this->db), 'PGPORT' => pg_port($this->db),
            'PGDATABASE' => pg_dbname($this->db), 'PGUSER' => (string) $this->config['USER'],
            'PGCONNECT_TIMEOUT' => '10', 'PGAPPNAME' => 'zabbix_database_export'
        ];
        if (!empty($this->config['ENCRYPTION']) && !empty($this->config['CA_FILE'])) {
            $env['PGSSLMODE'] = !empty($this->config['VERIFY_HOST']) ? 'verify-full' : 'verify-ca';
            foreach (['CA_FILE' => 'PGSSLROOTCERT', 'CERT_FILE' => 'PGSSLCERT', 'KEY_FILE' => 'PGSSLKEY'] as $key => $name) {
                if (!empty($this->config[$key])) {
                    $env[$name] = $this->config[$key];
                }
            }
        }
        return $env;
    }

    private function dump(array $command, array $environment, callable $write): void {
        $errors = tmpfile();
        if ($errors === false) {
            throw new \RuntimeException('Could not create a temporary backup file.');
        }
        $process = null;
        try {
            $process = @proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => $errors],
                $pipes, null, $environment);
            if (!is_resource($process)) {
                throw new \RuntimeException('PostgreSQL export requires proc_open and a compatible pg_dump client.');
            }
            try {
                while (!feof($pipes[1])) {
                    $chunk = fgets($pipes[1], 65536);
                    if ($chunk === false) {
                        if (feof($pipes[1])) {
                            break;
                        }
                        throw new \RuntimeException('The PostgreSQL export failed.');
                    }
                    // Newly created databases already contain public on standard PostgreSQL installations.
                    if ($chunk === "CREATE SCHEMA public;\n") {
                        $chunk = "CREATE SCHEMA IF NOT EXISTS public;\n";
                    }
                    $write($chunk);
                }
            } finally {
                fclose($pipes[1]);
            }
            $status = proc_close($process);
            $process = null;
            if ($status !== 0) {
                rewind($errors);
                $diagnostic = (string) stream_get_contents($errors, 65536);
                if (strpos($diagnostic, 'server version mismatch') !== false) {
                    throw new \RuntimeException('Install a pg_dump client compatible with the PostgreSQL server version.');
                }
                // Tool diagnostics can contain connection details. Return a localized operational message.
                throw new \RuntimeException('pg_dump failed. Check its version, database access and server logs.');
            }
        } finally {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
            fclose($errors);
        }
    }
}
