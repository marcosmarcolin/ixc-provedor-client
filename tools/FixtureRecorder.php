<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tools;

/**
 * Sends a scenario and writes its raw and anonymized fixtures.
 */
final class FixtureRecorder
{
    private HttpCapture $http;

    private Anonymizer $anonymizer;

    private string $directory;

    /** @var resource */
    private $output;

    /**
     * @param resource $output where the one-line summary of each scenario goes
     */
    public function __construct(HttpCapture $http, Anonymizer $anonymizer, string $directory, $output)
    {
        $this->http = $http;
        $this->anonymizer = $anonymizer;
        $this->directory = rtrim($directory, '/');
        $this->output = $output;
    }

    /**
     * @param non-empty-string $method
     * @param array<string, string> $headers
     * @param string|array<string, string>|null $body
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public function record(
        string $name,
        string $method,
        string $path,
        array $headers = [],
        $body = null,
        ?string $token = null
    ): array {
        $response = $this->http->send($method, $path, $headers, $body, $token);

        $decoded = json_decode($response['body'], true);
        $isJson = is_array($decoded);
        $request = ['method' => $method, 'path' => $path, 'headers' => $headers, 'body' => $body];
        // json_encode()'s defaults are the server's, so the escapes stay as captured.
        $body = $isJson ? (string) json_encode($this->anonymizer->anonymize($decoded)) : $response['body'];

        $this->write('raw/' . $name, ['request' => $request, 'response' => $response]);
        $this->write($name, [
            'request' => $request,
            'response' => ['status' => $response['status'], 'headers' => $response['headers'], 'body' => $body],
        ]);

        fprintf(
            $this->output,
            "%-26s %3d  %-38s %s\n",
            $name,
            $response['status'],
            $response['headers']['content-type'] ?? '-',
            $isJson ? 'json' : 'not json, review by hand'
        );

        return $response;
    }

    /**
     * @param array<string, mixed> $fixture
     */
    private function write(string $name, array $fixture): void
    {
        $file = $this->directory . '/' . $name . '.json';
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }
}
