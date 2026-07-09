<?php declare(strict_types=1);

namespace UnitshotTheme\Classes;

use WP_REST_Request;
use WP_REST_Response;

if (!defined("ABSPATH")) die;

/**
 * Обратный звонок.
 *
 * Принимает контакты из модалки звонка и шлёт уведомление (email + Telegram)
 * тем же Notifier, что и заказ. Нигде не сохраняется - уведомление и есть заявка.
 *
 * @package UnitshotTheme
 */
class Callback
{
    /** CSRF-защита: тот же wp_rest nonce, что у корзины/заказа. */
    public static function rest_permission(WP_REST_Request $request): bool
    {
        return (bool) wp_verify_nonce((string) $request->get_header('X-WP-Nonce'), 'wp_rest');
    }

    /**
     * POST /callback/create -> отправляет заявку на звонок.
     * Тело: { phone, username, comment }.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function rest_create(WP_REST_Request $request): WP_REST_Response
    {
        $data = [
            'phone'    => sanitize_text_field((string) $request->get_param('phone')),
            'username' => sanitize_text_field((string) $request->get_param('username')),
            'comment'  => sanitize_textarea_field((string) $request->get_param('comment')),
        ];

        if ($data['phone'] === '' || $data['username'] === '') {
            return new WP_REST_Response(['ok' => false, 'error' => 'required'], 200);
        }

        $message = (new Notification('Запит на зворотній дзвінок'))
            ->row("Ім'я", $data['username'])
            ->row('Телефон', $data['phone'])
            ->row('Коментар', $data['comment']);

        Notifier::send(self::subject(), $message);

        return new WP_REST_Response(['ok' => true], 200);
    }

    private static function subject(): string
    {
        return get_bloginfo('name') . ' — ' . 'зворотній дзвінок';
    }
}
