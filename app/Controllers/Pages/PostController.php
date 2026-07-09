<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Pages;

use Timber\Timber;
use UnitshotTheme\Classes\Breadcrumbs;
use UnitshotTheme\Classes\Fields;
use UnitshotTheme\Classes\Helpers;
use UnitshotTheme\DTO\ImageDTO;

class PostController
{
    private int $post_id;
    private array $fields;

    function __construct(int $post_id)
    {
        $this->post_id = $post_id;
        $this->fields = Fields::get_post($post_id);
    }

    private function get_more_posts(): array
    {
        return array_map(function ($post_id) {
            $article = get_post($post_id);
            if (!is_a($article, 'WP_Post')) return [];

            return [
                'date'  => Helpers::post_date($article->ID),
                'link'  => get_the_permalink($article->ID),
                'title' => $article->post_title,
            ];
        }, $this->fields['more_posts']);
    }

    public function render(): void
    {
        Timber::render("@pages/post.twig", [
            'breadcrumbs'   => (new Breadcrumbs())->for_post(),
            'title'         => get_the_title($this->post_id),
            'date'          => Helpers::post_date($this->post_id),
            'image'         => (new ImageDTO(get_post_thumbnail_id($this->post_id)))->get_array(),
            'content'       => get_the_content($this->post_id),
            'more_posts'    => $this->get_more_posts(),
        ]);
    }
}
