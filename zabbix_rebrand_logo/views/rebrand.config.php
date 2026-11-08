<?php

/**
 * Rebrand module - Branding configuration view.
 *
 * @var CView $this
 * @var array $data
 */

use Modules\ZabbixRebrandLogo\Lib\ServerNameOverride;
use Modules\ZabbixRebrandLogo\Lib\LanguageManager;

$user_theme = getUserTheme(CWebUser::$data);
$is_dark_theme = in_array($user_theme, ['dark-theme', 'hc-dark'], true);

$html_page = (new CHtmlPage())->setTitle(LanguageManager::t('Zabbix Rebrand Logo'));

$form = (new CForm('post'))
	->setName('rebrand-form')
	->setAttribute('action', 'zabbix.php')
	->setAttribute('enctype', 'multipart/form-data')
	->addVar('action', 'rebrand.config.update')
	->addItem((new CVar(CSRF_TOKEN_NAME, CCsrfTokenHelper::get('rebrand.config.update')))->removeId());

$logos_url = $data['storage_url'];
$storage_dir = $data['storage_dir'];

/**
 * Build a cache-busting query param from the stored file's mtime so the preview
 * URL only changes when the file actually changes (instead of on every render).
 */
$cache_bust = static function (?string $filename) use ($storage_dir): string {
	if ($filename === null || $filename === '' || $storage_dir === '') {
		return '1';
	}

	$mtime = @filemtime($storage_dir.'/'.$filename);

	return ($mtime !== false) ? (string) $mtime : '1';
};

/**
 * Render an upload row: optional themed preview + remove checkbox, plus the file
 * input and a help note.
 */
$build_logo_fields = static function (
		?string $current_file, string $logo_key, string $remove_name, string $remove_label,
		string $accept, string $help_text
	) use ($logos_url, $cache_bust): array {
	$fields = [];

	if ($current_file) {
		$img = (new CTag('img', true))
			->setAttribute('src', $logos_url.'/'.$current_file.'?'.$cache_bust($current_file))
			->addClass('rebrand-logo-preview')
			->setAttribute('data-rebrand-preview', $logo_key);

		$fields[] = (new CDiv($img));
		$fields[] = (new CDiv([
			(new CCheckBox($remove_name, '1')),
			' ',
			$remove_label
		]))->addClass('rebrand-remove-label');
	}

	$fields[] = (new CTag('input', false))
		->setAttribute('type', 'file')
		->setAttribute('name', $logo_key)
		->setAttribute('accept', $accept)
		->setAttribute('data-rebrand-upload', $logo_key);
	$fields[] = (new CTag('div', true, $help_text))
		->addClass(ZBX_STYLE_GREY)
		->setAttribute('style', 'margin-top: 4px;');

	return $fields;
};

$logo_main_fields = $build_logo_fields(
	$data['logo_main'], 'logo_main', 'remove_logo_main', LanguageManager::t('Remove current logo'),
	'.png,.jpg,.jpeg,.gif',
	LanguageManager::t('Recommended: 120 x 30 pixels. Formats: PNG, JPG, GIF.')
);

if ($data['login_logo_sizing']) {
	$ratio = $data['login_logo_ratio'];
	$width = $data['logo_main_width'];
	$size = $width.' × '.intdiv($width, $ratio).' px';
	$logo_main_fields[] = (new CTag('label', true, [
		new CSpan(sprintf(LanguageManager::t('Logo size (4:1, %s)'), $size)),
		(new CTag('input', false))
			->setAttribute('type', 'range')
			->setAttribute('name', 'logo_main_width')
			->setAttribute('min', (string) $ratio)
			->setAttribute('max', (string) $data['login_logo_max_width'])
			->setAttribute('step', (string) $ratio)
			->setAttribute('value', (string) $width)
			->setAttribute('aria-valuetext', $size)
			->setAttribute('data-aspect-ratio', (string) $ratio)
	]))->addClass('rebrand-logo-size');
}
else {
	$logo_main_fields[] = (new CDiv(LanguageManager::t(
		'Install the local login view described in the module README to enable custom dimensions.'
	)))->addClass(ZBX_STYLE_GREY);
}

$logo_sidebar_fields = $build_logo_fields(
	$data['logo_sidebar'], 'logo_sidebar', 'remove_logo_sidebar', LanguageManager::t('Remove current logo'),
	'.png,.jpg,.jpeg,.gif',
	LanguageManager::t('Recommended: 91 x 24 pixels. Formats: PNG, JPG, GIF.')
);

$logo_compact_fields = $build_logo_fields(
	$data['logo_compact'], 'logo_compact', 'remove_logo_compact', LanguageManager::t('Remove current logo'),
	'.png,.jpg,.jpeg,.gif,.ico',
	LanguageManager::t('Recommended: 24 x 24 pixels. Formats: PNG, JPG, GIF, ICO.')
);

$favicon_fields = $build_logo_fields(
	$data['favicon'], 'favicon', 'remove_favicon', LanguageManager::t('Remove current favicon'),
	'.ico,.png,.gif,.jpg,.jpeg',
	LanguageManager::t('Saved to assets/logos/favicon.ico. Requires a one-time symlink from /usr/share/zabbix/favicon.ico to that file — see the module README. Recommended: 32 x 32 pixels. Formats: ICO, PNG, GIF, JPG.')
);

// --- Server name (the text under the sidebar logo) ---

$zabbix_server_name = $data['zabbix_server_name'];

$server_name_fields = [
	(new CRadioButtonList('server_name_mode', $data['server_name_mode']))
		->addValue(LanguageManager::t('Default'), ServerNameOverride::MODE_DEFAULT)
		->addValue(LanguageManager::t('Custom'), ServerNameOverride::MODE_CUSTOM)
		->addValue(LanguageManager::t('Hidden'), ServerNameOverride::MODE_HIDDEN)
		->setModern(),
	(new CDiv(
		(new CTextBox('server_name', $data['server_name'], false, ServerNameOverride::MAX_LENGTH))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', LanguageManager::t('e.g. Production monitoring'))
			->setEnabled($data['server_name_mode'] === ServerNameOverride::MODE_CUSTOM)
	))->setAttribute('style', 'margin-top: 6px;'),
	(new CTag('div', true, [
		LanguageManager::t('Shown under the sidebar logo and before the page title in the browser tab.'),
		BR(),
		($zabbix_server_name !== '')
			? _s('Default is the Zabbix server name, currently "%1$s" (ZBX_SERVER_NAME in Docker, $ZBX_SERVER_NAME in zabbix.conf.php).',
				$zabbix_server_name
			)
			: LanguageManager::t('Default is the Zabbix server name, which is not set (ZBX_SERVER_NAME in Docker, $ZBX_SERVER_NAME in zabbix.conf.php).'),
		BR(),
		LanguageManager::t('The sign-in page always shows the default, because Zabbix loads modules only after sign-in.')
	]))
		->addClass(ZBX_STYLE_GREY)
		->setAttribute('style', 'margin-top: 4px;')
];

// --- Build form list ---

$logo_column = (new CFormList())
	->addRow(LanguageManager::t('Compact sidebar icon'), $logo_compact_fields)
	->addRow(LanguageManager::t('Browser favicon'), $favicon_fields)
	->addRow(LanguageManager::t('Sidebar logo'), $logo_sidebar_fields)
	->addRow(LanguageManager::t('Login page logo'), $logo_main_fields);

$settings_column = (new CFormList())
	->addRow(LanguageManager::t('Server name'), $server_name_fields)
	->addRow(LanguageManager::t('Footer text'),
		(new CTextBox('brand_footer', $data['brand_footer'], false, 128))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', LanguageManager::t('e.g. My Company'))
	)
	->addRow(LanguageManager::t('Help URL'),
		(new CTextBox('brand_help_url', $data['brand_help_url'], false, 255))
			->setWidth(ZBX_TEXTAREA_STANDARD_WIDTH)
			->setAttribute('placeholder', LanguageManager::t('e.g. https://example.com/help'))
	);

$tab_content = new CDiv();

if ($data['runtime_error']) {
	$tab_content->addItem(
		(new CDiv($data['runtime_error']))->addClass('rebrand-banner')->addClass('rebrand-banner-error')
	);
}

if (!$data['storage_writable'] || !$data['conf_writable']) {
	$tab_content->addItem(
		(new CDiv(
			'Warning: Logo files are stored in '.$data['storage_dir'].' and branding config in '.$data['brand_conf_file'].'. ' .
			'The PHP process user "'.$data['runtime_user'].'" needs write access to these paths. ' .
			'If SELinux is enforcing, label both paths with httpd_sys_rw_content_t.'
		))->addClass('rebrand-banner')->addClass('rebrand-banner-warning')
	);
}

$tab_content->addItem((new CDiv([$logo_column, $settings_column]))->addClass('rebrand-columns'));

$tab_view = (new CTabView())->addTab('rebrand_tab', LanguageManager::t('Logo settings'), $tab_content);

$form->addItem([
	$tab_view,
	(new CDiv([
		new CSubmit('update', LanguageManager::t('Update')),
		(new CSpan())->setId('rebrand-update-status')->setAttribute('role', 'status')
	]))->addClass('rebrand-update')
]);

$page_wrapper = (new CDiv($form))
	->addClass('rebrand-page')
	->setAttribute('data-rebrand-theme', $is_dark_theme ? 'dark' : 'light');

$html_page->addItem($page_wrapper);
$html_page->show();

$this->includeJsFile('rebrand.config.js.php');
