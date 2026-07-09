<?php declare(strict_types=1);

namespace UnitshotTheme\Classes;

if (!defined("ABSPATH")) die;

/**
 * Отправка уведомлений в каналы (email + Telegram).
 *
 * Доменно-нейтральный сервис: знает КАК доставить сообщение, но не ЧТО в нём.
 * Контент собирают вызывающие (заказ - Order::...).
 * Конфиг (токен бота, chat id, email получателя) - в настройках
 * «API Ключи» (Fields::get_settings_api_keys). Канал без конфига молча
 * пропускается, чтобы одна несконфигурированная доставка не роняла остальные.
 *
 * @package UnitshotTheme
 */
class Notifier
{
    /**
     * Отправить во все каналы. Возвращает true, если хотя бы один канал доставил.
     *
     * @param string $subject       тема письма
     * @param string $email_html    HTML тела письма
     * @param string $telegram_text текст для Telegram (parse_mode HTML)
     * @return bool
     */
    public static function notify(string $subject, string $email_html, string $telegram_text): bool
    {
        $email    = self::email($subject, $email_html);
        $telegram = self::telegram($telegram_text);

        return $email || $telegram;
    }

    /** Высокоуровневая отправка готового сообщения (заказ, звонок и т.п.). */
    public static function send(string $subject, Notification $message): bool
    {
        return self::notify($subject, $message->email_html(), $message->telegram_text());
    }

    /** Письмо на email получателя заказов (или email администратора сайта). */
    public static function email(string $subject, string $html): bool
    {
        $to = Fields::get_settings_api_keys()['order_email'];
        if ($to === '') return false;

        return wp_mail($to, $subject, $html, ['Content-Type: text/html; charset=UTF-8']);
    }

    /** Сообщение в Telegram через Bot API (sendMessage). */
    public static function telegram(string $text): bool
    {
        $config = Fields::get_settings_api_keys();
        $token  = (string) $config['telegram_token'];
        $chat   = (string) $config['telegram_chat_id'];
        if ($token === '' || $chat === '') return false;

        $response = wp_remote_post("https://api.telegram.org/bot{$token}/sendMessage", [
            'timeout' => 10,
            'body'    => [
                'chat_id'                  => $chat,
                'text'                     => $text,
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => true,
            ],
        ]);

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }
}
