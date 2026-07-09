<?php declare(strict_types=1);

namespace UnitshotTheme\DTO;

use UnitshotTheme\Classes\Fields;
use WP_Term;

class CategoryDTO
{
    function __construct(
        public int      $id,
        public string   $name,
        public string   $url,
        public ImageDTO $image,
        public array    $text_image = [],
        public array    $children = [],
    )
    {
    }

    public static function from_wp_term(WP_Term $term, bool $with_children = false): self
    {
        $fields     = Fields::get_category($term->term_id);
        $children   = [];

        if ($with_children) {
            $child_terms = get_terms([
                'taxonomy'   => $term->taxonomy,
                'parent'     => $term->term_id,
                'hide_empty' => false,
            ]);

            if (!is_wp_error($child_terms)) {
                foreach ($child_terms as $child) {
                    $children[] = self::from_wp_term($child, true); // рекурсия
                }
            }
        }

        return new self(
            id:         $term->term_id,
            name:       $term->name,
            url:        get_term_link($term->term_id),
            image:      new ImageDTO($fields['logo']),
            text_image: $fields['text_image'] ?? [],
            children:   $children,
        );
    }
}
