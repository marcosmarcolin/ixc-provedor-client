<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tests\Unit;

use Http\Mock\Client as MockClient;
use InvalidArgumentException;
use Marcolin\IxcProvedor\Credentials;
use Marcolin\IxcProvedor\Exception\ApiErrorException;
use Marcolin\IxcProvedor\Resource;
use Marcolin\IxcProvedor\Tests\Support\Fixture;
use Marcolin\IxcProvedor\Transport;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class ResourceTest extends TestCase
{
    private MockClient $http;

    protected function setUp(): void
    {
        $this->http = new MockClient();
    }

    public function testListCallsTheResourceLikeTheWikisGet(): void
    {
        $this->http->addResponse(Fixture::response('resource_contract_status_missing'));

        $response = $this->resource('cliente_contrato_15464')->list(['id_contrato' => '2806']);

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            'https://ixc.example.com/webservice/v1/cliente_contrato_15464',
            (string) $request->getUri()
        );
        self::assertSame('listar', $request->getHeaderLine('ixcsoft'));
        self::assertSame('{"id_contrato":"2806"}', (string) $request->getBody());
        self::assertSame(200, $response->status());
    }

    public function testPostCallsTheResourceWithoutTheListarHeader(): void
    {
        $this->http->addResponse(Fixture::response('resource_protocol_post'));

        $this->resource('gerar_protocolo_atendimento')->post();

        self::assertFalse($this->lastRequest()->hasHeader('ixcsoft'));
    }

    public function testSendsNoParametersAsAnEmptyObject(): void
    {
        $this->http->addResponse(Fixture::response('resource_protocol_listar'));

        $protocol = $this->resource('gerar_protocolo_atendimento')->list()->json();

        self::assertSame('{}', (string) $this->lastRequest()->getBody());
        self::assertIsString($protocol);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function resourceErrors(): iterable
    {
        yield 'trust unlock of a missing contract' => [
            'desbloqueio_confianca',
            'resource_trust_unlock_missing',
            'ID de contrato inexistente na base de dados.',
        ];
        yield 'closing an order without its fields' => [
            'su_oss_chamado_fechar',
            'resource_close_order_missing',
            'Preencha Colaborador responsável',
        ];
        yield 'unknown resource' => [
            'tabela_inexistente',
            'error_unknown_resource',
            'Recurso tabela_inexistente não está disponível!',
        ];
    }

    /**
     * @dataProvider resourceErrors
     */
    public function testRaisesTheResourcesErrors(string $name, string $fixture, string $message): void
    {
        $this->http->addResponse(Fixture::response($fixture));

        $this->expectException(ApiErrorException::class);
        $this->expectExceptionMessage($message);

        $this->resource($name)->post(['id' => '999999999']);
    }

    public function testAcceptsNumberedNames(): void
    {
        $this->http->addResponse(Fixture::response('resource_protocol_post'));

        self::assertSame('radusuarios_25452', $this->resource('radusuarios_25452')->name());
        self::assertSame('cliente_contrato_15464', $this->resource('cliente_contrato_15464')->name());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidNames(): iterable
    {
        yield 'empty' => [''];
        yield 'with a path' => ['cliente/1'];
        yield 'with a dot' => ['cliente.id'];
    }

    /**
     * @dataProvider invalidNames
     */
    public function testRejectsInvalidNames(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resource($name);
    }

    public function testRejectsParametersThatAreNotJson(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->resource('get_boleto')->list(['boletos' => "\xB1\x31"]);
    }

    private function resource(string $name): Resource
    {
        $factory = new Psr17Factory();
        $transport = new Transport(
            $this->http,
            $factory,
            $factory,
            'https://ixc.example.com',
            Credentials::fromToken('6:not-a-real-secret')
        );

        return new Resource($transport, $name);
    }

    private function lastRequest(): RequestInterface
    {
        $request = $this->http->getLastRequest();
        self::assertInstanceOf(RequestInterface::class, $request);

        return $request;
    }
}
