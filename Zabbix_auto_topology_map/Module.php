<?php declare(strict_types = 1);

namespace Modules\Topomap;

use Modules\Topomap\Lib\LanguageManager;

if (class_exists('Zabbix\\Core\\CModule')) {
	class Module extends \Zabbix\Core\CModule {
		public function init(): void {
			$this->registerMenu();
		}

		/**
		 * 在「监测」下注册「自动拓扑图」入口。
		 * 整段包在 try/catch 里：菜单组件在不同版本签名略有差异，
		 * 失败时只丢菜单，不能让所有前端页面白屏。
		 */
		private function registerMenu(): void {
			try {
				// 注意：必须是 \APP，不能写成 APP —— 命名空间内 APP 会被解析成
				// Modules\Topomap\APP，Zabbix 的 autoloader 找不到就直接抛异常，
				// PHP 的「全局类回退」不会发生，结果就是菜单静默丢失。
				$submenu = \APP::Component()->get('menu.main')
					->findOrAdd(_('Monitoring'))
					->getSubmenu();

				if (method_exists($submenu, 'find') && $submenu->find(LanguageManager::t('自动拓扑图')) !== null) {
					return;
				}

				$item = (new \CMenuItem(LanguageManager::t('自动拓扑图')))->setAction('topomap.view');

				if (method_exists($submenu, 'insertAfter')) {
					$submenu->insertAfter(_('Maps'), $item);
				}
				else {
					$submenu->add($item);
				}
			}
			catch (\Throwable $e) {
				// 菜单注册失败不影响模块页面本身（仍可直接访问 zabbix.php?action=topomap.view）
			}
		}
	}
}
else {
	class Module extends \Core\CModule {
		public function init(): void {
			$this->registerMenu();
		}

		private function registerMenu(): void {
			try {
				// 注意：必须是 \APP，不能写成 APP —— 命名空间内 APP 会被解析成
				// Modules\Topomap\APP，Zabbix 的 autoloader 找不到就直接抛异常，
				// PHP 的「全局类回退」不会发生，结果就是菜单静默丢失。
				$submenu = \APP::Component()->get('menu.main')
					->findOrAdd(_('Monitoring'))
					->getSubmenu();

				if (method_exists($submenu, 'find') && $submenu->find(LanguageManager::t('自动拓扑图')) !== null) {
					return;
				}

				$item = (new \CMenuItem(LanguageManager::t('自动拓扑图')))->setAction('topomap.view');

				if (method_exists($submenu, 'insertAfter')) {
					$submenu->insertAfter(_('Maps'), $item);
				}
				else {
					$submenu->add($item);
				}
			}
			catch (\Throwable $e) {
			}
		}
	}
}
