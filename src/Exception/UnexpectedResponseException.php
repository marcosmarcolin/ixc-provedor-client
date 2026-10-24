<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Exception;

use RuntimeException;

/**
 * The answer is not what the route returns, such as the HTML page of a server failure with HTTP 200.
 */
final class UnexpectedResponseException extends RuntimeException implements IxcException
{
    private int $statusCode;

    private string $responseBody;

    public function __construct(string $message, int $statusCode, string $responseBody)
    {
        // The body stays out of the message: it can hold row data, and messages end up in logs.
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function responseBody(): string
    {
        return $this->responseBody;
    }
}
