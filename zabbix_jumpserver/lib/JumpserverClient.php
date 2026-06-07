<?php

namespace Modules\ZabbixJumpserver\Lib;

/**
 * JumpServer REST API 客户端
 *
 * 使用 JumpServer AccessKey 的 HTTP Signature 认证方式（与 drf-httpsig 一致）：
 * - 签名头：(request-target) accept date
 * - 算法：hmac-sha256，签名结果 base64 编码
 * - Authorization: Signature keyId="...",algorithm="hmac-sha256",headers="(request-target) accept date",signature="..."
 *
 * 参考：https://docs.jumpserver.org/zh/v4/dev/rest_api/
 */
class JumpserverClient {

    private $baseUrl;
    private $keyId;
    private $secret;
    private $orgId;
    private $verifySsl;

    public function __construct(array $config) {
        $this->baseUrl = rtrim((string) ($config['jumpserver_url'] ?? ''), '/');
        $this->keyId = (string) ($config['access_key_id'] ?? '');
        $this->secret = (string) ($config['access_key_secret'] ?? '');
        $this->orgId = (string) ($config['org_id'] ?? '00000000-0000-0000-0000-000000000002');
        $this->verifySsl = (bool) ($config['verify_ssl'] ?? false);
    }

    public function isConfigured(): bool {
        return $this->baseUrl !== '' && $this->keyId !== '' && $this->secret !== '';
    }

    /**
     * 执行一次 API 请求
     *
     * @param string     $method GET/POST/PATCH/DELETE
     * @param string     $path   以 / 开头的 API 路径（可含 query string）
     * @param array|null $body   请求体（将编码为 JSON）
     * @return array ['ok' => bool, 'status' => int, 'data' => mixed, 'error' => string]
     */
    public function request(string $method, string $path, ?array $body = null): array {
        $method = strtoupper($method);
        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $accept = 'application/json';

        // 构造签名串：request-target 必须与实际请求的 method + path 完全一致
        $requestTarget = strtolower($method) . ' ' . $path;
        $signingString = '(request-target): ' . $requestTarget . "\n"
            . 'accept: ' . $accept . "\n"
            . 'date: ' . $date;
        $signature = base64_encode(hash_hmac('sha256', $signingString, $this->secret, true));

        $authorization = sprintf(
            'Signature keyId="%s",algorithm="hmac-sha256",headers="(request-target) accept date",signature="%s"',
            $this->keyId,
            $signature
        );

        $headers = [
            'Accept: ' . $accept,
            'Date: ' . $date,
            'X-JMS-ORG: ' . $this->orgId,
            'Authorization: ' . $authorization,
        ];

        $payload = null;
        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        }

        $url = $this->baseUrl . $path;

        if (function_exists('curl_init')) {
            return $this->curlRequest($method, $url, $headers, $payload);
        }

        return $this->streamRequest($method, $url, $headers, $payload);
    }

    private function curlRequest(string $method, string $url, array $headers, ?string $payload): array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['ok' => false, 'status' => 0, 'data' => null, 'error' => $error];
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $this->buildResult($status, $response);
    }

    private function streamRequest(string $method, string $url, array $headers, ?string $payload): array {
        $opts = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'timeout' => 30,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => $this->verifySsl,
                'verify_peer_name' => $this->verifySsl,
            ],
        ];
        if ($payload !== null) {
            $opts['http']['content'] = $payload;
        }

        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);

        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }

        if ($response === false) {
            return ['ok' => false, 'status' => $status, 'data' => null, 'error' => 'Request failed'];
        }

        return $this->buildResult($status, $response);
    }

    private function buildResult(int $status, string $response): array {
        $data = json_decode($response, true);
        $ok = ($status >= 200 && $status < 300);

        $error = '';
        if (!$ok) {
            $error = is_string($response) ? $response : '';
            if (is_array($data)) {
                $error = json_encode($data, JSON_UNESCAPED_UNICODE);
            }
        }

        return [
            'ok' => $ok,
            'status' => $status,
            'data' => $data,
            'error' => $error,
        ];
    }

    /**
     * 获取分页/非分页列表的全部结果（JumpServer 可能返回数组或 {count,results}）
     */
    public function getAll(string $basePath): array {
        $sep = (strpos($basePath, '?') !== false) ? '&' : '?';
        $result = $this->request('GET', $basePath . $sep . 'limit=1000');
        if (!$result['ok']) {
            return [];
        }

        $data = $result['data'];
        if (is_array($data) && isset($data['results']) && is_array($data['results'])) {
            return $data['results'];
        }
        if (is_array($data)) {
            return $data;
        }

        return [];
    }

    // ── 节点（资产分组） ──

    public function getNodes(): array {
        return $this->getAll('/api/v1/assets/nodes/');
    }

    public function createNode(string $name): ?array {
        $result = $this->request('POST', '/api/v1/assets/nodes/', ['value' => $name]);
        if ($result['ok'] && is_array($result['data'])) {
            return $result['data'];
        }
        return null;
    }

    // ── 平台 ──

    public function getPlatforms(): array {
        return $this->getAll('/api/v1/assets/platforms/');
    }

    // ── 主机资产 ──

    public function getHosts(): array {
        return $this->getAll('/api/v1/assets/hosts/');
    }

    public function createHost(array $data): array {
        return $this->request('POST', '/api/v1/assets/hosts/', $data);
    }

    public function updateHost(string $assetId, array $data): array {
        return $this->request('PATCH', '/api/v1/assets/hosts/' . $assetId . '/', $data);
    }

    // ── 账号 ──

    /**
     * 获取账号模板列表（用于校验 / 展示）
     */
    public function getAccountTemplates(): array {
        return $this->getAll('/api/v1/accounts/account-templates/');
    }

    /**
     * 按账号模板为资产关联账号
     *
     * 使用批量接口 /api/v1/accounts/account-bulk/，模板会自动填充
     * 用户名 / 密钥 / 密钥类型；on_invalid=skip 保证重复推送幂等
     * （账号唯一键为 asset_id + username + secret_type）。
     *
     * @param bool $pushNow 是否在目标主机上推送创建账号（false 仅录入凭据）
     */
    public function associateAccountByTemplate(string $assetId, string $templateId, bool $pushNow = false): array {
        return $this->request('POST', '/api/v1/accounts/account-bulk/', [
            'assets'     => [$assetId],
            'template'   => $templateId,
            'push_now'   => $pushNow,
            'on_invalid' => 'skip',
        ]);
    }
}
