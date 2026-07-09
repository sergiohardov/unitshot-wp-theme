<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Blocks;

use UnitshotTheme\DTO\ImageDTO;

class SliderReviewsController extends AbstractController
{
    protected function get_template_path(): string
    {
        return '@blocks/slider-reviews.twig';
    }

    protected function prepare_fields(array $fields): array
    {
        return [
            'block_id'  => !empty($fields['block_id'] ?? null)  ? $fields['block_id']   : '',
            'title'     => !empty($fields['title'] ?? null)     ? $fields['title']      : '',
            'slides'    => is_array($fields['slides'] ?? null)  ? $fields['slides']     : [],
        ];
    }

    public function get_context(): array
    {
        // review_index - порядковый номер отзыва среди слайдов, у которых он есть.
        // По нему фронт собирает галерею Fancybox и стартует с кликнутого слайда.
        $slides = [];
        $review_index = 0;

        foreach ($this->fields['slides'] as $slide) {
            $review = $this->slide_review($slide);

            $slides[] = [
                'name'         => !empty($slide['name'] ?? null)       ? $slide['name']                : '',
                'position'     => !empty($slide['position'] ?? null)   ? $slide['position']            : '',
                'image'        => is_numeric($slide['image'] ?? null)  ? new ImageDTO($slide['image']) : null,
                'review'       => $review,
                'review_index' => $review !== null ? $review_index++ : null,
            ];
        }

        return [
            'block_id'  => $this->fields['block_id'],
            'title'     => $this->fields['title'],
            'slides'    => $slides,
        ];
    }

    /**
     * Медиа отзыва для Fancybox: картинка или видео. null - отзыва нет.
     *
     * @param array<string, mixed> $slide
     * @return array{type: string, src: string}|null
     */
    private function slide_review(array $slide): ?array
    {
        $type = $slide['review_type'] ?? 'image';

        if ($type === 'image' && is_numeric($slide['review_image'] ?? null)) {
            $src = wp_get_attachment_image_url((int) $slide['review_image'], 'full');
            return $src ? ['type' => 'image', 'src' => $src] : null;
        }

        if ($type === 'video' && !empty($slide['review_video'] ?? null)) {
            return ['type' => 'video', 'src' => (string) $slide['review_video']];
        }

        return null;
    }
}

