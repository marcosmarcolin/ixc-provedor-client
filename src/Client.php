<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;

/**
 * Entry point: one IXC Provedor instance, its routes (forms) and its resources (actions).
 */
final class Client
{
    private Transport $transport;

    public function __construct(Transport $transport)
    {
        $this->transport = $transport;
    }

    /**
     * Builds a client for an instance. Without $httpClient, an installed PSR-18 client is discovered;
     * the PSR-17 factories always are. To accept a self-signed certificate, pass a client configured
     * for it. For full control over the factories, build a Transport and use the constructor.
     *
     * @param string $host the instance's address, such as "https://ixc.example.com", without "/webservice/v1"
     * @param string $token the webservice token, "<user id>:<secret>"
     */
    public static function create(
        string $host,
        string $token,
        ?ClientInterface $httpClient = null,
        ?ApiVersion $version = null
    ): self {
        return new self(new Transport(
            $httpClient ?? Psr18ClientDiscovery::find(),
            Psr17FactoryDiscovery::findRequestFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
            $host,
            Credentials::fromToken($token),
            $version
        ));
    }

    public function route(string $name): Route
    {
        return new Route($this->transport, $name);
    }

    public function resource(string $name): Resource
    {
        return new Resource($this->transport, $name);
    }
}
