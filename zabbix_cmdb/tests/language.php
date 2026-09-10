<?php
// Standalone language regression tests. Does not modify any Zabbix settings or rules.
require_once dirname(__DIR__).'/lib/LanguageManager.php';
require_once dirname(__DIR__).'/lib/ItemConfig.php';
use Modules\ZabbixCmdb\Lib\LanguageManager as LM;
use Modules\ZabbixCmdb\Lib\ItemConfig;
class CWebUser { public static $data = []; }
class CSettingsHelper {
    public static $language = 'en_US';
    public static function get($key) { return self::$language; }
}
function checkLanguage($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
    echo "PASS: $message\n";
}
foreach ([['zh_CN','en_US','zh_CN'], ['en_US','zh_CN','en_US'], ['default','zh_CN','zh_CN'],
        ['default','en_US','en_US'], ['en-GB','zh_CN','en_US'], ['zh-CN','en_US','zh_CN'],
        ['fr_FR','zh_CN','en_US'], ['', 'zh_CN', 'zh_CN']] as $case) {
    CWebUser::$data['lang'] = $case[0]; CSettingsHelper::$language = $case[1]; LM::resetLanguage();
    checkLanguage(LM::detectLanguage() === $case[2], 'User/system language: '.implode(' / ', $case));
}
$property = new ReflectionProperty(LM::class, 'translations');
if (PHP_VERSION_ID < 80100) { $property->setAccessible(true); }
$translations = $property->getValue();
checkLanguage(array_keys($translations['zh_CN']) === array_keys($translations['en_US']), 'Chinese and English translation keys are aligned');
foreach ($translations['en_US'] as $key => $english) {
    checkLanguage(!preg_match('/\p{Han}/u', $english), 'English text: '.$key);
    preg_match_all('/%\d*\$?[dsf]/', $english, $enPlaceholders);
    preg_match_all('/%\d*\$?[dsf]/', $translations['zh_CN'][$key], $zhPlaceholders);
    if ($enPlaceholders[0] !== $zhPlaceholders[0]) { throw new RuntimeException('Placeholder mismatch: '.$key); }
}
foreach (['zh_CN', 'en_US'] as $language) {
    CWebUser::$data['lang'] = $language; LM::resetLanguage();
    checkLanguage(LM::t('Available') === ($language === 'zh_CN' ? '可用' : 'Available'), 'Availability: '.$language);
    checkLanguage(LM::t('Active Hosts') === ($language === 'zh_CN' ? '活跃主机' : 'Active Hosts'), 'Active host label: '.$language);
    checkLanguage(LM::t('Item Configuration') === ($language === 'zh_CN' ? '监控项配置' : 'Item Configuration'), 'Configuration title: '.$language);
    checkLanguage(strpos(LM::tf('Showing %d to %d of %d hosts', 1, 10, 20), '20') !== false, 'Pagination placeholders: '.$language);
    try { ItemConfig::validate([], ['cpu_count']); throw new LogicException('Invalid configuration accepted'); }
    catch (InvalidArgumentException $e) {
        checkLanguage((bool) preg_match('/\p{Han}/u', $e->getMessage()) === ($language === 'zh_CN'), 'Validation error language: '.$language);
    }
}
echo "All CMDB language tests passed.\n";
