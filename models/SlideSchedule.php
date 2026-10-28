<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumCarousel\Models;

/**
 * When a slide is live. Pure date rules, kept apart from the database so they can be tested on their own.
 *
 * A slide with no dates is always live. With dates it is live from the start to the end date, both days included
 * (either may be left open). A recurring slide ignores the year: it is live on those calendar days every year,
 * and an end earlier in the year than the start wraps over New Year (for example 20 Dec to 5 Jan).
 */
class SlideSchedule
{
    public static function isLive(?string $start, ?string $end, bool $recurring, string $today): bool
    {
        $hasNoDates = $start === null && $end === null;
        if ($hasNoDates) return true;

        if ($recurring) return self::isLiveEachYear(start: $start, end: $end, today: $today);

        $started = $start === null || $today >= $start;
        $notEnded = $end === null || $today <= $end;

        return $started && $notEnded;
    }

    /**
     * What an admin sees beside a slide: Live, Scheduled, Ended or Off season (a recurring slide outside its days)
     */
    public static function label(?string $start, ?string $end, bool $recurring, string $today): string
    {
        $live = self::isLive(start: $start, end: $end, recurring: $recurring, today: $today);
        if ($live) return 'Live';
        if ($recurring) return 'Off season';

        $notStarted = $start !== null && $today < $start;

        return $notStarted ? 'Scheduled' : 'Ended';
    }

    private static function isLiveEachYear(?string $start, ?string $end, string $today): bool
    {
        $todayDay = \substr($today, 5);
        $startDay = $start === null ? '01-01' : \substr($start, 5);
        $endDay = $end === null ? '12-31' : \substr($end, 5);

        $wraps = $startDay > $endDay;
        if ($wraps) return $todayDay >= $startDay || $todayDay <= $endDay;

        return $todayDay >= $startDay && $todayDay <= $endDay;
    }
}
