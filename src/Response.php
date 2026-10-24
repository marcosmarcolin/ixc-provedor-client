<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor;

use Marcolin\IxcProvedor\Exception\UnexpectedResponseException;
use Psr\Http\Message\ResponseInterface;

/**
 * The raw answer of a resource. Resources answer JSON objects, JSON strings (a protocol number),
 * base64 or binary content, or nothing at all, so nothing is assumed about the body.
 */
final class Response
{
    private int $status;

    /** @var array<string, string> lowercase name => value */
    private array $headers;

    private string $body;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(int $status, array $headers, string $body)
    {
        $this->status = $status;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->body = $body;
    }

    public static function fromPsr(ResponseInterface $response): self
    {
        $headers = [];
        foreach (array_keys($response->getHeaders()) as $name) {
            $headers[(string) $name] = $response->getHeaderLine((string) $name);
        }

        return new self($response->getStatusCode(), $headers, (string) $response->getBody());
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * A header's value, or "" when it is absent. Names are case-insensitive.
     */
    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    public function body(): string
    {
        return $this->body;
    }

    public function isEmpty(): bool
    {
        return $this->body === '';
    }

    /**
     * The body decoded as JSON: an array for objects, a string for a protocol number, and so on.
     * Big integers stay strings.
     *
     * @return mixed
     * @throws UnexpectedResponseException when the body is not JSON
     */
    public function json()
    {
        $decoded = json_decode($this->body, true, 512, JSON_BIGINT_AS_STRING);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new UnexpectedResponseException(
                $this->body === '' ? 'The answer is empty.' : 'The answer is not JSON.',
                $this->status,
                $this->body
            );
        }

        return $decoded;
    }
}
