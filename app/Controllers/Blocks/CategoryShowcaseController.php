<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Blocks;

use UnitshotTheme\Classes\Helpers;
use UnitshotTheme\DTO\BrandDTO;
use UnitshotTheme\DTO\CategoryDTO;
use UnitshotTheme\Enums\TaxonomiesEnum;

class CategoryShowcaseController extends AbstractController
{
    protected function get_template_path(): string
    {
        return '@blocks/category-showcase.twig';
    }

    protected function prepare_fields(array $fields): array
    {
        return [
            'block_id' => !empty($fields['block_id'] ?? null)       ? $fields['block_id']       : '',
            'category' => is_numeric($fields['category'] ?? null)   ? (int)$fields['category']  : null,
            'products' => is_array($fields['products'] ?? null)     ? $fields['products']       : [],
        ];
    }

    // TODO: Заменить ссылку на категорию, (на страницу поиска) проверить мультиязычность ссылки
    protected function get_context(): array
    {
        $term = get_term_by('id', $this->fields['category'], TaxonomiesEnum::ProductCategory->value);
        if (!is_a($term, 'WP_Term')) return [];

        $term       = CategoryDTO::from_wp_term($term, true);
        $products   = [];

        foreach ($this->fields['products'] as $id) {
            if(get_post_status((int) $id) !== 'publish') continue;

            $product = Helpers::get_product_dto_by_id((int) $id);
            if($product === null) continue;

            $products[] = Helpers::product_card_dto($product);
        }

        $categories = array_map(function ($child) {
            return [
                'title' => $child->name,
                'link'  => $child->url,
                'image' => $child->image->get_array(),
            ];
        }, $term->children);
        $brands = [];

        foreach ($products as $product) {
            $product_brands = get_the_terms($product['id'], TaxonomiesEnum::ProductBrand->value);

            foreach ($product_brands as $item) {
                if (!is_a($item, 'WP_Term')) continue;
                $brand = BrandDTO::from_wp_term($item);

                $brands[] = [
                    'link'  => $brand->url,
                    'image' => $brand->image->get_array(),
                ];
            }
        }

        return [
            'block_id'      => $this->fields['block_id'],
            'link'          => $term->url,
            'title'         => $term->name,
            'categories'    => $categories,
            'brands'        => array_unique($brands, SORT_REGULAR),
            'products'      => $products,
        ];
    }

    /**
     * Фильтрация товаров по категории в ACF поле
     *
     * @see Acf
     * @param array $args
     * @param array $field
     * @param int|string $post_id
     * @return array
     */
    public static function acf_filter_products_by_category(array $args, array $field, $post_id): array
    {
        $category_id = $_POST['category_id'] ?? $_GET['category_id'] ?? null;

        if (is_array($category_id)) $category_id = reset($category_id);

        if ($category_id && $category_id !== 'undefined' && (int)$category_id > 0) {
            $args['tax_query'] = [
                [
                    'taxonomy'  => TaxonomiesEnum::ProductCategory->value,
                    'field'     => 'term_id',
                    'terms'     => (int)$category_id,
                ]
            ];
        }

        return $args;
    }

    /**
     * @see Acf
     * @return void
     */
    public static function acf_admin_enqueue_script(): void
    {
        wp_enqueue_script('unitshot-acf-block-category-showcase', UNITSHOT_APP_SCRIPTS_URI . '/acf-block-category-showcase.js', ['acf-input'], false, true);
    }
}

