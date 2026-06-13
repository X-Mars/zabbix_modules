<?php

namespace Modules\ZabbixIm\Lib;

require_once __DIR__ . '/LanguageManager.php';

/**
 * 飞书通讯录 API（企业自建应用）
 * - 获取部门信息列表：https://open.feishu.cn/document/server-docs/contact-v3/department/children
 * - 获取单个部门信息：https://open.feishu.cn/document/server-docs/contact-v3/department/get
 * - 获取部门直属用户列表：https://open.feishu.cn/document/server-docs/contact-v3/user/find_by_department
 * - 获取 tenant_access_token：https://open.feishu.cn/document/server-docs/authentication-management/access-token/tenant_access_token_internal
 */
class FeishuClient implements ImProviderInterface {

    private const BASE = 'https://open.feishu.cn/open-apis';

    private $appId;
    private $appSecret;
    private $rootDepartmentId;
    private $http;
    private $tenantAccessToken = '';
    private $tokenExpiresAt = 0;

    /** @var array<int, array<string, mixed>> */
    private $apiDebugLog = [];

    public function __construct(array $config, bool $verifySsl = true) {
        $this->appId = trim((string) ($config['app_id'] ?? ''));
        $this->appSecret = trim((string) ($config['app_secret'] ?? ''));
        $this->rootDepartmentId = (string) ($config['root_department_id'] ?? '0');
        if ($this->rootDepartmentId === '') {
            $this->rootDepartmentId = '0';
        }
        $this->http = new HttpClient($verifySsl);
    }

    public function getApiDebugLog(): array {
        return $this->apiDebugLog;
    }

    public function getDepartments(): array {
        $this->ensureTenantAccessToken();

        $departments = [];
        $seen = [];

        // 飞书的子部门列表不含根部门本身，先把根部门加入（与企业微信保持一致）。
        $root = $this->fetchDepartmentDetail($this->rootDepartmentId);
        if ($root !== null) {
            $departments[] = $root;
            $seen[$root['id']] = true;
        }

        $this->collectDepartments($this->rootDepartmentId, $departments, $seen);

        return $departments;
    }

    public function getDepartmentUsers(string $departmentId): array {
        $this->ensureTenantAccessToken();

        $users = [];
        $pageToken = '';

        do {
            $query = http_build_query(array_filter([
                'department_id'      => $departmentId,
                'department_id_type' => 'open_department_id',
                'page_size'          => 50,
                'page_token'         => $pageToken !== '' ? $pageToken : null,
            ], static function ($value) {
                return $value !== null;
            }));

            $url = self::BASE . '/contact/v3/users/find_by_department?' . $query;
            $result = $this->http->get($url, $this->authHeaders());
            $this->recordApiResponse('user.list', $url, $result, [
                'department_id' => $departmentId,
            ]);
            $this->assertApiOk($result, 'Feishu user list failed');

            $data = $result['data']['data'] ?? [];
            foreach (($data['items'] ?? []) as $user) {
                if (!is_array($user)) {
                    continue;
                }

                $openId = (string) ($user['open_id'] ?? '');
                $userId = (string) ($user['user_id'] ?? '');
                $id = $openId !== '' ? $openId : $userId;
                if ($id === '') {
                    continue;
                }

                $email = (string) ($user['email'] ?? '');
                if ($email === '') {
                    $email = (string) ($user['enterprise_email'] ?? '');
                }

                $users[] = [
                    'id'       => $id,
                    'name'     => (string) ($user['name'] ?? ''),
                    'username' => $userId !== '' ? $userId : $id,
                    'email'    => $email,
                    'mobile'   => (string) ($user['mobile'] ?? ''),
                    'raw'      => $user,
                ];
            }

            $pageToken = (string) ($data['page_token'] ?? '');
            $hasMore = (bool) ($data['has_more'] ?? false);
        } while ($hasMore && $pageToken !== '');

        return $users;
    }

    /**
     * 获取单个部门信息（用于补齐根部门名称）。根部门 ID 为 0。
     */
    private function fetchDepartmentDetail(string $departmentId): ?array {
        $query = http_build_query([
            'department_id_type' => 'open_department_id',
        ]);
        $url = self::BASE . '/contact/v3/departments/' . rawurlencode($departmentId) . '?' . $query;
        $result = $this->http->get($url, $this->authHeaders());
        $this->recordApiResponse('department.get', $url, $result, [
            'department_id' => $departmentId,
        ]);

        // 根部门需要全员权限，失败时不抛异常，使用兜底名称。
        $dept = $result['data']['data']['department'] ?? null;
        $ok = $result['ok'] && (int) ($result['data']['code'] ?? -1) === 0 && is_array($dept);

        $name = $ok ? trim((string) ($dept['name'] ?? '')) : '';
        if ($name === '') {
            $name = $departmentId === '0'
                ? LanguageManager::t('Feishu root department')
                : LanguageManager::tf('Department %s', $departmentId);
        }

        return [
            // 强制使用配置的根部门 ID（如 0），确保子部门的 parent_id 能正确链接到根。
            'id'        => $departmentId,
            'name'      => $name,
            'parent_id' => '',
            'raw'       => is_array($dept) ? $dept : null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $departments
     * @param array<string, bool>              $seen
     */
    private function collectDepartments(string $parentId, array &$departments, array &$seen): void {
        $pageToken = '';

        do {
            $query = http_build_query(array_filter([
                'parent_department_id' => $parentId,
                'department_id_type'   => 'open_department_id',
                'fetch_child'          => 'false',
                'page_size'            => 50,
                'page_token'           => $pageToken !== '' ? $pageToken : null,
            ], static function ($value) {
                return $value !== null;
            }));

            $url = self::BASE . '/contact/v3/departments?' . $query;
            $result = $this->http->get($url, $this->authHeaders());
            $this->recordApiResponse('department.children', $url, $result, [
                'parent_department_id' => $parentId,
            ]);
            $this->assertApiOk($result, 'Feishu department list failed');

            $data = $result['data']['data'] ?? [];
            foreach (($data['items'] ?? []) as $dept) {
                if (!is_array($dept)) {
                    continue;
                }

                $id = (string) ($dept['open_department_id'] ?? ($dept['department_id'] ?? ''));
                $name = trim((string) ($dept['name'] ?? ''));
                if ($id === '' || $name === '' || isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;
                $departments[] = [
                    'id'        => $id,
                    'name'      => $name,
                    'parent_id' => (string) ($dept['parent_department_id'] ?? $parentId),
                    'raw'       => $dept,
                ];

                $this->collectDepartments($id, $departments, $seen);
            }

            $pageToken = (string) ($data['page_token'] ?? '');
            $hasMore = (bool) ($data['has_more'] ?? false);
        } while ($hasMore && $pageToken !== '');
    }

    private function ensureTenantAccessToken(): void {
        if ($this->tenantAccessToken !== '' && time() < $this->tokenExpiresAt - 60) {
            return;
        }

        if ($this->appId === '' || $this->appSecret === '') {
            throw new \RuntimeException(LanguageManager::t('Feishu app_id or app_secret is empty'));
        }

        $url = self::BASE . '/auth/v3/tenant_access_token/internal';
        $result = $this->http->postJson($url, [
            'app_id'     => $this->appId,
            'app_secret' => $this->appSecret,
        ]);
        $this->recordApiResponse('tenant_access_token', $url, $result, [
            'app_id' => $this->appId,
        ]);

        if (!$result['ok']) {
            throw new \RuntimeException(LanguageManager::t('Feishu tenant_access_token failed') . ': '
                . ($result['error'] ?: LanguageManager::t('HTTP error')));
        }

        $code = (int) ($result['data']['code'] ?? -1);
        if ($code !== 0) {
            throw new \RuntimeException(LanguageManager::t('Feishu tenant_access_token failed') . ': '
                . ($result['data']['msg'] ?? 'unknown error') . ' (' . $code . ')');
        }

        $this->tenantAccessToken = (string) ($result['data']['tenant_access_token'] ?? '');
        $expiresIn = (int) ($result['data']['expire'] ?? 7200);
        $this->tokenExpiresAt = time() + max(300, $expiresIn);

        if ($this->tenantAccessToken === '') {
            throw new \RuntimeException(LanguageManager::t('Feishu tenant_access_token is empty'));
        }
    }

    /**
     * @return array<int, string>
     */
    private function authHeaders(): array {
        return [
            'Authorization: Bearer ' . $this->tenantAccessToken,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function recordApiResponse(string $action, string $url, array $result, array $context = []): void {
        $parsed = $result['data'] ?? null;
        $raw = (string) ($result['raw'] ?? '');

        if ($raw === '' && is_array($parsed)) {
            $raw = json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $entry = array_merge($context, [
            'provider'    => 'feishu',
            'action'      => $action,
            'url'         => $url,
            'http_status' => (int) ($result['status'] ?? 0),
            'http_ok'     => !empty($result['ok']),
            'parsed'      => $this->sanitizeSensitiveApiData($action, $parsed),
            'raw'         => $this->sanitizeSensitiveRaw($action, $raw),
        ]);

        if ($action === 'user.list' && is_array($parsed)) {
            $items = $parsed['data']['items'] ?? [];
            $entry['userlist_count'] = is_array($items) ? count($items) : 0;
            $entry['userids'] = [];
            foreach ((is_array($items) ? $items : []) as $user) {
                if (!is_array($user)) {
                    continue;
                }
                $uid = (string) ($user['user_id'] ?? ($user['open_id'] ?? ''));
                if ($uid !== '') {
                    $entry['userids'][] = $uid;
                }
            }
        }

        $this->apiDebugLog[] = $entry;

        $logContext = $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        error_log('[Zabbix IM Feishu] ' . $action . $logContext . ' raw='
            . $this->sanitizeSensitiveRaw($action, $raw));
    }

    /**
     * @param mixed $data
     * @return mixed
     */
    private function sanitizeSensitiveApiData(string $action, $data) {
        if ($action !== 'tenant_access_token' || !is_array($data)) {
            return $data;
        }
        if (isset($data['tenant_access_token'])) {
            $data['tenant_access_token'] = '***';
        }
        return $data;
    }

    private function sanitizeSensitiveRaw(string $action, string $raw): string {
        if ($action !== 'tenant_access_token') {
            return $raw;
        }
        return preg_replace('/"tenant_access_token"\s*:\s*"[^"]+"/', '"tenant_access_token":"***"', $raw) ?? $raw;
    }

    private function assertApiOk(array $result, string $messageKey): void {
        $prefix = LanguageManager::t($messageKey);
        $httpError = LanguageManager::t('HTTP error');

        if (!$result['ok']) {
            throw new \RuntimeException($prefix . ': ' . ($result['error'] ?: $httpError));
        }

        $code = (int) ($result['data']['code'] ?? -1);
        if ($code !== 0) {
            $msg = (string) ($result['data']['msg'] ?? 'unknown error');
            throw new \RuntimeException($prefix . ': ' . $msg . ' (' . $code . ')');
        }
    }
}
