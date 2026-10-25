<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tests\Unit;

use Marcolin\IxcProvedor\ApiText;
use PHPUnit\Framework\TestCase;

final class ApiTextTest extends TestCase
{
    public function testRepairsDoubleEncodedText(): void
    {
        self::assertSame('Horário não permitido!', ApiText::repair('HorÃ¡rio nÃ£o permitido!'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function textsThatStay(): iterable
    {
        yield 'ascii' => ['Registro 18 excluido com sucesso!'];
        yield 'plain utf-8' => ['Recurso tabela_x não está disponível!'];
        yield 'outside latin-1' => ['Contrato — ativo, não editável'];
        yield 'empty' => [''];
    }

    /**
     * @dataProvider textsThatStay
     */
    public function testLeavesOtherTextUnchanged(string $text): void
    {
        self::assertSame($text, ApiText::repair($text));
    }

    public function testSplitsOnLineBreaksAndDropsEmptyParts(): void
    {
        self::assertSame(
            ['Preencha Filial', 'Preencha Cliente', 'A & B'],
            ApiText::split('Preencha Filial<br />Preencha Cliente<BR>A &amp; B<br />')
        );
    }

    public function testSplitsDoubleEncodedTextRepaired(): void
    {
        self::assertSame(['Seu IP não está liberado'], ApiText::split('Seu IP nÃ£o estÃ¡ liberado<br />'));
    }
}
