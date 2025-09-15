<?php
// 简化的测试页面，用于验证CMDB模块加载
echo "<h1>CMDB模块测试</h1>";

// 检查基本的PHP功能
echo "<h2>1. 基本环境检查</h2>";
echo "<p>PHP版本: " . phpversion() . "</p>";
echo "<p>当前时间: " . date('Y-m-d H:i:s') . "</p>";

// 检查类加载
echo "<h2>2. 类加载检查</h2>";
if (class_exists('CController')) {
    echo "<p style='color: green;'>✓ CController类可用</p>";
} else {
    echo "<p style='color: red;'>✗ CController类不可用</p>";
}

// 检查API类
if (class_exists('API')) {
    echo "<p style='color: green;'>✓ API类可用</p>";
} else {
    echo "<p style='color: red;'>✗ API类不可用</p>";
}

// 检查模块类
echo "<h2>3. CMDB模块类检查</h2>";
try {
    if (class_exists('Modules\\ZabbixCmdb\\Actions\\Cmdb')) {
        echo "<p style='color: green;'>✓ CMDB控制器类可用</p>";
    } else {
        echo "<p style='color: orange;'>⚠ CMDB控制器类未加载</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ 加载CMDB控制器失败: " . $e->getMessage() . "</p>";
}

// 检查语言管理器
try {
    if (class_exists('Modules\\ZabbixCmdb\\Lib\\LanguageManager')) {
        echo "<p style='color: green;'>✓ LanguageManager类可用</p>";
        
        // 测试语言功能
        require_once __DIR__ . '/zabbix_cmdb/lib/LanguageManager.php';
        $testText = Modules\ZabbixCmdb\Lib\LanguageManager::t('CMDB');
        echo "<p>测试翻译: '$testText'</p>";
    } else {
        echo "<p style='color: orange;'>⚠ LanguageManager类未加载</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ 加载LanguageManager失败: " . $e->getMessage() . "</p>";
}

// 显示manifest.json内容
echo "<h2>4. 模块清单检查</h2>";
$manifestPath = __DIR__ . '/zabbix_cmdb/manifest.json';
if (file_exists($manifestPath)) {
    $manifest = json_decode(file_get_contents($manifestPath), true);
    if ($manifest) {
        echo "<p style='color: green;'>✓ manifest.json加载成功</p>";
        echo "<pre>" . json_encode($manifest, JSON_PRETTY_PRINT) . "</pre>";
    } else {
        echo "<p style='color: red;'>✗ manifest.json格式错误</p>";
    }
} else {
    echo "<p style='color: red;'>✗ manifest.json文件不存在</p>";
}

// 检查文件结构
echo "<h2>5. 文件结构检查</h2>";
$files = [
    'zabbix_cmdb/actions/Cmdb.php',
    'zabbix_cmdb/actions/CmdbDebug.php',
    'zabbix_cmdb/views/cmdb.php',
    'zabbix_cmdb/lib/LanguageManager.php',
    'zabbix_cmdb/lib/ItemFinder.php'
];

foreach ($files as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "<p style='color: green;'>✓ $file 存在</p>";
    } else {
        echo "<p style='color: red;'>✗ $file 不存在</p>";
    }
}

echo "<h2>测试完成</h2>";
echo "<p><a href='zabbix.php?action=cmdb.view'>尝试访问CMDB页面</a></p>";
echo "<p><a href='zabbix.php?action=cmdb.debug'>尝试访问调试页面</a></p>";
?>
