<?php declare(strict_types=1);

use Modules\SwitchVisual\Includes\Translation;

$form = new CWidgetFormView($data);

// Shown directly below the standard "Refresh interval" section.
$form->addField(new CWidgetFieldMultiSelectGroupView($data['fields']['hostgroups']));

$form->addField(new CWidgetFieldMultiSelectHostView($data['fields']['hostids']));

// ── PORTS (expanded by default) ──────────────────────────────────────────────
$form->addFieldset(
    (new CWidgetFormFieldsetCollapsibleView(Translation::t('Ports')))
        ->setExpanded(true)
        ->addField(new CWidgetFieldTextBoxView($data['fields']['bw_in_pattern']))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['bw_out_pattern']))
        ->addField(new CWidgetFieldCheckBoxView($data['fields']['bw_bits']))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['status_pattern']))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['speed_pattern']))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['err_in_pattern']))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['err_out_pattern']))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['alias_pattern']))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['duplex_pattern']))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['sfp_rx_pwr_pattern']))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['sfp_tx_pwr_pattern']))
        ->addField(new CWidgetFieldSelectView($data['fields']['port_shape']))
        ->addField(new CWidgetFieldIntegerBoxView($data['fields']['num_ports']))
        ->addField(new CWidgetFieldSelectView($data['fields']['rj45_pos']))
        ->addField(new CWidgetFieldIntegerBoxView($data['fields']['num_sfp']))
        ->addField(new CWidgetFieldIntegerBoxView($data['fields']['port_rows']))
        ->addField(new CWidgetFieldCheckBoxView($data['fields']['port_inverted']))
        ->addField(new CWidgetFieldCheckBoxView($data['fields']['port_sequential']))
        ->addField(new CWidgetFieldIntegerBoxView($data['fields']['scale']))
        ->addField(new CWidgetFieldIntegerBoxView($data['fields']['port_index_start']))
        ->addField(new CWidgetFieldCheckBoxView($data['fields']['auto_detect_ports']))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['exclude_ports']))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['port_aliases_manual']))
);

// ── POE (collapsed) ──────────────────────────────────────────────────────────
$form->addFieldset(
    (new CWidgetFormFieldsetCollapsibleView(Translation::t('PoE')))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['poe_pattern']))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['poe_pwr_pattern']))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['poe_total_key']))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['poe_max_key']))
);

// ── SYSTEM (collapsed) ───────────────────────────────────────────────────────
$form->addFieldset(
    (new CWidgetFormFieldsetCollapsibleView(Translation::t('System')))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['uptime_key']))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['serial_key']))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['model_key']))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['cpu_key']))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['memory_key']))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['temperature_key']))
        ->addField(new CWidgetFieldIntegerBoxView($data['fields']['temperature_threshold']))
        ->addField(new CWidgetFieldTextBoxView($data['fields']['fan_pattern']))
        ->addField(new CWidgetFieldIntegerBoxView($data['fields']['fan_ok_value']))
);

// ── APPEARANCE (collapsed) ───────────────────────────────────────────────────
$form->addFieldset(
    (new CWidgetFormFieldsetCollapsibleView(Translation::t('Appearance')))
        ->addField(new CWidgetFieldColorView($data['fields']['chassis_color']))
        ->addField(new CWidgetFieldColorView($data['fields']['color_1g']))
        ->addField(new CWidgetFieldColorView($data['fields']['color_100m']))
        ->addField(new CWidgetFieldColorView($data['fields']['color_10g']))
        ->addField(new CWidgetFieldColorView($data['fields']['color_alert']))
        ->addField(new CWidgetFieldColorView($data['fields']['color_error']))
);

// ── DISPLAY (collapsed) ──────────────────────────────────────────────────────
$form->addFieldset(
    (new CWidgetFormFieldsetCollapsibleView(Translation::t('Display')))
        ->addField(new CWidgetFieldCheckBoxView($data['fields']['show_summary']))
        ->addField(new CWidgetFieldCheckBoxView($data['fields']['show_port_numbers']))
        ->addField(new CWidgetFieldCheckBoxView($data['fields']['show_port_labels']))
        ->addField(new CWidgetFieldCheckBoxView($data['fields']['show_sparkline']))
        ->addField(new CWidgetFieldIntegerBoxView($data['fields']['sparkline_minutes']))
        ->addField(new CWidgetFieldIntegerBoxView($data['fields']['util_threshold']))
);

// Initialize the color picker controls of the "Appearance" section
$form->includeJsFile('widget.edit.js.php');
$form->addJavaScript('switch_visual_form.init();');

$form->show();
