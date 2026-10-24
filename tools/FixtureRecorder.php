<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tools;

/**
 * Sends a scenario and writes its raw and anonymized fixtures.
 */
final class FixtureRecorder
{
    /** Bodies kept whole up to this size; longer or binary ones (PDFs) keep only their start. */
    private const MAX_BODY = 2048;

    private const KEPT_PREFIX = 512;

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
     * @param string|array<string, string|\CURLFile>|null $body
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
        $isBinary = preg_match('//u', $response['body']) !== 1;
        $request = ['method' => $method, 'path' => $path, 'headers' => $headers, 'body' => self::describe($body)];
        // json_encode()'s defaults are the server's, so the escapes stay as captured.
        $responseBody = $isJson ? (string) json_encode($this->anonymizer->anonymize($decoded)) : $response['body'];

        $this->write('raw/' . $name, ['request' => $request, 'response' => self::storable($response, PHP_INT_MAX)]);
        $this->write($name, [
            // Write bodies are built from a listed row, so they carry the same personal data.
            'request' => ['body' => $this->requestBody($headers, self::describe($body))] + $request,
            'response' => self::storable(['body' => $responseBody] + $response, $isJson ? PHP_INT_MAX : self::MAX_BODY),
        ]);

        fprintf(
            $this->output,
            "%-26s %3d  %-38s %s\n",
            $name,
            $response['status'],
            $response['headers']['content-type'] ?? '-',
            $isJson ? 'json' : ($isBinary ? 'binary, start kept' : 'not json, review by hand')
        );

        return $response;
    }

    /**
     * A body that JSON can hold as text stays "body"; a binary one goes as "body_base64". Past $max bytes,
     * only the start is kept, with "body_length" telling the real size: a PDF's start is its structure,
     * while the rest holds the customer's data, which cannot be anonymized.
     *
     * @param array{status: int, headers: array<string, string>, body: string} $response
     * @return array<string, mixed>
     */
    private static function storable(array $response, int $max): array
    {
        $body = $response['body'];
        $stored = ['status' => $response['status'], 'headers' => $response['headers']];
        $truncated = strlen($body) > $max;
        $kept = $truncated ? substr($body, 0, self::KEPT_PREFIX) : $body;
        if (preg_match('//u', $kept) === 1) {
            $stored['body'] = $kept;
        } else {
            $stored['body_base64'] = base64_encode($kept);
        }
        if ($truncated) {
            $stored['body_length'] = strlen($body);
        }

        return $stored;
    }

    /**
     * Multipart files are recorded by name and type, never by content.
     *
     * @param string|array<string, string|\CURLFile>|null $body
     * @return string|array<string, string>|null
     */
    private static function describe($body)
    {
        if (!is_array($body)) {
            return $body;
        }
        $described = [];
        foreach ($body as $name => $value) {
            $described[$name] = $value instanceof \CURLFile
                ? sprintf('<file %s, %s>', $value->getPostFilename(), $value->getMimeType())
                : $value;
        }

        return $described;
    }

    /**
     * Listing parameters carry no personal data; write bodies are built from listed rows, so they do.
     *
     * @param array<string, string> $headers
     * @param string|array<string, string>|null $body
     * @return mixed
     */
    private function requestBody(array $headers, $body)
    {
        if (($headers['ixcsoft'] ?? '') === 'listar') {
            return $body;
        }
        if (is_array($body)) {
            $anonymized = [];
            foreach ($body as $name => $value) {
                $anonymized[$name] = strpos($value, '<file ') === 0 ? $value : $this->anonymizer->anonymize($value);
            }

            return $anonymized;
        }
        $decoded = is_string($body) ? json_decode($body, true) : null;

        return is_array($decoded) ? (string) json_encode($this->anonymizer->anonymize($decoded)) : $body;
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
