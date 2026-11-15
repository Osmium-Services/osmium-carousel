<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumCarousel\Models;

/**
 * What the carousel service tells the front-end staff bar: that the page asked for slides (adminbar.context) and the
 * admin link that goes with it (adminbar.links). A theme asks through Carousel::active(), which notes it here; core
 * asks after the page is built, so the answer is current. A supporting link (order 90): it sits after the page's own and
 * any main service link. A page that did not ask for slides announces nothing.
 */
class CarouselAdminBar
{
    private static bool $shown = false;

    /** Called by Carousel::active() */
    public static function noteShown(): void
    {
        self::$shown = true;
    }

    public static function wasShown(): bool
    {
        return self::$shown;
    }

    /** For tests: forget that slides were asked for. */
    public static function forgetShown(): void
    {
        self::$shown = false;
    }

    /**
     * @param array<string, mixed> $payload the finished page's view data (unused: Carousel::active() notes itself)
     * @return string|null "carousel:all" when the page asked for slides, null otherwise
     */
    public static function context(array $payload): ?string
    {
        return self::$shown ? 'carousel:all' : null;
    }

    /**
     * @param array{context?: string, role?: string} $payload
     * @return array<int, array<string, mixed>>|null
     */
    public static function links(array $payload): ?array
    {
        $isContext = ($payload['context'] ?? '') === 'carousel:all';
        if (!$isContext) return null;

        return [['label' => 'Edit carousel', 'icon' => 'bx-images', 'path' => 'carousel/', 'primary' => true, 'order' => 90]];
    }
}
