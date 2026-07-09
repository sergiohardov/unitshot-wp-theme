<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Pages;

use Timber\Timber;
use UnitshotTheme\Classes\Breadcrumbs;
use UnitshotTheme\Classes\Pagination;
use UnitshotTheme\DTO\ImageDTO;
use WP_Query;

class BlogController
{
    private ?WP_Query $query = null;

    private function get_posts(): array
    {
        $paged = max(1, get_query_var('paged'), get_query_var('page'));
        $posts_per_page = (int) get_option('posts_per_page');

        $this->query = new WP_Query([
            'posts_per_page'      => $posts_per_page,
            'paged'               => $paged,
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'ignore_sticky_posts' => 1,
            'orderby'             => 'date',
            'order'               => 'DESC',
        ]);

        $posts = $this->query->get_posts();

        return array_map(function ($post) {
            return [
                'title'   => $post->post_title,
                'link'    => get_the_permalink($post->ID),
                'image'   => new ImageDTO(get_post_thumbnail_id($post->ID)),
                'caption' => get_the_excerpt($post->ID),
            ];
        }, $posts);
    }

    public function render(): void
    {
        // Сначала get_posts() - он заполняет $this->query, откуда Pagination берёт
        // max_num_pages (иначе зависимость держалась бы на порядке строк в массиве).
        $posts = $this->get_posts();

        Timber::render("@pages/blog.twig", [
            'breadcrumbs' => (new Breadcrumbs())->for_blog_page(),
            'posts'       => $posts,
            'pagination'  => (new Pagination((int) $this->query->max_num_pages))->build(),
        ]);
    }
}
