<?php declare(strict_types=1);

namespace UnitshotTheme\Classes;

use UnitshotTheme\Polylang;
use UnitshotTheme\Rest;
use UnitshotTheme\Enums\ProductFieldsEnum;
use UnitshotTheme\Enums\ProductStatusesEnum;
use UnitshotTheme\Enums\ProductTypesEnum;
use UnitshotTheme\Enums\TaxonomiesEnum;
use UnitshotTheme\Enums\TypesEnum;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) die;

class Products
{
    private const WILDCARD      = '*';
    private const HANDLE        = 'us-product-app';
    private const NONCE_ACTION  = 'us_product_react';
    private const NONCE_FIELD   = 'us_product_react_nonce';
    private const STATE_FIELD   = 'us_product_state';

    /**
     * Метабокс товара — контейнер React-острова + начальные данные JSON-тегом.
     * @see \UnitshotTheme\Admin регистрируется там
     *
     * Данные для React: state (редактируемые поля, уходят обратно) + options
     * (справочники для селектов/радио) + config (REST).
     */
    public static function render_meta_box(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $post_id    = $post->ID;
        $type       = (string) get_post_meta($post_id, ProductFieldsEnum::Type->meta(), true);
        $data       = [
            'state'     => [
                'id'                    => $post_id,
                'type'                  => ProductTypesEnum::tryFrom($type)?->value ?? ProductTypesEnum::Simple->value,
                'sku'                   => (string) get_post_meta($post_id, ProductFieldsEnum::Sku->meta(), true),
                'in_stock'              => metadata_exists('post', $post_id, ProductFieldsEnum::InStock->meta()) ? (bool) get_post_meta($post_id, ProductFieldsEnum::InStock->meta(), true) : true, // Новый товар (мета ещё не сохранялась) — «в наличии» по умолчанию.
                'status'                => (string) get_post_meta($post_id, ProductFieldsEnum::Status->meta(), true),
                'model'                 => (string) get_post_meta($post_id, ProductFieldsEnum::Model->meta(), true),
                'base_price'            => (string) get_post_meta($post_id, ProductFieldsEnum::BasePrice->meta(), true),
                'sale_price'            => (string) get_post_meta($post_id, ProductFieldsEnum::SalePrice->meta(), true),
                'short_description'     => (string) get_post_meta($post_id, ProductFieldsEnum::ShortDescription->meta(), true),
                'description'           => (string) get_post_meta($post_id, ProductFieldsEnum::Description->meta(), true),
                'gallery'               => self::gallery_items((array) get_post_meta($post_id, ProductFieldsEnum::Gallery->meta(), true)),
                'files'                 => self::file_items((array) get_post_meta($post_id, ProductFieldsEnum::Files->meta(), true)),
                'attributes'            => self::attribute_rows($post_id),
                'variations'            => array_values(array_filter((array) get_post_meta($post_id, ProductFieldsEnum::Variations->meta(), true), 'is_array')),
                'default_attributes'    => (object) self::default_attributes($post_id), // {taxonomy: term_id}
                'set_items'             => self::set_item_rows($post_id),
                'popular_products'      => self::popular_product_rows($post_id),
                'parameters'            => self::parameter_rows($post_id),
            ],
            'options'   => [
                'types'       => ProductTypesEnum::group_labels(),
                'statuses'    => ProductStatusesEnum::group_labels(),
                'currency'    => Currency::get_default_currency()->symbol,
                'tax_choices' => (object) ProductAttributes::taxonomy_choices(), // {taxonomy: label}
                'parameters'  => self::parameter_term_choices($post_id), // [{id,name}] термы таксономии «Параметры»
                'wildcard'    => self::WILDCARD,
            ],
            'config'    => [
                'restUrl'   => esc_url_raw(rest_url(Rest::REST_NAMESPACE)),
                'nonce'     => wp_create_nonce('wp_rest'),
                'postId'    => $post_id,
                'field_key' => self::STATE_FIELD
            ],
        ];
        $json       = (string) wp_json_encode($data, JSON_HEX_TAG); // JSON_HEX_TAG — чтобы значения не могли закрыть <script>.

        echo '<div id="us-product-app"></div>';
        echo '<script type="application/json" id="us-product-data">' . $json . '</script>';
    }

    /**
     * Маппинг state (JSON из React-острова) в меты товара. Переиспользует те же
     * приватные хелперы (канонизация/нормализация). Строки уже без слэшей
     * (json_decode(wp_unslash(...)) в admin_save_post).
     *
     * @param int $post_id
     * @param WP_Post $post
     * @return void
     * @see \UnitshotTheme\Admin
     */
    public static function admin_save_post(int $post_id, WP_Post $post): void
    {
        if (!isset($_POST[self::NONCE_FIELD]) || !wp_verify_nonce($_POST[self::NONCE_FIELD], self::NONCE_ACTION)) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $state = json_decode(wp_unslash((string) ($_POST[self::STATE_FIELD] ?? '')), true);
        if (!is_array($state)) return;

        $type = sanitize_key((string) ($state['type'] ?? ''));
        if (!ProductTypesEnum::tryFrom($type)) $type = ProductTypesEnum::Simple->value;

        $status = sanitize_key((string) ($state['status'] ?? ''));
        $status = ProductStatusesEnum::tryFrom($status) ? $status : '';

        update_post_meta($post_id, ProductFieldsEnum::Type->meta(),             $type);
        update_post_meta($post_id, ProductFieldsEnum::Sku->meta(),              sanitize_text_field((string) ($state['sku'] ?? '')));
        update_post_meta($post_id, ProductFieldsEnum::InStock->meta(),          !empty($state['in_stock']) ? 1 : 0);
        update_post_meta($post_id, ProductFieldsEnum::Status->meta(),           $status);
        update_post_meta($post_id, ProductFieldsEnum::Model->meta(),            sanitize_text_field((string) ($state['model'] ?? '')));
        update_post_meta($post_id, ProductFieldsEnum::BasePrice->meta(),        self::clean_price($state['base_price'] ?? ''));
        update_post_meta($post_id, ProductFieldsEnum::SalePrice->meta(),        self::clean_price($state['sale_price'] ?? ''));
        update_post_meta($post_id, ProductFieldsEnum::ShortDescription->meta(),  wp_kses_post((string) ($state['short_description'] ?? '')));
        update_post_meta($post_id, ProductFieldsEnum::Description->meta(),       wp_kses_post((string) ($state['description'] ?? '')));
        update_post_meta($post_id, ProductFieldsEnum::Gallery->meta(),          self::ids_from($state['gallery'] ?? []));
        update_post_meta($post_id, ProductFieldsEnum::Files->meta(),            self::ids_from($state['files'] ?? []));
        // Похожие товары (блок «Схожі товари») - каноничные id (мультиязычность).
        update_post_meta($post_id, ProductFieldsEnum::PopularProducts->meta(),  array_map([self::class, 'canonical_post'], self::ids_from($state['popular_products'] ?? [])));
        // Параметры - каноничный term_id + значение + флаг «основной» (значение общее между языками, имя берётся из терма).
        update_post_meta($post_id, ProductFieldsEnum::Parameters->meta(),       self::normalize_parameters($state['parameters'] ?? []));

        if($type === ProductTypesEnum::Simple->value || $type === ProductTypesEnum::Variable->value) {
            // Атрибуты (все типы): terms [{id,name}] -> [id] под persist_attributes.
            $attr_rows = [];
            foreach ((array) ($state['attributes'] ?? []) as $a) {
                if (!is_array($a)) continue;
                $attr_rows[] = [
                    'taxonomy'      => $a['taxonomy'] ?? '',
                    'terms'         => array_column((array) ($a['terms'] ?? []), 'id'),
                    'in_variations' => !empty($a['in_variations']),
                    'main'          => !empty($a['main']),
                ];
            }
            self::persist_attributes($post_id, $attr_rows);
        }

        if ($type === ProductTypesEnum::Variable->value) {
            update_post_meta($post_id, ProductFieldsEnum::Variations->meta(), self::normalize_variations($state['variations'] ?? []));
            update_post_meta($post_id, ProductFieldsEnum::DefaultAttrs->meta(), self::sanitize_default_attributes($state['default_attributes'] ?? []));
        }

        if ($type === ProductTypesEnum::Complete->value) {
            self::persist_set_items($post_id, $state['set_items'] ?? []);
        }

        // Денормализуем цену к оплате в числовую мету - чтобы каталог мог фильтровать
        // и сортировать по цене через WP_Query (у вариативных она иначе в массиве вариаций).
        // Считаем после записи всех метаданных - DTO собирается из уже сохранённого.
        $price = Helpers::get_product_dto_by_id($post_id)?->display_price();
        update_post_meta($post_id, ProductFieldsEnum::PriceIndex->meta(), is_numeric($price) ? (float) $price : '');
    }

    /**
     * @see \UnitshotTheme\Assets
     * @param string $hook
     * @return void
     */
    public static function assets_enqueue_script(string $hook): void
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;

        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== TypesEnum::Product->value) return;

        wp_enqueue_media(); // нужен window.wp.media для выбора изображений галереи/файлов

        $css = UNITSHOT_APP_DIST_PATH . '/admin.css';
        $js  = UNITSHOT_APP_DIST_PATH . '/product-metabox.js';

        wp_enqueue_style(self::HANDLE, UNITSHOT_APP_DIST_URI . '/admin.css', [], (string) @filemtime($css));
        wp_enqueue_script(self::HANDLE, UNITSHOT_APP_DIST_URI . '/product-metabox.js', [], (string) @filemtime($js), true);

        // Грузим entry как ES-модуль (на перспективу нескольких страниц/общих чанков).
        add_filter('script_loader_tag', [self::class, 'module_tag'], 10, 3);
    }

    /**
     * @see Polylang
     * @param array $keys
     * @param bool $sync
     * @return array
     */
    public static function polylang_copy_metas(array $keys, bool $sync): array
    {
        // Общие для всех языков meta-ключи (описание - НЕ сюда, оно переводится).
        $copy_metas = [
            ProductFieldsEnum::Type->meta(),
            ProductFieldsEnum::Model->meta(),
            ProductFieldsEnum::Sku->meta(),
            ProductFieldsEnum::Status->meta(),
            ProductFieldsEnum::InStock->meta(),
            ProductFieldsEnum::BasePrice->meta(),
            ProductFieldsEnum::SalePrice->meta(),
            ProductFieldsEnum::PriceIndex->meta(),
            ProductFieldsEnum::Gallery->meta(),
            ProductFieldsEnum::Files->meta(),
            ProductFieldsEnum::SetItems->meta(),
            ProductFieldsEnum::SetMember->meta(),
            ProductFieldsEnum::PopularProducts->meta(),
            ProductFieldsEnum::Parameters->meta(),
            ProductFieldsEnum::AttributeOptions->meta(),
            ProductFieldsEnum::Variations->meta(),
            ProductFieldsEnum::DefaultAttrs->meta(),
        ];

        return array_values(array_unique(array_merge($keys, $copy_metas)));
    }

    /**
     * GET /product/attribute-terms?taxonomy=&s= -> [{id,name}] для tag-поиска.
     * @see \UnitshotTheme\Rest
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function rest_attribute_terms(WP_REST_Request $request): WP_REST_Response
    {
        $tax = sanitize_key((string) $request->get_param('taxonomy'));
        if (!$tax || !in_array($tax, ProductAttributes::taxonomy_names(), true) || !taxonomy_exists($tax)) return new WP_REST_Response([], 200);

        $args = [
            'taxonomy'   => $tax,
            'hide_empty' => false,
            'search'     => sanitize_text_field((string) $request->get_param('s')),
            'number'     => 20,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ];

        // [PLL] В REST язык не определяется -> get_terms вернул бы термы ВСЕХ языков.
        // Передаём язык редактируемого товара: Polylang отфильтрует переводимые
        // таксономии по нему (непереводимые игнорирует - они language-neutral).
        $lang = Polylang::get_editing_language(absint($request->get_param('post_id')));
        if ($lang) $args['lang'] = $lang;

        $terms = get_terms($args);

        $out = is_wp_error($terms) ? [] : array_map(fn ($term) => ['id' => (int) $term->term_id, 'canonical' => self::canonical_term((int) $term->term_id), 'name' => $term->name], $terms);

        return new WP_REST_Response($out, 200);
    }

    /**
     * GET /product/search?s=&exclude= -> [{id,title,price}] для подбора членов набора.
     * Только simple/variable (наборы не вкладываются); price - эффективная (display_price).
     * @see Rest
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function rest_search_products(WP_REST_Request $request): WP_REST_Response
    {
        $post_id = absint($request->get_param('post_id'));
        // Каноничный id редактируемого товара - чтобы исключить его во всех языках.
        $exclude = self::canonical_post($post_id);

        $args = [
            'post_type'      => TypesEnum::Product->value,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'title',
            'order'          => 'ASC',
            's'              => sanitize_text_field((string) $request->get_param('s')),
        ];

        // [PLL] В REST язык не определяется - отдаём товары языка редактируемого набора.
        $lang = Polylang::get_editing_language($post_id);
        if ($lang) $args['lang'] = $lang;

        $ids = get_posts($args);

        $out = [];
        foreach ($ids as $pid) {
            $pid = (int) $pid;
            if ($exclude > 0 && self::canonical_post($pid) === $exclude) continue; // текущий товар

            $dto = Helpers::get_product_dto_by_id($pid);
            if (!$dto || $dto->is_complete()) continue; // наборы не вкладываем

            $out[] = ['id' => $pid, 'title' => $dto->name, 'sku' => $dto->sku, 'is_variable' => $dto->is_variable(), 'price' => $dto->display_price()];
        }

        return new WP_REST_Response($out, 200);
    }

    /**
     * GET /product/front-search?s=&lang= -> публичный живой поиск для шапки.
     * До 6 товаров текущего языка: [{title, url, image, price}] (цена форматирована
     * в текущей валюте). Язык приходит параметром - в REST Polylang его не знает.
     *
     * @see Rest
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function rest_front_search(WP_REST_Request $request): WP_REST_Response
    {
        $s = sanitize_text_field((string) $request->get_param('s'));
        if (mb_strlen($s) < 2) return new WP_REST_Response([], 200);

        $args = [
            'post_type'      => TypesEnum::Product->value,
            'post_status'    => 'publish',
            'posts_per_page' => 6,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            's'              => $s,
        ];

        $lang = sanitize_key((string) $request->get_param('lang'));
        if ($lang) $args['lang'] = $lang;

        $out = [];
        foreach (get_posts($args) as $pid) {
            $dto = Helpers::get_product_dto_by_id((int) $pid);
            if (!$dto) continue;

            $out[] = [
                'title'     => $dto->name,
                'url'       => $dto->url,
                'image'     => $dto->gallery[0]->src ?? null,
                'price'     => Helpers::format_price($dto->display_price()),
                'old_price' => Helpers::format_price($dto->display_old_price()), // null - нет скидки
            ];
        }

        return new WP_REST_Response($out, 200);
    }

    /**
     * GET /product/variations?post_id= -> [{key,label,price}] вариаций товара
     * (для выбора конкретной вариации члена набора).
     * @see Rest
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function rest_product_variations(WP_REST_Request $request): WP_REST_Response
    {
        $dto = Helpers::get_product_dto_by_id(absint($request->get_param('post_id')));

        return new WP_REST_Response($dto ? $dto->variation_options() : [], 200);
    }

    /**
     * permission_callback: правка любых записей (поиск термов).
     * @see Rest
     * @return bool
     */
    public static function rest_can_edit_posts(): bool
    {
        return current_user_can('edit_posts');
    }

    /** Список id из [{id,...}] или плоского массива - уникальные положительные int. */
    private static function ids_from($list): array
    {
        $ids = array_map(fn ($x) => is_array($x) ? (int) ($x['id'] ?? 0) : (int) $x, (array) $list);
        return array_values(array_unique(array_filter($ids)));
    }

    /** type="module" для нашего бандла. */
    public static function module_tag(string $tag, string $handle, string $src): string
    {
        if ($handle !== self::HANDLE) return $tag;

        return '<script type="module" src="' . esc_url($src) . '" id="' . self::HANDLE . '-js"></script>' . "\n";
    }

    /**
     * Назначенные атрибуты для React: [{taxonomy, terms:[{id,name}], in_variations, main}].
     * Прим.: id термов - текущего языка (для дефолт-языка совпадают с каноничными,
     * на которых хранятся вариации; полную мультиязычность даст реальный save).
     */
    private static function attribute_rows(int $post_id): array
    {
        $options = self::attribute_options($post_id);

        $rows = [];
        foreach (ProductAttributes::taxonomy_names() as $tax) {
            if (!taxonomy_exists($tax)) continue;

            $terms = wp_get_object_terms($post_id, $tax, ['fields' => 'all']);
            if (is_wp_error($terms) || empty($terms)) continue;

            $rows[] = [
                'taxonomy'      => $tax,
                'terms'         => array_map(fn ($t) => ['id' => (int) $t->term_id, 'canonical' => self::canonical_term((int) $t->term_id), 'name' => $t->name], $terms),
                'in_variations' => $options[$tax]['in_variations'] ?? true,
                'main'          => $options[$tax]['main'] ?? false,
            ];
        }
        return $rows;
    }

    /** Состав набора для React: [{id, title, sku, is_variable, variations, variation, price}]. */
    private static function set_item_rows(int $post_id): array
    {
        $rows = [];
        foreach ((array) get_post_meta($post_id, ProductFieldsEnum::SetItems->meta(), true) as $row) {
            $canonical     = is_array($row) ? (int) ($row['id'] ?? 0) : (int) $row;
            $variation_key = is_array($row) ? (string) ($row['variation'] ?? '') : '';
            if ($canonical <= 0) continue;

            $dto = Helpers::get_product_dto_by_id(self::localized_post($canonical));
            if (!$dto || $dto->is_complete()) continue;

            $is_variable = $dto->is_variable();
            $price = ($is_variable && $variation_key !== '') ? $dto->variation_price($variation_key) : $dto->display_price();

            $rows[] = [
                'id'          => $dto->id,
                'title'       => $dto->name,
                'sku'         => $dto->sku,
                'is_variable' => $is_variable,
                'variations'  => $is_variable ? $dto->variation_options() : [],
                'variation'   => $variation_key,
                'price'       => $price,
            ];
        }
        return $rows;
    }

    /**
     * Рекомендованные товары для пикера метабокса: [{id, title, sku}] (язык текущий).
     *
     * @return array<int, array{id:int, title:string, sku:?string}>
     */
    private static function popular_product_rows(int $post_id): array
    {
        $rows = [];
        foreach ((array) get_post_meta($post_id, ProductFieldsEnum::PopularProducts->meta(), true) as $canonical) {
            $dto = Helpers::get_product_dto_by_id(self::localized_post((int) $canonical));
            if (!$dto) continue;

            $rows[] = ['id' => $dto->id, 'title' => $dto->name, 'sku' => $dto->sku];
        }
        return $rows;
    }

    /**
     * Термы таксономии «Параметры» для селекта (язык редактируемого товара).
     *
     * @return array<int, array{id:int, name:string}>
     */
    private static function parameter_term_choices(int $post_id): array
    {
        $args = ['taxonomy' => TaxonomiesEnum::ProductParameter->value, 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC'];

        $lang = Polylang::get_editing_language($post_id);
        if ($lang) $args['lang'] = $lang;

        $terms = get_terms($args);
        if (is_wp_error($terms)) return [];

        return array_map(fn ($t) => ['id' => (int) $t->term_id, 'name' => $t->name], $terms);
    }

    /**
     * Параметры товара для метабокса: {term (id текущего языка), value, main}.
     * Хранится каноничный term_id — резолвим в текущий язык для селекта.
     *
     * @return array<int, array{term:int, value:string, main:bool}>
     */
    private static function parameter_rows(int $post_id): array
    {
        $stored = get_post_meta($post_id, ProductFieldsEnum::Parameters->meta(), true);
        if (!is_array($stored)) return [];

        $rows = [];
        foreach ($stored as $row) {
            if (!is_array($row)) continue;

            $canonical = (int) ($row['term'] ?? 0);
            if ($canonical <= 0) continue;

            $localized = self::localized_term($canonical);
            $term      = get_term($localized);
            if (!$term instanceof \WP_Term || $term->taxonomy !== TaxonomiesEnum::ProductParameter->value) continue;

            $rows[] = ['term' => $localized, 'value' => (string) ($row['value'] ?? ''), 'main' => !empty($row['main'])];
        }
        return $rows;
    }

    /**
     * Нормализация параметров из $_POST-state: каноничный term_id (валидный терм
     * таксономии «Параметры»), значение, флаг «основной»; дедуп по терму.
     *
     * @return array<int, array{term:int, value:string, main:bool}>
     */
    private static function normalize_parameters($raw): array
    {
        if (!is_array($raw)) return [];

        $out  = [];
        $seen = [];
        foreach ($raw as $row) {
            if (!is_array($row)) continue;

            $canonical = self::canonical_term((int) ($row['term'] ?? 0));
            if ($canonical <= 0 || isset($seen[$canonical])) continue;

            $term = get_term($canonical);
            if (!$term instanceof \WP_Term || $term->taxonomy !== TaxonomiesEnum::ProductParameter->value) continue;

            $seen[$canonical] = true;
            $out[] = [
                'term'  => $canonical,
                'value' => sanitize_text_field((string) ($row['value'] ?? '')),
                'main'  => !empty($row['main']),
            ];
        }
        return $out;
    }

    /** Галерея для React: [{id, url}] (url - миниатюра для превью). */
    private static function gallery_items(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $id  = (int) $id;
            $url = $id > 0 ? wp_get_attachment_image_url($id, 'thumbnail') : false;
            if ($url) $out[] = ['id' => $id, 'url' => $url];
        }
        return $out;
    }

    /** Файлы для React: [{id, name}] (name - заголовок вложения / имя файла). */
    private static function file_items(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            $id  = (int) $id;
            $url = $id > 0 ? wp_get_attachment_url($id) : false;
            if (!$url) continue;

            $title = get_the_title($id);
            $out[] = ['id' => $id, 'name' => $title !== '' ? $title : basename($url)];
        }
        return $out;
    }

    /**
     * Состав набора из $_POST: индексированные строки us_set_items[i][id|variation].
     * Каноничные id членов (только simple/variable, без вложенности и самоссылки);
     * вариация - только для variable и если ключ есть среди её вариаций.
     * Дублируем id по строке в SetMember для реверс-поиска.
     *
     * @param mixed $rows
     * @return void
     */
    private static function persist_set_items(int $post_id, $rows): void
    {
        $self  = self::canonical_post($post_id);
        $items = [];
        $seen  = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row)) continue;

                $canonical = self::canonical_post((int) ($row['id'] ?? 0));
                if ($canonical <= 0 || $canonical === $self || isset($seen[$canonical])) continue;

                $member = get_post($canonical);
                if (!$member instanceof \WP_Post || $member->post_type !== TypesEnum::Product->value) continue;

                $member_type = (string) get_post_meta($canonical, ProductFieldsEnum::Type->meta(), true);
                if ($member_type === ProductTypesEnum::Complete->value) continue; // без вложенности

                // Вариация - только у вариативного и только существующая.
                $variation = '';
                if ($member_type === ProductTypesEnum::Variable->value) {
                    $key = sanitize_text_field((string) ($row['variation'] ?? ''));
                    if ($key !== '' && self::variation_key_exists($canonical, $key)) $variation = $key;
                }

                $seen[$canonical] = true;
                $items[] = ['id' => $canonical, 'variation' => $variation];
            }
        }

        update_post_meta($post_id, ProductFieldsEnum::SetItems->meta(), $items);

        // Зеркало: по строке на члена - для чистого meta_query в get_sets_for_product().
        delete_post_meta($post_id, ProductFieldsEnum::SetMember->meta());
        foreach ($items as $item) {
            add_post_meta($post_id, ProductFieldsEnum::SetMember->meta(), $item['id']);
        }
    }

    /** Есть ли вариация с таким ключом у товара. */
    private static function variation_key_exists(int $product_id, string $key): bool
    {
        foreach ((array) get_post_meta($product_id, ProductFieldsEnum::Variations->meta(), true) as $variation) {
            if (is_array($variation) && (string) ($variation['key'] ?? '') === $key) return true;
        }
        return false;
    }

    /**
     * Per-product флаги назначенных атрибутов { taxonomy: {in_variations, main} }.
     * Хранятся только для реально назначенных таксономий; отсутствие = легаси-дефолты.
     *
     * @return array<string, array{in_variations: bool, main: bool}>
     */
    public static function attribute_options(int $post_id): array
    {
        $stored = get_post_meta($post_id, ProductFieldsEnum::AttributeOptions->meta(), true);
        if (!is_array($stored)) return [];

        $out = [];
        foreach ($stored as $tax => $opt) {
            $tax = sanitize_key((string) $tax);
            if ($tax === '' || !is_array($opt)) continue;

            $out[$tax] = [
                'in_variations' => !empty($opt['in_variations']),
                'main'          => !empty($opt['main']),
            ];
        }
        return $out;
    }

    /** @return float|string '' если не число. */
    private static function clean_price($value)
    {
        return is_numeric($value) ? (float) $value : '';
    }

    /**
     * Полная сверка атрибут-таксономий товара: выбранные применяются, остальные
     * очищаются (как «Save attributes» в Woo). Возвращает назначенные оси.
     *
     * @param mixed $rows  [['taxonomy'=>..,'terms'=>[..]], ...]
     * @return array<int, array{taxonomy:string, label:string, terms:array}>
     */
    private static function persist_attributes(int $post_id, $rows): array
    {
        $allowed  = ProductAttributes::taxonomy_names();
        $selected = [];
        $options  = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!is_array($row)) continue;
                $tax = sanitize_key((string) ($row['taxonomy'] ?? ''));
                if (!$tax || !in_array($tax, $allowed, true) || isset($selected[$tax])) continue;

                $terms = isset($row['terms']) ? (array) $row['terms'] : [];
                $selected[$tax] = array_values(array_unique(array_filter(array_map('intval', $terms))));
                $options[$tax]  = [
                    'in_variations' => !empty($row['in_variations']),
                    'main'          => !empty($row['main']),
                ];
            }
        }

        foreach ($allowed as $tax) {
            if (!taxonomy_exists($tax)) continue;
            wp_set_object_terms($post_id, $selected[$tax] ?? [], $tax, false);
        }

        // Флаги храним только для реально назначенных (с термами) таксономий.
        update_post_meta($post_id, ProductFieldsEnum::AttributeOptions->meta(), array_intersect_key($options, array_filter($selected)));

        return self::assigned_axes($post_id);
    }

    /**
     * Назначенные товару оси: таксономия + её НАЗНАЧЕННЫЕ термы (для сборки
     * селектов вариаций).
     *
     * @return array<int, array{taxonomy:string, label:string, terms:array<int, array{id:int, name:string}>}>
     */
    private static function assigned_axes(int $post_id): array
    {
        $options = self::attribute_options($post_id);

        $axes = [];
        foreach (ProductAttributes::taxonomy_names() as $tax) {
            if (!taxonomy_exists($tax)) continue;
            // Атрибут с флагом «не учитывать в вариациях» - не ось (но остаётся
            // назначенным: фильтрация + блок «Характеристики»). Легаси без флага - ось.
            if (isset($options[$tax]) && !$options[$tax]['in_variations']) continue;

            $terms = wp_get_object_terms($post_id, $tax, ['fields' => 'all']);
            if (is_wp_error($terms) || empty($terms)) continue;

            // Значение оси — каноничный (default-language) id, чтобы совпадало с
            // хранимым в матрице независимо от языка экрана; подпись - в текущем.
            $items = array_map(fn ($term) => ['id' => self::canonical_term((int) $term->term_id), 'name' => $term->name], $terms);

            $obj    = get_taxonomy($tax);
            $axes[] = ['taxonomy' => $tax, 'label' => $obj ? $obj->labels->singular_name : $tax, 'terms' => $items];
        }
        return $axes;
    }

    /**
     * Назначенные оси как { taxonomy => каноничные term_id[] } - для сверки
     * хранимых комбинаций/дефолта с актуальным набором значений.
     *
     * @return array<string, int[]>
     */
    private static function assigned_term_ids(int $post_id): array
    {
        $map = [];
        foreach (self::assigned_axes($post_id) as $axis) {
            $map[$axis['taxonomy']] = array_column($axis['terms'], 'id');
        }
        return $map;
    }

    /** term_id любого языка -> каноничный (default-language). Без Polylang - как есть. */
    public static function canonical_term(int $term_id): int
    {
        if ($term_id <= 0) return 0;
        if (!function_exists('pll_default_language') || !function_exists('pll_get_term')) return $term_id;

        $default = pll_default_language('slug');
        if (!$default) return $term_id;

        $canonical = (int) pll_get_term($term_id, $default);
        return $canonical > 0 ? $canonical : $term_id;
    }

    /** post_id любого языка -> каноничный (default-language). Без Polylang - как есть. */
    public static function canonical_post(int $post_id): int
    {
        if ($post_id <= 0) return 0;
        if (!function_exists('pll_default_language') || !function_exists('pll_get_post')) return $post_id;

        $default = pll_default_language('slug');
        if (!$default) return $post_id;

        $canonical = (int) pll_get_post($post_id, $default);
        return $canonical > 0 ? $canonical : $post_id;
    }

    /** Каноничный post_id -> версия текущего языка. Без Polylang/перевода - как есть. */
    public static function localized_post(int $canonical_id): int
    {
        if ($canonical_id <= 0) return 0;
        if (!function_exists('pll_current_language') || !function_exists('pll_get_post')) return $canonical_id;

        $lang = pll_current_language('slug');
        if (!$lang) return $canonical_id;

        $localized = (int) pll_get_post($canonical_id, $lang);
        return $localized > 0 ? $localized : $canonical_id;
    }

    /**
     * Каноничный post_id -> версия текущего языка, либо null если перевода нет.
     * Для фронта: товар без перевода на текущий язык показывать не нужно.
     * Без Polylang возвращает сам id (один язык).
     */
    public static function localized_post_or_null(int $canonical_id): ?int
    {
        if ($canonical_id <= 0) return null;
        if (!function_exists('pll_current_language') || !function_exists('pll_get_post')) return $canonical_id;

        $lang = pll_current_language('slug');
        if (!$lang) return $canonical_id;

        $localized = (int) pll_get_post($canonical_id, $lang);
        return $localized > 0 ? $localized : null; // нет перевода — не показываем
    }

    /** Каноничный term_id -> версия текущего языка. Без Polylang/перевода - как есть. */
    public static function localized_term(int $canonical_id): int
    {
        if ($canonical_id <= 0) return 0;
        if (!function_exists('pll_current_language') || !function_exists('pll_get_term')) return $canonical_id;

        $lang = pll_current_language('slug');
        if (!$lang) return $canonical_id;

        $localized = (int) pll_get_term($canonical_id, $lang);
        return $localized > 0 ? $localized : $canonical_id;
    }

    /** Каноничный id терма, если он принадлежит таксономии $tax; иначе null. */
    private static function canonical_in_taxonomy(int $id, string $tax): ?int
    {
        $canonical = self::canonical_term($id);
        $term      = get_term($canonical);

        return ($term instanceof \WP_Term && $term->taxonomy === $tax) ? $canonical : null;
    }

    /**
     * Комбинация по умолчанию: { taxonomy => каноничный term_id }. Возвращает
     * только валидные записи (ось назначена и терм среди её значений).
     */
    public static function default_attributes(int $post_id): array
    {
        $stored = get_post_meta($post_id, ProductFieldsEnum::DefaultAttrs->meta(), true);
        if (!is_array($stored)) return [];

        $assigned = self::assigned_term_ids($post_id);

        $out = [];
        foreach ($stored as $tax => $id) {
            $tax = sanitize_key((string) $tax);
            $id  = (int) $id;
            if (in_array($id, $assigned[$tax] ?? [], true)) {
                $out[$tax] = $id;
            }
        }
        return $out;
    }

    /** Санитайз дефолт-комбинации из $_POST: валидные таксономии-атрибуты + каноничные id. */
    private static function sanitize_default_attributes($raw): array
    {
        if (!is_array($raw)) return [];

        $allowed = ProductAttributes::taxonomy_names();
        $out     = [];

        foreach ($raw as $tax => $id) {
            $tax = sanitize_key((string) $tax);
            if (!in_array($tax, $allowed, true)) continue;

            $id = absint($id);
            if ($id <= 0) continue; // «— не задано —»

            $canonical = self::canonical_in_taxonomy($id, $tax);
            if ($canonical !== null) $out[$tax] = $canonical;
        }
        return $out;
    }

    /**
     * Нормализует матрицу из $_POST: каноничные id, валидация, ключ, дедуп,
     * отбрасывание неполных строк.
     */
    private static function normalize_variations($raw): array
    {
        if (!is_array($raw)) return [];

        $allowed = ProductAttributes::taxonomy_names();
        $out     = [];
        $seen    = [];

        foreach ($raw as $row) {
            if (!is_array($row)) continue;

            $attributes = self::normalize_variation_attributes($row['attributes'] ?? null, $allowed);
            if ($attributes === null) continue; // неполная/битая комбинация

            $key = self::variation_signature($attributes);
            if (isset($seen[$key])) continue;   // дубль комбинации
            $seen[$key] = true;

            // У вариации только цены — sku/наличие/статус общие на уровне товара.
            $out[] = [
                'key'        => $key,
                'attributes' => $attributes,
                'price'      => self::clean_price($row['price'] ?? ''),
                'sale_price' => self::clean_price($row['sale_price'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, int|string>|null  null - пустая/битая ось -> строку отбросить.
     */
    private static function normalize_variation_attributes($raw, array $allowed): ?array
    {
        if (!is_array($raw)) return null;

        $attributes = [];
        foreach ($raw as $taxonomy => $val) {
            $taxonomy = sanitize_key((string) $taxonomy);
            if (!in_array($taxonomy, $allowed, true)) continue;

            if ($val === self::WILDCARD) {
                $attributes[$taxonomy] = self::WILDCARD;
                continue;
            }

            $id = absint($val);
            if ($id <= 0) return null;

            $canonical = self::canonical_in_taxonomy($id, $taxonomy);
            if ($canonical === null) return null;

            $attributes[$taxonomy] = $canonical;
        }

        return $attributes !== [] ? $attributes : null;
    }

    private static function variation_signature(array $attributes): string
    {
        ksort($attributes);
        $parts = [];
        foreach ($attributes as $taxonomy => $val) {
            $parts[] = $taxonomy . '=' . $val;
        }
        return substr(md5(implode('|', $parts)), 0, 12);
    }
}
