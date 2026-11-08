<?php

// Install as local/app/views/general.login.php. Keep Zabbix's login view as
// the source of the form so authentication and upstream updates still apply.
if (!isset($this) || !$this instanceof CView) {
	return;
}

$frontend_root = dirname(__DIR__, 3);
$login_view = $frontend_root.'/include/views/general.login.php';
if (is_file($frontend_root.'/app/views/general.login.php')) {
	$login_view = $frontend_root.'/app/views/general.login.php';
}

$config_file = $frontend_root.'/local/conf/brand.conf.php';
$branding = is_readable($config_file) ? (include $config_file) : [];
$width = $branding['BRAND_LOGO_WIDTH'] ?? null;
$height = $branding['BRAND_LOGO_HEIGHT'] ?? null;

if (!is_int($width) || $width < 1 || !is_int($height) || $height < 1) {
	return include $login_view;
}

$logo_style = sprintf(
	'<style>.signin-logo img.zabbix-logo{width:%1$dpx;height:auto;aspect-ratio:%1$d/%2$d;max-width:100%%;object-fit:contain}</style>',
	$width,
	$height
);

ob_start(static function (string $html) use ($logo_style): string {
	return str_replace('</head>', $logo_style.'</head>', $html);
});
try {
	return include $login_view;
}
finally {
	ob_end_flush();
}
