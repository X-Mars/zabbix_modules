<?php

namespace Modules\ZabbixIm\Lib;

require_once __DIR__ . '/LanguageManager.php';

/**
 * 企业微信通讯录 API
 * https://developer.work.weixin.qq.com/document/path/90208
 */
class WeComClient implements ImProviderInterface {

    private $corpId;
    private $corpSecret;
    private $rootDepartmentId;
    private $http;
    private $accessToken = '';
    private $tokenExpiresAt = 0;

    /** @var array<int, array<string, mixed>> */
    private $apiDebugLog = [];

    public function __construct(array $config, bool $verifySsl = true) {
        $this->corpId = trim((string) ($config['corp_id'] ?? ''));
        $this->corpSecret = trim((string) ($config['corp_secret'] ?? ''));
        $this->rootDepartmentId = (string) ($config['root_department_id'] ?? 1);
        $this->http = new HttpClient($verifySsl);
    }

    public function getApiDebugLog(): array {
        return $this->apiDebugLog;
    }

    public function getDepartments(): array {
        $this->ensureAccessToken();

        $url = 'https://qyapi.weixin.qq.com/cgi-bin/department/list?access_token='
            . urlencode($this->accessToken)
            . '&id=' . urlencode($this->rootDepartmentId);

        $result = $this->http->get($url);
        $this->recordApiResponse('department.list', $url, $result, [
            'root_department_id' => $this->rootDepartmentId,
        ]);
        $this->assertApiOk($result, 'WeCom department list failed');

        $departments = [];
        foreach (($result['data']['department'] ?? []) as $dept) {
            $id = (string) ($dept['id'] ?? '');
            $name = trim((string) ($dept['name'] ?? ''));
            if ($id === '' || $name === '') {
                continue;
            }
            $departments[] = [
                'id'        => $id,
                'name'      => $name,
                'parent_id' => (string) ($dept['parentid'] ?? '0'),
                'raw'       => $dept,
            ];
        }

        return $departments;
    }

    public function getDepartmentUsers(string $departmentId): array {
        $this->ensureAccessToken();

        $url = 'https://qyapi.weixin.qq.com/cgi-bin/user/list?access_token='
            . urlencode($this->accessToken)
            . '&department_id=' . urlencode($departmentId)
            . '&fetch_child=0';

        $result = $this->http->get($url);
        $this->recordApiResponse('user.list', $url, $result, [
            'department_id' => $departmentId,
        ]);
        $this->assertApiOk($result, 'WeCom user list failed');

        $users = [];
        $skippedNoUserid = [];
        foreach (($result['data']['userlist'] ?? []) as $index => $user) {
            if (!is_array($user)) {
                $skippedNoUserid[] = ['index' => $index, 'entry' => $user];
                continue;
            }

            $id = (string) ($user['userid'] ?? '');
            if ($id === '') {
                $skippedNoUserid[] = ['index' => $index, 'entry' => $user];
                error_log('[Zabbix IM WeCom] user.list dept=' . $departmentId
                    . ' entry missing userid: ' . json_encode($user, JSON_UNESCAPED_UNICODE));
                continue;
            }

            $users[] = [
                'id'       => $id,
                'name'     => (string) ($user['name'] ?? ''),
                'username' => $id,
                'email'    => (string) ($user['email'] ?? ($user['biz_mail'] ?? '')),
                'mobile'   => (string) ($user['mobile'] ?? ''),
                'raw'      => $user,
            ];
        }

        if ($skippedNoUserid !== []) {
            $this->appendApiDebugNote('user.list.skipped_no_userid', [
                'department_id' => $departmentId,
                'skipped'       => $skippedNoUserid,
            ]);
        }

        return $users;
    }

    private function ensureAccessToken(): void {
        if ($this->accessToken !== '' && time() < $this->tokenExpiresAt - 60) {
            return;
        }

        if ($this->corpId === '' || $this->corpSecret === '') {
            throw new \RuntimeException(LanguageManager::t('WeCom corp_id or corp_secret is empty'));
        }

        $url = 'https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid='
            . urlencode($this->corpId)
            . '&corpsecret=' . urlencode($this->corpSecret);

        $result = $this->http->get($url);
        $this->recordApiResponse('gettoken', $url, $result, [
            'corp_id' => $this->corpId,
        ]);
        $this->assertApiOk($result, 'WeCom gettoken failed');

        $this->accessToken = (string) ($result['data']['access_token'] ?? '');
        $expiresIn = (int) ($result['data']['expires_in'] ?? 7200);
        $this->tokenExpiresAt = time() + max(300, $expiresIn);

        if ($this->accessToken === '') {
            throw new \RuntimeException(LanguageManager::t('WeCom access_token is empty'));
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
            'provider'    => 'wecom',
            'action'      => $action,
            'url'         => $safeUrl,
            'http_status' => (int) ($result['status'] ?? 0),
            'http_ok'     => !empty($result['ok']),
            'parsed'      => $this->sanitizeSensitiveApiData($action, $parsed),
            'raw'         => $this->sanitizeSensitiveRaw($action, $raw),
        ]);

        if ($action === 'user.list' && is_array($parsed)) {
            $entry['userlist_count'] = count($parsed['userlist'] ?? []);
            $entry['userids'] = [];
            foreach (($parsed['userlist'] ?? []) as $user) {
                if (is_array($user) && ($user['userid'] ?? '') !== '') {
                    $entry['userids'][] = (string) $user['userid'];
                }
            }
        }

        $this->apiDebugLog[] = $entry;

        $logContext = $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        error_log('[Zabbix IM WeCom] ' . $action . $logContext . ' raw='
            . $this->sanitizeSensitiveRaw($action, $raw));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function appendApiDebugNote(string $action, array $data): void {
        $entry = array_merge($data, [
            'provider' => 'wecom',
            'action'   => $action,
        ]);
        $this->apiDebugLog[] = $entry;
        error_log('[Zabbix IM WeCom] ' . $action . ' ' . json_encode($data, JSON_UNESCAPED_UNICODE));
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

        $errcode = (int) ($result['data']['errcode'] ?? 0);
        if ($errcode !== 0) {
            $errmsg = (string) ($result['data']['errmsg'] ?? 'unknown error');
            throw new \RuntimeException($prefix . ': ' . $errmsg . ' (' . $errcode . ')');
        }
    }
}
