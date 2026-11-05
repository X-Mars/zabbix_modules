<?php
namespace Modules\ZabbixImportExport\Lib;
/** Resolve the same profile/default locale as the frontend, without browser-language overrides. */
final class LanguageManager
{
    private static $catalogs = [];
    public static function locale(): string
    {
        $locale = \CWebUser::$data["lang"] ?? "default";
        if (!$locale || $locale === "default") {
            $locale = \CSettingsHelper::get("default_lang");
        }
        $locale = str_replace("-", "_", (string) $locale);
        return preg_match('/^[a-z]{2,3}_[A-Z]{2}$/D', $locale) ? $locale : "en_US";
    }
    public static function t(string $text): string
    {
        if ($text === "") {
            return "";
        }
        $locale = self::locale();
        if (!isset(self::$catalogs[$locale])) {
            $path = dirname(__DIR__) . "/locales/" . $locale . ".php";
            self::$catalogs[$locale] = is_file($path) ? require $path : [];
        }
        $catalog = self::$catalogs[$locale];
        if (isset($catalog[$text])) {
            return $catalog[$text];
        }
        foreach (
            [
                "Unsupported fields: ",
                "Invalid field type: ",
                "Missing required field: ",
                "Missing host group: ",
                "Missing template: ",
                "Missing template group: ",
                "Source IDs are forbidden: ",
                "Object missing, inaccessible or ambiguous: ",
                "Invalid filter: "
            ]
            as $prefix
        ) {
            if (strpos($text, $prefix) === 0 && isset($catalog[$prefix])) {
                return $catalog[$prefix] . substr($text, strlen($prefix));
            }
        }
        // Existing Zabbix gettext catalogs translate shared labels in other frontend locales.
        return _($text);
    }
}
