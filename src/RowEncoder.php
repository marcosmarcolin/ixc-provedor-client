<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor;

use InvalidArgumentException;
use JsonException;

/**
 * Encodes a row for an insert or update, in the format the API writes.
 *
 * Listings return dates as Y-m-d, but writes take d/m/Y: a Y-m-d date is answered with success and
 * saved blank or corrupted (1990-01-01 became 0001-01-00). So Y-m-d dates become d/m/Y, and the
 * zero date becomes empty, which keeps it zero.
 */
final class RowEncoder
{
    /**
     * @param array<string, mixed> $row
     */
    public function encode(array $row): string
    {
        $body = [];
        foreach ($row as $field => $value) {
            $body[$field] = $this->value($field, $value);
        }

        try {
            return json_encode($body, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The row cannot be encoded as JSON: ' . $exception->getMessage());
        }
    }

    /**
     * @param mixed $value
     */
    private function value(string $field, $value): string
    {
        if ($value === null) {
            return '';
        }
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            throw new InvalidArgumentException(sprintf(
                'The value of "%s" must be a string, a number or null; the API takes every value as text.',
                $field
            ));
        }

        $value = (string) $value;
        if ($value === '0000-00-00') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $date) === 1) {
            return $date[3] . '/' . $date[2] . '/' . $date[1];
        }

        return $value;
    }
}
