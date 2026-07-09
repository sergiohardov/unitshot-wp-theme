<?php declare(strict_types=1);

namespace UnitshotTheme\Classes;

use CurrencyApi\CurrencyApi\CurrencyApiClient;
use UnitshotTheme\Acf;
use UnitshotTheme\DTO\CurrencyDTO;
use UnitshotTheme\Enums\PageSlugsEnum;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

class Currency
{
    /**
     * @see Acf
     * @param $post_id
     * @param $menu_slug
     * @return void
     */
    public static function acf_manual_update_currencies($post_id, $menu_slug): void
    {
        if ($menu_slug !== PageSlugsEnum::Currency->value) return;

        $settings = Fields::get_settings_currency();
        if (!$settings['is_update']) return;

        self::update_currencies_with_api();
    }

    /**
     * @see Cron
     * @return void
     */
    public static function cron_update_currencies(): void
    {
        self::update_currencies_with_api();
    }

    /**
     * @see Rest
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function rest_switch_currency(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $new_currency = sanitize_text_field($request->get_param('currency'));

        Cookies::set_currency($new_currency);

        wp_safe_redirect(wp_get_referer() ?: pll_home_url());
        exit;
    }

    /**
     * Получить данные валюты которая отмечена "По умолчанию"
     *
     * @return CurrencyDTO
     */
    public static function get_default_currency(): CurrencyDTO
    {
        $settings   = Fields::get_settings_currency();
        $currencies = $settings['currencies'];

        foreach ($currencies as $currency) {
            $currency_dto = CurrencyDTO::from_field($currency);
            if ($currency_dto->is_default) return $currency_dto;
        }

        return CurrencyDTO::get_default();
    }

    /**
     * Получить данные валюты которая выбрана на фронте
     *
     * @return CurrencyDTO
     */
    public static function get_current_currency(): CurrencyDTO
    {
        $settings       = Fields::get_settings_currency();
        $currencies     = $settings['currencies'];
        $codes          = array_column($currencies, 'code');
        $index          = array_search(Cookies::get_currency(), $codes);
        $field          = $currencies[$index];

        return is_array($field) ? CurrencyDTO::from_field($field) : CurrencyDTO::get_default();
    }

    public static function get_codes(): array
    {
        $settings   = Fields::get_settings_currency();
        $currencies = $settings['currencies'];
        return array_column($currencies, 'code');
    }

    public static function update_currencies_with_api(): void
    {
        $settings               = Fields::get_settings_currency();
        $apitokens              = Fields::get_settings_api_keys();
        $settings['is_update']  = false;
        if (empty($settings['currencies'])) return;

        $client         = new CurrencyApiClient($apitokens['currencyapi']);
        $status         = $client->status();
        $is_available   = ($status['quotas']['month']['remaining'] ?? 0) > 0;
        // TODO: обработать ошибку или недоступность оптравки, показать сообщение, преещелкнуть свитчер в off
        if (!$is_available) return;

        $values = $client->latest(['base_currency' => self::get_default_currency()->code, 'currencies' => self::get_codes()]);
        $values = $values['data'];
        if (empty($values)) return;

        $settings['currencies'] = array_map(function ($currency) use ($values) {
            $value = $values[$currency['code']]['value'] ?? $currency['value'];

            $currency['value'] = $value;

            return $currency;
        }, $settings['currencies']);

        Fields::update_settings_currency($settings);
    }
}
