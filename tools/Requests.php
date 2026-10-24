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

    /**
     * The first row of a listing, or [] when there is none.
     *
     * @return array<string, mixed>
     */
    public static function firstRow(string $body): array
    {
        $decoded = json_decode($body, true);
        $rows = is_array($decoded) ? ($decoded['registros'] ?? null) : null;
        if (!is_array($rows) || !isset($rows[0]) || !is_array($rows[0])) {
            return [];
        }
        $row = [];
        foreach ($rows[0] as $key => $value) {
            $row[(string) $key] = $value;
        }

        return $row;
    }

    /**
     * A listed row turned into an insert body: no id, dates as writes take them (d/m/Y).
     *
     * @param array<string, mixed> $row
     * @return array<string, string>
     */
    public static function insertable(array $row): array
    {
        unset($row['id']);
        $body = [];
        foreach ($row as $key => $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }
            $value = (string) $value;
            if (strpos($value, '0000-00-00') === 0) {
                $value = '';
            } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $date) === 1) {
                $value = $date[3] . '/' . $date[2] . '/' . $date[1];
            }
            $body[$key] = $value;
        }

        return $body;
    }
}
