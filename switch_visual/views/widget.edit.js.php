<?php declare(strict_types=1); ?>

'use strict';

window.switch_visual_form = {

	/**
	 * Initialize the color pickers of the widget configuration form.
	 * Zabbix renders only the markup of CWidgetFieldColorView fields —
	 * the picker control itself is initialized here (official module
	 * tutorial pattern for custom widgets).
	 */
	init: function() {
		for (const colorpicker of jQuery('.<?= ZBX_STYLE_COLOR_PICKER ?> input')) {
			jQuery(colorpicker).colorpicker();
		}

		// Hide any open picker when the properties overlay reloads/closes
		const overlay = overlays_stack.getById('widget_properties');
		for (const event of ['overlay.reload', 'overlay.close']) {
			overlay.$dialogue[0].addEventListener(event, () => jQuery.colorpicker('hide'));
		}
	}
};
