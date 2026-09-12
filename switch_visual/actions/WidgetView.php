<?php declare(strict_types=1);

namespace Modules\SwitchVisual\Actions;

use CControllerDashboardWidgetView;
use CControllerResponseData;

class WidgetView extends CControllerDashboardWidgetView {

    protected function doAction(): void {
        require_once __DIR__ . '/../includes/Translation.php';
        try {
            $f        = $this->fields_values;
            $hostid   = $this->extractFirstId($f['hostids'] ?? []);
            $groupids = $this->extractIds($f['hostgroups'] ?? []);

            // Target hosts: explicit host selection wins; otherwise every host
            // of the selected host groups.
            $targets = [];
            if ($hostid !== '') {
                $targets = [$hostid];
            } elseif ($groupids !== []) {
                try {
                    $rows = \API::Host()->get([
                        'output'    => ['hostid'],
                        'groupids'  => $groupids,
                        'sortfield' => 'name',
                    ]);
                    if (is_array($rows)) {
                        foreach ($rows as $row) {
                            $hid = (string) ($row['hostid'] ?? '');
                            if (ctype_digit($hid) && (int) $hid > 0) $targets[] = $hid;
                        }
                    }
                } catch (\Throwable $e) {}
            }
            $targets = array_values(array_unique($targets));

            // Resolve host names for the chassis headers
            $host_names = [];
            if ($targets !== []) {
                try {
                    $rows = \API::Host()->get([
                        'output'  => ['hostid', 'name'],
                        'hostids' => $targets,
                    ]);
                    if (is_array($rows)) {
                        foreach ($rows as $row) {
                            $host_names[(string) $row['hostid']] = (string) $row['name'];
                        }
                    }
                } catch (\Throwable $e) {}
            }

            $hosts = [];
            if ($targets !== []) {
                require_once __DIR__ . '/../includes/DataFetcher.php';

                $base_config = [
                    'num_ports'           => (int)    ($f['num_ports']       ?? 24),
                    'rj45_pos'            => ((int) ($f['rj45_pos'] ?? 0) === 1) ? 'back' : 'front',
                    'num_sfp'             => (int)    ($f['num_sfp']         ?? 2),
                    'bw_in_pattern'       => trim((string) ($f['bw_in_pattern']   ?? 'ifInOctets[*]')),
                    'bw_out_pattern'      => trim((string) ($f['bw_out_pattern']  ?? 'ifOutOctets[*]')),
                    'bw_bits'             => (bool)(int)($f['bw_bits'] ?? 1),
                    'status_pattern'      => trim((string) ($f['status_pattern']  ?? 'ifOperStatus[*]')),
                    'speed_pattern'       => trim((string) ($f['speed_pattern']   ?? 'ifHighSpeed[*]')),
                    'alias_pattern'       => trim((string) ($f['alias_pattern']   ?? '')),
                    'err_in_pattern'      => trim((string) ($f['err_in_pattern']  ?? 'ifInErrors[*]')),
                    'err_out_pattern'     => trim((string) ($f['err_out_pattern'] ?? 'ifOutErrors[*]')),
                    'uptime_key'          => trim((string) ($f['uptime_key']      ?? '')),
                    'serial_key'          => trim((string) ($f['serial_key']      ?? '')),
                    'model_key'           => trim((string) ($f['model_key']       ?? '')),
                    'cpu_key'             => trim((string) ($f['cpu_key']         ?? '')),
                    'memory_key'          => trim((string) ($f['memory_key']      ?? '')),
                    'temperature_key'     => trim((string) ($f['temperature_key']     ?? '')),
                    'temperature_threshold' => max(0.0, (float) ($f['temperature_threshold'] ?? 0)),
                    'poe_total_key'       => trim((string) ($f['poe_total_key']       ?? '')),
                    'poe_max_key'         => trim((string) ($f['poe_max_key']         ?? '')),
                    'port_index_start'    => max(1, (int)  ($f['port_index_start']    ?? 1)),
                    'sparkline_minutes'   => max(5, (int)  ($f['sparkline_minutes']   ?? 30)),
                    'auto_detect_ports'   => (bool)(int)  ($f['auto_detect_ports']    ?? 0),
                    'exclude_ports'       => trim((string) ($f['exclude_ports']       ?? '')),
                    'warn_util_threshold' => max(1.0, min(100.0, (float) ($f['util_threshold'] ?? 80))),
                    'poe_pattern'         => trim((string) ($f['poe_pattern']         ?? '')),
                    'poe_pwr_pattern'     => trim((string) ($f['poe_pwr_pattern']     ?? '')),
                    'duplex_pattern'      => trim((string) ($f['duplex_pattern']      ?? '')),
                    'sfp_rx_pwr_pattern'  => trim((string) ($f['sfp_rx_pwr_pattern']  ?? '')),
                    'sfp_tx_pwr_pattern'  => trim((string) ($f['sfp_tx_pwr_pattern']  ?? '')),
                    'fan_pattern'         => trim((string) ($f['fan_pattern']         ?? '')),
                    'fan_ok_value'        => max(1, (int)  ($f['fan_ok_value']        ?? 3)),
                ];

                foreach ($targets as $hid) {
                    $fetcher = new \Modules\SwitchVisual\Includes\DataFetcher(
                        ['hostid' => $hid] + $base_config
                    );
                    $result = $fetcher->fetchAll();

                    // The view splits RJ45/SFP per stack member from each port's
                    // is_sfp flag — num_ports stays exactly as configured.
                    $hosts[] = [
                        'hostid'           => $hid,
                        'name'             => $host_names[$hid] ?? $hid,
                        'num_ports'        => (int)    ($f['num_ports'] ?? 24),
                        'ports'            => $result['ports'],
                        'summary'          => $result['summary'],
                        'port_aliases'     => $result['port_aliases'],
                        'global_sparkline' => (string) ($result['global_sparkline'] ?? ''),
                        'global_peak_rx'   => (string) ($result['global_peak_rx']   ?? ''),
                        'global_peak_tx'   => (string) ($result['global_peak_tx']   ?? ''),
                    ];
                }
            }

            // Widget name: try request input first (live name), then fields, then default.
            $widget_name = trim((string) $this->getInput('name', ''));
            if ($widget_name === '' && method_exists($this->widget, 'getName')) {
                $widget_name = trim((string) $this->widget->getName());
            }
            if ($widget_name === '') {
                $widget_name = \Modules\SwitchVisual\Includes\Translation::t('Switch Visual');
            }

            $this->setResponse(new CControllerResponseData([
                'name'        => $widget_name,
                'widget_name' => $widget_name,
                'hosts'       => $hosts,
                'no_host'     => ($hosts === []),
                'error'       => null,
                'fields'      => $f,
                'user'        => ['debug_mode' => $this->getDebugMode()],
            ]));

        } catch (\Throwable $e) {
            $this->setResponse(new CControllerResponseData([
                'name'        => \Modules\SwitchVisual\Includes\Translation::t('Switch Visual'),
                'no_host'     => false,
                'error'       => get_class($e) . ': ' . $e->getMessage()
                                 . ' in ' . basename($e->getFile()) . ':' . $e->getLine(),
                'hosts'       => [],
                'fields'      => [],
                'user'        => ['debug_mode' => false],
            ]));
        }
    }

    private function extractFirstId($value): string {
        $stack = [$value];
        while ($stack !== []) {
            $current = array_pop($stack);
            if (is_array($current)) {
                foreach ($current as $k => $v) {
                    if (is_scalar($k)) {
                        $key = trim((string) $k);
                        if (ctype_digit($key) && (int) $key > 0) return $key;
                    }
                    $stack[] = $v;
                }
                continue;
            }
            if (is_scalar($current)) {
                $text = trim((string) $current);
                if (ctype_digit($text) && (int) $text > 0) return $text;
            }
        }
        return '';
    }

    /**
     * Collect every numeric id found in a multi-select field value.
     * Handles both storage layouts: plain id arrays and id => name maps.
     *
     * @return string[]
     */
    private function extractIds($value): array {
        $ids    = [];
        $stack  = [$value];
        while ($stack !== []) {
            $current = array_pop($stack);
            if (is_array($current)) {
                foreach ($current as $k => $v) {
                    if (is_scalar($k)) {
                        $key = trim((string) $k);
                        if (ctype_digit($key) && (int) $key > 0) $ids[] = $key;
                    }
                    $stack[] = $v;
                }
                continue;
            }
            if (is_scalar($current)) {
                $text = trim((string) $current);
                if (ctype_digit($text) && (int) $text > 0) $ids[] = $text;
            }
        }
        return array_values(array_unique($ids));
    }
}
