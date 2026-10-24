<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor;

use InvalidArgumentException;

/**
 * How the built-in HTTP client checks the instance's certificate.
 *
 * IXC Provedor instances commonly use self-signed certificates, so the default accepts them, as the
 * official client's $selfSigned does. That leaves the connection open to interception; verifyWith()
 * pins the instance's own certificate instead.
 */
final class Tls
{
    private bool $verify;

    private ?string $certificate;

    private function __construct(bool $verify, ?string $certificate)
    {
        $this->verify = $verify;
        $this->certificate = $certificate;
    }

    /**
     * Accepts any certificate, self-signed included. The default.
     */
    public static function acceptSelfSigned(): self
    {
        return new self(false, null);
    }

    /**
     * Verifies the certificate against the system's certificate authorities.
     */
    public static function verify(): self
    {
        return new self(true, null);
    }

    /**
     * Verifies the certificate against the given PEM file, such as the instance's self-signed one.
     */
    public static function verifyWith(string $certificate): self
    {
        if (!is_file($certificate) || !is_readable($certificate)) {
            throw new InvalidArgumentException(sprintf('Cannot read the certificate "%s".', $certificate));
        }

        return new self(true, $certificate);
    }

    public function verifies(): bool
    {
        return $this->verify;
    }

    public function certificate(): ?string
    {
        return $this->certificate;
    }
}
