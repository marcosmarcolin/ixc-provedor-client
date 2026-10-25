<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tests\Unit;

use Http\Mock\Client as MockClient;
use InvalidArgumentException;
use Marcolin\IxcProvedor\Attachment;
use Marcolin\IxcProvedor\Credentials;
use Marcolin\IxcProvedor\Exception\ApiErrorException;
use Marcolin\IxcProvedor\Exception\NotFoundException;
use Marcolin\IxcProvedor\Exception\UnexpectedResponseException;
use Marcolin\IxcProvedor\Query;
use Marcolin\IxcProvedor\Route;
use Marcolin\IxcProvedor\Tests\Support\Fixture;
use Marcolin\IxcProvedor\Transport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class RouteTest extends TestCase
{
    private MockClient $http;

    private Route $route;

    protected function setUp(): void
    {
        $this->http = new MockClient();
        $this->route = new Route($this->transport(), 'cliente');
    }

    public function testListsWithTheListarHeaderAndTheQueryAsJson(): void
    {
        $this->http->addResponse(Fixture::response('list_page'));

        $page = $this->route->list(Query::where('ativo', '=', 'S')->withPerPage(2));

        $request = $this->request(0);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://ixc.example.com/webservice/v1/cliente', (string) $request->getUri());
        self::assertSame('listar', $request->getHeaderLine('ixcsoft'));
        self::assertSame(
            ['qtype' => 'cliente.ativo', 'query' => 'S', 'oper' => '=', 'page' => '1', 'rp' => '2',
                'sortname' => 'cliente.ativo', 'sortorder' => 'asc'],
            $this->body($request)
        );
        self::assertSame(1, $page->total());
    }

    public function testListsEverythingWithoutAQuery(): void
    {
        $this->http->addResponse(Fixture::response('list_empty'));

        self::assertTrue($this->route->list()->isEmpty());
        self::assertSame(Query::all()->toParameters('cliente'), $this->body($this->request(0)));
    }

    public function testEachWalksThePagesUntilAShortOne(): void
    {
        $this->http->addResponse($this->listing(3, ['1', '2']));
        $this->http->addResponse($this->listing(3, ['3']));

        $ids = [];
        foreach ($this->route->each(Query::all()->withPerPage(2)) as $row) {
            $ids[] = $row['id'];
        }

        self::assertSame(['1', '2', '3'], $ids);
        self::assertCount(2, $this->http->getRequests());
        self::assertSame('2', $this->body($this->request(1))['page']);
    }

    public function testEachStopsWhenTheTotalIsReached(): void
    {
        $this->http->addResponse($this->listing(2, ['1', '2']));

        self::assertCount(2, iterator_to_array($this->route->each(Query::all()->withPerPage(2)), false));
        self::assertCount(1, $this->http->getRequests());
    }

    public function testEachOfAnEmptyListingYieldsNothing(): void
    {
        $this->http->addResponse(Fixture::response('list_empty'));

        self::assertSame([], iterator_to_array($this->route->each(), false));
    }

    public function testFindsOneRecordById(): void
    {
        $this->http->addResponse(Fixture::response('find'));

        $row = $this->route->find('2');

        self::assertNotNull($row);
        self::assertArrayHasKey('razao', $row);
        $body = $this->body($this->request(0));
        self::assertSame(['cliente.id', '=', '2', '1'], [$body['qtype'], $body['oper'], $body['query'], $body['rp']]);
    }

    public function testFindReturnsNullWhenThereIsNoSuchRecord(): void
    {
        $this->http->addResponse(Fixture::response('list_empty'));

        self::assertNull($this->route->find('999'));
    }

    public function testCreateSendsTheRowWithWriteDatesAndReturnsTheId(): void
    {
        $this->http->addResponse(Fixture::response('insert'));

        $id = $this->route->create(['razao' => 'Teste', 'data_nascimento' => '1990-05-17']);

        $request = $this->request(0);
        self::assertSame('POST', $request->getMethod());
        self::assertFalse($request->hasHeader('ixcsoft'));
        self::assertSame(['razao' => 'Teste', 'data_nascimento' => '17/05/1990'], $this->body($request));
        self::assertMatchesRegularExpression('/^\d+$/', $id);
    }

    public function testCreateWithAFileSendsMultipartFormData(): void
    {
        $this->http->addResponse(Fixture::response('insert_with_file'));

        $id = (new Route($this->transport(), 'cliente_arquivos'))->create(
            ['id_cliente' => '10', 'descricao' => 'Contrato', 'data_envio' => '2026-10-24'],
            ['local_arquivo' => Attachment::fromContents('contrato.txt', 'conteudo', 'text/plain')]
        );

        $request = $this->request(0);
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://ixc.example.com/webservice/v1/cliente_arquivos', (string) $request->getUri());
        $contentType = $request->getHeaderLine('Content-Type');
        self::assertMatchesRegularExpression('/^multipart\/form-data; boundary=ixc-[0-9a-f]{32}$/', $contentType);
        $body = (string) $request->getBody();
        self::assertStringContainsString("name=\"descricao\"\r\n\r\nContrato\r\n", $body);
        self::assertStringContainsString("name=\"data_envio\"\r\n\r\n24/10/2026\r\n", $body);
        self::assertStringContainsString(
            "name=\"local_arquivo\"; filename=\"contrato.txt\"\r\nContent-Type: text/plain\r\n\r\nconteudo\r\n",
            $body
        );
        // The insert answer of cliente_arquivos has a numeric id, not a string.
        $answer = json_decode((string) Fixture::response('insert_with_file')->getBody(), true);
        self::assertIsArray($answer);
        self::assertIsInt($answer['id']);
        self::assertSame((string) $answer['id'], $id);
    }

    public function testCreateRaisesTheValidationErrors(): void
    {
        $this->http->addResponse(Fixture::response('insert_invalid'));

        $this->expectException(ApiErrorException::class);
        $this->expectExceptionMessage('CPF/CNPJ obrigatório!');

        $this->route->create(['ativo' => 'S']);
    }

    public function testCreateRejectsASuccessWithoutAnId(): void
    {
        $this->http->addResponse(new Response(200, [], '{"type":"success","message":"ok"}'));

        $this->expectException(UnexpectedResponseException::class);

        $this->route->create(['ativo' => 'S']);
    }

    public function testUpdateReadsTheRecordAndWritesItBackWholeWithTheChanges(): void
    {
        $this->http->addResponse(Fixture::response('find'));
        $this->http->addResponse(Fixture::response('update'));

        $this->route->update('2', ['obs' => 'alterado']);

        $put = $this->request(1);
        self::assertSame('PUT', $put->getMethod());
        self::assertSame('https://ixc.example.com/webservice/v1/cliente/2', (string) $put->getUri());
        $body = $this->body($put);
        self::assertSame('alterado', $body['obs']);
        self::assertArrayNotHasKey('id', $body);
        self::assertSame('01/01/2000', $body['data_nascimento']);
        self::assertGreaterThan(100, count($body));
    }

    public function testUpdateOfAMissingRecordThrowsInsteadOfTheApisSuccess(): void
    {
        $this->http->addResponse(Fixture::response('list_empty'));

        try {
            $this->route->update('999999999', ['obs' => 'x']);
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $exception) {
            self::assertCount(1, $this->http->getRequests());
        }
    }

    public function testReplaceWritesTheRowAsGiven(): void
    {
        $this->http->addResponse(Fixture::response('update_not_found'));

        $this->route->replace('999999999', ['razao' => 'Teste']);

        self::assertSame(['razao' => 'Teste'], $this->body($this->request(0)));
    }

    public function testPatchSendsTheWholeRowWithPatch(): void
    {
        $this->http->addResponse(Fixture::response('patch'));

        $this->route->patch('2', ['razao' => 'Teste', 'data_nascimento' => '1990-05-17']);

        $request = $this->request(0);
        self::assertSame('PATCH', $request->getMethod());
        self::assertSame('https://ixc.example.com/webservice/v1/cliente/2', (string) $request->getUri());
        self::assertSame(['razao' => 'Teste', 'data_nascimento' => '17/05/1990'], $this->body($request));
    }

    public function testPatchRaisesTheValidationOfAPartialRow(): void
    {
        $this->http->addResponse(Fixture::response('patch_partial'));

        $this->expectException(ApiErrorException::class);
        $this->expectExceptionMessage('CPF/CNPJ obrigatório!');

        $this->route->patch('2', ['obs' => 'somente obs']);
    }

    public function testPatchOfAMissingIdRaisesTheApiErrorInTipoValorFormat(): void
    {
        $this->http->addResponse(Fixture::response('patch_not_found'));

        $this->expectException(ApiErrorException::class);
        $this->expectExceptionMessage('Registro não encontrado!');

        $this->route->patch('999999999', ['obs' => 'teste']);
    }

    public function testDeleteSendsTheIdInThePath(): void
    {
        $this->http->addResponse(Fixture::response('delete'));
        $this->http->addResponse(Fixture::response('delete_not_found'));

        $this->route->delete('2');
        $this->route->delete('2');

        self::assertSame('DELETE', $this->request(0)->getMethod());
        self::assertSame('https://ixc.example.com/webservice/v1/cliente/2', (string) $this->request(1)->getUri());
    }

    public function testDeleteSendsSeveralIdsInOneRequest(): void
    {
        $this->http->addResponse(Fixture::response('delete_many'));

        $this->route->delete('18', '19');

        self::assertCount(1, $this->http->getRequests());
        self::assertSame('DELETE', $this->request(0)->getMethod());
        self::assertSame('https://ixc.example.com/webservice/v1/cliente/18,19', (string) $this->request(0)->getUri());
    }

    public function testDeleteRejectsAnInvalidIdAmongSeveral(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->route->delete('18', '19; DROP');
    }

    public function testDeleteOfAMissingIdRaisesTheErrorOfRoutesThatReportIt(): void
    {
        $this->http->addResponse(Fixture::response('delete_not_found_error'));

        $this->expectException(ApiErrorException::class);
        $this->expectExceptionMessage('Ocorreu um erro no servidor ao tentar excluir.');

        $this->route->delete('999999999');
    }

    public function testAWriteAnsweredWithoutSuccessIsUnexpected(): void
    {
        $this->http->addResponse(Fixture::response('list_invalid_field'));

        $this->expectException(UnexpectedResponseException::class);

        $this->route->delete('2');
    }

    public function testListAnsweredWithHtmlIsUnexpected(): void
    {
        $this->http->addResponse(Fixture::response('list_invalid_field'));

        $this->expectException(UnexpectedResponseException::class);

        $this->route->list();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIds(): iterable
    {
        yield 'empty' => [''];
        yield 'zero' => ['0'];
        yield 'negative' => ['-1'];
        yield 'list' => ['1,2'];
        yield 'path' => ['1/2'];
    }

    /**
     * @dataProvider invalidIds
     */
    public function testRejectsInvalidIds(string $id): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->route->delete($id);
    }

    public function testRejectsInvalidRouteNames(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Route($this->transport(), 'cliente/1');
    }

    private function transport(): Transport
    {
        $factory = new Psr17Factory();

        return new Transport(
            $this->http,
            $factory,
            $factory,
            'https://ixc.example.com',
            Credentials::fromToken('6:not-a-real-secret')
        );
    }

    /**
     * @param list<string> $ids
     */
    private function listing(int $total, array $ids): Response
    {
        $rows = array_map(static fn (string $id): array => ['id' => $id], $ids);

        return new Response(200, [], (string) json_encode(['page' => '1', 'total' => $total, 'registros' => $rows]));
    }

    private function request(int $index): RequestInterface
    {
        $request = $this->http->getRequests()[$index] ?? null;
        self::assertInstanceOf(RequestInterface::class, $request);

        return $request;
    }

    /**
     * @return array<mixed>
     */
    private function body(RequestInterface $request): array
    {
        $body = json_decode((string) $request->getBody(), true);
        self::assertIsArray($body);

        return $body;
    }
}
