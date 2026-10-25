<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tests\Unit;

use Marcolin\IxcProvedor\Exception\UnexpectedResponseException;
use Marcolin\IxcProvedor\Response;
use Marcolin\IxcProvedor\Tests\Support\Fixture;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testDecodesAProtocolSentAsAJsonString(): void
    {
        $response = Response::fromPsr(Fixture::response('resource_protocol_listar'));

        self::assertSame(200, $response->status());
        $protocol = $response->json();
        self::assertIsString($protocol);
        self::assertMatchesRegularExpression('/^\d{20}$/', $protocol);
    }

    public function testDecodesAnObjectAsAnArray(): void
    {
        $json = Response::fromPsr(Fixture::response('resource_contract_status_missing'))->json();

        self::assertIsArray($json);
        self::assertSame('success', $json['type']);
    }

    public function testKeepsBigIntegersAsStrings(): void
    {
        self::assertSame('20261024162331914000', (new Response(200, [], '20261024162331914000'))->json());
    }

    public function testAnEmptyBodyIsNotJson(): void
    {
        $response = Response::fromPsr(Fixture::response('resource_boleto_missing'));

        self::assertTrue($response->isEmpty());
        try {
            $response->json();
            self::fail('Expected an UnexpectedResponseException.');
        } catch (UnexpectedResponseException $exception) {
            self::assertSame('The answer is empty.', $exception->getMessage());
        }
    }

    public function testRawContentIsNotJson(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('The answer is not JSON.');

        (new Response(200, [], 'JVBERi0xLjQK'))->json();
    }

    public function testReadsHeadersWithoutCase(): void
    {
        $response = Response::fromPsr(Fixture::response('resource_boleto_missing'));

        self::assertSame('text/html; charset=ISO-8859-1', $response->header('Content-Type'));
        self::assertSame('', $response->header('X-Missing'));
    }

    public function testGivesTheSuccessMessageOfAResource(): void
    {
        $response = Response::fromPsr(Fixture::response('resource_activate_contract'));

        self::assertSame('Contrato ativado com sucesso!', $response->message());
        self::assertSame(['Contrato ativado com sucesso!'], $response->messages());
    }

    public function testSplitsSeveralMessages(): void
    {
        $response = Response::fromPsr(Fixture::response('resource_clear_mac'));

        self::assertSame(
            ['MAC removido com sucesso para o(s) usuário(s) com ID: 2', 'Ação executada com sucesso'],
            $response->messages()
        );
        self::assertSame(implode('; ', $response->messages()), $response->message());
    }

    public function testDropsTheTrailingLineBreak(): void
    {
        $response = Response::fromPsr(Fixture::response('resource_receive_invoice'));

        self::assertSame(['Registro inserido com sucesso!'], $response->messages());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function answersWithoutMessage(): iterable
    {
        yield 'protocol string' => ['resource_protocol_listar'];
        yield 'pdf' => ['resource_boleto_binary'];
        yield 'empty body' => ['resource_boleto_missing'];
        yield 'object without message' => ['resource_viability'];
    }

    /**
     * @dataProvider answersWithoutMessage
     */
    public function testHasNoMessageWhenTheBodyHasNone(string $fixture): void
    {
        $response = Response::fromPsr(Fixture::response($fixture));

        self::assertSame([], $response->messages());
        self::assertSame('', $response->message());
    }

    public function testRepairsADoubleEncodedMessage(): void
    {
        $body = '{"type":"success","message":"Opera\\u00c3\\u00a7\\u00c3\\u00a3o conclu\\u00c3\\u00adda"}';
        $response = new Response(200, [], $body);

        self::assertSame('Operação concluída', $response->message());
    }
}
