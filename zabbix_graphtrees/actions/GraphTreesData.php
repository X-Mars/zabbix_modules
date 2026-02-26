<?php

namespace Modules\ZabbixGraphTrees\Actions;

use CController,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
use Modules\ZabbixGraphTrees\Lib\LanguageManager;

class GraphTreesData extends CController {

    public function init(): void {
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation();
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation();
        }
    }

    protected function checkInput(): bool {
        return $this->validateInput([
            'type' => 'string',
            'hostid' => 'int32',
            'category' => 'string'
        ]);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $type = $this->getInput('type', '');
        $hostid = $this->getInput('hostid', 0);

        $result = ['success' => false, 'error' => 'Invalid request'];

        switch ($type) {
            case 'categories':
                $result = $this->getCategories($hostid);
                break;
            case 'graphs':
                $category = $this->getInput('category', '');
                $result = $this->getGraphs($hostid, $category);
                break;
        }

        header('Content-Type: application/json');
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * 获取主机的分类列表（基于监控项标签）
     * 类似旧版Zabbix的Application概念
     */
    private function getCategories(int $hostid): array {
        if ($hostid <= 0) {
            return ['success' => false, 'error' => 'Invalid hostid'];
        }

        try {
            // 获取主机所有活跃监控项及其标签
            $items = API::Item()->get([
                'output' => ['itemid', 'name', 'value_type', 'units'],
                'hostids' => [$hostid],
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'selectTags' => ['tag', 'value'],
                'sortfield' => 'name'
            ]);

            // 获取主机的预配置图表
            $graphs = API::Graph()->get([
                'output' => ['graphid', 'name'],
                'hostids' => [$hostid],
                'selectItems' => ['itemid'],
                'sortfield' => 'name'
            ]);

            // 构建监控项→标签值映射 & 监控项信息索引
            $itemTagValues = [];
            $itemInfo = [];
            foreach ($items as $item) {
                $itemTagValues[$item['itemid']] = [];
                $itemInfo[$item['itemid']] = $item;
                if (!empty($item['tags'])) {
                    foreach ($item['tags'] as $tag) {
                        if (!empty($tag['value'])) {
                            $itemTagValues[$item['itemid']][] = $tag['value'];
                        }
                    }
                }
            }

            // 构建已被预配置图表覆盖的监控项集合
            $graphItemIds = [];
            foreach ($graphs as $graph) {
                foreach ($graph['items'] as $gItem) {
                    $graphItemIds[$gItem['itemid']] = true;
                }
            }

            // 按标签值分组统计
            $categories = [];
            $uncategorizedCount = 0;
            $uncategorizedNumericCount = 0;
            foreach ($items as $item) {
                $tagValues = $itemTagValues[$item['itemid']];
                $isNumeric = in_array((int)$item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64]);
                if (empty($tagValues)) {
                    $uncategorizedCount++;
                    if ($isNumeric) $uncategorizedNumericCount++;
                } else {
                    foreach ($tagValues as $tagValue) {
                        if (!isset($categories[$tagValue])) {
                            $categories[$tagValue] = [
                                'key' => $tagValue,
                                'name' => $tagValue,
                                'itemCount' => 0,
                                'numericItemCount' => 0,
                                'graphCount' => 0
                            ];
                        }
                        $categories[$tagValue]['itemCount']++;
                        if ($isNumeric) $categories[$tagValue]['numericItemCount']++;
                    }
                }
            }

            // 统计每个分类的预配置图表数量
            $otherGraphCount = 0;
            foreach ($graphs as $graph) {
                $graphCategories = [];
                $hasUncategorizedItem = false;
                foreach ($graph['items'] as $gItem) {
                    if (isset($itemTagValues[$gItem['itemid']])) {
                        if (empty($itemTagValues[$gItem['itemid']])) {
                            $hasUncategorizedItem = true;
                        }
                        foreach ($itemTagValues[$gItem['itemid']] as $tagValue) {
                            $graphCategories[$tagValue] = true;
                        }
                    }
                }
                foreach ($graphCategories as $catName => $_) {
                    if (isset($categories[$catName])) {
                        $categories[$catName]['graphCount']++;
                    }
                }
                if ($hasUncategorizedItem) {
                    $otherGraphCount++;
                }
            }

            // 统计每个分类的 adhoc 图表卡片数（孤立数值型监控项按单位合并，每8个一张）
            foreach ($categories as $catName => &$cat) {
                $orphanUnits = [];
                foreach ($items as $item) {
                    $inCat = in_array($catName, $itemTagValues[$item['itemid']] ?? []);
                    if ($inCat
                        && !isset($graphItemIds[$item['itemid']])
                        && in_array((int)$item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])
                    ) {
                        $u = $item['units'] ?? '';
                        $orphanUnits[$u] = ($orphanUnits[$u] ?? 0) + 1;
                    }
                }
                foreach ($orphanUnits as $cnt) {
                    $cat['graphCount'] += (int)ceil($cnt / 8);
                }
            }
            unset($cat);

            // 统计"其他"分类的 adhoc 图表卡片数
            $otherOrphanUnits = [];
            foreach ($items as $item) {
                if (empty($itemTagValues[$item['itemid']])
                    && !isset($graphItemIds[$item['itemid']])
                    && in_array((int)$item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])
                ) {
                    $u = $item['units'] ?? '';
                    $otherOrphanUnits[$u] = ($otherOrphanUnits[$u] ?? 0) + 1;
                }
            }
            foreach ($otherOrphanUnits as $cnt) {
                $otherGraphCount += (int)ceil($cnt / 8);
            }

            ksort($categories);

            // 未分类的监控项归入"其他"
            if ($uncategorizedCount > 0) {
                $categories['__other__'] = [
                    'key' => '__other__',
                    'name' => LanguageManager::t('Other'),
                    'itemCount' => $uncategorizedCount,
                    'numericItemCount' => $uncategorizedNumericCount,
                    'graphCount' => $otherGraphCount
                ];
            }

            // 计算"所有图表"的总卡片数
            $totalCards = count($graphs);
            $allOrphanUnits = [];
            foreach ($items as $item) {
                if (!isset($graphItemIds[$item['itemid']])
                    && in_array((int)$item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])
                ) {
                    $u = $item['units'] ?? '';
                    $allOrphanUnits[$u] = ($allOrphanUnits[$u] ?? 0) + 1;
                }
            }
            foreach ($allOrphanUnits as $cnt) {
                $totalCards += (int)ceil($cnt / 8);
            }

            return [
                'success' => true,
                'categories' => array_values($categories),
                'totalGraphs' => $totalCards,
                'totalItems' => count($items)
            ];
        } catch (\Exception $e) {
            error_log("GraphTreesData: getCategories failed - " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 获取指定主机+分类的图表列表
     * 返回预配置图表（graphid）和自动聚合的监控项图表（itemids）
     */
    private function getGraphs(int $hostid, string $category): array {
        if ($hostid <= 0) {
            return ['success' => false, 'error' => 'Invalid hostid'];
        }

        try {
            $items = API::Item()->get([
                'output' => ['itemid', 'name', 'key_', 'value_type', 'units'],
                'hostids' => [$hostid],
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'selectTags' => ['tag', 'value'],
                'sortfield' => 'name'
            ]);

            $graphs = API::Graph()->get([
                'output' => ['graphid', 'name', 'width', 'height'],
                'hostids' => [$hostid],
                'selectItems' => ['itemid'],
                'sortfield' => 'name'
            ]);

            // 筛选属于当前分类的监控项
            $categoryItemIds = [];
            if ($category === '__all__') {
                $categoryItemIds = array_column($items, 'itemid');
            } elseif ($category === '__other__') {
                foreach ($items as $item) {
                    $hasTag = false;
                    if (!empty($item['tags'])) {
                        foreach ($item['tags'] as $tag) {
                            if (!empty($tag['value'])) {
                                $hasTag = true;
                                break;
                            }
                        }
                    }
                    if (!$hasTag) {
                        $categoryItemIds[] = $item['itemid'];
                    }
                }
            } else {
                foreach ($items as $item) {
                    if (!empty($item['tags'])) {
                        foreach ($item['tags'] as $tag) {
                            if ($tag['value'] === $category) {
                                $categoryItemIds[] = $item['itemid'];
                                break;
                            }
                        }
                    }
                }
            }
            $categoryItemIdsMap = array_flip($categoryItemIds);

            // 查找属于当前分类的预配置图表
            $categoryGraphs = [];
            $graphItemIds = [];

            if ($category === '__all__') {
                $categoryGraphs = $graphs;
                foreach ($graphs as $graph) {
                    foreach ($graph['items'] as $gItem) {
                        $graphItemIds[$gItem['itemid']] = true;
                    }
                }
            } else {
                foreach ($graphs as $graph) {
                    $hasMatch = false;
                    foreach ($graph['items'] as $gItem) {
                        if (isset($categoryItemIdsMap[$gItem['itemid']])) {
                            $hasMatch = true;
                        }
                    }
                    if ($hasMatch) {
                        $categoryGraphs[] = $graph;
                        foreach ($graph['items'] as $gItem) {
                            $graphItemIds[$gItem['itemid']] = true;
                        }
                    }
                }
            }

            // 查找没有预配置图表的数值型监控项（孤立项）
            $orphanItems = [];
            foreach ($items as $item) {
                if (isset($categoryItemIdsMap[$item['itemid']]) && !isset($graphItemIds[$item['itemid']])) {
                    if (in_array((int)$item['value_type'], [ITEM_VALUE_TYPE_FLOAT, ITEM_VALUE_TYPE_UINT64])) {
                        $orphanItems[] = $item;
                    }
                }
            }

            // 构建结果列表
            $graphList = [];
            foreach ($categoryGraphs as $graph) {
                $graphList[] = [
                    'graphid' => $graph['graphid'],
                    'name' => $graph['name'],
                    'type' => 'graph'
                ];
            }

            // 合并同类孤立监控项到同一张图表
            $mergedOrphans = $this->mergeOrphanItems($orphanItems);
            foreach ($mergedOrphans as $merged) {
                $graphList[] = [
                    'itemids' => $merged['itemids'],
                    'name' => $merged['name'],
                    'type' => 'adhoc'
                ];
            }

            return [
                'success' => true,
                'graphs' => $graphList
            ];
        } catch (\Exception $e) {
            error_log("GraphTreesData: getGraphs failed - " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 将孤立监控项按单位分组合并
     * 相同单位的监控项绘制在同一张图表上（最多8条线）
     */
    private function mergeOrphanItems(array $items): array {
        if (empty($items)) {
            return [];
        }

        // 按单位分组
        $groups = [];
        foreach ($items as $item) {
            $units = $item['units'] ?? '';
            $groups[$units][] = $item;
        }

        $result = [];
        foreach ($groups as $units => $groupItems) {
            if (count($groupItems) <= 8) {
                $result[] = [
                    'itemids' => array_column($groupItems, 'itemid'),
                    'name' => count($groupItems) === 1
                        ? $groupItems[0]['name']
                        : $this->findCommonPrefix($groupItems)
                ];
            } else {
                // 大组拆分为每8个一组
                $chunks = array_chunk($groupItems, 8);
                foreach ($chunks as $i => $chunk) {
                    $name = $this->findCommonPrefix($chunk);
                    if (count($chunks) > 1) {
                        $name .= ' #' . ($i + 1);
                    }
                    $result[] = [
                        'itemids' => array_column($chunk, 'itemid'),
                        'name' => $name
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * 查找一组监控项名称的公共前缀
     */
    private function findCommonPrefix(array $items): string {
        if (count($items) === 1) {
            return $items[0]['name'];
        }

        $names = array_column($items, 'name');
        $prefix = $names[0];

        for ($i = 1; $i < count($names); $i++) {
            while (strpos($names[$i], $prefix) !== 0 && strlen($prefix) > 0) {
                $prefix = substr($prefix, 0, -1);
            }
            if (strlen($prefix) === 0) {
                break;
            }
        }

        $prefix = rtrim($prefix, " :-_/\\");

        if (strlen($prefix) >= 3) {
            return $prefix;
        }

        // 无公共前缀时使用前几个名称
        $display = array_slice($names, 0, 3);
        return implode(', ', $display) . (count($names) > 3 ? '...' : '');
    }
}
