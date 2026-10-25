<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Exception;

use Marcolin\IxcProvedor\ApiText;
use RuntimeException;

/**
 * The API answered an error: a body with "type": "error" (usually with HTTP 200), or an HTTP error status.
 *
 * The message is the API's own text, in Portuguese, so it can be shown to users as is.
 */
final class ApiErrorException extends RuntimeException implements IxcException
{
    private int $statusCode;

    private string $apiMessage;

    private string $responseBody;

    public function __construct(int $statusCode, string $apiMessage, string $responseBody)
    {
        $this->statusCode = $statusCode;
        $this->apiMessage = $apiMessage;
        $this->responseBody = $responseBody;

        $messages = ApiText::split($apiMessage);
        parent::__construct($messages === [] ? sprintf('HTTP %d', $statusCode) : implode('; ', $messages));
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * The "message" exactly as the API sent it, HTML line breaks included.
     */
    public function apiMessage(): string
    {
        return $this->apiMessage;
    }

    /**
     * The API joins several validation errors with <br />; this gives them one by one, as plain text,
     * with double-encoded text repaired.
     *
     * @return list<string>
     */
    public function messages(): array
    {
        return ApiText::split($this->apiMessage);
    }

    public function responseBody(): string
    {
        return $this->responseBody;
    }
}
