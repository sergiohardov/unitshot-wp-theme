<?php declare(strict_types=1);

namespace UnitshotTheme;

use UnitshotTheme\Classes\Utils;

/**
 * Базовая конфигурация темы.
 *
 * Класс отвечает за настройку фундаментального поведения WordPress-темы,
 * формирует базовую конфигурацию темы и управляет поведением ядра WordPress
 * на уровне отображения и редактирования контента.
 *
 * @package UnitshotTheme
 */
class Theme
{
    function __construct()
    {
        add_action('after_setup_theme',         [$this, 'register_menus']);
        add_action('after_setup_theme',         [$this, 'theme_supports']);
        add_filter('use_block_editor_for_post', [$this, 'disable_guttenberg'], 10, 2);
        add_filter('sanitize_title',            [Utils::class, 'transliterate'], 9, 1);
    }

    public function register_menus(): void
    {
        register_nav_menus([
            'top_menu'      => 'Хедер Строка',
            'catalog_menu'  => 'Хедер Каталог товаров',
            'brands_menu'   => 'Хедер Бренды',
            'footer_menu_1' => 'Футер Меню 1',
            'footer_menu_2' => 'Футер Меню 2',
        ]);
    }

    public function theme_supports(): void
    {
        add_theme_support('post-thumbnails');
        add_theme_support('title-tag');
        add_theme_support('align-wide');
    }

    public function disable_guttenberg($can_edit, $post): bool
    {
        $contact_template = get_pages([
            'meta_key' => '_wp_page_template',
            'meta_value' => 'page-templates/contact.php'
        ]);

        if ($post->ID === get_option('page_on_front')) return false;
        if (in_array($post->ID, array_column($contact_template, 'ID'))) return false;
        return $can_edit;
    }

    /**
     * Получение данных меню из WP настроек
     *
     * @param string $location
     * @see register_menus()
     * @return array
     */
    public static function get_menu_data_by_location(string $location): array {
        $result = ['title' => '', 'menu' => []];

        $locations = get_nav_menu_locations();
        if (empty($locations[$location])) return $result;

        $menu_obj = wp_get_nav_menu_object($locations[$location]);
        if (!$menu_obj) return $result;

        $menu_items = wp_get_nav_menu_items($menu_obj->term_id);
        if (empty($menu_items)) return $result;

        $items_by_id = [];
        $formatted_menu = [];

        // 1: Формируем базовый массив и индексируем его по ID элемента
        foreach ($menu_items as $item) {
            $items_by_id[$item->ID] = [
                'id'         => $item->ID,
                'object_id'  => $item->object_id,
                'title'      => $item->title,
                'link'       => $item->url,
                'is_current' => ($item->object_id == get_queried_object_id()),
                'submenu'    => []
            ];
        }

        // 2: Распределяем элементы по родителям (строим дерево)
        foreach ($menu_items as $item) {
            if ($item->menu_item_parent == 0) {
                $formatted_menu[] = &$items_by_id[$item->ID]; // Это элемент верхнего уровня — кладем ссылку на него в итоговый массив
            } else {
                // Это дочерний элемент — кладем ссылку на него в submenu его родителя
                if (isset($items_by_id[$item->menu_item_parent])) {
                    $items_by_id[$item->menu_item_parent]['submenu'][] = &$items_by_id[$item->ID];
                }
            }
        }

        $result['title'] = $menu_obj->name;
        $result['menu'] = $formatted_menu;

        return $result;
    }
}
