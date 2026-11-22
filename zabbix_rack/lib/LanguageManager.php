<?php

namespace Modules\ZabbixRack\Lib;

class LanguageManager {
    /**
     * 与 Zabbix 前端保持一致的标识与默认值
     */
    private const LANG_DEFAULT = 'default';
    private const ZBX_DEFAULT_LANG = 'en_US';
    
    private static $currentLanguage = null;
    private static $translations = [
        'zh_CN' => [
            // 模块和菜单（用于 Module.php）
            'Rack Module' => '机柜管理',
            'Rack View' => '机柜视图',
            'Rack Config' => '机柜配置',
            
            // 页面标题（用于视图文件，下划线命名）
            'rack_view' => '机柜视图',
            'rack_manage' => '机柜配置',
            'rack_management' => '机柜管理',
            'room_management' => '机房管理',
            
            // 通用
            'search' => '搜索',
            'search_placeholder' => '搜索机柜或主机...',
            'all_rooms' => '所有机房',
            'all_racks' => '所有机柜',
            'room' => '机房',
            'rack' => '机柜',
            'racks' => '机柜',
            'rooms' => '机房',
            'name' => '名称',
            'location' => '位置',
            'height' => '高度',
            'units' => '单位',
            'actions' => '操作',
            'add' => '添加',
            'edit' => '编辑',
            'delete' => '删除',
            'save' => '保存',
            'cancel' => '取消',
            'close' => '关闭',
            'confirm' => '确认',
            'yes' => '是',
            'no' => '否',
            'empty' => '空闲',
            'occupied' => '已占用',
            'filter' => '筛选',
            'remove' => '移除',
            'description' => '描述',
            'created_at' => '创建时间',
            
            // 机房相关
            'add_room' => '添加机房',
            'edit_room' => '编辑机房',
            'room_name' => '机房名称',
            'enter_room_name' => '请输入机房名称',
            'room_description' => '机房描述',
            'enter_room_description' => '请输入机房描述',
            'delete_room' => '删除机房',
            'confirm_delete_room' => '确定要删除此机房吗？该机房下的所有机柜也将被删除。',
            'no_rooms' => '暂无机房',
            'rack_count' => '机柜数量',
            
            // 机柜相关
            'add_rack' => '添加机柜',
            'edit_rack' => '编辑机柜',
            'rack_name' => '机柜名称',
            'enter_rack_name' => '请输入机柜名称',
            'rack_height' => '机柜高度 (U)',
            'select_room' => '选择机房',
            'delete_rack' => '删除机柜',
            'confirm_delete_rack' => '确定要删除此机柜吗？所有主机分配将被移除。',
            'no_racks_found' => '未找到机柜',
            'no_rooms_configured' => '暂无机房配置，请先添加机房。',
            'no_racks' => '暂无机柜',
            'no_rack_selected' => '请选择机柜',
            
            // 主机分配
            'assign_host' => '分配主机',
            'assigned_host' => '分配主机',
            'custom_host' => '自定义主机',
            'custom_host_name' => '主机名',
            'custom_host_ip' => 'IP 地址',
            'custom_host_name_placeholder' => '请输入自定义主机名',
            'custom_host_ip_placeholder' => '例如：192.168.1.10',
            'remove_host' => '移除主机',
            'select_host' => '选择主机',
            'host_group' => '主机分组',
            'all_groups' => '所有分组',
            'position' => '位置',
            'u_start' => '起始U位',
            'u_end' => '结束U位',
            'host' => '主机',
            'ip_address' => 'IP地址',
            'status' => '状态',
            'enabled' => '启用',
            'disabled' => '禁用',
            'search_host' => '搜索主机',
            'search_host_placeholder' => '输入主机名搜索...',
            'hosts_in_rack' => '机柜内主机',
            'search_results' => '搜索结果',
            
            // 操作结果
            'save_success' => '保存成功',
            'save_failed' => '保存失败',
            'delete_success' => '删除成功',
            'delete_failed' => '删除失败',
            'assign_success' => '分配成功',
            'assign_failed' => '分配失败',
            'remove_success' => '移除成功',
            'remove_failed' => '移除失败',
            'operation_failed' => '操作失败',
            'save_permission_hint' => "保存失败，请检查 {path} 的目录和文件写权限，以及 SELinux 标签（httpd_sys_rw_content_t）。仅修改归属或 chmod 无法解除 SELinux 拒绝；详见模块 README 与 PHP/Web 错误日志。",
            
            // 错误消息
            'room_not_found' => '机房不存在',
            'rack_not_found' => '机柜不存在',
            'invalid_position' => 'U位范围无效',
            'position_occupied' => '该U位已被占用',
            'custom_host_required' => '请输入自定义主机名和 IP 地址',
            'invalid_custom_ip' => '请输入有效的 IP 地址',
            'room_name_required' => '请输入机房名称',
            'rack_name_required' => '请输入机柜名称',
            'room_selection_required' => '请选择机房',
            
            // 提示
            'click_to_assign_host' => '点击分配主机',
            'drag_to_reposition' => '拖拽调整位置',
            'double_click_to_edit' => '双击编辑',
            'loading' => '加载中...',
            'no_data' => '暂无数据',
            
            // 统计
            'total_rooms' => '机房总数',
            'total_racks' => '机柜总数',
            'total_hosts' => '主机总数',
            'total_problems' => '告警总数',
            'utilization' => '使用率',
            'space_usage' => '空间使用',
            'usage_rate' => '使用率',
            'total_space' => '总空间',
            'front_space' => '正面空间',
            'back_space' => '背面空间',
            'total_usage_rate' => '总使用率',
            'front_usage_rate' => '正面使用率',
            'back_usage_rate' => '背面使用率',
            'select_rack' => '选择机柜',
            'total_capacity' => '总容量',
            'used_capacity' => '已使用',
            'used' => '已使用',
            'enter_rack_description' => '请输入机柜描述',
            'hosts' => '主机',
            'usage' => '使用',
            'view_details' => '查看详情',
            
            // 告警相关
            'problems' => '告警',
            'no_problems' => '无告警',
            'host_problems' => '主机告警',
            'problem_count' => '告警数',
            'no_active_problems' => '✅ 当前无活跃告警',
            'fetch_problems_failed' => '获取告警失败',
            
            // 严重程度名称
            'severity_not_classified' => '未分类',
            'severity_information' => '信息',
            'severity_warning' => '警告',
            'severity_average' => '一般严重',
            'severity_high' => '严重',
            'severity_disaster' => '灾难',
            'severity_unknown' => '未知',
            
            // 机柜视图 JS 国际化
            'host_name_label' => '主机名:',
            'ip_label' => 'IP:',
            'host_group_label' => '主机组:',
            'position_label' => '位置:',
            'status_label' => '状态:',
            'unnamed_host' => '未命名主机',
            'edit_host_position' => '编辑主机位置',
            'no_matching_hosts' => '无匹配主机',
            'select_a_host' => '请选择一个主机',
            'invalid_u_range' => '请输入有效的U位范围',
            'confirm_remove_host' => '确定要从机柜中移除此主机吗？',
            
            // 机柜管理 JS 国际化
            'delete_room_title' => '删除机房',
            'delete_rack_title' => '删除机柜',
            'room_has_racks_warning' => '该机房下还有 {count} 个机柜，将一并删除！',
            'rack_height_hint' => '有效范围：1-60U，默认42U',
            
            // 后端验证
            'invalid_input' => '输入参数无效',
            'permission_denied' => '无权访问该机房',
            'room_permissions' => '访问权限',
            'room_user_groups' => 'Zabbix 用户组',
            'room_users' => 'Zabbix 用户',
            'permission_public' => '全员可见',
            'permission_restricted' => '受限',
            'permission_user_groups_count' => '%d 个用户组',
            'permission_users_count' => '%d 个用户',
            'permission_hint' => '默认选中全部用户组和用户表示全员可见；取消部分选项可限制访问范围。',
            'select_user_groups' => '按住 Ctrl/Shift 多选用户组',
            'select_users' => '按住 Ctrl/Shift 多选用户',
            'no_accessible_rooms' => '当前账号没有可访问的机房',
            'manage_super_admin_only' => '仅超级管理员可访问机柜配置',
            'rack_side' => '机柜面',
            'rack_side_front' => '正面',
            'rack_side_back' => '背面',
            'rack_side_front_view' => '机柜正面',
            'rack_side_back_view' => '机柜背面',
            'switch_to_front' => '切换到正面',
            'switch_to_back' => '切换到背面',
        ],
        'en_US' => [
            // 模块和菜单（用于 Module.php）
            'Rack Module' => 'Rack Management',
            'Rack View' => 'Rack View',
            'Rack Config' => 'Rack Config',
            
            // 页面标题（用于视图文件，下划线命名）
            'rack_view' => 'Rack View',
            'rack_manage' => 'Rack Config',
            'rack_management' => 'Rack Management',
            'room_management' => 'Room Management',
            
            // 通用
            'search' => 'Search',
            'search_placeholder' => 'Search rack or host...',
            'all_rooms' => 'All Rooms',
            'all_racks' => 'All Racks',
            'room' => 'Room',
            'rack' => 'Rack',
            'racks' => 'Racks',
            'rooms' => 'Rooms',
            'name' => 'Name',
            'location' => 'Location',
            'height' => 'Height',
            'units' => 'Units',
            'actions' => 'Actions',
            'add' => 'Add',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'save' => 'Save',
            'cancel' => 'Cancel',
            'close' => 'Close',
            'confirm' => 'Confirm',
            'yes' => 'Yes',
            'no' => 'No',
            'empty' => 'Empty',
            'occupied' => 'Occupied',
            'filter' => 'Filter',
            'remove' => 'Remove',
            'description' => 'Description',
            'created_at' => 'Created At',
            
            // 机房相关
            'add_room' => 'Add Room',
            'edit_room' => 'Edit Room',
            'room_name' => 'Room Name',
            'enter_room_name' => 'Enter room name',
            'room_description' => 'Room Description',
            'enter_room_description' => 'Enter room description',
            'delete_room' => 'Delete Room',
            'confirm_delete_room' => 'Are you sure to delete this room? All racks in this room will also be deleted.',
            'no_rooms' => 'No rooms',
            'rack_count' => 'Rack Count',
            
            // 机柜相关
            'add_rack' => 'Add Rack',
            'edit_rack' => 'Edit Rack',
            'rack_name' => 'Rack Name',
            'enter_rack_name' => 'Enter rack name',
            'rack_height' => 'Rack Height (U)',
            'select_room' => 'Select Room',
            'delete_rack' => 'Delete Rack',
            'confirm_delete_rack' => 'Are you sure to delete this rack? All host assignments will be removed.',
            'no_racks_found' => 'No racks found',
            'no_rooms_configured' => 'No rooms configured. Please add a room first.',
            'no_racks' => 'No racks',
            'no_rack_selected' => 'Please select a rack',
            
            // 主机分配
            'assign_host' => 'Assign Host',
            'assigned_host' => 'Assigned Host',
            'custom_host' => 'Custom Host',
            'custom_host_name' => 'Host Name',
            'custom_host_ip' => 'IP Address',
            'custom_host_name_placeholder' => 'Enter a custom host name',
            'custom_host_ip_placeholder' => 'For example: 192.168.1.10',
            'remove_host' => 'Remove Host',
            'select_host' => 'Select Host',
            'host_group' => 'Host Group',
            'all_groups' => 'All Groups',
            'position' => 'Position',
            'u_start' => 'Start U',
            'u_end' => 'End U',
            'host' => 'Host',
            'ip_address' => 'IP Address',
            'status' => 'Status',
            'enabled' => 'Enabled',
            'disabled' => 'Disabled',
            'search_host' => 'Search Host',
            'search_host_placeholder' => 'Search by host name...',
            'hosts_in_rack' => 'Hosts in Rack',
            'search_results' => 'Search Results',
            
            // 操作结果
            'save_success' => 'Save successful',
            'save_failed' => 'Save failed',
            'delete_success' => 'Delete successful',
            'delete_failed' => 'Delete failed',
            'assign_success' => 'Assign successful',
            'assign_failed' => 'Assign failed',
            'remove_success' => 'Remove successful',
            'remove_failed' => 'Remove failed',
            'operation_failed' => 'Operation failed',
            'save_permission_hint' => "Save failed. Check directory/file permissions on {path} and the SELinux httpd_sys_rw_content_t label. Ownership or chmod alone cannot resolve SELinux denials. See the module README and PHP/Web logs.",
            
            // 错误消息
            'room_not_found' => 'Room not found',
            'rack_not_found' => 'Rack not found',
            'invalid_position' => 'Invalid U position range',
            'position_occupied' => 'Position is occupied',
            'custom_host_required' => 'Please enter a custom host name and IP address',
            'invalid_custom_ip' => 'Please enter a valid IP address',
            'room_name_required' => 'Room name is required',
            'rack_name_required' => 'Rack name is required',
            'room_selection_required' => 'Please select a room',
            
            // 提示
            'click_to_assign_host' => 'Click to assign host',
            'drag_to_reposition' => 'Drag to reposition',
            'double_click_to_edit' => 'Double click to edit',
            'loading' => 'Loading...',
            'no_data' => 'No data',
            
            // 统计
            'total_rooms' => 'Total Rooms',
            'total_racks' => 'Total Racks',
            'total_hosts' => 'Total Hosts',
            'total_problems' => 'Total Problems',
            'utilization' => 'Utilization',
            'space_usage' => 'Space Usage',
            'usage_rate' => 'Usage Rate',
            'total_space' => 'Total Space',
            'front_space' => 'Front Space',
            'back_space' => 'Rear Space',
            'total_usage_rate' => 'Total Usage',
            'front_usage_rate' => 'Front Usage',
            'back_usage_rate' => 'Rear Usage',
            'select_rack' => 'Select Rack',
            'total_capacity' => 'Total Capacity',
            'used_capacity' => 'Used',
            'used' => 'Used',
            'enter_rack_description' => 'Enter rack description',
            'hosts' => 'Hosts',
            'usage' => 'Usage',
            'view_details' => 'View Details',
            
            // 告警相关
            'problems' => 'Problems',
            'no_problems' => 'No Problems',
            'host_problems' => 'Host Problems',
            'problem_count' => 'Problem Count',
            'no_active_problems' => '✅ No active problems',
            'fetch_problems_failed' => 'Failed to fetch problems',
            
            // 严重程度名称
            'severity_not_classified' => 'Not classified',
            'severity_information' => 'Information',
            'severity_warning' => 'Warning',
            'severity_average' => 'Average',
            'severity_high' => 'High',
            'severity_disaster' => 'Disaster',
            'severity_unknown' => 'Unknown',
            
            // 机柜视图 JS 国际化
            'host_name_label' => 'Host:',
            'ip_label' => 'IP:',
            'host_group_label' => 'Group:',
            'position_label' => 'Position:',
            'status_label' => 'Status:',
            'unnamed_host' => 'Unnamed Host',
            'edit_host_position' => 'Edit Host Position',
            'no_matching_hosts' => 'No matching hosts',
            'select_a_host' => 'Please select a host',
            'invalid_u_range' => 'Please enter a valid U range',
            'confirm_remove_host' => 'Are you sure to remove this host from the rack?',
            
            // 机柜管理 JS 国际化
            'delete_room_title' => 'Delete Room',
            'delete_rack_title' => 'Delete Rack',
            'room_has_racks_warning' => 'This room has {count} rack(s), which will also be deleted!',
            'rack_height_hint' => 'Valid range: 1-60U, default 42U',
            
            // 后端验证
            'invalid_input' => 'Invalid input',
            'permission_denied' => 'You do not have access to this room',
            'room_permissions' => 'Access control',
            'room_user_groups' => 'Zabbix user groups',
            'room_users' => 'Zabbix users',
            'permission_public' => 'Visible to all users',
            'permission_restricted' => 'Restricted',
            'permission_user_groups_count' => '%d user group(s)',
            'permission_users_count' => '%d user(s)',
            'permission_hint' => 'All user groups and users selected by default means visible to everyone; deselect entries to restrict access.',
            'select_user_groups' => 'Hold Ctrl/Shift to select multiple user groups',
            'select_users' => 'Hold Ctrl/Shift to select multiple users',
            'no_accessible_rooms' => 'No accessible rooms for your account',
            'manage_super_admin_only' => 'Only Super Admin can access Rack Config',
            'rack_side' => 'Rack side',
            'rack_side_front' => 'Front',
            'rack_side_back' => 'Rear',
            'rack_side_front_view' => 'Rack front',
            'rack_side_back_view' => 'Rack rear',
            'switch_to_front' => 'Switch to front',
            'switch_to_back' => 'Switch to rear',
        ],
        'ru_RU' => [
            'Rack Module' => 'Управление стойками',
            'Rack View' => 'Просмотр стоек',
            'Rack Config' => 'Настройка стоек',
            'rack_view' => 'Просмотр стоек',
            'rack_manage' => 'Настройка стоек',
            'rack_management' => 'Управление стойками',
            'room_management' => 'Управление помещениями',
            'search' => 'Поиск',
            'search_placeholder' => 'Поиск стойки или узла сети…',
            'all_rooms' => 'Все помещения',
            'all_racks' => 'Все стойки',
            'room' => 'Помещение',
            'rack' => 'Стойка',
            'racks' => 'Стойки',
            'rooms' => 'Помещения',
            'name' => 'Имя',
            'location' => 'Расположение',
            'height' => 'Высота',
            'units' => 'Юниты',
            'actions' => 'Действия',
            'add' => 'Добавить',
            'edit' => 'Изменить',
            'delete' => 'Удалить',
            'save' => 'Сохранить',
            'cancel' => 'Отмена',
            'close' => 'Закрыть',
            'confirm' => 'Подтвердить',
            'yes' => 'Да',
            'no' => 'Нет',
            'empty' => 'Пусто',
            'occupied' => 'Занято',
            'filter' => 'Фильтр',
            'remove' => 'Убрать',
            'description' => 'Описание',
            'created_at' => 'Дата создания',
            'add_room' => 'Добавить помещение',
            'edit_room' => 'Изменить помещение',
            'room_name' => 'Название помещения',
            'enter_room_name' => 'Введите название помещения',
            'room_description' => 'Описание помещения',
            'enter_room_description' => 'Введите описание помещения',
            'delete_room' => 'Удалить помещение',
            'confirm_delete_room' => 'Удалить это помещение и все его стойки?',
            'no_rooms' => 'Нет помещений',
            'rack_count' => 'Количество стоек',
            'add_rack' => 'Добавить стойку',
            'edit_rack' => 'Изменить стойку',
            'rack_name' => 'Название стойки',
            'enter_rack_name' => 'Введите название стойки',
            'rack_height' => 'Высота стойки (U)',
            'select_room' => 'Выберите помещение',
            'delete_rack' => 'Удалить стойку',
            'confirm_delete_rack' => 'Удалить стойку? Все привязки узлов будут сняты.',
            'no_racks_found' => 'Стойки не найдены',
            'no_rooms_configured' => 'Помещения ещё не настроены. Сначала добавьте помещение.',
            'no_racks' => 'Нет стоек',
            'no_rack_selected' => 'Выберите стойку',
            'assign_host' => 'Разместить узел сети',
            'assigned_host' => 'Размещённый узел сети',
            'custom_host' => 'Произвольный узел сети',
            'custom_host_name' => 'Имя узла сети',
            'custom_host_ip' => 'IP-адрес',
            'custom_host_name_placeholder' => 'Введите имя произвольного узла сети',
            'custom_host_ip_placeholder' => 'Например, 192.168.1.10',
            'remove_host' => 'Убрать узел сети',
            'select_host' => 'Выберите узел сети',
            'host_group' => 'Группа узлов сети',
            'all_groups' => 'Все группы',
            'position' => 'Позиция',
            'u_start' => 'Начальный юнит',
            'u_end' => 'Конечный юнит',
            'host' => 'Узел сети',
            'ip_address' => 'IP-адрес',
            'status' => 'Состояние',
            'enabled' => 'Включено',
            'disabled' => 'Отключено',
            'search_host' => 'Найти узел сети',
            'search_host_placeholder' => 'Поиск по имени узла сети…',
            'hosts_in_rack' => 'Узлы сети в стойке',
            'search_results' => 'Результаты поиска',
            'save_success' => 'Сохранено',
            'save_failed' => 'Не удалось сохранить',
            'delete_success' => 'Удалено',
            'delete_failed' => 'Не удалось удалить',
            'assign_success' => 'Узел размещён',
            'assign_failed' => 'Не удалось разместить узел',
            'remove_success' => 'Узел убран',
            'remove_failed' => 'Не удалось убрать узел',
            'operation_failed' => 'Не удалось выполнить операцию',
            'save_permission_hint' => 'Не удалось сохранить. Проверьте права на файлы и каталог {path}, а также наличие метки SELinux httpd_sys_rw_content_t. Ошибки доступа SELinux требуют настройки контекста. См. README модуля и журналы PHP и веб-сервера.',
            'room_not_found' => 'Помещение не найдено',
            'rack_not_found' => 'Стойка не найдена',
            'invalid_position' => 'Недопустимый диапазон юнитов',
            'position_occupied' => 'Позиция занята',
            'custom_host_required' => 'Введите имя и IP-адрес произвольного узла сети',
            'invalid_custom_ip' => 'Введите допустимый IP-адрес',
            'room_name_required' => 'Укажите название помещения',
            'rack_name_required' => 'Укажите название стойки',
            'room_selection_required' => 'Выберите помещение',
            'click_to_assign_host' => 'Нажмите, чтобы разместить узел сети',
            'drag_to_reposition' => 'Перетащите, чтобы переместить',
            'double_click_to_edit' => 'Дважды нажмите, чтобы изменить',
            'loading' => 'Загрузка…',
            'no_data' => 'Нет данных',
            'total_rooms' => 'Всего помещений',
            'total_racks' => 'Всего стоек',
            'total_hosts' => 'Всего узлов сети',
            'total_problems' => 'Всего проблем',
            'utilization' => 'Заполненность',
            'space_usage' => 'Использование места',
            'usage_rate' => 'Доля занятого места',
            'total_space' => 'Всего места',
            'front_space' => 'Место спереди',
            'back_space' => 'Место сзади',
            'total_usage_rate' => 'Всего занято',
            'front_usage_rate' => 'Занято спереди',
            'back_usage_rate' => 'Занято сзади',
            'select_rack' => 'Выберите стойку',
            'total_capacity' => 'Общая вместимость',
            'used_capacity' => 'Использовано',
            'used' => 'Использовано',
            'enter_rack_description' => 'Введите описание стойки',
            'hosts' => 'Узлы сети',
            'usage' => 'Использование',
            'view_details' => 'Подробнее',
            'problems' => 'Проблемы',
            'no_problems' => 'Проблем нет',
            'host_problems' => 'Проблемы узла сети',
            'problem_count' => 'Количество проблем',
            'no_active_problems' => '✅ Нет активных проблем',
            'fetch_problems_failed' => 'Не удалось получить проблемы',
            'severity_not_classified' => 'Не классифицировано',
            'severity_information' => 'Информация',
            'severity_warning' => 'Предупреждение',
            'severity_average' => 'Средняя',
            'severity_high' => 'Высокая',
            'severity_disaster' => 'Чрезвычайная',
            'severity_unknown' => 'Неизвестно',
            'host_name_label' => 'Узел сети:',
            'ip_label' => 'IP:',
            'host_group_label' => 'Группа:',
            'position_label' => 'Позиция:',
            'status_label' => 'Состояние:',
            'unnamed_host' => 'Узел сети без имени',
            'edit_host_position' => 'Изменить позицию узла сети',
            'no_matching_hosts' => 'Подходящие узлы сети не найдены',
            'select_a_host' => 'Выберите узел сети',
            'invalid_u_range' => 'Введите допустимый диапазон юнитов',
            'confirm_remove_host' => 'Убрать этот узел сети из стойки?',
            'delete_room_title' => 'Удалить помещение',
            'delete_rack_title' => 'Удалить стойку',
            'room_has_racks_warning' => 'Вместе с помещением будут удалены и его стойки ({count})!',
            'rack_height_hint' => 'Допустимо от 1 до 60 U, по умолчанию 42 U',
            'invalid_input' => 'Недопустимые данные',
            'permission_denied' => 'У вас нет доступа к этому помещению',
            'room_permissions' => 'Управление доступом',
            'room_user_groups' => 'Группы пользователей Zabbix',
            'room_users' => 'Пользователи Zabbix',
            'permission_public' => 'Видно всем пользователям',
            'permission_restricted' => 'Доступ ограничен',
            'permission_user_groups_count' => 'Групп пользователей: %d',
            'permission_users_count' => 'Пользователей: %d',
            'permission_hint' => 'По умолчанию выбраны все группы и пользователи. Чтобы ограничить доступ, снимите выделение с нужных записей.',
            'select_user_groups' => 'Удерживайте Ctrl или Shift, чтобы выбрать несколько групп пользователей',
            'select_users' => 'Удерживайте Ctrl или Shift, чтобы выбрать несколько пользователей',
            'no_accessible_rooms' => 'Вашей учётной записи недоступны помещения',
            'manage_super_admin_only' => 'Настройка стоек доступна только суперадминистратору',
            'rack_side' => 'Сторона стойки',
            'rack_side_front' => 'Спереди',
            'rack_side_back' => 'Сзади',
            'rack_side_front_view' => 'Передняя сторона стойки',
            'rack_side_back_view' => 'Задняя сторона стойки',
            'switch_to_front' => 'Показать переднюю сторону',
            'switch_to_back' => 'Показать заднюю сторону',
        ],
    ];

    /**
     * 检测当前语言（对齐 Zabbix 源码逻辑）
     * 优先级：
     * 1) 用户语言（users.lang），如果为 'default' 则继承系统默认
     * 2) 系统默认语言（settings.default_lang），读取失败则回退到 ZBX 默认
     * 3) Zabbix 默认语言（en_US）
     */
    public static function detectLanguage() {
        if (self::$currentLanguage !== null) {
            return self::$currentLanguage;
        }
        
        // 1) 用户语言
        $userLang = self::getUserLanguageFromZabbix();
        if (!empty($userLang)) {
            $mapped = self::mapZabbixLangToOurs($userLang);
            // 'default' 表示继承系统默认
            if ($mapped === self::LANG_DEFAULT) {
                $sys = self::getSystemDefaultLanguage();
                self::$currentLanguage = self::ensureSupportedOrFallback($sys);
                return self::$currentLanguage;
            }
            self::$currentLanguage = self::ensureSupportedOrFallback($mapped);
            return self::$currentLanguage;
        }

        // 2) 系统默认语言
        $sys = self::getSystemDefaultLanguage();
        if (!empty($sys)) {
            self::$currentLanguage = self::ensureSupportedOrFallback($sys);
            return self::$currentLanguage;
        }

        // 3) Zabbix 默认语言
        self::$currentLanguage = self::ensureSupportedOrFallback(self::ZBX_DEFAULT_LANG);
        return self::$currentLanguage;
    }

    /**
     * 尝试从Zabbix系统中获取当前用户的语言设置
     */
    private static function getUserLanguageFromZabbix() {
        // 方法0: 优先使用 Zabbix 官方封装 CWebUser
        try {
            if (class_exists('CWebUser') || class_exists('\\CWebUser')) {
                // 静态 get 方法（较新版本）
                if (method_exists('CWebUser', 'get')) {
                    $lang = \CWebUser::get('lang');
                    if (!empty($lang)) {
                        return $lang;
                    }
                }
                // 旧版本静态数据容器
                if (isset(\CWebUser::$data) && is_array(\CWebUser::$data) && isset(\CWebUser::$data['lang']) && !empty(\CWebUser::$data['lang'])) {
                    return \CWebUser::$data['lang'];
                }
            }
        } catch (\Throwable $e) {
            // 忽略并继续其他方式
        }

        // 方法1: 尝试通过全局变量获取CWebUser信息
        try {
            if (isset($GLOBALS['USER_DETAILS']) && isset($GLOBALS['USER_DETAILS']['lang'])) {
                return $GLOBALS['USER_DETAILS']['lang'];
            }
        } catch (\Throwable $e) {
            // 继续其他方法
        }
        
        // 方法2: 尝试从全局变量中获取
        try {
            if (isset($GLOBALS['ZBX_LOCALES']) && isset($GLOBALS['ZBX_LOCALES']['selected'])) {
                return $GLOBALS['ZBX_LOCALES']['selected'];
            }
        } catch (\Throwable $e) {
            // 继续其他方法
        }
        
        // 方法3: 从Session中获取
        if (isset($_SESSION['zbx_lang']) && !empty($_SESSION['zbx_lang'])) {
            return $_SESSION['zbx_lang'];
        }
        if (isset($_SESSION['lang']) && !empty($_SESSION['lang'])) {
            return $_SESSION['lang'];
        }

        // 方法4: 尝试直接访问数据库获取用户语言设置
        return self::getUserLanguageFromDatabase();
    }

    /**
     * 尝试直接从数据库获取当前用户的语言设置
     */
    private static function getUserLanguageFromDatabase() {
        try {
            // 获取当前用户ID
            $userid = null;

            if (isset($_SESSION['userid'])) {
                $userid = $_SESSION['userid'];
            } elseif (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['userid'])) {
                $userid = $_SESSION['user']['userid'];
            }

            if (!$userid) {
                return null;
            }

            // 尝试连接数据库
            if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
                $pdo = new \PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                    DB_USER,
                    defined('DB_PASSWORD') ? DB_PASSWORD : ''
                );

                $stmt = $pdo->prepare('SELECT lang FROM users WHERE userid = ? LIMIT 1');
                $stmt->execute([$userid]);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($result && isset($result['lang'])) {
                    return $result['lang'];
                }
            }
        } catch (\Throwable $e) {
            // 数据库连接失败或其他错误
        }

        return null;
    }

    /**
     * 读取系统默认语言
     */
    private static function getSystemDefaultLanguage() {
        try {
            // 优先使用 Zabbix 官方封装 CSettingsHelper
            if (class_exists('CSettingsHelper') || class_exists('\\CSettingsHelper')) {
                if (method_exists('CSettingsHelper', 'get')) {
                    $val = \CSettingsHelper::get('default_lang');
                    if (!empty($val)) {
                        return $val;
                    }
                }
            }

            if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER')) {
                $pdo = new \PDO(
                    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
                    DB_USER,
                    defined('DB_PASSWORD') ? DB_PASSWORD : ''
                );

                // 先查 settings 表
                $stmt = $pdo->prepare("SELECT value_str FROM settings WHERE name='default_lang' LIMIT 1");
                if ($stmt->execute()) {
                    $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                    if ($row && !empty($row['value_str'])) {
                        return $row['value_str'];
                    }
                }

                // 兼容旧版本：config 表
                $stmt2 = $pdo->query("SHOW TABLES LIKE 'config'");
                $hasConfig = $stmt2 && $stmt2->fetch();
                if ($hasConfig) {
                    $stmt3 = $pdo->query("SELECT default_lang FROM config LIMIT 1");
                    $row2 = $stmt3 ? $stmt3->fetch(\PDO::FETCH_ASSOC) : false;
                    if ($row2 && !empty($row2['default_lang'])) {
                        return $row2['default_lang'];
                    }
                }
            }
        } catch (\Throwable $e) {
            // 忽略并回退
        }

        return self::ZBX_DEFAULT_LANG;
    }

    /**
     * 将Zabbix语言代码映射到我们的语言代码
     */
    private static function mapZabbixLangToOurs($zabbixLang) {
        $lowerLang = strtolower(trim($zabbixLang));

        if (preg_match('/^ru(?:[-_]|$)/i', trim($lowerLang))) {
            return 'ru_RU';
        }

        $langMap = [
            // 中文的各种变体
            'zh_cn' => 'zh_CN',
            'zh-cn' => 'zh_CN',
            'zh_tw' => 'zh_CN',
            'zh-tw' => 'zh_CN',
            'zh' => 'zh_CN',
            'chinese' => 'zh_CN',
            'china' => 'zh_CN',
            'cn' => 'zh_CN',

            // 英文的各种变体
            'en_us' => 'en_US',
            'en-us' => 'en_US',
            'en_gb' => 'en_US',
            'en-gb' => 'en_US',
            'en' => 'en_US',
            'english' => 'en_US',
            'us' => 'en_US',
            'gb' => 'en_US',

            // 默认
            'default' => self::LANG_DEFAULT
        ];

        if (isset($langMap[$lowerLang])) {
            return $langMap[$lowerLang];
        }

        // 部分匹配
        if (strpos($lowerLang, 'zh') === 0 || strpos($lowerLang, 'cn') !== false || strpos($lowerLang, 'chinese') !== false) {
            return 'zh_CN';
        }

        if (strpos($lowerLang, 'en') === 0 || strpos($lowerLang, 'english') !== false) {
            return 'en_US';
        }

        return self::ZBX_DEFAULT_LANG;
    }

    /**
     * 检查并回退到受支持语言
     */
    private static function ensureSupportedOrFallback($lang) {
        $mapped = self::mapZabbixLangToOurs($lang);
        if (self::isSupportedLocale($mapped)) {
            return $mapped;
        }
        return self::ZBX_DEFAULT_LANG;
    }

    /**
     * 仅将我们已提供翻译的语言视为可用
     */
    private static function isSupportedLocale($lang) {
        return in_array($lang, array_keys(self::$translations), true);
    }

    /**
     * 获取翻译文本（静态方法，也可通过实例调用）
     * 兼容 LanguageManager::t('key') 和 $lm->t('key') 两种调用方式
     */
    public static function t($key) {
        $lang = self::detectLanguage();

        if (isset(self::$translations[$lang][$key])) {
            return self::$translations[$lang][$key];
        }

        // 如果当前语言没有翻译，尝试使用英语
        if ($lang !== 'en_US' && isset(self::$translations['en_US'][$key])) {
            return self::$translations['en_US'][$key];
        }

        // 如果都没有，返回原键值
        return $key;
    }

    public static function tf($key, ...$args) {
        return sprintf(self::t($key), ...$args);
    }

    /**
     * 静态获取翻译文本（translate 方法的别名）
     */
    public static function translate($key) {
        return self::t($key);
    }

    /**
     * 获取当前语言
     */
    public static function getCurrentLanguage() {
        return self::detectLanguage();
    }

    /**
     * 检查当前是否为中文语言
     */
    public static function isChinese() {
        return self::detectLanguage() === 'zh_CN';
    }
}
