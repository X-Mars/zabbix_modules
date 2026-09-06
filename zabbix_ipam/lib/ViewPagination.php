<?php

namespace Modules\ZabbixIpam\Lib;

require_once __DIR__.'/LanguageManager.php';

class ViewPagination {
    public static function render(string $action, array $pagination, array $filters = []): string {
        $escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $translate = [LanguageManager::class, 't'];
        $base = array_merge(['action' => $action], $filters);
        unset($base['page'], $base['per_page']);

        $hidden = '';
        foreach ($base as $name => $value) {
            $hidden .= '<input type="hidden" name="'.$escape($name).'" value="'.$escape($value).'">';
        }

        $sizes = '';
        foreach ([20, 50, 100, 200] as $size) {
            $selected = (int) $pagination['per_page'] === $size ? ' selected' : '';
            $sizes .= '<option value="'.$size.'"'.$selected.'>'.$size.'</option>';
        }

        $page_url = static function (int $page) use ($base, $pagination): string {
            return 'zabbix.php?'.http_build_query(array_merge($base, [
                'page' => $page,
                'per_page' => $pagination['per_page']
            ]));
        };

        $html = '<div class="ipam-pagination"><div class="ipam-pagination-info">'
            .'<form class="ipam-page-size js-auto-filter" method="get">'.$hidden.'<input type="hidden" name="page" value="1">'
            .'<label><span>'.$translate('Items per page').'</span><select name="per_page">'.$sizes.'</select></label></form>'
            .'<span>'.sprintf($translate('%1$d items in total'), (int) $pagination['total']).'</span></div><nav>';

        if ($pagination['page'] > 1) {
            $html .= '<a class="btn-alt" href="'.$escape($page_url($pagination['page'] - 1)).'">'.$translate('Previous').'</a>';
        }
        $html .= '<span>'.sprintf($translate('Page %1$d of %2$d'), $pagination['page'], $pagination['pages']).'</span>';
        if ($pagination['page'] < $pagination['pages']) {
            $html .= '<a class="btn-alt" href="'.$escape($page_url($pagination['page'] + 1)).'">'.$translate('Next').'</a>';
        }

        return $html.'</nav></div>';
    }
}
