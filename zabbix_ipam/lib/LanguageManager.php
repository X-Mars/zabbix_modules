<?php

namespace Modules\ZabbixIpam\Lib;

class LanguageManager {
    private static $language;

    private static $zh = [
        'IPAM' => 'IP 管理',
        'IP Management' => 'IP 管理',
        'IP Details' => 'IP 详情',
        'Report' => '报表',
        'IP matrix' => 'IP 矩阵',
        'Task Management' => '任务管理',
        'Manage IPv4 ranges and run ICMP-only availability scans.' => '集中管理 IPv4 地址段并执行仅 ICMP 的存活扫描。',
        'Track ICMP scan progress and review completed background tasks.' => '跟踪 ICMP 扫描进度并查看后台任务结果。',
        'Browse every address, its latest ICMP status, and its matched Zabbix host.' => '查看每个 IP、最近一次 ICMP 状态及其匹配的 Zabbix 主机。',
        'Quick guide' => '使用说明',
        'Add a range, start an ICMP scan, then click the IP count to view the availability matrix. Green means alive; gray means unreachable or not scanned.' => '添加 IP 段后启动 ICMP 扫描；点击 IP 数量可查看使用矩阵。绿色表示存活，灰色表示不可达或尚未扫描。',
        'Association rules' => '关联说明',
        'A host is associated automatically when one of its Zabbix interfaces uses the same IPv4 address. Click the host name to open its latest data page.' => '当 Zabbix 主机接口使用相同 IPv4 地址时自动建立关联。点击主机名称可打开该主机的最新数据页面。',
        'Add IP range' => '添加 IP 段',
        'Edit IP range' => '修改 IP 段',
        'All IP ranges' => '全部 IP 段',
        'IP' => 'IP',
        'IP range' => 'IP 段',
        'IP range name' => 'IP 段名称',
        'No.' => '序号',
        'Task ID' => '任务 ID',
        'IP status' => 'IP 状态',
        'Status last updated' => '状态最后更新时间',
        'Name' => '名称',
        'Example: Office network' => '例如：办公网络',
        'Search' => '搜索',
        'Search name or CIDR' => '搜索名称或 CIDR',
        'Search task ID or range name' => '搜索任务 ID 或 IP 段名称',
        'Search IP, range, or host name' => '搜索 IP、IP 段或主机名称',
        'Status' => '状态',
        'Enabled status' => '启用状态',
        'Scan status' => '扫描状态',
        'Last scan time' => '最后扫描时间',
        'Filter' => '筛选',
        'Reset' => '重置',
        'All statuses' => '全部状态',
        'All enabled statuses' => '全部启用状态',
        'All scan statuses' => '全部扫描状态',
        'All IP statuses' => '全部 IP 状态',
        'Enabled' => '已启用',
        'Disabled' => '已停用',
        'Pending' => '等待中',
        'Running' => '扫描中',
        'Completed' => '已完成',
        'Failed' => '失败',
        'Stopped' => '已停止',
        'Never' => '尚未扫描',
        'Alive' => '存活',
        'Unreachable' => '不可达',
        'Not scanned' => '未扫描',
        'Alive IPs / Total' => '存活 IP / IP 总数',
        'Host association' => '主机关联状态',
        'Associated host' => '关联主机名称',
        'All associations' => '全部关联状态',
        'Associated' => '已关联',
        'Unassociated' => '未关联',
        'No associated host' => '无关联主机',
        'Actions' => '操作',
        'Start scan' => '开始扫描',
        'View task' => '查看扫描任务',
        'Edit' => '修改',
        'Delete' => '删除',
        'No IP ranges found.' => '没有符合条件的 IP 段。',
        'No IP addresses found.' => '没有符合条件的 IP。',
        'Scan schedule' => '扫描计划',
        'The scan interval is controlled by crontab. Each enabled IP range is scanned whenever the cron job runs.' => '扫描间隔由 crontab 设置；每次 cron 任务运行时，所有已启用的 IP 段都会执行扫描。',
        'Enable scheduled scans' => '启用定时扫描',
        'When enabled, this range is scanned each time the IPAM cron job runs.' => '启用后，每次 IPAM cron 任务运行时都会扫描此 IP 段。',
        'Cancel' => '取消',
        'Save' => '保存',
        'Close' => '关闭',
        'IP availability matrix' => 'IP 使用矩阵',
        'Unreachable / not scanned' => '不可达 / 未扫描',
        'Loading...' => '正在加载…',
        'Request failed.' => '请求失败，请稍后重试。',
        'Delete this IP range?' => '确定删除这个 IP 段吗？',
        'Tasks' => '任务总数',
        'Task / IP range' => '任务 / IP 段',
        'Progress' => '进度',
        'Shards' => '分片',
        'Created' => '创建时间',
        'Stop' => '停止',
        'View range' => '查看 IP 段',
        'No scan tasks found.' => '没有符合条件的扫描任务。',
        'Previous' => '上一页',
        'Next' => '下一页',
        'Items per page' => '每页数量',
        'Page %1$d of %2$d' => '第 %1$d / %2$d 页',
        '%1$d items in total' => '共 %1$d 条',
        '%1$d IPs in total' => '共 %1$d 个 IP',
        'Range saved.' => 'IP 段已保存。',
        'Range deleted.' => 'IP 段已删除。',
        'Task started.' => '扫描任务已启动。',
        'Task stopped.' => '扫描任务已停止。',
        'Task not found.' => '未找到扫描任务。',
        'Range not found.' => '未找到 IP 段。',
        'Failed to start task.' => '无法启动扫描任务。',
        'Stop active tasks before deleting this range.' => '请先停止该 IP 段正在运行的扫描任务。',
        'Data directory is not writable.' => '数据目录不可写。'
    ];

    private const RUSSIAN = [
        'IPAM' => 'IPAM',
        'IP Management' => 'Управление IP-адресами',
        'IP Details' => 'Сведения об IP-адресах',
        'Report' => 'Отчёт',
        'IP matrix' => 'Матрица IP-адресов',
        'Task Management' => 'Управление задачами',
        'Manage IPv4 ranges and run ICMP-only availability scans.' => 'Управляйте диапазонами IPv4 и проверяйте их доступность по ICMP.',
        'Track ICMP scan progress and review completed background tasks.' => 'Следите за проверками ICMP и просматривайте результаты фоновых задач.',
        'Browse every address, its latest ICMP status, and its matched Zabbix host.' => 'Просматривайте адреса, их последнее состояние по ICMP и связанные узлы Zabbix.',
        'Quick guide' => 'Краткая инструкция',
        'Add a range, start an ICMP scan, then click the IP count to view the availability matrix. Green means alive; gray means unreachable or not scanned.' => 'Добавьте диапазон и запустите сканирование ICMP. Нажмите на количество адресов, чтобы открыть матрицу доступности. Зелёный цвет обозначает доступный адрес, серый — недоступный или ещё не проверенный.',
        'Association rules' => 'Правила связывания',
        'A host is associated automatically when one of its Zabbix interfaces uses the same IPv4 address. Click the host name to open its latest data page.' => 'Связь создаётся автоматически, если IPv4-адрес совпадает с адресом одного из интерфейсов узла Zabbix. Нажмите на имя узла, чтобы открыть его последние данные.',
        'Add IP range' => 'Добавить диапазон IP',
        'Edit IP range' => 'Изменить диапазон IP',
        'All IP ranges' => 'Все диапазоны IP',
        'IP' => 'IP',
        'IP range' => 'Диапазон IP',
        'IP range name' => 'Имя диапазона IP',
        'No.' => '№',
        'Task ID' => 'ID задачи',
        'IP status' => 'Состояние IP-адреса',
        'Status last updated' => 'Последнее обновление состояния',
        'Name' => 'Имя',
        'Example: Office network' => 'Например, офисная сеть',
        'Search' => 'Поиск',
        'Search name or CIDR' => 'Поиск по имени или CIDR',
        'Search task ID or range name' => 'Поиск по ID задачи или имени диапазона',
        'Search IP, range, or host name' => 'Поиск по IP-адресу, диапазону или имени узла сети',
        'Status' => 'Состояние',
        'Enabled status' => 'Состояние включения',
        'Scan status' => 'Состояние сканирования',
        'Last scan time' => 'Время последнего сканирования',
        'Filter' => 'Фильтр',
        'Reset' => 'Сбросить',
        'All statuses' => 'Все состояния',
        'All enabled statuses' => 'Включённые и отключённые',
        'All scan statuses' => 'Все состояния сканирования',
        'All IP statuses' => 'Все состояния IP-адресов',
        'Enabled' => 'Включено',
        'Disabled' => 'Отключено',
        'Pending' => 'Ожидает',
        'Running' => 'Выполняется',
        'Completed' => 'Завершено',
        'Failed' => 'Ошибка',
        'Stopped' => 'Остановлено',
        'Never' => 'Ни разу',
        'Alive' => 'Доступен',
        'Unreachable' => 'Недоступен',
        'Not scanned' => 'Не проверялся',
        'Alive IPs / Total' => 'Доступных IP / всего',
        'Host association' => 'Связь с узлом сети',
        'Associated host' => 'Связанный узел сети',
        'All associations' => 'Все состояния связи',
        'Associated' => 'Связан',
        'Unassociated' => 'Не связан',
        'No associated host' => 'Нет связанного узла сети',
        'Actions' => 'Действия',
        'Start scan' => 'Начать сканирование',
        'View task' => 'Открыть задачу',
        'Edit' => 'Изменить',
        'Delete' => 'Удалить',
        'No IP ranges found.' => 'Диапазоны IP не найдены.',
        'No IP addresses found.' => 'IP-адреса не найдены.',
        'Scan schedule' => 'Расписание сканирования',
        'The scan interval is controlled by crontab. Each enabled IP range is scanned whenever the cron job runs.' => 'Интервал сканирования задаётся в crontab. При каждом запуске задачи cron сканируются все включённые диапазоны.',
        'Enable scheduled scans' => 'Включить сканирование по расписанию',
        'When enabled, this range is scanned each time the IPAM cron job runs.' => 'Сканировать этот диапазон при каждом запуске задачи IPAM в cron.',
        'Cancel' => 'Отмена',
        'Save' => 'Сохранить',
        'Close' => 'Закрыть',
        'IP availability matrix' => 'Матрица доступности IP-адресов',
        'Unreachable / not scanned' => 'Недоступен или не проверялся',
        'Loading...' => 'Загрузка…',
        'Request failed.' => 'Запрос не выполнен.',
        'Delete this IP range?' => 'Удалить этот диапазон IP?',
        'Tasks' => 'Задачи',
        'Task / IP range' => 'Задача / диапазон IP',
        'Progress' => 'Ход выполнения',
        'Shards' => 'Части задачи',
        'Created' => 'Создано',
        'Stop' => 'Остановить',
        'View range' => 'Открыть диапазон',
        'No scan tasks found.' => 'Задачи сканирования не найдены.',
        'Previous' => 'Предыдущая',
        'Next' => 'Следующая',
        'Items per page' => 'Записей на странице',
        'Page %1$d of %2$d' => 'Страница %1$d из %2$d',
        '%1$d items in total' => 'Всего записей: %1$d',
        '%1$d IPs in total' => 'Всего IP-адресов: %1$d',
        'Range saved.' => 'Диапазон сохранён.',
        'Range deleted.' => 'Диапазон удалён.',
        'Task started.' => 'Задача запущена.',
        'Task stopped.' => 'Задача остановлена.',
        'Task not found.' => 'Задача не найдена.',
        'Range not found.' => 'Диапазон не найден.',
        'Failed to start task.' => 'Не удалось запустить задачу.',
        'Stop active tasks before deleting this range.' => 'Остановите выполняемые задачи перед удалением диапазона.',
        'Data directory is not writable.' => 'Нет прав записи в каталог данных.',
    ];

    public static function detectLanguage(): string {
        if (self::$language !== null) {
            return self::$language;
        }

        $language = $GLOBALS['USER_DETAILS']['lang']
            ?? $GLOBALS['ZBX_LOCALES']['selected']
            ?? $_SESSION['zbx_lang']
            ?? $_SESSION['lang']
            ?? 'en_US';

        try {
            if (class_exists('CWebUser') && method_exists('CWebUser', 'getLang')) {
                $language = \CWebUser::getLang() ?: $language;
            }
            elseif (class_exists('CWebUser') && method_exists('CWebUser', 'get')) {
                $language = \CWebUser::get('lang') ?: $language;
            }
            elseif (isset(\CWebUser::$data['lang'])) {
                $language = \CWebUser::$data['lang'] ?: $language;
            }
        }
        catch (\Throwable $exception) {
        }

        if ($language === 'default') {
            try {
                if (class_exists('CSettingsHelper')) {
                    $default = method_exists('CSettingsHelper', 'getPublic')
                        ? \CSettingsHelper::getPublic('default_lang')
                        : \CSettingsHelper::get('default_lang');
                    $language = $default ?: $language;
                }
            }
            catch (\Throwable $exception) {
            }
            if ($language === 'default') {
                $language = $GLOBALS['ZBX_LOCALES']['selected'] ?? $language;
            }
        }

        if (preg_match('/^ru(?:[-_]|$)/i', trim((string) $language))) {
            return self::$language = 'ru_RU';
        }

        return self::$language = strpos((string) $language, 'zh') === 0 ? 'zh_CN' : 'en_US';
    }

    public static function t(string $text): string {
        $language = self::detectLanguage();
        if ($language === 'ru_RU') {
            return self::RUSSIAN[$text] ?? $text;
        }

        return $language === 'zh_CN' ? (self::$zh[$text] ?? $text) : $text;
    }
}
