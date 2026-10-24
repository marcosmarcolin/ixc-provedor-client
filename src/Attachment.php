<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor;

use InvalidArgumentException;

/**
 * A file to send in a form field, such as local_arquivo of cliente_arquivos.
 *
 * The contents are held in memory: the API takes the whole request at once.
 */
final class Attachment
{
    private string $filename;

    private string $contents;

    private string $mediaType;

    private function __construct(string $filename, string $contents, string $mediaType)
    {
        if ($filename === '' || preg_match('/["\r\n\\\\\/]/', $filename) === 1) {
            throw new InvalidArgumentException(
                'The file name must be non-empty, with no quotes, slashes or line breaks.'
            );
        }
        if (preg_match('/^[\w.+-]+\/[\w.+-]+$/', $mediaType) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid media type "%s".', $mediaType));
        }
        $this->filename = $filename;
        $this->contents = $contents;
        $this->mediaType = $mediaType;
    }

    /**
     * @param string|null $filename the name the API stores; defaults to the file's own name
     */
    public static function fromPath(
        string $path,
        string $mediaType = 'application/octet-stream',
        ?string $filename = null
    ): self {
        $contents = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            throw new InvalidArgumentException(sprintf('Cannot read the file "%s".', $path));
        }

        return new self($filename ?? basename($path), $contents, $mediaType);
    }

    public static function fromContents(
        string $filename,
        string $contents,
        string $mediaType = 'application/octet-stream'
    ): self {
        return new self($filename, $contents, $mediaType);
    }

    public function filename(): string
    {
        return $this->filename;
    }

    public function contents(): string
    {
        return $this->contents;
    }

    public function mediaType(): string
    {
        return $this->mediaType;
    }
}
