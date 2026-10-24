<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tests\Unit;

use Marcolin\IxcProvedor\Resources;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ResourcesTest extends TestCase
{
    public function testNamesEveryResourceTheWikiDocumentsOnce(): void
    {
        $names = (new ReflectionClass(Resources::class))->getConstants();

        self::assertCount(17, $names);
        $seen = [];
        foreach ($names as $constant => $name) {
            self::assertIsString($name);
            self::assertMatchesRegularExpression('/^[A-Za-z0-9_]+$/', $name, $constant);
            self::assertNotContains($name, $seen, $constant . ' repeats another name.');
            $seen[] = $name;
        }
    }

    public function testEveryNamedResourceWasCapturedFromARealInstance(): void
    {
        $captured = [];
        foreach (glob(dirname(__DIR__) . '/Fixtures/resource_*.json') ?: [] as $file) {
            $fixture = json_decode((string) file_get_contents($file), true);
            self::assertIsArray($fixture);
            self::assertIsArray($fixture['request']);
            self::assertIsString($fixture['request']['path']);
            $captured[] = basename($fixture['request']['path']);
        }

        foreach ((new ReflectionClass(Resources::class))->getConstants() as $constant => $name) {
            self::assertContains($name, $captured, $constant . ' has no captured fixture.');
        }
    }
}
