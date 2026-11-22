<?php
namespace Modules\SwitchVisual\Actions;

use Modules\SwitchVisual\Includes\Translation;

require_once dirname(__DIR__).'/includes/Translation.php';
require_once dirname(__DIR__).'/includes/DataFetcher.php';

class StandaloneView extends \CController {
    protected function init(): void {
        if (method_exists($this, 'disableCsrfValidation')) {
            $this->disableCsrfValidation();
        } else {
            $this->disableSIDvalidation();
        }
    }
    protected function checkInput(): bool {
        return $this->validateInput(['hostid' => 'db hosts.hostid']);
    }
    protected function checkPermissions(): bool {
        return $this->getUserType() >= USER_TYPE_ZABBIX_USER;
    }
    protected function doAction(): void {
        $options = \API::Host()->get(['output' => ['hostid', 'name'], 'sortfield' => 'name']);
        $selected = (string) $this->getInput('hostid', '');
        $hosts = [];
        foreach ($options as $host) {
            if ((string) $host['hostid'] !== $selected) {
                continue;
            }
            $fetcher = new \Modules\SwitchVisual\Includes\DataFetcher(['hostid' => $selected]);
            $hosts[] = $fetcher->fetchAll() + $host + ['num_ports' => 24];
        }
        $title = Translation::t('Switch visual');
        $response = new \CControllerResponseData([
            'standalone' => true, 'hosts' => $hosts, 'no_host' => !$hosts,
            'fields' => [], 'error' => null, 'host_options' => $options,
            'selected_hostid' => $selected, 'widget_name' => $title
        ]);
        $response->setTitle($title);
        $this->setResponse($response);
    }
}
