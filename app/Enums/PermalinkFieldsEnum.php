<?php declare(strict_types=1);

namespace UnitshotTheme\Enums;

enum PermalinkFieldsEnum: string
{
    case Category   = 'us_product_category_base';
    case Brand      = 'us_product_brand_base';
    case Blog       = 'us_post_base';

    public function get_label(): string
    {
        return match ($this) {
            self::Category  => 'Префикс для категории продуктов',
            self::Brand     => 'Префикс для брендов продуктов',
            self::Blog      => 'Префикс для постов блога',
        };
    }
}
