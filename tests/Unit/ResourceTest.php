<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tests\Unit;

use Http\Mock\Client as MockClient;
use InvalidArgumentException;
use Marcolin\IxcProvedor\Credentials;
use Marcolin\IxcProvedor\Exception\ApiErrorException;
use Marcolin\IxcProvedor\Resource;
use Marcolin\IxcProvedor\Response;
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

    /**
     * Resources whose answer is an error: the business rule in the message reaches the caller.
     *
     * @return iterable<string, array{string, string, string}>
     */
    public static function businessErrors(): iterable
    {
        yield 'access status of an active contract' => [
            'cliente_contrato_15464',
            'resource_contract_status_active',
            "Não é possível prossegui, quando o status de acesso já está 'Ativo'.",
        ];
        yield 'trust unlock of an active contract' => [
            'desbloqueio_confianca',
            'resource_trust_unlock_active',
            'quando o contrato estiver ativo.',
        ];
        yield 'speed release without its parameters' => [
            'liberacao_reducao_contrato_29157',
            'resource_speed_release',
            'menu de Parâmetros',
        ];
        yield 'ticket transfer before taking it' => [
            'su_transf_atendimento_26466',
            'resource_transfer_ticket',
            'Para transferir, assuma o atendimento.',
        ];
        yield 'comodato without its fields' => [
            'su_oss_mov_comodato_wiz_novo',
            'resource_comodato_incomplete',
            'Preencha fator_conversao',
        ];
    }

    /**
     * @dataProvider businessErrors
     */
    public function testBusinessRuleErrorsBecomeExceptions(string $name, string $fixture, string $message): void
    {
        $this->http->addResponse(Fixture::response($fixture));

        $this->expectException(ApiErrorException::class);
        $this->expectExceptionMessage($message);

        $this->resource($name)->list();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function successes(): iterable
    {
        yield 'activate a contract' => ['cliente_contrato_ativar_cliente', 'resource_activate_contract'];
        yield 'clear a login MAC' => ['radusuarios_25452', 'resource_clear_mac'];
        yield 'analyze an order' => ['su_oss_chamado_analisar', 'resource_analyze_order'];
        yield 'forward an order' => ['su_oss_chamado_alterar_setor', 'resource_forward_order'];
        yield 'close an order' => ['su_oss_chamado_fechar', 'resource_close_order'];
        yield 'receive an invoice' => ['fn_areceber_recebimentos_baixas_novo', 'resource_receive_invoice'];
    }

    /**
     * @dataProvider successes
     */
    public function testSuccessesComeBackAsJson(string $name, string $fixture): void
    {
        $this->http->addResponse(Fixture::response($fixture));

        $json = $this->resource($name)->post()->json();

        self::assertIsArray($json);
        self::assertSame('success', $json['type']);
    }

    public function testABoletoComesAsDataBase64OrRawPdf(): void
    {
        $this->http->addResponse(Fixture::response('resource_boleto_data'));
        $this->http->addResponse(Fixture::response('resource_boleto_base64'));
        $this->http->addResponse(Fixture::response('resource_boleto_binary'));
        $boleto = $this->resource('get_boleto');

        $data = $boleto->list(['tipo_boleto' => 'dados'])->json();
        $base64 = $boleto->list(['tipo_boleto' => 'arquivo', 'base64' => 'S'])->body();
        $binary = $boleto->list(['tipo_boleto' => 'arquivo', 'base64' => 'N']);

        self::assertIsArray($data);
        self::assertIsArray($data[0]);
        self::assertArrayHasKey('cedente_nome', $data[0]);
        self::assertStringStartsWith('%PDF-', base64_decode($base64));
        self::assertStringStartsWith('%PDF-', $binary->body());
        self::assertSame('application/pdf', $binary->header('Content-Type'));
    }

    /**
     * Answers that are neither {"type": "success"} nor {"type": "error"}: they pass through untouched,
     * so each resource's own format is read by the caller.
     *
     * @return iterable<string, array{string, string, callable(Response): void}>
     */
    public static function ownFormats(): iterable
    {
        yield 'temporary release answers null' => ['cliente_contrato_btn_lib_temp_24722', 'resource_temporary_release',
            static function (Response $response): void {
                self::assertNull($response->json());
            }];
        yield 'viability answers success false' => ['viabilidade_tecnica', 'resource_viability',
            static function (Response $response): void {
                $json = $response->json();
                self::assertIsArray($json);
                self::assertFalse($json['success']);
            }];
        yield 'disconnect nests its error in msg' => ['desconectar_clientes', 'resource_disconnect_login',
            static function (Response $response): void {
                $json = $response->json();
                self::assertIsArray($json);
                self::assertArrayNotHasKey('type', $json);
                self::assertIsArray($json['msg']);
                self::assertIsArray($json['msg'][0]);
                self::assertSame('error', $json['msg'][0]['type']);
            }];
        yield 'boleto by mail answers registros' => ['get_boleto', 'resource_boleto_mail',
            static function (Response $response): void {
                self::assertSame(['registros' => [['sucesso' => true]]], $response->json());
            }];
        yield 'boleto by sms answers nothing' => ['get_boleto', 'resource_boleto_sms',
            static function (Response $response): void {
                self::assertTrue($response->isEmpty());
            }];
        yield 'printing a missing invoice answers latin-1 html' => ['imprimir_nota', 'resource_print_invoice_missing',
            static function (Response $response): void {
                self::assertStringContainsString('<div', $response->body());
                self::assertStringContainsString('text/html', $response->header('Content-Type'));
            }];
    }

    /**
     * @dataProvider ownFormats
     * @param callable(Response): void $check
     */
    public function testOtherAnswersPassThroughInTheirOwnFormat(string $name, string $fixture, callable $check): void
    {
        $this->http->addResponse(Fixture::response($fixture));

        $check($this->resource($name)->list());
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
