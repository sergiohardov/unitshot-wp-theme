<?php declare(strict_types=1);

namespace UnitshotTheme;

use UnitshotTheme\Classes\Cart;
use UnitshotTheme\Classes\Currency;
use UnitshotTheme\Classes\ProductAttributes;
use UnitshotTheme\Classes\Products;

/**
 * Управление подключением ассетов (CSS и JavaScript).
 *
 * Класс отвечает за регистрацию и подключение фронтенд и админ ресурсов
 * в WordPress, а также делегирует подключение специализированных скриптов
 * отдельным компонентам темы.
 *
 * Класс обеспечивает централизованное управление загрузкой ресурсов
 * и предотвращает дублирование подключения ассетов в разных частях темы.
 *
 * @package UnitshotTheme
 */
class Assets
{
    function __construct()
    {
        add_action('wp_enqueue_scripts',    [$this, 'styles']);
        add_action('wp_enqueue_scripts',    [$this, 'scripts']);
        add_action('wp_enqueue_scripts',    [$this, 'localize']);
        add_action('admin_enqueue_scripts', [Products::class, 'assets_enqueue_script']);
        add_action('admin_enqueue_scripts', [ProductAttributes::class, 'assets_enqueue']);
    }

    public function styles(): void
    {
        wp_enqueue_style('unitshot-main', UNITSHOT_CSS_URI . '/main.css');
    }

    public function scripts(): void
    {
        wp_enqueue_script('unitshot-main', UNITSHOT_JS_URI . '/main.js', [], false, true);
    }

    public function localize(): void
    {
        $currency = Currency::get_current_currency();

        wp_localize_script('unitshot-main', 'UNITSHOT', [
            // Текущий язык - шлём в REST (Polylang там его не определяет сам).
            'lang' => function_exists('pll_current_language') ? (string) pll_current_language('slug') : '',
            // Текущая валюта -> JS: клиентский formatPrice конвертирует цены так же,
            // как серверный Helpers::format_price (множитель value + код). Свитчер
            // делает жёсткий редирект, поэтому значение актуально на каждый рендер.
            'currency'=> [
                'value'  => $currency->value,
                'code'   => $currency->code,
                'symbol' => $currency->symbol,
            ],
            // Живой поиск товаров в шапке: публичный REST-эндпоинт, страница полной выдачи (?s=).
            'search' => [
                'endpoint' => rest_url(Rest::REST_NAMESPACE . Rest::REST_ROUTE_PRODUCT_SEARCH),
                'page'     => home_url('/'),
                'min'      => 3,
            ],
            // Корзина: REST-эндпоинты мутаций (+nonce) и начальное состояние (текущий язык).
            'cart' => [
                'add'    => rest_url(Rest::REST_NAMESPACE . Rest::REST_ROUTE_CART_ADD),
                'update' => rest_url(Rest::REST_NAMESPACE . Rest::REST_ROUTE_CART_UPDATE),
                'remove' => rest_url(Rest::REST_NAMESPACE . Rest::REST_ROUTE_CART_REMOVE),
                'nonce'  => wp_create_nonce('wp_rest'),
                'state'  => Cart::view(),
            ],
            // Оформление заказа (nonce общий с корзиной - тот же wp_rest).
            'order' => [
                'create' => rest_url(Rest::REST_NAMESPACE . Rest::REST_ROUTE_ORDER_CREATE),
            ],
            // Обратный звонок (модалка звонка).
            'callback' => [
                'create' => rest_url(Rest::REST_NAMESPACE . Rest::REST_ROUTE_CALLBACK_CREATE),
            ],
        ]);
    }
}
