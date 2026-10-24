<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor;

use Marcolin\IxcProvedor\Exception\UnexpectedResponseException;
use Psr\Http\Message\ResponseInterface;

/**
 * One page of a listing.
 */
final class Page
{
    private int $page;

    private int $total;

    /** @var list<array<string, mixed>> */
    private array $rows;

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(int $page, int $total, array $rows)
    {
        $this->page = $page;
        $this->total = $total;
        $this->rows = $rows;
    }

    /**
     * Reads {"page", "total", "registros"}; "total" comes as an int or a numeric string, and an empty
     * listing has no "registros".
     */
    public static function fromResponse(ResponseInterface $response): self
    {
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);
        $page = is_array($decoded) ? self::integer($decoded['page'] ?? null) : null;
        $total = is_array($decoded) ? self::integer($decoded['total'] ?? null) : null;
        $rows = is_array($decoded) ? ($decoded['registros'] ?? []) : null;
        if ($page === null || $total === null || !is_array($rows)) {
            throw new UnexpectedResponseException(
                'The listing answer has no "page" and "total".',
                $response->getStatusCode(),
                $body
            );
        }

        $list = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new UnexpectedResponseException(
                    'A row of the listing is not an object.',
                    $response->getStatusCode(),
                    $body
                );
            }
            $list[] = self::row($row);
        }

        return new self($page, $total, $list);
    }

    public function page(): int
    {
        return $this->page;
    }

    /**
     * How many records match the query, across all pages.
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(): array
    {
        return $this->rows;
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    /**
     * @param mixed $value
     */
    private static function integer($value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && preg_match('/^\d+$/', $value) === 1 ? (int) $value : null;
    }

    /**
     * @param array<mixed> $row
     * @return array<string, mixed>
     */
    private static function row(array $row): array
    {
        $typed = [];
        foreach ($row as $key => $value) {
            $typed[(string) $key] = $value;
        }

        return $typed;
    }
}
