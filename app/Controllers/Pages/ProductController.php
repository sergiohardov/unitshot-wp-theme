<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Pages;

use Timber\Timber;
use UnitshotTheme\Classes\Breadcrumbs;
use UnitshotTheme\Classes\Fields;
use UnitshotTheme\Classes\Helpers;
use UnitshotTheme\Classes\Products;
use UnitshotTheme\Controllers\Blocks\FeedbackInfoController;
use UnitshotTheme\DTO\ProductDTO;
use UnitshotTheme\Enums\ProductFieldsEnum;

class ProductController
{
    private ?ProductDTO $product;

    function __construct(int $post_id)
    {
        $this->product = Helpers::get_product_dto_by_id($post_id);
    }

    public function render(): void
    {
        if (!$this->product) {
            Timber::render('@pages/index.twig', []);
            return;
        }

        $product = $this->product;
        $in_stock = $product->in_stock;

        // Описание хранится «чисто» (как post_content): без <p>. Восстанавливаем
        // абзацы/переносы на выводе через wpautop - то, что делает the_content.
        // Короткое - у блока с вариативностью/кнопками; полное - в аккордеоне «Опис товару».
        $short_description = wpautop($product->short_description);
        $description = wpautop($product->description);

        // База обоих блоков: бренд + категория (автоподтягиваемые характеристики).
        // Категории (родитель -> ребёнок, уже отсортированы в DTO) - ОДНОЙ строкой
        // через запятую: «Кофемашини, Без вагів», а не двумя строками «Категорія».
        $category_names = array_map(fn($category) => $category->name, $product->categories);
        $base_stats = array_merge(
            array_map(fn($brand) => ['name' => pll__('Бренд'), 'value' => $brand->name], $product->brands),
            $category_names ? [['name' => pll__('Категорія'), 'value' => implode(', ', $category_names)]] : [],
        );

        $attr_stat  = fn($attr) => ['name' => $attr['label'], 'value' => implode(', ', $attr['values'])];
        $param_stat = fn($param) => ['name' => $param['label'], 'value' => $param['value']];

        // «Основні характеристики» - база + атрибуты и параметры с галочкой «Основная».
        $main_stats = array_merge(
            $base_stats,
            array_map($attr_stat, array_filter($product->attributes, fn($attr) => !empty($attr['main']))),
            array_map($param_stat, array_filter($product->parameters, fn($param) => !empty($param['main']))),
        );

        // «Характеристики» (аккордеон) - база + все атрибуты + все параметры.
        $all_stats = array_merge(
            $base_stats,
            array_map($attr_stat, $product->attributes),
            array_map($param_stat, $product->parameters),
        );

        $block_feedback_controller = new FeedbackInfoController(Fields::get_settings_pages()['product_feedback_info']);

        Timber::render('@pages/product.twig', [
            'breadcrumbs' => (new Breadcrumbs())->for_products(),
            'title' => $product->name,
            'gallery' => array_map(fn($image) => $image->get_array(), $product->gallery),
            'is_simple' => $product->is_simple(),
            'is_variable' => $product->is_variable(),
            'is_complete' => $product->is_complete(),
            'set_items' => array_map(fn($item) => $this->set_member_card($item), $product->set_items),
            'price' => $product->display_price(),
            'old_price' => $product->display_old_price(),
            'price_from' => $product->price_is_from(),
            'sku' => $product->sku,
            // Бейдж наличия: «В наявності» (зелёный --success) / «Немає в наявності».
            'status' => [
                'text' => $in_stock ? pll__('В наявності') : pll__('Немає в наявності'),
                'modify' => $in_stock ? 'success' : '',
            ],
            'properties' => $this->properties_view(),
            'variation_json' => $this->variation_json(),
            'short_description' => $short_description,
            'stats' => $main_stats,
            'info' => [
                'stats' => $all_stats,
                'description' => $description,
                'files' => array_map(fn($file) => $file->get_array(), $product->files),
            ],
            // Наборы, в которые входит товар (для слайдера на странице товара-члена).
            'sets' => $product->is_complete() ? [] : array_map(fn($set) => [
                'url' => $set->url,
                'title' => $set->name,
                'price' => $set->display_price(),      // цена комплекта
                'old_price' => $set->display_old_price(),  // Σ цен членов
                'items' => array_map(
                    fn($item) => $this->set_member_card($item),
                    array_values(array_filter($set->set_items, fn($item) => $item['product']->id !== $product->id)),
                ),
            ], Helpers::get_sets_for_product($product->id)),
            'block_feedback' => $block_feedback_controller->get_context(),
            'popular_products' => $this->popular_products_cards(),
        ]);
    }

    /**
     * Карточки рекомендованных товаров (блок «Схожі товари»). Берём каноничные id
     * из меты, резолвим в текущий язык. Логика карточки повторяет
     * PopularProductsController
     *
     * @return array<int, array>
     */
    private function popular_products_cards(): array
    {
        $cards = [];
        foreach ((array) get_post_meta($this->product->id, ProductFieldsEnum::PopularProducts->meta(), true) as $canonical) {
            // Нет перевода на текущий язык - товар не показываем.
            $localized = Products::localized_post_or_null((int) $canonical);
            if ($localized === null) continue;

            $product = Helpers::get_product_dto_by_id($localized);
            if ($product === null) continue;

            // Базовая карточка (теги/цена/изображение) + бренд/категория для этого блока.
            $cards[] = Helpers::product_card_dto($product);
        }
        return $cards;
    }

    /**
     * Карточка члена набора: картинка/имя/ссылка товара + цена выбранной в наборе
     * вариации (для variable) или собственная цена товара.
     *
     * @param array{product: ProductDTO, price: ?float, old_price: ?float} $item
     */
    private function set_member_card(array $item): array
    {
        $product = $item['product'];

        return [
            'image' => $product->gallery ? $product->gallery[0]->get_array() : null,
            'title' => $product->name,
            'url' => $product->url,
            'price' => $item['price'],
            'old_price' => $item['old_price'],
        ];
    }

    /** Оси вариаций как селектор значений (id каноничные - для Alpine-резолвера). */
    private function properties_view(): array
    {
        if (!$this->product->is_variable()) return [];

        return array_map(fn($axis) => [
            'taxonomy' => $axis['taxonomy'],
            'name' => $axis['label'],
            'values' => array_map(fn($term) => [
                'id' => $term['id'],
                'name' => $term['name'],
            ], $axis['terms']),
        ], $this->product->axes);
    }

    /** JSON для Alpine-резолвера: список осей + матрица вариаций (только цены; sku/наличие/статус - уровень товара). */
    private function variation_json(): string
    {
        $product = $this->product;

        // Оси и матрица - только у вариативного; product_id/is_variable нужны всегда
        // (кнопка «в кошик» работает для всех типов через ProductPage).
        $axes = $product->is_variable() ? array_map(fn($axis) => $axis['taxonomy'], $product->axes) : [];

        $variations = $product->is_variable() ? array_map(function ($variation) use ($product) {
            $eff = $product->variation_effective($variation); // единый источник правила скидки

            return [
                'key'        => $variation['key'] ?? '',     // ключ для добавления в корзину
                'attributes' => $variation['attributes'] ?? [],
                'price'      => $eff['price'],                // эффективная (к оплате)
                'old_price'  => $eff['old'],                 // зачёркнутая
            ];
        }, $product->variations) : [];

        return wp_json_encode([
            'product_id'  => $product->id,
            'is_variable' => $product->is_variable(),
            'axes'        => $axes,
            'variations'  => $variations,
            'defaults'    => (object) $product->default_attributes, // {taxonomy: term_id} для предвыбора
        ]) ?: '';
    }
}
