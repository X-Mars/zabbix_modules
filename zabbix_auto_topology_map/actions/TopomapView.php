<?php declare(strict_types = 1);

namespace Modules\Topomap\Actions;

use API;
use CController;
use CControllerResponseData;
use CProfile;
use Modules\Topomap\Lib\LanguageManager;

/**
 * 「监测 → 自动拓扑图」页面。
 * 页面本身只输出骨架，数据与交互全部在 assets/js/topomap.js 里，
 * 后端接口见 TopomapData（zabbix.php?action=topomap.data）。
 *
 * 视图记忆：完全照抄原生 map.view 的做法 ——
 *   原生 CControllerMapView 在不带 sysmapid 时读 CProfile::get('web.maps.sysmapid')，
 *   而列表页 sysmaps.php 打开时会 CProfile::delete('web.maps.sysmapid')。
 *   所以「在拓扑图画布切走、再从菜单点回来还是那张图；在列表切走点回来还是列表」
 *   本质是「用户 profile 里存着上次看的图 id，回列表时清掉」。
 *   本模块是单页 SPA（列表和画布同一个 action），所以「回列表」的清理由前端
 *   调 topomap.data&mode=remember 完成，读写共用同一个 profile key。
 */
class TopomapView extends CController {

	/** 用户 profile 里记「上次看的是哪张图」的键，与原生 web.maps.sysmapid 同构。 */
	public const PROFILE_KEY = 'web.topomap.sysmapid';

	public function init(): void {
		// 7.0 的 CController::run() 对未关闭 CSRF 校验的控制器，GET 请求会被判为
		// 校验失败直接「访问拒绝」，只读页面必须关闭。方法名各版本不一致，逐个尝试。
		foreach (['disableCsrfValidation', 'disableSIDValidation', 'disableSIDvalidation'] as $method) {
			if (method_exists($this, $method)) {
				$this->$method();
			}
		}
	}

	protected function checkInput(): bool {
		$this->validateInput([
			'sysmapid' => 'string'
		]);

		return true;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
	}

	protected function doAction(): void {
		$sysmapid = trim((string) $this->getInput('sysmapid', ''));

		// 菜单点进来是不带 sysmapid 的（菜单项 action 固定为 topomap.view），
		// 这时用 profile 里记住的图，对齐原生行为。
		if ($sysmapid === '') {
			$sysmapid = $this->rememberedMapId();
		}

		$response = new CControllerResponseData([
			'main_menu' => '',
			'sub_menu' => '',
			'sysmapid' => $sysmapid
		]);

		// 7.0 只认 setTitle()，把 title 塞进构造参数是取不到的，
		// 结果浏览器标签只剩站名。必须显式 setTitle。
		$response->setTitle(LanguageManager::t('自动拓扑图'));

		$this->setResponse($response);
	}

	/**
	 * 取上次浏览的拓扑图 id；图已被删或没有记录则返回空串（前端据此显示列表）。
	 */
	private function rememberedMapId(): string {
		try {
			$sysmapid = (int) CProfile::get(self::PROFILE_KEY, 0);

			if ($sysmapid <= 0) {
				return '';
			}

			$maps = API::Map()->get([
				'output' => ['sysmapid'],
				'sysmapids' => [$sysmapid]
			]);

			if ($maps) {
				return (string) $sysmapid;
			}

			// 图被删了：顺手把脏记录清掉，免得每次点菜单都白查一次。
			CProfile::delete(self::PROFILE_KEY);
		}
		catch (\Throwable $e) {
			// 取不到就当没有记忆，绝不能因此让整页 500。
		}

		return '';
	}
}
