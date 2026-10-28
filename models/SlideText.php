<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumCarousel\Models;

/**
 * Rules for the text a slide is saved with, kept apart from the controller so they can be tested on their own.
 */
class SlideText
{
    /** A web address (http or https) or a path on this site: anything else (javascript:, data:) is refused */
    public static function isValidLink(string $link): bool
    {
        $isTooLong = \strlen($link) > 255;
        if ($isTooLong) return false;

        $isSitePath = \str_starts_with($link, '/') && !\str_starts_with($link, '//');
        if ($isSitePath) return true;

        $scheme = \parse_url($link, PHP_URL_SCHEME);
        $host = \parse_url($link, PHP_URL_HOST);

        return \in_array($scheme, ['http', 'https'], true) && !empty($host);
    }

    /** A real calendar date as YYYY-MM-DD */
    public static function isDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /** Empty after trimming means "none" */
    public static function nullIfEmpty(string $value): ?string
    {
        $trimmed = \trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
