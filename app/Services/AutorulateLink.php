<?php

namespace App\Services;

use RuntimeException;

class AutorulateLink
{
    public const PLACEHOLDER = 'https://placeholder.local/LINK_RULATE';

    public const BASE_URL = 'https://radacini.ro/autorulate/vinde-masina-rulata';

    public static function make(string $email, string $campaign = ''): string
    {
        $secret = config('services.autorulate.link_secret');

        if (empty($secret)) {
            throw new RuntimeException('AUTORULATE_LINK_SECRET is not configured.');
        }

        $email = strtolower(trim($email));

        return self::BASE_URL
            . '?utm_source=email&utm_campaign=' . urlencode($campaign)
            . '&e=' . urlencode($email)
            . '&sig=' . hash_hmac('sha256', $email, $secret);
    }

    /**
     * Replace the placeholder in an HTML body with the recipient's signed link.
     */
    public static function apply(string $html, string $email, string $campaign = ''): string
    {
        if (!str_contains($html, self::PLACEHOLDER)) {
            return $html;
        }

        $link = self::make($email, $campaign);
        $htmlLink = str_replace('&', '&amp;', $link);

        return str_replace(
            [str_replace('&', '&amp;', self::PLACEHOLDER), self::PLACEHOLDER],
            $htmlLink,
            $html
        );
    }
}
