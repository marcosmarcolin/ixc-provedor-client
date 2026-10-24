<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor;

use InvalidArgumentException;

/**
 * A webservice user's token, in the "<user id>:<secret>" form shown by IXC Provedor.
 */
final class Credentials
{
    private string $userId;

    private string $secret;

    public function __construct(string $userId, string $secret)
    {
        if (preg_match('/^[1-9]\d*\z/', $userId) !== 1) {
            throw new InvalidArgumentException('The user id must be a positive integer.');
        }
        if ($secret === '' || preg_match('/\s/', $secret) === 1) {
            throw new InvalidArgumentException('The token secret must be non-empty and contain no whitespace.');
        }

        $this->userId = $userId;
        $this->secret = $secret;
    }

    public static function fromToken(string $token): self
    {
        $parts = explode(':', $token, 2);
        if (count($parts) !== 2) {
            throw new InvalidArgumentException('The token must have the form "<user id>:<secret>".');
        }

        return new self($parts[0], $parts[1]);
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function authorizationHeader(): string
    {
        return 'Basic ' . base64_encode($this->userId . ':' . $this->secret);
    }

    /**
     * Keeps the secret out of var_dump() and debug output.
     *
     * @return array{userId: string, secret: string}
     */
    public function __debugInfo(): array
    {
        return ['userId' => $this->userId, 'secret' => '***'];
    }
}
