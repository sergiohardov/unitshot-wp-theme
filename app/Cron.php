<?php declare(strict_types=1);

namespace UnitshotTheme;

use UnitshotTheme\Classes\Currency;
use UnitshotTheme\Controllers\Pages\FeedController;

/**
 * Управление задачами WP-Cron.
 *
 * Класс отвечает за регистрацию и планирование фоновых задач
 * через встроенный механизм WordPress Cron.
 *
 * @package UnitshotTheme
 */
class Cron
{
    function __construct()
    {
        $this->register('cron_update_currencies', 'twicedaily', [Currency::class, 'cron_update_currencies']);
        $this->register('cron_refresh_feeds', 'twicedaily', [FeedController::class, 'refresh']);
    }

    /**
     * Регистрация WP Cron задачи.
     *
     * @param string $hook Название WP Cron задачи.
     * @param string $recurrence Интервал выполнения задачи (ex., 'hourly').
     * @param callable|string $callback Функция для выполнения.
     */
    private static function register(string $hook, string $recurrence, callable|string $callback): void
    {
        add_action($hook, $callback);

        if (wp_next_scheduled($hook) === false) {
            if (!wp_schedule_event(time(), $recurrence, $hook)) {
                throw new \RuntimeException("Failed to schedule cron job: {$hook}");
            }
        }
    }
}
