<?php
/**
 * 机柜配置管理器
 * 负责机房、机柜数据的存储和读取
 */

namespace Modules\ZabbixRack\Lib;

require_once __DIR__ . '/RackPermission.php';

class RackConfig {
    
    private static $configFile = null;
    private static $config = null;
    
    /**
     * 获取配置文件路径
     */
    private static function getConfigFile(): string {
        if (self::$configFile === null) {
            self::$configFile = dirname(__DIR__) . '/data/config.json';
        }
        return self::$configFile;
    }
    
    /**
     * 确保数据目录存在
     */
    private static function ensureDataDir(): void {
        $dataDir = dirname(self::getConfigFile());
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
    }
    
    /**
     * 加载配置
     */
    public static function load(): array {
        if (self::$config !== null) {
            return self::$config;
        }
        
        $configFile = self::getConfigFile();
        
        if (file_exists($configFile)) {
            $content = file_get_contents($configFile);
            self::$config = json_decode($content, true);
            if (!is_array(self::$config)) {
                self::$config = self::getDefaultConfig();
            }
        } else {
            self::$config = self::getDefaultConfig();
        }
        
        return self::$config;
    }
    
    /**
     * 保存配置
     */
    public static function save(array $config): bool {
        self::ensureDataDir();
        self::$config = $config;
        
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return file_put_contents(self::getConfigFile(), $json) !== false;
    }
    
    /**
     * 检查数据目录是否可写
     */
    public static function isDataDirWritable(): bool {
        $dataDir = dirname(self::getConfigFile());
        return is_dir($dataDir) && is_writable($dataDir);
    }
    
    /**
     * 获取数据目录路径
     */
    public static function getDataDir(): string {
        return dirname(self::getConfigFile());
    }
    
    /**
     * 获取默认配置
     */
    private static function getDefaultConfig(): array {
        return [
            'rooms' => [],
            'racks' => [],
            'custom_hosts' => []
        ];
    }

    public static function getCustomHosts(?string $roomId = null, ?string $rackId = null): array {
        $hosts = self::load()['custom_hosts'] ?? [];

        return array_values(array_filter($hosts, static function (array $host) use ($roomId, $rackId): bool {
            return ($roomId === null || (string) ($host['room_id'] ?? '') === $roomId)
                && ($rackId === null || (string) ($host['rack_id'] ?? '') === $rackId);
        }));
    }

    public static function getCustomHost(string $id): ?array {
        foreach (self::getCustomHosts() as $host) {
            if ((string) ($host['id'] ?? '') === $id) {
                return $host;
            }
        }
        return null;
    }

    public static function saveCustomHost(array $host): bool {
        $config = self::load();
        $config['custom_hosts'] = $config['custom_hosts'] ?? [];

        if (empty($host['id'])) {
            $host['id'] = self::generateId();
            $host['created_at'] = date('Y-m-d H:i:s');
            $config['custom_hosts'][] = $host;
        } else {
            $found = false;
            foreach ($config['custom_hosts'] as &$existingHost) {
                if ((string) ($existingHost['id'] ?? '') === (string) $host['id']) {
                    $host['updated_at'] = date('Y-m-d H:i:s');
                    $existingHost = array_merge($existingHost, $host);
                    $found = true;
                    break;
                }
            }
            unset($existingHost);
            if (!$found) {
                $host['created_at'] = date('Y-m-d H:i:s');
                $config['custom_hosts'][] = $host;
            }
        }

        return self::save($config);
    }

    public static function deleteCustomHost(string $id): bool {
        $config = self::load();
        $config['custom_hosts'] = array_values(array_filter(
            $config['custom_hosts'] ?? [],
            static function (array $host) use ($id): bool {
                return (string) ($host['id'] ?? '') !== $id;
            }
        ));
        return self::save($config);
    }
    
    /**
     * 获取所有机房
     */
    public static function getRooms(): array {
        $config = self::load();
        return $config['rooms'] ?? [];
    }
    
    /**
     * 获取机房
     */
    public static function getRoom(string $roomId): ?array {
        $rooms = self::getRooms();
        foreach ($rooms as $room) {
            if ($room['id'] === $roomId) {
                return $room;
            }
        }
        return null;
    }
    
    /**
     * 添加/更新机房
     */
    public static function saveRoom(array $room): bool {
        $config = self::load();
        $room = RackPermission::applyRoomPermissions($room);
        if (empty($room['id'])) {
            // 新建机房
            $room['id'] = self::generateId();
            $room['created_at'] = date('Y-m-d H:i:s');
            $config['rooms'][] = $room;
        } else {
            // 更新机房
            $found = false;
            foreach ($config['rooms'] as &$existingRoom) {
                if ($existingRoom['id'] === $room['id']) {
                    $room['updated_at'] = date('Y-m-d H:i:s');
                    $existingRoom = array_merge($existingRoom, $room);
                    if (!isset($room['user_groups'])) {
                        unset($existingRoom['user_groups']);
                    }
                    if (!isset($room['users'])) {
                        unset($existingRoom['users']);
                    }
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $room['created_at'] = date('Y-m-d H:i:s');
                $config['rooms'][] = $room;
            }
        }
        
        return self::save($config);
    }
    
    /**
     * 删除机房
     */
    public static function deleteRoom(string $roomId): bool {
        $config = self::load();
        
        // 删除机房下的所有机柜
        $config['racks'] = array_filter($config['racks'], function($rack) use ($roomId) {
            return $rack['room_id'] !== $roomId;
        });
        $config['racks'] = array_values($config['racks']);
        
        // 删除机房
        $config['rooms'] = array_filter($config['rooms'], function($room) use ($roomId) {
            return $room['id'] !== $roomId;
        });
        $config['rooms'] = array_values($config['rooms']);
        $config['custom_hosts'] = array_values(array_filter(
            $config['custom_hosts'] ?? [],
            static function (array $host) use ($roomId): bool {
                return (string) ($host['room_id'] ?? '') !== $roomId;
            }
        ));
        
        return self::save($config);
    }
    
    /**
     * 获取所有机柜
     */
    public static function getRacks(?string $roomId = null): array {
        $config = self::load();
        $racks = $config['racks'] ?? [];
        
        if ($roomId !== null) {
            $racks = array_filter($racks, function($rack) use ($roomId) {
                return $rack['room_id'] === $roomId;
            });
            $racks = array_values($racks);
        }
        
        return $racks;
    }
    
    /**
     * 获取机柜
     */
    public static function getRack(string $rackId): ?array {
        $racks = self::getRacks();
        foreach ($racks as $rack) {
            if ($rack['id'] === $rackId) {
                return $rack;
            }
        }
        return null;
    }
    
    /**
     * 添加/更新机柜
     */
    public static function saveRack(array $rack): bool {
        $config = self::load();
        
        if (empty($rack['id'])) {
            // 新建机柜
            $rack['id'] = self::generateId();
            $rack['created_at'] = date('Y-m-d H:i:s');
            if (!isset($rack['height'])) {
                $rack['height'] = 42; // 默认42U
            }
            $config['racks'][] = $rack;
        } else {
            // 更新机柜
            $found = false;
            foreach ($config['racks'] as &$existingRack) {
                if ($existingRack['id'] === $rack['id']) {
                    $rack['updated_at'] = date('Y-m-d H:i:s');
                    $existingRack = array_merge($existingRack, $rack);
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $rack['created_at'] = date('Y-m-d H:i:s');
                $config['racks'][] = $rack;
            }
        }
        
        return self::save($config);
    }
    
    /**
     * 删除机柜
     */
    public static function deleteRack(string $rackId): bool {
        $config = self::load();
        
        $config['racks'] = array_filter($config['racks'], function($rack) use ($rackId) {
            return $rack['id'] !== $rackId;
        });
        $config['racks'] = array_values($config['racks']);
        $config['custom_hosts'] = array_values(array_filter(
            $config['custom_hosts'] ?? [],
            static function (array $host) use ($rackId): bool {
                return (string) ($host['rack_id'] ?? '') !== $rackId;
            }
        ));
        
        return self::save($config);
    }
    
    /**
     * 生成唯一ID
     */
    private static function generateId(): string {
        return uniqid('', true);
    }
    
    /**
     * 搜索机柜
     */
    public static function searchRacks(string $keyword): array {
        $racks = self::getRacks();
        $rooms = self::getRooms();
        
        // 创建机房名称映射
        $roomNames = [];
        foreach ($rooms as $room) {
            $roomNames[$room['id']] = $room['name'];
        }
        
        $keyword = strtolower($keyword);
        $results = [];
        
        foreach ($racks as $rack) {
            $rackName = strtolower($rack['name']);
            $roomName = strtolower($roomNames[$rack['room_id']] ?? '');
            
            if (strpos($rackName, $keyword) !== false || strpos($roomName, $keyword) !== false) {
                $rack['room_name'] = $roomNames[$rack['room_id']] ?? '';
                $results[] = $rack;
            }
        }
        
        return $results;
    }
}
