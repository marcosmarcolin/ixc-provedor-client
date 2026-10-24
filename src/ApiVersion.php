<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor;

use InvalidArgumentException;

/**
 * The webservice API version, the "v1" in https://HOST/webservice/v1.
 */
final class ApiVersion
{
    public const V1 = 'v1';

    /** Versions whose routes and payloads this client implements. */
    private const SUPPORTED = [self::V1];

    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function v1(): self
    {
        return new self(self::V1);
    }

    public static function latest(): self
    {
        return self::v1();
    }

    public static function fromString(string $version): self
    {
        if (!in_array($version, self::SUPPORTED, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported API version "%s"; supported: %s.',
                $version,
                implode(', ', self::SUPPORTED)
            ));
        }

        return new self($version);
    }

    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        return self::SUPPORTED;
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * The path prefix of every route, without slashes: "webservice/v1".
     */
    public function basePath(): string
    {
        return 'webservice/' . $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
