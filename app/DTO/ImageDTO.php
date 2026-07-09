<?php declare(strict_types=1);

namespace UnitshotTheme\DTO;

class ImageDTO
{
    public ?string $src = null;
    public ?string $alt = null;

    public function __construct(?int $image_id)
    {
        if (!is_numeric($image_id)) return;

        $this->src = $this->get_src($image_id);
        $this->alt = $this->get_alt($image_id);
    }

    private function get_src($image_id): ?string
    {
        $src = wp_get_attachment_image_url($image_id, 'full');
        return is_string($src) ? $src : null;
    }

    private function get_alt($image_id): ?string
    {
        $alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);
        return is_string($alt) ? $alt : null;
    }

    public function get_array(): array {
        return [
            'src' => $this->src,
            'alt' => $this->alt,
        ];
    }
}
