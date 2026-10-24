<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tests\Unit;

use InvalidArgumentException;
use Marcolin\IxcProvedor\Attachment;
use Marcolin\IxcProvedor\MultipartBody;
use PHPUnit\Framework\TestCase;

final class MultipartBodyTest extends TestCase
{
    public function testBuildsFieldsThenFiles(): void
    {
        $body = new MultipartBody(
            ['id_cliente' => '10', 'descricao' => 'Contrato de adesão'],
            ['local_arquivo' => Attachment::fromContents('contrato.txt', "linha 1\nlinha 2", 'text/plain')],
            'b'
        );

        self::assertSame('multipart/form-data; boundary=b', $body->contentType());
        self::assertSame(
            "--b\r\nContent-Disposition: form-data; name=\"id_cliente\"\r\n\r\n10\r\n"
            . "--b\r\nContent-Disposition: form-data; name=\"descricao\"\r\n\r\nContrato de adesão\r\n"
            . "--b\r\nContent-Disposition: form-data; name=\"local_arquivo\"; filename=\"contrato.txt\"\r\n"
            . "Content-Type: text/plain\r\n\r\nlinha 1\nlinha 2\r\n"
            . "--b--\r\n",
            $body->body()
        );
    }

    public function testUsesARandomBoundaryByDefault(): void
    {
        $first = new MultipartBody(['a' => '1'], []);
        $second = new MultipartBody(['a' => '1'], []);

        $pattern = '/^multipart\/form-data; boundary=ixc-[0-9a-f]{32}$/';
        self::assertMatchesRegularExpression($pattern, $first->contentType());
        self::assertNotSame($first->contentType(), $second->contentType());
    }

    public function testRejectsANameUsedAsFieldAndFile(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MultipartBody(['local_arquivo' => ''], ['local_arquivo' => Attachment::fromContents('a.txt', 'x')]);
    }

    public function testRejectsContentsHoldingTheBoundary(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MultipartBody(['a' => "x\r\n--b\r\n"], [], 'b');
    }

    public function testRejectsUnsafeFieldNames(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new MultipartBody(["a\"\r\nX: y" => '1'], []);
    }
}
