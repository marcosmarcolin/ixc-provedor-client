<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor;

use InvalidArgumentException;
use Marcolin\IxcProvedor\Exception\ApiErrorException;
use Marcolin\IxcProvedor\Exception\AuthenticationException;
use Marcolin\IxcProvedor\Exception\TransportException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Builds the authenticated PSR-7 request for a route, sends it, and turns the API's errors into exceptions.
 *
 * Errors are recognized by the body, not the status: the API answers most of them with HTTP 200 and
 * {"type": "error", "message": ...}. Other bodies, JSON or not, are returned untouched.
 */
final class Transport
{
    private ClientInterface $client;

    private RequestFactoryInterface $requestFactory;

    private StreamFactoryInterface $streamFactory;

    private string $baseUrl;

    private Credentials $credentials;

    /**
     * @param string $host the instance's address, such as "https://ixc.example.com", without "/webservice/v1"
     */
    public function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        string $host,
        Credentials $credentials,
        ?ApiVersion $version = null
    ) {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->baseUrl = self::normalizeHost($host) . '/' . ($version ?? ApiVersion::latest())->basePath() . '/';
        $this->credentials = $credentials;
    }

    /**
     * @param string $path a table or resource, optionally followed by "/<id>": "cliente", "cliente/12"
     * @param array<string, string> $headers
     * @param string|null $body sent with $contentType, a JSON document by default
     * @throws TransportException when no answer came back
     * @throws AuthenticationException on HTTP 401
     * @throws ApiErrorException on an error body or any other HTTP error status
     */
    public function send(
        string $method,
        string $path,
        array $headers = [],
        ?string $body = null,
        string $contentType = 'application/json'
    ): ResponseInterface {
        if (preg_match('/^[A-Za-z0-9_]+(\/[A-Za-z0-9_]+)*$/', $path) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid route "%s".', $path));
        }

        $request = $this->requestFactory->createRequest($method, $this->baseUrl . $path)
            ->withHeader('Authorization', $this->credentials->authorizationHeader())
            ->withHeader('Accept', 'application/json');
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', $contentType)
                ->withBody($this->streamFactory->createStream($body));
        }

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new TransportException(
                sprintf('%s %s got no answer: %s', $method, $path, $exception->getMessage()),
                0,
                $exception
            );
        }

        $this->guard($response);

        return $response;
    }

    private function guard(ResponseInterface $response): void
    {
        $status = $response->getStatusCode();
        if ($status === 401) {
            throw new AuthenticationException('HTTP 401: the token was rejected.');
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);
        $isError = is_array($decoded) && ($decoded['type'] ?? null) === 'error';
        if (!$isError && $status < 400) {
            return;
        }

        $message = is_array($decoded) && isset($decoded['message']) && is_scalar($decoded['message'])
            ? (string) $decoded['message']
            : $response->getReasonPhrase();

        throw new ApiErrorException($status, $message, $body);
    }

    private static function normalizeHost(string $host): string
    {
        $parts = parse_url(trim($host));
        $scheme = is_array($parts) ? strtolower($parts['scheme'] ?? '') : '';
        if (!is_array($parts) || !in_array($scheme, ['http', 'https'], true) || !isset($parts['host'])) {
            // The given value is never echoed: it could carry credentials.
            throw new InvalidArgumentException('The host must be an http(s) URL such as "https://ixc.example.com".');
        }
        if (isset($parts['user']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new InvalidArgumentException('The host must have no credentials, query or fragment.');
        }
        if (trim($parts['path'] ?? '', '/') !== '') {
            // The wiki examples include it in $host, so this is the likely mistake.
            throw new InvalidArgumentException(
                'Pass only the instance address, without a path such as "/webservice/v1".'
            );
        }

        return $scheme . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }
}
