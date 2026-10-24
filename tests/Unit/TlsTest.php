<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tests\Unit;

use InvalidArgumentException;
use Marcolin\IxcProvedor\Tls;
use PHPUnit\Framework\TestCase;

final class TlsTest extends TestCase
{
    public function testAcceptingSelfSignedVerifiesNothing(): void
    {
        $tls = Tls::acceptSelfSigned();

        self::assertFalse($tls->verifies());
        self::assertNull($tls->certificate());
    }

    public function testVerifyUsesTheSystemAuthorities(): void
    {
        $tls = Tls::verify();

        self::assertTrue($tls->verifies());
        self::assertNull($tls->certificate());
    }

    public function testVerifyWithPinsAReadableCertificate(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'pem');
        self::assertIsString($file);

        try {
            $tls = Tls::verifyWith($file);
            self::assertTrue($tls->verifies());
            self::assertSame($file, $tls->certificate());
        } finally {
            unlink($file);
        }
    }

    public function testRejectsACertificateThatCannotBeRead(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Tls::verifyWith('/does/not/exist.pem');
    }
}
