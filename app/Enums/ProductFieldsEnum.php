<?php declare(strict_types=1);

namespace UnitshotTheme\Enums;

enum ProductFieldsEnum: string
{
    case Type               = 'product_type';
    case Sku                = 'sku';
    case InStock            = 'in_stock';
    case Status             = 'status';
    case Model              = 'model';
    case BasePrice          = 'base_price';
    case SalePrice          = 'sale_price';
    case ShortDescription   = 'short_description';
    case Description        = 'description';
    case Gallery            = 'gallery';
    case Files              = 'files';
    case Attributes         = 'attributes';
    case AttributeOptions   = 'attribute_options'; // Per-product флаги назначенных атрибутов: { taxonomy: {in_variations, main} }.
    case Variations         = 'variations';
    case DefaultAttrs       = 'default_attributes';
    case SetItems           = 'set_items'; // Состав набора (complete): упорядоченные каноничные id товаров-членов.
    case SetMember          = 'set_member'; // Зеркало членов набора (по строке на товар) - для реверс-поиска «в каких наборах товар».
    case PopularProducts    = 'popular_products'; // Рекомендованные товары для блока «Схожі товари» на странице товара (каноничные id).
    case Parameters         = 'parameters'; // Свободные параметры: [{term: каноничный id терма product_parameter, value, main}]. Значение общее между языками, имя берётся из терма.
    case PriceIndex         = 'price_index'; // Денормализованная числовая цена к оплате (display_price) - для фильтра/сортировки по цене в каталоге (у вариативных цена иначе в массиве вариаций).

    /** Protected post-meta (префикс «_» = скрытая мета). */
    public function meta(): string { return '_us_' . $this->value; }
}
