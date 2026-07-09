<?php declare(strict_types=1);

namespace UnitshotTheme\Enums;

enum TaxonomiesEnum: string
{
    case ProductBrand           = 'product_brand';
    case ProductCategory        = 'product_category';
    case ProductParameter       = 'product_parameter';
    case ProductAttributePrefix = 'product_attribute_';
}
