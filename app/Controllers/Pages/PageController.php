<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Pages;

use Timber\Timber;
use UnitshotTheme\Classes\Breadcrumbs;

class PageController
{
    private int $post_id;

    function __construct(int $post_id)
    {
        $this->post_id = $post_id;
    }

    public function render(): void
    {
        Timber::render("@pages/page.twig", [
            'breadcrumbs'   => (new Breadcrumbs())->for_page(),
            'title'         => get_the_title($this->post_id),
            'content'       => get_the_content($this->post_id),
        ]);
    }
}
