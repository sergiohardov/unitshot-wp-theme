<?php declare(strict_types=1);

namespace UnitshotTheme;

use UnitshotTheme\Classes\Fields;
use UnitshotTheme\Enums\TaxonomiesEnum;
use UnitshotTheme\Enums\TypesEnum;

if (!defined("ABSPATH")) die;

/**
 * Централизованный вывод SEO meta-данных (title/description).
 *
 * Значение для каждого объекта берётся по приоритету:
 *   1. Локальные поля объекта (ACF-группа "unitshot_seo" на странице/записи/
 *      товаре/термине).
 *   2. Глобальный шаблон по типу контента (переводимая options-страница SEO),
 *      с подстановкой тегов {page_title}, {site_name}, {site_description},
 *      {separator}.
 *   3. Стандартное поведение WordPress (заголовок) либо фолбэк-описание
 *      (анонс записи / описание термина).
 *
 * @package UnitshotTheme
 */
class Seo
{
    function __construct()
    {
        add_filter('pre_get_document_title', [$this, 'document_title']);
        add_action('wp_head',                [$this, 'meta_description'], 1);
    }

    /**
     * Заголовок документа: локальный meta_title → глобальный шаблон → дефолт WP.
     *
     * Непустой результат отключает стандартную генерацию заголовка WP, поэтому
     * при отсутствии значения возвращаем $title без изменений — соберёт ядро.
     */
    public function document_title(string $title): string
    {
        $context = $this->current_context();
        if ($context === null) return $title;

        $local = Fields::get_seo($context['object_id'])['meta_title'];
        if ($local !== '') return $this->render_template($local, $context);

        $global = $this->global_template($context['type'], 'title');
        if ($global !== '') return $this->render_template($global, $context);

        return $title;
    }

    /**
     * <meta name="description">: локальный → глобальный шаблон → фолбэк.
     */
    public function meta_description(): void
    {
        $context = $this->current_context();
        if ($context === null) return;

        $local = Fields::get_seo($context['object_id'])['meta_description'];

        if ($local !== '') {
            $description = $this->render_template($local, $context);
        } else {
            $global      = $this->global_template($context['type'], 'description');
            $description = $global !== '' ? $this->render_template($global, $context) : $this->fallback_description();
        }

        if ($description === '') return;

        printf('<meta name="description" content="%s">' . "\n", esc_attr($description));
    }

    /**
     * Контекст текущего запроса: тип контента, ACF-идентификатор объекта и его
     * «естественный» заголовок (для тега {page_title}). null — вне поддержки.
     *
     * @return array{type: string, object_id: int|string, title: string}|null
     */
    private function current_context(): ?array
    {
        if (is_singular()) {
            $id   = get_queried_object_id();
            $type = match (get_post_type($id)) {
                TypesEnum::Page->value    => 'page',
                TypesEnum::Post->value    => 'post',
                TypesEnum::Product->value => 'product',
                default                   => null,
            };

            return $id && $type ? ['type' => $type, 'object_id' => $id, 'title' => get_the_title($id)] : null;
        }

        if (is_tax([TaxonomiesEnum::ProductCategory->value, TaxonomiesEnum::ProductBrand->value])) {
            $term = get_queried_object();
            if (!$term instanceof \WP_Term) return null;

            $type = $term->taxonomy === TaxonomiesEnum::ProductCategory->value ? 'category' : 'brand';
            return ['type' => $type, 'object_id' => "term_{$term->term_id}", 'title' => $term->name];
        }

        return null;
    }

    /**
     * Глобальный шаблон title/description для типа контента (или '').
     */
    private function global_template(string $type, string $field): string
    {
        $settings = Fields::get_settings_seo();

        return (string) ($settings[$type][$field] ?? '');
    }

    /**
     * Подставляет в шаблон значения тегов текущего объекта.
     */
    private function render_template(string $template, array $context): string
    {
        $settings = Fields::get_settings_seo();

        $replacements = [
            '{page_title}'       => $context['title'],
            '{site_name}'        => $settings['site_name'] !== '' ? $settings['site_name'] : get_bloginfo('name'),
            '{site_description}' => get_bloginfo('description'),
            '{separator}'        => $settings['separator'],
        ];

        $result = strtr($template, $replacements);

        // Схлопываем двойные пробелы от пустых тегов и подчищаем края.
        return trim((string) preg_replace('/\s{2,}/', ' ', $result));
    }

    /**
     * Описание по умолчанию: анонс записи или описание термина.
     */
    private function fallback_description(): string
    {
        $object = get_queried_object();

        if ($object instanceof \WP_Post) {
            $excerpt = has_excerpt($object) ? get_the_excerpt($object) : $object->post_content;
        } elseif ($object instanceof \WP_Term) {
            $excerpt = $object->description;
        } else {
            return '';
        }

        $excerpt = trim(wp_strip_all_tags((string) $excerpt));
        if ($excerpt === '') return '';

        return wp_trim_words($excerpt, 30, '');
    }
}
