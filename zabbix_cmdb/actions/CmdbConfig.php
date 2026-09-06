<?php
namespace Modules\ZabbixCmdb\Actions;

require_once dirname(__DIR__).'/lib/ItemConfig.php';
use Modules\ZabbixCmdb\Lib\ItemConfig;

class CmdbConfig extends \CController {
    public function init(): void {
        // This action serves GET and POST; POST tokens are explicitly checked below.
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation();
        } elseif (method_exists($this, 'disableSIDvalidation')) {
            $this->disableSIDvalidation();
        }
    }

    protected function checkInput(): bool {
        if (!$this->validateInput(['rules_json' => 'string', 'config_token' => 'string'])) {
            $this->setResponse(new \CControllerResponseFatal());
            return false;
        }
        return true;
    }

    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_SUPER_ADMIN;
    }

    public static function token(): string {
        return class_exists('CCsrfTokenHelper')
            ? \CCsrfTokenHelper::get('cmdb.config')
            : hash('sha256', \CSessionHelper::getId().'cmdb.config');
    }

    protected function doAction(): void {
        $message = '';
        $error = '';
        $rules = [];
        try {
            $rules = ItemConfig::load();
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                if (!hash_equals(self::token(), $this->getInput('config_token', ''))) {
                    throw new \RuntimeException('安全令牌无效，请刷新页面后重试。');
                }
                $input = $this->getInput('rules_json', '');
                if (strlen($input) > 200000) {
                    throw new \InvalidArgumentException('配置内容过大。');
                }
                $changes = json_decode($input, true, 32, JSON_THROW_ON_ERROR);
                if (!is_array($changes)) {
                    throw new \InvalidArgumentException('配置必须是 JSON 对象。');
                }
                ItemConfig::save($changes);
                $rules = ItemConfig::load();
                $message = '配置已保存，主机列表和分组统计立即使用新规则。';
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        $response = new \CControllerResponseData([
            'rules' => $rules, 'message' => $message, 'error' => $error,
            'token' => self::token(),
            'submitted' => $error !== '' ? $this->getInput('rules_json', '') : ''
        ]);
        $response->setTitle('CMDB 配置');
        $this->setResponse($response);
    }
}
