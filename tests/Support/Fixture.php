<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tests\Support;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Loads a captured response from tests/Fixtures/ as a PSR-7 response.
 */
final class Fixture
{
    public static function response(string $name): ResponseInterface
    {
        $file = dirname(__DIR__) . '/Fixtures/' . $name . '.json';
        $fixture = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $response = is_array($fixture) ? ($fixture['response'] ?? null) : null;
        if (
            !is_array($response)
            || !is_int($response['status'] ?? null)
            || !is_array($response['headers'] ?? null)
            || !is_string($response['body'] ?? null)
        ) {
            throw new RuntimeException(sprintf('Fixture "%s" is missing or malformed.', $name));
        }

        $headers = [];
        foreach ($response['headers'] as $header => $value) {
            $headers[(string) $header] = is_string($value) ? $value : '';
        }

        return new Response($response['status'], $headers, $response['body']);
    }
}
