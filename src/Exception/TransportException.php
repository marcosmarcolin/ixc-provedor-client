<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Exception;

use RuntimeException;

/**
 * The request never got an answer: DNS, connection, TLS or timeout, as reported by the PSR-18 client.
 */
final class TransportException extends RuntimeException implements IxcException
{
}
