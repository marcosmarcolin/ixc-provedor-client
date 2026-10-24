<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tests\Unit;

use InvalidArgumentException;
use Marcolin\IxcProvedor\Page;
use Marcolin\IxcProvedor\RowEncoder;
use Marcolin\IxcProvedor\Tests\Support\Fixture;
use PHPUnit\Framework\TestCase;

final class RowEncoderTest extends TestCase
{
    public function testTheApiCorruptsDatesWrittenAsTheyWereListed(): void
    {
        // The reason for the encoder: the same row PUT back as listed (update_as_listed) answered success.
        $before = Page::fromResponse(Fixture::response('find'))->rows()[0];
        $after = Page::fromResponse(Fixture::response('find_after_update_as_listed'))->rows()[0];

        self::assertSame('2000-01-01', $before['data_nascimento']);
        self::assertSame('0001-01-00', $after['data_nascimento']);
    }

    public function testWritesDatesAsDayMonthYearAndTheZeroDateAsEmpty(): void
    {
        $body = (new RowEncoder())->encode([
            'data_nascimento' => '1990-05-17',
            'data_cadastro' => '0000-00-00',
            'emp_data_admissao' => '10/11/2020',
            'ultima_atualizacao' => '2026-07-03 09:36:11',
        ]);

        self::assertSame(
            '{"data_nascimento":"17\/05\/1990","data_cadastro":"","emp_data_admissao":"10\/11\/2020",'
            . '"ultima_atualizacao":"2026-07-03 09:36:11"}',
            $body
        );
    }

    public function testEncodesARowReadFromAListingWithoutAnyListedDate(): void
    {
        $row = Page::fromResponse(Fixture::response('find'))->rows()[0];

        $encoded = json_decode((new RowEncoder())->encode($row), true);

        self::assertIsArray($encoded);
        foreach ($encoded as $field => $value) {
            self::assertIsString($value);
            self::assertDoesNotMatchRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $value, (string) $field);
        }
        self::assertSame('01/01/2000', $encoded['data_nascimento']);
    }

    public function testSendsEveryValueAsText(): void
    {
        self::assertSame('{"a":"1","b":"2.5","c":""}', (new RowEncoder())->encode(['a' => 1, 'b' => 2.5, 'c' => null]));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function valuesTheApiCannotTake(): iterable
    {
        yield 'boolean' => [true];
        yield 'array' => [['x']];
        yield 'invalid utf-8' => ["\xB1\x31"];
    }

    /**
     * @dataProvider valuesTheApiCannotTake
     * @param mixed $value
     */
    public function testRejectsValuesTheApiCannotTake($value): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new RowEncoder())->encode(['campo' => $value]);
    }
}
