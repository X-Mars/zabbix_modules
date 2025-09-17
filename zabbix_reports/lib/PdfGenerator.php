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
        // 简单的HTML格式，可以被浏览器打印为PDF
        $html = '<!DOCTYPE html><html><head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>' . $this->title . '</title>';
        $html .= '<style>';
        $html .= 'body { font-family: Arial, sans-serif; margin: 20px; }';
        $html .= 'h1 { color: #333; text-align: center; }';
        $html .= 'table { width: 100%; border-collapse: collapse; margin: 20px 0; }';
        $html .= 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
        $html .= 'th { background-color: #f2f2f2; }';
        $html .= '.summary { background-color: #f9f9f9; padding: 15px; margin: 10px 0; }';
        $html .= '</style></head><body>';
        
        $html .= '<h1>' . $this->title . '</h1>';
        
        if (isset($this->data['date'])) {
            $html .= '<p><strong>' . LanguageManager::t('Report Date') . ':</strong> ' . $this->data['date'] . '</p>';
        }
        
        $html .= '<div class="summary">';
        $html .= '<table>';
        if (isset($this->data['problemCount'])) {
            $html .= '<tr><td><strong>' . LanguageManager::t('Problem Count') . ':</strong></td><td>' . $this->data['problemCount'] . '</td></tr>';
        }
        if (isset($this->data['resolvedCount'])) {
            $html .= '<tr><td><strong>' . LanguageManager::t('Resolved Count') . ':</strong></td><td>' . $this->data['resolvedCount'] . '</td></tr>';
        }
        if (isset($this->data['topHosts'])) {
            $html .= '<tr><td><strong>' . LanguageManager::t('Top Problem Hosts') . ':</strong></td><td>' . implode(', ', array_keys($this->data['topHosts'])) . '</td></tr>';
        }
        $html .= '</table>';
        $html .= '</div>';
        
        // 第一部分：告警信息
        $html .= '<h2>' . LanguageManager::t('Part 1: Alert Information') . '</h2>';
        $html .= '<table>';
        $html .= '<tr><th>' . LanguageManager::t('Host Name') . '</th><th>' . LanguageManager::t('Alert Name') . '</th><th>' . LanguageManager::t('Alert Time') . '</th><th>' . LanguageManager::t('Recovery Time') . '</th></tr>';
        if (!empty($this->data['alertInfo'])) {
            $count = 0;
            foreach ($this->data['alertInfo'] as $alert) {
                if ($count >= 10) break; // 显示前10条告警
                $recoveryTime = isset($alert['recovery_time']) && $alert['recovery_time'] ? $alert['recovery_time'] : '-';
                $html .= "<tr><td>{$alert['host']}</td><td>{$alert['alert']}</td><td>{$alert['time']}</td><td>{$recoveryTime}</td></tr>";
                $count++;
            }
        } else {
            $html .= '<tr><td>' . LanguageManager::t('No alerts found') . '</td><td></td><td></td><td></td></tr>';
        }
        $html .= '</table>';
        
        // 第二部分：主机群组信息
        $html .= '<h2>' . LanguageManager::t('Part 2: Host Group Information') . '</h2>';
        $html .= '<table>';
        $html .= '<tr><th>' . LanguageManager::t('Host Group') . '</th><th>' . LanguageManager::t('Host Name') . '</th><th>' . LanguageManager::t('CPU Usage') . '</th><th>' . LanguageManager::t('CPU Total') . '</th><th>' . LanguageManager::t('Memory Usage') . '</th><th>' . LanguageManager::t('Memory Total') . '</th></tr>';
        if (!empty($this->data['hostsByGroup'])) {
            $count = 0;
            foreach ($this->data['hostsByGroup'] as $groupName => $hosts) {
                foreach ($hosts as $host) {
                    if ($count >= 20) break; // 显示前20个主机
                    $html .= "<tr><td>$groupName</td><td>{$host['name']}</td><td>{$host['cpu_usage']}</td><td>{$host['cpu_total']}</td><td>{$host['mem_usage']}</td><td>{$host['mem_total']}</td></tr>";
                    $count++;
                }
                if ($count >= 20) break;
            }
        } else {
            $html .= '<tr><td>' . LanguageManager::t('No host data available') . '</td><td></td><td></td><td></td><td></td><td></td></tr>';
        }
        $html .= '</table>';
        
        // CPU Information (TOP 5)
        $html .= '<h3>' . LanguageManager::t('CPU Information (TOP 5)') . '</h3>';
        $html .= '<table>';
        $html .= '<tr><th>' . LanguageManager::t('Host Name') . '</th><th>' . LanguageManager::t('CPU Usage') . ' (%)</th><th>' . LanguageManager::t('CPU Total') . '</th></tr>';
        if (!empty($this->data['topCpuHosts'])) {
            $count = 0;
            foreach ($this->data['topCpuHosts'] as $host => $usage) {
                if ($count >= 5) break;
                $cpuTotal = isset($this->data['cpuTotal'][$host]) ? $this->data['cpuTotal'][$host] : 'N/A';
                $html .= "<tr><td>$host</td><td>" . number_format($usage, 2) . "%</td><td>$cpuTotal</td></tr>";
                $count++;
            }
        } else {
            $html .= '<tr><td>' . LanguageManager::t('No data available') . '</td><td></td><td></td></tr>';
        }
        $html .= '</table>';
        
        // Memory Information (TOP 5)
        $html .= '<h3>' . LanguageManager::t('Memory Information (TOP 5)') . '</h3>';
        $html .= '<table>';
        $html .= '<tr><th>' . LanguageManager::t('Host Name') . '</th><th>' . LanguageManager::t('Memory Usage') . ' (%)</th><th>' . LanguageManager::t('Memory Total (GB)') . '</th></tr>';
        if (!empty($this->data['topMemHosts'])) {
            $count = 0;
            foreach ($this->data['topMemHosts'] as $host => $usage) {
                if ($count >= 5) break;
                $memTotal = isset($this->data['memTotal'][$host]) ? number_format($this->data['memTotal'][$host] / (1024*1024*1024), 2) : 'N/A';
                $html .= "<tr><td>$host</td><td>" . number_format($usage, 2) . "%</td><td>$memTotal " . LanguageManager::t('GB') . "</td></tr>";
                $count++;
            }
        } else {
            $html .= '<tr><td>' . LanguageManager::t('No data available') . '</td><td></td><td></td></tr>';
        }
        $html .= '</table>';
        
        $html .= '</body></html>';
        
        return $html;
    }
}
