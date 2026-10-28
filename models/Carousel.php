<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumCarousel\Models;

use Osmium\Core\Library\OsmiumPDO;

/**
 * What a theme calls to show the slides: the ones live today, in display order.
 *
 *     $carousel = new \Osmium\Services\OsmiumCarousel\Models\Carousel($this->osmium->dataSource);
 *     $slides = $carousel->active();            // slides with no group: the site's main carousel
 *     $offers = $carousel->active('Offers');    // slides in the group "Offers": a second carousel
 *
 * A slide is an array with title, summary, buttonText, url (or null) and image / imageMobile, each with width,
 * height, jpg, webp and avif (webp and avif are null when the server could not write them). If any live slide in the
 * group is "exclusive", only the exclusive ones are returned.
 */
class Carousel
{
    public function __construct(private OsmiumPDO $database, private ?string $root = null)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function active(?string $group = null, ?string $today = null): array
    {
        $today ??= \date('Y-m-d');
        $root = $this->root ?? (string) $_SERVER['DOCUMENT_ROOT'];

        $live = [];
        foreach ((new Slide($this->database))->listEnabled() as $row) {
            $otherGroup = ($row['group_name'] ?: null) !== $group;
            if ($otherGroup) continue;

            $isLive = SlideSchedule::isLive(
                start: $row['schedule_start'],
                end: $row['schedule_end'],
                recurring: (bool) $row['schedule_recurring'],
                today: $today,
            );
            if ($isLive) $live[] = $row;
        }

        $exclusive = \array_values(\array_filter($live, static fn(array $row): bool => (bool) $row['exclusive']));
        $shown = $exclusive !== [] ? $exclusive : $live;

        return \array_map(fn(array $row): array => $this->present(row: $row, root: $root), $shown);
    }

    private function present(array $row, string $root): array
    {
        $width = (int) $row['image_width'];
        $height = (int) $row['image_height'];
        $name = (string) $row['image_filename'];

        return [
            'title' => (string) $row['title'],
            'summary' => $row['summary'] ?? null,
            'buttonText' => $row['button_text'] ?? null,
            'url' => $this->url($row),
            'image' => ['width' => $width, 'height' => $height] + SlideImage::urls(name: $name, suffix: '', root: $root),
            'imageMobile' => ['width' => \intdiv($width, 2), 'height' => \intdiv($height, 2)] + SlideImage::urls(name: $name, suffix: '-mobile', root: $root),
        ];
    }

    private function url(array $row): ?string
    {
        if ($row['link_type'] === 'url') return $row['link_url'] ?: null;

        $isPageLink = $row['link_type'] === 'page' && $row['page_url'] !== null;
        if (!$isPageLink) return null;

        $path = \trim((string) $row['page_url'], '/');

        return $path === '' ? '/' : '/' . $path . '/';
    }
}
