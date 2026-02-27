<?php

namespace Modules\ZabbixReports\Lib;

class PdfGenerator {
    
    private $data;
    private $title;
    
    public function __construct($title = 'Zabbix Report') {
        $this->title = $title;
    }
    
    public function setData($data) {
        $this->data = $data;
    }
    
    public function generate() {
        // 检查是否有可用的PDF库
        if (class_exists('TCPDF')) {
            return $this->generateTCPDF();
        } elseif (class_exists('FPDF')) {
            return $this->generateFPDF();
        } else {
            // 如果没有PDF库，使用简单的HTML转PDF方案
            return $this->generateHTML();
        }
    }
    
    private function generateTCPDF() {
        $pdf = new \TCPDF();
        $pdf->SetCreator('Zabbix Reports');
        $pdf->SetAuthor('Zabbix');
        $pdf->SetTitle($this->title);
        $pdf->SetSubject('Zabbix Report');
        
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->SetMargins(15, 27, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(TRUE, 25);
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        
        $pdf->AddPage();
        
        $html = $this->generateHTML();
        $pdf->writeHTML($html, true, false, true, false, '');
        
        return $pdf->Output('', 'S');
    }
    
    private function generateFPDF() {
        $pdf = new \FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, $this->title, 0, 1, 'C');
        $pdf->Ln(10);
        
        $pdf->SetFont('Arial', '', 12);
        
        // 添加基本内容
        if (isset($this->data['problemCount'])) {
            $pdf->Cell(0, 8, LanguageManager::t('Problem Count') . ': ' . $this->data['problemCount'], 0, 1);
        }
        if (isset($this->data['resolvedCount'])) {
            $pdf->Cell(0, 8, LanguageManager::t('Resolved Count') . ': ' . $this->data['resolvedCount'], 0, 1);
        }
        
        $pdf->Ln(5);
        
        if (!empty($this->data['topHosts'])) {
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 8, LanguageManager::t('Top Problem Hosts') . ':', 0, 1);
            $pdf->SetFont('Arial', '', 10);
            
            foreach ($this->data['topHosts'] as $host => $count) {
                $pdf->Cell(0, 6, "  $host: $count " . LanguageManager::t('problems'), 0, 1);
            }
        }
        
        return $pdf->Output('', 'S');
    }
    
    private function generateHTML() {
        // 现代仪表盘风格 HTML（与 ReportViewHelper 保持一致）
        $html = '<!DOCTYPE html><html><head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>' . htmlspecialchars($this->title) . '</title>';
        $html .= '<style>';
        $html .= '
:root {
    --rpt-primary: #4361ee; --rpt-primary-light: #eef1ff;
    --rpt-success: #2ec4b6; --rpt-success-light: #e8f8f5;
    --rpt-warning: #ff9f1c; --rpt-warning-light: #fff8ee;
    --rpt-danger: #e63946;  --rpt-danger-light: #fdebed;
    --rpt-info: #457b9d;    --rpt-info-light: #eaf2f7;
    --rpt-text: #2b2d42;    --rpt-text-secondary: #6c757d;
    --rpt-bg: #f8f9fc;      --rpt-card-bg: #ffffff;
    --rpt-border: #e9ecef;  --rpt-radius: 10px;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans SC", sans-serif; background: var(--rpt-bg); color: var(--rpt-text); padding: 30px; }
.rpt-container { max-width: 1100px; margin: 0 auto; }
h1.rpt-title { font-size: 22px; font-weight: 700; color: var(--rpt-text); margin-bottom: 24px; padding-bottom: 12px; border-bottom: 2px solid var(--rpt-primary); }
.rpt-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
.rpt-stat-card { background: var(--rpt-card-bg); border-radius: var(--rpt-radius); padding: 20px; display: flex; align-items: center; gap: 16px; border: 1px solid var(--rpt-border); transition: all 0.25s ease; }
.rpt-stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
.rpt-stat-icon.danger { background: var(--rpt-danger-light); color: var(--rpt-danger); }
.rpt-stat-icon.success { background: var(--rpt-success-light); color: var(--rpt-success); }
.rpt-stat-icon.warning { background: var(--rpt-warning-light); color: var(--rpt-warning); }
.rpt-stat-icon.info { background: var(--rpt-info-light); color: var(--rpt-info); }
.rpt-stat-icon.primary { background: var(--rpt-primary-light); color: var(--rpt-primary); }
.rpt-stat-body { flex: 1; min-width: 0; }
.rpt-stat-value { font-size: 24px; font-weight: 700; color: var(--rpt-text); line-height: 1.2; }
.rpt-stat-label { font-size: 12px; color: var(--rpt-text-secondary); margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px; }
.rpt-section { background: var(--rpt-card-bg); border-radius: var(--rpt-radius); margin-bottom: 20px; border: 1px solid var(--rpt-border); overflow: hidden; }
.rpt-section-header { padding: 14px 20px; border-bottom: 1px solid var(--rpt-border); background: linear-gradient(135deg, var(--rpt-card-bg), var(--rpt-bg)); }
.rpt-section-title { font-size: 14px; font-weight: 700; color: var(--rpt-text); }
.rpt-section-badge { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 20px; background: var(--rpt-primary-light); color: var(--rpt-primary); margin-left: 8px; }
table { width: 100%; border-collapse: collapse; }
th { background: var(--rpt-bg); color: var(--rpt-text-secondary); font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 14px; text-align: left; border-bottom: 2px solid var(--rpt-border); }
td { padding: 9px 14px; border-bottom: 1px solid var(--rpt-border); font-size: 12px; }
tr:hover { background-color: var(--rpt-primary-light); }
.rpt-sev-not-classified { background: #97AAB3; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600; }
.rpt-sev-information { background: #7499FF; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600; }
.rpt-sev-warning { background: #FFC859; color: #333; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600; }
.rpt-sev-average { background: #FFA059; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600; }
.rpt-sev-high { background: #E97659; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600; }
.rpt-sev-disaster { background: #E45959; color: #fff; padding: 2px 6px; border-radius: 3px; font-size: 10px; font-weight: 600; }
.rpt-host-name { font-weight: 600; }
.rpt-time { font-family: "SF Mono", "Menlo", monospace; font-size: 11px; color: var(--rpt-text-secondary); }
.rpt-time-recovered { color: var(--rpt-success); }
.rpt-rank { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; font-size: 10px; font-weight: 700; }
.rpt-rank-1 { background: linear-gradient(135deg, #FFD700, #FFA000); color: #fff; }
.rpt-rank-2 { background: linear-gradient(135deg, #C0C0C0, #9E9E9E); color: #fff; }
.rpt-rank-3 { background: linear-gradient(135deg, #CD7F32, #A0522D); color: #fff; }
.rpt-rank-n { background: var(--rpt-bg); color: var(--rpt-text-secondary); }
.rpt-progress-bar { display: inline-block; width: 100px; height: 7px; background: var(--rpt-border); border-radius: 4px; overflow: hidden; vertical-align: middle; margin-right: 6px; }
.rpt-progress-fill { display: block; height: 100%; border-radius: 4px; }
.level-low { background: var(--rpt-success); } .level-mid { background: var(--rpt-warning); } .level-high { background: var(--rpt-danger); }
.rpt-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.rpt-problem-tags { padding: 14px 20px; }
.rpt-problem-tag { display: inline-block; background: var(--rpt-warning-light); color: #e67700; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; margin: 2px 4px 2px 0; }
.rpt-footer { text-align: center; padding: 16px; font-size: 11px; color: var(--rpt-text-secondary); border-top: 1px solid var(--rpt-border); margin-top: 24px; }
@media print { body { padding: 10px; background: #fff; } .rpt-stat-card, .rpt-section { box-shadow: none; } }
@media (max-width: 768px) { .rpt-stats-grid { grid-template-columns: repeat(2, 1fr); } .rpt-grid-2 { grid-template-columns: 1fr; } }
';
        $html .= '</style></head><body>';
        $html .= '<div class="rpt-container">';

        // 标题
        $html .= '<h1 class="rpt-title">' . htmlspecialchars($this->title) . '</h1>';

        // 统计摘要卡片（与 ReportViewHelper::buildStatCard 结构完全一致）
        $html .= '<div class="rpt-stats-grid">';
        $html .= '<div class="rpt-stat-card rpt-animate rpt-animate-delay-1"><div class="rpt-stat-icon danger">' . "\u{26A0}" . '</div><div class="rpt-stat-body"><div class="rpt-stat-value">' . ($this->data['problemCount'] ?? 0) . '</div><div class="rpt-stat-label">' . LanguageManager::t('Problem Count') . '</div></div></div>';
        $html .= '<div class="rpt-stat-card rpt-animate rpt-animate-delay-2"><div class="rpt-stat-icon success">' . "\u{2705}" . '</div><div class="rpt-stat-body"><div class="rpt-stat-value">' . ($this->data['resolvedCount'] ?? 0) . '</div><div class="rpt-stat-label">' . LanguageManager::t('Resolved Count') . '</div></div></div>';
        $groupCount = !empty($this->data['hostsByGroup']) ? count($this->data['hostsByGroup']) : 0;
        $html .= '<div class="rpt-stat-card rpt-animate rpt-animate-delay-3"><div class="rpt-stat-icon info">' . "\u{1F5C2}" . '</div><div class="rpt-stat-body"><div class="rpt-stat-value">' . $groupCount . '</div><div class="rpt-stat-label">' . LanguageManager::t('Host Groups') . '</div></div></div>';
        $hostCount = 0;
        if (!empty($this->data['hostsByGroup'])) { foreach ($this->data['hostsByGroup'] as $hosts) { $hostCount += count($hosts); } }
        $html .= '<div class="rpt-stat-card rpt-animate rpt-animate-delay-4"><div class="rpt-stat-icon primary">' . "\u{1F5A5}" . '</div><div class="rpt-stat-body"><div class="rpt-stat-value">' . $hostCount . '</div><div class="rpt-stat-label">' . LanguageManager::t('Total Hosts') . '</div></div></div>';
        $html .= '</div>';

        // 严重等级映射
        $severityMap = [
            0 => ['label' => LanguageManager::t('Not classified'), 'class' => 'rpt-sev-not-classified'],
            1 => ['label' => LanguageManager::t('Information'),    'class' => 'rpt-sev-information'],
            2 => ['label' => LanguageManager::t('Warning'),        'class' => 'rpt-sev-warning'],
            3 => ['label' => LanguageManager::t('Average'),        'class' => 'rpt-sev-average'],
            4 => ['label' => LanguageManager::t('High'),           'class' => 'rpt-sev-high'],
            5 => ['label' => LanguageManager::t('Disaster'),       'class' => 'rpt-sev-disaster'],
        ];

        // CPU / Memory TOP 5 双列
        $html .= '<div class="rpt-grid-2">';

        // CPU TOP
        $html .= '<div class="rpt-section"><div class="rpt-section-header"><span class="rpt-section-title">' . "\u{1F4BB} " . LanguageManager::t('CPU Information (TOP 5)') . '</span></div>';
        $html .= '<table><tr><th>#</th><th>' . LanguageManager::t('Host Name') . '</th><th>' . LanguageManager::t('CPU Usage') . '</th><th>' . LanguageManager::t('CPU Total') . '</th></tr>';
        if (!empty($this->data['topCpuHosts'])) {
            $rank = 0;
            foreach ($this->data['topCpuHosts'] as $host => $usage) {
                if ($rank >= 5) break;
                $rank++;
                $rankClass = $rank <= 3 ? 'rpt-rank rpt-rank-' . $rank : 'rpt-rank rpt-rank-n';
                $level = $usage < 60 ? 'low' : ($usage < 85 ? 'mid' : 'high');
                $cpuTotal = isset($this->data['cpuTotal'][$host]) ? $this->data['cpuTotal'][$host] : 'N/A';
                $html .= '<tr>';
                $html .= '<td><span class="' . $rankClass . '">' . $rank . '</span></td>';
                $html .= '<td><span class="rpt-host-name">' . htmlspecialchars($host) . '</span></td>';
                $html .= '<td><span class="rpt-progress-bar"><span class="rpt-progress-fill level-' . $level . '" style="width:' . min($usage, 100) . '%"></span></span> <strong style="color:var(--rpt-' . ($level === 'low' ? 'success' : ($level === 'mid' ? 'warning' : 'danger')) . ')">' . number_format($usage, 2) . '%</strong></td>';
                $html .= '<td>' . htmlspecialchars((string)$cpuTotal) . '</td>';
                $html .= '</tr>';
            }
        } else {
            $html .= '<tr><td colspan="4" style="text-align:center;padding:20px;color:var(--rpt-text-secondary);">' . LanguageManager::t('No data available') . '</td></tr>';
        }
        $html .= '</table></div>';

        // Memory TOP
        $html .= '<div class="rpt-section"><div class="rpt-section-header"><span class="rpt-section-title">' . "\u{1F4BE} " . LanguageManager::t('Memory Information (TOP 5)') . '</span></div>';
        $html .= '<table><tr><th>#</th><th>' . LanguageManager::t('Host Name') . '</th><th>' . LanguageManager::t('Memory Usage') . '</th><th>' . LanguageManager::t('Memory Total (GB)') . '</th></tr>';
        if (!empty($this->data['topMemHosts'])) {
            $rank = 0;
            foreach ($this->data['topMemHosts'] as $host => $usage) {
                if ($rank >= 5) break;
                $rank++;
                $rankClass = $rank <= 3 ? 'rpt-rank rpt-rank-' . $rank : 'rpt-rank rpt-rank-n';
                $level = $usage < 60 ? 'low' : ($usage < 85 ? 'mid' : 'high');
                $memTotal = isset($this->data['memTotal'][$host]) ? number_format($this->data['memTotal'][$host] / (1024*1024*1024), 2) . ' GB' : 'N/A';
                $html .= '<tr>';
                $html .= '<td><span class="' . $rankClass . '">' . $rank . '</span></td>';
                $html .= '<td><span class="rpt-host-name">' . htmlspecialchars($host) . '</span></td>';
                $html .= '<td><span class="rpt-progress-bar"><span class="rpt-progress-fill level-' . $level . '" style="width:' . min($usage, 100) . '%"></span></span> <strong style="color:var(--rpt-' . ($level === 'low' ? 'success' : ($level === 'mid' ? 'warning' : 'danger')) . ')">' . number_format($usage, 2) . '%</strong></td>';
                $html .= '<td>' . htmlspecialchars($memTotal) . '</td>';
                $html .= '</tr>';
            }
        } else {
            $html .= '<tr><td colspan="4" style="text-align:center;padding:20px;color:var(--rpt-text-secondary);">' . LanguageManager::t('No data available') . '</td></tr>';
        }
        $html .= '</table></div>';
        $html .= '</div>'; // rpt-grid-2

        // 问题主机标签
        if (!empty($this->data['topHosts'])) {
            $html .= '<div class="rpt-section"><div class="rpt-section-header"><span class="rpt-section-title">' . "\u{1F525} " . LanguageManager::t('Top Problem Hosts') . '</span></div>';
            $html .= '<div class="rpt-problem-tags">';
            foreach ($this->data['topHosts'] as $host => $count) {
                $html .= '<span class="rpt-problem-tag">' . htmlspecialchars($host) . ' (' . $count . ')</span>';
            }
            $html .= '</div></div>';
        }

        // 告警信息
        $html .= '<div class="rpt-section"><div class="rpt-section-header"><span class="rpt-section-title">' . "\u{1F514} " . LanguageManager::t('Alert Information');
        $alertCount = !empty($this->data['alertInfo']) ? count($this->data['alertInfo']) : 0;
        $html .= '<span class="rpt-section-badge">' . $alertCount . '</span></span></div>';
        $html .= '<table>';
        $html .= '<tr><th>#</th><th>' . LanguageManager::t('Host Name') . '</th><th>' . LanguageManager::t('Severity') . '</th><th>' . LanguageManager::t('Alert Name') . '</th><th>' . LanguageManager::t('Alert Time') . '</th><th>' . LanguageManager::t('Recovery Time') . '</th></tr>';
        if (!empty($this->data['alertInfo'])) {
            $seq = 0;
            foreach ($this->data['alertInfo'] as $alert) {
                $seq++;
                $sev = (int)($alert['severity'] ?? 0);
                $sevInfo = $severityMap[$sev] ?? $severityMap[0];
                $recoveryTime = !empty($alert['recovery_time']) ? $alert['recovery_time'] : '-';
                $recClass = $recoveryTime !== '-' ? 'rpt-time rpt-time-recovered' : 'rpt-time';
                $html .= '<tr>';
                $html .= '<td><span class="rpt-rank rpt-rank-n">' . $seq . '</span></td>';
                $html .= '<td><span class="rpt-host-name">' . htmlspecialchars($alert['host']) . '</span></td>';
                $html .= '<td><span class="' . $sevInfo['class'] . '">' . $sevInfo['label'] . '</span></td>';
                $html .= '<td>' . htmlspecialchars($alert['alert']) . '</td>';
                $html .= '<td><span class="rpt-time">' . htmlspecialchars($alert['time']) . '</span></td>';
                $html .= '<td><span class="' . $recClass . '">' . htmlspecialchars($recoveryTime) . '</span></td>';
                $html .= '</tr>';
            }
        } else {
            $html .= '<tr><td colspan="6" style="text-align:center;padding:30px;color:var(--rpt-text-secondary);">' . LanguageManager::t('No alerts found') . '</td></tr>';
        }
        $html .= '</table></div>';
        
        // 主机群组信息
        $html .= '<div class="rpt-section"><div class="rpt-section-header"><span class="rpt-section-title">' . "\u{1F4CB} " . LanguageManager::t('Host Group Information');
        $html .= '<span class="rpt-section-badge">' . $hostCount . '</span></span></div>';
        $html .= '<table>';
        $html .= '<tr><th>#</th><th>' . LanguageManager::t('Host Group') . '</th><th>' . LanguageManager::t('Host Name') . '</th><th>' . LanguageManager::t('CPU Usage') . '</th><th>' . LanguageManager::t('CPU Total') . '</th><th>' . LanguageManager::t('Memory Usage') . '</th><th>' . LanguageManager::t('Memory Total') . '</th></tr>';
        if (!empty($this->data['hostsByGroup'])) {
            $seq = 0;
            foreach ($this->data['hostsByGroup'] as $groupName => $hosts) {
                foreach ($hosts as $host) {
                    $seq++;
                    $html .= '<tr>';
                    $html .= '<td><span class="rpt-rank rpt-rank-n">' . $seq . '</span></td>';
                    $html .= '<td>' . htmlspecialchars($groupName) . '</td>';
                    $html .= '<td><span class="rpt-host-name">' . htmlspecialchars($host['name']) . '</span></td>';
                    $html .= '<td>' . htmlspecialchars($host['cpu_usage']) . '</td>';
                    $html .= '<td>' . htmlspecialchars((string)$host['cpu_total']) . '</td>';
                    $html .= '<td>' . htmlspecialchars($host['mem_usage']) . '</td>';
                    $html .= '<td>' . htmlspecialchars((string)$host['mem_total']) . '</td>';
                    $html .= '</tr>';
                }
            }
        } else {
            $html .= '<tr><td colspan="7" style="text-align:center;padding:30px;color:var(--rpt-text-secondary);">' . LanguageManager::t('No host data available') . '</td></tr>';
        }
        $html .= '</table></div>';

        // 页脚
        $html .= '<div class="rpt-footer">' . LanguageManager::t('Generated by Zabbix Reports Module') . ' - ' . date('Y-m-d H:i:s') . ' | <a href="https://github.com/X-Mars/zabbix_modules" target="_blank" style="color:var(--rpt-primary);text-decoration:none;">https://github.com/X-Mars/zabbix_modules</a></div>';

        $html .= '</div></body></html>';
        
        return $html;
    }
}
