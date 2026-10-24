<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor;

use Http\Discovery\Psr17FactoryDiscovery;
use Marcolin\IxcProvedor\Http\CurlHttpClient;
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
     * Builds a client on the built-in HTTP client (ext-curl), which accepts self-signed certificates
     * unless $tls says otherwise. The PSR-17 factories are discovered.
     *
     * @param string $host the instance's address, such as "https://ixc.example.com", without "/webservice/v1"
     * @param string $token the webservice token, "<user id>:<secret>"
     * @param Tls|null $tls Tls::acceptSelfSigned() by default; Tls::verifyWith($pem) pins a certificate
     */
    public static function create(string $host, string $token, ?Tls $tls = null, ?ApiVersion $version = null): self
    {
        $http = new CurlHttpClient(
            Psr17FactoryDiscovery::findResponseFactory(),
            Psr17FactoryDiscovery::findStreamFactory(),
            $tls
        );

        return self::withHttpClient($host, $token, $http, $version);
    }

    /**
     * Builds a client on any PSR-18 client, such as Guzzle; its own settings decide TLS, timeouts and
     * proxies. The PSR-17 factories are discovered.
     */
    public static function withHttpClient(
        string $host,
        string $token,
        ClientInterface $httpClient,
        ?ApiVersion $version = null
    ): self {
        return new self(new Transport(
            $httpClient,
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
