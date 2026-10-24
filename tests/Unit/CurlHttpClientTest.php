<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tests\Unit;

use Marcolin\IxcProvedor\Http\CurlException;
use Marcolin\IxcProvedor\Http\CurlHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\NetworkExceptionInterface;

final class CurlHttpClientTest extends TestCase
{
    public function testVerifiesTheCertificateByDefault(): void
    {
        $options = $this->client()->options(new Request('POST', 'https://ixc.example.com/webservice/v1/cliente'));

        self::assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        self::assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
    }

    public function testAcceptsASelfSignedCertificateWhenAsked(): void
    {
        $options = $this->client(true)->options(new Request('POST', 'https://ixc.example.com/webservice/v1/cliente'));

        self::assertFalse($options[CURLOPT_SSL_VERIFYPEER]);
        self::assertSame(0, $options[CURLOPT_SSL_VERIFYHOST]);
    }

    public function testSendsTheMethodHeadersAndBodyAsGiven(): void
    {
        $request = new Request(
            'PUT',
            'https://ixc.example.com/webservice/v1/cliente/2',
            ['Authorization' => 'Basic eA==', 'Content-Type' => 'application/json'],
            '{"obs":"x"}'
        );

        $options = $this->client()->options($request);

        self::assertSame('https://ixc.example.com/webservice/v1/cliente/2', $options[CURLOPT_URL]);
        self::assertSame('PUT', $options[CURLOPT_CUSTOMREQUEST]);
        self::assertSame('{"obs":"x"}', $options[CURLOPT_POSTFIELDS]);
        $headers = $options[CURLOPT_HTTPHEADER];
        self::assertIsArray($headers);
        self::assertContains('Authorization: Basic eA==', $headers);
        self::assertContains('Content-Type: application/json', $headers);
        self::assertContains('Expect:', $headers);
        self::assertFalse($options[CURLOPT_FOLLOWLOCATION]);
    }

    public function testSendsNoBodyWhenThereIsNone(): void
    {
        $options = $this->client()->options(new Request('DELETE', 'https://ixc.example.com/webservice/v1/cliente/2'));

        self::assertArrayNotHasKey(CURLOPT_POSTFIELDS, $options);
    }

    public function testKeepsOnlyTheHeadersOfTheLastResponse(): void
    {
        $headers = CurlHttpClient::headers([
            "HTTP/1.1 100 Continue\r\n",
            "\r\n",
            "HTTP/1.1 200 OK\r\n",
            "Content-Type: text/x-json; charset=utf-8\r\n",
            "Set-Cookie: a=1\r\n",
            "Set-Cookie: b=2\r\n",
            "\r\n",
        ]);

        self::assertSame(
            ['Content-Type' => ['text/x-json; charset=utf-8'], 'Set-Cookie' => ['a=1', 'b=2']],
            $headers
        );
    }

    public function testReportsAFailedConnectionAsANetworkException(): void
    {
        // Port 1 on the loopback refuses at once; nothing leaves the machine.
        $request = new Request('POST', 'http://127.0.0.1:1/webservice/v1/cliente');

        try {
            (new CurlHttpClient(new Psr17Factory(), new Psr17Factory(), false, 5, 2))->sendRequest($request);
            self::fail('Expected a CurlException.');
        } catch (CurlException $exception) {
            self::assertInstanceOf(NetworkExceptionInterface::class, $exception);
            self::assertSame($request, $exception->getRequest());
            self::assertNotSame('', $exception->getMessage());
        }
    }

    private function client(bool $selfSigned = false): CurlHttpClient
    {
        return new CurlHttpClient(new Psr17Factory(), new Psr17Factory(), $selfSigned);
    }
}
