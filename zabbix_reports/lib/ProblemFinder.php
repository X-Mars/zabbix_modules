<?php
/**
 * 告警事件查找器
 * 统一处理报表中的告警查询逻辑
 * 
 * 核心改进：查询与报表周期有交集的所有告警事件，而不仅仅是在周期内产生的告警。
 * 
 * 匹配条件（告警时间范围与报表周期有交集）：
 *   clock <= till AND (r_clock >= from OR r_clock = 0/未恢复)
 * 
 * 覆盖场景：
 *   A) 在周期内产生并恢复     → clock ∈ [from, till], r_clock ∈ [from, till]
 *   B) 在周期内产生但未恢复   → clock ∈ [from, till], r_clock = 0
 *   C) 在周期前产生，周期内恢复 → clock < from, r_clock ∈ [from, till]
 *   D) 在周期前产生，至今未恢复 → clock < from, r_clock = 0
 *   E) 在周期前产生，周期后恢复 → clock < from, r_clock > till
 */

namespace Modules\ZabbixReports\Lib;

use API;

class ProblemFinder {

    /**
     * 获取与指定时间周期有交集的所有问题事件（详细版本）
     * 
     * @param int $from 周期开始时间戳
     * @param int $till 周期结束时间戳
     * @param int $limit 最大返回数量
     * @return array 包含 problemEvents, problemCount, resolvedCount 的结果
     */
    public static function getProblemsInPeriod(int $from, int $till, int $limit = 500, array $hostids = []): array {
        // ====================================================
        // 第1步：获取在报表周期内产生的问题事件（场景A和B）
        // ====================================================
        $eventOptions = [
            'output' => ['eventid', 'objectid', 'name', 'clock', 'r_eventid', 'severity'],
            'source' => 0,
            'object' => 0,
            'value' => TRIGGER_VALUE_TRUE,
            'time_from' => $from,
            'time_till' => $till,
            'sortfield' => ['clock'],
            'sortorder' => 'DESC',
            'limit' => $limit,
            'selectHosts' => ['hostid', 'name'],
        ];
        if (!empty($hostids)) {
            $eventOptions['hostids'] = $hostids;
        }
        $eventsInPeriod = API::Event()->get($eventOptions);
        if (!is_array($eventsInPeriod)) {
            $eventsInPeriod = [];
        }

        // ====================================================
        // 第2步：获取在报表周期开始前产生但在周期内仍活跃的问题事件（场景C、D、E）
        // 策略：查询周期开始前产生的事件，然后通过恢复时间过滤
        // ====================================================
        $eventBeforeOptions = [
            'output' => ['eventid', 'objectid', 'name', 'clock', 'r_eventid', 'severity'],
            'source' => 0,
            'object' => 0,
            'value' => TRIGGER_VALUE_TRUE,
            'time_till' => $from - 1,  // 在周期开始前产生
            'sortfield' => ['clock'],
            'sortorder' => 'DESC',
            'limit' => 2000,  // 加大限制以捕获所有跨周期事件
            'selectHosts' => ['hostid', 'name'],
        ];
        if (!empty($hostids)) {
            $eventBeforeOptions['hostids'] = $hostids;
        }
        $eventsBeforePeriod = API::Event()->get($eventBeforeOptions);
        if (!is_array($eventsBeforePeriod)) {
            $eventsBeforePeriod = [];
        }

        // 获取周期前事件的恢复时间
        $earlyRecoveryMap = [];
        $earlyRecoveryIds = [];
        foreach ($eventsBeforePeriod as $event) {
            if (!empty($event['r_eventid']) && $event['r_eventid'] != 0) {
                $earlyRecoveryIds[] = $event['r_eventid'];
            }
        }
        if (!empty($earlyRecoveryIds)) {
            $earlyRecoveryEvents = API::Event()->get([
                'output' => ['eventid', 'clock'],
                'eventids' => array_values(array_unique($earlyRecoveryIds)),
            ]);
            if (is_array($earlyRecoveryEvents)) {
                foreach ($earlyRecoveryEvents as $re) {
                    $earlyRecoveryMap[$re['eventid']] = (int)$re['clock'];
                }
            }
        }

        // 验证：对周期前未恢复的事件（r_eventid=0），通过 Problem API 确认是否真正活跃
        // Event 表中 r_eventid=0 不一定代表仍活跃，可能是手动关闭、触发器删除等情况
        $unresolvedEventIds = [];
        foreach ($eventsBeforePeriod as $event) {
            if (empty($event['r_eventid']) || $event['r_eventid'] == 0) {
                $unresolvedEventIds[] = $event['eventid'];
            }
        }
        $confirmedActiveProblems = [];
        if (!empty($unresolvedEventIds)) {
            $activeProblems = API::Problem()->get([
                'output' => ['eventid'],
                'eventids' => $unresolvedEventIds,
            ]);
            if (is_array($activeProblems)) {
                foreach ($activeProblems as $p) {
                    $confirmedActiveProblems[$p['eventid']] = true;
                }
            }
        }

        // 过滤：只保留在报表周期内仍活跃的事件
        $activeBeforePeriod = [];
        foreach ($eventsBeforePeriod as $event) {
            if (empty($event['r_eventid']) || $event['r_eventid'] == 0) {
                // 未恢复 → 必须通过 Problem API 确认确实仍活跃
                if (isset($confirmedActiveProblems[$event['eventid']])) {
                    $activeBeforePeriod[] = $event;
                }
            } elseif (isset($earlyRecoveryMap[$event['r_eventid']])) {
                $rClock = $earlyRecoveryMap[$event['r_eventid']];
                if ($rClock >= $from) {
                    // 在周期开始后才恢复（恢复时间 >= 周期开始）→ 周期内有交集
                    $activeBeforePeriod[] = $event;
                }
                // 如果 rClock < from → 在周期开始前就恢复了 → 不纳入
            }
            // 如果有 r_eventid 但找不到恢复事件 → 安全起见不纳入
        }

        // ====================================================
        // 第3步：合并去重（按 eventid 去重）
        // ====================================================
        $allEventIds = array_column($eventsInPeriod, 'eventid');
        foreach ($activeBeforePeriod as $event) {
            if (!in_array($event['eventid'], $allEventIds)) {
                $eventsInPeriod[] = $event;
                $allEventIds[] = $event['eventid'];
            }
        }

        // ====================================================
        // 第3.5步：检查事件对应的触发器状态（存在/启用/禁用/已删除）
        // Event 表保留所有历史记录，包括已删除/禁用触发器的事件
        // 通过 Trigger API 查询触发器状态，标记而非过滤
        // ====================================================
        $triggerStatusMap = []; // objectid => 'enabled' | 'disabled' | 'deleted'
        $allObjectIds = array_values(array_unique(array_column($eventsInPeriod, 'objectid')));
        if (!empty($allObjectIds)) {
            $existingTriggers = API::Trigger()->get([
                'output' => ['triggerid', 'status'],
                'triggerids' => $allObjectIds,
                'preservekeys' => true,
            ]);
            foreach ($allObjectIds as $oid) {
                if (!isset($existingTriggers[$oid])) {
                    $triggerStatusMap[$oid] = 'deleted';
                } elseif ((int)$existingTriggers[$oid]['status'] !== TRIGGER_STATUS_ENABLED) {
                    $triggerStatusMap[$oid] = 'disabled';
                } else {
                    $triggerStatusMap[$oid] = 'enabled';
                }
            }
        }

        // 按时间降序排序
        usort($eventsInPeriod, function($a, $b) {
            return (int)$b['clock'] - (int)$a['clock'];
        });

        // 截断到限制数量
        if (count($eventsInPeriod) > $limit) {
            $eventsInPeriod = array_slice($eventsInPeriod, 0, $limit);
        }

        // ====================================================
        // 第4步：计算统计数据
        // ====================================================
        // 问题总数 = 与周期有交集的所有问题事件数量
        $problemCount = count($eventsInPeriod);

        // 恢复事件计数仍按原逻辑：统计周期内产生的恢复事件（value=TRIGGER_VALUE_FALSE）
        // 这代表"在此周期内实际恢复了多少告警"
        $resolvedOptions = [
            'countOutput' => true,
            'filter' => ['value' => TRIGGER_VALUE_FALSE],
            'time_from' => $from,
            'time_till' => $till
        ];
        if (!empty($hostids)) {
            $resolvedOptions['hostids'] = $hostids;
        }
        $resolvedCount = API::Event()->get($resolvedOptions);

        // ====================================================
        // 第5步：批量获取恢复事件时间
        // ====================================================
        $recoveryMap = [];
        $allRecoveryIds = [];
        foreach ($eventsInPeriod as $event) {
            if (!empty($event['r_eventid']) && $event['r_eventid'] != 0) {
                $allRecoveryIds[] = $event['r_eventid'];
            }
        }
        if (!empty($allRecoveryIds)) {
            $recoveryEvents = API::Event()->get([
                'output' => ['eventid', 'clock'],
                'eventids' => array_values(array_unique($allRecoveryIds)),
            ]);
            if (is_array($recoveryEvents)) {
                foreach ($recoveryEvents as $re) {
                    $recoveryMap[$re['eventid']] = $re['clock'];
                }
            }
        }

        // ====================================================
        // 第6步：解析主机名（对 selectHosts 返回空的事件，通过 Trigger API 补充）
        // ====================================================
        $unknownTriggerIds = [];
        foreach ($eventsInPeriod as $event) {
            if (empty($event['hosts'])) {
                $unknownTriggerIds[$event['objectid']] = true;
            }
        }
        $triggerHostMap = [];
        if (!empty($unknownTriggerIds)) {
            $triggers = API::Trigger()->get([
                'output' => ['triggerid'],
                'triggerids' => array_keys($unknownTriggerIds),
                'selectHosts' => ['name'],
            ]);
            if (is_array($triggers)) {
                foreach ($triggers as $trigger) {
                    if (!empty($trigger['hosts'])) {
                        $triggerHostMap[$trigger['triggerid']] = $trigger['hosts'][0]['name'];
                    }
                }
            }
        }

        return [
            'problemEvents' => $eventsInPeriod,
            'problemCount' => $problemCount,
            'resolvedCount' => (int)$resolvedCount,
            'recoveryMap' => $recoveryMap,
            'triggerHostMap' => $triggerHostMap,
            'triggerStatusMap' => $triggerStatusMap,
        ];
    }

    /**
     * 简化版：获取与指定时间周期有交集的问题事件（仅基本字段）
     * 用于 Send 类等只需要 eventid/objectid 的场景
     * 
     * @param int $from 周期开始时间戳
     * @param int $till 周期结束时间戳
     * @return array 包含 events, problemCount, resolvedCount 的结果
     */
    public static function getSimpleProblemsInPeriod(int $from, int $till): array {
        // 第1步：获取周期内产生的事件
        $eventsInPeriod = API::Event()->get([
            'output' => ['eventid', 'objectid', 'clock', 'r_eventid'],
            'source' => 0,
            'object' => 0,
            'value' => TRIGGER_VALUE_TRUE,
            'time_from' => $from,
            'time_till' => $till,
        ]);
        if (!is_array($eventsInPeriod)) {
            $eventsInPeriod = [];
        }

        // 第2步：获取周期前产生但在周期内仍活跃的事件
        $eventsBeforePeriod = API::Event()->get([
            'output' => ['eventid', 'objectid', 'clock', 'r_eventid'],
            'source' => 0,
            'object' => 0,
            'value' => TRIGGER_VALUE_TRUE,
            'time_till' => $from - 1,
            'limit' => 2000,
        ]);
        if (!is_array($eventsBeforePeriod)) {
            $eventsBeforePeriod = [];
        }

        // 获取恢复时间
        $earlyRecoveryMap = [];
        $earlyRecoveryIds = [];
        foreach ($eventsBeforePeriod as $event) {
            if (!empty($event['r_eventid']) && $event['r_eventid'] != 0) {
                $earlyRecoveryIds[] = $event['r_eventid'];
            }
        }
        if (!empty($earlyRecoveryIds)) {
            $earlyRecoveryEvents = API::Event()->get([
                'output' => ['eventid', 'clock'],
                'eventids' => array_values(array_unique($earlyRecoveryIds)),
            ]);
            if (is_array($earlyRecoveryEvents)) {
                foreach ($earlyRecoveryEvents as $re) {
                    $earlyRecoveryMap[$re['eventid']] = (int)$re['clock'];
                }
            }
        }

        // 验证：对周期前未恢复的事件，通过 Problem API 确认是否真正活跃
        $unresolvedEventIds = [];
        foreach ($eventsBeforePeriod as $event) {
            if (empty($event['r_eventid']) || $event['r_eventid'] == 0) {
                $unresolvedEventIds[] = $event['eventid'];
            }
        }
        $confirmedActiveProblems = [];
        if (!empty($unresolvedEventIds)) {
            $activeProblems = API::Problem()->get([
                'output' => ['eventid'],
                'eventids' => $unresolvedEventIds,
            ]);
            if (is_array($activeProblems)) {
                foreach ($activeProblems as $p) {
                    $confirmedActiveProblems[$p['eventid']] = true;
                }
            }
        }

        // 过滤活跃事件
        $allEventIds = array_column($eventsInPeriod, 'eventid');
        foreach ($eventsBeforePeriod as $event) {
            $include = false;
            if (empty($event['r_eventid']) || $event['r_eventid'] == 0) {
                // 必须通过 Problem API 确认确实仍活跃
                if (isset($confirmedActiveProblems[$event['eventid']])) {
                    $include = true;
                }
            } elseif (isset($earlyRecoveryMap[$event['r_eventid']])) {
                if ($earlyRecoveryMap[$event['r_eventid']] >= $from) {
                    $include = true;  // 周期内才恢复
                }
            }
            if ($include && !in_array($event['eventid'], $allEventIds)) {
                $eventsInPeriod[] = $event;
                $allEventIds[] = $event['eventid'];
            }
        }

        // 验证：检查触发器状态（存在/启用/禁用/已删除），标记而非过滤
        $triggerStatusMap = [];
        $allObjectIds = array_values(array_unique(array_column($eventsInPeriod, 'objectid')));
        if (!empty($allObjectIds)) {
            $existingTriggers = API::Trigger()->get([
                'output' => ['triggerid', 'status'],
                'triggerids' => $allObjectIds,
                'preservekeys' => true,
            ]);
            foreach ($allObjectIds as $oid) {
                if (!isset($existingTriggers[$oid])) {
                    $triggerStatusMap[$oid] = 'deleted';
                } elseif ((int)$existingTriggers[$oid]['status'] !== TRIGGER_STATUS_ENABLED) {
                    $triggerStatusMap[$oid] = 'disabled';
                } else {
                    $triggerStatusMap[$oid] = 'enabled';
                }
            }
        }

        // 统计
        $problemCount = count($eventsInPeriod);

        $resolvedCount = API::Event()->get([
            'countOutput' => true,
            'filter' => ['value' => TRIGGER_VALUE_FALSE],
            'time_from' => $from,
            'time_till' => $till
        ]);

        return [
            'events' => $eventsInPeriod,
            'problemCount' => $problemCount,
            'resolvedCount' => (int)$resolvedCount,
            'triggerStatusMap' => $triggerStatusMap,
        ];
    }

    /**
     * 从问题事件列表构建告警信息数组
     * 
     * @param array $problemEvents 问题事件列表
     * @param array $recoveryMap 恢复事件映射 [r_eventid => clock]
     * @param array $triggerHostMap 触发器主机映射 [triggerid => hostname]
     * @param string $unknownHostLabel 未知主机标签文字
     * @param array $triggerStatusMap 触发器状态映射 [triggerid => 'enabled'|'disabled'|'deleted']
     * @return array ['alertInfo' => [...], 'hostCounts' => [...], 'topHosts' => [...]]
     */
    public static function buildAlertInfo(array $problemEvents, array $recoveryMap, array $triggerHostMap, string $unknownHostLabel = 'Unknown Host', array $triggerStatusMap = []): array {
        $alertInfo = [];
        $hostCounts = [];

        foreach ($problemEvents as $event) {
            if (!empty($event['hosts'])) {
                $hostName = $event['hosts'][0]['name'];
            } elseif (isset($triggerHostMap[$event['objectid']])) {
                $hostName = $triggerHostMap[$event['objectid']];
            } else {
                $hostName = $unknownHostLabel;
            }

            $recoveryTime = null;
            if (!empty($event['r_eventid']) && isset($recoveryMap[$event['r_eventid']])) {
                $recoveryTime = date('Y-m-d H:i:s', $recoveryMap[$event['r_eventid']]);
            }

            // 判断触发器是否已禁用或已删除
            $triggerDisabled = false;
            if (!empty($triggerStatusMap)) {
                $status = $triggerStatusMap[$event['objectid']] ?? 'enabled';
                $triggerDisabled = ($status === 'disabled' || $status === 'deleted');
            }

            $alertInfo[] = [
                'host' => $hostName,
                'alert' => $event['name'],
                'severity' => (int)($event['severity'] ?? 0),
                'time' => date('Y-m-d H:i:s', $event['clock']),
                'recovery_time' => $recoveryTime,
                'trigger_disabled' => $triggerDisabled,
            ];
            $hostCounts[$hostName] = ($hostCounts[$hostName] ?? 0) + 1;
        }

        arsort($hostCounts);
        $topHosts = array_slice($hostCounts, 0, 10, true);

        return [
            'alertInfo' => $alertInfo,
            'hostCounts' => $hostCounts,
            'topHosts' => $topHosts,
        ];
    }
}
