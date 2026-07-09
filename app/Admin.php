<?php declare(strict_types=1);

namespace UnitshotTheme;

use UnitshotTheme\Classes\Fields;
use UnitshotTheme\Classes\ProductAttributes;
use UnitshotTheme\Classes\ProductQuery;
use UnitshotTheme\Classes\Products;
use UnitshotTheme\Enums\PageSlugsEnum;
use UnitshotTheme\Enums\PermalinkFieldsEnum;
use UnitshotTheme\Enums\TaxonomiesEnum;
use UnitshotTheme\Enums\TypesEnum;
use WP_Post;

if (!defined("ABSPATH")) die;

/**
 * Административный слой темы.
 *
 * Класс централизует регистрацию административных хуков WordPress
 * и связывает бизнес-логику темы с интерфейсом админ-панели.
 *
 * Класс выступает как точка сборки административных действий
 *
 * @package UnitshotTheme
 */
class Admin
{
    public function __construct()
    {
        add_action('admin_menu',                             [$this, 'register_admin_menus']);
        add_action('add_meta_boxes',                         [$this, 'register_meta_boxes']);
        add_action('admin_init',                             [$this, 'register_permalink_fields']);
        add_action('load-options-permalink.php',             [$this, 'save_permalink_fields']);
        add_action('save_post_' . TypesEnum::Product->value, [Products::class, 'admin_save_post'], 10, 2);
        add_action('pre_get_posts',                          [ProductQuery::class, 'catalog_main_query']);

        // Префикс блога для постов: применяется ТОЛЬКО к типу post (без смены
        // глобальной структуры пермалинков, чтобы не задеть товары/страницы).
        // Правила добавляем в группу post_rewrite_rules с приоритетом < 10, чтобы
        // Polylang (хук priority 10 на той же группе) добавил языковые варианты.
        add_filter('post_rewrite_rules',                     [$this, 'filter_post_rewrite_rules'], 9);
        add_filter('pre_post_link',                          [$this, 'apply_post_base'], 10, 2);
    }

    public function register_admin_menus(): void
    {
        add_submenu_page('edit.php?post_type=' . TypesEnum::Product->value, 'Атрибуты товаров', 'Атрибуты', 'manage_options', PageSlugsEnum::ProductAttributes->value, [ProductAttributes::class, 'admin_render_menu_page']);
    }

    public function register_meta_boxes(): void
    {
        add_meta_box('us-product', 'Товар', [Products::class, 'render_meta_box'], TypesEnum::Product->value, 'normal', 'high');
    }

    public function register_permalink_fields(): void
    {
        foreach (PermalinkFieldsEnum::cases() as $case) {
            add_settings_field($case->value, $case->get_label(), function () use ($case) {
                printf('<input name="%1$s" id="%1$s" type="text" value="%2$s" class="regular-text code">', $case->value, get_option($case->value, ''));
            }, 'permalink', 'optional');
        }
    }

    public function save_permalink_fields(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') return;
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'update-permalink')) return;

        foreach (PermalinkFieldsEnum::cases() as $case) {
            if (!isset($_POST[$case->value])) continue;

            $value = (string) $_POST[$case->value];
            $value = preg_replace('#/+#', '/', trim($value));
            $value = trim((string) $value, '/');

            update_option($case->value, $value);
        }
    }

    /**
     * Структура ссылки поста с префиксом блога. Применяется только к типу post
     * через pre_post_link (получает СТРУКТУРУ, напр. «/%postname%/», до подстановки).
     *
     * @param string   $permalink структура пермалинка
     * @param WP_Post $post
     * @return string
     */
    public function apply_post_base(string $permalink, WP_Post $post): string
    {
        $base = Fields::get_permalink_prefix(PermalinkFieldsEnum::Blog);
        if ($base !== '' && $post->post_type === TypesEnum::Post->value) return '/' . $base . $permalink;
        return $permalink;
    }

    /**
     * Добавляет в группу post_rewrite_rules правила для постов под префиксом блога
     * (одиночный пост + пагинация контента через <!--nextpage-->). Группа post -
     * переводимая, поэтому Polylang сам добавит языковые варианты (/en/blog/...).
     * Правила ставим первыми, чтобы они срабатывали раньше общего правила страниц.
     *
     * @param array<string, string> $rules
     * @return array<string, string>
     */
    public function filter_post_rewrite_rules(array $rules): array
    {
        $base = Fields::get_permalink_prefix(PermalinkFieldsEnum::Blog);
        if ($base === '') return $rules;

        $own = [
            '^' . $base . '/([^/]+)/?$'                    => 'index.php?post_type=post&name=$matches[1]',
            '^' . $base . '/([^/]+)/page/?([0-9]{1,})/?$'  => 'index.php?post_type=post&name=$matches[1]&page=$matches[2]',
        ];

        return $own + $rules;
    }
}
