<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Exception;

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

        $messages = self::split($apiMessage);
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
     * The API joins several validation errors with <br />; this gives them one by one, as plain text.
     *
     * @return list<string>
     */
    public function messages(): array
    {
        return self::split($this->apiMessage);
    }

    public function responseBody(): string
    {
        return $this->responseBody;
    }

    /**
     * @return list<string>
     */
    private static function split(string $message): array
    {
        $parts = preg_split('/<br\s*\/?>/i', $message) ?: [];
        $messages = [];
        foreach ($parts as $part) {
            $text = trim(html_entity_decode(strip_tags($part), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($text !== '') {
                $messages[] = $text;
            }
        }

        return $messages;
    }
}
