<?php declare(strict_types=1);

namespace Modules\SwitchVisual\Includes;

class DataFetcher {

    private array $config;

    /** itemid → iface, populated by fetchPorts() for sparkline use */
    private array $in_itemid_map  = [];   // itemid(string) → iface
    private array $out_itemid_map = [];   // itemid(string) → iface
    /** iface → value_type for history API */
    private array $in_value_type  = [];   // iface → int
    private array $out_value_type = [];   // iface → int
    /** iface → port position */
    private array $iface_to_pos   = [];

    public function __construct(array $config) {
        $this->config = $config;
    }

    public function fetchAll(): array {
        $ports        = $this->fetchPorts();
        $port_aliases = $this->fetchAliases();
        $summary      = $this->fetchSummary();

        // Attach per-port combined RX+TX sparklines and compute global aggregate
        $sparks = $this->fetchSparklines();
        foreach ($sparks['per_port'] as $iface => $spark_url) {
            $pos = $this->iface_to_pos[$iface] ?? null;
            if ($pos !== null && isset($ports[$pos])) {
                $ports[$pos]['sparkline'] = (string) $spark_url;
            }
        }
        $global_sparkline = (string) ($sparks['global'] ?? '');
        $peak_rx_raw      = (float)  ($sparks['global_peak_rx'] ?? 0.0);
        $peak_tx_raw      = (float)  ($sparks['global_peak_tx'] ?? 0.0);
        $global_peak_rx   = $peak_rx_raw > 0.0 ? self::fmtBwShort($peak_rx_raw * 8) . 'bps' : '';
        $global_peak_tx   = $peak_tx_raw > 0.0 ? self::fmtBwShort($peak_tx_raw * 8) . 'bps' : '';

        // Mark ports with active Zabbix triggers — force state to red
        foreach ($this->fetchTriggers() as $iface => $trig) {
            $pos = $this->iface_to_pos[$iface] ?? null;
            if ($pos !== null && isset($ports[$pos])) {
                $ports[$pos]['has_active_trigger'] = true;
                $ports[$pos]['trigger_desc']       = $trig['desc'];
                $ports[$pos]['state']              = 'red';
            }
        }

        return [
            'ports'            => $ports,
            'summary'          => $summary,
            'port_aliases'     => $port_aliases,
            'global_sparkline' => $global_sparkline,
            'global_peak_rx'   => $global_peak_rx,
            'global_peak_tx'   => $global_peak_tx,
        ];
    }

    // ── Ports ──────────────────────────────────────────────────────────────────

    private function fetchPorts(): array {
        $hostid    = (string) ($this->config['hostid']      ?? '');
        $bw_bits   = (bool)   ($this->config['bw_bits']    ?? true); // true = items deliver bits/sec, not bytes/sec
        $num_ports = (int)    ($this->config['num_ports']   ?? 24);
        $num_sfp   = (int)    ($this->config['num_sfp']     ?? 2);
        $total     = $num_ports + $num_sfp;
        $in_pat    = (string) ($this->config['bw_in_pattern']   ?? 'ifInOctets[*]');
        $out_pat   = (string) ($this->config['bw_out_pattern']  ?? 'ifOutOctets[*]');
        $stat_pat  = (string) ($this->config['status_pattern']  ?? 'ifOperStatus[*]');
        $spd_pat   = (string) ($this->config['speed_pattern']   ?? 'ifHighSpeed[*]');
        $err_in_pat  = trim((string) ($this->config['err_in_pattern']  ?? 'ifInErrors[*]'));
        $err_out_pat = trim((string) ($this->config['err_out_pattern'] ?? 'ifOutErrors[*]'));
        $poe_pat     = trim((string) ($this->config['poe_pattern']        ?? ''));
        $poe_pwr_pat = trim((string) ($this->config['poe_pwr_pattern']    ?? ''));
        $duplex_pat  = trim((string) ($this->config['duplex_pattern']     ?? ''));
        $sfp_rx_pat  = trim((string) ($this->config['sfp_rx_pwr_pattern'] ?? ''));
        $sfp_tx_pat  = trim((string) ($this->config['sfp_tx_pwr_pattern'] ?? ''));

        if ($hostid === '' || strpos($in_pat, '*') === false) return [];

        try {
            $in_items = \API::Item()->get([
                'output'                 => ['itemid', 'key_', 'lastvalue', 'value_type'],
                'hostids'                => [$hostid],
                'search'                 => ['key_' => $in_pat],
                'searchWildcardsEnabled' => true,
                'webitems'               => true,
            ]);
        } catch (\Throwable $e) { return []; }

        if (!is_array($in_items) || $in_items === []) return [];

        $by_iface = [];
        foreach ($in_items as $item) {
            $iface = self::matchWildcard($in_pat, (string) $item['key_']);
            if ($iface === null) continue;
            $by_iface[$iface] = [
                'bw_in'      => (float) $item['lastvalue'] / ($bw_bits ? 8.0 : 1.0),
                'bw_in_key'  => (string) $item['key_'],
                'in_itemid'  => (string) $item['itemid'],
                'in_vtype'   => (int)    $item['value_type'],
            ];
        }

        $auto_detect = (bool)(int)($this->config['auto_detect_ports'] ?? 0);

        // Auto-detect: drop excluded interfaces (VLANs, aggregates, mgmt, ...)
        if ($auto_detect) {
            $by_iface = array_filter($by_iface, fn($v, $k): bool => !$this->isExcluded((string) $k), ARRAY_FILTER_USE_BOTH);
        }

        if ($by_iface === []) return [];

        ksort($by_iface, SORT_NATURAL);

        $port_index_start = max(1, (int) ($this->config['port_index_start'] ?? 1));
        $ifaces      = [];
        $pos_member  = [];   // global pos → stack member (0 = none/standalone)
        $pos_local   = [];   // global pos → port position within its member

        if ($port_index_start > 1) {
            // Map by absolute SNMP index: iface 4096 with start=4096 → port 1
            foreach (array_keys($by_iface) as $iface) {
                $iface = (string) $iface;
                $clean = self::cleanIface($iface);
                if (!ctype_digit($clean)) continue;
                $pos = (int) $clean - $port_index_start + 1;
                // Auto-detect: no upper bound; manual: clamp to configured total
                if ($pos >= 1 && ($auto_detect || $pos <= $total)) {
                    $this->iface_to_pos[$iface] = $pos;
                    $ifaces[] = $iface;
                }
            }
        } else {
            // Default: sequential — first N sorted ifaces map to ports 1..N
            if ($auto_detect) {
                // Stack members: "GigabitEthernet2/0/3" → member 2 (aiops rule).
                // Ports are grouped per member and sorted by physical port
                // number; members render as separate panels and the RJ45/SFP
                // split plus port numbering restart for every member. The
                // configured num_ports is the RJ45 count PER MEMBER —
                // everything beyond it is SFP; no SFP count needs configuring.
                $by_member = [];
                foreach (array_keys($by_iface) as $iface) {
                    $clean = self::cleanIface((string) $iface);
                    $by_member[self::stackMember($clean)][] = ['raw' => (string) $iface, 'num' => self::displayNum($clean, 0)];
                }
                ksort($by_member);

                // aiops rule: order each member's ports by the PHYSICAL port
                // number parsed from the interface name, never by the raw name
                // string — mixed type prefixes (GE/TE/XGE...), quotes and name
                // suffixes make natural name order differ from panel order.
                foreach ($by_member as $member => $rows) {
                    usort($rows, static fn(array $a, array $b): int =>
                        ($a['num'] <=> $b['num']) ?: strnatcmp($a['raw'], $b['raw']));
                    $by_member[$member] = array_column($rows, 'raw');
                }

                $gpos       = 0;
                foreach ($by_member as $member => $list) {
                    foreach ($list as $i => $iface) {
                        $gpos++;
                        $this->iface_to_pos[$iface]  = $gpos;
                        $pos_member[$gpos]           = (int) $member;
                        $pos_local[$gpos]            = $i + 1;
                        $ifaces[]                    = $iface;
                    }
                }
            } else {
                $ifaces = array_map('strval', array_slice(array_keys($by_iface), 0, $total));
                foreach ($ifaces as $idx => $iface) {
                    $this->iface_to_pos[$iface] = $idx + 1;
                }
            }
        }

        // Auto-detect: show every detected port (after exclusions) — the
        // RJ45/SFP split is positional per member, driven by num_ports only.
        if ($auto_detect && $ifaces !== []) {
            $total = max(array_map(fn($i) => $this->iface_to_pos[$i], $ifaces));
        }

        // RJ45 position: "front" (default) = first N ports of every member are
        // RJ45; "back" = last N are RJ45 and the SFP ports come first.
        $rj45_back = (($this->config['rj45_pos'] ?? 'front') === 'back');

        // Per-member port totals — required to split from the end ("back")
        $member_totals = [];
        foreach ($ifaces as $k => $iface) {
            $m = (int) ($pos_member[$k + 1] ?? 0);
            $member_totals[$m] = ($member_totals[$m] ?? 0) + 1;
        }

        // Fuzzy-fetch each configured wildcard pattern keyed by the matched
        // interface — supports plain LLD keys (ifOperStatus["GigabitEthernet1/0/4"])
        // as well as wrapped keys like net.if.status[ifOperStatus.4(GigabitEthernet1/0/4)].
        $pat_maps = [
            'out'     => $this->fetchPatternMap($out_pat,     $ifaces),
            'stat'    => $this->fetchPatternMap($stat_pat,    $ifaces),
            'spd'     => $this->fetchPatternMap($spd_pat,     $ifaces),
            'err_in'  => $this->fetchPatternMap($err_in_pat,  $ifaces),
            'err_out' => $this->fetchPatternMap($err_out_pat, $ifaces),
            'poe'     => $this->fetchPatternMap($poe_pat,     $ifaces),
            'poe_pwr' => $this->fetchPatternMap($poe_pwr_pat, $ifaces),
            'duplex'  => $this->fetchPatternMap($duplex_pat,  $ifaces),
            'sfp_rx'  => $this->fetchPatternMap($sfp_rx_pat,  $ifaces),
            'sfp_tx'  => $this->fetchPatternMap($sfp_tx_pat,  $ifaces),
        ];

        $ports = [];
        foreach ($ifaces as $idx => $iface) {
            $pos       = $idx + 1;
            $member    = $pos_member[$pos] ?? 0;
            $local     = $pos_local[$pos]  ?? $pos;
            $out_item  = $pat_maps['out'][$iface]     ?? null;
            $stat_item = $pat_maps['stat'][$iface]    ?? null;
            $spd_item  = $pat_maps['spd'][$iface]     ?? null;
            $err_in_i  = $pat_maps['err_in'][$iface]  ?? null;
            $err_out_i = $pat_maps['err_out'][$iface] ?? null;
            $poe_item  = $pat_maps['poe'][$iface]     ?? null;
            $poe_pwr_i = $pat_maps['poe_pwr'][$iface] ?? null;
            $duplex_i  = $pat_maps['duplex'][$iface]  ?? null;
            $sfprx_i   = $pat_maps['sfp_rx'][$iface]  ?? null;
            $sfptx_i   = $pat_maps['sfp_tx'][$iface]  ?? null;

            $status_raw       = $stat_item['value'] ?? null;
            $status           = ($status_raw === null) ? 'up' : (((string) $status_raw === '1') ? 'up' : 'down');
            $speed_negotiated = ($spd_item !== null) ? (int) $spd_item['value'] : 0;
            $bw_out           = ($out_item !== null) ? (float) $out_item['value'] / ($bw_bits ? 8.0 : 1.0) : 0.0;

            $error_rate  = (float) (($err_in_i  !== null) ? $err_in_i['value']  : 0.0)
                         + (float) (($err_out_i !== null) ? $err_out_i['value'] : 0.0);
            $last_change = (int) ($stat_item['lastchange'] ?? 0);

            $poe_status  = ($poe_item  !== null) ? (int)   $poe_item['value']  : 0;
            // poe_power_mw: milliwatts per port (RFC 3621 pethPsePortPowerConsumption); null = item not configured
            $poe_power_mw = ($poe_pwr_i !== null) ? (int)   $poe_pwr_i['value'] : null;
            $duplex_val  = ($duplex_i  !== null) ? (int)   $duplex_i['value']  : 0;
            $sfp_rx_pwr  = ($sfprx_i   !== null) ? (float) $sfprx_i['value']  : null;
            $sfp_tx_pwr  = ($sfptx_i   !== null) ? (float) $sfptx_i['value']  : null;

            // Store in/out itemids for sparkline fetching
            $in_itemid = $by_iface[$iface]['in_itemid'];
            $in_vtype  = $by_iface[$iface]['in_vtype'];
            $this->in_itemid_map[$in_itemid]  = $iface;
            $this->in_value_type[$iface]       = $in_vtype;

            if ($out_item !== null) {
                $this->out_itemid_map[$out_item['itemid']] = $iface;
                $this->out_value_type[$iface]              = (int) $out_item['value_type'];
            }

            $clean = self::cleanIface($iface);
            $raw = [
                // Display name without surrounding quotes (item keys are often
                // quoted, e.g. ifInOctets["Vlan-interface40 Interface"])
                'iface_name'         => $clean,
                'status'             => $status,
                'speed_negotiated'   => $speed_negotiated,
                'bw_in'              => $by_iface[$iface]['bw_in'],
                'bw_out'             => $bw_out,
                'bw_in_key'          => $by_iface[$iface]['bw_in_key'],
                'bw_out_key'         => ($out_item !== null) ? $out_item['key'] : '',
                'in_itemid'          => $in_itemid,
                'error_rate'         => $error_rate,
                'last_change'        => $last_change,
                // Stack member info (auto-detect only): member 0 = standalone
                'member'             => $member,
                'pos_in_member'      => $local,
                // Physical port label from the interface name (aiops rule:
                // "GigabitEthernet1/0/5" displays as port 5)
                'port_num'           => $auto_detect ? self::displayNum($clean, $local) : $pos,
                // RJ45/SFP split per member: front = first N are RJ45 (default);
                // back = last N are RJ45, i.e. the first (total − N) are SFP
                'is_sfp'             => $rj45_back
                    ? ($local <= ($member_totals[$member] ?? 0) - $num_ports)
                    : ($local > $num_ports),
                'has_active_trigger' => false,
                'trigger_desc'       => '',
                'sparkline'          => '',
                'poe_delivering'     => ($poe_status === 3),
                'poe_power_mw'       => $poe_power_mw,
                'half_duplex'        => ($duplex_val === 2),
                'sfp_rx_pwr'         => $sfp_rx_pwr,
                'sfp_tx_pwr'         => $sfp_tx_pwr,
            ];
            $ports[$pos] = self::resolveState($raw, $this->config);
        }

        for ($i = 1; $i <= $total; $i++) {
            if (!isset($ports[$i])) {
                $ports[$i] = $this->emptyPort($i, $rj45_back ? ($i <= max(0, $total - $num_ports)) : ($i > $num_ports));
            }
        }
        ksort($ports);
        return $ports;
    }

    // ── Sparklines ─────────────────────────────────────────────────────────────

    /**
     * Fetch RX + TX history for all ports and return a combined SVG
     * sparkline per iface as a base64 data URL.
     *
     * @return array<string, string>  iface → 'data:image/svg+xml;base64,...'
     */
    private function fetchSparklines(): array {
        $sparkline_minutes = max(5, min(360, (int) ($this->config['sparkline_minutes'] ?? 30)));
        $time_from         = time() - ($sparkline_minutes * 60);
        $sparklines = [];   // iface → combined data URL (string)

        $in_history  = $this->batchHistory(array_keys($this->in_itemid_map),  $time_from);
        $out_history = $this->batchHistory(array_keys($this->out_itemid_map), $time_from);

        // If items deliver bits/sec, convert to bytes/sec so all display/util logic is unit-consistent
        if ($this->config['bw_bits'] ?? true) {
            foreach ($in_history  as &$s) { $s = array_map(fn($v) => $v / 8.0, $s); } unset($s);
            foreach ($out_history as &$s) { $s = array_map(fn($v) => $v / 8.0, $s); } unset($s);
        }

        // Union of all ifaces that have any history
        $ifaces = array_unique(array_merge(
            array_values($this->in_itemid_map),
            array_values($this->out_itemid_map)
        ));

        foreach ($ifaces as $iface) {
            $in_vals  = [];
            $out_vals = [];

            foreach ($this->in_itemid_map as $itemid => $mapped) {
                if ($mapped === $iface && isset($in_history[$itemid])) {
                    $in_vals = $in_history[$itemid];
                    break;
                }
            }
            foreach ($this->out_itemid_map as $itemid => $mapped) {
                if ($mapped === $iface && isset($out_history[$itemid])) {
                    $out_vals = $out_history[$itemid];
                    break;
                }
            }

            if ($in_vals === [] && $out_vals === []) continue;

            $url = self::sparklineDual($in_vals, $out_vals);
            if ($url !== '') {
                $sparklines[$iface] = $url;
            }
        }

        // Compute global aggregate sparkline — reuses the already-fetched history, no extra API calls
        $global = $this->computeGlobalSparkline($in_history, $out_history);
        return [
            'per_port'       => $sparklines,
            'global'         => $global['svg'],
            'global_peak_rx' => $global['peak_rx'],
            'global_peak_tx' => $global['peak_tx'],
        ];
    }

    /**
     * Batch-fetch history for a list of itemids, trying float (type 0) then
     * uint64 (type 3).
     *
     * @return array<string, float[]>  itemid → ordered float values
     */
    private function batchHistory(array $itemids, int $time_from): array {
        if ($itemids === []) return [];

        $result = [];
        foreach ([0, 3] as $vtype) {   // 0=float, 3=uint64
            try {
                $rows = \API::History()->get([
                    'output'    => ['itemid', 'clock', 'value'],
                    'itemids'   => $itemids,
                    'time_from' => $time_from,
                    'history'   => $vtype,
                    'sortfield' => 'clock',
                    'sortorder' => 'ASC',
                    'limit'     => 2000,
                ]);
                if (!is_array($rows)) continue;
                foreach ($rows as $r) {
                    $result[(string) $r['itemid']][] = (float) $r['value'];
                }
            } catch (\Throwable $e) {}
        }
        return $result;
    }

    /**
     * Generate a side-by-side RX (green, left) + TX (blue, right) sparkline SVG
     * and return it as a base64 data URL.  Each panel has its own Y scale so
     * even a lightly-loaded TX line is readable when RX dominates.
     *
     * @param float[] $in_vals   RX sample values (30-minute window)
     * @param float[] $out_vals  TX sample values (30-minute window)
     */
    private static function sparklineDual(array $in_vals, array $out_vals): string {
        if (count($in_vals) < 2 && count($out_vals) < 2) return '';

        $total_w = 162; $h = 28; $pad = 2;
        $panel_w = 76;  $gap = 10;  // 76 + 10 + 76 = 162

        $make_panel = static function(array $vals, string $stroke, string $fill_rgba, float $ox)
                use ($panel_w, $h, $pad): string {
            $n = count($vals);
            if ($n < 2) return '';
            $max      = max($vals) ?: 1.0;
            $area_pts = [($ox + $pad) . ',' . $h];
            $line_pts = [];
            foreach ($vals as $i => $v) {
                $x          = $ox + $pad + ($i / ($n - 1)) * ($panel_w - 2 * $pad);
                $y          = $h - $pad - ($v / $max) * ($h - 2 * $pad);
                $pt         = round($x, 1) . ',' . round($y, 1);
                $area_pts[] = $pt;
                $line_pts[] = $pt;
            }
            $area_pts[] = ($ox + $panel_w - $pad) . ',' . $h;
            return '<polygon points="'  . implode(' ', $area_pts) . '" fill="' . $fill_rgba . '"/>'
                 . '<polyline points="' . implode(' ', $line_pts)
                 . '" fill="none" stroke="' . $stroke . '" stroke-width="1.5" stroke-linejoin="round"/>';
        };

        $tx_ox   = $panel_w + $gap;
        // Convert bytes/sec to bits/sec for display (network engineers expect Mbps/Gbps)
        $lbl_in  = count($in_vals)  >= 2 ? self::fmtBwShort(max($in_vals)  * 8) . 'bps' : '';
        $lbl_out = count($out_vals) >= 2 ? self::fmtBwShort(max($out_vals) * 8) . 'bps' : '';

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $total_w . '" height="' . $h . '">'
             // panel backgrounds
             . '<rect x="0" y="0" width="' . $panel_w . '" height="' . $h . '" fill="#0a0e14" rx="2"/>'
             . '<rect x="' . $tx_ox . '" y="0" width="' . $panel_w . '" height="' . $h . '" fill="#0a0e14" rx="2"/>'
             // chart data
             . $make_panel($in_vals,  '#27c060', 'rgba(39,192,96,0.25)',  0)
             . $make_panel($out_vals, '#4499ff', 'rgba(68,153,255,0.2)',  $tx_ox)
             // channel labels (left side)
             . '<text x="3" y="9" font-size="7" font-family="monospace" fill="#27c060" font-weight="bold">RX</text>'
             . '<text x="' . ($tx_ox + 3) . '" y="9" font-size="7" font-family="monospace" fill="#4499ff" font-weight="bold">TX</text>'
             // peak-value scale (right side)
             . ($lbl_in  !== '' ? '<text x="' . ($panel_w - 2) . '" y="10" font-size="7" font-family="monospace" fill="#38b870" font-weight="bold" text-anchor="end">' . htmlspecialchars($lbl_in)  . '</text>' : '')
             . ($lbl_out !== '' ? '<text x="' . ($tx_ox + $panel_w - 2) . '" y="10" font-size="7" font-family="monospace" fill="#5599ff" font-weight="bold" text-anchor="end">' . htmlspecialchars($lbl_out) . '</text>' : '')
             . '</svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Sum per-port history (already fetched) into 40 time-normalised bins
     * to produce a global aggregate sparkline for the whole switch.
     * No extra API calls — reuses $in_history / $out_history from fetchSparklines().
     *
     * @param array<string, float[]> $in_history   itemid → ordered float values
     * @param array<string, float[]> $out_history  itemid → ordered float values
     */
    private function computeGlobalSparkline(array $in_history, array $out_history): array {
        $n = 40;
        $in_totals  = array_fill(0, $n, 0.0);
        $out_totals = array_fill(0, $n, 0.0);

        foreach ($in_history as $series) {
            if (count($series) < 2) continue;
            foreach (self::resample($series, $n) as $i => $v) {
                $in_totals[$i] += $v;
            }
        }
        foreach ($out_history as $series) {
            if (count($series) < 2) continue;
            foreach (self::resample($series, $n) as $i => $v) {
                $out_totals[$i] += $v;
            }
        }

        if (max($in_totals) <= 0.0 && max($out_totals) <= 0.0) {
            return ['svg' => '', 'peak_rx' => 0.0, 'peak_tx' => 0.0];
        }
        return self::sparklineGlobal($in_totals, $out_totals);
    }

    /**
     * Linear-interpolation resample of $vals to exactly $n evenly-spaced points.
     *
     * @param  float[] $vals  source series (≥ 1 element)
     * @return float[]        resampled series of length $n
     */
    private static function resample(array $vals, int $n): array {
        $count = count($vals);
        if ($count === 0) return array_fill(0, $n, 0.0);
        if ($count === 1) return array_fill(0, $n, $vals[0]);
        $result = [];
        for ($i = 0; $i < $n; $i++) {
            $pos      = ($i / ($n - 1)) * ($count - 1);
            $lo       = (int) $pos;
            $hi       = min($lo + 1, $count - 1);
            $frac     = $pos - $lo;
            $result[] = $vals[$lo] * (1.0 - $frac) + $vals[$hi] * $frac;
        }
        return $result;
    }

    /**
     * Generate a full-width global aggregate sparkline (RX green + TX blue overlaid)
     * as a viewBox SVG data URL.  No fixed pixel width — CSS sets the display size.
     *
     * @param float[] $in_vals   aggregated RX values (40 points)
     * @param float[] $out_vals  aggregated TX values (40 points)
     */
    private static function sparklineGlobal(array $in_vals, array $out_vals): array {
        $n   = count($in_vals);
        $w   = 320; $h = 22; $pad = 2;
        $max = max(max($in_vals ?: [0.0]), max($out_vals ?: [0.0])) ?: 1.0;

        $make_area = static function(array $vals, string $stroke, string $fill)
                use ($n, $w, $h, $pad, $max): string {
            if ($n < 2 || max($vals) <= 0.0) return '';
            $area = [$pad . ',' . $h];
            $line = [];
            foreach ($vals as $i => $v) {
                $x      = $pad + ($i / ($n - 1)) * ($w - 2 * $pad);
                $y      = $h - $pad - ($v / $max) * ($h - 2 * $pad);
                $pt     = round($x, 1) . ',' . round($y, 1);
                $area[] = $pt;
                $line[] = $pt;
            }
            $area[] = ($w - $pad) . ',' . $h;
            return '<polygon points="' . implode(' ', $area) . '" fill="' . $fill . '"/>'
                 . '<polyline points="' . implode(' ', $line)
                 . '" fill="none" stroke="' . $stroke . '" stroke-width="1.5" stroke-linejoin="round"/>';
        };

        // No text in the SVG — text stretches with background-image on wide chassis.
        // Peak labels are rendered as HTML elements in the view instead.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $h . '" preserveAspectRatio="none">'
             . '<rect x="0" y="0" width="' . $w . '" height="' . $h . '" fill="#0a0e14" rx="3"/>'
             . $make_area($in_vals,  '#27c060', 'rgba(39,192,96,0.22)')
             . $make_area($out_vals, '#4499ff', 'rgba(68,153,255,0.18)')
             . '</svg>';

        return [
            'svg'     => 'data:image/svg+xml;base64,' . base64_encode($svg),
            'peak_rx' => count($in_vals)  ? max($in_vals)  : 0.0,
            'peak_tx' => count($out_vals) ? max($out_vals) : 0.0,
        ];
    }

    /**
     * Format a raw history value as a compact scale label for SVG.
     * No unit is assumed — values may be bytes/sec, octets, or deltas depending
     * on item configuration.  Just conveys the order of magnitude.
     */
    private static function fmtBwShort(float $val): string {
        $v = abs($val);
        if ($v >= 1e12) return round($v / 1e12, 1) . 'T';
        if ($v >= 1e9)  return round($v / 1e9,  1) . 'G';
        if ($v >= 1e6)  return round($v / 1e6,  1) . 'M';
        if ($v >= 1e3)  return round($v / 1e3,  0) . 'K';
        return max(1, (int) $v) . '';
    }

    // ── Triggers ───────────────────────────────────────────────────────────────

    /**
     * Fetch active triggers for the host and return a map of iface → trigger info
     * for ports that are currently in a problem state.  Only triggers whose items
     * match a configured port key pattern are returned.
     *
     * @return array<string, array{desc: string, priority: int}>
     */
    private function fetchTriggers(): array {
        $hostid = (string) ($this->config['hostid'] ?? '');
        if ($hostid === '' || $this->iface_to_pos === []) return [];

        $patterns = array_values(array_filter([
            $this->config['bw_in_pattern']  ?? '',
            $this->config['bw_out_pattern'] ?? '',
            $this->config['status_pattern'] ?? '',
            $this->config['speed_pattern']  ?? '',
        ], static fn(string $p): bool => strpos($p, '*') !== false));

        if ($patterns === []) return [];

        $problems = [];   // iface → ['desc' => string, 'priority' => int]
        try {
            $triggers = \API::Trigger()->get([
                'output'        => ['triggerid', 'description', 'priority', 'value'],
                'hostids'       => [$hostid],
                'filter'        => ['value' => 1],   // TRIGGER_VALUE_TRUE = problem
                'monitored'     => true,
                'skipDependent' => true,
                'active'        => true,
                'selectItems'   => ['key_'],
            ]);
            if (!is_array($triggers)) return [];

            foreach ($triggers as $trig) {
                $priority = (int)    ($trig['priority']    ?? 0);
                $desc     = (string) ($trig['description'] ?? '');
                $matched  = false;
                foreach ((array) ($trig['items'] ?? []) as $item) {
                    if ($matched) break;
                    $key = (string) ($item['key_'] ?? '');
                    foreach ($patterns as $pat) {
                        $iface = self::matchWildcard($pat, $key);
                        if ($iface !== null) {
                            // Keep highest-priority trigger per iface
                            if (!isset($problems[$iface]) || $priority > $problems[$iface]['priority']) {
                                $problems[$iface] = ['desc' => $desc, 'priority' => $priority];
                            }
                            $matched = true;
                            break;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {}

        return $problems;
    }

    // ── Aliases ────────────────────────────────────────────────────────────────

    private function fetchAliases(): array {
        $hostid    = (string) ($this->config['hostid']       ?? '');
        $alias_pat = trim((string) ($this->config['alias_pattern'] ?? ''));

        if ($hostid === '' || $alias_pat === '' || strpos($alias_pat, '*') === false) return [];

        $aliases = [];
        try {
            $items = \API::Item()->get([
                'output'                 => ['key_', 'lastvalue'],
                'hostids'                => [$hostid],
                'search'                 => ['key_' => $alias_pat],
                'searchWildcardsEnabled' => true,
                'webitems'               => true,
            ]);
            if (is_array($items)) {
                foreach ($items as $item) {
                    $iface = self::matchWildcard($alias_pat, (string) $item['key_']);
                    if ($iface !== null) {
                        $aliases[(string) $iface] = trim((string) $item['lastvalue']);
                    }
                }
            }
        } catch (\Throwable $e) {}

        if ($aliases === []) return [];

        // Reuse the position map built by fetchPorts() — it already applied
        // exclusions, port-index offsets and stack-member ordering, so alias
        // positions always match the rendered ports.
        $indexed = [];
        foreach ($aliases as $iface => $val) {
            $pos = $this->iface_to_pos[(string) $iface] ?? null;
            if ($pos !== null) {
                $indexed[$pos] = $val;
            }
        }
        return $indexed;
    }

    // ── Summary ────────────────────────────────────────────────────────────────

    private function fetchSummary(): array {
        $hostid = (string) ($this->config['hostid'] ?? '');
        $result = ['ip' => '', 'hostname' => '', 'uptime' => '', 'serial' => '', 'model' => '', 'cpu' => '', 'memory' => '', 'temperature' => '', 'temperatures' => [], 'temperature_alarm' => false, 'poe_total' => '', 'poe_max' => '', 'fan_status' => '', 'fan_failed' => 0];

        if ($hostid === '') return $result;

        try {
            $hosts = \API::Host()->get([
                'output'           => ['hostid', 'name'],
                'hostids'          => [$hostid],
                'selectInterfaces' => ['ip', 'dns', 'type', 'main', 'useip'],
            ]);
            if (is_array($hosts) && !empty($hosts[0])) {
                $result['hostname'] = (string) ($hosts[0]['name'] ?? '');
                if (!empty($hosts[0]['interfaces'])) {
                    foreach ($hosts[0]['interfaces'] as $iface) {
                        if ((int) $iface['main'] === 1) {
                            $result['ip'] = ((int) $iface['useip'] === 1) ? $iface['ip'] : $iface['dns'];
                            break;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {}

        // Exact single-key items (uptime, serial, model, PoE)
        $key_map = [];
        foreach (['uptime_key' => 'uptime', 'serial_key' => 'serial', 'model_key' => 'model',
                  'poe_total_key' => 'poe_total', 'poe_max_key' => 'poe_max'] as $cfg => $field) {
            $k = trim((string) ($this->config[$cfg] ?? ''));
            if ($k !== '') $key_map[$k] = $field;
        }

        if ($key_map !== []) {
            try {
                $items = \API::Item()->get([
                    'output'   => ['key_', 'lastvalue'],
                    'hostids'  => [$hostid],
                    'filter'   => ['key_' => array_keys($key_map)],
                    'webitems' => true,
                ]);
                if (is_array($items)) {
                    foreach ($items as $item) {
                        $field = $key_map[(string) $item['key_']] ?? null;
                        if ($field === null) continue;
                        $val = (string) $item['lastvalue'];
                        $result[$field] = ($field === 'uptime') ? self::formatUptime((float) $val) : $val;
                    }
                }
            } catch (\Throwable $e) {}
        }

        // Pattern-based metrics (CPU / memory / temperature) — wildcard or exact key
        $temp_thr = max(0.0, (float) ($this->config['temperature_threshold'] ?? 0));

        $cpu = $this->summaryPatternValues(trim((string) ($this->config['cpu_key'] ?? '')));
        if ($cpu !== [] && max($cpu) > 0.0) $result['cpu'] = (string) round(max($cpu), 1);

        $mem = $this->summaryPatternValues(trim((string) ($this->config['memory_key'] ?? '')));
        if ($mem !== [] && max($mem) > 0.0) $result['memory'] = (string) round(max($mem), 1);

        // Multiple temperature sensors: panel shows the max, hover shows all;
        // alarm flag when the hottest sensor reaches the configured threshold
        $temps = $this->summaryPatternValues(trim((string) ($this->config['temperature_key'] ?? '')));
        if ($temps !== []) {
            $result['temperature']  = (string) round(max($temps), 1);
            $result['temperatures'] = $temps;
            if ($temp_thr > 0.0 && max($temps) >= $temp_thr) {
                $result['temperature_alarm'] = true;
            }
        }

        $fan_pat = trim((string) ($this->config['fan_pattern']  ?? ''));
        $fan_ok  = (int)         ($this->config['fan_ok_value'] ?? 3);
        if ($fan_pat !== '' && $hostid !== '') {
            try {
                $fan_items = \API::Item()->get([
                    'output'                 => ['key_', 'lastvalue'],
                    'hostids'                => [$hostid],
                    'search'                 => ['key_' => $fan_pat],
                    'searchWildcardsEnabled' => true,
                    'webitems'               => true,
                ]);
                if (is_array($fan_items) && $fan_items !== []) {
                    $fan_total = count($fan_items);
                    $fan_good  = 0;
                    foreach ($fan_items as $fi) {
                        if ((int) $fi['lastvalue'] === $fan_ok) $fan_good++;
                    }
                    $result['fan_status'] = $fan_good . '/' . $fan_total;
                    $result['fan_failed'] = $fan_total - $fan_good;
                }
            } catch (\Throwable $e) {}
        }

        return $result;
    }

    // ── State resolution ───────────────────────────────────────────────────────

    public static function resolveState(array $raw, array $config = []): array {
        $speed_raw    = (int)   ($raw['speed_negotiated'] ?? 0);
        // ifHighSpeed returns Mbps (e.g. 1000 for 1 Gbps); ifSpeed returns bps (e.g. 1_000_000_000).
        // Normalize to bytes/sec so we can compare directly with bw_in/bw_out (bytes/sec).
        $speed_mbps   = $speed_raw > 1_000_000 ? (int) round($speed_raw / 1_000_000) : $speed_raw;
        $speed_Bps    = $speed_mbps * 125_000;  // 1 Mbps = 125,000 B/s
        $bw_in        = (float) ($raw['bw_in']  ?? 0.0);
        $bw_out       = (float) ($raw['bw_out'] ?? 0.0);
        $util_pct     = ($speed_Bps > 0) ? round(($bw_in  / $speed_Bps) * 100, 2) : 0.0;
        $util_pct_out = ($speed_Bps > 0) ? round(($bw_out / $speed_Bps) * 100, 2) : 0.0;

        if ($raw['status'] === 'down') {
            return array_merge($raw, ['state' => 'gray', 'util_pct' => 0.0, 'util_pct_out' => 0.0, 'warnings' => []]);
        }

        $state    = 'green';
        $warnings = [];
        $err      = (float) ($raw['error_rate'] ?? 0.0);
        $crit     = (float) ($config['crit_err_threshold']  ?? 1.0);
        $warn     = (float) ($config['warn_err_threshold']  ?? 0.1);
        $util_thr = (float) ($config['warn_util_threshold'] ?? 80.0);

        if ($err >= $crit || ($raw['has_active_trigger'] ?? false)) {
            $state = 'red';
        } elseif ($err >= $warn && $err > 0) {
            $state = 'amber';
            $warnings[] = sprintf(Translation::t('Error rate %.2f%%'), $err);
        } elseif ($util_thr > 0 && ($util_pct >= $util_thr || $util_pct_out >= $util_thr)) {
            $state = 'amber';
            $warnings[] = sprintf(Translation::t('Utilization > %.0f%%'), $util_thr);
        }

        return array_merge($raw, ['state' => $state, 'util_pct' => $util_pct, 'util_pct_out' => $util_pct_out, 'warnings' => $warnings]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Whether an interface should be excluded from auto-detected ports.
     * Entries are comma/space separated (ASCII or full-width separators):
     * pure digits match the extracted iface exactly, globs (*) match the
     * full name, anything else matches as a case-insensitive SUBSTRING —
     * so "vlan" excludes "Vlan-interface40 Interface" even when the item
     * key wraps the name in quotes.
     */
    private function isExcluded(string $iface): bool {
        $raw = trim((string) ($this->config['exclude_ports'] ?? ''));
        if ($raw === '') return false;

        $parts = preg_split('/[\s,;，、；]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($parts)) return false;

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;
            if (strpos($part, '*') !== false) {
                $re = '/^' . str_replace('\*', '.*', preg_quote($part, '/')) . '$/i';
                if (preg_match($re, $iface)) return true;
            } elseif (ctype_digit($part)) {
                if ($iface === $part) return true;
            } elseif (stripos($iface, $part) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Strip surrounding whitespace and quotes from an interface name extracted
     * from an item key.  Discovered keys are usually quoted (e.g.
     * ifInOctets["Vlan-interface40 Interface"]) — without this the exclude
     * prefix "vlan" would never match because of the leading quote.
     */
    private static function cleanIface(string $iface): string {
        return trim($iface, " \t\r\n\"'");
    }

    /**
     * Stack member number parsed from the interface name (aiops rule):
     * "GigabitEthernet2/0/3" → member 2, port 3.  Names without a three-level
     * X/Y/Z (or X_Y_Z) numeric suffix carry no member info → 0 (standalone).
     */
    private static function stackMember(string $iface): int {
        if (preg_match('/(\d+)[_\/](\d+)[_\/](\d+)/', $iface, $m)) {
            return (int) $m[1];
        }
        return 0;
    }

    /**
     * Physical port label for display: the trailing number of the interface
     * name ("GigabitEthernet1/0/5" shows as port 5, "Ethernet3" as 3), so
     * stacked members and excluded ports keep their real panel numbering.
     * Falls back to the member-local sequence number when the name carries
     * no digits, and to the sequence for numeric ifIndex-style keys.
     */
    private static function displayNum(string $iface, int $fallback): int {
        if (ctype_digit($iface)) return $fallback;
        if (preg_match('/(\d+)[_\/](\d+)[_\/](\d+)/', $iface, $m)) return (int) $m[3];
        if (preg_match_all('/(\d+)/', $iface, $ms) && isset($ms[1]) && $ms[1] !== []) {
            return (int) end($ms[1]);
        }
        return $fallback;
    }

    private static function matchWildcard(string $pattern, string $key): ?string {
        $parts  = explode('*', $pattern, 2);
        $before = preg_quote($parts[0], '/');
        $after  = isset($parts[1]) ? preg_quote($parts[1], '/') : '';
        if (!preg_match('/^' . $before . '(.+)' . $after . '$/', $key, $m)) return null;
        return $m[1];
    }

    /**
     * Fuzzy-fetch items matching a wildcard pattern (e.g. net.if.status[*],
     * net.if.speed[*]) and return them keyed by a known port iface.  When the
     * wildcard fragment is not exactly one of the port ifaces (typical for
     * net.if.* keys such as net.if.status[ifOperStatus.4(GigabitEthernet1/0/4)]),
     * the embedded interface identifier is resolved with bestIfaceMatch().
     *
     * @return array<string, array{key: string, value: string, lastchange: int, itemid: string, value_type: int}>
     */
    private function fetchPatternMap(string $pattern, array $ifaces): array {
        if ($pattern === '' || strpos($pattern, '*') === false) return [];

        try {
            $rows = \API::Item()->get([
                'output'                 => ['key_', 'lastvalue', 'lastchange', 'itemid', 'value_type'],
                'hostids'                => [(string) ($this->config['hostid'] ?? '')],
                'search'                 => ['key_' => $pattern],
                'searchWildcardsEnabled' => true,
                'webitems'               => true,
            ]);
        } catch (\Throwable $e) {
            return [];
        }

        if (!is_array($rows)) return [];

        // clean iface name → raw iface (raw keys keep quotes; matching uses cleaned names)
        $known = [];
        foreach ($ifaces as $if) {
            $known[self::cleanIface((string) $if)] = (string) $if;
        }

        $map = [];
        foreach ($rows as $r) {
            $wild = self::matchWildcard($pattern, (string) $r['key_']);
            if ($wild === null) continue;
            $clean = self::cleanIface($wild);
            $raw_iface = $known[$clean] ?? $known[$wild] ?? null;
            if ($raw_iface === null) {
                $best = self::bestIfaceMatch($clean, array_keys($known));
                if ($best === null) continue;
                $raw_iface = $known[$best];
            }
            $map[$raw_iface] = [
                'key'        => (string) $r['key_'],
                'value'      => (string) $r['lastvalue'],
                'lastchange' => (int)    ($r['lastchange'] ?? 0),
                'itemid'     => (string) $r['itemid'],
                'value_type' => (int)    $r['value_type'],
            ];
        }
        return $map;
    }

    /**
     * Pick the known iface that best matches a wildcard-matched item-key
     * fragment.  Preference is given to the match that ends closest to the
     * end of the fragment (the trailing SNMP index, e.g. the "4" in
     * ifOperStatus.4(GigabitEthernet1/0/4)) — the usual per-port id.
     *
     * @param string[] $clean_names  cleaned iface names
     */
    private static function bestIfaceMatch(string $haystack, array $clean_names): ?string {
        $best       = null;
        $best_score = -1;
        foreach ($clean_names as $name) {
            $name = (string) $name;
            if ($name === '') continue;
            $pos = strrpos($haystack, $name);
            if ($pos === false) continue;
            $score = ($pos + strlen($name)) * 1000 - strlen($name);
            if ($score > $best_score) {
                $best       = $name;
                $best_score = $score;
            }
        }
        return $best;
    }

    /**
     * Return numeric last-values for a summary metric pattern (CPU / memory /
     * temperature).  Wildcard patterns use item search; exact keys use filter.
     *
     * @return float[]
     */
    private function summaryPatternValues(string $pattern): array {
        if ($pattern === '') return [];

        try {
            if (strpos($pattern, '*') !== false) {
                $items = \API::Item()->get([
                    'output'                 => ['key_', 'lastvalue'],
                    'hostids'                => [(string) ($this->config['hostid'] ?? '')],
                    'search'                 => ['key_' => $pattern],
                    'searchWildcardsEnabled' => true,
                    'webitems'               => true,
                ]);
            } else {
                $items = \API::Item()->get([
                    'output'   => ['key_', 'lastvalue'],
                    'hostids'  => [(string) ($this->config['hostid'] ?? '')],
                    'filter'   => ['key_' => [$pattern]],
                    'webitems' => true,
                ]);
            }
        } catch (\Throwable $e) {
            return [];
        }

        if (!is_array($items)) return [];

        $values = [];
        foreach ($items as $item) {
            $values[] = (float) $item['lastvalue'];
        }
        return $values;
    }

    private function emptyPort(int $pos, bool $is_sfp): array {
        return [
            'iface_name'         => (string) $pos,
            'status'             => 'down',
            'speed_negotiated'   => 0,
            'bw_in'              => 0.0,
            'bw_out'             => 0.0,
            'bw_in_key'          => '',
            'bw_out_key'         => '',
            'error_rate'         => 0.0,
            'util_pct'           => 0.0,
            'util_pct_out'       => 0.0,
            'state'              => 'gray',
            'warnings'           => [],
            'last_change'        => 0,
            'member'             => 0,
            'pos_in_member'      => $pos,
            'port_num'           => $pos,
            'is_sfp'             => $is_sfp,
            'has_active_trigger' => false,
            'trigger_desc'       => '',
            'sparkline'          => '',
            'poe_delivering'     => false,
            'poe_power_mw'       => null,
            'half_duplex'        => false,
            'sfp_rx_pwr'         => null,
            'sfp_tx_pwr'         => null,
        ];
    }

    private static function formatUptime(float $s): string {
        if ($s <= 0.0) return '';
        $d = (int) ($s / 86400);
        $h = (int) (($s % 86400) / 3600);
        $m = (int) (($s % 3600) / 60);
        if ($d > 0) return $d . Translation::t('d') . ' ' . $h . Translation::t('h');
        if ($h > 0) return $h . Translation::t('h') . ' ' . $m . Translation::t('m');
        return $m . Translation::t('m');
    }
}
