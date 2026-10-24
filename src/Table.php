<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor;

use Generator;
use InvalidArgumentException;
use Marcolin\IxcProvedor\Exception\IxcException;
use Marcolin\IxcProvedor\Exception\NotFoundException;
use Marcolin\IxcProvedor\Exception\UnexpectedResponseException;
use Psr\Http\Message\ResponseInterface;

/**
 * The routes of one table: list, find, create (with files), update, replace, patch and delete.
 *
 * Rows are associative arrays with the API's own field names; every value the API returns is a string.
 */
final class Table
{
    private Transport $transport;

    private string $name;

    private RowEncoder $encoder;

    public function __construct(Transport $transport, string $name, ?RowEncoder $encoder = null)
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid table name "%s".', $name));
        }
        $this->transport = $transport;
        $this->name = $name;
        $this->encoder = $encoder ?? new RowEncoder();
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * One page of records; without a query, the first page of all of them.
     *
     * @throws IxcException
     */
    public function list(?Query $query = null): Page
    {
        $parameters = ($query ?? Query::all())->toParameters($this->name);
        $response = $this->transport->send(
            'POST',
            $this->name,
            ['ixcsoft' => 'listar'],
            (string) json_encode($parameters)
        );

        return Page::fromResponse($response);
    }

    /**
     * Every record matching the query, page after page, starting at the query's page.
     *
     * @return Generator<int, array<string, mixed>>
     * @throws IxcException
     */
    public function each(?Query $query = null): Generator
    {
        $query = $query ?? Query::all();
        $seen = 0;
        while (true) {
            $page = $this->list($query);
            foreach ($page->rows() as $row) {
                yield $row;
            }
            $seen += count($page->rows());
            // A short page also ends it, in case "total" is off.
            if (count($page->rows()) < $query->perPage() || $seen >= $page->total()) {
                return;
            }
            $query = $query->withPage($query->page() + 1);
        }
    }

    /**
     * @return array<string, mixed>|null the record, or null when there is none with that id
     * @throws IxcException
     */
    public function find(string $id): ?array
    {
        $rows = $this->list(Query::where('id', '=', self::id($id))->withPerPage(1))->rows();

        return $rows[0] ?? null;
    }

    /**
     * Inserts a row and returns its id. The API validates the whole form, so build the row from an
     * existing record or the wiki's full template; Y-m-d dates are sent as d/m/Y.
     *
     * Files go in their form fields, such as ['local_arquivo' => Attachment::fromPath(...)]; with
     * files, the row is sent as multipart form data, the only way the API takes them.
     *
     * @param array<string, mixed> $row
     * @param array<string, Attachment> $files
     * @throws IxcException
     */
    public function create(array $row, array $files = []): string
    {
        if ($files === []) {
            $response = $this->transport->send('POST', $this->name, [], $this->encoder->encode($row));
        } else {
            $body = new MultipartBody($this->encoder->values($row), $files);
            $response = $this->transport->send('POST', $this->name, [], $body->body(), $body->contentType());
        }
        $answer = $this->success($response);
        $id = $answer['id'] ?? null;
        if (!is_scalar($id) || (string) $id === '') {
            throw new UnexpectedResponseException(
                'The insert answer has no id.',
                $response->getStatusCode(),
                (string) $response->getBody()
            );
        }

        return (string) $id;
    }

    /**
     * Changes some fields of a record: reads it, applies the changes and writes the whole row back,
     * since the API validates every field. Two requests.
     *
     * @param array<string, mixed> $changes
     * @throws NotFoundException when the record does not exist (the API itself would answer success)
     * @throws IxcException
     */
    public function update(string $id, array $changes): void
    {
        $row = $this->find($id);
        if ($row === null) {
            throw new NotFoundException(sprintf('There is no %s with id %s.', $this->name, $id));
        }
        unset($row['id']);

        $this->replace($id, array_merge($row, $changes));
    }

    /**
     * Writes a whole row over a record, as given (dates converted). A missing field is saved blank, and a
     * missing id is answered with success without changing anything; update() avoids both.
     *
     * @param array<string, mixed> $row
     * @throws IxcException
     */
    public function replace(string $id, array $row): void
    {
        $path = $this->name . '/' . self::id($id);
        $this->success($this->transport->send('PUT', $path, [], $this->encoder->encode($row)));
    }

    /**
     * The PATCH route. The API validates it like PUT, so it takes the whole row as well: it is
     * replace() under another method, for callers that need PATCH itself.
     *
     * @param array<string, mixed> $row
     * @throws IxcException
     */
    public function patch(string $id, array $row): void
    {
        $path = $this->name . '/' . self::id($id);
        $this->success($this->transport->send('PATCH', $path, [], $this->encoder->encode($row)));
    }

    /**
     * Deletes a record. The API answers success even when the id does not exist.
     *
     * @throws IxcException
     */
    public function delete(string $id): void
    {
        $this->success($this->transport->send('DELETE', $this->name . '/' . self::id($id)));
    }

    /**
     * @return array<mixed>
     */
    private function success(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);
        if (!is_array($decoded) || ($decoded['type'] ?? null) !== 'success') {
            throw new UnexpectedResponseException(
                'The answer is not {"type": "success"}.',
                $response->getStatusCode(),
                $body
            );
        }

        return $decoded;
    }

    private static function id(string $id): string
    {
        if (preg_match('/^[1-9]\d*$/', $id) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid id "%s".', $id));
        }

        return $id;
    }
}
