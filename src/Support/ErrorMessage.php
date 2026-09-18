<?php

declare(strict_types=1);

namespace Movecloser\ProcessManager\Support;

use Illuminate\Support\Str;

class ErrorMessage
{
    public const int MAX_LENGTH = 300;

    /**
     * Zamienia komunikat błędu na jedną linię czystego tekstu - treść trafia do
     * karty na dashboardzie jako HTML, a strony błędu serwerów niosą m.in. <base
     * href>, który przestawia adresy wszystkich względnych linków na stronie.
     */
    public static function plain(string $message, int $limit = self::MAX_LENGTH): string
    {
        $plain = mb_check_encoding($message, 'UTF-8')
            ? $message
            : mb_convert_encoding($message, 'UTF-8', 'UTF-8');

        $plain = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $plain) ?? $plain;
        $plain = html_entity_decode(strip_tags($plain), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plain = preg_replace('/[\s\x00-\x1F\x7F]+/u', ' ', $plain) ?? $plain;

        return Str::limit(trim($plain), $limit);
    }
}
