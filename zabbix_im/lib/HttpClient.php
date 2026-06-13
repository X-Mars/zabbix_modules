<?php

namespace Modules\ZabbixIm\Lib;

/**
 * 通用 HTTP 客户端
 */
class HttpClient {

    private $verifySsl;

    public function __construct(bool $verifySsl = true) {
        $this->verifySsl = $verifySsl;
    }

    /**
     * @return array ['ok' => bool, 'status' => int, 'data' => mixed, 'error' => string, 'raw' => string]
     */
    public function request(string $method, string $url, array $headers = [], ?array $body = null): array {
        $method = strtoupper($method);
        $payload = null;

        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $hasContentType = false;
            foreach ($headers as $header) {
                if (stripos($header, 'Content-Type:') === 0) {
                    $hasContentType = true;
                    break;
                }
            }
            if (!$hasContentType) {
                $headers[] = 'Content-Type: application/json';
            }
        }

        if (function_exists('curl_init')) {
            return $this->curlRequest($method, $url, $headers, $payload);
        }

        return $this->streamRequest($method, $url, $headers, $payload);
    }

    public function get(string $url, array $headers = []): array {
        return $this->request('GET', $url, $headers);
    }

    public function postJson(string $url, array $body, array $headers = []): array {
        return $this->request('POST', $url, $headers, $body);
    }

    private function curlRequest(string $method, string $url, array $headers, ?string $payload): array {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
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
            return ['ok' => false, 'status' => 0, 'data' => null, 'error' => $error, 'raw' => ''];
        }

        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $this->parseResponse($status, (string) $response);
    }

    private function streamRequest(string $method, string $url, array $headers, ?string $payload): array {
        $headerLines = implode("\r\n", $headers);
        $contextOptions = [
            'http' => [
                'method'        => $method,
                'header'        => $headerLines,
                'timeout'         => 60,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => $this->verifySsl,
                'verify_peer_name' => $this->verifySsl,
            ],
        ];

        if ($payload !== null) {
            $contextOptions['http']['content'] = $payload;
        }

        $context = stream_context_create($contextOptions);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'HTTP request failed', 'raw' => ''];
        }

        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $status = (int) $m[1];
        }

        return $this->parseResponse($status, (string) $response);
    }

    private function parseResponse(int $status, string $raw): array {
        $data = json_decode($raw, true);
        if ($data === null && $raw !== '' && $raw !== 'null') {
            $data = $raw;
        }

        return [
            'ok'     => $status >= 200 && $status < 300,
            'status' => $status,
            'data'   => $data,
            'error'  => ($status >= 200 && $status < 300) ? '' : ('HTTP ' . $status),
            'raw'    => $raw,
        ];
    }
}
