<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tools;

/**
 * Replaces personal data in a decoded JSON response, keeping its keys, types and formats.
 *
 * Equal inputs give equal outputs, so ids that reference each other still match.
 */
final class Anonymizer
{
    /** Keys whose values describe the response, not a customer: the API's own text and field names. */
    private const KEPT_KEYS = ['page', 'total', 'type', 'message', 'campo'];

    /**
     * @param mixed $value
     * @return mixed
     */
    public function anonymize($value)
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = in_array($key, self::KEPT_KEYS, true) ? $item : $this->anonymize($item);
            }

            return $result;
        }

        return is_string($value) ? $this->anonymizeString($value) : $value;
    }

    private function anonymizeString(string $value): string
    {
        // Empty values, zero dates and one-letter flags (S/N, F/J...) carry no personal data.
        if ($value === '' || strlen($value) === 1 || preg_match('/^[0\-: ]+$/', $value) === 1) {
            return $value;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $date) === 1) {
            // Invalid dates (0001-01-00 from a bad write) are server artifacts, not personal data.
            return checkdate((int) $date[2], (int) $date[3], (int) $date[1]) ? '2000-01-01' : $value;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return '2000-01-01 00:00:00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return '00:00:00';
        }
        if (preg_match('/^\d+$/', $value) === 1) {
            return $this->digits($value);
        }
        if (preg_match('/^-?\d+\.\d+$/', $value) === 1) {
            return number_format(1, strlen((string) strrchr($value, '.')) - 1, '.', '');
        }
        if (strpos($value, '@') !== false) {
            return 'anon-' . $this->hash($value) . '@example.com';
        }

        return 'anon-' . $this->hash($value);
    }

    /** Same length, no leading zero, so numeric ids keep looking like ids. */
    private function digits(string $value): string
    {
        $seed = (string) hexdec($this->hash($value));
        $digits = str_pad($seed, strlen($value), $seed, STR_PAD_RIGHT);

        return '1' . substr($digits, 1, strlen($value) - 1);
    }

    private function hash(string $value): string
    {
        return substr(hash('sha256', $value), 0, 8);
    }
}
