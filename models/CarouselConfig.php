<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumCarousel\Models;

/**
 * The slide size the whole site uses, kept in this service's own settings file
 * (app/config/services/osmium-carousel.json.php). A new image is cropped to this size and refused if smaller.
 */
class CarouselConfig
{
    public const DEFAULT_WIDTH = 1920;
    public const DEFAULT_HEIGHT = 600;
    public const MIN_SIDE = 200;
    public const MAX_SIDE = 4000;
    public const PATH = 'app/config/services/osmium-carousel.json.php';

    /**
     * @return array{width: int, height: int}
     */
    public static function get(string $path = self::PATH): array
    {
        $defaults = ['width' => self::DEFAULT_WIDTH, 'height' => self::DEFAULT_HEIGHT];

        $fileExists = \file_exists($path);
        if (!$fileExists) return $defaults;

        $content = (string) \file_get_contents($path);
        $jsonStart = \strpos(haystack: $content, needle: '{');
        $noJsonFound = $jsonStart === false;
        if ($noJsonFound) return $defaults;

        $decoded = \json_decode(\substr(string: $content, offset: $jsonStart), associative: true);
        $saved = \is_array($decoded) ? ($decoded['carousel'] ?? []) : [];

        $width = (int) ($saved['width'] ?? 0);
        $height = (int) ($saved['height'] ?? 0);

        return [
            'width' => self::validSide($width) ? $width : $defaults['width'],
            'height' => self::validSide($height) ? $height : $defaults['height'],
        ];
    }

    public static function save(int $width, int $height, string $path = self::PATH): void
    {
        $dir = \dirname($path);
        $dirMissing = !\is_dir($dir);
        if ($dirMissing) \mkdir(directory: $dir, permissions: 0755, recursive: true);

        $json = \json_encode(
            value: ['carousel' => ['width' => $width, 'height' => $height]],
            flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );

        \file_put_contents($path, "<?php exit(); ?>\n" . $json . "\n");
    }

    public static function validSide(int $side): bool
    {
        return $side >= self::MIN_SIDE && $side <= self::MAX_SIDE;
    }
}
