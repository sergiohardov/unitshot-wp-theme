<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Blocks;

use UnitshotTheme\Classes\Helpers;
use UnitshotTheme\DTO\ImageDTO;

class TextImageController extends AbstractController
{
    protected function get_template_path(): string
    {
        return '@blocks/text-image.twig';
    }

    protected function prepare_fields(array $fields): array
    {
        return [
            'block_id'      => !empty($fields['block_id'] ?? null)      ? $fields['block_id']       : '',
            'title'         => !empty($fields['title'] ?? null)         ? $fields['title']          : '',
            'description'   => !empty($fields['description'] ?? null)   ? $fields['description']    : '',
            'media_type'    => in_array($fields['media_type'] ?? null, ['image', 'posts'], true) ? $fields['media_type'] : 'image',
            'image'         => is_numeric($fields['image'] ?? null)     ? (int)$fields['image']     : null,
            'posts'         => is_array($fields['posts'] ?? null)       ? array_slice($fields['posts'], 0, 3) : [], // не больше 3 постов
            'is_hidden'     => is_bool($fields['is_hidden'] ?? null)    ? $fields['is_hidden']      : false,
        ];
    }

    public function get_context(): array
    {
        return [
            'block_id'      => $this->fields['block_id'],
            'title'         => $this->fields['title'],
            'description'   => $this->fields['description'],
            'media_type'    => $this->fields['media_type'],
            'image'         => new ImageDTO($this->fields['image']),
            'posts'         => $this->map_posts($this->fields['posts']),
            'hide_text'     => $this->fields['is_hidden'],
        ];
    }

    /**
     * Маппинг выбранных постов в данные карточки (картинка, дата, ссылка, заголовок).
     *
     * @param array<int> $ids
     * @return array<int, array{image: ImageDTO, date: string, link: string, title: string}>
     */
    private function map_posts(array $ids): array
    {
        return array_values(array_filter(array_map(function ($id) {
            $article = get_post($id);
            if (!is_a($article, 'WP_Post')) return null;

            return [
                'image' => new ImageDTO(get_post_thumbnail_id($article->ID)),
                'date'  => Helpers::post_date($article->ID),
                'link'  => get_the_permalink($article->ID),
                'title' => $article->post_title,
            ];
        }, $ids)));
    }
}

