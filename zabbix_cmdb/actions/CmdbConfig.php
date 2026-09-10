<?php
namespace Modules\ZabbixCmdb\Actions;

require_once dirname(__DIR__).'/lib/ItemConfig.php';
use Modules\ZabbixCmdb\Lib\ItemConfig;
use Modules\ZabbixCmdb\Lib\LanguageManager;

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
                    throw new \RuntimeException(LanguageManager::t('Invalid security token. Refresh the page and try again.'));
                }
                $input = $this->getInput('rules_json', '');
                if (strlen($input) > 200000) {
                    throw new \InvalidArgumentException(LanguageManager::t('The configuration is too large.'));
                }
                $changes = json_decode($input, true, 32, JSON_THROW_ON_ERROR);
                if (!is_array($changes)) {
                    throw new \InvalidArgumentException(LanguageManager::t('The configuration must be a JSON object.'));
                }
                ItemConfig::save($changes);
                $rules = ItemConfig::load();
                $message = LanguageManager::t('Configuration saved. The host list and group statistics now use the new rules.');
            }
        } catch (\JsonException $e) {
            $error = LanguageManager::t('Invalid JSON configuration.');
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }
        $response = new \CControllerResponseData([
            'rules' => $rules, 'message' => $message, 'error' => $error,
            'token' => self::token(),
            'submitted' => $error !== '' ? $this->getInput('rules_json', '') : ''
        ]);
        $response->setTitle(LanguageManager::t('Item Configuration'));
        $this->setResponse($response);
    }
}
