<?php declare(strict_types=1);

namespace UnitshotTheme;

use UnitshotTheme\Enums\TaxonomiesEnum;
use UnitshotTheme\Enums\TypesEnum;

/**
 * Регистрация пользовательских типов записей (Custom Post Types).
 *
 * @package UnitshotTheme
 */
class Types
{
    function __construct()
    {
        add_action('init', [$this, 'register_product']);
    }

    public function register_product(): void
    {
        register_post_type(TypesEnum::Product->value, array(
            'labels' => [
                'name' => 'Продукты',
                'singular_name' => 'Продукт',
                'menu_name' => 'Продукты',
                'all_items' => 'Все Продукты',
                'edit_item' => 'Изменить Продукт',
                'view_item' => 'Посмотреть Продукт',
                'view_items' => 'Посмотреть Продукты',
                'add_new_item' => 'Добавить новое Продукт',
                'add_new' => 'Добавить новое Продукт',
                'new_item' => 'Новый Продукт',
                'parent_item_colon' => 'Родитель Продукт:',
                'search_items' => 'Поиск Продукты',
                'not_found' => 'Не найдено продукты',
                'not_found_in_trash' => 'В корзине не найдено продукты',
                'archives' => 'Архивы Продукт',
                'attributes' => 'Атрибуты Продукт',
                'insert_into_item' => 'Вставить в продукт',
                'uploaded_to_this_item' => 'Загружено в это продукт',
                'filter_items_list' => 'Фильтровать список продукты',
                'filter_by_date' => 'Фильтр продукты по дате',
                'items_list_navigation' => 'Продукты навигация по списку',
                'items_list' => 'Продукты список',
                'item_published' => 'Продукт опубликовано.',
                'item_published_privately' => 'Продукт опубликована приватно.',
                'item_reverted_to_draft' => 'Продукт преобразован в черновик.',
                'item_scheduled' => 'Продукт запланировано.',
                'item_updated' => 'Продукт обновлён.',
                'item_link' => 'Cсылка на Продукт',
                'item_link_description' => 'Ссылка на продукт.',
            ],
            'public' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-admin-post',
            'supports' => ['title', 'thumbnail', 'custom-fields'],
            'taxonomies' => [TaxonomiesEnum::ProductBrand->value, TaxonomiesEnum::ProductCategory->value],
            'delete_with_user' => false,
        ));
    }

}
