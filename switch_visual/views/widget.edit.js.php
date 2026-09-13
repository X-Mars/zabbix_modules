<?php declare(strict_types=1); ?>

'use strict';

window.switch_visual_form = {

	/**
	 * Initialize the color pickers of the widget configuration form.
	 * Zabbix renders only the markup of CWidgetFieldColorView fields —
	 * the picker control itself is initialized here (official module
	 * tutorial pattern for custom widgets).
	 *
	 * The ZBX_STYLE_COLOR_PICKER constant was removed in Zabbix 7.4, where
	 * the frontend initializes the color pickers on its own — so the legacy
	 * init is skipped there (using the constant would be a fatal PHP error
	 * on PHP 8 and crashed the widget edit form with a 500).
	 */
	init: function() {
		const picker_class = <?= json_encode(defined('ZBX_STYLE_COLOR_PICKER') ? ZBX_STYLE_COLOR_PICKER : null) ?>;

		// Zabbix 7.4+: nothing to do — core initializes the pickers itself.
		if (picker_class === null || typeof jQuery.fn.colorpicker !== 'function') {
			return;
		}

		for (const colorpicker of jQuery('.' + picker_class + ' input')) {
			jQuery(colorpicker).colorpicker();
		}

		// Hide any open picker when the properties overlay reloads/closes
		const overlay = overlays_stack.getById('widget_properties');
		if (overlay && overlay.$dialogue && overlay.$dialogue[0]) {
			for (const event of ['overlay.reload', 'overlay.close']) {
				overlay.$dialogue[0].addEventListener(event, () => jQuery.colorpicker('hide'));
			}
		}
	}
};
