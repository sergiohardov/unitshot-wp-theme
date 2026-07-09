<?php declare(strict_types=1);

namespace UnitshotTheme\Classes;

use UnitshotTheme\DTO\ProductDTO;
use UnitshotTheme\Enums\TypesEnum;
use WP_REST_Request;
use WP_REST_Response;

if (!defined("ABSPATH")) die;

/**
 * Корзина товаров.
 *
 * Хранилище - httponly-кука us_cart с JSON-массивом строк
 * [{ id: каноничный (default-language) post_id, variation: ключ вариации|'', qty }].
 * Каноничные id делают корзину языконезависимой: одна и та же позиция видна на
 * всех языках, где у товара есть перевод.
 *
 * Все мутации (add/update/remove) проходят через сервер и валидируются здесь -
 * клиент не может положить в корзину то, чего нет (несуществующий товар,
 * невалидную/чужую вариацию). Кука httponly + SameSite=Lax, пишет только сервер.
 *
 * Вывод (view) резолвит каноничные id в текущий язык; позиции без перевода на
 * текущем языке пропускаются (скрыты и не считаются), но остаются в хранилище.
 *
 * @package UnitshotTheme
 */
class Cart
{
    public const MAX_QTY = 99;

    /* ----------------------------- mutations ----------------------------- */

    /**
     * Добавить товар (по id текущего языка) с КОНКРЕТНОЙ комбинацией атрибутов
     * { taxonomy => term_id } (пусто для простых/наборов). Возвращает вид корзины.
     *
     * @param array<string, int> $attrs
     * @return array<string, mixed>
     */
    public static function add(int $product_id, array $attrs, int $qty, ?string $lang = null): array
    {
        $canonical = Products::canonical_post($product_id);
        $attrs     = self::normalize_attrs($canonical, $attrs);
        $qty       = self::clamp($qty);

        if ($canonical > 0 && $attrs !== null && $qty > 0) {
            $signature = self::signature($attrs);
            $items     = Cookies::get_cart();
            $found     = false;

            foreach ($items as &$item) {
                if ($item['id'] === $canonical && ($item['variation'] ?? '') === $signature) {
                    $item['qty'] = self::clamp($item['qty'] + $qty);
                    $found = true;
                    break;
                }
            }
            unset($item);

            if (!$found) $items[] = ['id' => $canonical, 'variation' => $signature, 'attrs' => $attrs, 'qty' => $qty];

            Cookies::set_cart($items);
        }

        return self::view($lang);
    }

    /**
     * Установить количество позиции (0 - удалить). id - каноничный.
     *
     * @return array<string, mixed>
     */
    public static function update(int $canonical, string $variation, int $qty, ?string $lang = null): array
    {
        $qty   = max(0, min(self::MAX_QTY, $qty));
        $items = [];

        foreach (Cookies::get_cart() as $item) {
            if ($item['id'] === $canonical && $item['variation'] === $variation) {
                if ($qty === 0) continue; // удаляем
                $item['qty'] = $qty;
            }
            $items[] = $item;
        }

        Cookies::set_cart($items);
        return self::view($lang);
    }

    /**
     * Удалить позицию. id - каноничный.
     *
     * @return array<string, mixed>
     */
    public static function remove(int $canonical, string $variation, ?string $lang = null): array
    {
        return self::update($canonical, $variation, 0, $lang);
    }

    /** Полностью очистить корзину. */
    public static function clear(): void
    {
        Cookies::set_cart([]);
    }

    /* ----------------------------- validation ----------------------------- */

    private static function clamp(int $qty): int
    {
        return max(1, min(self::MAX_QTY, $qty));
    }

    /**
     * Нормализует/валидирует КОНКРЕТНУЮ комбинацию атрибутов для товара.
     * - simple/complete: комбинации нет -> [].
     * - variable: требуется выбор по КАЖДОЙ оси, и комбинация должна продаваться
     *   (совпадать хотя бы с одной вариацией, с учётом wildcard '*').
     * Возвращает нормализованную карту { taxonomy => canonical term_id } ([] для
     * простых) или null, если товар/комбинация невалидны.
     *
     * @param array<string, int> $attrs
     * @return array<string, int>|null
     */
    private static function normalize_attrs(int $canonical, array $attrs): ?array
    {
        if ($canonical <= 0) return null;

        $post = get_post($canonical);
        if (!$post || $post->post_type !== TypesEnum::Product->value || $post->post_status !== 'publish') return null;

        $dto = Helpers::get_product_dto_by_id($canonical);
        if (!$dto) return null;

        if (!$dto->is_variable()) return []; // комбинация не применима

        // Оси и значения каноничны (одинаковы во всех языках).
        $clean = [];
        foreach ($dto->axes as $axis) {
            $taxonomy = $axis['taxonomy'];
            $id       = (int) ($attrs[$taxonomy] ?? 0);
            if ($id <= 0) return null; // ось не выбрана
            $clean[$taxonomy] = $id;
        }

        return $dto->match_variation($clean) !== null ? $clean : null;
    }

    /**
     * Стабильная строка-подпись комбинации (идентичность позиции в корзине).
     * Пусто для простых. Порядок осей нормализуем, чтобы подпись не зависела от него.
     *
     * @param array<string, int> $attrs
     */
    private static function signature(array $attrs): string
    {
        ksort($attrs);

        $parts = [];
        foreach ($attrs as $taxonomy => $id) {
            $parts[] = $taxonomy . ':' . (int) $id;
        }
        return implode('|', $parts);
    }

    /* ----------------------------- view ----------------------------- */

    /**
     * Вид корзины для ТЕКУЩЕГО языка: позиции (с резолвом каноничный -> локальный),
     * количество, сумма, ссылка на оформление. Позиции без перевода пропускаются.
     *
     * @return array{items:array<int,array<string,mixed>>, count:int, total:?float}
     */
    public static function view(?string $lang = null): array
    {
        // В REST Polylang не знает язык - принимаем его параметром; на фронте берём текущий.
        $lang  = $lang ?: (function_exists('pll_current_language') ? (string) pll_current_language('slug') : '');
        $items = [];
        $count = 0;
        $total = 0.0;

        foreach (Cookies::get_cart() as $row) {
            $localized = self::localized($row['id'], $lang);
            if ($localized === null) continue; // нет перевода на текущем языке - скрываем

            $dto = Helpers::get_product_dto_by_id($localized);
            if (!$dto) continue;

            [$price, $old, $label] = self::resolve_price($dto, $row['attrs']);
            if ($price === null) continue;

            $qty   = $row['qty'];
            $count += $qty;
            $total += $price * $qty;

            $items[] = [
                'canonical'       => $row['id'],         // для мутаций
                'variation'       => $row['variation'],  // для мутаций
                'product_id'      => $localized,
                'url'             => $dto->url,
                'title'           => $dto->name,
                'image'           => isset($dto->gallery[0]) ? $dto->gallery[0]->get_array() : ['src' => null, 'alt' => null],
                'variation_label' => $label,
                'qty'             => $qty,
                'price'           => $price,                              // за единицу, в базовой валюте
                'old'             => $old,                                // старая за единицу (null - без скидки)
                'line'            => $price * $qty,                       // сумма строки
                'old_line'        => $old !== null ? $old * $qty : null,  // старая сумма строки (зачёркнутая)
            ];
        }

        return [
            'items' => $items,
            'count' => $count,
            'total' => $items ? $total : null,
        ];
    }

    /** Кол-во позиций для бейджа в шапке (текущий язык). */
    public static function count(): int
    {
        return (int) self::view()['count'];
    }

    /**
     * Каноничный post_id -> id перевода в нужном языке; null, если перевода нет.
     */
    private static function localized(int $canonical, string $lang): ?int
    {
        if (!function_exists('pll_get_post') || $lang === '') return $canonical > 0 ? $canonical : null;

        $localized = (int) pll_get_post($canonical, $lang);
        return $localized > 0 ? $localized : null;
    }

    /**
     * Цена за единицу + старая (зачёркнутая) цена + подпись для позиции.
     * variable: по КОНКРЕТНОЙ комбинации ($attrs) - цену даёт сматченная вариация
     * (с учётом wildcard), а подпись строится из реального выбора пользователя
     * (без «любое»). Простой/набор - display_price/display_old_price.
     *
     * @param array<string, int> $attrs
     * @return array{0: ?float, 1: ?float, 2: string} [price, old, label]
     */
    private static function resolve_price(ProductDTO $dto, array $attrs): array
    {
        if (!$dto->is_variable()) {
            return [$dto->display_price(), $dto->display_old_price(), ''];
        }

        if (!$attrs) return [null, null, ''];

        $variation = $dto->match_variation($attrs);
        if ($variation === null) return [null, null, ''];

        $eff = $dto->variation_effective($variation);
        if ($eff['price'] === null) return [null, null, ''];

        return [$eff['price'], $eff['old'], $dto->attributes_label($attrs)];
    }

    /* ----------------------------- REST ----------------------------- */

    /** CSRF-защита: мутации требуют валидный wp_rest nonce (шлёт фронт в X-WP-Nonce). */
    public static function rest_permission(WP_REST_Request $request): bool
    {
        return (bool) wp_verify_nonce((string) $request->get_header('X-WP-Nonce'), 'wp_rest');
    }

    public static function rest_add(WP_REST_Request $request): WP_REST_Response
    {
        // attrs = { taxonomy => term_id }; санитайзинг и валидация - в normalize_attrs.
        $attrs = $request->get_param('attrs');

        return new WP_REST_Response(self::add(
            absint($request->get_param('product_id')),
            is_array($attrs) ? $attrs : [],
            (int) $request->get_param('qty'),
            sanitize_key((string) $request->get_param('lang'))
        ), 200);
    }

    public static function rest_update(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(self::update(
            absint($request->get_param('id')),
            sanitize_text_field((string) $request->get_param('variation')),
            (int) $request->get_param('qty'),
            sanitize_key((string) $request->get_param('lang'))
        ), 200);
    }

    public static function rest_remove(WP_REST_Request $request): WP_REST_Response
    {
        return new WP_REST_Response(self::remove(
            absint($request->get_param('id')),
            sanitize_text_field((string) $request->get_param('variation')),
            sanitize_key((string) $request->get_param('lang'))
        ), 200);
    }
}
