<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Exception;

use RuntimeException;

/**
 * HTTP 401: the token was rejected, or the route only accepts requests from the server itself.
 */
final class AuthenticationException extends RuntimeException implements IxcException
{
}
