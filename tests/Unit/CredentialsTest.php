<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tests\Unit;

use InvalidArgumentException;
use Marcolin\IxcProvedor\Credentials;
use PHPUnit\Framework\TestCase;

final class CredentialsTest extends TestCase
{
    public function testBuildsTheBasicAuthorizationHeaderFromTheToken(): void
    {
        $credentials = Credentials::fromToken('6:4dacdb8e47193e8c');

        self::assertSame('6', $credentials->userId());
        self::assertSame('Basic ' . base64_encode('6:4dacdb8e47193e8c'), $credentials->authorizationHeader());
    }

    public function testKeepsColonsInsideTheSecret(): void
    {
        $credentials = Credentials::fromToken('12:abc:def');

        self::assertSame('Basic ' . base64_encode('12:abc:def'), $credentials->authorizationHeader());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTokens(): iterable
    {
        yield 'no separator' => ['4dacdb8e47193e8c'];
        yield 'empty user id' => [':4dacdb8e47193e8c'];
        yield 'non-numeric user id' => ['admin:4dacdb8e47193e8c'];
        yield 'zero user id' => ['0:4dacdb8e47193e8c'];
        yield 'empty secret' => ['6:'];
        yield 'whitespace in secret' => ['6:4dac db8e'];
        yield 'trailing newline' => ["6:4dacdb8e\n"];
        yield 'newline in user id' => ["6\n:4dacdb8e"];
    }

    /**
     * @dataProvider invalidTokens
     */
    public function testRejectsMalformedTokens(string $token): void
    {
        $this->expectException(InvalidArgumentException::class);

        Credentials::fromToken($token);
    }

    public function testHidesTheSecretFromDebugOutput(): void
    {
        $credentials = Credentials::fromToken('6:supersecret');

        self::assertSame(['userId' => '6', 'secret' => '***'], $credentials->__debugInfo());
        self::assertStringNotContainsString('supersecret', print_r($credentials, true));
    }
}
