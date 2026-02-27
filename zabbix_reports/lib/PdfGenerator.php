<?php

namespace Modules\ZabbixReports\Lib;

require_once __DIR__ . '/ReportViewHelper.php';

/**
 * PDF / HTML 导出生成器
 * 
 * 复用 ReportViewHelper 的 CTag 组件构建导出内容，
 * 避免重复的 HTML 拼接，与页面视图保持完全一致。
 */
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

    /**
     * 将 Export 数据键名映射为 View 数据键名，以便复用 ReportViewHelper
     */
    private function normalizeData(): array {
        $d = $this->data;
        return [
            'problem_count'      => $d['problemCount']  ?? $d['problem_count']     ?? 0,
            'resolved_count'     => $d['resolvedCount'] ?? $d['resolved_count']    ?? 0,
            'alert_info'         => $d['alertInfo']     ?? $d['alert_info']        ?? [],
            'hosts_by_group'     => $d['hostsByGroup']  ?? $d['hosts_by_group']    ?? [],
            'top_problem_hosts'  => $d['topHosts']      ?? $d['top_problem_hosts'] ?? [],
            'top_cpu_hosts'      => $d['topCpuHosts']   ?? $d['top_cpu_hosts']     ?? [],
            'top_mem_hosts'      => $d['topMemHosts']   ?? $d['top_mem_hosts']     ?? [],
            'cpu_total'          => $d['cpuTotal']      ?? $d['cpu_total']         ?? [],
            'mem_total'          => $d['memTotal']      ?? $d['mem_total']         ?? [],
        ];
    }
    
    /**
     * 生成独立 HTML 文档 —— 复用 ReportViewHelper 的 CTag 组件
     * 与页面视图共享相同的样式和 DOM 结构
     */
    private function generateHTML() {
        $viewData = $this->normalizeData();

        // ===== HTML 文档头部 =====
        $html = '<!DOCTYPE html><html><head>';
        $html .= '<meta charset="UTF-8">';
        $html .= '<title>' . htmlspecialchars($this->title) . '</title>';

        // 复用 ReportViewHelper 统一样式
        $html .= ReportViewHelper::getStyleTag()->toString();

        // 补充独立文档所需的 body 基础样式
        $html .= '<style>'
            . 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans SC",sans-serif;'
            . 'background:var(--rpt-bg);color:var(--rpt-text);padding:30px;margin:0}'
            . '.rpt-container{max-width:1100px}'
            . '</style>';

        $html .= '</head><body>';

        // ===== 构建内容容器（CTag 方式，与视图页面一致）=====
        $container = (new \CDiv())->addClass('rpt-container');

        // 页面标题
        $title = (new \CTag('h1', true))
            ->addClass('rpt-title')
            ->addItem((new \CSpan("\u{1F4CA}"))->addClass('rpt-title-icon'))
            ->addItem($this->title);
        $container->addItem(
            (new \CDiv())->addClass('rpt-header')->addItem($title)
        );

        // 统计摘要卡片
        $container->addItem(ReportViewHelper::buildStatsGrid($viewData));

        // Top CPU / Memory 双列
        $container->addItem(ReportViewHelper::buildTopResourceSection($viewData));

        // 告警级别雷达 + 主机组问题分布双列图表
        $container->addItem(ReportViewHelper::buildChartsRow($viewData['alert_info'], $viewData['hosts_by_group']));

        // 问题主机标签
        $container->addItem(ReportViewHelper::buildTopProblemHosts($viewData['top_problem_hosts']));

        // 告警信息
        $container->addItem(ReportViewHelper::buildAlertSection($viewData['alert_info']));

        // 主机群组信息
        $container->addItem(ReportViewHelper::buildHostGroupSection($viewData['hosts_by_group']));

        // 页脚
        $footer = (new \CDiv())->addClass('rpt-footer');
        $footer->addItem(
            LanguageManager::t('Generated by Zabbix Reports Module') . ' - ' . date('Y-m-d H:i:s') . ' | '
        );
        $footer->addItem(
            (new \CTag('a', true, 'https://github.com/X-Mars/zabbix_modules'))
                ->setAttribute('href', 'https://github.com/X-Mars/zabbix_modules')
                ->setAttribute('target', '_blank')
                ->setAttribute('style', 'color:var(--rpt-primary);text-decoration:none;')
        );
        $container->addItem($footer);

        $html .= $container->toString();

        // JS 交互增强（搜索、排序、数字动画）
        $html .= ReportViewHelper::getScriptTag();

        $html .= '</body></html>';
        
        return $html;
    }
}
