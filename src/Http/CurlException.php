<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Http;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

/**
 * curl could not complete the request: DNS, connection, TLS or timeout.
 */
final class CurlException extends RuntimeException implements NetworkExceptionInterface
{
    private RequestInterface $request;

    public function __construct(string $message, RequestInterface $request)
    {
        parent::__construct($message);
        $this->request = $request;
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
