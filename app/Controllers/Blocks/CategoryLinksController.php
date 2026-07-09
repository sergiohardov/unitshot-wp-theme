<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Blocks;

use UnitshotTheme\DTO\BrandDTO;
use UnitshotTheme\DTO\CategoryDTO;
use UnitshotTheme\DTO\ImageDTO;
use UnitshotTheme\Enums\TaxonomiesEnum;

class CategoryLinksController extends AbstractController
{
    protected function get_template_path(): string
    {
        return '@blocks/category-links.twig';
    }

    protected function prepare_fields(array $fields): array
    {
        return [
            'block_id'  => !empty($fields['block_id'] ?? null)  ? $fields['block_id']   : '',
            'title'     => !empty($fields['title'] ?? null)     ? $fields['title']      : '',
            'pages'     => is_array($fields['pages'] ?? null)   ? $fields['pages']      : [],
        ];
    }

    protected function get_context(): array
    {
        $categories = [];

        foreach ($this->fields['pages'] as $key => $page) {
            $type   = !empty($page['type']) ? $page['type'] : 'page';
            $title  = '';
            $image  = [];
            $link   = '';

            if ($type === 'page' && is_numeric($page['page'])) {
                $title = get_the_title($page['page']);
                $image = (new ImageDTO(get_post_thumbnail_id($page['page'])))->get_array();
                $link  = get_the_permalink($page['page']);
            }

            if ($type === 'category' && is_numeric($page['category'])) {
                $category = get_term_by('id', $page['category'], TaxonomiesEnum::ProductCategory->value);

                if (is_a($category, 'WP_Term')) {
                    $categoryDTO = CategoryDTO::from_wp_term($category);
                    $title       = $categoryDTO->name;
                    $image       = $categoryDTO->image->get_array();
                    $link        = $categoryDTO->url;
                }
            }

            if (empty($title) || empty($link)) continue;

            $categories[] = [
                'title' => $title,
                'image' => $image,
                'link'  => $link,
                'brands'    => array_map(function ($brand) {
                    $term = get_term_by('id', $brand, TaxonomiesEnum::ProductBrand->value);
                    if (!is_a($term, 'WP_Term')) return [];

                    $brand_dto = BrandDTO::from_wp_term($term);

                    return [
                        'link'  => $brand_dto->url,
                        'image' => $brand_dto->image,
                    ];
                }, $page['brands'] ?? []),
            ];
        }

        return [
            'block_id'      => $this->fields['block_id'],
            'title'         => $this->fields['title'],
            'categories'    => $categories,
        ];
    }
}
