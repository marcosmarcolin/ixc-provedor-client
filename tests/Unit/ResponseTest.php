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
}
