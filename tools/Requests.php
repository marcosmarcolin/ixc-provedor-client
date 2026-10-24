<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tools;

/**
 * Builds the bodies of the capture scenarios and reads ids from their answers.
 */
final class Requests
{
    /**
     * Listing parameters, as the official client sends them.
     *
     * @param array<string, string> $extra
     * @return array<string, string>
     */
    public static function params(string $qtype, string $oper, string $value, array $extra = []): array
    {
        return array_merge([
            'qtype' => $qtype,
            'query' => $value,
            'oper' => $oper,
            'page' => '1',
            'rp' => '2',
            'sortname' => $qtype,
            'sortorder' => 'asc',
        ], $extra);
    }

    /**
     * @param array<string, string> $extra
     */
    public static function query(string $qtype, string $oper, string $value, array $extra = []): string
    {
        return (string) json_encode(self::params($qtype, $oper, $value, $extra));
    }

    /**
     * The "id" of an insert answer, or of the first row of a listing; "" when there is none.
     */
    public static function firstId(string $body): string
    {
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return '';
        }
        $rows = $decoded['registros'] ?? null;
        $row = is_array($rows) && isset($rows[0]) && is_array($rows[0]) ? $rows[0] : $decoded;

        return isset($row['id']) && is_scalar($row['id']) ? (string) $row['id'] : '';
    }
}
