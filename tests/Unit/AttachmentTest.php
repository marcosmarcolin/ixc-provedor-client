<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tests\Unit;

use InvalidArgumentException;
use Marcolin\IxcProvedor\Attachment;
use PHPUnit\Framework\TestCase;

final class AttachmentTest extends TestCase
{
    public function testHoldsContentsGivenDirectly(): void
    {
        $file = Attachment::fromContents('contrato.pdf', '%PDF-1.4', 'application/pdf');

        self::assertSame('contrato.pdf', $file->filename());
        self::assertSame('%PDF-1.4', $file->contents());
        self::assertSame('application/pdf', $file->mediaType());
    }

    public function testReadsAFileAndKeepsItsNameUnlessAnotherIsGiven(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ixc');
        self::assertIsString($path);
        file_put_contents($path, 'conteudo');

        try {
            $file = Attachment::fromPath($path, 'text/plain');
            self::assertSame(basename($path), $file->filename());
            self::assertSame('conteudo', $file->contents());
            self::assertSame('application/octet-stream', Attachment::fromPath($path)->mediaType());
            self::assertSame('contrato.txt', Attachment::fromPath($path, 'text/plain', 'contrato.txt')->filename());
        } finally {
            unlink($path);
        }
    }

    public function testRejectsAFileThatCannotBeRead(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Attachment::fromPath('/does/not/exist.pdf');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidFiles(): iterable
    {
        yield 'empty name' => ['', 'text/plain'];
        yield 'quote in name' => ['a"b.txt', 'text/plain'];
        yield 'line break in name' => ["a\r\nb.txt", 'text/plain'];
        yield 'path in name' => ['../a.txt', 'text/plain'];
        yield 'bad media type' => ['a.txt', "text/plain\r\nX: y"];
    }

    /**
     * @dataProvider invalidFiles
     */
    public function testRejectsUnsafeNamesAndTypes(string $filename, string $mediaType): void
    {
        $this->expectException(InvalidArgumentException::class);

        Attachment::fromContents($filename, 'x', $mediaType);
    }
}
