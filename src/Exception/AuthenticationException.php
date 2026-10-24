<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Exception;

use RuntimeException;

/**
 * HTTP 401: the token was rejected (wrong secret or unknown user).
 */
final class AuthenticationException extends RuntimeException implements IxcException
{
}
