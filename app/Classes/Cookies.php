<?php declare(strict_types=1);

namespace UnitshotTheme\Classes;

class Cookies
{
    public static function get_currency(): ?string
    {
        if ($_COOKIE['currency'] === null) self::set_currency(Currency::get_default_currency()->code);
        return $_COOKIE['currency'];
    }

    public static function set_currency(?string $currency): void
    {
        $allowed = Currency::get_codes();

        if (in_array($currency, $allowed)) {
            setcookie('currency', $currency, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
            $_COOKIE['currency'] = $currency;
        }
    }

    /**
     * Показывали ли уже RU-посетителю предложение украинской версии.
     * Флаг ставит фронт (document.cookie) при показе модалки - сервер только читает.
     *
     * @return bool
     */
    public static function is_lang_suggest_seen(): bool
    {
        return !empty($_COOKIE['us_lang_suggest']);
    }

    /**
     * @return array<int, array{id:int, variation:string, attrs:array<string,int>, qty:int}>
     */
    public static function get_cart(): array
    {
        $data = json_decode((string) wp_unslash($_COOKIE['us_cart'] ?? ''), true);
        if (!is_array($data)) return [];

        $items = [];
        foreach ($data as $row) {
            if (!is_array($row)) continue;

            $id  = (int) ($row['id'] ?? 0);
            $qty = (int) ($row['qty'] ?? 0);
            if ($id <= 0 || $qty <= 0) continue;

            // Конкретная комбинация выбранных термов { taxonomy => canonical term_id }.
            // Хранится, чтобы у вариативных с wildcard-осью не терялся выбор
            // пользователя (иначе в заявку уходит «любое» вместо конкретного значения).
            $attrs = [];
            foreach ((array) ($row['attrs'] ?? []) as $tax => $term) {
                $tax  = sanitize_key((string) $tax);
                $term = (int) $term;
                if ($tax !== '' && $term > 0) $attrs[$tax] = $term;
            }

            $items[] = [
                'id'        => $id,
                'variation' => (string) ($row['variation'] ?? ''),
                'attrs'     => $attrs,
                'qty'       => min(Cart::MAX_QTY, $qty),
            ];
        }
        return $items;
    }

    /**
     * @param array<int, array{id:int, variation:string, qty:int}> $items
     * @return void
     */
    public static function set_cart(array $items): void
    {
        $json   = wp_json_encode(array_values($items)) ?: '[]';
        $params = [
            'expires'  => time() + 30 * DAY_IN_SECONDS,
            'path'     => COOKIEPATH ?: '/',
            'domain'   => COOKIE_DOMAIN ?: '',
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        setcookie('us_cart', $json, $params);
        $_COOKIE['us_cart'] = $json; // чтобы view() в этом же запросе видел изменения
    }
}
