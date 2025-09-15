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

// 模拟数据
$data = [
    'host_groups' => [
        ['groupid' => 2, 'name' => 'Linux servers'],
        ['groupid' => 4, 'name' => 'Network'],
        ['groupid' => 5, 'name' => 'Zabbix servers']
    ],
    'selected_groupid' => 0
];

// 构建下拉框选项 - 使用与实际代码相同的方式
$groupOptions = '<option value="0">All Groups</option>';

foreach ($data['host_groups'] as $group) {
    $selected = ($group['groupid'] == $data['selected_groupid']) ? ' selected' : '';
    $groupOptions .= '<option value="' . htmlspecialchars($group['groupid']) . '"' . $selected . '>' . htmlspecialchars($group['name']) . '</option>';
}

// 创建表单 - 使用与实际代码相同的方式
$form = new CDiv();
$form->addClass('search-form');
$form->addItem(
    (new CDiv())
        ->addClass('form-field')
        ->addItem('<label>Select host group</label>')
        ->addItem(new CTag('div', true,
            '<select name="groupid" id="groupid-select">' . $groupOptions . '</select>'
        ))
);

echo "<!DOCTYPE html><html><head><title>Zabbix CMDB Select Test</title></head><body>";
echo "<h1>Zabbix CMDB Select Element Test</h1>";
echo "<h2>Raw HTML Output:</h2>";
echo "<pre>" . htmlspecialchars($form->toString()) . "</pre>";
echo "<h2>Rendered Form:</h2>";
echo $form->toString();
echo "<h2>JavaScript Test:</h2>";
echo "<button onclick='testSelect()'>Test Select</button>";
echo "<script>
function testSelect() {
    var select = document.getElementById('groupid-select');
    console.log('Select element:', select);
    console.log('Options count:', select ? select.options.length : 'Not found');
    if (select) {
        for (var i = 0; i < select.options.length; i++) {
            console.log('Option ' + i + ':', select.options[i].value, select.options[i].text);
        }
    }
}
</script>";
echo "</body></html>";

?>
