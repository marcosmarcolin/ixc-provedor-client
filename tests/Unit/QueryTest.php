<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tests\Unit;

use InvalidArgumentException;
use Marcolin\IxcProvedor\Query;
use PHPUnit\Framework\TestCase;

final class QueryTest extends TestCase
{
    public function testAllListsEveryRecordLikeTheWikiExamples(): void
    {
        self::assertSame([
            'qtype' => 'cliente.id',
            'query' => '0',
            'oper' => '>',
            'page' => '1',
            'rp' => '20',
            'sortname' => 'cliente.id',
            'sortorder' => 'asc',
        ], Query::all()->toParameters('cliente'));
    }

    public function testQualifiesBareFieldsAndKeepsQualifiedOnes(): void
    {
        $parameters = Query::where('razao', 'L', 'Maria')
            ->orderBy('cidade.nome', 'DESC')
            ->withPage(3)
            ->withPerPage(50)
            ->toParameters('cliente');

        self::assertSame('cliente.razao', $parameters['qtype']);
        self::assertSame('L', $parameters['oper']);
        self::assertSame('Maria', $parameters['query']);
        self::assertSame('cidade.nome', $parameters['sortname']);
        self::assertSame('desc', $parameters['sortorder']);
        self::assertSame('3', $parameters['page']);
        self::assertSame('50', $parameters['rp']);
        self::assertArrayNotHasKey('grid_param', $parameters);
    }

    public function testExtraConditionsGoToGridParamAsAJsonString(): void
    {
        $parameters = Query::all()->andWhere('ativo', '=', 'S')->andWhere('cliente.cidade', '=', '4376')
            ->toParameters('cliente');

        self::assertSame(
            '[{"TB":"cliente.ativo","OP":"=","P":"S"},{"TB":"cliente.cidade","OP":"=","P":"4376"}]',
            $parameters['grid_param']
        );
    }

    public function testIsImmutable(): void
    {
        $query = Query::all();
        $query->withPage(2)->withPerPage(5)->andWhere('ativo', '=', 'S')->orderBy('razao');

        self::assertSame(1, $query->page());
        self::assertSame(20, $query->perPage());
        self::assertSame(Query::all()->toParameters('cliente'), $query->toParameters('cliente'));
    }

    /**
     * @return iterable<string, array{callable(): Query}>
     */
    public static function invalidQueries(): iterable
    {
        yield 'injected field' => [static fn (): Query => Query::where('id; drop', '=', '1')];
        yield 'empty operator' => [static fn (): Query => Query::where('id', '', '1')];
        yield 'operator with space' => [static fn (): Query => Query::where('id', '= 1', '1')];
        yield 'page zero' => [static fn (): Query => Query::all()->withPage(0)];
        yield 'page size zero' => [static fn (): Query => Query::all()->withPerPage(0)];
        yield 'bad sort order' => [static fn (): Query => Query::all()->orderBy('id', 'up')];
        yield 'bad filter field' => [static fn (): Query => Query::all()->andWhere('a.b.c', '=', '1')];
    }

    /**
     * @dataProvider invalidQueries
     */
    public function testRejectsInvalidParts(callable $build): void
    {
        $this->expectException(InvalidArgumentException::class);

        $build();
    }
}
