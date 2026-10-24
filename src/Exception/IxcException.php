<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Exception;

use Throwable;

/**
 * Implemented by every exception this client throws about a request, so callers can catch them all.
 */
interface IxcException extends Throwable
{
}
