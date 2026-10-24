<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor\Tests\Support;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

/**
 * Loads a captured response from tests/Fixtures/ as a PSR-7 response.
 *
 * Binary bodies are stored as "body_base64"; long ones keep only their start (see "body_length").
 */
final class Fixture
{
    public static function response(string $name): ResponseInterface
    {
        $file = dirname(__DIR__) . '/Fixtures/' . $name . '.json';
        $fixture = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        $response = is_array($fixture) ? ($fixture['response'] ?? null) : null;
        $body = is_array($response) ? ($response['body'] ?? null) : null;
        if (is_array($response) && isset($response['body_base64']) && is_string($response['body_base64'])) {
            $body = base64_decode($response['body_base64'], true);
        }
        if (
            !is_array($response)
            || !is_int($response['status'] ?? null)
            || !is_array($response['headers'] ?? null)
            || !is_string($body)
        ) {
            throw new RuntimeException(sprintf('Fixture "%s" is missing or malformed.', $name));
        }

        $headers = [];
        foreach ($response['headers'] as $header => $value) {
            $headers[(string) $header] = is_string($value) ? $value : '';
        }

        return new Response($response['status'], $headers, $body);
    }
}
