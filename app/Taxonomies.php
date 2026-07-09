<?php declare(strict_types=1);

namespace UnitshotTheme;

use UnitshotTheme\Classes\Fields;
use UnitshotTheme\Classes\ProductAttributes;
use UnitshotTheme\Enums\PermalinkFieldsEnum;
use UnitshotTheme\Enums\TaxonomiesEnum;
use UnitshotTheme\Enums\TypesEnum;

/**
 * Регистрация и управление таксономиями продукта.
 *
 * Класс отвечает за создание и настройку пользовательских таксономий,
 *
 * @package UnitshotTheme
 */
class Taxonomies
{
    function __construct()
    {
        add_action('init', [$this, 'register_product_brand']);
        add_action('init', [$this, 'register_product_category']);
        add_action('init', [$this, 'register_product_parameter']);
        add_action('init', [ProductAttributes::class, 'taxonomies_register_product_attributes']);
    }

    public function register_product_brand(): void
    {
        register_taxonomy(TaxonomiesEnum::ProductBrand->value, [TypesEnum::Product->value], [
            'labels' => [
                'name' => 'Бренды',
                'singular_name' => 'Бренд',
                'menu_name' => 'Бренды',
                'all_items' => 'Все Бренды',
                'edit_item' => 'Изменить Бренд',
                'view_item' => 'Посмотреть Бренд',
                'update_item' => 'Обновить Бренд',
                'add_new_item' => 'Добавить новое Бренд',
                'new_item_name' => 'Новое Бренд название',
                'parent_item' => 'Родитель Бренд',
                'parent_item_colon' => 'Родитель Бренд:',
                'search_items' => 'Поиск Бренды',
                'not_found' => 'Не найдено бренды',
                'no_terms' => 'Нет бренды',
                'filter_by_item' => 'Фильтр по бренд',
                'items_list_navigation' => 'Бренды навигация по списку',
                'items_list' => 'Бренды список',
                'back_to_items' => '← Перейти к бренды',
                'item_link' => 'Cсылка на Бренд',
                'item_link_description' => 'Ссылка на бренд',
            ],
            'public' => true,
            'hierarchical' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'rewrite' => ['slug' => Fields::get_permalink_prefix(PermalinkFieldsEnum::Brand) ?: TaxonomiesEnum::ProductBrand->value],
        ]);
    }

    public function register_product_category(): void
    {
        register_taxonomy(TaxonomiesEnum::ProductCategory->value, [TypesEnum::Product->value], [
            'labels' => [
                'name' => 'Категории',
                'singular_name' => 'Категория',
                'menu_name' => 'Категории',
                'all_items' => 'Все Категории',
                'edit_item' => 'Изменить Категория',
                'view_item' => 'Посмотреть Категория',
                'update_item' => 'Обновить Категория',
                'add_new_item' => 'Добавить новое Категория',
                'new_item_name' => 'Новое Категория название',
                'search_items' => 'Поиск Категории',
                'not_found' => 'Не найдено категории',
                'no_terms' => 'Нет категории',
                'items_list_navigation' => 'Категории навигация по списку',
                'items_list' => 'Категории список',
                'back_to_items' => '← Перейти к категории',
                'item_link' => 'Cсылка на Категория',
                'item_link_description' => 'Ссылка на категория',
            ],
            'public' => true,
            'hierarchical' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'show_in_quick_edit' => true,
            'rewrite' => ['slug' => Fields::get_permalink_prefix(PermalinkFieldsEnum::Category) ?: TaxonomiesEnum::ProductCategory->value, 'hierarchical' => true],
        ]);
    }

    public function register_product_parameter(): void
    {
        register_taxonomy(TaxonomiesEnum::ProductParameter->value, [TypesEnum::Product->value], [
            'labels' => [
                'name' => 'Параметры',
                'singular_name' => 'Параметр',
                'menu_name' => 'Параметры',
                'all_items' => 'Все Параметры',
                'edit_item' => 'Изменить Параметр',
                'view_item' => 'Посмотреть Параметр',
                'update_item' => 'Обновить Параметр',
                'add_new_item' => 'Добавить новое Параметр',
                'new_item_name' => 'Новое Параметр название',
                'search_items' => 'Поиск Параметры',
                'popular_items' => 'Популярные Параметры',
                'separate_items_with_commas' => 'Разделять параметры запятыми',
                'add_or_remove_items' => 'Добавить или удалить параметры',
                'choose_from_most_used' => 'Выберите из наиболее часто используемых параметры',
                'not_found' => 'Не найдено параметры',
                'no_terms' => 'Нет параметры',
                'items_list_navigation' => 'Параметры навигация по списку',
                'items_list' => 'Параметры список',
                'back_to_items' => '← Перейти к параметры',
                'item_link' => 'Cсылка на Параметр',
                'item_link_description' => 'Ссылка на параметр',
            ],
            'public' => false,
            'publicly_queryable' => false,
            'query_var' => false,
            'rewrite' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'show_in_nav_menus' => false,
        ]);
    }
}
