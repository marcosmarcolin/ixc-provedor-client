<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;

/**
 * The built-in PSR-18 client, on ext-curl: PSR-18 has no TLS settings, and the $selfSigned option of
 * the official client needs them.
 */
final class CurlHttpClient implements ClientInterface
{
    private ResponseFactoryInterface $responseFactory;

    private StreamFactoryInterface $streamFactory;

    private bool $selfSigned;

    private int $timeout;

    private int $connectTimeout;

    /**
     * @param bool $selfSigned true accepts a self-signed certificate, skipping verification
     * @param int $timeout seconds for the whole request
     * @param int $connectTimeout seconds to connect
     */
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        bool $selfSigned = false,
        int $timeout = 60,
        int $connectTimeout = 10
    ) {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->selfSigned = $selfSigned;
        $this->timeout = $timeout;
        $this->connectTimeout = $connectTimeout;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $curl = curl_init();
        if ($curl === false) {
            throw new RuntimeException('curl_init() failed.');
        }

        $head = [];
        curl_setopt_array($curl, $this->options($request));
        curl_setopt($curl, CURLOPT_HEADERFUNCTION, static function ($curl, string $line) use (&$head): int {
            $head[] = $line;

            return strlen($line);
        });

        $body = curl_exec($curl);
        if (!is_string($body)) {
            $error = curl_error($curl);
            self::close($curl);

            throw new CurlException($error, $request);
        }
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        self::close($curl);

        /** @var list<string> $head */
        $response = $this->responseFactory->createResponse($status)
            ->withBody($this->streamFactory->createStream($body));
        foreach (self::headers($head) as $name => $values) {
            $response = $response->withHeader($name, $values);
        }

        return $response;
    }

    /**
     * The curl options for a request, certificate check included.
     *
     * @return array<int, mixed>
     */
    public function options(RequestInterface $request): array
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[] = $name . ': ' . implode(', ', $values);
        }
        // Without it, curl adds "Expect: 100-continue" to larger bodies and waits for the server.
        $headers[] = 'Expect:';

        $options = [
            CURLOPT_URL => (string) $request->getUri(),
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => !$this->selfSigned,
            CURLOPT_SSL_VERIFYHOST => $this->selfSigned ? 0 : 2,
        ];
        $body = (string) $request->getBody();
        if ($body !== '') {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        return $options;
    }

    /**
     * Frees the handle on PHP 7; since PHP 8 curl_close() does nothing and is deprecated in 8.5.
     *
     * @param resource $curl
     */
    private static function close($curl): void
    {
        if (PHP_VERSION_ID < 80000) {
            curl_close($curl);
        }
    }

    /**
     * Header lines of the last response (after any 100 Continue), grouped by name.
     *
     * @param list<string> $lines
     * @return array<string, list<string>>
     */
    public static function headers(array $lines): array
    {
        $headers = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, 'HTTP/') === 0) {
                $headers = [];
                continue;
            }
            $parts = explode(':', $line, 2);
            if (count($parts) === 2 && trim($parts[0]) !== '') {
                $headers[trim($parts[0])][] = trim($parts[1]);
            }
        }

        return $headers;
    }
}
