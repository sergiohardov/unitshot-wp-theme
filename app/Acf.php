<?php declare(strict_types=1);

namespace UnitshotTheme;

use UnitshotTheme\Classes\Currency;
use UnitshotTheme\Classes\Fields;
use UnitshotTheme\Controllers\Blocks\CategoryShowcaseController;
use UnitshotTheme\Controllers\Pages\FeedController;
use UnitshotTheme\Enums\IconsEnum;

if (!defined("ABSPATH")) die;

/**
 * Интеграция темы с плагином Advanced Custom Fields (ACF).
 *
 * Класс выступает единым слоем интеграции между ACF, бизнес-логикой проекта,
 * настройками магазина и пользовательскими блоками.
 *
 * @package UnitshotTheme
 */
class Acf
{
    function __construct()
    {
        add_filter('acf/settings/load_json',                                [$this, 'load_fields']);
        add_filter('acf/prepare_field/key=field_6a4beb8cad8c1',             [$this, 'load_icons_choices']);
        add_filter('acf/fields/google_map/api',                             [$this, 'register_google_maps_api']);
        add_action('acf/options_page/save',                                 [Currency::class, 'acf_manual_update_currencies'], 10, 2);
        add_action('acf/options_page/save',                                 [FeedController::class, 'acf_manual_refresh'], 10, 2);
        add_filter('acf/fields/post_object/query/key=field_6a0b246919050',  [CategoryShowcaseController::class, 'acf_filter_products_by_category'], 99, 3);
        add_action('acf/input/admin_enqueue_scripts',                       [CategoryShowcaseController::class, 'acf_admin_enqueue_script']);

        $this->save_fields();
    }

    /**
     * Точечное сохранение полей
     *
     * Реализует механизм точечного сохранения конфигураций ACF,
     * предотвращая конфликты с другими плагинами и темами, которые могут
     * переопределять глобальные пути сохранения JSON-файлов.
     *
     * @return void
     */
    private function save_fields(): void {
        $field_keys = [
            /* Options Pages */
            'ui_options_page_69fc71d298320', // OptionsPageUnitshot
            'ui_options_page_6a13e9a52dcf7', // OptionsPageApiKeys
            'ui_options_page_69fda63814480', // OptionsPageCurrency
            'ui_options_page_69fcc5c109a12', // OptionsPageGeneral
            'ui_options_page_6a40e0d5e0010', // OptionsPageSeo
            'ui_options_page_6a4bffd6c25aa', // OptionsPagePages

            /* Fields: Options */
            'group_6a13eae8667d6', // OptionsApiKeys
            'group_69fda6d578de9', // OptionsCurrency
            'group_69fc7222a40b6', // OptionsGeneral
            'group_6a40e0d5e0011', // OptionsSeo
            'group_6a4c003a35d84', // OptionsPages

            /* Fields: Blocks */
            'group_6a07b8aa33288', // BlockBrandLogos
            'group_6a0ab081c0be7', // BlockPopularProducts
            'group_6a0b20c114e23', // BlockCategoryShowcase
            'group_6a0b384f93076', // BlockCategoryLinks
            'group_6a0b423a95eaf', // BlockTextImage
            'group_6a0b463834302', // BlockClientLogos
            'group_6a0b49ad8e740', // BlockSliderReviews
            'group_6a0b4d56e89b6', // BlockFeedbackInfo
            'group_6a0b532e7c48b', // BlockSliderPosts

            /* Fields: Taxonomies */
            'group_6a0ab8da54754', // TaxonomyProductBrand
            'group_6a0b2c9838ca9', // TaxonomyProductCategory

            /* Fields: Post Types */
            'group_6a0f3641ea906', // PostTypePost

            /* Fields: Pages */
            'group_6a03998c00377', // PageFront
            'group_6a0f556934621', // PageContact

            /* Fields: SEO (общая группа на страницы, посты, товары, категории, бренды) */
            'group_6a40e0d5e0001', // SeoMeta
        ];

        foreach ($field_keys as $fields_key) {
            add_filter("acf/settings/save_json/key=$fields_key", fn($path) => UNITSHOT_ACF_JSON_PATH);
        }
    }

    /**
     * Добавляет путь для чтения local json полей
     *
     * @param array $paths
     * @return array
     */
    public function load_fields(array $paths): array
    {
        $paths[] = UNITSHOT_ACF_JSON_PATH;
        return $paths;
    }

    public function load_icons_choices(array $field): array
    {
        $field['instructions']  = 'Список иконок подгружается из класса "app/Acf.php".' . "\n" . $field['instructions'];
        $field['choices']       = IconsEnum::group();
        return $field;
    }

    public function register_google_maps_api(array $api): array
    {
        $apikeys    = Fields::get_settings_api_keys();
        $api['key'] = $apikeys['googlemaps'];
        return $api;
    }
}
