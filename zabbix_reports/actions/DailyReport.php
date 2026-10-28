<?php

namespace Modules\ZabbixReports\Actions;

use CController,
    CControllerResponseData,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
require_once dirname(__DIR__) . '/lib/ProblemFinder.php';
require_once dirname(__DIR__) . '/lib/HostMetrics.php';
use Modules\ZabbixReports\Lib\LanguageManager;
use Modules\ZabbixReports\Lib\ProblemFinder;
use Modules\ZabbixReports\Lib\HostMetrics;

class DailyReport extends CController {

    public function init(): void {
        // 兼容Zabbix 6和7
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation(); // Zabbix 7
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation(); // Zabbix 6
        }
    }

    protected function checkInput(): bool {
        $fields = [
            'groupid' => 'string',
            'year' => 'string',
            'month' => 'string',
            'day' => 'string',
            'apply' => 'string'
        ];
        $ret = $this->validateInput($fields);
        return $ret;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $groupId = $this->getInput('groupid', '');

        // 获取所有主机组（用于过滤下拉框）
        $allGroups = API::HostGroup()->get([
            'output' => ['groupid', 'name'],
            'sortfield' => 'name',
            'with_monitored_hosts' => true
        ]);

        $currentYear = (int)date('Y');
        $year = (int)$this->getInput('year', $currentYear);
        $month = (int)$this->getInput('month', date('m'));
        $day = (int)$this->getInput('day', date('d') - 1);
        if ($year < $currentYear - 1 || $year > $currentYear) {
            $year = $currentYear;
        }
        if ($month < 1 || $month > 12) {
            $month = (int)date('m');
        }
        $maxDay = (int)date('t', mktime(0, 0, 0, $month, 1, $year));
        if ($day < 1 || $day > $maxDay) {
            $day = min((int)date('d') - 1, $maxDay);
            if ($day < 1) $day = 1;
        }

        $yesterday = mktime(0, 0, 0, $month, $day, $year);
        $today = mktime(0, 0, 0, $month, $day + 1, $year);
        $from = $yesterday;
        $till = $today;

        // 获取主机（支持分组过滤）
        $hostOptions = [
            'output' => ['hostid', 'name'],
            'filter' => ['status' => HOST_STATUS_MONITORED]
        ];
        if ($groupId !== '') {
            $hostOptions['groupids'] = [$groupId];
        }
        $hosts = API::Host()->get($hostOptions);

        // 获取过滤后的主机ID，用于告警过滤
        $filteredHostIds = array_column($hosts, 'hostid');

        // 使用 ProblemFinder 获取与报表周期有交集的所有告警（按主机过滤）
        $problemResult = ProblemFinder::getProblemsInPeriod($from, $till, 500, $filteredHostIds);
        $problemCount = $problemResult['problemCount'];
        $resolvedCount = $problemResult['resolvedCount'];
        $problemEvents = $problemResult['problemEvents'];
        $recoveryMap = $problemResult['recoveryMap'];
        $triggerHostMap = $problemResult['triggerHostMap'];
        $triggerStatusMap = $problemResult['triggerStatusMap'] ?? [];

        // 构建告警信息
        $alertResult = ProblemFinder::buildAlertInfo(
            $problemEvents, $recoveryMap, $triggerHostMap,
            LanguageManager::t('Unknown Host'), $triggerStatusMap
        );
        $alertInfo = $alertResult['alertInfo'];
        $hostCounts = $alertResult['hostCounts'];
        $topHosts = $alertResult['topHosts'];

        $hostMetrics = HostMetrics::build($hosts, $from, $till);

        $response = new CControllerResponseData([
            'title' => LanguageManager::t('Daily Report'),
            'report_date' => LanguageManager::formatPeriod('daily', $yesterday),
            'report_date_raw' => date('Y-m-d', $yesterday),
            'problem_count' => $problemCount,
            'resolved_count' => $resolvedCount,
            'alert_info' => $alertInfo,
            'hosts_by_group' => $hostMetrics['hosts_by_group'],
            'top_problem_hosts' => $topHosts,
            'top_cpu_hosts' => $hostMetrics['top_cpu_hosts'],
            'top_mem_hosts' => $hostMetrics['top_mem_hosts'],
            'cpu_total' => $hostMetrics['cpu_total'],
            'mem_total' => $hostMetrics['mem_total'],
            'language' => LanguageManager::getCurrentLanguage(),
            'is_chinese' => LanguageManager::isChinese(),
            'all_groups' => $allGroups,
            'filter_groupid' => $groupId,
            'filter_group_name' => $this->getGroupName($groupId, $allGroups),
            'filter_year' => $year,
            'filter_month' => $month,
            'filter_day' => $day
        ]);
        
        // 显式设置响应标题（Zabbix 6.0 需要）
        $response->setTitle(LanguageManager::t('Daily Report'));

        $this->setResponse($response);
    }

    private function getGroupName(string $groupId, array $allGroups): string {
        if ($groupId === '') return '';
        foreach ($allGroups as $group) {
            if ((string)$group['groupid'] === (string)$groupId) {
                return $group['name'];
            }
        }
        return '';
    }
}
