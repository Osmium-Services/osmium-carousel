<?php

declare(strict_types=1);

namespace Tests\Services\OsmiumCarousel;

use Osmium\Services\OsmiumCarousel\Models\Carousel;
use Osmium\Services\OsmiumCarousel\Models\CarouselConfig;
use Osmium\Services\OsmiumCarousel\Models\Slide;
use Osmium\Services\OsmiumCarousel\Models\SlideImage;
use Osmium\Services\OsmiumCarousel\Models\SlideSchedule;
use Osmium\Services\OsmiumCarousel\Models\SlideText;
use Tests\Integration\DatabaseTestCase;

require_once __DIR__ . '/../../../core/library/ImageTools.php';
require_once __DIR__ . '/../models/SlideSchedule.php';
require_once __DIR__ . '/../models/SlideText.php';
require_once __DIR__ . '/../models/CarouselConfig.php';
require_once __DIR__ . '/../models/Slide.php';
require_once __DIR__ . '/../models/SlideImage.php';
require_once __DIR__ . '/../models/Carousel.php';

/**
 * The schedule, link and size rules on their own, and what a theme gets from Carousel::active() against the installed
 * table (rolled back with each test; the service must be installed, because creating the table here would commit the
 * test's transaction).
 */
class CarouselTest extends DatabaseTestCase
{
    private const NO_SCHEDULE = ['scheduleStart' => null, 'scheduleEnd' => null, 'recurring' => false, 'exclusive' => false];

    public function test_a_picture_is_named_from_the_title_folder_and_the_uploaded_file_and_a_clash_gets_a_number(): void
    {
        $root = \sys_get_temp_dir() . '/carousel-name-' . \uniqid();
        \mkdir($root . SlideImage::DIR . 'summer-sale', 0755, true);

        $this->assertSame('summer-sale/beach-hero', SlideImage::name(title: 'Summer Sale!', originalName: 'Beach Hero.JPG', root: $root));
        $this->assertSame('image', SlideImage::name(title: '', originalName: '???', root: $root));

        \touch($root . SlideImage::DIR . 'summer-sale/beach-hero.jpg');
        $this->assertSame('summer-sale/beach-hero-2', SlideImage::name(title: 'Summer Sale', originalName: 'beach-hero.png', root: $root));

        \unlink($root . SlideImage::DIR . 'summer-sale/beach-hero.jpg');
        \rmdir($root . SlideImage::DIR . 'summer-sale');
        \rmdir($root . SlideImage::DIR);
        \rmdir($root . '/assets/images');
        \rmdir($root . '/assets');
        \rmdir($root);
    }

    public function test_a_stored_name_must_be_a_folder_and_file_or_an_older_bare_name(): void
    {
        $this->assertTrue(SlideImage::isValidName('summer-sale/beach-hero'));
        $this->assertTrue(SlideImage::isValidName('3fa85f6457174562'));
        $this->assertFalse(SlideImage::isValidName('../../etc/passwd'));
        $this->assertFalse(SlideImage::isValidName('a/b/c'));
        $this->assertFalse(SlideImage::isValidName(null));
        $this->assertSame('/assets/images/carousel/summer-sale/beach-hero-mobile.jpg', SlideImage::thumbUrl('summer-sale/beach-hero'));
        $this->assertSame('', SlideImage::thumbUrl('../x'));
    }

    public function test_changing_the_title_moves_the_pictures_files_into_the_new_folder(): void
    {
        $root = \sys_get_temp_dir() . '/carousel-move-' . \uniqid();
        $dir = $root . SlideImage::DIR;
        \mkdir($dir . 'old-title/webp', 0755, true);
        foreach (['beach-hero', 'beach-hero-mobile'] as $file) {
            \touch($dir . 'old-title/' . $file . '.jpg');
            \touch($dir . 'old-title/webp/' . $file . '.webp');
        }
        \touch($dir . 'old-title/other.jpg'); // Another slide's picture sharing the folder

        $moved = SlideImage::move(name: 'old-title/beach-hero', title: 'New Title', root: $root, aloneInFolder: false);

        $this->assertSame('new-title/beach-hero', $moved);
        $this->assertFileExists($dir . 'new-title/beach-hero-mobile.jpg');
        $this->assertFileExists($dir . 'new-title/webp/beach-hero.webp');
        $this->assertFileDoesNotExist($dir . 'old-title/beach-hero.jpg');
        $this->assertFileExists($dir . 'old-title/other.jpg');
        $this->assertSame('old-title/other', SlideImage::move(name: 'old-title/other', title: 'Old Title', root: $root, aloneInFolder: true));

        \touch($dir . 'new-title/.DS_Store');
        SlideImage::remove(name: 'new-title/beach-hero', root: $root);
        $this->assertDirectoryDoesNotExist($dir . 'new-title');
        $this->assertSame('3fa85f6457174562', SlideImage::move(name: '3fa85f6457174562', title: 'Anything', root: $root, aloneInFolder: true));

        \unlink($dir . 'old-title/other.jpg');
        \rmdir($dir . 'old-title');
        \rmdir($dir);
        \rmdir($root . '/assets/images');
        \rmdir($root . '/assets');
        \rmdir($root);
    }

    public function test_a_slide_alone_in_its_folder_has_the_whole_folder_renamed_unless_the_new_one_exists(): void
    {
        $root = \sys_get_temp_dir() . '/carousel-rename-' . \uniqid();
        $dir = $root . SlideImage::DIR;
        \mkdir($dir . 'old-title/webp', 0755, true);
        \touch($dir . 'old-title/pic.jpg');
        \touch($dir . 'old-title/webp/pic.webp');

        $this->assertSame('new-title/pic', SlideImage::move(name: 'old-title/pic', title: 'New Title', root: $root, aloneInFolder: true));
        $this->assertDirectoryDoesNotExist($dir . 'old-title');
        $this->assertFileExists($dir . 'new-title/webp/pic.webp');

        \mkdir($dir . 'taken');
        \touch($dir . 'taken/pic.jpg');
        $this->assertSame('taken/pic-2', SlideImage::move(name: 'new-title/pic', title: 'Taken', root: $root, aloneInFolder: true));
        $this->assertFileExists($dir . 'taken/pic.jpg');
        $this->assertFileExists($dir . 'taken/pic-2.jpg');
        $this->assertDirectoryDoesNotExist($dir . 'new-title');

        foreach (['taken/pic.jpg', 'taken/pic-2.jpg', 'taken/webp/pic-2.webp'] as $file) @\unlink($dir . $file);
        foreach (['taken/webp', 'taken', ''] as $folder) @\rmdir($dir . $folder);
        \rmdir($root . '/assets/images');
        \rmdir($root . '/assets');
        \rmdir($root);
    }

    public function test_an_older_flat_picture_moves_into_its_folder_and_gets_its_list_and_grid_copies(): void
    {
        $root = \sys_get_temp_dir() . '/carousel-flat-' . \uniqid();
        $dir = $root . SlideImage::DIR;
        \mkdir($dir, 0755, true);
        \imagejpeg(\imagecreatetruecolor(1920, 600), $dir . 'old-pic.jpg');
        \imagejpeg(\imagecreatetruecolor(960, 300), $dir . 'old-pic-mobile.jpg');

        $moved = SlideImage::move(name: 'old-pic', title: 'Spring Sale', root: $root, aloneInFolder: false);
        SlideImage::ensureThumbs(name: $moved, root: $root);

        $this->assertSame('spring-sale/old-pic', $moved);
        $this->assertFileDoesNotExist($dir . 'old-pic.jpg');
        $this->assertFileExists($dir . 'spring-sale/old-pic-mobile.jpg');
        $this->assertSame(136, \getimagesize($dir . 'spring-sale/old-pic-list.jpg')[0]);
        $this->assertSame(240, \getimagesize($dir . 'spring-sale/old-pic-grid.jpg')[0]);

        SlideImage::remove(name: $moved, root: $root);
        $this->assertDirectoryDoesNotExist($dir . 'spring-sale');
        \rmdir($dir);
        \rmdir($root . '/assets/images');
        \rmdir($root . '/assets');
        \rmdir($root);
    }

    public function test_slides_sharing_a_picture_keep_their_files_when_one_of_them_follows_its_title(): void
    {
        $root = \sys_get_temp_dir() . '/carousel-shared-' . \uniqid();
        $dir = $root . SlideImage::DIR;
        \mkdir($dir, 0755, true);
        \touch($dir . 'shared-pic.jpg');
        \touch($dir . 'shared-pic-mobile.jpg');

        $moved = SlideImage::move(name: 'shared-pic', title: 'Christmas', root: $root, aloneInFolder: false, shared: true);

        $this->assertSame('christmas/shared-pic', $moved);
        $this->assertFileExists($dir . 'christmas/shared-pic.jpg');
        $this->assertFileExists($dir . 'shared-pic.jpg');
        $this->assertFileExists($dir . 'shared-pic-mobile.jpg');

        foreach (['shared-pic.jpg', 'shared-pic-mobile.jpg', 'christmas/shared-pic.jpg', 'christmas/shared-pic-mobile.jpg'] as $file) \unlink($dir . $file);
        \rmdir($dir . 'christmas');
        \rmdir($dir);
        \rmdir($root . '/assets/images');
        \rmdir($root . '/assets');
        \rmdir($root);
    }

    public function test_a_slide_without_dates_is_always_live(): void
    {
        $this->assertTrue(SlideSchedule::isLive(start: null, end: null, recurring: false, today: '2026-06-15'));
    }

    public function test_dates_bound_a_slide_inclusive_of_both_days(): void
    {
        $live = fn(string $today): bool => SlideSchedule::isLive(start: '2026-11-20', end: '2026-11-30', recurring: false, today: $today);

        $this->assertFalse($live('2026-11-19'));
        $this->assertTrue($live('2026-11-20'));
        $this->assertTrue($live('2026-11-30'));
        $this->assertFalse($live('2026-12-01'));
    }

    public function test_either_date_can_be_left_open(): void
    {
        $this->assertTrue(SlideSchedule::isLive(start: '2026-01-01', end: null, recurring: false, today: '2030-01-01'));
        $this->assertTrue(SlideSchedule::isLive(start: null, end: '2026-12-31', recurring: false, today: '2020-01-01'));
        $this->assertFalse(SlideSchedule::isLive(start: null, end: '2026-12-31', recurring: false, today: '2027-01-01'));
    }

    public function test_a_recurring_slide_ignores_the_year_and_can_wrap_new_year(): void
    {
        $blackFriday = fn(string $today): bool => SlideSchedule::isLive(start: '2024-11-20', end: '2024-11-30', recurring: true, today: $today);
        $christmas = fn(string $today): bool => SlideSchedule::isLive(start: '2024-12-20', end: '2025-01-05', recurring: true, today: $today);

        $this->assertTrue($blackFriday('2031-11-25'));
        $this->assertFalse($blackFriday('2031-12-01'));
        $this->assertTrue($christmas('2031-12-28'));
        $this->assertTrue($christmas('2032-01-03'));
        $this->assertFalse($christmas('2032-06-01'));
    }

    public function test_the_admin_label_says_why_a_slide_is_not_live(): void
    {
        $label = fn(string $today, bool $recurring = false): string => SlideSchedule::label(start: '2026-11-20', end: '2026-11-30', recurring: $recurring, today: $today);

        $this->assertSame('Live', $label('2026-11-25'));
        $this->assertSame('Scheduled', $label('2026-11-01'));
        $this->assertSame('Ended', $label('2026-12-15'));
        $this->assertSame('Off season', $label('2026-06-01', true));
    }

    public function test_only_web_addresses_and_site_paths_are_valid_links(): void
    {
        $this->assertTrue(SlideText::isValidLink('https://example.com/a'));
        $this->assertTrue(SlideText::isValidLink('/shop/solar-panels/'));
        $this->assertFalse(SlideText::isValidLink('//evil.example'));
        $this->assertFalse(SlideText::isValidLink('javascript:alert(1)'));
        $this->assertFalse(SlideText::isValidLink('ftp://example.com'));
    }

    public function test_dates_must_be_real_calendar_dates(): void
    {
        $this->assertTrue(SlideText::isDate('2026-02-28'));
        $this->assertFalse(SlideText::isDate('2026-02-30'));
        $this->assertFalse(SlideText::isDate('28/02/2026'));
    }

    public function test_the_size_defaults_and_is_read_back_from_its_file(): void
    {
        $path = \sys_get_temp_dir() . '/carousel-size-' . \uniqid() . '.json.php';

        $this->assertSame(['width' => 1920, 'height' => 600], CarouselConfig::get($path));

        CarouselConfig::save(width: 1200, height: 400, path: $path);
        $this->assertSame(['width' => 1200, 'height' => 400], CarouselConfig::get($path));

        \unlink($path);
        $this->assertFalse(CarouselConfig::validSide(50));
        $this->assertFalse(CarouselConfig::validSide(9000));
    }

    public function test_active_returns_live_slides_in_order_and_skips_off_and_binned_ones(): void
    {
        $slide = new Slide($this->db);
        $first = $this->makeSlide($slide, 'zzFirst', 1);
        $second = $this->makeSlide($slide, 'zzSecond', 2);
        $off = $this->makeSlide($slide, 'zzOff', 3);
        $binned = $this->makeSlide($slide, 'zzBinned', 4);
        $slide->setEnabled(id: $off, enabled: false);
        $slide->softDelete($binned);

        $titles = \array_column((new Carousel($this->db, '/var/www/html'))->active(today: '2026-06-15'), 'title');
        $mine = \array_values(\array_filter($titles, static fn(string $t): bool => \str_starts_with($t, 'zz')));

        $this->assertSame(['zzFirst', 'zzSecond'], $mine);
        $this->assertGreaterThan(0, $first + $second);
    }

    public function test_an_exclusive_slide_hides_the_others_while_it_is_live(): void
    {
        $slide = new Slide($this->db);
        $this->makeSlide($slide, 'zzNormal', 1);
        $this->makeSlide($slide, 'zzSale', 2, ['scheduleStart' => '2026-11-20', 'scheduleEnd' => '2026-11-30', 'exclusive' => true]);

        $during = \array_column((new Carousel($this->db, '/var/www/html'))->active(today: '2026-11-25'), 'title');
        $before = \array_column((new Carousel($this->db, '/var/www/html'))->active(today: '2026-11-01'), 'title');

        $this->assertSame(['zzSale'], \array_values(\array_filter($during, static fn(string $t): bool => \str_starts_with($t, 'zz'))));
        $this->assertContains('zzNormal', $before);
        $this->assertNotContains('zzSale', $before);
    }

    public function test_a_slide_links_to_a_page_a_url_or_nothing(): void
    {
        $slide = new Slide($this->db);
        $pageId = (int) $slide->listPages()[0]['id'];
        $pageUrl = \trim((string) $slide->listPages()[0]['url'], '/');
        $this->makeSlide($slide, 'zzPage', 1, ['linkType' => 'page', 'linkPageId' => $pageId]);
        $this->makeSlide($slide, 'zzUrl', 2, ['linkType' => 'url', 'linkUrl' => 'https://example.com/x']);
        $this->makeSlide($slide, 'zzNone', 3);

        $byTitle = [];
        foreach ((new Carousel($this->db, '/var/www/html'))->active(today: '2026-06-15') as $row) {
            $byTitle[$row['title']] = $row;
        }

        $this->assertSame($pageUrl === '' ? '/' : '/' . $pageUrl . '/', $byTitle['zzPage']['url']);
        $this->assertSame('https://example.com/x', $byTitle['zzUrl']['url']);
        $this->assertNull($byTitle['zzNone']['url']);
        $this->assertSame(1920, $byTitle['zzNone']['image']['width']);
        $this->assertSame(960, $byTitle['zzNone']['imageMobile']['width']);
        $this->assertStringEndsWith('-mobile.jpg', $byTitle['zzNone']['imageMobile']['jpg']);
    }

    public function test_a_group_is_its_own_carousel_and_ungrouped_slides_are_the_default_one(): void
    {
        $slide = new Slide($this->db);
        $this->makeSlide($slide, 'zzMain', 1);
        $this->makeSlide($slide, 'zzOffer2', 3, ['groupName' => 'zzOffers']);
        $this->makeSlide($slide, 'zzOffer1', 2, ['groupName' => 'zzOffers']);
        $this->makeSlide($slide, 'zzSaleOnly', 4, ['groupName' => 'zzOther', 'exclusive' => true]);

        $carousel = new Carousel($this->db, '/var/www/html');
        $titles = static fn(array $slides): array => \array_values(\array_filter(\array_column($slides, 'title'), static fn(string $t): bool => \str_starts_with($t, 'zz')));

        $this->assertSame(['zzMain'], $titles($carousel->active(today: '2026-06-15')));
        $this->assertSame(['zzOffer1', 'zzOffer2'], $titles($carousel->active('zzOffers', '2026-06-15')));
        $this->assertSame(['zzSaleOnly'], $titles($carousel->active('zzOther', '2026-06-15')));
    }

    private function makeSlide(Slide $slide, string $title, int $order, array $overrides = []): int
    {
        $id = $slide->create($overrides + self::NO_SCHEDULE + [
            'title' => $title,
            'groupName' => null,
            'summary' => null,
            'buttonText' => null,
            'linkType' => 'none',
            'linkPageId' => null,
            'linkUrl' => null,
            'enabled' => true,
            'sortOrder' => $order,
            'notes' => null,
        ]);
        $slide->setImage(id: $id, imageFilename: 'test-' . $id, width: 1920, height: 600);

        return $id;
    }
}
