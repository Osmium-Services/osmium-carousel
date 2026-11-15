<?php

declare(strict_types=1);

namespace Osmium\Services\OsmiumCarousel\Models;

use Osmium\Core\Library\ImageTools;

/**
 * A slide's picture on disk: the desktop size, a half-size mobile copy, each as JPG plus WebP and AVIF where this
 * server's GD can write them. A picture's name is <folder>/<file>, the folder from the slide's title and the file from the
 * uploaded file's name, as the Blog does; older pictures have a bare name with no folder. Files are named <name>.jpg,
 * <name>-mobile.jpg, <name>-list.jpg and <name>-grid.jpg (small copies for the admin lists), and webp/ and avif/ copies
 * beside them inside the folder.
 */
class SlideImage
{
    public const DIR = '/assets/images/carousel/';
    public const TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    /** Name suffix => longest side in pixels: the admin list shows a small box, the grid view about 250px wide */
    public const THUMBS = ['list' => 136, 'grid' => 240];
    private const QUALITY = ['jpg' => 75, 'webp' => 80, 'avif' => 60];
    private const THUMB_QUALITY = ['jpg' => 75, 'webp' => 70, 'avif' => 50];
    private const SUFFIXES = ['', '-mobile', '-list', '-grid'];

    /**
     * The name for a new picture: the slide title's folder and the uploaded file's own name, with -2, -3 added only when taken
     */
    public static function name(string $title, string $originalName, string $root): string
    {
        $folder = self::slug($title);
        $prefix = $folder === '' ? '' : $folder . '/';
        $base = self::slug(\pathinfo($originalName, \PATHINFO_FILENAME)) ?: 'image';
        $dir = $root . self::DIR . $prefix;

        $name = $base;
        for ($number = 2; self::taken($dir, $name); $number++) {
            $name = $base . '-' . $number;
        }

        return $prefix . $name;
    }

    /** Whether any copy of a picture called this already exists in the folder */
    private static function taken(string $dir, string $name): bool
    {
        foreach (self::SUFFIXES as $suffix) {
            if (\is_file($dir . $name . $suffix . '.jpg')) return true;
        }

        return false;
    }

    /** A picture's list or grid thumbnail with no extension, for core's Image to pick the best copy of */
    public static function thumbBase(string $name, string $kind): string
    {
        return self::DIR . $name . '-' . $kind;
    }

    /** Whether a stored name has the shape this class writes, so a stored value can never point outside the carousel folder */
    public static function isValidName(?string $name): bool
    {
        return $name !== null && \preg_match('#^([a-z0-9-]+/)?[a-z0-9-]+$#', $name) === 1;
    }

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

        $dir = $root . self::DIR . self::folder($name);
        $base = \basename($name);
        $tools->storeVariants($desktop, $dir, $base, self::QUALITY);
        $tools->storeVariants($mobile, $dir, $base . '-mobile', self::QUALITY);
        foreach (self::THUMBS as $kind => $side) {
            $tools->storeVariants($tools->fit($desktop, $side), $dir, $base . '-' . $kind, self::THUMB_QUALITY);
        }

        return ['width' => $width, 'height' => $height];
    }

    /** Deletes every copy of a picture, then its folder when that leaves it empty */
    public static function remove(?string $name, string $root): void
    {
        $unusable = !self::isValidName($name);
        if ($unusable) return;

        $dir = $root . self::DIR . self::folder($name);
        $safe = \basename($name);
        foreach (self::SUFFIXES as $suffix) {
            foreach (['' => 'jpg', 'webp/' => 'webp', 'avif/' => 'avif'] as $sub => $extension) {
                $path = $dir . $sub . $safe . $suffix . '.' . $extension;
                $exists = \file_exists($path);
                if ($exists) \unlink($path);
            }
        }

        self::removeEmptyFolder(dir: $dir, root: $root);
    }

    /**
     * Follows a slide's new title: the picture's folder is renamed in one step when the slide is the only one using it and
     * the new folder does not exist yet. Otherwise the picture's own copies are moved into the new folder one by one, keeping
     * the file's name (a number is added only if the new folder already has one of that name), and the old folder goes if
     * that leaves it empty. A picture saved before folders existed is moved into its folder the same way. A title that
     * makes the same folder, or no folder at all, leaves the picture where it is.
     *
     * @return string The picture's name afterwards, which is the same name when nothing moved
     */
    public static function move(?string $name, string $title, string $root, bool $aloneInFolder): string
    {
        $unusable = !self::isValidName($name);
        if ($unusable) return (string) $name;

        $oldFolder = self::folder($name);
        $newFolder = self::slug($title);
        $stays = $newFolder === '' || $oldFolder === $newFolder . '/';
        if ($stays) return $name;

        $from = $root . self::DIR . $oldFolder;
        $to = $root . self::DIR . $newFolder . '/';
        $noPicture = !\is_file($from . \basename($name) . '.jpg'); // Nothing on disk to follow, so the record is left alone
        if ($noPicture) return $name;

        $canRenameFolder = $oldFolder !== '' && $aloneInFolder && !\file_exists($to);
        if ($canRenameFolder && \rename($from, $to)) return $newFolder . '/' . \basename($name);

        return self::moveCopies(name: $name, title: $title, root: $root);
    }

    private static function moveCopies(string $name, string $title, string $root): string
    {
        $newName = self::name(title: $title, originalName: \basename($name), root: $root);
        $from = $root . self::DIR . self::folder($name);
        $to = $root . self::DIR . self::folder($newName);
        foreach (self::SUFFIXES as $suffix) {
            foreach (['' => 'jpg', 'webp/' => 'webp', 'avif/' => 'avif'] as $sub => $extension) {
                $source = $from . $sub . \basename($name) . $suffix . '.' . $extension;
                $exists = \file_exists($source);
                if (!$exists) continue;

                $folder = $to . $sub;
                $missing = !\is_dir($folder);
                if ($missing) \mkdir(directory: $folder, permissions: 0755, recursive: true);
                \rename($source, $folder . \basename($newName) . $suffix . '.' . $extension);
            }
        }

        self::removeEmptyFolder(dir: $from, root: $root);

        return $newName;
    }

    /**
     * Makes the list and grid copies of a picture that lacks them, from its desktop JPG. A picture saved before these
     * copies existed gets them the first time its slide is saved.
     */
    public static function ensureThumbs(?string $name, string $root): void
    {
        $unusable = !self::isValidName($name);
        if ($unusable) return;

        $dir = $root . self::DIR . self::folder($name);
        $base = \basename($name);
        $source = $dir . $base . '.jpg';
        $tools = new ImageTools();
        $image = null;
        foreach (self::THUMBS as $kind => $side) {
            $made = \is_file($dir . $base . '-' . $kind . '.jpg');
            if ($made || !\is_file($source)) continue;

            $image ??= $tools->load(path: $source, allowedTypes: ['image/jpeg']);
            $tools->storeVariants($tools->fit($image, $side), $dir, $base . '-' . $kind, self::THUMB_QUALITY);
        }
    }

    /** The URL of a picture's mobile JPG, which the admin shows as its thumbnail */
    public static function thumbUrl(?string $name): string
    {
        $unusable = !self::isValidName($name);

        return $unusable ? '' : self::DIR . $name . '-mobile.jpg';
    }

    /**
     * The copies of a picture as URLs, for a theme's <picture> element; webp and avif are null when not written
     *
     * @return array{jpg: string, webp: ?string, avif: ?string}
     */
    public static function urls(string $name, string $suffix, string $root): array
    {
        $folder = self::folder($name);
        $dir = $root . self::DIR . $folder;
        $base = \basename($name) . $suffix;
        $webp = \file_exists($dir . 'webp/' . $base . '.webp') ? self::DIR . $folder . 'webp/' . $base . '.webp' : null;
        $avif = \file_exists($dir . 'avif/' . $base . '.avif') ? self::DIR . $folder . 'avif/' . $base . '.avif' : null;

        return ['jpg' => self::DIR . $folder . $base . '.jpg', 'webp' => $webp, 'avif' => $avif];
    }

    private static function removeEmptyFolder(string $dir, string $root): void
    {
        $hasFolder = $dir !== $root . self::DIR;
        if ($hasFolder) {
            foreach (['webp/', 'avif/', ''] as $sub) {
                @\unlink($dir . $sub . '.DS_Store'); // Finder leaves this behind, and it would stop the folder counting as empty
                @\rmdir($dir . $sub); // Only succeeds on an empty folder
            }
        }
    }

    /** A name's folder with its trailing slash, or '' for an older picture with none */
    private static function folder(string $name): string
    {
        $slash = \strrpos($name, '/');

        return $slash === false ? '' : \substr($name, 0, $slash + 1);
    }

    private static function slug(string $text): string
    {
        return \substr(\trim((string) \preg_replace('/[^a-z0-9]+/', '-', \strtolower($text)), '-'), 0, 60);
    }
}
