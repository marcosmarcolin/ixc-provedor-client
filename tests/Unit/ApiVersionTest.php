<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tests\Unit;

use InvalidArgumentException;
use Marcolin\IxcProvedor\ApiVersion;
use PHPUnit\Framework\TestCase;

final class ApiVersionTest extends TestCase
{
    public function testV1PointsToTheWebserviceV1Path(): void
    {
        $version = ApiVersion::v1();

        self::assertSame('v1', $version->value());
        self::assertSame('webservice/v1', $version->basePath());
    }

    public function testLatestIsV1(): void
    {
        self::assertTrue(ApiVersion::latest()->equals(ApiVersion::v1()));
    }

    public function testParsesASupportedVersion(): void
    {
        self::assertTrue(ApiVersion::fromString('v1')->equals(ApiVersion::v1()));
        self::assertSame(['v1'], ApiVersion::supported());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedVersions(): iterable
    {
        yield 'unknown version' => ['v2'];
        yield 'missing prefix' => ['1'];
        yield 'uppercase' => ['V1'];
        yield 'empty' => [''];
        yield 'with slash' => ['/v1'];
    }

    /**
     * @dataProvider unsupportedVersions
     */
    public function testRejectsUnsupportedVersions(string $version): void
    {
        $this->expectException(InvalidArgumentException::class);

        ApiVersion::fromString($version);
    }
}
