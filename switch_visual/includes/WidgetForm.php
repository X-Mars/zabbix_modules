<?php declare(strict_types=1);

namespace Modules\SwitchVisual\Includes;

use Zabbix\Widgets\CWidgetForm;
use Zabbix\Widgets\Fields\CWidgetFieldCheckBox;
use Zabbix\Widgets\Fields\CWidgetFieldColor;
use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectGroup;
use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectHost;
use Zabbix\Widgets\Fields\CWidgetFieldIntegerBox;
use Zabbix\Widgets\Fields\CWidgetFieldSelect;
use Zabbix\Widgets\Fields\CWidgetFieldTextBox;

class WidgetForm extends CWidgetForm {

	public function addFields(): self {
		require_once __DIR__ . '/Translation.php';

		// Shown directly below the standard "Refresh interval" section.
		$this->addField(
			(new CWidgetFieldMultiSelectGroup('hostgroups', Translation::t('Host groups')))
		);

		$this->addField(
			(new CWidgetFieldMultiSelectHost('hostids', Translation::t('Host')))->setMultiple(false)
		);

		$this->addField(
			(new CWidgetFieldTextBox('bw_in_pattern', Translation::t('BW In item pattern')))->setDefault('ifInOctets[*]')
		);
		$this->addField(
			(new CWidgetFieldTextBox('bw_out_pattern', Translation::t('BW Out item pattern')))->setDefault('ifOutOctets[*]')
		);
		$this->addField(
			(new CWidgetFieldCheckBox('bw_bits', Translation::t('Bandwidth items deliver bits/sec (not bytes/sec)')))->setDefault(1)
		);
		$this->addField(
			(new CWidgetFieldTextBox('status_pattern', Translation::t('Status item pattern')))->setDefault('ifOperStatus[*]')
		);
		$this->addField(
			(new CWidgetFieldTextBox('speed_pattern', Translation::t('Speed item pattern')))->setDefault('ifHighSpeed[*]')
		);
		$this->addField(
			(new CWidgetFieldTextBox('err_in_pattern', Translation::t('Errors In item pattern')))->setDefault('ifInErrors[*]')
		);
		$this->addField(
			(new CWidgetFieldTextBox('err_out_pattern', Translation::t('Errors Out item pattern')))->setDefault('ifOutErrors[*]')
		);

		$this->addField(
			(new CWidgetFieldTextBox('alias_pattern', Translation::t('Interface alias pattern (optional)')))->setDefault('')
		);
		$this->addField(
			(new CWidgetFieldTextBox('poe_pattern', Translation::t('PoE status pattern (optional, e.g. pethPsePortDetectionStatus[*])')))->setDefault('')
		);
		$this->addField(
			(new CWidgetFieldTextBox('poe_pwr_pattern', Translation::t('PoE port power pattern (optional, e.g. pethPsePortPowerConsumption[*], milliwatts)')))->setDefault('')
		);
		$this->addField(
			(new CWidgetFieldTextBox('duplex_pattern', Translation::t('Duplex mode pattern (optional, e.g. dot3StatsDuplexStatus[*])')))->setDefault('')
		);
		$this->addField(
			(new CWidgetFieldTextBox('sfp_rx_pwr_pattern', Translation::t('SFP optical RX power pattern (optional)')))->setDefault('')
		);
		$this->addField(
			(new CWidgetFieldTextBox('sfp_tx_pwr_pattern', Translation::t('SFP optical TX power pattern (optional)')))->setDefault('')
		);

		$this->addField(
			(new CWidgetFieldSelect('port_shape', Translation::t('Port shape'), [
				1 => Translation::t('Style 1 (stepped)'),
				2 => Translation::t('Style 2 (metal)'),
				3 => Translation::t('Style 3 (classic)'),
			]))->setDefault(3)
		);
		$this->addField(
			(new CWidgetFieldIntegerBox('num_ports', Translation::t('RJ45 ports (per stack member; the rest are SFP)'), 1, 96))->setDefault(24)
		);
		$this->addField(
			(new CWidgetFieldSelect('rj45_pos', Translation::t('RJ45 position'), [
				0 => Translation::t('Front (ports 1..N are RJ45)'),
				1 => Translation::t('Back (last N ports are RJ45 — SFP first)'),
			]))->setDefault(0)
		);
		$this->addField(
			(new CWidgetFieldIntegerBox('num_sfp', Translation::t('SFP ports (manual mode only — with auto-detect the ports beyond RJ45 count are SFP)'), 0, 96))->setDefault(0)
		);
		$this->addField(
			(new CWidgetFieldIntegerBox('port_rows', Translation::t('Port rows (1 or 2)'), 1, 2))->setDefault(2)
		);
		$this->addField(
			(new CWidgetFieldCheckBox('port_inverted', Translation::t('Invert row order (even ports top — e.g. Huawei)')))->setDefault(0)
		);
		$this->addField(
			(new CWidgetFieldCheckBox('port_sequential', Translation::t('Sequential rows (first half top, second half bottom — e.g. 1–24 / 25–48)')))->setDefault(0)
		);
		$this->addField(
			(new CWidgetFieldIntegerBox('scale', Translation::t('Zoom (50–300%)'), 50, 300))->setDefault(100)
		);
		$this->addField(
			(new CWidgetFieldTextBox('port_aliases_manual', Translation::t('Port aliases (e.g. 1=Uplink, 3=ESX01, 4-6=VLAN1)')))->setDefault('')
		);

		$this->addField(
			(new CWidgetFieldTextBox('uptime_key', Translation::t('Uptime item key (optional)')))->setDefault('')
		);
		$this->addField(
			(new CWidgetFieldTextBox('serial_key', Translation::t('Serial number item key (optional)')))->setDefault('')
		);
		$this->addField(
			(new CWidgetFieldTextBox('model_key', Translation::t('Model item key (optional)')))->setDefault('')
		);
		$this->addField(
			(new CWidgetFieldTextBox('cpu_key', Translation::t('CPU % item pattern (optional, e.g. system.cpu.util[*])')))->setDefault('')
		);
		$this->addField(
			(new CWidgetFieldTextBox('memory_key', Translation::t('Memory % item pattern (optional, e.g. vm.memory.pused[*])')))->setDefault('')
		);
		$this->addField(
			(new CWidgetFieldTextBox('temperature_key', Translation::t('Temperature item pattern (optional, e.g. sensor.temp.value[*]; multiple sensors: panel shows max, hover shows all)')))->setDefault('')
		);
		$this->addField(
			(new CWidgetFieldIntegerBox('temperature_threshold', Translation::t('Temperature alarm threshold (°C, 0 = disabled)'), 0, 200))->setDefault(0)
		);
		$this->addField(
			(new CWidgetFieldTextBox('poe_total_key', Translation::t('Total PoE consumed item key (optional, e.g. pethMainPseConsumptionPower.1, watts)')))->setDefault('')
		);
		$this->addField(
			(new CWidgetFieldTextBox('poe_max_key', Translation::t('Max PoE capacity item key (optional, e.g. pethMainPsePower.1, watts)')))->setDefault('')
		);
		$this->addField(
			(new CWidgetFieldTextBox('fan_pattern', Translation::t('Fan status pattern (optional, e.g. hpicfFanState[*])')))->setDefault('')
		);
		$this->addField(
			(new CWidgetFieldIntegerBox('fan_ok_value', Translation::t('Fan OK value (3=HP good, 1=some vendors — check MIB)'), 1, 255))->setDefault(3)
		);

		$this->addField(
			(new CWidgetFieldColor('chassis_color', Translation::t('Chassis color')))->setDefault('404c58')
		);
		$this->addField(
			(new CWidgetFieldColor('color_1g', Translation::t('1 Gbps port color')))->setDefault('2ad468')
		);
		$this->addField(
			(new CWidgetFieldColor('color_100m', Translation::t('100 Mbps port color')))->setDefault('c8a020')
		);
		$this->addField(
			(new CWidgetFieldColor('color_10g', Translation::t('10 Gbps port color')))->setDefault('2090e0')
		);
		$this->addField(
			(new CWidgetFieldColor('color_alert', Translation::t('Alert / warning port color')))->setDefault('e89000')
		);
		$this->addField(
			(new CWidgetFieldColor('color_error', Translation::t('Error / down port color')))->setDefault('e83838')
		);
		$this->addField(
			(new CWidgetFieldIntegerBox('port_index_start', Translation::t('Port index start (SNMP offset)'), 1, 999999))->setDefault(1)
		);
		$this->addField(
			(new CWidgetFieldCheckBox('auto_detect_ports', Translation::t('Auto-detect port count (may include VLANs/tunnels)')))->setDefault(0)
		);
		$this->addField(
			(new CWidgetFieldTextBox('exclude_ports', Translation::t('Excluded ports (auto-detect only; comma/space separated; name substring match, digits match index, * wildcard, e.g. vlan, Bridge-Aggregation, mgmt*)')))->setDefault('')
		);
		$this->addField(
			(new CWidgetFieldCheckBox('show_summary', Translation::t('Show summary bar')))->setDefault(1)
		);
		$this->addField(
			(new CWidgetFieldCheckBox('show_port_numbers', Translation::t('Show port numbers')))->setDefault(1)
		);
		$this->addField(
			(new CWidgetFieldCheckBox('show_port_labels', Translation::t('Show port labels / aliases')))->setDefault(1)
		);
		$this->addField(
			(new CWidgetFieldCheckBox('show_sparkline', Translation::t('Show global traffic sparkline')))->setDefault(1)
		);
		$this->addField(
			(new CWidgetFieldIntegerBox('sparkline_minutes', Translation::t('Sparkline window (minutes)'), 5, 360))->setDefault(30)
		);
		$this->addField(
			(new CWidgetFieldIntegerBox('util_threshold', Translation::t('Utilization warning threshold (%)'), 1, 100))->setDefault(80)
		);

		return $this;
	}
}
