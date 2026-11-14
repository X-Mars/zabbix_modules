<?php

namespace Modules\ZabbixBackupMysql\Lib;

final class Access {
    public static function allowed(): bool {
        // A complete dump includes passwords, API tokens and configuration outside Admin host permissions.
        return \CWebUser::isLoggedIn() && (int) \CWebUser::getType() === USER_TYPE_SUPER_ADMIN;
    }
}
