<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Exception;

use RuntimeException;

/**
 * The record does not exist. Raised by Route::update(), which reads the record before writing it.
 */
final class NotFoundException extends RuntimeException implements IxcException
{
}
