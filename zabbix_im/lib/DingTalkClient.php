<?php

namespace Modules\ZabbixIm\Lib;

require_once __DIR__ . '/LanguageManager.php';

/**
 * 钉钉通讯录 API（企业内部应用）
 * - 获取部门详情：https://open.dingtalk.com/document/orgapp/query-department-details0-v2
 * - 获取子部门列表：https://open.dingtalk.com/document/orgapp/obtain-the-department-list
 * - 获取部门用户详情：https://open.dingtalk.com/document/orgapp/obtain-department-members-details
 * - 获取凭证：https://open.dingtalk.com/document/orgapp/obtain-orgapp-token
 */
class DingTalkClient implements ImProviderInterface {

    /** 家校通讯录的特殊部门 ID，需剔除（不属于内部通讯录范畴） */
    private const SCHOOL_CONTACT_DEPT_ID = '-7';

    private $appKey;
    private $appSecret;
    private $rootDepartmentId;
    private $http;
    private $accessToken = '';
    private $tokenExpiresAt = 0;

    /** @var array<int, array<string, mixed>> */
    private $apiDebugLog = [];

    public function __construct(array $config, bool $verifySsl = true) {
        $this->appKey = trim((string) ($config['app_key'] ?? ''));
        $this->appSecret = trim((string) ($config['app_secret'] ?? ''));
        $this->rootDepartmentId = (int) ($config['root_department_id'] ?? 1);
        $this->http = new HttpClient($verifySsl);
    }

    public function getApiDebugLog(): array {
        return $this->apiDebugLog;
    }

    public function getDepartments(): array {
        $this->ensureAccessToken();

        $departments = [];
        $seen = [];

        // 钉钉的 listsub 仅返回下一级子部门，不含根部门本身。
        // 为与企业微信保持一致（根部门也作为一个用户组），先把根部门加入列表。
        $root = $this->fetchDepartmentDetail($this->rootDepartmentId);
        if ($root !== null) {
            $departments[] = $root;
            $seen[$root['id']] = true;
        }

        $this->collectDepartments($this->rootDepartmentId, (string) $this->rootDepartmentId, $departments, $seen);

        return $departments;
    }

    public function getDepartmentUsers(string $departmentId): array {
        $this->ensureAccessToken();

        $users = [];
        $skipped = [];
        $cursor = 0;

        do {
            $url = 'https://oapi.dingtalk.com/topapi/v2/user/list?access_token=' . urlencode($this->accessToken);
            $body = [
                'dept_id'  => (int) $departmentId,
                'cursor'   => $cursor,
                'size'     => 100,
                'language' => 'zh_CN',
            ];
            $result = $this->http->postJson($url, $body);
            $this->recordApiResponse('user.list', $url, $result, [
                'department_id' => $departmentId,
                'cursor'        => $cursor,
            ]);
            $this->assertApiOk($result, 'DingTalk user list failed');

            $list = $result['data']['result']['list'] ?? [];
            foreach ((is_array($list) ? $list : []) as $index => $user) {
                if (!is_array($user)) {
                    $skipped[] = ['index' => $index, 'entry' => $user];
                    continue;
                }

                $id = (string) ($user['userid'] ?? '');
                if ($id === '') {
                    $skipped[] = ['index' => $index, 'entry' => $user];
                    continue;
                }

                $email = (string) ($user['email'] ?? '');
                if ($email === '') {
                    $email = (string) ($user['org_email'] ?? '');
                }

                $mobile = trim((string) ($user['mobile'] ?? ''));
                if ($mobile !== '') {
                    $mobile = preg_replace('/[\s-]/', '', $mobile) ?? $mobile;
                }

                $displayName = trim((string) ($user['name'] ?? ''));
                // 钉钉：Zabbix 用户名优先手机号；无手机号时将姓名转拼音；仍无法生成时回退 userid。
                if ($mobile !== '') {
                    $username = $mobile;
                }
                else {
                    $username = PinyinHelper::nameToUsername($displayName);
                    if ($username === '') {
                        $username = $id;
                    }
                }

                $users[] = [
                    'id'       => $id,
                    'name'     => $displayName,
                    'username' => $username,
                    'email'    => $email,
                    'mobile'   => $mobile,
                    'raw'      => $user,
                ];
            }

            $hasMore = (bool) ($result['data']['result']['has_more'] ?? false);
            $cursor = (int) ($result['data']['result']['next_cursor'] ?? 0);
        } while ($hasMore);

        if ($skipped !== []) {
            $this->appendApiDebugNote('user.list.skipped_no_userid', [
                'department_id' => $departmentId,
                'skipped'       => $skipped,
            ]);
        }

        return $users;
    }

    /**
     * 获取单个部门详情（主要用于补齐根部门名称）。
     */
    private function fetchDepartmentDetail(int $deptId): ?array {
        $url = 'https://oapi.dingtalk.com/topapi/v2/department/get?access_token=' . urlencode($this->accessToken);
        $result = $this->http->postJson($url, [
            'dept_id'  => $deptId,
            'language' => 'zh_CN',
        ]);
        $this->recordApiResponse('department.get', $url, $result, [
            'dept_id' => $deptId,
        ]);

        // 根部门或权限受限时可能失败，此处不抛异常，返回 null 由调用方决定。
        if (!$result['ok'] || (int) ($result['data']['errcode'] ?? -1) !== 0) {
            return null;
        }

        $dept = $result['data']['result'] ?? null;
        if (!is_array($dept)) {
            return null;
        }

        // 强制使用配置的根部门 ID，确保子部门的 parent_id 能正确链接到根。
        $id = (string) $deptId;
        $name = trim((string) ($dept['name'] ?? ''));
        if ($name === '') {
            // 根部门有时不返回名称，给出兜底名，避免漏建用户组。
            $name = $deptId === 1
                ? LanguageManager::t('DingTalk root department')
                : LanguageManager::tf('Department %s', $id);
        }

        return [
            'id'        => $id,
            'name'      => $name,
            // 根部门统一指向 0，避免父子自引用导致路径构建异常。
            'parent_id' => '0',
            'raw'       => $dept,
        ];
    }

    /**
     * 递归获取子部门（listsub 每次仅返回下一级）。
     *
     * @param array<int, array<string, mixed>> $departments
     * @param array<string, bool>              $seen
     */
    private function collectDepartments(int $deptId, string $parentId, array &$departments, array &$seen): void {
        $url = 'https://oapi.dingtalk.com/topapi/v2/department/listsub?access_token=' . urlencode($this->accessToken);
        $result = $this->http->postJson($url, [
            'dept_id'  => $deptId,
            'language' => 'zh_CN',
        ]);
        $this->recordApiResponse('department.listsub', $url, $result, [
            'parent_dept_id' => $deptId,
        ]);
        $this->assertApiOk($result, 'DingTalk department listsub failed');

        foreach (($result['data']['result'] ?? []) as $dept) {
            if (!is_array($dept)) {
                continue;
            }

            $id = (string) ($dept['dept_id'] ?? '');
            $name = trim((string) ($dept['name'] ?? ''));
            if ($id === '' || $name === '') {
                continue;
            }

            // 剔除家校通讯录，并避免重复与环路。
            if ($id === self::SCHOOL_CONTACT_DEPT_ID || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $departments[] = [
                'id'        => $id,
                'name'      => $name,
                'parent_id' => (string) ($dept['parent_id'] ?? $parentId),
                'raw'       => $dept,
            ];

            $this->collectDepartments((int) $id, $id, $departments, $seen);
        }
    }

    private function ensureAccessToken(): void {
        if ($this->accessToken !== '' && time() < $this->tokenExpiresAt - 60) {
            return;
        }

        if ($this->appKey === '' || $this->appSecret === '') {
            throw new \RuntimeException(LanguageManager::t('DingTalk app_key or app_secret is empty'));
        }

        $url = 'https://oapi.dingtalk.com/gettoken?appkey='
            . urlencode($this->appKey)
            . '&appsecret=' . urlencode($this->appSecret);

        $result = $this->http->get($url);
        $this->recordApiResponse('gettoken', $url, $result, [
            'app_key' => $this->appKey,
        ]);
        $this->assertApiOk($result, 'DingTalk gettoken failed');

        $this->accessToken = (string) ($result['data']['access_token'] ?? '');
        $expiresIn = (int) ($result['data']['expires_in'] ?? 7200);
        $this->tokenExpiresAt = time() + max(300, $expiresIn);

        if ($this->accessToken === '') {
            throw new \RuntimeException(LanguageManager::t('DingTalk access_token is empty'));
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function recordApiResponse(string $action, string $url, array $result, array $context = []): void {
        $safeUrl = preg_replace('/access_token=[^&]+/', 'access_token=***', $url) ?? $url;
        $parsed = $result['data'] ?? null;
        $raw = (string) ($result['raw'] ?? '');

        if ($raw === '' && is_array($parsed)) {
            $raw = json_encode($parsed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $entry = array_merge($context, [
            'provider'    => 'dingtalk',
            'action'      => $action,
            'url'         => $safeUrl,
            'http_status' => (int) ($result['status'] ?? 0),
            'http_ok'     => !empty($result['ok']),
            'parsed'      => $this->sanitizeSensitiveApiData($action, $parsed),
            'raw'         => $this->sanitizeSensitiveRaw($action, $raw),
        ]);

        if ($action === 'user.list' && is_array($parsed)) {
            $list = $parsed['result']['list'] ?? [];
            $entry['userlist_count'] = is_array($list) ? count($list) : 0;
            $entry['userids'] = [];
            foreach ((is_array($list) ? $list : []) as $user) {
                if (is_array($user) && ($user['userid'] ?? '') !== '') {
                    $entry['userids'][] = (string) $user['userid'];
                }
            }
        }

        $this->apiDebugLog[] = $entry;

        $logContext = $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        error_log('[Zabbix IM DingTalk] ' . $action . $logContext . ' raw='
            . $this->sanitizeSensitiveRaw($action, $raw));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function appendApiDebugNote(string $action, array $data): void {
        $entry = array_merge($data, [
            'provider' => 'dingtalk',
            'action'   => $action,
        ]);
        $this->apiDebugLog[] = $entry;
        error_log('[Zabbix IM DingTalk] ' . $action . ' ' . json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param mixed $data
     * @return mixed
     */
    private function sanitizeSensitiveApiData(string $action, $data) {
        if ($action !== 'gettoken' || !is_array($data)) {
            return $data;
        }
        if (isset($data['access_token'])) {
            $data['access_token'] = '***';
        }
        return $data;
    }

    private function sanitizeSensitiveRaw(string $action, string $raw): string {
        if ($action !== 'gettoken') {
            return $raw;
        }
        return preg_replace('/"access_token"\s*:\s*"[^"]+"/', '"access_token":"***"', $raw) ?? $raw;
    }

    private function assertApiOk(array $result, string $messageKey): void {
        $prefix = LanguageManager::t($messageKey);
        $httpError = LanguageManager::t('HTTP error');

        if (!$result['ok']) {
            throw new \RuntimeException($prefix . ': ' . ($result['error'] ?: $httpError));
        }

        $errcode = (int) ($result['data']['errcode'] ?? -1);
        if ($errcode !== 0) {
            $errmsg = (string) ($result['data']['errmsg'] ?? 'unknown error');
            throw new \RuntimeException($prefix . ': ' . $errmsg . ' (' . $errcode . ')');
        }
    }
}
