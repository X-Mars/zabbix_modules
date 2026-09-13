<?php declare(strict_types=1);

namespace Modules\SwitchVisual\Actions;

use CController;
use CControllerResponseData;

/**
 * AJAX endpoint: returns 30-minute TX/RX history series for a single port.
 *
 * Called by widget JS on first hover/click. Returns JSON:
 *   { ok: bool, error: string, data: { in: float[], out: float[] } }
 *
 * CSRF validation disabled — read-only, permission-gated endpoint.
 */
class WidgetSparkline extends CController {

    private const LOOKBACK_SECONDS = 1800;
    private const POINT_LIMIT      = 30;

    protected function init(): void {
        $this->disableCsrfValidation();
    }

    protected function checkInput(): bool {
        return $this->validateInput([
            'hostid'     => 'required|int32',
            'bw_in_key'  => 'string',
            'bw_out_key' => 'string',
        ]);
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }

    protected function doAction(): void {
        $hostid     = (int)    $this->getInput('hostid');
        $bw_in_key  = trim((string) $this->getInput('bw_in_key',  ''));
        $bw_out_key = trim((string) $this->getInput('bw_out_key', ''));

        if ($hostid <= 0) {
            $this->jsonRespond(false, 'Invalid host.');
            return;
        }

        $keys = array_values(array_unique(array_filter([$bw_in_key, $bw_out_key])));

        if ($keys === []) {
            $this->jsonRespond(true, '', ['in' => [], 'out' => []]);
            return;
        }

        try {
            $items = \API::Item()->get([
                'output'   => ['itemid', 'key_', 'value_type'],
                'hostids'  => [$hostid],
                'filter'   => ['key_' => $keys],
                'webitems' => true,
            ]);
        } catch (\Throwable $e) {
            $this->jsonRespond(false, 'API error.');
            return;
        }

        if (!is_array($items)) {
            $this->jsonRespond(false, 'API error.');
            return;
        }

        $meta = [];
        foreach ($items as $item) {
            $meta[(string) $item['key_']] = [
                'itemid'     => (string) $item['itemid'],
                'value_type' => (int)    $item['value_type'],
            ];
        }

        $this->jsonRespond(true, '', [
            'in'  => $this->loadSeries($meta[$bw_in_key]  ?? null),
            'out' => $this->loadSeries($meta[$bw_out_key] ?? null),
        ]);
    }

    private function loadSeries(?array $item_meta): array {
        if ($item_meta === null) {
            return [];
        }
        // Only numeric value types supported by history.get (float=0, uint64=3).
        if (!in_array($item_meta['value_type'], [0, 3], true)) {
            return [];
        }

        try {
            $rows = \API::History()->get([
                'output'    => ['clock', 'value'],
                'itemids'   => [$item_meta['itemid']],
                'history'   => $item_meta['value_type'],
                'time_from' => time() - self::LOOKBACK_SECONDS,
                'sortfield' => 'clock',
                'sortorder' => 'DESC',
                'limit'     => self::POINT_LIMIT,
            ]);
        } catch (\Throwable $e) {
            return [];
        }

        if (!is_array($rows) || $rows === []) {
            return [];
        }

        return array_map(
            static fn(array $r): float => (float) $r['value'],
            array_reverse($rows)
        );
    }

    private function jsonRespond(bool $ok, string $error = '', array $data = []): void {
        $this->setResponse(new CControllerResponseData([
            'main_block' => json_encode(
                ['ok' => $ok, 'error' => $error, 'data' => $data],
                JSON_THROW_ON_ERROR
            ),
        ]));
    }
}
