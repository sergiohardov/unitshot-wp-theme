<?php declare(strict_types=1);

namespace UnitshotTheme\Classes;

use UnitshotTheme\DTO\BrandDTO;
use UnitshotTheme\DTO\CategoryDTO;
use UnitshotTheme\DTO\FileDTO;
use UnitshotTheme\DTO\ImageDTO;
use UnitshotTheme\DTO\ProductDTO;
use UnitshotTheme\Enums\ProductFieldsEnum;
use UnitshotTheme\Enums\ProductStatusesEnum;
use UnitshotTheme\Enums\ProductTypesEnum;
use UnitshotTheme\Enums\TaxonomiesEnum;
use UnitshotTheme\Enums\TypesEnum;

if (!defined("ABSPATH")) die;

class Helpers
{
    /**
     * Форматирование цены с учетом выбраной валюты
     *
     * @param float|null $price
     * @return string|null
     */
    public static function format_price(?float $price): ?string {
        if(!is_numeric($price)) return null;
        return number_format($price * Currency::get_current_currency()->value, 0, '.', ' ') . ' ' . Currency::get_current_currency()->code;
    }

    /**
     * Дата поста в локали текущего языка Polylang (название месяца переводится).
     *
     * get_the_date()/date_i18n() берут название месяца из глобальной локали
     * WordPress, которая на фронте не всегда переключена под текущий язык - из-за
     * этого месяц выводится по-английски. Здесь на время форматирования явно
     * переключаемся на локаль текущего языка (switch_to_locale), затем
     * восстанавливаем прежнюю.
     *
     * @param int    $post_id
     * @param string $format формат даты (по умолчанию «j F Y»)
     * @return string
     */
    public static function post_date(int $post_id, string $format = 'j F Y'): string
    {
        $timestamp = get_post_timestamp($post_id);
        if (!$timestamp) return '';

        $locale = function_exists('pll_current_language') ? (string) pll_current_language('locale') : '';

        // Украинский язык в Polylang настроен на нестандартную локаль "ua_UA", под
        // которую у WordPress нет перевода (есть только "uk.mo") - месяцы выпадают
        // в английский. Маппим на реальную локаль WP. Правильно было бы выставить
        // "uk" в настройках языка Polylang, но маппинг страхует и без этого.
        $locale = ['ua_UA' => 'uk'][$locale] ?? $locale;

        $switched = $locale !== '' && $locale !== get_locale() ? switch_to_locale($locale) : false;

        $date = wp_date($format, $timestamp);

        if ($switched) restore_previous_locale();

        return is_string($date) ? $date : '';
    }

    /**
     * Унифицированная карточка товара для сеток/слайдеров (каталог, popular,
     * showcase). Единый источник правил: бейджи (скидка/статус), цена с «від»
     * для вариативных без дефолт-комбинации, первое изображение галереи.
     *
     * @return array{id:int, tags:array, image:array, title:array{link:string,text:string}, price:array{old:?string,new:?string}}
     */
    public static function product_card_dto(ProductDTO $product): array
    {
        $tags = [];

        $discount = $product->discount_percent();
        if ($discount > 0) $tags[] = ['status' => 'discount', 'text' => '-' . $discount . '%'];
        if ($product->status) $tags[] = ['status' => 'info', 'text' => $product->status->status_label()];

        $price_new = self::format_price($product->display_price());
        if ($price_new !== null && $product->price_is_from()) $price_new = 'від ' . $price_new;

        $image = isset($product->gallery[0]) ? $product->gallery[0]->get_array() : [];

        return [
            'id'    => $product->id,
            'tags'  => $tags,
            'image' => $image,
            'title' => ['link' => $product->url, 'text' => $product->name],
            'price' => ['old' => self::format_price($product->display_old_price()), 'new' => $price_new],
            'add'   => ['is_variable' => $product->is_variable(), 'id' => $product->id, 'url' => $product->url], // Кнопка покупки: у вариативного нельзя выбрать вариацию на карточке - ведём на страницу товара; простой/набор добавляются прямо с карточки.
        ];
    }

    public static function get_product_dto_by_id(int $post_id): ?ProductDTO
    {
        $product = get_post($post_id);
        if ($post_id === 0 || !is_a($product, 'WP_Post') || $product->post_type !== TypesEnum::Product->value) return null;

        $id = $product->ID;

        $type   = ProductTypesEnum::tryFrom((string) get_post_meta($id, ProductFieldsEnum::Type->meta(), true)) ?? ProductTypesEnum::Simple;
        $status = ProductStatusesEnum::tryFrom((string) get_post_meta($id, ProductFieldsEnum::Status->meta(), true));
        $sku    = (string) get_post_meta($id, ProductFieldsEnum::Sku->meta(), true);
        $base   = get_post_meta($id, ProductFieldsEnum::BasePrice->meta(), true);
        $sale   = get_post_meta($id, ProductFieldsEnum::SalePrice->meta(), true);
        $model = (string) get_post_meta($id, ProductFieldsEnum::Model->meta(), true);

        // Галерея: миниатюра + _us_gallery
        $gallery = [];
        if ($thumb = get_post_thumbnail_id($id)) $gallery[] = new ImageDTO((int) $thumb);
        foreach ((array) get_post_meta($id, ProductFieldsEnum::Gallery->meta(), true) as $aid) {
            if (($aid = (int) $aid) > 0) $gallery[] = new ImageDTO($aid);
        }

        // Бренды / категории
        $brands = wp_get_post_terms($id, TaxonomiesEnum::ProductBrand->value);
        $brands = is_array($brands) ? array_map(fn ($b) => BrandDTO::from_wp_term($b), $brands) : [];

        $categories = wp_get_post_terms($id, TaxonomiesEnum::ProductCategory->value);
        if (is_array($categories)) {
            usort($categories, fn ($a, $b) => ($a->parent <=> $b->parent));
            $categories = array_map(fn ($c) => CategoryDTO::from_wp_term($c), $categories);
        } else {
            $categories = [];
        }

        // Файлы (доп. материалы) - только у simple/variable, как на экране редактирования.
        $files = in_array($type, [ProductTypesEnum::Simple, ProductTypesEnum::Variable], true)
            ? array_values(array_filter(array_map(
                fn ($fid) => FileDTO::from_attachment((int) $fid),
                (array) get_post_meta($id, ProductFieldsEnum::Files->meta(), true)
            )))
            : [];

        // Атрибуты (назначенные термы) + оси для селектора
        [$attributes, $axes] = self::collect_product_attributes($id);

        // Вариации — только у вариативного
        $variations = $type === ProductTypesEnum::Variable
            ? array_values(array_filter((array) get_post_meta($id, ProductFieldsEnum::Variations->meta(), true), 'is_array'))
            : [];

        // Состав набора - только у complete. Храним {id, variation} (каноничные id),
        // резолвим в текущий язык; члены - simple/variable, без вложенности.
        // Для вариативного члена цена = выбранная вариация (иначе дефолт/min товара).
        $set_items = [];
        if ($type === ProductTypesEnum::Complete) {
            foreach ((array) get_post_meta($id, ProductFieldsEnum::SetItems->meta(), true) as $row) {
                // Совместимость со старым форматом (плоский id) и новым ({id, variation}).
                $member_canonical = is_array($row) ? (int) ($row['id'] ?? 0) : (int) $row;
                $variation_key    = is_array($row) ? (string) ($row['variation'] ?? '') : '';
                if ($member_canonical <= 0) continue;

                $member = self::get_product_dto_by_id(Products::localized_post($member_canonical));
                if (!$member || $member->is_complete()) continue;

                if ($member->is_variable() && $variation_key !== '') {
                    $price = $member->variation_price($variation_key);
                    $old   = $member->variation_old($variation_key);
                } else {
                    $price = $member->display_price();
                    $old   = $member->display_old_price();
                }

                $set_items[] = [
                    'product'       => $member,
                    'variation_key' => $variation_key !== '' ? $variation_key : null,
                    'price'         => $price,
                    'old_price'     => $old,
                ];
            }
        }

        return new ProductDTO(
            id:          $id,
            name:        $product->post_title,
            url:         (string) get_the_permalink($id),
            type:        $type,
            sku:         $sku !== '' ? $sku : null,
            status:      $status,
            in_stock:    (bool) get_post_meta($id, ProductFieldsEnum::InStock->meta(), true),
            base_price:  is_numeric($base) ? (float) $base : null,
            sale_price:  is_numeric($sale) ? (float) $sale : null,
            description: (string) get_post_meta($id, ProductFieldsEnum::Description->meta(), true),
            short_description: (string) get_post_meta($id, ProductFieldsEnum::ShortDescription->meta(), true),
            model:       $model,
            gallery:     $gallery,
            files:       $files,
            brands:      $brands,
            categories:  $categories,
            attributes:  $attributes,
            axes:        $axes,
            variations:  $variations,
            default_attributes: $type === ProductTypesEnum::Variable ? Products::default_attributes($id) : [],
            set_items:   $set_items,
            parameters:  self::collect_product_parameters($id),
        );
    }

    /**
     * Свободные параметры товара: {label (имя терма в текущем языке), value, main}.
     * Хранятся каноничным term_id + значение (общее между языками); имя — из терма.
     *
     * @return array<int, array{label:string, value:string, main:bool}>
     */
    private static function collect_product_parameters(int $post_id): array
    {
        $stored = get_post_meta($post_id, ProductFieldsEnum::Parameters->meta(), true);
        if (!is_array($stored)) return [];

        $out = [];
        foreach ($stored as $row) {
            if (!is_array($row)) continue;

            $canonical = (int) ($row['term'] ?? 0);
            $value     = (string) ($row['value'] ?? '');
            if ($canonical <= 0 || $value === '') continue;

            $term = get_term(Products::localized_term($canonical));
            if (!$term instanceof \WP_Term) continue;

            $out[] = ['label' => $term->name, 'value' => $value, 'main' => !empty($row['main'])];
        }
        return $out;
    }

    /**
     * Наборы (complete), в состав которых входит товар. Реверс по зеркалу
     * SetMember (каноничные id). WP_Query с Polylang сам фильтрует по текущему
     * языку, так что наборы возвращаются на языке страницы.
     *
     * @return ProductDTO[]
     */
    public static function get_sets_for_product(int $product_id): array
    {
        $canonical = Products::canonical_post($product_id);
        if ($canonical <= 0) return [];

        $ids = get_posts([
            'post_type'      => TypesEnum::Product->value,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [[
                'key'   => ProductFieldsEnum::SetMember->meta(),
                'value' => (string) $canonical,
            ]],
        ]);

        $sets = [];
        foreach ($ids as $set_id) {
            $set = self::get_product_dto_by_id((int) $set_id);
            if ($set && $set->is_complete()) $sets[] = $set;
        }
        return $sets;
    }

    /**
     * Назначенные товару атрибуты: список для «характеристик» (label + значения)
     * и оси для селектора вариаций (label + термы с id).
     *
     * @return array{0: array<int, array{taxonomy:string, label:string, values:string[], main:bool}>, 1: array<int, array{taxonomy:string, label:string, terms:array<int, array{id:int, name:string}>}>}
     */
    private static function collect_product_attributes(int $post_id): array
    {
        $attributes = [];
        $axes       = [];
        $labels     = ProductAttributes::taxonomy_choices();   // [taxonomy => имя атрибута]
        $options    = Products::attribute_options($post_id);   // [taxonomy => {in_variations, main}]

        foreach (ProductAttributes::taxonomy_names() as $tax) {
            if (!taxonomy_exists($tax)) continue;

            $terms = wp_get_object_terms($post_id, $tax, ['fields' => 'all']);
            if (is_wp_error($terms) || empty($terms)) continue;

            // Ярлык атрибута переводится через String Translations (см. Polylang::register_attribute_strings)
            $label         = pll__($labels[$tax] ?? $tax);
            $in_variations = $options[$tax]['in_variations'] ?? true; // легаси без флага - ось
            $is_main       = $options[$tax]['main'] ?? false;

            $names = [];
            $items = [];
            foreach ($terms as $term) {
                $names[] = $term->name;
                // Каноничный (default-language) id - чтобы совпадало с матрицей вариаций
                // независимо от языка страницы; подпись - в текущем языке.
                $items[] = ['id' => Products::canonical_term((int) $term->term_id), 'name' => $term->name];
            }

            // Характеристики - все назначенные (флаг main -> блок «Основні»).
            $attributes[] = ['taxonomy' => $tax, 'label' => $label, 'values' => $names, 'main' => $is_main];

            // Оси селектора - только варьируемые.
            if ($in_variations) {
                $axes[] = ['taxonomy' => $tax, 'label' => $label, 'terms' => $items];
            }
        }

        return [$attributes, $axes];
    }
}
