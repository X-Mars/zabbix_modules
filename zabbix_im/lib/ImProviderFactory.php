<?php

namespace Modules\ZabbixIm\Lib;

class ImProviderFactory {

    public static function create(array $config): ImProviderInterface {
        $provider = strtolower(trim((string) ($config['provider'] ?? 'wecom')));
        $verifySsl = (bool) ($config['verify_ssl'] ?? true);

        switch ($provider) {
            case 'feishu':
                return new FeishuClient($config['feishu'] ?? [], $verifySsl);
            case 'dingtalk':
                return new DingTalkClient($config['dingtalk'] ?? [], $verifySsl);
            case 'wecom':
            default:
                return new WeComClient($config['wecom'] ?? [], $verifySsl);
        }
    }
}
