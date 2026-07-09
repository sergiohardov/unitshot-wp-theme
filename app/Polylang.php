<?php declare(strict_types=1);

namespace UnitshotTheme;

use UnitshotTheme\Classes\ProductAttributes;
use UnitshotTheme\Classes\Products;

/**
 * Интеграция темы с плагином Polylang.
 *
 * Отвечает за мультиязычность проекта и централизует всю логику
 *
 * @package UnitshotTheme
 */
class Polylang
{
    function __construct()
    {
        add_action('init',                  [$this, 'add_strings']);
        add_filter('pll_copy_taxonomies',   [$this, 'copy_non_translatable_taxonomies'], 10, 2);
        add_action('init',                  [ProductAttributes::class, 'polylang_register_attribute_strings']);
        add_action('pll_get_taxonomies',    [ProductAttributes::class, 'polylang_register_product_attributes'],  10, 2);
        add_filter('pll_copy_post_metas',   [Products::class, 'polylang_copy_metas'], 10, 2);
    }

    /**
     * Парсинг json файла содержащий строки и их регистрация в polylang
     * Парсится с помощью команды "composer collect-pll"
     * TODO: Обработать ошибку если файл не найден или битый json
     *
     * @return void
     */
    public function add_strings(): void
    {
        try {
            $strings = json_decode(file_get_contents(UNITSHOT_LANG_PATH . '/pll-strings.json'), true);

            foreach ($strings as $key => $string) {
                pll_register_string('unitshot_string_' . $key, $string, 'Unitshot Theme', false);
            }
        } catch (\Exception $e) {
        }
    }

    /**
     * Тема-wide политика: непереводимые таксономии копируются в перевод.
     *
     * Polylang при создании перевода переносит связи только тех таксономий,
     * которые он переводит (их термы мапятся на перевод). Непереводимые
     * (language-neutral) таксономии он в перевод не переносит - отсюда пустые
     * галочки на новой языковой версии. Их термы общие для всех языков, поэтому
     * достаточно добавить такие таксономии в список копируемых.
     *
     * Правило универсальное: перебираем таксономии переводимых типов записей и
     * добавляем те, что Polylang НЕ переводит. Так оно само покрывает атрибуты
     * товара, бренд и любые будущие непереводимые таксономии - без ручных списков.
     *
     * @param array $taxonomies
     * @param bool $sync
     * @return array
     */
    public function copy_non_translatable_taxonomies(array $taxonomies, bool $sync): array
    {
        if (!function_exists('pll_is_translated_taxonomy') || !function_exists('pll_is_translated_post_type')) return $taxonomies;

        // Служебные таксономии Polylang: их НЕЛЬЗЯ копировать в перевод. Иначе в
        // перевод переносится язык/связь оригинала все записи сваливаются под
        // один язык, в остальных языках список пуст. pll_is_translated_taxonomy()
        // для них false, поэтому отсекаем явным списком.
        $pll_internal = ['language', 'term_language', 'post_translations', 'term_translations'];

        foreach (get_taxonomies([], 'objects') as $taxonomy) {
            if (in_array($taxonomy->name, $pll_internal, true)) continue;

            // Интересуют только таксономии переводимых типов записей.
            $on_translated_type = false;
            foreach ((array)$taxonomy->object_type as $post_type) {
                if (pll_is_translated_post_type($post_type)) {
                    $on_translated_type = true;
                    break;
                }
            }
            if (!$on_translated_type) continue;

            // Переводимые Polylang мапит сам - копируем только непереводимые.
            if (pll_is_translated_taxonomy($taxonomy->name)) continue;

            $taxonomies[] = $taxonomy->name;
        }

        return array_values(array_unique($taxonomies));
    }

    /**
     * Язык редактируемого товара (slug) для фильтрации термов;
     * фолбэк - язык по умолчанию.
     *
     * @param int $post_id
     * @return string
     */
    public static function get_editing_language(int $post_id): string
    {
        if ($post_id && function_exists('pll_get_post_language')) {
            $lang = (string) pll_get_post_language($post_id, 'slug');
            if ($lang) return $lang;
        }
        if (function_exists('pll_default_language')) {
            return (string) pll_default_language('slug');
        }
        return '';
    }

    /**
     * Данные переключателя языков для шапки: [{title, link, is_current}].
     *
     * Базовый список берём у Polylang, но на архиве терма (категория/бренд) URL у
     * него ведёт на главную — подменяем на этот же терм в нужном языке
     * (term_translation_url). Так весь фикс собран в одном месте.
     *
     * @return array<int, array{title:string, link:string, is_current:bool}>
     */
    public static function language_switcher(): array
    {
        $languages = function_exists('pll_the_languages') ? pll_the_languages(['raw' => 1, 'hide_if_no_translation' => 0]) : [];
        if (!is_array($languages)) return [];

        $queried = get_queried_object();
        $term    = $queried instanceof \WP_Term ? $queried : null;

        return array_values(array_map(function ($lang) use ($term) {
            $slug = (string) ($lang['slug'] ?? '');
            $url  = (string) ($lang['url'] ?? '');

            if ($term) {
                $fixed = self::term_translation_url($term, $slug);
                if ($fixed !== null) $url = $fixed;
            }

            return [
                'title'      => strtoupper($slug),
                'link'       => $url,
                'is_current' => !empty($lang['current_lang']),
            ];
        }, $languages));
    }

    /**
     * URL текущего контента в указанном языке (для предложения сменить язык).
     * Если прямого перевода нет — Polylang отдаёт домашнюю того языка. Архивы
     * терма правятся так же, как в language_switcher (term_translation_url).
     *
     * @param string $lang язык назначения (slug)
     * @return string|null
     */
    public static function translation_url(string $lang): ?string
    {
        if ($lang === '' || !function_exists('pll_the_languages')) return null;

        $languages = pll_the_languages(['raw' => 1, 'hide_if_no_translation' => 0]);
        if (!is_array($languages) || !isset($languages[$lang])) return null;

        $url = (string) ($languages[$lang]['url'] ?? '');

        $queried = get_queried_object();
        if ($queried instanceof \WP_Term) {
            $fixed = self::term_translation_url($queried, $lang);
            if ($fixed !== null) $url = $fixed;
        }

        return $url !== '' ? $url : null;
    }

    /**
     * URL архива терма для другого языка — чтобы переключатель языков на странице
     * категории/бренда вёл на ту же страницу, а не на главную (как делает Polylang
     * по умолчанию для архивов кастомных таксономий).
     *
     * - переводимая таксономия -> ссылка на переведённый терм (null, если перевода нет);
     * - непереводимая (общий терм) -> тот же терм, подменяем язык в ссылке.
     *
     * @param \WP_Term $term
     * @param string   $slug язык назначения
     * @return string|null
     */
    public static function term_translation_url(\WP_Term $term, string $slug): ?string
    {
        if ($slug === '') return null;

        if (function_exists('pll_is_translated_taxonomy') && pll_is_translated_taxonomy($term->taxonomy)) {
            $translated = function_exists('pll_get_term') ? (int) pll_get_term($term->term_id, $slug) : 0;
            if ($translated <= 0) return null; // нет перевода - оставляем дефолт Polylang

            $link = get_term_link($translated, $term->taxonomy);
            return is_wp_error($link) ? null : $link;
        }

        // Непереводимая таксономия: тот же терм, меняем язык в ссылке.
        $link = get_term_link($term);
        if (is_wp_error($link)) return null;

        if (function_exists('PLL')) {
            $pll = PLL();
            if (isset($pll->links_model, $pll->model) && method_exists($pll->links_model, 'switch_language_in_link')) {
                $lang = $pll->model->get_language($slug);
                if ($lang) return $pll->links_model->switch_language_in_link($link, $lang);
            }
        }
        return null;
    }
}
