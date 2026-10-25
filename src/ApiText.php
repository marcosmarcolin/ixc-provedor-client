<?php

declare(strict_types=1);

namespace Marcolin\IxcProvedor;

/**
 * Turns the API's "message" into plain text: repairs double-encoded UTF-8 and splits on <br />.
 *
 * @internal
 */
final class ApiText
{
    /**
     * Messages the API encodes twice ("nÃ£o" for "não", as in access refusals) are decoded once.
     * Text that is not double-encoded comes back unchanged.
     */
    public static function repair(string $text): string
    {
        // Double-encoded text holds only U+0000 to U+00FF, with some character above U+007F.
        if (preg_match('/^[\x{0}-\x{FF}]*$/u', $text) !== 1 || preg_match('/[\x{80}-\x{FF}]/u', $text) !== 1) {
            return $text;
        }

        // Each character back to its Latin-1 byte, without ext-mbstring.
        $bytes = (string) preg_replace_callback('/[\x{80}-\x{FF}]/u', static function (array $match): string {
            return chr(((ord($match[0][0]) & 0x1F) << 6) | (ord($match[0][1]) & 0x3F));
        }, $text);

        return preg_match('//u', $bytes) === 1 ? $bytes : $text;
    }

    /**
     * The API joins several messages with <br />; this gives them one by one, as plain text.
     *
     * @return list<string>
     */
    public static function split(string $message): array
    {
        $parts = preg_split('/<br\s*\/?>/i', self::repair($message)) ?: [];
        $messages = [];
        foreach ($parts as $part) {
            $text = trim(html_entity_decode(strip_tags($part), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($text !== '') {
                $messages[] = $text;
            }
        }

        return $messages;
    }
}
