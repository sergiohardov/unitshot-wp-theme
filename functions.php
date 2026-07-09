<?php declare(strict_types=1);

final class UnitshotThemeMain
{
    private array $errors = [];

    function __construct()
    {
        $this->defines();
        $this->requires();
        $this->setup();
    }

    private function defines(): void
    {
        define('UNITSHOT_PATH', get_template_directory());
        define('UNITSHOT_LANG_PATH', UNITSHOT_PATH . '/languages');
        define('UNITSHOT_TWIG_PATH', UNITSHOT_PATH . '/assets/twig');
        define('UNITSHOT_IMAGES_PATH', UNITSHOT_PATH . '/assets/images');
        define('UNITSHOT_ACF_JSON_PATH', UNITSHOT_PATH . '/json-acf');
        define('UNITSHOT_APP_DIST_PATH', UNITSHOT_PATH . '/app/assets');

        define('UNITSHOT_URI', get_template_directory_uri());
        define('UNITSHOT_CSS_URI', UNITSHOT_URI . '/assets/css');
        define('UNITSHOT_JS_URI', UNITSHOT_URI . '/assets/js');
        define('UNITSHOT_IMAGES_URI', UNITSHOT_URI . '/assets/images');
        define('UNITSHOT_APP_SCRIPTS_URI', UNITSHOT_URI . '/app/scripts');
        define('UNITSHOT_APP_DIST_URI', UNITSHOT_URI . '/app/assets');
    }

    private function requires(): void
    {
        if (!file_exists(UNITSHOT_PATH . '/vendor/autoload.php')) $this->create_error('Composer Autoload not installed.');
        if (!is_plugin_active('advanced-custom-fields-pro/acf.php')) $this->create_error('ACF Pro plugin not installed.');
        if (!is_plugin_active('polylang-pro/polylang.php')) $this->create_error('Polylang Pro plugin not installed.');
        if (!is_plugin_active('acf-options-for-polylang/bea-acf-options-for-polylang.php')) $this->create_error('AOFP plugin not installed.');
    }

    private function setup(): void
    {
        if (in_array(true, $this->errors)) return;

        require_once UNITSHOT_PATH . '/vendor/autoload.php';

        new UnitshotTheme\Twig();
        new UnitshotTheme\Bea();
        new UnitshotTheme\Polylang();
        new UnitshotTheme\Acf();
        new UnitshotTheme\Taxonomies();
        new UnitshotTheme\Types();
        new UnitshotTheme\Admin();
        new UnitshotTheme\Cron();
        new UnitshotTheme\Rest();
        new UnitshotTheme\Routing();
        new UnitshotTheme\Assets();
        new UnitshotTheme\Theme();
        new UnitshotTheme\Seo();

        // Что бы заработала функция автообновления, необходимо подкинуть в класс GIT_REPO/GIT_TOKEN
        // UnitshotTheme\Classes\Utils::autoupdate();
    }

    private function create_error(string $message): void
    {
        add_action('admin_head', fn() => wp_admin_notice("Unitshot Theme: $message", ['type' => 'error']));
        $this->errors[] = true;
    }
}

new UnitshotThemeMain();
