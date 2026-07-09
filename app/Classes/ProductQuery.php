<?php declare(strict_types=1);

namespace UnitshotTheme\Classes;

use UnitshotTheme\Enums\ProductFieldsEnum;
use UnitshotTheme\Enums\TaxonomiesEnum;
use UnitshotTheme\Enums\TypesEnum;
use WP_Query;

if (!defined("ABSPATH")) die;

/**
 * Билдер выборки товаров для каталога (категория / бренд / поиск).
 *
 * Чистый конструктор аргументов WP_Query: на вход - «scope» (залоченная область:
 * терм категории/бренда или строка поиска) и нормализованные фильтры (id термов в
 * текущем языке + диапазон цены в БАЗОВОЙ валюте). Конвертацию валюты и парсинг
 * GET делает контроллер - класс остаётся currency-agnostic и переиспользуемым
 * (страница каталога сейчас, AJAX-эндпоинт позже).
 *
 * Цена фильтруется/сортируется по денормализованному индексу _us_price_index
 * (пишется в Products::admin_save_post) - у вариативных товаров цена иначе лежит
 * в массиве вариаций и недоступна для meta_query.
 *
 * @package UnitshotTheme
 */
class ProductQuery
{
    public const SORT_DEFAULT    = 'default';
    public const SORT_PRICE_ASC  = 'price_asc';
    public const SORT_PRICE_DESC = 'price_desc';

    public const PER_PAGE = 16;

    public const SCOPE_CATEGORY = 'category';
    public const SCOPE_BRAND    = 'brand';
    public const SCOPE_SEARCH   = 'search';

    private string $scope_type;
    private int    $scope_term;
    private string $search;

    /** @var int[] */
    private array  $brands;
    /** @var int[] */
    private array  $categories;
    /** @var array<string, int[]> taxonomy => term_id[] */
    private array  $attributes;
    private ?float $min_price;
    private ?float $max_price;
    private string $sort;
    private int    $paged;
    private int    $per_page;

    /**
     * @param array{type?:string, term?:int, search?:string} $scope
     * @param array{brands?:int[], categories?:int[], attributes?:array<string,int[]>, min_price?:?float, max_price?:?float, sort?:string, paged?:int, per_page?:int} $filters
     */
    public function __construct(array $scope, array $filters)
    {
        $this->scope_type = (string) ($scope['type'] ?? '');
        $this->scope_term = (int) ($scope['term'] ?? 0);
        $this->search     = (string) ($scope['search'] ?? '');

        $this->brands     = array_values(array_filter(array_map('intval', (array) ($filters['brands'] ?? []))));
        $this->categories = array_values(array_filter(array_map('intval', (array) ($filters['categories'] ?? []))));

        $this->attributes = [];
        foreach ((array) ($filters['attributes'] ?? []) as $taxonomy => $ids) {
            $ids = array_values(array_filter(array_map('intval', (array) $ids)));
            if ($ids) $this->attributes[(string) $taxonomy] = $ids;
        }

        $this->min_price = is_numeric($filters['min_price'] ?? null) ? (float) $filters['min_price'] : null;
        $this->max_price = is_numeric($filters['max_price'] ?? null) ? (float) $filters['max_price'] : null;

        $this->sort     = in_array($filters['sort'] ?? '', [self::SORT_PRICE_ASC, self::SORT_PRICE_DESC], true) ? $filters['sort'] : self::SORT_DEFAULT;
        $this->paged    = max(1, (int) ($filters['paged'] ?? 1));
        $this->per_page = (int) ($filters['per_page'] ?? self::PER_PAGE);
    }

    public function query(): WP_Query
    {
        return new WP_Query($this->args());
    }

    /**
     * @return array<string, mixed> Аргументы WP_Query.
     */
    public function args(): array
    {
        $args = [
            'post_type'           => TypesEnum::Product->value,
            'post_status'         => 'publish',
            'posts_per_page'      => $this->per_page,
            'paged'               => $this->paged,
            'ignore_sticky_posts' => true,
        ];

        if ($this->scope_type === self::SCOPE_SEARCH && $this->search !== '') {
            $args['s'] = $this->search;
        }

        $tax_query = $this->tax_query();
        if (count($tax_query) > 1) $args['tax_query'] = $tax_query;

        $this->apply_price($args);

        return $args;
    }

    /**
     * tax_query: залоченная область (scope) + пользовательские фасеты, все через
     * AND (внутри одной таксономии term_id IN = OR). Возвращает массив с ключом
     * relation - даже если клауз нет (вызывающий проверяет count > 1).
     *
     * @return array<string|int, mixed>
     */
    private function tax_query(): array
    {
        $clauses = ['relation' => 'AND'];

        // Scope: текущая категория/бренд - неснимаемое ограничение.
        if ($this->scope_term > 0) {
            if ($this->scope_type === self::SCOPE_CATEGORY) $clauses[] = $this->term_clause(TaxonomiesEnum::ProductCategory->value, [$this->scope_term]);
            if ($this->scope_type === self::SCOPE_BRAND)    $clauses[] = $this->term_clause(TaxonomiesEnum::ProductBrand->value, [$this->scope_term]);
        }

        // Пользовательские фасеты.
        if ($this->brands)     $clauses[] = $this->term_clause(TaxonomiesEnum::ProductBrand->value, $this->brands);
        if ($this->categories) $clauses[] = $this->term_clause(TaxonomiesEnum::ProductCategory->value, $this->categories);

        foreach ($this->attributes as $taxonomy => $ids) {
            if (taxonomy_exists($taxonomy)) $clauses[] = $this->term_clause($taxonomy, $ids);
        }

        return $clauses;
    }

    /**
     * @param string $taxonomy
     * @param int[]  $terms
     * @return array<string, mixed>
     */
    private function term_clause(string $taxonomy, array $terms): array
    {
        return [
            'taxonomy'         => $taxonomy,
            'field'            => 'term_id',
            'terms'            => $terms,
            'include_children' => true,
        ];
    }

    /**
     * Цена: фильтр по диапазону и/или сортировка - через meta-клаузу по индексу
     * цены.
     *
     * Важно: при сортировке по цене НЕ теряем товары без меты _us_price_index
     * (импортированные/созданные не через метабокс). Обычная сортировка по мете
     * делает INNER JOIN и отбрасывает такие товары - поэтому при сортировке без
     * диапазона добавляем ветку NOT EXISTS (relation OR), тогда WP делает
     * LEFT JOIN и товары без цены попадают в выдачу (в конец/начало по NULL).
     * При фильтре по диапазону товары без цены отсекаются осознанно - сравнивать
     * не с чем.
     *
     * @param array<string, mixed> $args
     * @return void
     */
    private function apply_price(array &$args): void
    {
        $sort_by_price = in_array($this->sort, [self::SORT_PRICE_ASC, self::SORT_PRICE_DESC], true);
        $has_range     = $this->min_price !== null || $this->max_price !== null;
        $key           = ProductFieldsEnum::PriceIndex->meta();

        if ($has_range) {
            $clause = ['key' => $key, 'type' => 'NUMERIC'];

            if ($this->min_price !== null && $this->max_price !== null) {
                $clause['value']   = [$this->min_price, $this->max_price];
                $clause['compare'] = 'BETWEEN';
            } elseif ($this->min_price !== null) {
                $clause['value']   = $this->min_price;
                $clause['compare'] = '>=';
            } else {
                $clause['value']   = $this->max_price;
                $clause['compare'] = '<=';
            }

            $args['meta_query'] = ['price' => $clause];
        } elseif ($sort_by_price) {
            $args['meta_query'] = [
                'relation'      => 'OR',
                'price'         => ['key' => $key, 'type' => 'NUMERIC', 'compare' => 'EXISTS'],
                'price_missing' => ['key' => $key, 'compare' => 'NOT EXISTS'],
            ];
        }

        if ($sort_by_price) {
            $args['orderby'] = ['price' => $this->sort === self::SORT_PRICE_ASC ? 'ASC' : 'DESC'];
        } else {
            $args['orderby'] = ['date' => 'DESC'];
        }
    }

    /**
     * Выравнивает главный запрос архивов каталога (категория/бренд/поиск) под
     * постраничность каталога. Иначе WP считает страницы по настройке
     * «posts_per_page» и помечает is_404 на номерах страниц, которых нет в
     * главном запросе, хотя у нашей выборки (ProductQuery) они есть.
     * Для поиска дополнительно ограничиваем тип записи товаром - каталог
     * показывает только товары.
     *
     * @param \WP_Query $query
     * @return void
     */
    public static function catalog_main_query(\WP_Query $query): void
    {
        if (is_admin() || !$query->is_main_query()) return;

        $is_catalog = $query->is_tax(TaxonomiesEnum::ProductCategory->value) || $query->is_tax(TaxonomiesEnum::ProductBrand->value) || $query->is_search();
        if (!$is_catalog) return;

        $query->set('posts_per_page', ProductQuery::PER_PAGE);
        if ($query->is_search()) $query->set('post_type', TypesEnum::Product->value);
    }
}
