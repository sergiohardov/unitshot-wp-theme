<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Blocks;

use UnitshotTheme\Classes\Helpers;

class PopularProductsController extends AbstractController
{
    protected function get_template_path(): string
    {
        return '@blocks/popular-products.twig';
    }

    protected function prepare_fields(array $fields): array
    {
        return [
            'block_id'  => !empty($fields['block_id'] ?? null)    ? $fields['block_id']    : '',
            'title'     => !empty($fields['title'] ?? null)       ? $fields['title']       : '',
            'products'  => is_array($fields['products'] ?? null)  ? $fields['products']    : []
        ];
    }

    // TODO: Заменить ссылку на бренд, проверить мультиязычность ссылки
    protected function get_context(): array
    {
        $products = [];

        foreach ($this->fields['products'] as $id) {
            if(get_post_status((int) $id) !== 'publish') continue;

            $product = Helpers::get_product_dto_by_id((int) $id);
            if($product === null) continue;

            $products[] = Helpers::product_card_dto($product);
        }

        return [
            'block_id'  => $this->fields['block_id'],
            'title'     => $this->fields['title'],
            'products'  => $products
        ];
    }
}

