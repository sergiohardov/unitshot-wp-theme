<?php declare(strict_types=1);

use Timber\Timber;
use UnitshotTheme\Classes\ProductQuery;
use UnitshotTheme\Controllers\Pages\BlogController;
use UnitshotTheme\Controllers\Pages\CatalogController;
use UnitshotTheme\Controllers\Pages\HomeController;
use UnitshotTheme\Controllers\Pages\PageController;
use UnitshotTheme\Controllers\Pages\PostController;
use UnitshotTheme\Controllers\Pages\ProductController;
use UnitshotTheme\Enums\TaxonomiesEnum;
use UnitshotTheme\Enums\TypesEnum;

$id = get_queried_object_id();

// Роутинг шаблонов: первое истинное условие выигрывает, порядок задаёт приоритет
// (специфичные проверки выше общих). Архивы каталога (категория/бренд/поиск) -
// один CatalogController; объект запроса там WP_Term, поэтому до singular-проверок.
match (true) {
    is_front_page()                                => (new HomeController())->render(),
    is_home()                                      => (new BlogController())->render(),
    is_search()                                    => (new CatalogController(ProductQuery::SCOPE_SEARCH))->render(),
    is_tax(TaxonomiesEnum::ProductCategory->value) => (new CatalogController(ProductQuery::SCOPE_CATEGORY, get_queried_object()))->render(),
    is_tax(TaxonomiesEnum::ProductBrand->value)    => (new CatalogController(ProductQuery::SCOPE_BRAND, get_queried_object()))->render(),
    is_singular(TypesEnum::Product->value)         => (new ProductController($id))->render(),
    is_singular(TypesEnum::Post->value)            => (new PostController($id))->render(),
    is_page()                                      => (new PageController($id))->render(),
    is_404()                                       => Timber::render('@pages/404.twig', []),
    default                                        => Timber::render('@pages/index.twig', []),
};
