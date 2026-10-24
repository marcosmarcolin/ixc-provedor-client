<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor;

use InvalidArgumentException;

/**
 * A multipart/form-data body (RFC 7578), the only way the API takes files.
 */
final class MultipartBody
{
    private string $boundary;

    private string $body;

    /**
     * @param array<string, string> $fields
     * @param array<string, Attachment> $files
     */
    public function __construct(array $fields, array $files, ?string $boundary = null)
    {
        $this->boundary = $boundary ?? 'ixc-' . bin2hex(random_bytes(16));

        $parts = '';
        foreach ($fields as $name => $value) {
            $parts .= $this->part($name, [], $value);
        }
        foreach ($files as $name => $file) {
            if (array_key_exists($name, $fields)) {
                throw new InvalidArgumentException(sprintf('"%s" is both a field and a file.', $name));
            }
            $parts .= $this->part(
                $name,
                ['filename' => $file->filename(), 'type' => $file->mediaType()],
                $file->contents()
            );
        }
        $this->body = $parts . '--' . $this->boundary . "--\r\n";
    }

    public function contentType(): string
    {
        return 'multipart/form-data; boundary=' . $this->boundary;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @param array{filename?: string, type?: string} $file
     */
    private function part(string $name, array $file, string $contents): string
    {
        if ($name === '' || preg_match('/["\r\n]/', $name) === 1) {
            throw new InvalidArgumentException(sprintf('Invalid form field name "%s".', $name));
        }
        if (strpos($contents, '--' . $this->boundary) !== false) {
            throw new InvalidArgumentException('A value contains the multipart boundary.');
        }

        $disposition = 'Content-Disposition: form-data; name="' . $name . '"';
        $headers = isset($file['filename'])
            ? $disposition . '; filename="' . $file['filename'] . "\"\r\nContent-Type: " . ($file['type'] ?? '')
            : $disposition;

        return '--' . $this->boundary . "\r\n" . $headers . "\r\n\r\n" . $contents . "\r\n";
    }
}
