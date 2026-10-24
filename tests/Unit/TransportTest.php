<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tests\Unit;

use Http\Client\Exception\NetworkException;
use Http\Mock\Client as MockClient;
use InvalidArgumentException;
use Marcolin\IxcProvedor\ApiVersion;
use Marcolin\IxcProvedor\Credentials;
use Marcolin\IxcProvedor\Exception\ApiErrorException;
use Marcolin\IxcProvedor\Exception\AuthenticationException;
use Marcolin\IxcProvedor\Exception\IxcException;
use Marcolin\IxcProvedor\Exception\TransportException;
use Marcolin\IxcProvedor\Tests\Support\Fixture;
use Marcolin\IxcProvedor\Transport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class TransportTest extends TestCase
{
    private const SECRET = 'not-a-real-secret';

    private MockClient $http;

    protected function setUp(): void
    {
        $this->http = new MockClient();
    }

    public function testSendsAnAuthenticatedListingRequestToTheVersionedRoute(): void
    {
        $this->http->addResponse(Fixture::response('list_page'));

        $this->transport('https://ixc.example.com/')->send('POST', 'cliente', ['ixcsoft' => 'listar'], '{"rp":"2"}');

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://ixc.example.com/webservice/v1/cliente', (string) $request->getUri());
        self::assertSame('Basic ' . base64_encode('6:' . self::SECRET), $request->getHeaderLine('Authorization'));
        self::assertSame('listar', $request->getHeaderLine('ixcsoft'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('{"rp":"2"}', (string) $request->getBody());
    }

    public function testSendsNoBodyOrContentTypeWithoutABody(): void
    {
        $this->http->addResponse(Fixture::response('delete'));

        $this->transport()->send('DELETE', 'cliente/2');

        $request = $this->lastRequest();
        self::assertSame('https://ixc.example.com/webservice/v1/cliente/2', (string) $request->getUri());
        self::assertFalse($request->hasHeader('Content-Type'));
        self::assertSame('', (string) $request->getBody());
    }

    public function testKeepsThePortOfTheHost(): void
    {
        $this->http->addResponse(Fixture::response('list_page'));

        $this->transport('http://10.0.0.5:8080', ApiVersion::v1())->send('POST', 'cliente');

        self::assertSame('http://10.0.0.5:8080/webservice/v1/cliente', (string) $this->lastRequest()->getUri());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function successfulAnswers(): iterable
    {
        yield 'listing' => ['list_page'];
        yield 'empty listing' => ['list_empty'];
        yield 'insert' => ['insert'];
        yield 'update' => ['update'];
        yield 'delete' => ['delete'];
        // Not an error envelope: deciding what a non-JSON body means is up to the caller.
        yield 'html from a server failure' => ['list_invalid_field'];
    }

    /**
     * @dataProvider successfulAnswers
     */
    public function testReturnsAnswersThatAreNotErrors(string $fixture): void
    {
        $this->http->addResponse(Fixture::response($fixture));

        $response = $this->transport()->send('POST', 'cliente');

        self::assertSame((string) Fixture::response($fixture)->getBody(), (string) $response->getBody());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function errorAnswers(): iterable
    {
        yield 'unknown resource' => ['error_unknown_resource', 'Recurso tabela_inexistente não está disponível!'];
        yield 'unknown version' => ['error_unknown_version', 'Versão v2 do webservice não está disponível!'];
        yield 'validation on update' => ['update_invalid', 'Preencha Razão social/nome'];
        yield 'partial patch' => ['patch_partial', 'CPF/CNPJ obrigatório!'];
    }

    /**
     * @dataProvider errorAnswers
     */
    public function testTurnsAnErrorBodyWithHttp200IntoAnException(string $fixture, string $message): void
    {
        $this->http->addResponse(Fixture::response($fixture));

        try {
            $this->transport()->send('POST', 'cliente');
            self::fail('Expected an ApiErrorException.');
        } catch (ApiErrorException $exception) {
            self::assertSame(200, $exception->statusCode());
            self::assertSame([$message], $exception->messages());
            self::assertSame((string) Fixture::response($fixture)->getBody(), $exception->responseBody());
            self::assertStringContainsString($message, $exception->getMessage());
        }
    }

    public function testSplitsSeveralValidationErrors(): void
    {
        $this->http->addResponse(Fixture::response('insert_invalid'));

        try {
            $this->transport()->send('POST', 'cliente', [], '{"ativo":"S"}');
            self::fail('Expected an ApiErrorException.');
        } catch (ApiErrorException $exception) {
            self::assertStringContainsString('<br />', $exception->apiMessage());
            self::assertContains('Preencha o campo Telefone Celular', $exception->messages());
            self::assertContains('CPF/CNPJ obrigatório!', $exception->messages());
            self::assertGreaterThan(10, count($exception->messages()));
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function rejectedCredentials(): iterable
    {
        yield 'wrong secret' => ['error_wrong_secret'];
        yield 'unknown user' => ['error_unknown_user'];
    }

    /**
     * @dataProvider rejectedCredentials
     */
    public function testTurnsHttp401IntoAnAuthenticationExceptionWithoutTheToken(string $fixture): void
    {
        $this->http->addResponse(Fixture::response($fixture));

        try {
            $this->transport()->send('POST', 'cliente');
            self::fail('Expected an AuthenticationException.');
        } catch (AuthenticationException $exception) {
            self::assertInstanceOf(IxcException::class, $exception);
            self::assertStringNotContainsString(self::SECRET, $exception->getMessage());
            self::assertStringNotContainsString(base64_encode('6:' . self::SECRET), $exception->getMessage());
        }
    }

    public function testTurnsAnHttpErrorStatusIntoAnExceptionWithTheApiMessage(): void
    {
        // Shape taken from the server's blocked-method answer (rotas.php); not captured yet.
        $this->http->addResponse(new Response(
            405,
            ['Allow' => 'POST, GET', 'Content-Type' => 'text/html; charset=ISO-8859-1'],
            '{"type":"error","message":"Recurso chave_integrador não permite deletar registro(s)!"}'
        ));

        $this->expectException(ApiErrorException::class);
        $this->expectExceptionMessage('(HTTP 405): Recurso chave_integrador não permite deletar registro(s)!');

        $this->transport()->send('DELETE', 'chave_integrador/1');
    }

    public function testFallsBackToTheReasonPhraseWhenAnHttpErrorHasNoJson(): void
    {
        $this->http->addResponse(new Response(502, [], '<html>Bad Gateway</html>'));

        try {
            $this->transport()->send('POST', 'cliente');
            self::fail('Expected an ApiErrorException.');
        } catch (ApiErrorException $exception) {
            self::assertSame(502, $exception->statusCode());
            self::assertSame(['Bad Gateway'], $exception->messages());
        }
    }

    public function testWrapsClientFailuresWithoutTheToken(): void
    {
        $this->http->addException(new NetworkException('Connection refused', new Request('POST', 'https://x')));

        try {
            $this->transport()->send('POST', 'cliente');
            self::fail('Expected a TransportException.');
        } catch (TransportException $exception) {
            self::assertSame('POST cliente got no answer: Connection refused', $exception->getMessage());
            self::assertInstanceOf(NetworkException::class, $exception->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidHosts(): iterable
    {
        yield 'wiki-style host with the path' => ['https://ixc.example.com/webservice/v1'];
        yield 'no scheme' => ['ixc.example.com'];
        yield 'other scheme' => ['ftp://ixc.example.com'];
        yield 'credentials' => ['https://6:secret@ixc.example.com'];
        yield 'query' => ['https://ixc.example.com?a=1'];
        yield 'empty' => [''];
    }

    /**
     * @dataProvider invalidHosts
     */
    public function testRejectsInvalidHostsWithoutEchoingThem(string $host): void
    {
        try {
            $this->transport($host);
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringNotContainsString('secret', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRoutes(): iterable
    {
        yield 'empty' => [''];
        yield 'leading slash' => ['/cliente'];
        yield 'parent directory' => ['../cliente'];
        yield 'query string' => ['cliente?x=1'];
        yield 'trailing slash' => ['cliente/'];
    }

    /**
     * @dataProvider invalidRoutes
     */
    public function testRejectsInvalidRoutes(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->transport()->send('POST', $path);
    }

    public function testAcceptsResourceNamesAndIdLists(): void
    {
        $this->http->addResponse(Fixture::response('delete'));
        $this->http->addResponse(Fixture::response('delete'));

        $this->transport()->send('POST', 'botaoAjax_28509');
        $this->transport()->send('DELETE', 'cliente/1,2,3');

        $uri = (string) $this->lastRequest()->getUri();
        self::assertSame('https://ixc.example.com/webservice/v1/cliente/1,2,3', $uri);
    }

    private function transport(string $host = 'https://ixc.example.com', ?ApiVersion $version = null): Transport
    {
        $factory = new Psr17Factory();

        $credentials = Credentials::fromToken('6:' . self::SECRET);

        return new Transport($this->http, $factory, $factory, $host, $credentials, $version);
    }

    private function lastRequest(): RequestInterface
    {
        $request = $this->http->getLastRequest();
        self::assertInstanceOf(RequestInterface::class, $request);

        return $request;
    }
}
