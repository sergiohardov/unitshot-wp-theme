<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Blocks;

use UnitshotTheme\DTO\BrandDTO;
use UnitshotTheme\Enums\TaxonomiesEnum;

class BrandLogosController extends AbstractController
{
    protected function get_template_path(): string
    {
        return '@blocks/brand-logos.twig';
    }

    protected function prepare_fields(array $fields): array
    {
        return [
            'block_id'  => !empty($fields['block_id'] ?? null)  ? $fields['block_id']   : '',
            'brands'    => is_array($fields['brands'] ?? null)  ? $fields['brands']     : [],
        ];
    }

    // TODO: Заменить ссылку на бренд, проверить мультиязычность ссылки
    protected function get_context(): array
    {
        return [
            'block_id'  => $this->fields['block_id'],
            'brands'    => array_map(function ($brand_id) {
                $brand = get_term_by('id', $brand_id, TaxonomiesEnum::ProductBrand->value);;
                if (!is_a($brand, 'WP_Term')) return [];

                $brand = BrandDTO::from_wp_term($brand);

                return [
                    'link' => $brand->url,
                    'logo' => $brand->image->get_array()
                ];
            }, $this->fields['brands'])
        ];
    }
}

