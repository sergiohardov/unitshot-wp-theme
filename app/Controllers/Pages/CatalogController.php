<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Pages;

use Timber\Timber;
use UnitshotTheme\Classes\Breadcrumbs;
use UnitshotTheme\Classes\Currency;
use UnitshotTheme\Classes\Fields;
use UnitshotTheme\Classes\Helpers;
use UnitshotTheme\Classes\Pagination;
use UnitshotTheme\Classes\ProductAttributes;
use UnitshotTheme\Classes\ProductQuery;
use UnitshotTheme\Controllers\Blocks\FeedbackInfoController;
use UnitshotTheme\Controllers\Blocks\TextImageController;
use UnitshotTheme\DTO\BrandDTO;
use UnitshotTheme\DTO\CategoryDTO;
use UnitshotTheme\DTO\ImageDTO;
use UnitshotTheme\Enums\ProductFieldsEnum;
use UnitshotTheme\Enums\TaxonomiesEnum;
use WP_Term;

/**
 * Страница каталога: один шаблон (@pages/catalog.twig) на три контекста -
 * архив категории, архив бренда и страница поиска. Выборка/сортировка/фильтрация
 * идут через ProductQuery; фасеты фильтра строятся из товаров текущей области
 * (scope) по правилу «своя ось скрывается» (на бренде нет фасета брендов, на
 * категории - только подкатегории).
 *
 * Состояние фильтров живёт в GET (term_id в текущем языке + цена в текущей
 * валюте) - ссылка с активными фильтрами открывается напрямую.
 *
 * @package UnitshotTheme
 */
class CatalogController
{
    private string   $context;
    private ?WP_Term $term;
    private string   $search;

    public function __construct(string $context, ?WP_Term $term = null)
    {
        $this->context = $context;
        $this->term    = $term;
        $this->search  = $context === ProductQuery::SCOPE_SEARCH ? (string) get_search_query() : '';
    }

    public function render(): void
    {
        $filters    = $this->parse_filters();
        $scope_ids  = $this->scope_ids();

        $query = (new ProductQuery($this->scope_args(), $filters))->query();

        $products = [];
        foreach ($query->posts as $post) {
            $dto = Helpers::get_product_dto_by_id($post->ID);
            if ($dto) $products[] = Helpers::product_card_dto($dto);
        }

        $feedback_info_controller = new FeedbackInfoController(Fields::get_settings_pages()['catalog_feedback_info']);

        Timber::render('@pages/catalog.twig', [
            'breadcrumbs'   => (new Breadcrumbs())->for_catalog(),
            'title'         => $this->title(),
            'categories'    => $this->slider_categories(),
            'products'      => $products,
            'pagination'    => (new Pagination((int) $query->max_num_pages, $filters['paged']))->build(),
            'filters'       => $this->facets($scope_ids, $filters),
            'filter'        => $this->filter_meta(),
            'sort'          => $this->sort_view($filters['sort']),
            'feedback_info' => $feedback_info_controller->get_context(),
            'text_image'    => $this->text_image(),
        ]);
    }

    /**
     * @return array{type:string, term:int, search:string}
     */
    private function scope_args(): array
    {
        return [
            'type'   => $this->context,
            'term'   => $this->term ? (int) $this->term->term_id : 0,
            'search' => $this->search,
        ];
    }

    /**
     * GET -> нормализованные фильтры для ProductQuery. Цена приходит в текущей
     * валюте - переводим в базовую (индекс цены хранится в базовой).
     *
     * @return array<string, mixed>
     */
    private function parse_filters(): array
    {
        $rate = max(0.0001, Currency::get_current_currency()->value);

        $min_display = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float) $_GET['min_price'] : null;
        $max_display = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float) $_GET['max_price'] : null;

        $attributes = [];
        foreach ((array) ($_GET['attr'] ?? []) as $taxonomy => $ids) {
            $attributes[sanitize_key((string) $taxonomy)] = $this->ids($ids);
        }

        return [
            'brands'     => $this->ids($_GET['brand'] ?? []),
            'categories' => $this->ids($_GET['category'] ?? []),
            'attributes' => $attributes,
            'min_price'  => $min_display !== null ? $min_display / $rate : null,
            'max_price'  => $max_display !== null ? $max_display / $rate : null,
            'sort'       => isset($_GET['sort']) ? sanitize_key((string) $_GET['sort']) : ProductQuery::SORT_DEFAULT,
            // Номер страницы - из query var WP (нативная пагинация /page/N/ или ?paged=N).
            'paged'      => max(1, (int) get_query_var('paged')),
            'per_page'   => ProductQuery::PER_PAGE,
        ];
    }

    /**
     * GET-значение (массив из чекбоксов или строка-csv из ссылки) -> int[].
     *
     * @param mixed $raw
     * @return int[]
     */
    private function ids($raw): array
    {
        if (is_string($raw)) $raw = explode(',', $raw);
        return array_values(array_filter(array_map('intval', (array) $raw)));
    }

    /**
     * Все товары области (без пользовательских фасетов) - универсум значений для
     * фасетов и диапазона цены. Polylang фильтрует выборку по текущему языку.
     *
     * @return int[]
     */
    private function scope_ids(): array
    {
        $args = (new ProductQuery($this->scope_args(), ['per_page' => -1]))->args();
        $args['fields']        = 'ids';
        $args['no_found_rows'] = true;

        return array_map('intval', get_posts($args));
    }

    private function title(): string
    {
        if ($this->context === ProductQuery::SCOPE_SEARCH) return pll__('Результати пошуку') . ': ' . $this->search;
        return $this->term ? $this->term->name : '';
    }

    /**
     * Блок text-image терма (категория/бренд) - внизу страницы. Поля клонированы
     * в таксономию из группы блока; нормализуем под include text-image.twig.
     *
     * @return array<string, mixed>
     */
    private function text_image(): array
    {
        $block = [];

        if ($this->term && $this->context === ProductQuery::SCOPE_CATEGORY) $block = CategoryDTO::from_wp_term($this->term)->text_image;
        if ($this->term && $this->context === ProductQuery::SCOPE_BRAND)    $block = BrandDTO::from_wp_term($this->term)->text_image;

        $controller = new TextImageController($block);

        return $controller->get_context();
    }

    /** Слайдер подкатегорий - только на странице категории и только если они есть. */
    private function slider_categories(): array
    {
        if ($this->context !== ProductQuery::SCOPE_CATEGORY || !$this->term) return [];

        $category = CategoryDTO::from_wp_term($this->term, true);

        return array_map(fn ($child) => [
            'title' => $child->name,
            'link'  => $child->url,
            'image' => $child->image->get_array(),
        ], $category->children);
    }

    /**
     * Группы фильтра под @components/modal-filter.twig. Правило «своя ось скрыта»:
     * на бренде нет фасета брендов; на категории фасет категорий = подкатегории.
     *
     * @param int[] $scope_ids
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    private function facets(array $scope_ids, array $filters): array
    {
        $facets = [];

        if ($this->context !== ProductQuery::SCOPE_BRAND) {
            $options = $this->term_options(TaxonomiesEnum::ProductBrand->value, $scope_ids, $filters['brands']);
            if ($options) $facets[] = ['name' => 'brand[]', 'title' => pll__('Бренди'), 'type' => 'checkbox', 'options' => $options];
        }

        $extra = $this->context === ProductQuery::SCOPE_CATEGORY ? ['parent' => (int) $this->term->term_id] : [];
        $options = $this->term_options(TaxonomiesEnum::ProductCategory->value, $scope_ids, $filters['categories'], $extra);
        if ($options) $facets[] = ['name' => 'category[]', 'title' => pll__('Категорії'), 'type' => 'checkbox', 'options' => $options];

        $labels = ProductAttributes::taxonomy_choices();
        foreach (ProductAttributes::taxonomy_names() as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) continue;

            $active  = $filters['attributes'][$taxonomy] ?? [];
            $options = $this->term_options($taxonomy, $scope_ids, $active);
            if ($options) $facets[] = ['name' => "attr[$taxonomy][]", 'title' => pll__($labels[$taxonomy] ?? $taxonomy), 'type' => 'checkbox', 'options' => $options];
        }

        $price = $this->price_facet($scope_ids);
        if ($price) $facets[] = $price;

        return $facets;
    }

    /**
     * Опции чекбокс-фасета: термы, реально присутствующие у товаров области.
     *
     * @param string $taxonomy
     * @param int[]  $scope_ids
     * @param int[]  $active     отмеченные term_id из GET
     * @param array<string, mixed> $extra доп. аргументы get_terms (например parent)
     * @return array<int, array{value:int, label:string, checked:bool}>
     */
    private function term_options(string $taxonomy, array $scope_ids, array $active, array $extra = []): array
    {
        if (!$scope_ids) return [];

        $terms = get_terms(array_merge([
            'taxonomy'   => $taxonomy,
            'object_ids' => $scope_ids,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ], $extra));

        if (is_wp_error($terms)) return [];

        return array_map(fn ($term) => [
            'value'   => (int) $term->term_id,
            'label'   => $term->name,
            'checked' => in_array((int) $term->term_id, $active, true),
        ], $terms);
    }

    /**
     * Диапазон цены для range-фильтра (в текущей валюте). null - двигать нечего.
     *
     * @param int[] $scope_ids
     * @return array<string, mixed>|null
     */
    private function price_facet(array $scope_ids): ?array
    {
        if (!$scope_ids) return null;

        $rate   = Currency::get_current_currency()->value;
        $prices = [];
        foreach ($scope_ids as $id) {
            $value = get_post_meta($id, ProductFieldsEnum::PriceIndex->meta(), true);
            if (is_numeric($value)) $prices[] = (float) $value;
        }
        if (!$prices) return null;

        $min = (int) floor(min($prices) * $rate);
        $max = (int) ceil(max($prices) * $rate);
        if ($max <= $min) return null;

        $current_min = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (int) $_GET['min_price'] : $min;
        $current_max = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (int) $_GET['max_price'] : $max;

        return [
            'title'       => pll__('Ціна'),
            'type'        => 'range',
            'min'         => $min,
            'max'         => $max,
            'current_min' => $current_min,
            'current_max' => $current_max,
            'name_min'    => 'min_price',
            'name_max'    => 'max_price',
        ];
    }

    /**
     * Служебные данные формы фильтра (GET + перезагрузка): скрытые поля для
     * сохранения сортировки/поиска при сабмите и ссылка «Очистити».
     *
     * @return array{action:string, hidden:array<int,array{name:string,value:string}>, clear_url:string}
     */
    private function filter_meta(): array
    {
        $hidden = [];

        $sort = isset($_GET['sort']) ? sanitize_key((string) $_GET['sort']) : '';
        if ($sort !== '' && $sort !== ProductQuery::SORT_DEFAULT) $hidden[] = ['name' => 'sort', 'value' => $sort];

        if ($this->context === ProductQuery::SCOPE_SEARCH && $this->search !== '') $hidden[] = ['name' => 's', 'value' => $this->search];

        return [
            // GET-форма сабмитит на базовый URL (без /page/N/) - применение фильтра сбрасывает на стр. 1.
            'action'    => esc_url($this->base_url()),
            'hidden'    => $hidden,
            'clear_url' => $this->build_url(['brand' => null, 'category' => null, 'attr' => null, 'min_price' => null, 'max_price' => null]),
        ];
    }

    /**
     * Опции сортировки как ссылки (GET + перезагрузка). Сброс paged при смене.
     *
     * @param string $current
     * @return array<int, array{label:string, href:string, is_current:bool}>
     */
    private function sort_view(string $current): array
    {
        $options = [
            ['label' => pll__('Від більшого до меншого'), 'value' => ProductQuery::SORT_PRICE_DESC],
            ['label' => pll__('Від меншого до більшого'), 'value' => ProductQuery::SORT_PRICE_ASC],
            ['label' => pll__('За замовчуванням'),        'value' => ProductQuery::SORT_DEFAULT],
        ];

        return array_map(fn ($option) => [
            'label'      => $option['label'],
            'href'       => $this->build_url(['sort' => $option['value'] === ProductQuery::SORT_DEFAULT ? null : $option['value']]),
            'is_current' => $option['value'] === $current,
            'is_default' => $option['value'] === ProductQuery::SORT_DEFAULT,
        ], $options);
    }

    /**
     * URL области с текущими GET-параметрами и переопределениями (null = убрать
     * параметр). База - ссылка терма (текущий язык) или главная для поиска.
     *
     * @param array<string, mixed> $overrides
     * @return string
     */
    private function build_url(array $overrides): string
    {
        $base = $this->base_url();

        // Пагинация нативная (путь /page/N/), в query-строке её нет - поэтому
        // ссылки фильтра/сортировки от базового URL всегда сбрасывают на стр. 1.
        $keys   = ['brand', 'category', 'attr', 'min_price', 'max_price', 'sort'];
        if ($this->context === ProductQuery::SCOPE_SEARCH) $keys[] = 's';

        $params = [];
        foreach ($keys as $key) {
            if (isset($_GET[$key]) && $_GET[$key] !== '' && $_GET[$key] !== []) $params[$key] = $_GET[$key];
        }

        foreach ($overrides as $key => $value) {
            if ($value === null) unset($params[$key]);
            else $params[$key] = $value;
        }

        return $params ? esc_url($base . '?' . http_build_query($params)) : esc_url($base);
    }

    /** Базовый URL области (стр. 1, без пагинации): ссылка терма или главная для поиска. */
    private function base_url(): string
    {
        return $this->context === ProductQuery::SCOPE_SEARCH ? home_url('/') : (string) get_term_link($this->term);
    }
}
