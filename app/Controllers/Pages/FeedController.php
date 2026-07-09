<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Pages;

use UnitshotTheme\Classes\Currency;
use UnitshotTheme\Classes\Fields;
use UnitshotTheme\Classes\Helpers;
use UnitshotTheme\DTO\ProductDTO;
use UnitshotTheme\Enums\PageSlugsEnum;
use UnitshotTheme\Enums\RoutesEnum;
use UnitshotTheme\Enums\TypesEnum;
use UnitshotTheme\Routing;
use WP_Query;

if (!defined("ABSPATH")) die;

class FeedController
{
    private const CACHE_PREFIX      = 'unitshot_feed_google_';
    private const CACHE_TTL         = DAY_IN_SECONDS;
    private const NS                = 'http://base.google.com/ns/1.0';
    private const TOGGLE_FIELD_KEY  = 'unitshot_options_general_feed_update';

    /**
     * Отдаёт XML фида (из кэша) для текущего языка. Статус 200 и выход (exit)
     * выставляет Routing.
     *
     * @see Routing::render()
     */
    public function render(): void
    {
        $lang = function_exists('pll_current_language') ? (string) pll_current_language() : '';

        header('Content-Type: application/xml; charset=utf-8');

        echo self::get_or_build($lang);
    }

    /**
     * Пересборка фида: сбрасываем кэш и перезапрашиваем каждый язык loopback-ом
     * (запрос пересоберёт и закэширует фид в корректном языковом контексте
     * Polylang). Вызывается по Cron (дважды в день) и вручную из настроек.
     *
     * @see \UnitshotTheme\Cron
     * @see acf_manual_refresh()
     */
    public static function refresh(): void
    {
        self::invalidate();

        foreach (self::languages() as $lang) {
            wp_remote_get(Routing::url(RoutesEnum::Feed, $lang), ['timeout' => 30, 'blocking' => false]);
        }
    }

    /**
     * Ручное обновление фида из настроек после запуска тумблер сбрасывается обратно в off.
     *
     * @see \UnitshotTheme\Acf
     * @param int|string $post_id
     * @param string     $menu_slug
     */
    public static function acf_manual_refresh($post_id, $menu_slug): void
    {
        if ($menu_slug !== PageSlugsEnum::General->value) return;
        if (empty(Fields::get_settings_general()['feed_update'])) return;

        self::refresh();

        // Сбрасываем тумблер точечно по ключу поля, чтобы не трогать остальные
        // поля группы «Общие».
        update_field(self::TOGGLE_FIELD_KEY, false, 'options');
    }

    /**
     * Сброс кэша всех языков.
     */
    public static function invalidate(): void
    {
        foreach (self::languages() as $lang) {
            delete_transient(self::CACHE_PREFIX . ($lang !== '' ? $lang : 'default'));
        }
    }

    /**
     * XML из кэша, иначе собрать и закэшировать (ленивая сборка при промахе).
     */
    private static function get_or_build(string $lang): string
    {
        $key    = self::CACHE_PREFIX . ($lang !== '' ? $lang : 'default');
        $cached = get_transient($key);
        if (is_string($cached) && $cached !== '') return $cached;

        $xml = self::build();
        set_transient($key, $xml, self::CACHE_TTL);

        return $xml;
    }

    /**
     * Список языков (slug).
     *
     * @return string[]
     */
    private static function languages(): array
    {
        $langs = function_exists('pll_languages_list') ? (array) pll_languages_list(['fields' => 'slug']) : [];

        return $langs ?: [''];
    }

    /**
     * Собирает фид для текущего языкового контекста (Polylang фильтрует выборку).
     */
    private static function build(): string
    {
        $lang = function_exists('pll_current_language') ? (string) pll_current_language() : '';

        $args = [
            'post_type'           => TypesEnum::Product->value,
            'post_status'         => 'publish',
            'posts_per_page'      => -1,
            'fields'              => 'ids',
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
        ];
        if ($lang !== '') $args['lang'] = $lang;

        $items = '';
        foreach ((new WP_Query($args))->posts as $post_id) {
            $product = Helpers::get_product_dto_by_id((int) $post_id);
            if ($product !== null) $items .= self::product_items($product);
        }

        return self::wrap($items);
    }

    /**
     * Оборачивает товарные <item> в канал RSS.
     */
    private static function wrap(string $items): string
    {
        $channel =
            self::cdata('title', (string) get_bloginfo('name')) .
            self::el('link', (string) home_url('/')) .
            self::cdata('description', (string) get_bloginfo('description')) .
            $items;

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:g="' . self::NS . '">' . "\n"
            . '<channel>' . "\n" . $channel . '</channel>' . "\n"
            . '</rss>' . "\n";
    }

    /**
     * Один или несколько <item> для товара: вариативный - по item на вариацию,
     * остальные - единым item. Позиции без валидной цены пропускаются.
     */
    private static function product_items(ProductDTO $product): string
    {
        $group_id = $product->sku ?? ('p' . $product->id);

        if ($product->is_variable()) {
            $names  = self::axis_term_names($product);
            $output = '';

            foreach ($product->variations as $variation) {
                $key = (string) ($variation['key'] ?? '');
                if ($key === '') continue;

                $eff = $product->variation_effective($variation);
                if ($eff['price'] === null) continue;

                [$label, $details] = self::variation_label($variation, $names);

                $output .= self::item([
                    'id'            => $group_id . '-' . $key,
                    'item_group_id' => $group_id,
                    'title'         => $label !== '' ? $product->name . ' — ' . $label : $product->name,
                    'price'         => $eff['price'],
                    'old'           => $eff['old'],
                    'details'       => $details,
                ], $product);
            }

            return $output;
        }

        // simple / complete - единый item.
        $price = $product->display_price();
        if ($price === null) return '';

        return self::item([
            'id'            => $group_id,
            'item_group_id' => null,
            'title'         => $product->name,
            'price'         => $price,
            'old'           => $product->display_old_price(),
            'details'       => [],
        ], $product);
    }

    /**
     * Рендер одного <item>.
     *
     * @param array{id:string, item_group_id:?string, title:string, price:float, old:?float, details:array<int,array{name:string,value:string}>} $row
     */
    private static function item(array $row, ProductDTO $product): string
    {
        [$price, $sale] = self::price_pair($row['price'], $row['old']);

        $description = $product->short_description !== '' ? $product->short_description : $product->description;
        $description = trim(wp_strip_all_tags($description)) ?: $product->name;

        $xml  = self::el('g:id', $row['id']);
        if ($row['item_group_id'] !== null) $xml .= self::el('g:item_group_id', $row['item_group_id']);

        $xml .= self::cdata('g:title', $row['title']);
        $xml .= self::cdata('g:description', $description);
        $xml .= self::el('g:link', $product->url);
        $xml .= self::el('g:condition', 'new');
        $xml .= self::el('g:availability', $product->in_stock ? 'in_stock' : 'out_of_stock');
        $xml .= self::el('g:price', $price);
        if ($sale !== null) $xml .= self::el('g:sale_price', $sale);

        // Изображения: первое - основное, остальные (до 10) - дополнительные.
        if (isset($product->gallery[0]->src) && $product->gallery[0]->src) {
            $xml .= self::el('g:image_link', $product->gallery[0]->src);
        }
        foreach (array_slice($product->gallery, 1, 10) as $image) {
            if (!empty($image->src)) $xml .= self::el('g:additional_image_link', $image->src);
        }

        // Бренд + идентификатор товара.
        $brand = $product->brands[0]->name ?? '';
        if ($brand !== '') $xml .= self::cdata('g:brand', $brand);

        $mpn = $product->sku ?? ($product->model !== '' ? $product->model : null);
        if ($mpn !== null) {
            $xml .= self::el('g:mpn', $mpn);
        } else {
            $xml .= self::el('g:identifier_exists', 'no');
        }

        // Оси вариации как произвольные характеристики (универсально, без маппинга
        // на конкретные g:size/g:color - состав осей у товаров разный).
        foreach ($row['details'] as $detail) {
            $xml .= '<g:product_detail>'
                . self::cdata('g:attribute_name', $detail['name'])
                . self::cdata('g:attribute_value', $detail['value'])
                . '</g:product_detail>';
        }

        return '<item>' . $xml . '</item>' . "\n";
    }

    /**
     * Регулярная/акционная цена для Google: при наличии старой цены большей
     * текущей - price = старая, sale_price = текущая; иначе только price.
     *
     * @return array{0:string, 1:?string}
     */
    private static function price_pair(float $price, ?float $old): array
    {
        if ($old !== null && $old > $price) {
            return [self::money($old), self::money($price)];
        }

        return [self::money($price), null];
    }

    /**
     * Форматирование суммы в валюте «по умолчанию»: «1299.00 UAH».
     */
    private static function money(float $amount): string
    {
        $currency = Currency::get_default_currency();
        return number_format($amount * $currency->value, 2, '.', '') . ' ' . $currency->code;
    }

    /**
     * Карта имён термов по осям: [taxonomy][term_id] => name (текущий язык).
     *
     * @return array<string, array<int, string>>
     */
    private static function axis_term_names(ProductDTO $product): array
    {
        $names = [];
        foreach ($product->axes as $axis) {
            foreach ($axis['terms'] as $term) {
                $names[$axis['taxonomy']][(int) $term['id']] = (string) $term['name'];
            }
        }
        return $names;
    }

    /**
     * Подпись комбинации и её разбивка по осям для g:product_detail.
     *
     * @param array<string, array<int, string>> $names
     * @return array{0:string, 1:array<int,array{name:string,value:string}>}
     */
    private static function variation_label(array $variation, array $names): array
    {
        $parts   = [];
        $details = [];

        foreach (($variation['attributes'] ?? []) as $taxonomy => $value) {
            if ($value === '*') continue;

            $term_name  = $names[$taxonomy][(int) $value] ?? ('#' . $value);
            $axis_label = self::axis_label($taxonomy);

            $parts[]   = $term_name;
            $details[] = ['name' => $axis_label, 'value' => $term_name];
        }

        return [implode(' / ', $parts), $details];
    }

    /**
     * Человекочитаемое имя оси (ярлык таксономии), иначе сам slug.
     */
    private static function axis_label(string $taxonomy): string
    {
        $object = get_taxonomy($taxonomy);
        return $object ? $object->labels->singular_name : $taxonomy;
    }

    private static function el(string $name, string $value): string
    {
        return "<$name>" . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</$name>";
    }

    private static function cdata(string $name, string $value): string
    {
        return "<$name><![CDATA[" . str_replace(']]>', ']]&gt;', $value) . "]]></$name>";
    }
}
