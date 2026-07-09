<?php declare(strict_types=1);

namespace UnitshotTheme;

use UnitshotTheme\Classes\Callback;
use UnitshotTheme\Classes\Cart;
use UnitshotTheme\Classes\Currency;
use UnitshotTheme\Classes\Order;
use UnitshotTheme\Classes\ProductAttributes;
use UnitshotTheme\Classes\Products;

/**
 * REST API слой.
 *
 * Класс отвечает за регистрацию кастомных REST API эндпоинтов,
 * используемых как для публичных функций темы, так и для
 * административной логики управления продуктами.
 *
 * Класс выступает единым шлюзом между фронтендом и серверной логикой
 * управления данными.
 *
 * @package UnitshotTheme
 */
class Rest {
    const REST_NAMESPACE            = 'unitshot/v1';
    const REST_ROUTE_CURRENCY       = '/set-currency';
    const REST_ROUTE_PRODUCT_SEARCH = '/product/front-search';
    const REST_ROUTE_CART_ADD       = '/cart/add';
    const REST_ROUTE_CART_UPDATE    = '/cart/update';
    const REST_ROUTE_CART_REMOVE    = '/cart/remove';
    const REST_ROUTE_ORDER_CREATE   = '/order/create';
    const REST_ROUTE_CALLBACK_CREATE = '/callback/create';

    function __construct() {
        $this->register(self::REST_ROUTE_CURRENCY,          'GET',  [Currency::class, 'rest_switch_currency'], '__return_true');
        $this->register('/product-attributes/save',         'POST', [ProductAttributes::class, 'rest_save_attributes'], [ProductAttributes::class, 'rest_can_manage']);
        $this->register(self::REST_ROUTE_PRODUCT_SEARCH,    'GET',  [Products::class, 'rest_front_search'],              '__return_true');
        $this->register('/product/attribute-terms',         'GET',  [Products::class, 'rest_attribute_terms'],          [Products::class, 'rest_can_edit_posts']);
        $this->register('/product/search',                  'GET',  [Products::class, 'rest_search_products'],          [Products::class, 'rest_can_edit_posts']);
        $this->register('/product/variations',              'GET',  [Products::class, 'rest_product_variations'],       [Products::class, 'rest_can_edit_posts']);
        $this->register(self::REST_ROUTE_CART_ADD,          'POST', [Cart::class, 'rest_add'],                          [Cart::class, 'rest_permission']);
        $this->register(self::REST_ROUTE_CART_UPDATE,       'POST', [Cart::class, 'rest_update'],                       [Cart::class, 'rest_permission']);
        $this->register(self::REST_ROUTE_CART_REMOVE,       'POST', [Cart::class, 'rest_remove'],                       [Cart::class, 'rest_permission']);
        $this->register(self::REST_ROUTE_ORDER_CREATE,      'POST', [Order::class, 'rest_create'],                      [Order::class, 'rest_permission']);
        $this->register(self::REST_ROUTE_CALLBACK_CREATE,   'POST', [Callback::class, 'rest_create'],                   [Callback::class, 'rest_permission']);
    }

    private function register(string $uri, string $method, callable $callback, string|callable $permission_callback, array $args = []): void {
        add_action('rest_api_init', fn() => register_rest_route(self::REST_NAMESPACE, $uri, [
            'methods'               => $method,
            'callback'              => $callback,
            'permission_callback'   => $permission_callback,
            ...$args
        ]));
    }
}
