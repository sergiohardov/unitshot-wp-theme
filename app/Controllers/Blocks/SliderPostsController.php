<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Blocks;

use UnitshotTheme\Classes\Helpers;
use UnitshotTheme\DTO\ImageDTO;

class SliderPostsController extends AbstractController
{
    protected function get_template_path(): string
    {
        return '@blocks/slider-posts.twig';
    }

    protected function prepare_fields(array $fields): array
    {
        return [
            'block_id'  => !empty($fields['block_id'] ?? null)  ? $fields['block_id']   : '',
            'title'     => !empty($fields['title'] ?? null)     ? $fields['title']      : '',
            'posts'     => is_array($fields['posts'] ?? null)   ? $fields['posts']      : [],
        ];
    }

    public function get_context(): array
    {
        return [
            'block_id'  => $this->fields['block_id'],
            'title'     => $this->fields['title'],
            'posts'     => array_map(function ($item) {
                $article = get_post($item);
                if (!is_a($article, 'WP_Post')) return [];

                return [
                    'image' => new ImageDTO(get_post_thumbnail_id($article->ID)),
                    'date'  => Helpers::post_date($article->ID),
                    'link'  => get_the_permalink($article->ID),
                    'title' => $article->post_title,
                ];
            }, $this->fields['posts']),
        ];
    }
}

