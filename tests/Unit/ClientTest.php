<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tests\Unit;

use Http\Discovery\ClassDiscovery;
use Http\Discovery\Exception\NotFoundException;
use Http\Discovery\Strategy\MockClientStrategy;
use Http\Mock\Client as MockClient;
use InvalidArgumentException;
use Marcolin\IxcProvedor\Client;
use Marcolin\IxcProvedor\Tests\Support\Fixture;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class ClientTest extends TestCase
{
    /** @var array<string> */
    private array $strategies = [];

    protected function setUp(): void
    {
        foreach (ClassDiscovery::getStrategies() as $strategy) {
            $this->strategies[] = $strategy;
        }
    }

    protected function tearDown(): void
    {
        // Discovery keeps its strategies in static state; restore it for the other tests.
        ClassDiscovery::setStrategies($this->strategies);
        ClassDiscovery::clearCache();
    }

    public function testRoutesGoThroughTheGivenHttpClient(): void
    {
        $http = new MockClient();
        $http->addResponse(Fixture::response('list_page'));

        $page = Client::create('https://ixc.example.com', '6:not-a-real-secret', $http)->route('cliente')->list();

        $request = $http->getLastRequest();
        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('https://ixc.example.com/webservice/v1/cliente', (string) $request->getUri());
        self::assertSame('Basic ' . base64_encode('6:not-a-real-secret'), $request->getHeaderLine('Authorization'));
        self::assertSame(1, $page->total());
    }

    public function testResourcesGoThroughTheGivenHttpClient(): void
    {
        $http = new MockClient();
        $http->addResponse(Fixture::response('resource_protocol_listar'));

        $protocol = Client::create('https://ixc.example.com', '6:not-a-real-secret', $http)
            ->resource('gerar_protocolo_atendimento')
            ->list()
            ->json();

        self::assertIsString($protocol);
        $request = $http->getLastRequest();
        self::assertInstanceOf(RequestInterface::class, $request);
        self::assertSame('listar', $request->getHeaderLine('ixcsoft'));
    }

    public function testDiscoversAnInstalledHttpClientWhenNoneIsGiven(): void
    {
        ClassDiscovery::prependStrategy(MockClientStrategy::class);

        // Discovery builds its own mock, whose default answer is an empty 200.
        $response = Client::create('https://ixc.example.com', '6:not-a-real-secret')
            ->resource('gerar_protocolo_atendimento')
            ->post();

        self::assertSame(200, $response->status());
        self::assertTrue($response->isEmpty());
    }

    public function testExplainsThatAnHttpClientIsMissing(): void
    {
        ClassDiscovery::setStrategies([]);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('psr/http-client-implementation');

        Client::create('https://ixc.example.com', '6:not-a-real-secret');
    }

    public function testRejectsAMalformedTokenWithoutEchoingIt(): void
    {
        try {
            Client::create('https://ixc.example.com', 'secret-without-user-id', new MockClient());
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringNotContainsString('secret-without-user-id', $exception->getMessage());
        }
    }

    public function testRejectsAHostWithTheWebservicePath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Client::create('https://ixc.example.com/webservice/v1', '6:not-a-real-secret', new MockClient());
    }
}
