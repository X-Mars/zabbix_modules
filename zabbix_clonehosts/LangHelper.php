<?php declare(strict_types = 1);
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** Language helper for Zabbix Clonehosts module.
** Auto-detects Zabbix system language and provides Chinese, English and Russian translations.
** No gettext dependency — pure PHP array-based translation.
*/

namespace Modules\ZabbixClonehosts;

require_once __DIR__.'/lib/ModuleNotice.php';

class LangHelper {

	/** @var string|null Cached current language */
	private static $lang = null;

	/** @var array Translation table: key => [locale => translation] */
	private static $translations = [
		// ===== Menu =====
		'menu.clonehosts' => [
			'en_GB' => 'Host Batch Import',
			'ru_RU' => 'Массовый импорт узлов сети',
			'zh_CN' => '主机批量导入'
		],
		'page.clonehosts' => [
			'en_GB' => 'Clone hosts',
			'ru_RU' => 'Клонирование узлов сети',
			'zh_CN' => '克隆主机'
		],

		// ===== Step 1: Source Host Selection =====
		'step1.title' => [
			'en_GB' => 'Step 1: Select Source Host',
			'ru_RU' => 'Шаг 1. Выберите исходный узел сети',
			'zh_CN' => '第一步：选择克隆源主机'
		],
		'step1.description' => [
			'en_GB' => 'Select an existing monitored host. Its configuration (groups, templates, tags, macros, interfaces, TLS settings, etc.) will serve as the baseline template for all cloned hosts. Fields not specified in the import data will inherit values from this host.',
			'ru_RU' => 'Выберите существующий узел сети под мониторингом. Из него будут скопированы группы, шаблоны, теги, макросы, интерфейсы и настройки TLS для всех создаваемых узлов. Поля, которых нет в данных импорта, наследуются от исходного узла.',
			'zh_CN' => '选择一个已有的监控主机。其配置（群组、模板、标签、宏、接口、TLS设置等）将作为所有克隆主机的基准模板。导入数据中未指定的字段将继承该主机的配置值。'
		],
		'step1.select_placeholder' => [
			'en_GB' => '— Select Source Host —',
			'ru_RU' => '— Выберите исходный узел сети —',
			'zh_CN' => '— 选择源主机 —'
		],
		'step1.load_btn' => [
			'en_GB' => 'Load Source Host Info',
			'ru_RU' => 'Загрузить сведения об исходном узле',
			'zh_CN' => '加载源主机信息'
		],
		'step1.loading' => [
			'en_GB' => 'Loading...',
			'ru_RU' => 'Загрузка…',
			'zh_CN' => '加载中...'
		],
		'step1.load_failed' => [
			'en_GB' => 'Failed to load source host info',
			'ru_RU' => 'Не удалось загрузить сведения об исходном узле',
			'zh_CN' => '加载源主机信息失败'
		],
		'step1.load_failed_network' => [
			'en_GB' => 'Failed to load source host info, please check network connection.',
			'ru_RU' => 'Не удалось загрузить сведения об исходном узле. Проверьте подключение к сети.',
			'zh_CN' => '加载源主机信息失败，请检查网络连接。'
		],
		'step1.source_host_label' => [
			'en_GB' => 'Source Host: ',
			'ru_RU' => 'Исходный узел сети: ',
			'zh_CN' => '源主机：'
		],
		'step1.inherited_note' => [
			'en_GB' => 'Fields not specified in the import data will inherit the above configuration.',
			'ru_RU' => 'Поля, которых нет в данных импорта, наследуют настройки выше.',
			'zh_CN' => '导入数据中未指定的字段将继承以上配置。'
		],

		// ===== Host Filter =====
		'filter.host_group' => [
			'en_GB' => 'Host Group',
			'ru_RU' => 'Группа узлов сети',
			'zh_CN' => '主机群组'
		],
		'filter.all_groups' => [
			'en_GB' => '— All Groups —',
			'ru_RU' => '— Все группы —',
			'zh_CN' => '— 全部群组 —'
		],
		'filter.host_name' => [
			'en_GB' => 'Host Name',
			'ru_RU' => 'Имя узла сети',
			'zh_CN' => '主机名称'
		],
		'filter.name_placeholder' => [
			'en_GB' => 'Search by host name...',
			'ru_RU' => 'Поиск по имени узла сети…',
			'zh_CN' => '搜索主机名称...'
		],
		'filter.count' => [
			'en_GB' => 'Showing {total} of {all} hosts',
			'ru_RU' => 'Показано узлов сети: {total} из {all}',
			'zh_CN' => '显示 {total}/{all} 台主机'
		],
		'filter.no_match' => [
			'en_GB' => 'No matching hosts found',
			'ru_RU' => 'Подходящие узлы сети не найдены',
			'zh_CN' => '未找到匹配的主机'
		],

		// ===== Unified Search Box =====
		'search.placeholder' => [
			'en_GB' => 'Search hosts or host groups...',
			'ru_RU' => 'Поиск узлов сети или групп…',
			'zh_CN' => '搜索主机或主机群组...'
		],
		'search.hint' => [
			'en_GB' => 'Type to search. Click a host or host group in the dropdown to filter the list below.',
			'ru_RU' => 'Введите текст для поиска. Выберите узел сети или группу в списке, чтобы отфильтровать таблицу.',
			'zh_CN' => '输入关键字搜索。点击下拉框中的主机或主机群组，即可筛选下方列表。'
		],
		'search.category.host' => [
			'en_GB' => 'Hosts',
			'ru_RU' => 'Узлы сети',
			'zh_CN' => '主机'
		],
		'search.category.group' => [
			'en_GB' => 'Host Groups',
			'ru_RU' => 'Группы узлов сети',
			'zh_CN' => '主机群组'
		],
		'search.no_match' => [
			'en_GB' => 'No matching data',
			'ru_RU' => 'Подходящие данные не найдены',
			'zh_CN' => '无匹配数据'
		],
		'search.remove' => [
			'en_GB' => 'Remove',
			'ru_RU' => 'Убрать',
			'zh_CN' => '移除'
		],

		// ===== Multiselect Control =====
		'multiselect.search_placeholder' => [
			'en_GB' => 'Type to search...',
			'ru_RU' => 'Введите текст для поиска…',
			'zh_CN' => '输入关键字搜索...'
		],
		'multiselect.select_btn' => [
			'en_GB' => 'Select',
			'ru_RU' => 'Выбрать',
			'zh_CN' => '选择'
		],
		'multiselect.popup_title_group' => [
			'en_GB' => 'Select Host Groups',
			'ru_RU' => 'Выберите группы узлов сети',
			'zh_CN' => '选择主机群组'
		],
		'multiselect.popup_title_host' => [
			'en_GB' => 'Select Hosts',
			'ru_RU' => 'Выберите узлы сети',
			'zh_CN' => '选择主机'
		],
		'multiselect.popup_search_placeholder' => [
			'en_GB' => 'Search...',
			'ru_RU' => 'Поиск…',
			'zh_CN' => '搜索...'
		],
		'multiselect.popup_name_header' => [
			'en_GB' => 'Name',
			'ru_RU' => 'Имя',
			'zh_CN' => '名称'
		],
		'multiselect.popup_count' => [
			'en_GB' => '{count} selected',
			'ru_RU' => 'Выбрано: {count}',
			'zh_CN' => '已选 {count} 项'
		],
		'multiselect.popup_cancel' => [
			'en_GB' => 'Cancel',
			'ru_RU' => 'Отмена',
			'zh_CN' => '取消'
		],
		'multiselect.popup_apply' => [
			'en_GB' => 'Apply',
			'ru_RU' => 'Применить',
			'zh_CN' => '应用'
		],

		// ===== Step 2: Data Input =====
		'step2.title' => [
			'en_GB' => 'Step 2: Enter Host Data',
			'ru_RU' => 'Шаг 2. Введите данные узлов сети',
			'zh_CN' => '第二步：录入主机数据'
		],
		'step2.description' => [
			'en_GB' => 'Upload a CSV file or enter host data directly in the table below. Host name and interface IP are required; other fields are optional and will inherit from the source host when left blank.',
			'ru_RU' => 'Загрузите CSV или введите данные в таблицу ниже. Имя узла и IP-адрес интерфейса обязательны. Остальные поля можно оставить пустыми: они наследуются от исходного узла.',
			'zh_CN' => '上传CSV文件或在下方表格中直接录入主机数据。主机名称和接口IP为必填项，其他字段为选填，留空时将继承源主机的配置。'
		],
		'step2.tab_file' => [
			'en_GB' => 'File Upload (CSV)',
			'ru_RU' => 'Загрузка файла CSV',
			'zh_CN' => '文件上传 (CSV)'
		],
		'step2.tab_table' => [
			'en_GB' => 'Online Table Entry',
			'ru_RU' => 'Ввод в таблицу',
			'zh_CN' => '在线表格录入'
		],

		// ===== CSV Upload =====
		'csv.parse_btn' => [
			'en_GB' => 'Parse CSV File',
			'ru_RU' => 'Разобрать CSV',
			'zh_CN' => '解析CSV文件'
		],
		'csv.download_template' => [
			'en_GB' => 'Download CSV Template',
			'ru_RU' => 'Скачать шаблон CSV',
			'zh_CN' => '下载CSV模板'
		],
		'csv.encoding_hint' => [
			'en_GB' => 'Tip: If Chinese characters in the CSV appear garbled, try switching encoding to GBK and re-parse. The template file uses UTF-8 encoding and can be opened directly in Excel.',
			'ru_RU' => 'Если символы в CSV искажены, выберите кодировку исходного файла и повторите разбор. Шаблон использует UTF-8 и открывается в Excel.',
			'zh_CN' => '提示：若CSV文件中文显示乱码，请尝试切换编码为 GBK 后重新解析。模板文件使用 UTF-8 编码，可直接在 Excel 中打开编辑。'
		],
		'csv.no_data' => [
			'en_GB' => 'No valid data rows found in CSV file.',
			'ru_RU' => 'В файле CSV нет допустимых строк.',
			'zh_CN' => 'CSV文件中未找到有效数据行。'
		],
		'csv.parsed_rows' => [
			'en_GB' => 'Parsed: ',
			'ru_RU' => 'Разобрано: ',
			'zh_CN' => '解析：'
		],
		'csv.valid_rows' => [
			'en_GB' => 'Valid: ',
			'ru_RU' => 'Допустимых: ',
			'zh_CN' => '有效：'
		],
		'csv.invalid_rows' => [
			'en_GB' => 'Invalid (missing required fields): ',
			'ru_RU' => 'Недопустимых (пропущены обязательные поля): ',
			'zh_CN' => '无效（缺少必填字段）：'
		],
		'csv.more_rows' => [
			'en_GB' => '... ',
			'ru_RU' => '… ',
			'zh_CN' => '... 还有 '
		],
		'csv.more_rows_suffix' => [
			'en_GB' => ' more rows',
			'ru_RU' => ' дополнительных строк',
			'zh_CN' => ' 行'
		],

		// ===== CSV Headers =====
		'csv.header.host' => [
			'en_GB' => 'Host Name(*)',
			'ru_RU' => 'Имя узла сети (*)',
			'zh_CN' => '主机名称(*)'
		],
		'csv.header.visible_name' => [
			'en_GB' => 'Visible Name',
			'ru_RU' => 'Видимое имя',
			'zh_CN' => '可见的名称'
		],
		'csv.header.ip' => [
			'en_GB' => 'Interface IP(*)',
			'ru_RU' => 'IP-адрес интерфейса (*)',
			'zh_CN' => '接口IP(*)'
		],
		'csv.header.port' => [
			'en_GB' => 'Port',
			'ru_RU' => 'Порт',
			'zh_CN' => '端口'
		],
		'csv.header.groups' => [
			'en_GB' => 'Host Groups',
			'ru_RU' => 'Группы узлов сети',
			'zh_CN' => '主机群组'
		],
		'csv.header.templates' => [
			'en_GB' => 'Templates',
			'ru_RU' => 'Шаблоны',
			'zh_CN' => '模板'
		],
		'csv.header.tags' => [
			'en_GB' => 'Tags',
			'ru_RU' => 'Теги',
			'zh_CN' => '标签'
		],
		'csv.header.macros' => [
			'en_GB' => 'Macros',
			'ru_RU' => 'Макросы',
			'zh_CN' => '宏'
		],
		'csv.header.description' => [
			'en_GB' => 'Description',
			'ru_RU' => 'Описание',
			'zh_CN' => '描述'
		],

		// ===== Table Toolbar =====
		'table.add_row' => [
			'en_GB' => 'Add Row',
			'ru_RU' => 'Добавить строку',
			'zh_CN' => '添加行'
		],
		'table.remove_row' => [
			'en_GB' => 'Remove Selected Rows',
			'ru_RU' => 'Удалить выбранные строки',
			'zh_CN' => '删除选中行'
		],
		'table.clear_all' => [
			'en_GB' => 'Clear All',
			'ru_RU' => 'Очистить всё',
			'zh_CN' => '清空全部'
		],
		'table.row_count' => [
			'en_GB' => 'Total rows: ',
			'ru_RU' => 'Всего строк: ',
			'zh_CN' => '总行数：'
		],
		'table.clear_confirm' => [
			'en_GB' => 'Are you sure you want to clear all rows?',
			'ru_RU' => 'Очистить все строки?',
			'zh_CN' => '确定要清空所有行吗？'
		],
		'table.placeholder.required' => [
			'en_GB' => 'Required',
			'ru_RU' => 'Обязательное поле',
			'zh_CN' => '必填'
		],
		'table.placeholder.inherited' => [
			'en_GB' => '(inherited)',
			'ru_RU' => '(унаследовано)',
			'zh_CN' => '(继承)'
		],

		// ===== Column Headers =====
		'col.host_name' => [
			'en_GB' => 'Host Name',
			'ru_RU' => 'Имя узла сети',
			'zh_CN' => '主机名称'
		],
		'col.visible_name' => [
			'en_GB' => 'Visible Name',
			'ru_RU' => 'Видимое имя',
			'zh_CN' => '可见的名称'
		],
		'col.interface_ip' => [
			'en_GB' => 'Interface IP',
			'ru_RU' => 'IP-адрес интерфейса',
			'zh_CN' => '接口IP'
		],
		'col.port' => [
			'en_GB' => 'Port',
			'ru_RU' => 'Порт',
			'zh_CN' => '端口'
		],
		'col.host_groups' => [
			'en_GB' => 'Host Groups',
			'ru_RU' => 'Группы узлов сети',
			'zh_CN' => '主机群组'
		],
		'col.templates' => [
			'en_GB' => 'Templates',
			'ru_RU' => 'Шаблоны',
			'zh_CN' => '模板'
		],
		'col.tags' => [
			'en_GB' => 'Tags',
			'ru_RU' => 'Теги',
			'zh_CN' => '标签'
		],
		'col.macros' => [
			'en_GB' => 'Macros',
			'ru_RU' => 'Макросы',
			'zh_CN' => '宏'
		],
		'col.description' => [
			'en_GB' => 'Description',
			'ru_RU' => 'Описание',
			'zh_CN' => '描述'
		],
		'col.status' => [
			'en_GB' => 'Status',
			'ru_RU' => 'Состояние',
			'zh_CN' => '状态'
		],
		'col.result' => [
			'en_GB' => 'Result',
			'ru_RU' => 'Результат',
			'zh_CN' => '结果'
		],
		'col.host_id' => [
			'en_GB' => 'Host ID',
			'ru_RU' => 'ID узла сети',
			'zh_CN' => '主机ID'
		],
		'col.error' => [
			'en_GB' => 'Error',
			'ru_RU' => 'Ошибка',
			'zh_CN' => '错误'
		],

		// ===== Preview =====
		'preview.btn' => [
			'en_GB' => 'Preview Import',
			'ru_RU' => 'Предпросмотр импорта',
			'zh_CN' => '预览导入'
		],
		'preview.hint_no_data' => [
			'en_GB' => 'Please fill in at least one valid row of host data to continue.',
			'ru_RU' => 'Для продолжения заполните хотя бы одну допустимую строку с данными узла.',
			'zh_CN' => '请至少填写一行有效的主机数据以继续。'
		],
		'preview.hint_no_source' => [
			'en_GB' => 'Please select a source host first.',
			'ru_RU' => 'Сначала выберите исходный узел сети.',
			'zh_CN' => '请先选择源主机。'
		],
		'preview.hint_ready' => [
			'en_GB' => 'Ready to preview import.',
			'ru_RU' => 'Можно открыть предпросмотр импорта.',
			'zh_CN' => '可以预览导入。'
		],
		'preview.alert_no_data' => [
			'en_GB' => 'Please enter at least one valid host (host name and IP are required).',
			'ru_RU' => 'Введите хотя бы один допустимый узел сети. Требуются имя и IP-адрес.',
			'zh_CN' => '请至少输入一台有效的主机（主机名和IP为必填项）。'
		],

		// ===== Preview Page =====
		'preview.source_config' => [
			'en_GB' => 'Source Host Configuration (will be inherited)',
			'ru_RU' => 'Настройки исходного узла сети (будут унаследованы)',
			'zh_CN' => '源主机配置（将被继承）'
		],
		'preview.interfaces' => [
			'en_GB' => 'Interfaces',
			'ru_RU' => 'Интерфейсы',
			'zh_CN' => '接口'
		],
		'preview.none' => [
			'en_GB' => 'None',
			'ru_RU' => 'Нет',
			'zh_CN' => '无'
		],
		'preview.total' => [
			'en_GB' => 'Total: ',
			'ru_RU' => 'Всего: ',
			'zh_CN' => '总计：'
		],
		'preview.conflicts' => [
			'en_GB' => 'Conflicts: ',
			'ru_RU' => 'Конфликтов: ',
			'zh_CN' => '冲突：'
		],
		'preview.importable' => [
			'en_GB' => 'Importable: ',
			'ru_RU' => 'Доступно для импорта: ',
			'zh_CN' => '可导入：'
		],
		'preview.pending_count' => [
			'en_GB' => 'Hosts to import: ',
			'ru_RU' => 'Узлов к импорту: ',
			'zh_CN' => '待导入主机数：'
		],
		'preview.no_conflicts' => [
			'en_GB' => 'No conflicts detected',
			'ru_RU' => 'Конфликты не обнаружены',
			'zh_CN' => '未检测到冲突'
		],
		'preview.title' => [
			'en_GB' => 'Host Import Preview',
			'ru_RU' => 'Предпросмотр импорта узлов сети',
			'zh_CN' => '主机导入预览'
		],
		'preview.inherited' => [
			'en_GB' => '(inherited)',
			'ru_RU' => '(унаследовано)',
			'zh_CN' => '(继承)'
		],
		'preview.ready' => [
			'en_GB' => 'Ready',
			'ru_RU' => 'Готово',
			'zh_CN' => '就绪'
		],
		'preview.back' => [
			'en_GB' => 'Back',
			'ru_RU' => 'Назад',
			'zh_CN' => '返回'
		],
		'preview.start_import' => [
			'en_GB' => 'Start Import',
			'ru_RU' => 'Начать импорт',
			'zh_CN' => '开始导入'
		],
		'preview.resolve_conflicts' => [
			'en_GB' => 'Conflicting hosts will be skipped. You can uncheck ready hosts to exclude them too.',
			'ru_RU' => 'Узлы с конфликтами будут пропущены. Чтобы исключить из импорта готовые узлы, снимите их выделение.',
			'zh_CN' => '冲突主机将被跳过，您也可以取消勾选就绪主机以排除。'
		],
		'preview.back_edit' => [
			'en_GB' => 'Back to Edit',
			'ru_RU' => 'Вернуться к редактированию',
			'zh_CN' => '返回编辑'
		],
		'preview.import_selected' => [
			'en_GB' => 'Import Selected ({count})',
			'ru_RU' => 'Импортировать выбранные ({count})',
			'zh_CN' => '导入所选 ({count})'
		],
		'preview.no_selected' => [
			'en_GB' => 'Please select at least one ready host to import.',
			'ru_RU' => 'Выберите хотя бы один готовый к импорту узел сети.',
			'zh_CN' => '请至少勾选一台就绪主机进行导入。'
		],
		'preview.skipped' => [
			'en_GB' => 'Skipped',
			'ru_RU' => 'Пропущено',
			'zh_CN' => '已跳过'
		],
		'preview.col_select' => [
			'en_GB' => 'Import',
			'ru_RU' => 'Импортировать',
			'zh_CN' => '导入'
		],

		// ===== Field Status Legend =====
		'legend.exists' => [
			'en_GB' => 'Exists → Link directly',
			'ru_RU' => 'Уже существует → связать напрямую',
			'zh_CN' => '已存在 → 直接关联'
		],
		'legend.new' => [
			'en_GB' => 'Will be created',
			'ru_RU' => 'Будет создано',
			'zh_CN' => '将新建'
		],
		'legend.not_found' => [
			'en_GB' => 'Not found',
			'ru_RU' => 'Не найдено',
			'zh_CN' => '未找到'
		],
		'legend.inherited' => [
			'en_GB' => 'Inherited from source host',
			'ru_RU' => 'Унаследовано от исходного узла',
			'zh_CN' => '继承自源主机'
		],
		'legend.exists_tooltip' => [
			'en_GB' => 'Already exists, will be linked directly',
			'ru_RU' => 'Уже существует, будет связано напрямую',
			'zh_CN' => '已存在，将直接关联'
		],
		'legend.new_tooltip_group' => [
			'en_GB' => 'This host group will be created',
			'ru_RU' => 'Эта группа узлов сети будет создана',
			'zh_CN' => '将新建此主机群组'
		],
		'legend.not_found_tooltip_template' => [
			'en_GB' => 'Template not found, please verify the template name',
			'ru_RU' => 'Шаблон не найден. Проверьте его имя',
			'zh_CN' => '模板未找到，请确认模板名称是否正确'
		],

		// ===== Import Progress =====
		'import.progress_title' => [
			'en_GB' => 'Import Progress',
			'ru_RU' => 'Ход импорта',
			'zh_CN' => '导入进度'
		],
		'import.status_success' => [
			'en_GB' => 'Success:',
			'ru_RU' => 'Успешно:',
			'zh_CN' => '成功：'
		],
		'import.status_failed' => [
			'en_GB' => 'Failed:',
			'ru_RU' => 'С ошибкой:',
			'zh_CN' => '失败：'
		],
		'import.status_pending' => [
			'en_GB' => 'Pending:',
			'ru_RU' => 'Ожидает:',
			'zh_CN' => '待处理：'
		],
		'import.importing' => [
			'en_GB' => 'Importing...',
			'ru_RU' => 'Импорт…',
			'zh_CN' => '导入中...'
		],
		'import.complete' => [
			'en_GB' => 'Import Complete',
			'ru_RU' => 'Импорт завершён',
			'zh_CN' => '导入完成'
		],
		'import.results_title' => [
			'en_GB' => 'Import Results',
			'ru_RU' => 'Результаты импорта',
			'zh_CN' => '导入结果'
		],
		'import.success' => [
			'en_GB' => 'Success: ',
			'ru_RU' => 'Успешно: ',
			'zh_CN' => '成功：'
		],
		'import.failed' => [
			'en_GB' => 'Failed: ',
			'ru_RU' => 'С ошибкой: ',
			'zh_CN' => '失败：'
		],
		'import.download_report' => [
			'en_GB' => 'Download Report',
			'ru_RU' => 'Скачать отчёт',
			'zh_CN' => '下载报告'
		],
		'import.result_success' => [
			'en_GB' => 'Success',
			'ru_RU' => 'Успешно',
			'zh_CN' => '成功'
		],
		'import.result_failed' => [
			'en_GB' => 'Failed',
			'ru_RU' => 'Ошибка',
			'zh_CN' => '失败'
		],
		'import.ajax_error' => [
			'en_GB' => 'AJAX error: ',
			'ru_RU' => 'Ошибка AJAX: ',
			'zh_CN' => 'AJAX错误：'
		],
		'import.unknown_error' => [
			'en_GB' => 'Unknown',
			'ru_RU' => 'Неизвестно',
			'zh_CN' => '未知'
		],

		// ===== Report CSV Headers =====
		'report.col_row' => [
			'en_GB' => '#',
			'ru_RU' => '№',
			'zh_CN' => '#'
		],
		'report.col_result' => [
			'en_GB' => 'Result',
			'ru_RU' => 'Результат',
			'zh_CN' => '结果'
		],
		'report.col_host' => [
			'en_GB' => 'Host Name',
			'ru_RU' => 'Имя узла сети',
			'zh_CN' => '主机名称'
		],
		'report.col_ip' => [
			'en_GB' => 'Interface IP',
			'ru_RU' => 'IP-адрес интерфейса',
			'zh_CN' => '接口IP'
		],
		'report.col_hostid' => [
			'en_GB' => 'Host ID',
			'ru_RU' => 'ID узла сети',
			'zh_CN' => '主机ID'
		],
		'report.col_error' => [
			'en_GB' => 'Error',
			'ru_RU' => 'Ошибка',
			'zh_CN' => '错误'
		],

		// ===== Error Messages (from controllers) =====
		'err.host_required' => [
			'en_GB' => 'Host name is required',
			'ru_RU' => 'Укажите имя узла сети',
			'zh_CN' => '主机名称为必填项'
		],
		'err.ip_required' => [
			'en_GB' => 'Interface IP is required',
			'ru_RU' => 'Укажите IP-адрес интерфейса',
			'zh_CN' => '接口IP为必填项'
		],
		'err.host_exists' => [
			'en_GB' => 'This host already exists',
			'ru_RU' => 'Этот узел сети уже существует',
			'zh_CN' => '该主机已存在'
		],
		'err.template_exists' => [
			'en_GB' => 'A template with the same name already exists',
			'ru_RU' => 'Шаблон с таким именем уже существует',
			'zh_CN' => '同名模板已存在'
		],
		'err.source_not_found' => [
			'en_GB' => 'Source host not found',
			'ru_RU' => 'Исходный узел сети не найден',
			'zh_CN' => '源主机未找到'
		],
		'err.no_groups' => [
			'en_GB' => 'No host groups available (not specified and cannot inherit)',
			'ru_RU' => 'Нет доступных групп узлов сети. Группа не задана, а унаследовать её невозможно',
			'zh_CN' => '没有可用的主机群组（未指定且无法继承）'
		],
		'err.unknown_api_response' => [
			'en_GB' => 'Unknown API response',
			'ru_RU' => 'Неизвестный ответ API',
			'zh_CN' => '未知的API响应'
		],
		'err.batch_duplicate' => [
			'en_GB' => 'Duplicate host name within batch',
			'ru_RU' => 'Имя узла сети повторяется в пакете',
			'zh_CN' => '批次内主机名称重复'
		],
		'err.host_exists_in_zabbix' => [
			'en_GB' => 'This host name already exists in Zabbix',
			'ru_RU' => 'Узел сети с таким именем уже существует в Zabbix',
			'zh_CN' => '该主机名称在Zabbix中已存在'
		],

		// ===== Interface Type Names =====
		'iface.Agent' => [
			'en_GB' => 'Agent',
			'ru_RU' => 'Агент',
			'zh_CN' => 'Agent'
		],
		'iface.SNMP' => [
			'en_GB' => 'SNMP',
			'ru_RU' => 'SNMP',
			'zh_CN' => 'SNMP'
		],
		'iface.IPMI' => [
			'en_GB' => 'IPMI',
			'ru_RU' => 'IPMI',
			'zh_CN' => 'IPMI'
		],
		'iface.JMX' => [
			'en_GB' => 'JMX',
			'ru_RU' => 'JMX',
			'zh_CN' => 'JMX'
		],
		'iface.Type' => [
			'en_GB' => 'Type ',
			'ru_RU' => 'Тип ',
			'zh_CN' => 'Type '
		],
	];

	/**
	 * Detect the current user's language from Zabbix.
	 * Priority: CWebUser::$data['lang'] → API user.get → system default
	 *
	 * @return string Language code (e.g., 'zh_CN', 'en_GB')
	 */
	public static function getLang(): string {
		if (self::$lang !== null) {
			return self::$lang;
		}

		// Method 1: Try CWebUser (available in Zabbix frontend context)
		if (class_exists('CWebUser', false) && isset(\CWebUser::$data['lang'])) {
			self::$lang = self::normalizeLanguage(\CWebUser::$data['lang']);
			return self::$lang;
		}

		// Method 2: Try to get from the session/API
		try {
			$user = \API::User()->get([
				'output' => ['lang'],
				'userids' => [\CWebUser::$data['userid'] ?? 0]
			]);
			if (!empty($user) && !empty($user[0]['lang'])) {
				self::$lang = self::normalizeLanguage($user[0]['lang']);
				return self::$lang;
			}
		} catch (\Throwable $e) {
			// Fall through to default
		}

		self::$lang = self::normalizeLanguage('default');
		return self::$lang;
	}

	private static function normalizeLanguage(string $locale): string {
		if (trim($locale) === '' || strcasecmp(trim($locale), 'default') === 0) {
			return \ZabbixModuleNotice::language();
		}

		return preg_match('/^ru(?:[-_]|$)/i', trim($locale)) ? 'ru_RU' : $locale;
	}

	/**
	 * Check if the current language is Chinese.
	 *
	 * @return bool
	 */
	public static function isChinese(): bool {
		return self::getLang() === 'zh_CN' || strpos(self::getLang(), 'zh') === 0;
	}

	public static function getCsvHostHeaders(): array {
		return array_values(self::$translations['csv.header.host']);
	}

	/**
	 * Translate a key to the current language, with optional parameter substitution.
	 * Supports {placeholder} style parameters in the translation string.
	 *
	 * @param string $key Translation key
	 * @param array $params Optional ['{placeholder}' => 'value'] pairs for substitution
	 * @return string Translated string (falls back to English, then to the key itself)
	 */
	public static function t(string $key, array $params = []): string {
		$lang = self::getLang();

		if (isset(self::$translations[$key][$lang])) {
			$str = self::$translations[$key][$lang];
		} elseif (isset(self::$translations[$key]['en_GB'])) {
			$str = self::$translations[$key]['en_GB'];
		} else {
			$str = $key;
		}

		if (!empty($params)) {
			$str = strtr($str, $params);
		}

		return $str;
	}

	/**
	 * Get all translations for the current language as a JSON-serializable array.
	 * Used to pass translations to JavaScript.
	 *
	 * @return array Key => translated string
	 */
	public static function getAllForJs(): array {
		$lang = self::getLang();
		$result = [];

		foreach (self::$translations as $key => $langs) {
			if (isset($langs[$lang])) {
				$result[$key] = $langs[$lang];
			} elseif (isset($langs['en_GB'])) {
				$result[$key] = $langs['en_GB'];
			}
		}

		return $result;
	}
}
