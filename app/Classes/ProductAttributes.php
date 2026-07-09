<?php declare(strict_types=1);

namespace UnitshotTheme\Classes;

use UnitshotTheme\Enums\PageSlugsEnum;
use UnitshotTheme\Enums\TaxonomiesEnum;
use UnitshotTheme\Enums\TypesEnum;
use UnitshotTheme\Rest;
use WP_REST_Request;
use WP_REST_Response;

class ProductAttributes
{
    private const HANDLE = 'us-attributes-app';

    /**
     * Страница атрибутов - контейнер React-острова + начальные данные JSON-тегом.
     * Сохранение - live через REST (rest_save_attributes), без перезагрузки.
     * @return void
     * @see \UnitshotTheme\Admin регистрируется там
     */
    public static function admin_render_menu_page(): void
    {
        $data = [
            'state'     => [
                'attributes' => self::attribute_rows()
            ],
            'options'   => [
                'has_pll'           => function_exists('pll_languages_list'),
                'slug_max'          => self::get_slug_max(),
                'taxonomy_prefix'   => TaxonomiesEnum::ProductAttributePrefix->value,
            ],
            'config'    => [
                'restUrl'   => esc_url_raw(rest_url(Rest::REST_NAMESPACE)),
                'nonce'     => wp_create_nonce('wp_rest'),
            ],
        ];
        $json = (string)wp_json_encode($data, JSON_HEX_TAG); // JSON_HEX_TAG - значения не закроют <script>.

        echo '<div id="us-attributes-app"></div>';
        echo '<script type="application/json" id="us-attributes-data">' . $json . '</script>';
    }

    /**
     * @param string $hook
     * @return void
     * @see \UnitshotTheme\Assets
     */
    public static function assets_enqueue(string $hook): void
    {
        if ($hook !== 'product_page_' . PageSlugsEnum::ProductAttributes->value) return;

        $css = UNITSHOT_APP_DIST_PATH . '/admin.css';
        $js = UNITSHOT_APP_DIST_PATH . '/product-attributes.js';

        wp_enqueue_style(self::HANDLE, UNITSHOT_APP_DIST_URI . '/admin.css', [], (string)@filemtime($css));
        wp_enqueue_script(self::HANDLE, UNITSHOT_APP_DIST_URI . '/product-attributes.js', [], (string)@filemtime($js), true);

        add_filter('script_loader_tag', [self::class, 'module_tag'], 10, 3);
    }

    /** type="module" для нашего бандла. */
    public static function module_tag(string $tag, string $handle, string $src): string
    {
        if ($handle !== self::HANDLE) return $tag;

        return '<script type="module" src="' . esc_url($src) . '" id="' . self::HANDLE . '-js"></script>' . "\n";
    }

    /**
     * Список атрибутов для React: {name, slug, translatable, taxonomy, edit_link, is_new}.
     * is_new=false - существующие (slug в UI заблокирован).
     *
     * @return array<int, array>
     */
    private static function attribute_rows(): array
    {
        $prefix = TaxonomiesEnum::ProductAttributePrefix->value;

        return array_map(function ($a) use ($prefix) {
            $slug = (string)($a['slug'] ?? '');

            return [
                'name'          => (string)($a['name'] ?? ''),
                'slug'          => $slug,
                'translatable'  => (bool)($a['translatable'] ?? false),
                'taxonomy'      => (string)($a['taxonomy'] ?? $prefix . $slug),
                'edit_link'     => admin_url('edit-tags.php?taxonomy=' . $prefix . $slug . '&post_type=' . TypesEnum::Product->value),
                'is_new'        => false,
            ];
        }, Fields::get_products_attributes());
    }

    /**
     * POST /product-attributes/save {attributes:[{name,slug,translatable}]} -> пересобирает
     * option. Slug = идентичность (taxonomy = prefix+slug); существующие slug в UI
     * заблокированы, отсутствующие строки = удаление. Валидация: имя/ярлык, длина, дубли.
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     * @see \UnitshotTheme\Rest
     */
    public static function rest_save_attributes(WP_REST_Request $request): WP_REST_Response
    {
        $rows = $request->get_param('attributes');
        if (!is_array($rows)) $rows = [];

        $max    = self::get_slug_max();
        $prefix = TaxonomiesEnum::ProductAttributePrefix->value;
        $out    = [];
        $seen   = [];
        $errors = [];

        foreach ($rows as $i => $row) {
            if (!is_array($row)) continue;

            $name = sanitize_text_field((string)($row['name'] ?? ''));
            $slug = sanitize_key((string)($row['slug'] ?? ''));
            $line = 'Строка ' . ((int)$i + 1) . ': ';

            if ($name === '' || $slug === '') {
                $errors[] = $line . 'заполните название и ярлык.';
                continue;
            }
            if (strlen($slug) > $max) {
                $errors[] = $line . 'ярлык длиннее ' . $max . ' символов.';
                continue;
            }
            if (isset($seen[$slug])) {
                $errors[] = $line . 'дубликат ярлыка «' . $slug . '».';
                continue;
            }

            $seen[$slug] = true;
            $out[] = [
                'name'          => $name,
                'slug'          => $slug,
                'translatable'  => !empty($row['translatable']),
                'taxonomy'      => $prefix . $slug,
            ];
        }

        if ($errors) {
            return new WP_REST_Response(['ok' => false, 'errors' => $errors], 200);
        }

        Fields::update_products_attributes($out);

        return new WP_REST_Response(['ok' => true, 'attributes' => self::attribute_rows()], 200);
    }

    /** permission_callback: управление настройками темы (атрибуты). */
    public static function rest_can_manage(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * @return void
     * @see Polylang
     */
    public static function polylang_register_attribute_strings(): void
    {
        if (!function_exists('pll_register_string')) return;

        foreach (Fields::get_products_attributes() as $attr) {
            $name = (string)($attr['name'] ?? '');
            if ($name === '') continue;

            $taxonomy = (string)($attr['taxonomy'] ?? TaxonomiesEnum::ProductAttributePrefix->value . ($attr['slug'] ?? ''));
            pll_register_string($taxonomy, $name, 'Unitshot Attributes');
        }
    }

    /**
     * Добавляем переводимые атрибуты в список таксономий Polylang.
     * Работает поверх настроек в UI Polylang и не требует их ручного изменения.
     *
     * @param array $taxonomies
     * @param bool $is_settings
     * @return array
     * @see Polylang
     */
    public static function polylang_register_product_attributes(array $taxonomies, bool $is_settings): array
    {
        $attributes = Fields::get_products_attributes();

        foreach ($attributes as $attr) {
            $is_translatable = (bool)($attr['translatable'] ?? false);
            if (!$is_translatable) continue;

            $tax = TaxonomiesEnum::ProductAttributePrefix->value . $attr['slug'];
            $taxonomies[$tax] = $tax;
        }
        return $taxonomies;
    }

    /**
     * @return void
     * @see Taxonomies
     */
    public static function taxonomies_register_product_attributes(): void
    {
        $attributes = Fields::get_products_attributes();

        foreach ($attributes as $attr) {
            $taxonomy = TaxonomiesEnum::ProductAttributePrefix->value . $attr['slug'];

            if (taxonomy_exists($taxonomy)) continue;

            register_taxonomy($taxonomy, TypesEnum::Product->value, [
                'label' => $attr['name'],
                // Атрибуты нужны для фильтрации товаров, а не как самостоятельные
                // страницы-архивы. Делаем таксономию непубличной: без фронтовых URL
                // (/color/red/), без публичного query-var и без rewrite-правил.
                // Фильтрация строится вручную через tax_query (см. контроллеры блоков).
                'public' => false,
                'publicly_queryable' => false,
                'query_var' => false,
                'rewrite' => false,
                'hierarchical' => true,
                'show_ui' => true,  // нужен для экрана edit-tags.php («Настройка значений»)
                'show_in_menu' => false,
                'show_in_nav_menus' => false,
                'show_in_rest' => true,
                // Единственный редактор атрибутов на экране товара - ACF-поле.
                // Убираем нативный метабокс и Quick Edit, чтобы они не конфликтовали
                // с полной сверкой термов в Field::sync_post_terms (иначе нативные
                // правки затираются, т.к. ACF сохраняется после tax_input).
                'meta_box_cb' => false,
                'show_in_quick_edit' => false,
            ]);
        }
    }

    /**
     * Максимальная длина ярлыка атрибута.
     *
     * Имя таксономии = TAXONOMY_PREFIX + ярлык и не должно превышать
     * лимит WP на имя таксономии: колонка wp_term_taxonomy.taxonomy = VARCHAR(32),
     * иначе register_taxonomy создаст обрезанное/битое имя. Считаем динамически
     * от длины префикса, чтобы при его изменении ограничение не разъехалось
     *
     * return int
     */
    public static function get_slug_max(): int
    {
        return 32 - strlen(TaxonomiesEnum::ProductAttributePrefix->value);
    }

    /**
     * Имена всех зарегистрированных атрибут-таксономий (с префиксом).
     *
     * @return string[]
     */
    public static function taxonomy_names(): array
    {
        return array_column(Fields::get_products_attributes(), 'taxonomy');
    }

    public static function taxonomy_choices(): array
    {
        return array_column(Fields::get_products_attributes(), 'name', 'taxonomy');
    }
}
