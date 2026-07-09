<?php declare(strict_types=1);

namespace UnitshotTheme\DTO;

use UnitshotTheme\Classes\Fields;
use WP_Term;

class BrandDTO
{
    function __construct(
        public int      $id,
        public string   $name,
        public string   $url,
        public ImageDTO $image,
        public array    $text_image = [],
    )
    {
    }

    public static function from_wp_term(WP_Term $brand): self
    {
        $fields = Fields::get_brand($brand->term_id);

        return new self(
            $brand->term_id,
            $brand->name,
            get_term_link($brand->term_id),
            new ImageDTO($fields['logo']),
            $fields['text_image'] ?? [],
        );
    }
}
