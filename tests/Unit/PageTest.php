<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tests\Unit;

use Marcolin\IxcProvedor\Exception\UnexpectedResponseException;
use Marcolin\IxcProvedor\Page;
use Marcolin\IxcProvedor\Tests\Support\Fixture;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class PageTest extends TestCase
{
    public function testReadsAPageWithAnIntegerTotal(): void
    {
        $page = Page::fromResponse(Fixture::response('list_page'));

        self::assertSame(1, $page->page());
        self::assertSame(1, $page->total());
        self::assertCount(1, $page->rows());
        self::assertArrayHasKey('razao', $page->rows()[0]);
        self::assertFalse($page->isEmpty());
    }

    public function testReadsATotalSentAsAString(): void
    {
        $page = Page::fromResponse(Fixture::response('list_cidade'));

        self::assertSame(5566, $page->total());
        self::assertCount(2, $page->rows());
    }

    public function testReadsAnEmptyListingWithoutRegistros(): void
    {
        $page = Page::fromResponse(Fixture::response('list_empty'));

        self::assertSame(0, $page->total());
        self::assertSame([], $page->rows());
        self::assertTrue($page->isEmpty());
    }

    public function testRejectsTheHtmlOfAServerFailure(): void
    {
        try {
            Page::fromResponse(Fixture::response('list_invalid_field'));
            self::fail('Expected an UnexpectedResponseException.');
        } catch (UnexpectedResponseException $exception) {
            self::assertSame(200, $exception->statusCode());
            self::assertStringContainsString('Ocorreu um erro', $exception->responseBody());
            self::assertStringNotContainsString('Ocorreu um erro', $exception->getMessage());
        }
    }

    public function testRejectsRowsThatAreNotObjects(): void
    {
        $this->expectException(UnexpectedResponseException::class);

        Page::fromResponse(new Response(200, [], '{"page":"1","total":1,"registros":["x"]}'));
    }
}
