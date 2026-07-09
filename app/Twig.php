<?php declare(strict_types=1);

namespace UnitshotTheme;

use Timber\Timber;
use Twig\Environment;
use Twig\TwigFunction;
use UnitshotTheme\Classes\Cookies;
use UnitshotTheme\Classes\Helpers;
use UnitshotTheme\Controllers\Parts\FooterController;
use UnitshotTheme\Controllers\Parts\HeaderController;
use UnitshotTheme\Enums\RoutesEnum;
use UnitshotTheme\Polylang;
use UnitshotTheme\Routing;

/**
 * Класс для настройки и расширения интеграции Twig через Timber.
 *
 * Поддерживает подключение шаблонов через сокращённые пути:
 * layouts, components, blocks, parts и pages.
 *
 * @package UnitshotTheme
 */
class Twig
{
    function __construct()
    {
        Timber::init();

        add_filter('timber/twig',       [$this, 'add_functions']);
        add_filter('timber/locations',  [$this, 'add_alias']);
    }

    public function add_functions($twig): Environment
    {
        $twig->addFunction(new TwigFunction('home_url', fn() => pll_home_url()));
        $twig->addFunction(new TwigFunction('home_name', fn() => bloginfo('name')));
        $twig->addFunction(new TwigFunction('svg_icon', fn(string $name) => file_get_contents(UNITSHOT_IMAGES_PATH . '/icons/' . $name . '.svg') ?: "<!-- svg not found: {$name} -->"));
        $twig->addFunction(new TwigFunction('format_price', fn(float $price) => Helpers::format_price($price)));
        $twig->addFunction(new TwigFunction('header', fn() => (new HeaderController())->render()));
        $twig->addFunction(new TwigFunction('footer', fn() => (new FooterController())->render()));
        $twig->addFunction(new TwigFunction('pll__', fn(string $string) => pll__($string)));
        $twig->addFunction(new TwigFunction('get_images_uri', fn(string $string) => UNITSHOT_IMAGES_URI . $string));
        // Мультиязычный URL кастомного маршрута (единый реестр - RoutesEnum/Routing).
        $twig->addFunction(new TwigFunction('route_url', fn(string $route) => ($case = RoutesEnum::tryFrom($route)) ? Routing::url($case) : ''));
        $twig->addFunction(new TwigFunction('get_current_lang', fn() => function_exists('pll_current_language') ? pll_current_language('slug') : ''));
        // Предложение украинской версии RU-посетителю при первом визите (модалка lang).
        $twig->addFunction(new TwigFunction('lang_suggest', fn() => [
            'show' => function_exists('pll_current_language') && pll_current_language('slug') === 'ru' && !Cookies::is_lang_suggest_seen(),
            'url'  => Polylang::translation_url('ua') ?: (function_exists('pll_home_url') ? pll_home_url('ua') : home_url('/')),
        ]));

        return $twig;
    }

    public function add_alias($paths): array
    {
        $paths['layouts']       = [UNITSHOT_TWIG_PATH . '/layouts'];
        $paths['components']    = [UNITSHOT_TWIG_PATH . '/components'];
        $paths['blocks']        = [UNITSHOT_TWIG_PATH . '/blocks'];
        $paths['parts']         = [UNITSHOT_TWIG_PATH . '/parts'];
        $paths['pages']         = [UNITSHOT_TWIG_PATH . '/pages'];

        return $paths;
    }
}
