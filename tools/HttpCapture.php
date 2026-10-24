<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tools;

use RuntimeException;

/**
 * Sends one request to a real IXC Provedor instance with curl and returns what came back.
 *
 * Dev-only: the package itself never talks to curl directly.
 */
final class HttpCapture
{
    /** Response headers worth keeping in a fixture; the rest is server noise. */
    private const KEPT_HEADERS = ['content-type', 'allow', 'www-authenticate'];

    private string $host;

    private string $token;

    private bool $insecure;

    public function __construct(string $host, string $token, bool $insecure)
    {
        $this->host = rtrim($host, '/');
        $this->token = $token;
        $this->insecure = $insecure;
    }

    /**
     * @param non-empty-string $method
     * @param array<string, string> $headers
     * @param string|array<string, string|\CURLFile>|null $body a string is sent as is, an array as multipart form data
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function send(string $method, string $path, array $headers = [], $body = null, ?string $token = null): array
    {
        $curl = curl_init($this->host . '/' . ltrim($path, '/'));
        if ($curl === false) {
            throw new RuntimeException('curl_init() failed.');
        }

        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        $credentials = $token ?? $this->token;
        if ($credentials === '') {
            throw new RuntimeException('The token is empty.');
        }
        curl_setopt($curl, CURLOPT_USERPWD, $credentials);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $lines);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, !$this->insecure);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $this->insecure ? 0 : 2);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($curl);
        if (!is_string($response)) {
            // Names the path only, never the token.
            throw new RuntimeException(sprintf('%s %s failed: %s', $method, $path, curl_error($curl)));
        }
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        curl_close($curl);

        return [
            'status' => $status,
            'headers' => $this->keptHeaders(substr($response, 0, $headerSize)),
            'body' => substr($response, $headerSize),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function keptHeaders(string $head): array
    {
        $headers = [];
        foreach (preg_split('/\r?\n/', $head) ?: [] as $line) {
            $parts = explode(':', $line, 2);
            $name = strtolower(trim($parts[0]));
            if (count($parts) === 2 && in_array($name, self::KEPT_HEADERS, true)) {
                $headers[$name] = trim($parts[1]);
            }
        }

        return $headers;
    }
}
