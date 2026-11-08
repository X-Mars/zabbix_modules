<?php
namespace Modules\ZabbixRebrandLogo\Lib;
class LanguageManager {
    public static function t(string $text): string {
        $lang = null;
        try { if (class_exists('CWebUser')) { $lang = method_exists('CWebUser', 'get') ? \CWebUser::get('lang') : (\CWebUser::$data['lang'] ?? null); } } catch (\Throwable $e) {}
        if (!$lang || $lang === 'default') {
            try { $lang = class_exists('CSettingsHelper') ? \CSettingsHelper::get('default_lang') : 'en_US'; } catch (\Throwable $e) { $lang = 'en_US'; }
        }
        if (stripos((string) $lang, 'zh') !== 0) { return $text; }
        $translations = [
            'Default is the Zabbix server name, which is not set (ZBX_SERVER_NAME in Docker, $ZBX_SERVER_NAME in zabbix.conf.php).' => '默认使用 Zabbix 服务器名称，目前未设置（Docker 中为 ZBX_SERVER_NAME，zabbix.conf.php 中为 $ZBX_SERVER_NAME）。',
            "Zabbix Rebrand Logo" => "品牌 logo 设置",
            "Login page logo" => "登录页标志",
            "Sidebar logo" => "侧边栏标志",
            "Compact sidebar icon" => "折叠侧边栏图标",
            "Browser favicon" => "浏览器图标",
            "Remove current logo" => "移除当前标志",
            "Remove current favicon" => "移除当前浏览器图标",
            "Recommended: 120 x 30 pixels. Formats: PNG, JPG, GIF." => "建议尺寸：120 × 30 像素。格式：PNG、JPG、GIF。",
            "Recommended: 91 x 24 pixels. Formats: PNG, JPG, GIF." => "建议尺寸：91 × 24 像素。格式：PNG、JPG、GIF。",
            "Recommended: 24 x 24 pixels. Formats: PNG, JPG, GIF, ICO." => "建议尺寸：24 × 24 像素。格式：PNG、JPG、GIF、ICO。",
            "Saved to assets/logos/favicon.ico. Requires a one-time symlink from /usr/share/zabbix/favicon.ico to that file — see the module README. Recommended: 32 x 32 pixels. Formats: ICO, PNG, GIF, JPG." => "保存至 assets/logos/favicon.ico。需要创建一次符号链接，详见 README。建议尺寸：32 × 32 像素。格式：ICO、PNG、GIF、JPG。",
            "Default" => "默认",
            "Custom" => "自定义",
            "Hidden" => "隐藏",
            "e.g. Production monitoring" => "例如：生产环境监控",
            "Shown under the sidebar logo and before the page title in the browser tab." => "显示在侧边栏标志下方，以及浏览器标签页标题之前。",
            "The sign-in page always shows the default, because Zabbix loads modules only after sign-in." => "登录页始终显示默认名称，因为 Zabbix 登录后才加载模块。",
            "Server name" => "服务器名称",
            "Footer text" => "页脚文本",
            "e.g. My Company" => "例如：火星小刘的公司",
            "Help URL" => "帮助链接",
            "e.g. https://github.com/X-Mars/zabbix_modules" => "例如：https://github.com/X-Mars/zabbix_modules",
            "Logo settings" => "标志设置",
            "Update" => "更新",
            "Invalid input." => "输入无效。",
            "Cannot update branding." => "无法更新品牌设置。",
            "Branding updated successfully. Clear your browser cache to see the changes." => "品牌设置已更新。请清除浏览器缓存以查看更改。",
            "Branding update failed." => "品牌设置更新失败。",
            "Check write access for local/conf and review the web server error log." => "请检查 local/conf 的写入权限以及 Web 服务器错误日志。",
            "Preview" => "预览",
            "Crop image" => "裁剪图片",
            "Apply crop" => "应用裁剪",
            "Zoom" => "缩放",
            "Horizontal position" => "水平位置",
            "Vertical position" => "垂直位置",
            "Unable to load image." => "无法加载图片。",
            "Crop applied." => "裁剪已应用。",
            "Logo size (4:1, %s)" => "标志尺寸（4:1，%s）",
            "Install the local login view described in the module README to enable custom dimensions." => "安装模块 README 中说明的本地登录视图，即可自定义尺寸。",
            "The cropped image exceeds the upload limit." => "裁剪后的图片超出上传限制。",
            "Preparing image for upload…" => "正在处理上传图片…",
            "Check the login page logo and try again." => "请检查登录页标志后重试。",
        ];
        return $translations[$text] ?? $text;
    }
}
