<?php

// 模拟Zabbix环境
class CTag {
    private $tag;
    private $isSingle;
    private $content;
    private $attributes;

    public function __construct($tag, $isSingle = false, $content = '', $attributes = []) {
        $this->tag = $tag;
        $this->isSingle = $isSingle;
        $this->content = $content;
        $this->attributes = $attributes;
    }

    public function setAttribute($key, $value) {
        $this->attributes[$key] = $value;
        return $this;
    }

    public function addItem($item) {
        if (is_object($item) && method_exists($item, 'toString')) {
            $this->content .= $item->toString();
        } else {
            $this->content .= $item;
        }
        return $this;
    }

    public function toString() {
        $attrs = '';
        foreach ($this->attributes as $key => $value) {
            $attrs .= ' ' . $key . '="' . htmlspecialchars($value) . '"';
        }

        if ($this->isSingle) {
            return '<' . $this->tag . $attrs . '>' . $this->content . '</' . $this->tag . '>';
        } else {
            return '<' . $this->tag . $attrs . ' />';
        }
    }
}

class CDiv {
    private $content = '';
    private $class = '';

    public function addClass($class) {
        $this->class = $class;
        return $this;
    }

    public function addItem($item) {
        if (is_object($item) && method_exists($item, 'toString')) {
            $this->content .= $item->toString();
        } else {
            $this->content .= $item;
        }
        return $this;
    }

    public function toString() {
        $classAttr = $this->class ? ' class="' . $this->class . '"' : '';
        return '<div' . $classAttr . '>' . $this->content . '</div>';
    }
}

class CLabel {
    private $text;

    public function __construct($text) {
        $this->text = $text;
    }

    public function toString() {
        return '<label>' . htmlspecialchars($this->text) . '</label>';
    }
}

// 模拟数据
$data = [
    'host_groups' => [
        ['groupid' => 2, 'name' => 'Linux servers'],
        ['groupid' => 22, 'name' => 'Nerwork'],
        ['groupid' => 4, 'name' => 'Zabbix servers']
    ],
    'selected_groupid' => 0
];

// 模拟LanguageManager
class LanguageManager {
    public static function t($key) {
        $translations = [
            'Select host group' => '选择主机分组',
            'All Groups' => '所有分组'
        ];
        return isset($translations[$key]) ? $translations[$key] : $key;
    }
}

// 生成select元素（与实际代码相同）
$formField = new CDiv();
$formField->addClass('form-field');
$formField->addItem(new CLabel(LanguageManager::t('Select host group')));
$formField->addItem((function() use ($data) {
    $select = new CTag('select', true);
    $select->setAttribute('name', 'groupid');
    $select->setAttribute('id', 'groupid-select');

    // 添加"所有分组"选项
    $optAll = new CTag('option', true, LanguageManager::t('All Groups'));
    $optAll->setAttribute('value', '0');
    $select->addItem($optAll);

    // 添加实际的主机组
    if (!empty($data['host_groups'])) {
        foreach ($data['host_groups'] as $group) {
            $opt = new CTag('option', true, $group['name']);
            $opt->setAttribute('value', $group['groupid']);
            if (isset($data['selected_groupid']) && $data['selected_groupid'] == $group['groupid']) {
                $opt->setAttribute('selected', 'selected');
            }
            $select->addItem($opt);
        }
    }

    return $select;
})());

echo "<!DOCTYPE html><html><head><title>Select Test</title></head><body>";
echo "<h1>Select Element Test</h1>";
echo "<h2>Generated HTML:</h2>";
echo "<pre>" . htmlspecialchars($formField->toString()) . "</pre>";
echo "<h2>Rendered Form:</h2>";
echo $formField->toString();
echo "</body></html>";

?>
