<?php declare(strict_types=1);

namespace UnitshotTheme;

use UnitshotTheme\Controllers\Pages\CheckoutController;
use UnitshotTheme\Controllers\Pages\FeedController;
use UnitshotTheme\Enums\RoutesEnum;

if (!defined("ABSPATH")) die;

/**
 * Кастомные маршруты темы.
 *
 * Единая точка истины для путей, которых нет среди обычных страниц/архивов
 * (оформление заказа и т.п.): slug хранится в RoutesEnum, мультиязычный URL
 * строится здесь (self::url) - фронт берёт URL с сервера, без хардкода.
 *
 * Резолв сделан через template_redirect, а не rewrite-правила: так маршрут
 * срабатывает на любом языковом префиксе Polylang (/checkout/, /en/checkout/)
 * без ручной трансляции rewrite-групп и без flush. Запрос сопоставляем по пути
 * (со снятым языковым префиксом) с RoutesEnum и сами отдаём ответ - страницу
 * (Twig-контроллер) или фид (Feed).
 *
 * @package UnitshotTheme
 */
class Routing
{
    public function __construct()
    {
        add_action('template_redirect', [$this, 'render']);
    }

    /**
     * Если текущий URL - кастомный маршрут, рендерим его контроллер и выходим
     * (с 200, даже если WP пометил запрос как 404 - правила для него нет).
     *
     * @return void
     */
    public function render(): void
    {
        $route = self::match();
        if ($route === null) return;

        status_header(200);
        nocache_headers();

        match ($route) {
            RoutesEnum::Checkout => (new CheckoutController())->render(),
            RoutesEnum::Feed     => (new FeedController())->render(),
        };

        exit;
    }

    /**
     * Сопоставляет текущий запрос с кастомным маршрутом. Языковой префикс
     * (Polylang directory: /en/...) снимается, чтобы маршрут совпадал на всех языках.
     *
     * @return RoutesEnum|null
     */
    private static function match(): ?RoutesEnum
    {
        $path = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/');

        if (function_exists('pll_languages_list')) {
            foreach ((array) pll_languages_list(['fields' => 'slug']) as $slug) {
                if ($path === $slug || str_starts_with($path, $slug . '/')) {
                    $path = trim(substr($path, strlen((string) $slug)), '/');
                    break;
                }
            }
        }

        foreach (RoutesEnum::cases() as $route) {
            if ($path === $route->value) return $route;
        }

        return null;
    }

    /**
     * Мультиязычный URL маршрута: домашний URL (нужного/текущего языка) + slug.
     * Единственный источник пути - RoutesEnum, без хардкода по проекту.
     *
     * @param RoutesEnum  $route
     * @param string|null $lang  язык (slug); null/'' - текущий
     * @return string
     */
    public static function url(RoutesEnum $route, ?string $lang = null): string
    {
        $home = function_exists('pll_home_url') ? (string) pll_home_url((string) $lang) : home_url('/');
        $url  = trailingslashit($home) . $route->value;

        // Файловые маршруты (с расширением, напр. *.xml) - без завершающего слэша.
        return str_contains($route->value, '.') ? $url : trailingslashit($url);
    }
}
