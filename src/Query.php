<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor;

use InvalidArgumentException;

/**
 * The parameters of a listing: one condition (qtype, oper, query), paging, sorting and extra grid_param filters.
 *
 * Fields may be given bare ("id") or qualified ("cliente.id"); bare ones are qualified with the table name.
 */
final class Query
{
    private string $field;

    private string $operator;

    private string $value;

    private int $page = 1;

    private int $perPage = 20;

    private string $sortField;

    private string $sortOrder = 'asc';

    /** @var list<array{TB: string, OP: string, P: string}> */
    private array $filters = [];

    private function __construct(string $field, string $operator, string $value)
    {
        $this->field = self::field($field);
        $this->operator = self::operator($operator);
        $this->value = $value;
        $this->sortField = $this->field;
    }

    /**
     * @param string $operator as the API takes it, such as "=", ">", "<" or "L" (like)
     */
    public static function where(string $field, string $operator, string $value): self
    {
        return new self($field, $operator, $value);
    }

    /**
     * Every record, as the wiki examples list them: id > 0, sorted by id.
     */
    public static function all(): self
    {
        return new self('id', '>', '0');
    }

    /**
     * Adds a condition through grid_param, on top of the main one.
     */
    public function andWhere(string $field, string $operator, string $value): self
    {
        $copy = clone $this;
        $copy->filters[] = ['TB' => self::field($field), 'OP' => self::operator($operator), 'P' => $value];

        return $copy;
    }

    public function orderBy(string $field, string $order = 'asc'): self
    {
        $order = strtolower($order);
        if ($order !== 'asc' && $order !== 'desc') {
            throw new InvalidArgumentException('The sort order must be "asc" or "desc".');
        }
        $copy = clone $this;
        $copy->sortField = self::field($field);
        $copy->sortOrder = $order;

        return $copy;
    }

    public function withPage(int $page): self
    {
        if ($page < 1) {
            throw new InvalidArgumentException('The page must be 1 or greater.');
        }
        $copy = clone $this;
        $copy->page = $page;

        return $copy;
    }

    public function withPerPage(int $perPage): self
    {
        if ($perPage < 1) {
            throw new InvalidArgumentException('The page size must be 1 or greater.');
        }
        $copy = clone $this;
        $copy->perPage = $perPage;

        return $copy;
    }

    public function page(): int
    {
        return $this->page;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    /**
     * The listing body for a table, with every field qualified by its name.
     *
     * @return array<string, string>
     */
    public function toParameters(string $table): array
    {
        $parameters = [
            'qtype' => self::qualify($this->field, $table),
            'query' => $this->value,
            'oper' => $this->operator,
            'page' => (string) $this->page,
            'rp' => (string) $this->perPage,
            'sortname' => self::qualify($this->sortField, $table),
            'sortorder' => $this->sortOrder,
        ];
        if ($this->filters !== []) {
            $filters = [];
            foreach ($this->filters as $filter) {
                $filters[] = ['TB' => self::qualify($filter['TB'], $table)] + $filter;
            }
            $parameters['grid_param'] = (string) json_encode($filters);
        }

        return $parameters;
    }

    private static function field(string $field): string
    {
        if (preg_match('/^([A-Za-z0-9_]+\.)?[A-Za-z0-9_]+$/', $field) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid field "%s".', $field));
        }

        return $field;
    }

    private static function operator(string $operator): string
    {
        if ($operator === '' || preg_match('/\s/', $operator) === 1) {
            throw new InvalidArgumentException('The operator must be non-empty and contain no whitespace.');
        }

        return $operator;
    }

    private static function qualify(string $field, string $table): string
    {
        return strpos($field, '.') === false ? $table . '.' . $field : $field;
    }
}
