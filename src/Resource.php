<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor;

use InvalidArgumentException;
use JsonException;
use Marcolin\IxcProvedor\Exception\IxcException;

/**
 * An API resource ("Recursos da API"): a named action with its own parameters and answer format.
 *
 * Names are free-form (cliente_contrato_15464, radusuarios_25452), so any name is accepted; the
 * parameters are the ones the wiki documents for that resource, sent as given.
 */
final class Resource
{
    private Transport $transport;

    private string $name;

    public function __construct(Transport $transport, string $name)
    {
        if (preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid resource name "%s".', $name));
        }
        $this->transport = $transport;
        $this->name = $name;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * Calls the resource the way the wiki's $api->get() does: POST with "ixcsoft: listar".
     *
     * @param array<string, mixed> $parameters
     * @throws IxcException
     */
    public function list(array $parameters = []): Response
    {
        return $this->call(['ixcsoft' => 'listar'], $parameters);
    }

    /**
     * Calls the resource the way the wiki's $api->post() does: a plain POST.
     *
     * @param array<string, mixed> $parameters
     * @throws IxcException
     */
    public function post(array $parameters = []): Response
    {
        return $this->call([], $parameters);
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $parameters
     */
    private function call(array $headers, array $parameters): Response
    {
        try {
            // An empty list must still be a JSON object.
            $body = json_encode($parameters === [] ? new \stdClass() : $parameters, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('The parameters cannot be encoded as JSON: ' . $exception->getMessage());
        }

        return Response::fromPsr($this->transport->send('POST', $this->name, $headers, $body));
    }
}
