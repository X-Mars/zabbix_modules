<?php declare(strict_types=1);

$hosts       = $data['hosts']            ?? [];
$fields      = $data['fields']           ?? [];
// Stable per-instance class — prevents dynamic CSS rules (zoom, chassis color, bg) from bleeding across widgets
$widget_uid  = 'swi' . substr(md5(serialize($fields)), 0, 7);
$no_host     = $data['no_host']          ?? false;
$error       = $data['error']            ?? null;
$widget_name = (string) ($data['widget_name'] ?? $data['name'] ?? 'Switch');
$scale           = max(0.4, min(3.0, (float) ($fields['scale'] ?? 100) / 100));
$port_rows       = max(1, min(2, (int) ($fields['port_rows']      ?? 2)));
$port_inverted   = (bool)(int)($fields['port_inverted']   ?? 0);
$port_sequential = (bool)(int)($fields['port_sequential'] ?? 0);
$port_shape      = (int)  ($fields['port_shape']          ?? 3);
if (!in_array($port_shape, [1, 2, 3], true)) $port_shape = 3;
$show_summary    = (bool)(int)($fields['show_summary']      ?? 1);
$show_port_nums  = (bool)(int)($fields['show_port_numbers'] ?? 1);
$show_port_lbls  = (bool)(int)($fields['show_port_labels']  ?? 1);

// Module-local translator (EN → zh_CN when the user language is Chinese)
$t = static fn(string $s): string => \Modules\SwitchVisual\Includes\Translation::t($s);

$sfx = static function(string $state): string {
    return ['green' => 'g', 'amber' => 'a', 'red' => 'r', 'gray' => 'z', 'blue' => 'g'][$state] ?? 'z';
};

// Compact value formatter — bytes/sec from ifInOctets/ifOutOctets
$fmt_bw = static function(float $val): string {
    $b = abs($val) * 8;  // bytes/sec → bits/sec
    if ($b >= 1e9)  return round($b / 1e9, 2) . ' Gbps';
    if ($b >= 1e6)  return round($b / 1e6, 1) . ' Mbps';
    if ($b >= 1e3)  return round($b / 1e3, 0) . ' Kbps';
    return max(0, (int) $b) . ' bps';
};

// Duration formatter for "down for X" display
$fmt_dur = static function(int $secs) use ($t): string {
    if ($secs <= 0) return '';
    $d = (int) ($secs / 86400);
    $h = (int) (($secs % 86400) / 3600);
    $m = (int) (($secs % 3600) / 60);
    if ($d > 0) return $d . $t('d') . ' ' . $h . $t('h');
    if ($h > 0) return $h . $t('h') . ' ' . $m . $t('m');
    return max(1, $m) . $t('m');
};

// .sw-outer contains the dynamic zoom — defined separately below
$css = <<<'CSS'
.sw-chassis{
    border:1px solid #1a2028;border-radius:8px;padding:10px 14px 8px 14px;
    box-shadow:inset 0 1px 0 rgba(255,255,255,.15),0 6px 20px rgba(0,0,0,.4);
    display:inline-flex;flex-direction:column;gap:8px;
}
.sw-hdr{display:flex;justify-content:space-between;align-items:baseline;gap:14px;
    padding-bottom:6px;border-bottom:1px solid rgba(255,255,255,.18);}
.sw-hdr-name{font-family:monospace;font-size:13px;font-weight:700;
    color:#e8f2fc;letter-spacing:.04em;}
.sw-hdr-left{display:flex;flex-direction:row;align-items:baseline;gap:10px;min-width:0;}
.sw-hdr-model{font-family:monospace;font-size:11px;font-weight:700;
    color:#7fb4e0;letter-spacing:.02em;opacity:.92;white-space:nowrap;}
.sw-hdr-ip{font-family:monospace;font-size:11px;color:#8aaec8;font-weight:600;}
.sw-port-area{display:flex;align-items:flex-start;gap:14px;}
.sw-rj-block{display:flex;flex-direction:column;gap:4px;}
.sw-sfp-block{display:flex;flex-direction:column;gap:4px;
    border-left:2px solid #3d4d5a;padding-left:12px;}
.sw-row{display:flex;flex-direction:row;gap:3px;align-items:flex-start;}
.sw-pw{display:flex;flex-direction:column;align-items:center;gap:2px;cursor:pointer;width:32px;overflow:hidden;}
.sw-p{width:18px;height:16px;border-radius:2px 2px 3px 3px;border:2px solid #2d3340;
    background:linear-gradient(180deg,#1c2230 0%,#0a0e16 20%,#050810 100%);
    display:flex;align-items:flex-end;justify-content:center;padding-bottom:2px;
    position:relative;
    box-shadow:inset 0 6px 10px rgba(0,0,0,.95),inset 2px 0 5px rgba(0,0,0,.55),
               inset -2px 0 5px rgba(0,0,0,.55),inset 0 -1px 1px rgba(255,255,255,.05),
               0 1px 0 rgba(255,255,255,.07);}
.sw-p::before{content:'';position:absolute;top:0;left:22%;right:22%;height:2px;
    background:linear-gradient(180deg,#2a3444 0%,#141c28 100%);border-radius:0 0 2px 2px;}
.sw-p-g{border-color:#1f9048;background:linear-gradient(180deg,#122a18 0%,#051008 20%,#020704 100%);}
.sw-p-a{border-color:#a07d00;background:linear-gradient(180deg,#201800 0%,#0c0900 20%,#060400 100%);}
.sw-p-r{border-color:#a02828;background:linear-gradient(180deg,#220a0a 0%,#0e0404 20%,#060202 100%);}
.sw-p-z{border-color:#364050;background:linear-gradient(180deg,#1c2030 0%,#0e1220 20%,#080c16 100%);}
.sw-led{width:10px;height:4px;border-radius:1px;background:#1c2030;}
.sw-led-g{background:#2ad468;box-shadow:0 0 6px rgba(39,192,96,.9),0 0 2px rgba(42,212,104,.6);animation:sw-pulse 1.4s ease-in-out infinite;}
.sw-led-a{background:#e89000;box-shadow:0 0 6px rgba(232,144,0,.9),0 0 2px rgba(224,128,0,.6);}
@keyframes sw-pulse{0%,100%{opacity:1}50%{opacity:.3}}
.sw-led-r{background:#e83838;box-shadow:0 0 6px rgba(232,56,56,.9),0 0 2px rgba(224,48,48,.6);animation:sw-pulse 1.4s ease-in-out infinite;}
.sw-led-z{background:#1c2030;}
.sw-sfp{width:26px;height:19px;border-radius:2px;border:2px solid #1a406a;
    background:linear-gradient(180deg,#0c1520 0%,#060d14 100%);
    display:flex;align-items:center;justify-content:center;position:relative;
    box-shadow:inset 0 2px 3px rgba(0,0,0,.5),inset 0 -1px 0 rgba(255,255,255,.04);}
.sw-sfp-g{border-color:#1f9048;background:linear-gradient(180deg,#071a0e 0%,#030e08 100%);}
.sw-sfp-a{border-color:#a07d00;background:linear-gradient(180deg,#130e00 0%,#0a0700 100%);}
.sw-sfp-r{border-color:#a02828;background:linear-gradient(180deg,#150404 0%,#0a0202 100%);}
.sw-sfp-z{border-color:#1a406a;background:linear-gradient(180deg,#0c1520 0%,#060d14 100%);}
.sw-sfp-dot{width:8px;height:8px;border-radius:50%;background:#1e3050;flex-shrink:0;}
.sw-sfp-dot-g{background:#2ad468;box-shadow:0 0 5px rgba(39,192,96,.9);animation:sw-pulse 1.4s ease-in-out infinite;}
.sw-sfp-dot-a{background:#e89000;box-shadow:0 0 5px rgba(232,144,0,.9);}
.sw-sfp-dot-r{background:#e83838;box-shadow:0 0 5px rgba(232,56,56,.9);animation:sw-pulse 1.4s ease-in-out infinite;}
.sw-sfp-dot-z{background:#1e3050;}
.sw-num{font-size:9px;font-family:monospace;font-weight:700;line-height:1;}
.sw-num-g{color:#30c870;}.sw-num-a{color:#d09000;}.sw-num-r{color:#c03030;}.sw-num-z{color:#5a7090;}
.sw-alias{font-size:7px;font-family:monospace;color:#c8e0f4;line-height:1;font-weight:700;
    min-height:7px;width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:center;}
.sw-sum{border-top:1px solid rgba(255,255,255,.12);padding-top:6px;
    display:flex;flex-wrap:wrap;gap:0;font-family:monospace;}
.sw-sum-k{font-size:10px;color:#6888a0;font-weight:600;}
.sw-sum-v{font-size:10px;color:#b0ccdf;font-weight:700;margin-right:12px;}
.sw-sum-alert{color:#ff4444;text-decoration:underline;animation:sw-pulse 1.4s ease-in-out infinite;}
.sw-empty{padding:18px 24px;font-size:12px;font-family:monospace;color:#c8d8e8;
    text-align:center;background:rgba(255,255,255,.07);border-radius:4px;}
.sw-err{color:#ff6060;font-weight:bold;}
.sw-warn{color:#ffd060;font-weight:bold;}
.sw-ub{height:4px;width:18px;background:#141c28;border-radius:1px;overflow:hidden;display:block;}
.sw-ubr{display:block;height:2px;background:#27c060;}
.sw-ubt{display:block;height:2px;background:#4499ff;}
.sw-ubw0{width:0;}.sw-ubw1{width:10%;}.sw-ubw2{width:20%;}.sw-ubw3{width:30%;}
.sw-ubw4{width:40%;}.sw-ubw5{width:50%;}.sw-ubw6{width:60%;}.sw-ubw7{width:70%;}
.sw-ubw8{width:80%;}.sw-ubw9{width:90%;}.sw-ubw10{width:100%;}
.sw-poe::after{content:'';position:absolute;top:1px;right:1px;width:3px;height:3px;border-radius:50%;background:#ffcc00;box-shadow:0 0 4px rgba(255,204,0,.9);}
/* Shape ports: the 1px corner is clipped away — keep the PoE dot inside the wide body step */
.sw-shape.sw-poe::after{top:26%;right:10%;}
.sw-hdx{border-style:dashed!important;}
/* Speed-tier overrides — only affect green (up) ports */
.sw-spd-100m.sw-p-g{border-color:#3a7020;background:linear-gradient(180deg,#0e2008 0%,#060c03 20%,#030601 100%);}
.sw-spd-100m .sw-led-g{background:#7ab848;box-shadow:0 0 4px rgba(100,168,56,.7);}
.sw-spd-100m.sw-sfp-g{border-color:#3a7020;background:linear-gradient(180deg,#0b1a05 0%,#060e03 100%);}
.sw-spd-100m .sw-sfp-dot-g{background:#7ab848;box-shadow:0 0 4px rgba(100,168,56,.7);}
.sw-spd-10g.sw-p-g{border-color:#00c890;background:linear-gradient(180deg,#002c22 0%,#000e0c 20%,#000806 100%);}
.sw-spd-10g .sw-led-g{background:#00f0b0;box-shadow:0 0 9px rgba(0,230,170,1),0 0 3px rgba(0,255,200,.7);}
.sw-spd-10g.sw-sfp-g{border-color:#00c890;background:linear-gradient(180deg,#001f18 0%,#000e0c 100%);}
.sw-spd-10g .sw-sfp-dot-g{background:#00f0b0;box-shadow:0 0 9px rgba(0,230,170,1);}
.sw-warn-icon{font-size:11px;color:#ffcc00;font-weight:700;cursor:pointer;
    padding:1px 6px;border-radius:3px;background:rgba(255,200,0,.18);flex-shrink:0;}
/* Global aggregate sparkline */
.sw-gspk-wrap{padding:3px 0 1px;border-top:1px solid rgba(255,255,255,.08);position:relative;}
.sw-gspk{width:100%;height:22px;background-size:100% 100%;background-repeat:no-repeat;
    border-radius:2px;}
.sw-gspk-legend{position:absolute;top:4px;right:5px;display:flex;gap:8px;font-size:9px;
    line-height:1;pointer-events:none;text-shadow:0 0 4px #000;}
/* ── Port shape styles 1 & 2 (stepped / metal — aiops design; style 3 = classic above) ── */
.sw-slot{height:32px;display:flex;align-items:flex-end;justify-content:center;}
.sw-shape{position:relative;flex-shrink:0;}
.sw-shape-rj{width:32px;height:32px;}
.sw-shape-sfp{width:34px;height:28px;}
/* Clipped body — state fills / metal inner live inside; LED triangles stay unclipped on the root */
.sw-body{position:absolute;inset:0;}
.sw-body-rj{clip-path:polygon(70% 0%,70% 9.4%,80% 9.4%,80% 21.9%,100% 21.9%,100% 100%,0% 100%,0% 21.9%,20% 21.9%,20% 9.4%,30% 9.4%,30% 0%);}
.sw-body-rj.sw-down{clip-path:polygon(70% 100%,70% 90.6%,80% 90.6%,80% 78.1%,100% 78.1%,100% 0%,0% 0%,0% 78.1%,20% 78.1%,20% 90.6%,30% 90.6%,30% 100%);}
.sw-body-sfp{clip-path:polygon(70% 0%,70% 10.7%,100% 10.7%,100% 100%,0% 100%,0% 10.7%,30% 10.7%,30% 0%);}
.sw-body-sfp.sw-down{clip-path:polygon(70% 100%,70% 89.3%,100% 89.3%,100% 0%,0% 0%,0% 89.3%,30% 89.3%,30% 100%);}
/* solid state fills (style 1 + LED triangles) — overridden per widget by the color fields */
.sw-c-g{background:#2ad468;}
.sw-c-a{background:#e89000;}
.sw-c-r{background:#e83838;}
.sw-c-z{background:#1f2730;}
.sw-spd-100m.sw-c-g{background:#c8a020;}
.sw-spd-10g.sw-c-g{background:#2090e0;}
/* style 2: metal body + state-colored socket (+ 8 gold pins on RJ45) */
.sw-s2 .sw-s2-in{position:absolute;inset:0;
    background:linear-gradient(180deg,#e3e8ec 0%,#aab4bd 22%,#7e8a94 48%,#b9c2ca 75%,#6d7883 100%);}
.sw-s2.sw-down .sw-s2-in{transform:scaleY(-1);}
.sw-s2-hl{position:absolute;left:0;right:0;top:0;height:46%;
    background:linear-gradient(180deg,rgba(255,255,255,.45),rgba(255,255,255,0));}
.sw-s2-socket{position:absolute;left:10%;right:10%;top:25%;height:70%;border-radius:1px;
    background:linear-gradient(180deg,#05070a 0%,#141920 55%,#05070a 100%);
    box-shadow:inset 0 0 2px rgba(0,0,0,.9);}
/* socket filled with the port state color (like style 1 bodies) — base defaults,
   overridden per widget by the configured color fields */
.sw-s2 .sw-s2-socket.sw-c-g{background:#2ad468;}
.sw-s2 .sw-s2-socket.sw-c-a{background:#e89000;}
.sw-s2 .sw-s2-socket.sw-c-r{background:#e83838;}
.sw-s2 .sw-s2-socket.sw-c-z{background:#1f2730;}
.sw-s2 .sw-s2-socket.sw-spd-100m.sw-c-g{background:#c8a020;}
.sw-s2 .sw-s2-socket.sw-spd-10g.sw-c-g{background:#2090e0;}
.sw-s2-pin{position:absolute;top:78%;width:1px;height:14%;
    background:linear-gradient(180deg,#e2c069,#8a6a24);}
/* LED triangles: left up-tri = own status (always rendered); right down-tri = paired bottom port (top row of 2-row layout only) */
.sw-tri{position:absolute;width:9px;height:8px;top:-4px;opacity:.3;}
.sw-tri-sfp{top:-8px;}
.sw-tri-up{left:-3px;clip-path:polygon(50% 0,100% 100%,0 100%);}
.sw-tri-down{right:-2px;clip-path:polygon(50% 100%,100% 0,0 0);}
.sw-tri-lit{opacity:1;}
.sw-tri-lit.sw-c-g,.sw-tri-lit.sw-c-r{animation:sw-pulse 1.4s ease-in-out infinite;}
/* ── Stack members: one labelled panel per member (auto-detect, multi-member) ── */
.sw-stacked{flex-direction:column;gap:12px;}
.sw-member-sec{display:flex;flex-direction:column;gap:5px;}
.sw-member-hdr{display:flex;align-items:baseline;gap:10px;font-family:monospace;}
.sw-member-name{font-size:11px;font-weight:700;color:#e8f2fc;letter-spacing:.04em;}
.sw-member-stats{font-size:9px;color:#8aaec8;font-weight:600;}
CSS;

// Helper: derive scoped port-color CSS from a 6-char hex + tier identifier.
// Tiers: '' = 1 Gbps base, '100m', '10g', 'alert', 'error'.
$port_color_css = static function(string $hex, string $tier, string $uid): string {
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $brd = sprintf('%02x%02x%02x', (int)($r * .65), (int)($g * .65), (int)($b * .65));
    $bh  = sprintf('%02x%02x%02x', (int)($r * .18), (int)($g * .18), (int)($b * .18));
    $bm  = sprintf('%02x%02x%02x', (int)($r * .08), (int)($g * .08), (int)($b * .08));
    $bl  = sprintf('%02x%02x%02x', (int)($r * .04), (int)($g * .04), (int)($b * .04));
    $nc  = sprintf('%02x%02x%02x', (int)($r * .70), (int)($g * .70), (int)($b * .70));
    $u   = ".sw-outer.{$uid} ";
    $css = '';
    switch ($tier) {
        case '': // 1 Gbps — override the base green classes
            $css .= "{$u}.sw-p-g{border-color:#{$brd};background:linear-gradient(180deg,#{$bh} 0%,#{$bm} 20%,#{$bl} 100%);}";
            $css .= "{$u}.sw-led-g{background:#{$hex};box-shadow:0 0 6px rgba({$r},{$g},{$b},.9),0 0 2px rgba({$r},{$g},{$b},.6);}";
            $css .= "{$u}.sw-sfp-g{border-color:#{$brd};background:linear-gradient(180deg,#{$bh} 0%,#{$bm} 100%);}";
            $css .= "{$u}.sw-sfp-dot-g{background:#{$hex};box-shadow:0 0 5px rgba({$r},{$g},{$b},.9);}";
            $css .= "{$u}.sw-num-g{color:#{$nc};}";
            break;
        case '100m':
            $css .= "{$u}.sw-spd-100m.sw-p-g{border-color:#{$brd};background:linear-gradient(180deg,#{$bh} 0%,#{$bm} 20%,#{$bl} 100%);}";
            $css .= "{$u}.sw-spd-100m .sw-led-g{background:#{$hex};box-shadow:0 0 4px rgba({$r},{$g},{$b},.7);}";
            $css .= "{$u}.sw-spd-100m.sw-sfp-g{border-color:#{$brd};background:linear-gradient(180deg,#{$bh} 0%,#{$bm} 100%);}";
            $css .= "{$u}.sw-spd-100m .sw-sfp-dot-g{background:#{$hex};box-shadow:0 0 4px rgba({$r},{$g},{$b},.7);}";
            break;
        case '10g':
            $css .= "{$u}.sw-spd-10g.sw-p-g{border-color:#{$brd};background:linear-gradient(180deg,#{$bh} 0%,#{$bm} 20%,#{$bl} 100%);}";
            $css .= "{$u}.sw-spd-10g .sw-led-g{background:#{$hex};box-shadow:0 0 9px rgba({$r},{$g},{$b},1),0 0 3px rgba({$r},{$g},{$b},.7);}";
            $css .= "{$u}.sw-spd-10g.sw-sfp-g{border-color:#{$brd};background:linear-gradient(180deg,#{$bh} 0%,#{$bm} 100%);}";
            $css .= "{$u}.sw-spd-10g .sw-sfp-dot-g{background:#{$hex};box-shadow:0 0 9px rgba({$r},{$g},{$b},1);}";
            break;
        case 'alert':
            $css .= "{$u}.sw-p-a{border-color:#{$brd};background:linear-gradient(180deg,#{$bh} 0%,#{$bm} 20%,#{$bl} 100%);}";
            $css .= "{$u}.sw-led-a{background:#{$hex};box-shadow:0 0 6px rgba({$r},{$g},{$b},.9),0 0 2px rgba({$r},{$g},{$b},.6);}";
            $css .= "{$u}.sw-sfp-a{border-color:#{$brd};background:linear-gradient(180deg,#{$bh} 0%,#{$bm} 100%);}";
            $css .= "{$u}.sw-sfp-dot-a{background:#{$hex};box-shadow:0 0 5px rgba({$r},{$g},{$b},.9);}";
            $css .= "{$u}.sw-num-a{color:#{$nc};}";
            break;
        case 'error':
            $css .= "{$u}.sw-p-r{border-color:#{$brd};background:linear-gradient(180deg,#{$bh} 0%,#{$bm} 20%,#{$bl} 100%);}";
            $css .= "{$u}.sw-led-r{background:#{$hex};box-shadow:0 0 6px rgba({$r},{$g},{$b},.9),0 0 2px rgba({$r},{$g},{$b},.6);animation:sw-pulse 1.4s ease-in-out infinite;}";
            $css .= "{$u}.sw-sfp-r{border-color:#{$brd};background:linear-gradient(180deg,#{$bh} 0%,#{$bm} 100%);}";
            $css .= "{$u}.sw-sfp-dot-r{background:#{$hex};box-shadow:0 0 5px rgba({$r},{$g},{$b},.9);animation:sw-pulse 1.4s ease-in-out infinite;}";
            $css .= "{$u}.sw-num-r{color:#{$nc};}";
            break;
    }
    return $css;
};

// Chassis background — custom color (stored without # by CWidgetFieldColor) or default grey gradient
// Strip '#' for backward-compat with old text-field values that included it.
$chassis_color = ltrim(trim((string) ($fields['chassis_color'] ?? '')), '#');
if ($chassis_color !== '' && preg_match('/^[0-9a-fA-F]{6}$/i', $chassis_color)) {
    $r  = hexdec(substr($chassis_color, 0, 2));
    $g  = hexdec(substr($chassis_color, 2, 2));
    $b  = hexdec(substr($chassis_color, 4, 2));
    $hi = sprintf('%02x%02x%02x', min(255,(int)($r*1.35)), min(255,(int)($g*1.35)), min(255,(int)($b*1.35)));
    $lo = sprintf('%02x%02x%02x', (int)($r*.70), (int)($g*.70), (int)($b*.70));
    $css .= '.sw-outer.' . $widget_uid . ' .sw-chassis{background:linear-gradient(175deg,#' . $hi . ' 0%,#' . $chassis_color . ' 16%,#' . $lo . ' 100%);}';

    // Auto-contrast: override chassis text colours for light chassis backgrounds (luminance > 0.45)
    $lum = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    if ($lum > 0.45) {
        $uid = '.sw-outer.' . $widget_uid;
        $css .= $uid . ' .sw-hdr-name{color:#0e1a28;}';
        $css .= $uid . ' .sw-hdr-model{color:#1a3050;}';
        $css .= $uid . ' .sw-hdr-ip{color:#1a3050;}';
        $css .= $uid . ' .sw-hdr{border-color:rgba(0,0,0,.25);}';
        $css .= $uid . ' .sw-alias{color:#1a2838;}';
        $css .= $uid . ' .sw-num-z{color:#3a4a60;}';
        $css .= $uid . ' .sw-sum{border-color:rgba(0,0,0,.2);}';
        $css .= $uid . ' .sw-sum-k{color:#1a2a40;}';
        $css .= $uid . ' .sw-sum-v{color:#0e1a28;}';
        $css .= $uid . ' .sw-sfp-block{border-color:#2a3a50;}';
        $css .= $uid . ' .sw-warn-icon{color:#7a4a00;background:rgba(180,100,0,.15);}';
        $css .= $uid . ' .sw-member-name{color:#0e1a28;}';
        $css .= $uid . ' .sw-member-stats{color:#1a3050;}';
    }
} else {
    $css .= '.sw-outer.' . $widget_uid . ' .sw-chassis{background:linear-gradient(175deg,#606b78 0%,#404c58 16%,#2c333c 100%);}';
}

// Port speed-tier and state colors — dynamic overrides scoped to this widget instance
foreach ([
    'color_1g'    => '',
    'color_100m'  => '100m',
    'color_10g'   => '10g',
    'color_alert' => 'alert',
    'color_error' => 'error',
] as $field => $tier) {
    $hex = ltrim(trim((string) ($fields[$field] ?? '')), '#');
    if ($hex !== '' && preg_match('/^[0-9a-fA-F]{6}$/i', $hex)) {
        $css .= $port_color_css($hex, $tier, $widget_uid);

        // Solid fills for port shape styles 1 & 2 (stepped/metal bodies + LED triangles)
        $su = '.sw-outer.' . $widget_uid;
        if ($tier === '')          $css .= "{$su} .sw-c-g{background:#{$hex};}";
        elseif ($tier === '100m')  $css .= "{$su} .sw-spd-100m.sw-c-g{background:#{$hex};}";
        elseif ($tier === '10g')   $css .= "{$su} .sw-spd-10g.sw-c-g{background:#{$hex};}";
        elseif ($tier === 'alert') $css .= "{$su} .sw-c-a{background:#{$hex};}";
        elseif ($tier === 'error') $css .= "{$su} .sw-c-r{background:#{$hex};}";
    }
}

// Shape styles 1/2: widen port cells (34px SFP), let LED triangles overhang, stretch util bars
if ($port_shape !== 3) {
    $css .= '.sw-outer.' . $widget_uid . ' .sw-pw{width:34px;overflow:visible;}';
    $css .= '.sw-outer.' . $widget_uid . ' .sw-ub{width:32px;}';
}

// Dynamic rules scoped to this widget instance via $widget_uid — prevents bleed across multiple switch widgets
$css .= '.sw-outer.' . $widget_uid . '{display:block;width:100%;padding:4px;box-sizing:border-box;zoom:' . $scale . ';background:transparent;}';
$css .= 'div:has(>.sw-outer.' . $widget_uid . '){background:transparent!important;box-shadow:none!important;}';

// Tooltip CSS — injected inside each hint so it applies in Zabbix's hint popup container
$tip_css = '.swt-wrap{font-family:monospace;min-width:170px;padding:8px 10px;'
         .     'background:#111820;border:1px solid #2a3a4a;border-radius:5px;'
         .     'box-shadow:0 4px 16px rgba(0,0,0,.7);}'
         . '.swt-name{font-size:12px;font-weight:700;color:#e8f4ff;margin-bottom:7px;'
         .     'border-bottom:1px solid #2a3a4a;padding-bottom:5px;}'
         . '.swt-g{display:grid;grid-template-columns:max-content 1fr;gap:4px 14px;font-size:11px;}'
         . '.swt-k{color:#7ab0d0;font-weight:600;}'
         . '.swt-v{color:#e8f4ff;font-weight:700;}'
         . '.swt-w{color:#ffcc44;font-size:10px;font-weight:700;margin-top:5px;}'
         . '.swt-sl{margin-top:9px;}'
         . '.swt-sl-lbl{font-size:9px;color:#7ab0d0;font-weight:600;margin-bottom:3px;}'
         . '.swt-spark{width:162px;height:28px;display:block;border-radius:2px;}';

// ── Tooltip builder ────────────────────────────────────────────────────────────
$make_tip = static function(int $pos, array $port, string $alias) use ($tip_css, $fmt_bw, $fmt_dur, $t, $fields): CDiv {
    $state     = $port['state'] ?? 'gray';
    $speed     = (int) ($port['speed_negotiated'] ?? 0);
    $iface     = (string) ($port['iface_name'] ?? ('Port ' . $pos));
    $label     = $alias !== '' ? $alias . '  (' . $iface . ')' : $iface;
    $sparkline = (string) ($port['sparkline'] ?? '');

    // Unique CSS class per port so sparkline data URLs don't collide.
    // Keyed by itemid — stacked members / multiple hosts repeat port numbers.
    $cid       = 'swt-s' . (((string) ($port['in_itemid'] ?? '')) !== ''
        ? (string) $port['in_itemid']
        : 'p' . $pos);
    $spark_css = $sparkline !== ''
        ? '.' . $cid . '{background-image:url("' . $sparkline . '");background-size:162px 28px;background-repeat:no-repeat;}'
        : '';

    // Normalize speed to Mbps: ifSpeed returns bps (1e9 for 1 Gbps); ifHighSpeed returns Mbps (1000 for 1 Gbps)
    $speed_mbps = $speed > 1000000 ? (int) round($speed / 1e6) : $speed;

    // Speed label: show negotiated speed with unit
    $spd_label = 'N/A';
    if ($speed_mbps > 0) {
        $spd_label = $speed_mbps >= 1000 ? round($speed_mbps / 1000, 0) . ' Gbps' : $speed_mbps . ' Mbps';
    }

    // Port type includes SFP/RJ45 marker
    $type_label = (!empty($port['is_sfp']) ? 'SFP' : 'RJ45') . ($speed_mbps >= 10000 ? ' 10G' : ($speed_mbps >= 1000 ? ' 1G' : ($speed_mbps >= 100 ? ' 100M' : '')));

    // Stats grid
    $grid_rows = [
        $t('Type')     => $type_label,
        $t('Status')   => strtoupper($t($state)),
        $t('Speed')    => $spd_label,
        $t('RX')       => $fmt_bw((float) ($port['bw_in']  ?? 0.0)),
        $t('TX')       => $fmt_bw((float) ($port['bw_out'] ?? 0.0)),
        $t('Util')     => number_format((float) ($port['util_pct'] ?? 0.0), 1) . '%',
    ];
    // Show "Down for" when port is inactive and we have a timestamp
    if ($state === 'gray') {
        $lc = (int) ($port['last_change'] ?? 0);
        if ($lc > 0) {
            $grid_rows[$t('Down for')] = $fmt_dur(time() - $lc);
        }
    }
    if ($port['poe_delivering'] ?? false) {
        $poe_mw = $port['poe_power_mw'] ?? null;
        $grid_rows['PoE'] = ($poe_mw !== null) ? number_format($poe_mw / 1000, 1) . ' W' : $t('Delivering');
    }
    if ($port['half_duplex']    ?? false) $grid_rows[$t('Duplex')] = $t('Half (!)');
    if (($sfp_rxp = $port['sfp_rx_pwr'] ?? null) !== null) $grid_rows[$t('Opt RX')] = number_format((float) $sfp_rxp, 1) . ' dBm';
    if (($sfp_txp = $port['sfp_tx_pwr'] ?? null) !== null) $grid_rows[$t('Opt TX')] = number_format((float) $sfp_txp, 1) . ' dBm';

    $grid = (new CDiv())->addClass('swt-g');
    foreach ($grid_rows as $k => $v) {
        $grid->addItem((new CSpan($k . ':'))->addClass('swt-k'));
        $grid->addItem((new CSpan($v))->addClass('swt-v'));
    }

    // Side-by-side RX+TX sparkline — labels embedded in SVG (inside-out before addItem)
    $spark_section = null;
    if ($sparkline !== '') {
        $spark_min = (int) ($fields['sparkline_minutes'] ?? 30);
        $spark_lbl = $spark_min >= 60
            ? $t('Traffic') . ' — ' . round($spark_min / 60, 1) . ' ' . $t('h')
            : $t('Traffic') . ' — ' . $spark_min . ' ' . $t('min');
        $lbl  = (new CDiv($spark_lbl))->addClass('swt-sl-lbl');
        $bar  = (new CDiv())->addClass('swt-spark ' . $cid);
        $spark_section = (new CDiv())->addClass('swt-sl');
        $spark_section->addItem($lbl);
        $spark_section->addItem($bar);
    }

    // Assemble tooltip (inside-out: all children complete before addItem)
    $name_div = (new CDiv($label))->addClass('swt-name');

    $tip = (new CDiv())->addClass('swt-wrap');
    $tip->addItem(new CTag('style', true, $tip_css . $spark_css));
    $tip->addItem($name_div);
    $tip->addItem($grid);
    foreach ((array) ($port['warnings'] ?? []) as $w) {
        $tip->addItem((new CDiv('! ' . $w))->addClass('swt-w'));
    }
    if ($spark_section !== null) {
        $tip->addItem($spark_section);
    }
    return $tip;
};

// Speed-tier class for solid fills / LED triangles — '' = 1 Gbps base,
// ' sw-spd-100m' (amber tint), ' sw-spd-10g' (blue tint)
$spd_cls_of = static function(?array $p): string {
    if ($p === null) return '';
    $v = (int) ($p['speed_negotiated'] ?? 0);
    $m = $v > 1000000 ? (int) round($v / 1e6) : $v;
    if ($m >= 10000) return ' sw-spd-10g';
    if ($m > 0 && $m <= 100) return ' sw-spd-100m';
    return '';
};

// ── Shape port cell builder (styles 1 & 2) ────────────────────────────────────
// $row: 0 = top row, 1 = bottom row. RJ45: bottom row flipped (tab down);
// SFP: opposite — top row flipped. LED triangles: up-tri = own state (always
// rendered); inverted tri = paired bottom port (top row of 2-row layout only).
// Triangles are speed-tinted (100M amber / 10G blue) and blink on up + error.
$make_shape_pw = static function(int $pos, ?array $port, string $alias, bool $is_sfp, int $row, bool $tris, ?array $pair_port, string $hid) use ($sfx, $make_tip, $spd_cls_of, $show_port_nums, $show_port_lbls, $port_shape): CDiv {
    $state = $port !== null ? ($port['state'] ?? 'gray') : 'gray';
    $s     = $sfx($state);
    $down  = $is_sfp ? ($row === 0) : ($row === 1);

    $poe_on = ($port !== null && ($port['poe_delivering'] ?? false));

    $shape = (new CDiv())->addClass('sw-shape ' . ($is_sfp ? 'sw-shape-sfp' : 'sw-shape-rj') . ($down ? ' sw-down' : ''));
    if ($port_shape === 2) {
        $shape->addClass('sw-s2');
    }
    if ($poe_on) {
        $shape->addClass('sw-poe');
    }

    // Clipped body — complete before addItem (inside-out rule)
    $body = (new CDiv())->addClass('sw-body ' . ($is_sfp ? 'sw-body-sfp' : 'sw-body-rj') . ($down ? ' sw-down' : ''));
    if ($port_shape === 1) {
        $body->addClass('sw-c-' . $s);
        $spd_val  = (int) ($port['speed_negotiated'] ?? 0);
        $spd_mbps = $spd_val > 1000000 ? (int) round($spd_val / 1e6) : $spd_val;
        if ($spd_mbps >= 10000)                    $body->addClass('sw-spd-10g');
        elseif ($spd_mbps > 0 && $spd_mbps <= 100) $body->addClass('sw-spd-100m');
    } else {
        // Style 2: metal inner — highlight, state-colored socket, 8 gold pins (RJ45 only).
        // The socket is filled with the state color like style 1 bodies;
        // offline (gray) keeps the dark socket look.
        $in = (new CDiv())->addClass('sw-s2-in');
        $in->addItem((new CDiv())->addClass('sw-s2-hl'));
        $in->addItem((new CDiv())->addClass('sw-s2-socket sw-c-' . $s . $spd_cls_of($port)));
        if (!$is_sfp) {
            for ($pin = 0; $pin < 8; $pin++) {
                $p = (new CDiv())->addClass('sw-s2-pin');
                $p->setAttribute('style', 'left:' . (21 + $pin * 8.25) . '%;');
                $in->addItem($p);
            }
        }
        $body->addItem($in);
    }
    $shape->addItem($body);

    // LED triangles — up-tri = own status (1-row: every port; 2-row: top row
    // only), inverted down-tri = paired bottom port; speed-tinted, blink on up/error
    if ($tris) {
        $own = (new CDiv())->addClass('sw-tri sw-tri-up' . ($is_sfp ? ' sw-tri-sfp' : '') . ' sw-c-' . $s . $spd_cls_of($port) . ($state !== 'gray' ? ' sw-tri-lit' : ''));
        $shape->addItem($own);
        if ($pair_port !== null) {
            $p_state = (string) ($pair_port['state'] ?? 'gray');
            $pair = (new CDiv())->addClass('sw-tri sw-tri-down' . ($is_sfp ? ' sw-tri-sfp' : '') . ' sw-c-' . $sfx($p_state) . $spd_cls_of($pair_port) . ($p_state !== 'gray' ? ' sw-tri-lit' : ''));
            $shape->addItem($pair);
        }
    }

    // Utilization bar (RX/TX segments) — width stretched to 32px via scoped CSS
    $util_rx_w = ($state === 'gray') ? 0 : min(10, (int) round((float) ($port['util_pct']     ?? 0.0) / 10));
    $util_tx_w = ($state === 'gray') ? 0 : min(10, (int) round((float) ($port['util_pct_out'] ?? 0.0) / 10));
    $util_bar = (new CDiv())->addClass('sw-ub');
    $util_bar->addItem((new CDiv())->addClass('sw-ubr sw-ubw' . $util_rx_w));
    $util_bar->addItem((new CDiv())->addClass('sw-ubt sw-ubw' . $util_tx_w));

    $pw   = (new CDiv())->addClass('sw-pw');
    $slot = (new CDiv())->addClass('sw-slot');
    $slot->addItem($shape);
    $pw->addItem($slot);
    $pw->addItem($util_bar);
    if ($show_port_nums) {
        $num_text = $poe_on ? ('⚡' . $pos) : (string) $pos;
        $pw->addItem((new CDiv($num_text))->addClass('sw-num sw-num-' . $s));
    }
    if ($show_port_lbls) {
        $a = (new CDiv($alias))->addClass('sw-alias');
        if ($alias !== '') $a->setAttribute('title', $alias);
        $pw->addItem($a);
    }
    if ($port !== null && method_exists($pw, 'setHint')) {
        $pw->setHint($make_tip($pos, $port, $alias), '', false);
    }
    if ($port !== null && $hid !== '') {
        $itemid = (string) ($port['in_itemid'] ?? '');
        if ($itemid !== '') {
            $pw->setAttribute('data-href', 'history.php?action=showgraph&itemids[]=' . $itemid);
        }
    }
    return $pw;
};

// ── Build chassis content inside-out ──────────────────────────────────────────
// Rule: every CDiv must be fully populated BEFORE being passed to addItem().

$outer = (new CDiv())->addClass('sw-outer')->addClass($widget_uid);

// Manual aliases override SNMP aliases — format: "1=Uplink, 3=ESX01, 4-6=VLAN1, 5=Core"
$apply_manual_aliases = static function(array $port_aliases) use ($fields): array {
    $manual_raw = trim((string) ($fields['port_aliases_manual'] ?? ''));
    if ($manual_raw === '') return $port_aliases;
    foreach (explode(',', $manual_raw) as $pair) {
        $parts = explode('=', $pair, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $lbl = trim($parts[1]);
            if ($lbl === '') continue;
            if (strpos($key, '-') !== false) {
                // Range syntax: "4-6=Label" expands to ports 4, 5, 6
                [$from, $to] = explode('-', $key, 2);
                $from = (int) $from;
                $to   = (int) $to;
                if ($from > 0 && $to >= $from) {
                    for ($p = $from; $p <= $to; $p++) {
                        $port_aliases[$p] = $lbl;
                    }
                }
            } else {
                $pnum = (int) $key;
                if ($pnum > 0) {
                    $port_aliases[$pnum] = $lbl;
                }
            }
        }
    }
    return $port_aliases;
};

// One chassis per host: an explicit host selection renders a single chassis;
// host group selection renders every member host of the group.
if ($error !== null) {

    $chassis = (new CDiv())->addClass('sw-chassis');
    $chassis->addItem((new CDiv('Error: ' . htmlspecialchars($error)))->addClass('sw-empty sw-err'));
    $outer->addItem($chassis);

} elseif ($no_host) {

    $chassis = (new CDiv())->addClass('sw-chassis');
    $chassis->addItem((new CDiv($t('Select a host or host group in widget settings.')))->addClass('sw-empty'));
    $outer->addItem($chassis);

} else {
  foreach ($hosts as $h) {
    $hostid           = (string) ($h['hostid'] ?? '');
    $ports            = $h['ports']            ?? [];
    $summary          = $h['summary']          ?? [];
    $port_aliases     = $apply_manual_aliases($h['port_aliases'] ?? []);
    $global_sparkline = (string) ($h['global_sparkline'] ?? '');
    $global_peak_rx   = (string) ($h['global_peak_rx']   ?? '');
    $global_peak_tx   = (string) ($h['global_peak_tx']   ?? '');
    // Multiple hosts: label each chassis with its host name; single host keeps the widget name
    $hdr_name = trim((string) ($summary['hostname'] ?? ''));
    if ($hdr_name === '') $hdr_name = trim((string) ($h['name'] ?? ''));
    if ($hdr_name === '') $hdr_name = $widget_name;

    $chassis = (new CDiv())->addClass('sw-chassis');

    if (empty($ports)) {

        $chassis->addItem((new CDiv(
            'No ports found — verify item key patterns match your Zabbix items.'
        ))->addClass('sw-empty sw-warn'));
        $outer->addItem($chassis);
        continue;
    }

    // ── Collect active-trigger ports for chassis warning indicator ────────────
    $warn_list = [];
    foreach ($ports as $wpos => $wport) {
        if (!empty($wport['has_active_trigger'])) {
            $walias = $port_aliases[$wpos] ?? '';
            $wiface = (string) ($wport['iface_name'] ?? $wpos);
            $wname  = $walias !== '' ? $walias . ' (' . $wiface . ')' : $wiface;
            $wdesc  = trim((string) ($wport['trigger_desc'] ?? ''));
            $warn_list[] = $wname . ($wdesc !== '' ? ': ' . $wdesc : '');
        }
    }

    // ── Header ────────────────────────────────────────────────────────────────
    $hdr = (new CDiv())->addClass('sw-hdr');
    $hdr_left = (new CDiv())->addClass('sw-hdr-left');
    $hdr_left->addItem((new CDiv($hdr_name))->addClass('sw-hdr-name'));
    // Model — shown to the right of the hostname when available
    $model = trim((string) ($summary['model'] ?? ''));
    if ($model !== '') {
        $hdr_left->addItem((new CDiv($model))->addClass('sw-hdr-model'));
    }
    $hdr->addItem($hdr_left);

    if ($warn_list !== []) {
        $wtip = (new CDiv())->addClass('swt-wrap');
        $wtip->addItem(new CTag('style', true, $tip_css));
        $wtip->addItem((new CDiv($t('Active Problems') . ' (' . count($warn_list) . ')'))->addClass('swt-name'));
        foreach ($warn_list as $wline) {
            $wtip->addItem((new CDiv('⚠ ' . $wline))->addClass('swt-w'));
        }
        $wicon = (new CDiv('⚠ ' . count($warn_list)))->addClass('sw-warn-icon');
        if (method_exists($wicon, 'setHint')) {
            $wicon->setHint($wtip, '', false);
        }
        $hdr->addItem($wicon);
    }

    if (!empty($summary['ip'])) {
        $hdr->addItem((new CDiv('IP: ' . $summary['ip']))->addClass('sw-hdr-ip'));
    }

    // Row assignment: returns 0 (top) or 1 (bottom) for port index $idx out of $total.
    // Sequential: first half → top, second half → bottom.
    // Staggered:  odd → top, even → bottom (default Cisco style).
    // port_inverted flips whichever rule is active.
    $row_of = static function(int $idx, int $total) use ($port_rows, $port_inverted, $port_sequential): int {
        if ($port_rows <= 1) return 0;
        $in_bottom = $port_sequential ? ($idx > (int) ceil($total / 2)) : ($idx % 2 === 0);
        return (int) ($port_inverted ? !$in_bottom : $in_bottom);
    };

    // Unified classic (style 3) port-cell builder — RJ45 and SFP variants
    $make_classic_pw = static function(array $port, int $disp_num, bool $is_sfp, string $alias) use ($sfx, $make_tip, $show_port_nums, $show_port_lbls, $hostid): CDiv {
        $state = (string) ($port['state'] ?? 'gray');
        $s     = $sfx($state);

        $speed_val  = (int) ($port['speed_negotiated'] ?? 0);
        $speed_mbps = $speed_val > 1000000 ? (int) round($speed_val / 1e6) : $speed_val;
        $spd_cls    = '';
        if ($speed_mbps >= 10000)       $spd_cls = ' sw-spd-10g';
        elseif ($speed_mbps <= 100 && $speed_mbps > 0) $spd_cls = ' sw-spd-100m';

        $poe_on  = !empty($port['poe_delivering']);
        $poe_cls = $poe_on ? ' sw-poe' : '';
        $hdx_cls = !empty($port['half_duplex']) ? ' sw-hdx' : '';

        if ($is_sfp) {
            $card = (new CDiv())->addClass('sw-sfp sw-sfp-' . $s . $spd_cls . $poe_cls . $hdx_cls);
            $card->addItem((new CDiv())->addClass('sw-sfp-dot sw-sfp-dot-' . $s));
        } else {
            $card = (new CDiv())->addClass('sw-p sw-p-' . $s . $spd_cls . $poe_cls . $hdx_cls);
            $card->addItem((new CDiv())->addClass('sw-led sw-led-' . $s));
        }

        $util_rx_w = ($state === 'gray') ? 0 : min(10, (int) round((float) ($port['util_pct']     ?? 0.0) / 10));
        $util_tx_w = ($state === 'gray') ? 0 : min(10, (int) round((float) ($port['util_pct_out'] ?? 0.0) / 10));
        $util_bar = (new CDiv())->addClass('sw-ub');
        $util_bar->addItem((new CDiv())->addClass('sw-ubr sw-ubw' . $util_rx_w));
        $util_bar->addItem((new CDiv())->addClass('sw-ubt sw-ubw' . $util_tx_w));

        $pw = (new CDiv())->addClass('sw-pw');
        $pw->addItem($card);
        $pw->addItem($util_bar);
        if ($show_port_nums) {
            $num_text = $poe_on ? ('⚡' . $disp_num) : (string) $disp_num;
            $pw->addItem((new CDiv($num_text))->addClass('sw-num sw-num-' . $s));
        }
        if ($show_port_lbls) {
            $a = (new CDiv($alias))->addClass('sw-alias');
            if ($alias !== '') $a->setAttribute('title', $alias);
            $pw->addItem($a);
        }
        if (method_exists($pw, 'setHint')) {
            $pw->setHint($make_tip($disp_num, $port, $alias), '', false);
        }
        $itemid = (string) ($port['in_itemid'] ?? '');
        if ($itemid !== '' && $hostid !== '') {
            $pw->setAttribute('data-href', 'history.php?action=showgraph&itemids[]=' . $itemid);
        }
        return $pw;
    };

    // Build one RJ45/SFP block (rows of port cells) from a map of
    // global pos => display number, in display order. Column pairing drives
    // the LED triangles on shape styles 1/2.
    $build_block = static function(array $dnums, bool $is_sfp) use ($ports, $port_aliases, $row_of, $port_rows, $port_shape, $hostid, $make_shape_pw, $make_classic_pw): CDiv {
        $rows = [];
        for ($r = 0; $r < $port_rows; $r++) {
            $rows[$r] = (new CDiv())->addClass('sw-row');
        }

        $n    = count($dnums);
        $rc   = [];
        $next = [0, 0];
        $i    = 0;
        foreach ($dnums as $gpos => $dnum) {
            $i++;
            $r = $row_of($i, $n);
            $rc[$i] = [$r, $next[$r]++, $gpos];
        }
        $bypc = [];
        foreach ($rc as $bi => $rcv) {
            $bypc[$rcv[0]][$rcv[1]] = $bi;
        }

        foreach ($rc as $bi => [$r, $c, $gpos]) {
            $port  = $ports[$gpos] ?? [];
            $alias = $port_aliases[$gpos] ?? '';
            $dnum  = (int) $dnums[$gpos];

            // Styles 1 & 2 — stepped / metal shapes with column-paired LED triangles
            // (2-row: top row only; 1-row: own up-tri on every port)
            if ($port_shape !== 3) {
                $show_tris = ($port_rows === 2) ? ($r === 0) : true;
                $pair_port = null;
                if ($port_rows === 2 && $r === 0 && isset($bypc[1][$c])) {
                    $pair_port = $ports[$rc[$bypc[1][$c]][2]] ?? [];
                }
                $rows[$r]->addItem($make_shape_pw($dnum, $port, $alias, $is_sfp, $r, $show_tris, $pair_port, $hostid));
                continue;
            }

            $rows[$r]->addItem($make_classic_pw($port, $dnum, $is_sfp, $alias));
        }

        $block = (new CDiv())->addClass($is_sfp ? 'sw-sfp-block' : 'sw-rj-block');
        foreach ($rows as $row) {
            $block->addItem($row);
        }
        return $block;
    };

    // ── Port area — grouped by stack member (auto-detect) ─────────────────────
    // "GigabitEthernet2/0/3" → member 2. A single member renders exactly as
    // before (RJ45 block + SFP block); multiple members render one labelled
    // panel per member, each restarting numbering and the RJ45/SFP split.
    $member_groups = [];   // member => [global pos, ...] in display order
    foreach ($ports as $gpos => $p) {
        $member_groups[(int) ($p['member'] ?? 0)][] = (int) $gpos;
    }
    ksort($member_groups);
    // Stacked = two or more real member numbers (>= 1). Ports without member
    // info (member 0 — VLANs, mgmt, 2-level names) never count as a stack.
    $stacked = count(array_filter(array_keys($member_groups), static fn($m): bool => $m > 0)) > 1;

    // Split one member's ports into RJ45 / SFP maps of global pos => display num
    $split_member = static function(array $glist) use ($ports): array {
        $rj = []; $sfp = [];
        foreach ($glist as $gpos) {
            $dnum = (int) ($ports[$gpos]['port_num'] ?? $gpos);
            if (!empty($ports[$gpos]['is_sfp'])) $sfp[$gpos] = $dnum;
            else                                 $rj[$gpos]  = $dnum;
        }
        return [$rj, $sfp];
    };

    // "Back" RJ45 position: render the SFP block first so the panel follows
    // numeric port order (SFP 1..k, then RJ45 k+1..N)
    $rj45_back = ((int) ($fields['rj45_pos'] ?? 0) === 1);
    $block_order = $rj45_back
        ? [[true, 'sfp'], [false, 'rj']]
        : [[false, 'rj'], [true, 'sfp']];

    $port_area = (new CDiv())->addClass('sw-port-area');

    if (!$stacked) {
        // Flat layout — merge every member group back in global port order
        $all = [];
        foreach ($member_groups as $glist) {
            $all = array_merge($all, $glist);
        }
        [$rj_map, $sfp_map] = $split_member($all);
        foreach ($block_order as [$is_sfp, $key]) {
            $map = ($key === 'rj') ? $rj_map : $sfp_map;
            if ($map !== []) $port_area->addItem($build_block($map, $is_sfp));
        }
    } else {
        $port_area->addClass('sw-stacked');
        foreach ($member_groups as $m => $glist) {
            [$rj_map, $sfp_map] = $split_member($glist);
            if ($rj_map === [] && $sfp_map === []) continue;

            // Per-member stats for the label row (aiops style)
            $m_up = 0; $m_dn = 0;
            foreach ($glist as $gpos) {
                (($ports[$gpos]['status'] ?? '') === 'down') ? $m_dn++ : $m_up++;
            }

            $m_hdr = (new CDiv())->addClass('sw-member-hdr');
            $m_hdr->addItem((new CDiv($t('Member') . ' ' . $m))->addClass('sw-member-name'));
            $m_hdr->addItem((new CDiv($t('up') . ' ' . $m_up . ' / ' . $t('dn') . ' ' . $m_dn . ' / ' . $t('total') . ' ' . count($glist)))->addClass('sw-member-stats'));

            $m_area = (new CDiv())->addClass('sw-port-area');
            foreach ($block_order as [$is_sfp, $key]) {
                $map = ($key === 'rj') ? $rj_map : $sfp_map;
                if ($map !== []) $m_area->addItem($build_block($map, $is_sfp));
            }

            $m_sec = (new CDiv())->addClass('sw-member-sec');
            $m_sec->addItem($m_hdr);
            $m_sec->addItem($m_area);
            $port_area->addItem($m_sec);
        }
    }

    // ── Summary bar ───────────────────────────────────────────────────────────
    $up = 0; $dn = 0; $err_ports = 0;
    foreach ($ports as $p) {
        ($p['status'] ?? '') === 'down' ? $dn++ : $up++;
        if (($p['error_rate'] ?? 0.0) > 0.0) $err_ports++;
    }
    $sum_rows = [[$t('Ports'), $up . ' ' . $t('up') . ' / ' . $dn . ' ' . $t('dn')]];
    if ($err_ports > 0) $sum_rows[] = [$t('Err'), $err_ports . ' ' . $t('ports')];
    if (!empty($summary['uptime']))      $sum_rows[] = [$t('Up'),    $summary['uptime']];
    if (!empty($summary['serial']))      $sum_rows[] = [$t('S/N'),   $summary['serial']];
    if (!empty($summary['cpu']))         $sum_rows[] = ['CPU',   $summary['cpu'] . '%'];
    if (!empty($summary['memory']))      $sum_rows[] = [$t('Memory'), $summary['memory'] . '%'];
    // Temperature: panel shows the hottest sensor; hover lists every sensor;
    // alarm styling (⚠ + blink) when the configured threshold is reached
    if (!empty($summary['temperature'])) {
        $alarm   = !empty($summary['temperature_alarm']);
        $tmp_val = rtrim(rtrim(number_format((float) $summary['temperature'], 1), '0'), '.') . '°C';
        $tooltip = null;
        $temps   = (array) ($summary['temperatures'] ?? []);
        if (count($temps) > 1) {
            $tooltip = (new CDiv())->addClass('swt-wrap');
            $tooltip->addItem(new CTag('style', true, $tip_css));
            $tooltip->addItem((new CDiv($t('Temperature sensors')))->addClass('swt-name'));
            $tl = (new CDiv())->addClass('swt-g');
            foreach ($temps as $i => $tv) {
                $tl->addItem((new CSpan($t('Sensor') . ' ' . ($i + 1) . ':'))->addClass('swt-k'));
                $tl->addItem((new CSpan(rtrim(rtrim(number_format((float) $tv, 1), '0'), '.') . '°C'))->addClass('swt-v'));
            }
            $tooltip->addItem($tl);
        }
        $sum_rows[] = [$t('Temp'), ($alarm ? '⚠ ' : '') . $tmp_val, $alarm ? 'sw-sum-v sw-sum-alert' : 'sw-sum-v', $tooltip];
    }
    if (!empty($summary['poe_total']) || !empty($summary['poe_max'])) {
        $fmt_w = static fn(string $v): string =>
            rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
        $poe_val = $fmt_w(trim($summary['poe_total'] ?? ''));
        $poe_max = $fmt_w(trim($summary['poe_max']   ?? ''));
        if ($poe_val !== '' && $poe_max !== '') {
            $sum_rows[] = [$t('PoE'), $poe_val . ' / ' . $poe_max . ' W'];
        } elseif ($poe_val !== '') {
            $sum_rows[] = [$t('PoE'), $poe_val . ' W'];
        } elseif ($poe_max !== '') {
            $sum_rows[] = [$t('PoE cap'), $poe_max . ' W'];
        }
    }
    if (!empty($summary['fan_status'])) {
        $fan_str = $summary['fan_status'] . (($summary['fan_failed'] ?? 0) > 0 ? ' ' . $t('FAIL') : '');
        $sum_rows[] = [$t('Fans'), $fan_str];
    }
    $sum_rows[] = [$t('At'), date('H:i:s')];

    $sum = (new CDiv())->addClass('sw-sum');
    foreach ($sum_rows as $row) {
        [$k, $v] = $row;
        $vc   = (string) ($row[2] ?? 'sw-sum-v');
        $span = (new CSpan($v))->addClass($vc);
        if (($row[3] ?? null) !== null && method_exists($span, 'setHint')) {
            $span->setHint($row[3], '', false);
        }
        $sum->addItem((new CSpan($k . ': '))->addClass('sw-sum-k'));
        $sum->addItem($span);
    }

    // ── Global aggregate sparkline ────────────────────────────────────────────
    $show_sparkline = (bool) (int) ($fields['show_sparkline'] ?? 1);
    $gspk_section = null;
    if ($show_sparkline && $global_sparkline !== '') {
        $spk_bar = (new CDiv())->addClass('sw-gspk');
        $spk_bar->setAttribute('style', 'background-image:url("' . $global_sparkline . '")');
        $legend = (new CDiv())->addClass('sw-gspk-legend');
        $rx_lbl = 'RX' . ($global_peak_rx !== '' ? ' ' . $global_peak_rx : '');
        $tx_lbl = 'TX' . ($global_peak_tx !== '' ? ' ' . $global_peak_tx : '');
        $legend->addItem((new CTag('span', true))->setAttribute('style', 'color:#27c060')->addItem($rx_lbl));
        $legend->addItem((new CTag('span', true))->setAttribute('style', 'color:#4499ff')->addItem($tx_lbl));
        $gspk_section = (new CDiv())->addClass('sw-gspk-wrap');
        $gspk_section->addItem($spk_bar);
        $gspk_section->addItem($legend);
    }

    $chassis->addItem($hdr);
    $chassis->addItem($port_area);
    if ($gspk_section !== null) {
        $chassis->addItem($gspk_section);
    }
    if ($show_summary) {
        $chassis->addItem($sum);
    }

    $outer->addItem($chassis);
  } // foreach hosts
}

(new CWidgetView($data))->addItem(new CTag('style', true, $css))->addItem($outer)->show();
