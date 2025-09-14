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
        
        $html = $this->buildHTMLContent();
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
            $pdf->Cell(0, 8, 'Problem Count: ' . $this->data['problemCount'], 0, 1);
        }
        if (isset($this->data['resolvedCount'])) {
            $pdf->Cell(0, 8, 'Resolved Count: ' . $this->data['resolvedCount'], 0, 1);
        }
        
        $pdf->Ln(5);
        
        if (!empty($this->data['topHosts'])) {
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 8, 'Top Problem Hosts:', 0, 1);
            $pdf->SetFont('Arial', '', 10);
            
            foreach ($this->data['topHosts'] as $host => $count) {
                $pdf->Cell(0, 6, "  $host: $count problems", 0, 1);
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
            $html .= '<p><strong>Report Date:</strong> ' . $this->data['date'] . '</p>';
        }
        
        $html .= '<div class="summary">';
        $html .= '<h3>Summary</h3>';
        if (isset($this->data['problemCount'])) {
            $html .= '<p><strong>Problem Count:</strong> ' . $this->data['problemCount'] . '</p>';
        }
        if (isset($this->data['resolvedCount'])) {
            $html .= '<p><strong>Resolved Count:</strong> ' . $this->data['resolvedCount'] . '</p>';
        }
        $html .= '</div>';
        
        // 第一部分：告警信息
        if (!empty($this->data['alertInfo'])) {
            $html .= '<h2>第一部分：告警信息</h2>';
            $html .= '<table>';
            $html .= '<tr><th>主机名</th><th>告警名称</th><th>告警时间</th></tr>';
            foreach ($this->data['alertInfo'] as $alert) {
                $html .= "<tr><td>{$alert['host']}</td><td>{$alert['alert']}</td><td>{$alert['time']}</td></tr>";
            }
            $html .= '</table>';
        }
        
        // 第二部分：按主机群组分组的主机列表
        if (!empty($this->data['hostsByGroup'])) {
            $html .= '<h2>第二部分：主机资源信息（按群组分类）</h2>';
            foreach ($this->data['hostsByGroup'] as $groupName => $hosts) {
                $html .= "<h3>群组：$groupName</h3>";
                $html .= '<table>';
                $html .= '<tr><th>主机名</th><th>CPU使用率</th><th>CPU总数</th><th>内存使用率</th><th>内存总量</th></tr>';
                foreach ($hosts as $host) {
                    $html .= "<tr><td>{$host['name']}</td><td>{$host['cpu_usage']}</td><td>{$host['cpu_total']}</td><td>{$host['mem_usage']}</td><td>{$host['mem_total']}</td></tr>";
                }
                $html .= '</table>';
            }
        }
        
        // 保留兼容性：旧格式的问题主机统计
        if (!empty($this->data['topHosts'])) {
            $html .= '<h3>问题主机统计</h3>';
            $html .= '<table>';
            $html .= '<tr><th>Host Name</th><th>Problem Count</th></tr>';
            foreach ($this->data['topHosts'] as $host => $count) {
                $html .= "<tr><td>$host</td><td>$count</td></tr>";
            }
            $html .= '</table>';
        }
        
        if (!empty($this->data['topCpuHosts'])) {
            $html .= '<h3>Top CPU Usage Hosts</h3>';
            $html .= '<table>';
            $html .= '<tr><th>Host Name</th><th>CPU Usage (%)</th><th>CPU Count</th></tr>';
            foreach ($this->data['topCpuHosts'] as $host => $usage) {
                $cpuCount = isset($this->data['cpuTotal'][$host]) ? $this->data['cpuTotal'][$host] : 'N/A';
                $html .= "<tr><td>$host</td><td>" . number_format($usage, 2) . "%</td><td>$cpuCount</td></tr>";
            }
            $html .= '</table>';
        }
        
        if (!empty($this->data['topMemHosts'])) {
            $html .= '<h3>Top Memory Usage Hosts</h3>';
            $html .= '<table>';
            $html .= '<tr><th>Host Name</th><th>Memory Usage (%)</th><th>Total Memory (MB)</th></tr>';
            foreach ($this->data['topMemHosts'] as $host => $usage) {
                $memTotal = isset($this->data['memTotal'][$host]) ? number_format($this->data['memTotal'][$host] / (1024*1024), 0) : 'N/A';
                $html .= "<tr><td>$host</td><td>" . number_format($usage, 2) . "%</td><td>$memTotal MB</td></tr>";
            }
            $html .= '</table>';
        }
        
        $html .= '</body></html>';
        
        return $html;
    }
    
    private function buildHTMLContent() {
        $html = '<h1>' . $this->title . '</h1>';
        
        if (isset($this->data['date'])) {
            $html .= '<p><strong>Report Date:</strong> ' . $this->data['date'] . '</p>';
        }
        
        $html .= '<table border="1" cellpadding="5">';
        $html .= '<tr><th>Metric</th><th>Value</th></tr>';
        
        if (isset($this->data['problemCount'])) {
            $html .= '<tr><td>Problem Count</td><td>' . $this->data['problemCount'] . '</td></tr>';
        }
        if (isset($this->data['resolvedCount'])) {
            $html .= '<tr><td>Resolved Count</td><td>' . $this->data['resolvedCount'] . '</td></tr>';
        }
        
        $html .= '</table><br>';
        
        // 第一部分：告警信息
        if (!empty($this->data['alertInfo'])) {
            $html .= '<h3>第一部分：告警信息</h3>';
            $html .= '<table border="1" cellpadding="5">';
            $html .= '<tr><th>主机名</th><th>告警名称</th><th>告警时间</th></tr>';
            foreach ($this->data['alertInfo'] as $alert) {
                $html .= "<tr><td>{$alert['host']}</td><td>{$alert['alert']}</td><td>{$alert['time']}</td></tr>";
            }
            $html .= '</table><br>';
        }
        
        // 第二部分：按主机群组分组的主机列表
        if (!empty($this->data['hostsByGroup'])) {
            $html .= '<h3>第二部分：主机资源信息（按群组分类）</h3>';
            foreach ($this->data['hostsByGroup'] as $groupName => $hosts) {
                $html .= "<h4>群组：$groupName</h4>";
                $html .= '<table border="1" cellpadding="5">';
                $html .= '<tr><th>主机名</th><th>CPU使用率</th><th>CPU总数</th><th>内存使用率</th><th>内存总量</th></tr>';
                foreach ($hosts as $host) {
                    $html .= "<tr><td>{$host['name']}</td><td>{$host['cpu_usage']}</td><td>{$host['cpu_total']}</td><td>{$host['mem_usage']}</td><td>{$host['mem_total']}</td></tr>";
                }
                $html .= '</table><br>';
            }
        }
        
        // 保留兼容性的统计信息
        if (!empty($this->data['topHosts'])) {
            $html .= '<h3>问题主机统计</h3>';
            $html .= '<table border="1" cellpadding="5">';
            $html .= '<tr><th>Host Name</th><th>Problem Count</th></tr>';
            foreach ($this->data['topHosts'] as $host => $count) {
                $html .= "<tr><td>$host</td><td>$count</td></tr>";
            }
            $html .= '</table><br>';
        }
        
        return $html;
    }
}
