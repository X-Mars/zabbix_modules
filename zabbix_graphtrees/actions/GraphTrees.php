<?php

namespace Modules\ZabbixGraphTrees\Actions;

use CController,
    CControllerResponseData,
    API;

require_once dirname(__DIR__) . '/lib/LanguageManager.php';
use Modules\ZabbixGraphTrees\Lib\LanguageManager;

class GraphTrees extends CController {

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
            'hostid' => 'int32',
            'tag' => 'string',
            'tag_value' => 'string',
            'time_from' => 'int32',
            'time_to' => 'int32'
        ];

        $ret = $this->validateInput($fields);

        if (!$ret) {
            $this->setResponse(new CControllerResponseData(['error' => _('Invalid input parameters.')]));
        }

        return $ret;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $hostid = $this->getInput('hostid', 0);
        $tag = $this->getInput('tag', '');
        $tagValue = $this->getInput('tag_value', '');
        $timeFrom = $this->getInput('time_from', time() - 3600); // 默认最近1小时
        $timeTo = $this->getInput('time_to', time());

        // 获取所有主机分组
        $hostGroups = [];
        try {
            $hostGroups = API::HostGroup()->get([
                'output' => ['groupid', 'name'],
                'with_hosts' => true,
                'sortfield' => 'name',
                'sortorder' => 'ASC'
            ]);
        } catch (\Exception $e) {
            error_log("GraphTrees: Failed to get host groups - " . $e->getMessage());
        }

        // 构建树形结构数据
        $treeData = [];
        foreach ($hostGroups as $group) {
            try {
                $hosts = API::Host()->get([
                    'output' => ['hostid', 'host', 'name', 'status'],
                    'groupids' => [$group['groupid']],
                    'filter' => ['status' => HOST_STATUS_MONITORED],
                    'sortfield' => 'name',
                    'sortorder' => 'ASC'
                ]);

                if (!empty($hosts)) {
                    $treeData[] = [
                        'groupid' => $group['groupid'],
                        'groupname' => $group['name'],
                        'hosts' => $hosts
                    ];
                }
            } catch (\Exception $e) {
                error_log("GraphTrees: Failed to get hosts for group {$group['groupid']} - " . $e->getMessage());
            }
        }

        // 获取所有可用的标记（从主机和监控项中获取）
        $availableTags = [];
        try {
            $tagMap = [];
            
            // 从主机获取标记
            $hostsWithTags = API::Host()->get([
                'output' => ['hostid'],
                'selectTags' => ['tag', 'value'],
                'filter' => ['status' => HOST_STATUS_MONITORED]
            ]);

            foreach ($hostsWithTags as $host) {
                if (!empty($host['tags']) && is_array($host['tags'])) {
                    foreach ($host['tags'] as $hostTag) {
                        $tagName = $hostTag['tag'];
                        $tagVal = $hostTag['value'] ?? '';
                        
                        if (!isset($tagMap[$tagName])) {
                            $tagMap[$tagName] = [];
                        }
                        if ($tagVal !== '' && !in_array($tagVal, $tagMap[$tagName])) {
                            $tagMap[$tagName][] = $tagVal;
                        }
                    }
                }
            }
            
            // 从监控项获取标记（不设置limit，获取所有）
            $itemsWithTags = API::Item()->get([
                'output' => ['itemid'],
                'selectTags' => ['tag', 'value'],
                'filter' => ['status' => ITEM_STATUS_ACTIVE],
                'monitored' => true
            ]);
            
            foreach ($itemsWithTags as $item) {
                if (!empty($item['tags']) && is_array($item['tags'])) {
                    foreach ($item['tags'] as $itemTag) {
                        $tagName = $itemTag['tag'];
                        $tagVal = $itemTag['value'] ?? '';
                        
                        if (!isset($tagMap[$tagName])) {
                            $tagMap[$tagName] = [];
                        }
                        if ($tagVal !== '' && !in_array($tagVal, $tagMap[$tagName])) {
                            $tagMap[$tagName][] = $tagVal;
                        }
                    }
                }
            }

            // 按标记名排序
            ksort($tagMap);
            foreach ($tagMap as $tagName => $values) {
                sort($values);
                $availableTags[] = [
                    'tag' => $tagName,
                    'values' => $values
                ];
            }
        } catch (\Exception $e) {
            error_log("GraphTrees: Failed to get tags - " . $e->getMessage());
        }

        // 如果选择了主机，获取其监控项
        $items = [];
        $selectedHost = null;
        if ($hostid > 0) {
            try {
                $hosts = API::Host()->get([
                    'output' => ['hostid', 'host', 'name'],
                    'hostids' => [$hostid],
                    'limit' => 1
                ]);

                if (!empty($hosts)) {
                    $selectedHost = $hosts[0];

                    // 获取所有监控项（带标记信息）
                    $itemParams = [
                        'output' => ['itemid', 'name', 'key_', 'value_type', 'units'],
                        'hostids' => [$hostid],
                        'filter' => ['status' => ITEM_STATUS_ACTIVE],
                        'selectTags' => ['tag', 'value'],
                        'sortfield' => 'name',
                        'sortorder' => 'ASC'
                    ];

                    $allItems = API::Item()->get($itemParams);
                    
                    // 如果指定了标记，在PHP中手动过滤
                    if (!empty($tag)) {
                        $filteredItems = [];
                        foreach ($allItems as $item) {
                            $matched = false;
                            if (!empty($item['tags']) && is_array($item['tags'])) {
                                foreach ($item['tags'] as $itemTag) {
                                    // 标记名匹配
                                    if ($itemTag['tag'] === $tag) {
                                        // 如果没有指定标记值，或者标记值也匹配
                                        if (empty($tagValue) || $itemTag['value'] === $tagValue) {
                                            $matched = true;
                                            break;
                                        }
                                    }
                                }
                            }
                            if ($matched) {
                                $filteredItems[] = $item;
                            }
                        }
                        $allItems = $filteredItems;
                    }
                    
                    // 限制数量
                    $items = array_slice($allItems, 0, 100);
                }
            } catch (\Exception $e) {
                error_log("GraphTrees: Failed to get items for host {$hostid} - " . $e->getMessage());
            }
        }

        $response = new CControllerResponseData([
            'title' => LanguageManager::t('Graph Trees'),
            'tree_data' => $treeData,
            'available_tags' => $availableTags,
            'selected_hostid' => $hostid,
            'selected_host' => $selectedHost,
            'selected_tag' => $tag,
            'selected_tag_value' => $tagValue,
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'items' => $items,
            'language' => LanguageManager::getCurrentLanguage(),
            'is_chinese' => LanguageManager::isChinese()
        ]);
        
        $response->setTitle(LanguageManager::t('Graph Trees'));
        $this->setResponse($response);
    }
}
