<?php declare(strict_types=1);

namespace UnitshotTheme\Classes;

use UnitshotTheme\Enums\CustomOptionPageIdsEnum;
use UnitshotTheme\Enums\PermalinkFieldsEnum;

class Fields
{
    public static function get_options_field(string $acf_key): mixed
    {
        return get_field($acf_key, 'options');
    }

    public static function update_options_field(string $acf_key, mixed $value): bool
    {
        return update_field($acf_key, $value, 'options');
    }

    public static function get_settings_general(): array
    {
        $fields = self::get_options_field('unitshot_options_general') ?: [];

        return [
            'logo' => is_numeric($fields['logo'] ?? null) ? $fields['logo'] : null,
            'feed_update' => !empty($fields['feed_update'] ?? null),
            'phone_numbers' => is_array($fields['phone_numbers'] ?? null) ? $fields['phone_numbers'] : [],
            'social_medias' => is_array($fields['social_medias'] ?? null) ? $fields['social_medias'] : [],
            'email' => !empty($fields['email'] ?? null) ? $fields['email'] : '',
            'address' => !empty($fields['address'] ?? null) ? $fields['address'] : '',
        ];
    }

    public static function get_settings_currency(): array
    {
        $fields = get_field('unitshot_options_currency', CustomOptionPageIdsEnum::Currency->value) ?: [];

        return [
            'is_update'     => is_bool($fields['is_update'] ?? null)    ? $fields['is_update']  : false,
            'currencies'    => is_array($fields['currencies'] ?? null)  ? $fields['currencies'] : [],
        ];
    }

    public static function get_settings_api_keys(): array
    {
        $fields = get_field('unitshot_options_api_keys', CustomOptionPageIdsEnum::APIKeys->value) ?: [];

        return [
            'currencyapi'      => !empty($fields['currencyapi'] ?? null)      ? $fields['currencyapi']      : '',
            'googlemaps'       => !empty($fields['googlemaps'] ?? null)       ? $fields['googlemaps']       : '',
            'telegram_token'   => !empty($fields['telegram_token'] ?? null)   ? $fields['telegram_token']   : '',
            'telegram_chat_id' => !empty($fields['telegram_chat_id'] ?? null) ? $fields['telegram_chat_id'] : '',
            'order_email'      => !empty($fields['order_email'] ?? null)      ? $fields['order_email']      : '',
        ];
    }

    public static function get_settings_pages(): array
    {
        $fields = self::get_options_field('unitshot_options_pages') ?: [];

        return [
            'catalog_feedback_info' => is_array($fields['catalog_feedback_info_unitshot_blocks_feedback_info'] ?? null) ? $fields['catalog_feedback_info_unitshot_blocks_feedback_info'] : [],
            'product_feedback_info' => is_array($fields['product_feedback_info_unitshot_blocks_feedback_info'] ?? null) ? $fields['product_feedback_info_unitshot_blocks_feedback_info'] : [],
        ];
    }

    public static function get_brand(int $id): array
    {
        $fields = get_field('unitshot_taxonomy_product_brand', "term_$id");

        return [
            'logo'       => is_numeric($fields['logo'] ?? null) ? $fields['logo'] : null,
            'text_image' => is_array($fields['unitshot_blocks_text_image'] ?? null) ? $fields['unitshot_blocks_text_image'] : [],
        ];
    }

    public static function get_category(int $id): array
    {
        $fields = get_field('unitshot_taxonomy_product_category', "term_$id");

        return [
            'logo'       => is_numeric($fields['logo'] ?? null) ? $fields['logo'] : null,
            'text_image' => is_array($fields['unitshot_blocks_text_image'] ?? null) ? $fields['unitshot_blocks_text_image'] : [],
        ];
    }

    public static function get_post(int $post_id): array {
        $fields = get_field('unitshot_post_type_post', $post_id);

        return [
            'more_posts' => is_array($fields['more_posts'] ?? null) ? $fields['more_posts'] : [],
        ];
    }

    /**
     * Глобальные SEO-шаблоны по типам контента (переводимая options-страница).
     *
     * Значение каждого типа — пара шаблонов title/description с тегами
     * ({page_title}, {site_name}, {site_description}, {separator}).
     */
    public static function get_settings_seo(): array
    {
        $fields = self::get_options_field('unitshot_options_seo') ?: [];

        $template = fn(string $key): string => !empty($fields[$key] ?? null) ? (string) $fields[$key] : '';

        return [
            'site_name' => $template('site_name'),
            'separator' => !empty($fields['separator'] ?? null) ? (string) $fields['separator'] : '–',
            'page'      => ['title' => $template('page_title'),     'description' => $template('page_description')],
            'post'      => ['title' => $template('post_title'),     'description' => $template('post_description')],
            'product'   => ['title' => $template('product_title'),  'description' => $template('product_description')],
            'category'  => ['title' => $template('category_title'), 'description' => $template('category_description')],
            'brand'     => ['title' => $template('brand_title'),    'description' => $template('brand_description')],
        ];
    }

    /**
     * SEO-поля (meta title/description) для любого объекта.
     *
     * @param int|string $object_id ACF-идентификатор: ID поста/страницы/товара
     *                              или строка "term_{id}" для термина таксономии.
     */
    public static function get_seo(int|string $object_id): array
    {
        $fields = get_field('unitshot_seo', $object_id) ?: [];

        return [
            'meta_title'       => !empty($fields['meta_title'] ?? null)       ? (string) $fields['meta_title']       : '',
            'meta_description' => !empty($fields['meta_description'] ?? null) ? (string) $fields['meta_description'] : '',
        ];
    }

    public static function get_front_page(): array {
        $fields = get_field('unitshot_pages_front') ?: [];

        return [
            'blocks' => is_array($fields['blocks'] ?? null) ? $fields['blocks'] : [],
        ];
    }

    public static function get_contact_page(): array
    {
        $fields = get_field('unitshot_pages_contact') ?: [];

        return [
            'description' => !empty($fields['description'] ?? null) ? $fields['description'] : '',
            'contacts' => is_array($fields['contacts'] ?? null) ? $fields['contacts'] : [],
            'map' => is_array($fields['map'] ?? null) ? $fields['map'] : [],
        ];
    }

    public static function get_products_attributes(): array
    {
        // TODO[optimization]: мемоизировать в статическое свойство — метод дёргается
        // многократно за запрос (register_taxonomies, get_taxonomy_names/choices,
        // sync, ajax). get_option обычно берётся из object-cache, так что выгода
        // микроскопическая; если делать - не забыть сбрасывать кэш после
        // update_option() в handle_create()/handle_delete().
        // TODO: возможно сделать прослойку из DTO
        return (array) get_option('product_custom_attributes', []);
    }

    public static function get_permalink_prefix(PermalinkFieldsEnum $enum): string
    {
        return (string) get_option($enum->value, '');
    }

    public static function update_products_attributes(array $value): bool
    {
        return update_option('product_custom_attributes', $value);
    }

    public static function update_settings_currency(array $fields): bool
    {
        return update_field('unitshot_options_currency', $fields, CustomOptionPageIdsEnum::Currency->value);
    }
}
