<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/

namespace Modules\ZabbixClonehosts;

use APP,
	CMenu,
	CMenuItem;
use Modules\ZabbixClonehosts\CompatHelper;

/**
 * Trait containing the module initialization logic shared across Zabbix versions.
 */
trait ModuleInitTrait {

	public function init(): void {
		$menu_label = LangHelper::t('menu.clonehosts');

		// Zabbix menu structure differs by version:
		// - Zabbix 6.0: Configuration → Hosts
		// - Zabbix 6.4+: Data collection → Hosts
		$main_menu_name = CompatHelper::isZabbix60() ? _('Configuration') : _('Data collection');

		APP::Component()->get('menu.main')
			->findOrAdd($main_menu_name)
			->getSubmenu()
			->insertAfter(_('Hosts'),
				(new CMenuItem($menu_label))->setAction('clonehosts')
			);
	}
}

// Compatibility: Zabbix 6.4+ uses Zabbix\Core\CModule, Zabbix 6.0 uses Core\CModule
if (class_exists('Zabbix\\Core\\CModule', false)) {
	class Module extends \Zabbix\Core\CModule {
		use ModuleInitTrait;
	}
} else {
	class Module extends \Core\CModule {
		use ModuleInitTrait;
	}
}
