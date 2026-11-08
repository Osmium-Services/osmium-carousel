<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumCarousel\Models;

use Osmium\Core\Library\ImageTools;

/**
 * A slide's picture on disk: the desktop size, a half-size mobile copy, each as JPG plus WebP and AVIF where this
 * server's GD can write them. Files are named <name>.jpg, <name>-mobile.jpg, webp/<name>.webp and avif/<name>.avif.
 */
class SlideImage
{
    public const DIR = '/assets/images/carousel/';
    public const TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * Crops from the centre to the target shape and scales to the target size, then writes every copy
     *
     * @return array{width: int, height: int} Size of the desktop copy
     * @throws \InvalidArgumentException when the file is not a usable image or is smaller than the target size
     */
    public static function save(string $sourcePath, string $name, int $width, int $height, string $root): array
    {
        $tools = new ImageTools();
        $source = $tools->load(path: $sourcePath, allowedTypes: self::TYPES);

        $sourceWidth = \imagesx($source);
        $sourceHeight = \imagesy($source);
        $tooSmall = $sourceWidth < $width || $sourceHeight < $height;
        if ($tooSmall) {
            throw new \InvalidArgumentException("Image is too small. It must be at least {$width} x {$height}px; this one is {$sourceWidth} x {$sourceHeight}px");
        }

        $desktop = $tools->cover(image: $source, width: $width, height: $height, keepAlpha: false);
        $mobile = $tools->cover(image: $desktop, width: \intdiv($width, 2), height: \intdiv($height, 2), keepAlpha: false);

        $dir = $root . self::DIR;
        self::makeDirs($dir);
        self::writeFormats(image: $desktop, dir: $dir, name: $name);
        self::writeFormats(image: $mobile, dir: $dir, name: $name . '-mobile');

        return ['width' => $width, 'height' => $height];
    }

    /** Deletes every copy of a picture */
    public static function remove(?string $name, string $root): void
    {
        $hasName = !empty($name);
        if (!$hasName) return;

        $dir = $root . self::DIR;
        $safe = \basename($name);
        foreach (['', '-mobile'] as $suffix) {
            foreach (['' => 'jpg', 'webp/' => 'webp', 'avif/' => 'avif'] as $sub => $extension) {
                $path = $dir . $sub . $safe . $suffix . '.' . $extension;
                $exists = \file_exists($path);
                if ($exists) \unlink($path);
            }
        }
    }

    /**
     * The copies of a picture as URLs, for a theme's <picture> element; webp and avif are null when not written
     *
     * @return array{jpg: string, webp: ?string, avif: ?string}
     */
    public static function urls(string $name, string $suffix, string $root): array
    {
        $dir = $root . self::DIR;
        $base = $name . $suffix;
        $webp = \file_exists($dir . 'webp/' . $base . '.webp') ? self::DIR . 'webp/' . $base . '.webp' : null;
        $avif = \file_exists($dir . 'avif/' . $base . '.avif') ? self::DIR . 'avif/' . $base . '.avif' : null;

        return ['jpg' => self::DIR . $base . '.jpg', 'webp' => $webp, 'avif' => $avif];
    }

    private static function makeDirs(string $dir): void
    {
        foreach ([$dir, $dir . 'webp/', $dir . 'avif/'] as $path) {
            $missing = !\is_dir($path);
            if ($missing) \mkdir(directory: $path, permissions: 0755, recursive: true);
        }
    }

    private static function writeFormats(\GdImage $image, string $dir, string $name): void
    {
        $written = \imagejpeg(image: $image, file: $dir . $name . '.jpg', quality: 75);
        if (!$written) throw new \RuntimeException('Failed to save the image');

        if (\function_exists('imagewebp')) \imagewebp(image: $image, file: $dir . 'webp/' . $name . '.webp', quality: 80);
        if (\function_exists('imageavif')) \imageavif(image: $image, file: $dir . 'avif/' . $name . '.avif', quality: 60);
    }
}
