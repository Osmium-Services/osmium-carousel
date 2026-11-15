<?php

declare(strict_types=1);

namespace Tests\Services\OsmiumCarousel;

use Osmium\Services\OsmiumCarousel\Models\CarouselAdminBar;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/CarouselAdminBar.php';

/**
 * The staff bar's carousel answers: a page that asked for slides announces it and gets an "Edit carousel" link.
 */
final class CarouselAdminBarTest extends TestCase
{
    protected function setUp(): void
    {
        CarouselAdminBar::forgetShown();
    }

    public function test_a_page_that_did_not_ask_for_slides_announces_nothing(): void
    {
        $this->assertNull(CarouselAdminBar::context(['data' => []]));
    }

    public function test_a_page_that_asked_for_slides_announces_it(): void
    {
        CarouselAdminBar::noteShown();

        $this->assertSame('carousel:all', CarouselAdminBar::context(['data' => []]));
    }

    public function test_the_link_opens_the_carousel_admin_with_the_services_own_icon(): void
    {
        $links = CarouselAdminBar::links(['context' => 'carousel:all']);

        $this->assertSame('carousel/', $links[0]['path']);
        $this->assertSame('Edit carousel', $links[0]['label']);
        $this->assertSame('bx-images', $links[0]['icon']);
        $this->assertSame(90, $links[0]['order']); // Supporting: after the page's own and any main service link
    }

    public function test_other_contexts_get_no_links(): void
    {
        $this->assertNull(CarouselAdminBar::links(['context' => 'product:5']));
        $this->assertNull(CarouselAdminBar::links([]));
    }
}
