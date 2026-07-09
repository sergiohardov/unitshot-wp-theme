<?php declare(strict_types=1);

namespace UnitshotTheme\Classes;

use WP_REST_Request;
use WP_REST_Response;

if (!defined("ABSPATH")) die;

/**
 * Оформление заказа.
 *
 * Принимает только контактные данные покупателя; СОСТАВ заказа берётся
 * сервером из корзины (Cart::view по куке) - клиенту тут не доверяем. После
 * валидации шлёт уведомление (email + Telegram) через Notifier и очищает корзину.
 *
 * @package UnitshotTheme
 */
class Order
{
    /** CSRF-защита: тот же wp_rest nonce, что у корзины (шлёт фронт в X-WP-Nonce). */
    public static function rest_permission(WP_REST_Request $request): bool
    {
        return (bool) wp_verify_nonce((string) $request->get_header('X-WP-Nonce'), 'wp_rest');
    }

    /**
     * POST /order/create -> создаёт (отправляет) заказ.
     * Тело: { name, phone, post, comment, lang }. Состав - из серверной корзины.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function rest_create(WP_REST_Request $request): WP_REST_Response
    {
        $lang = sanitize_key((string) $request->get_param('lang'));

        $customer = [
            'name'    => sanitize_text_field((string) $request->get_param('name')),
            'phone'   => sanitize_text_field((string) $request->get_param('phone')),
            'post'    => sanitize_text_field((string) $request->get_param('post')),
            'comment' => sanitize_textarea_field((string) $request->get_param('comment')),
        ];

        if ($customer['name'] === '' || $customer['phone'] === '') {
            return new WP_REST_Response(['ok' => false, 'error' => 'required'], 200);
        }

        // Состав заказа - из серверной корзины (не из запроса).
        $cart = Cart::view($lang);
        if (empty($cart['items'])) {
            return new WP_REST_Response(['ok' => false, 'error' => 'empty'], 200);
        }

        Notifier::send(self::subject(), self::build_message($customer, $cart));

        Cart::clear();

        // Возвращаем уже пустую корзину - фронт обновит стор.
        return new WP_REST_Response(['ok' => true, 'cart' => Cart::view($lang)], 200);
    }

    private static function subject(): string
    {
        return get_bloginfo('name') . ' — ' . 'нове замовлення';
    }

    /**
     * Собирает сообщение заказа: контакты + позиции + сумма.
     *
     * @param array<string,string> $customer
     * @param array<string,mixed>  $cart
     * @return Notification
     */
    private static function build_message(array $customer, array $cart): Notification
    {
        $message = (new Notification('Нове замовлення'))
            ->row("Ім'я", $customer['name'])
            ->row('Телефон', $customer['phone'])
            ->row('Пошта', $customer['post'])
            ->row('Коментар', $customer['comment']);

        foreach ($cart['items'] as $item) {
            $title = $item['title'] . ($item['variation_label'] ? ' (' . $item['variation_label'] . ')' : '');
            $message->item(sprintf('%s — %d × = %s', $title, (int) $item['qty'], (string) Helpers::format_price($item['line'])));
        }

        return $message->total('Разом', (string) Helpers::format_price($cart['total']));
    }
}
