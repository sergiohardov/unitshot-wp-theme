<?php declare(strict_types=1);

namespace UnitshotTheme\Classes;

use UnitshotTheme\Enums\TaxonomiesEnum;
use WP_Post;
use WP_Post_Type;
use WP_Term;
use WP_User;

class Breadcrumbs
{
    private int $queried_object_id;
    private null|WP_Post|WP_Post_Type|WP_Term|WP_User $queried_object;
    private ?int $home_id;
    private ?int $blog_id;
    private array $breadcrumbs = [];

    public function __construct()
    {
        $this->queried_object_id    = get_queried_object_id();
        $this->queried_object       = get_queried_object();
        $this->home_id              = (int) get_option('page_on_front') ?: null;
        $this->blog_id              = (int) get_option('page_for_posts') ?: null;

        // По дефолту начальная точка хлебной крошки - главная
        $this->add_post($this->home_id);
    }

    private function add_item(string $link, string $title, ?int $current_id): void
    {
        $this->breadcrumbs[] = [
            'src'        => $link,
            'title'      => $title,
            'is_current' => $this->queried_object_id === $current_id,
        ];
    }

    /** Крошка-пост/страница по id: ссылка и заголовок берутся автоматически. */
    private function add_post(?int $id): void
    {
        $this->add_item((string) get_the_permalink($id), (string) get_the_title($id), $id);
    }

    public function for_blog_page(): array
    {
        $this->add_post($this->blog_id);
        return $this->breadcrumbs;
    }

    public function for_page(): array
    {
        $this->add_post($this->queried_object_id);
        return $this->breadcrumbs;
    }

    /** Главная -> текущий заголовок (для кастомных маршрутов без WP-объекта, напр. checkout). */
    public function for_custom(string $title): array
    {
        $this->add_item('', $title, $this->queried_object_id);
        return $this->breadcrumbs;
    }

    public function for_post(): array
    {
        $this->add_post($this->blog_id);
        $this->add_post($this->queried_object_id);
        return $this->breadcrumbs;
    }

    public function for_products(): array
    {
        // Корневая категория товара (parent === 0) - промежуточная крошка.
        foreach (wp_get_post_terms($this->queried_object_id, TaxonomiesEnum::ProductCategory->value) as $term) {
            if ($term instanceof WP_Term && $term->parent === 0) {
                $link = get_term_link($term);
                if (!is_wp_error($link)) $this->add_item($link, $term->name, $term->term_id);
                break;
            }
        }

        $this->add_post($this->queried_object_id);
        return $this->breadcrumbs;
    }

    public function for_catalog(): array
    {
        if (is_search()) {
            $this->add_item('', pll__('Результати пошуку'), $this->queried_object_id);
            return $this->breadcrumbs;
        }

        // Архив терма (категория/бренд): главная -> предки -> текущий терм.
        if ($this->queried_object instanceof WP_Term) {
            $ancestors = array_reverse(get_ancestors($this->queried_object->term_id, $this->queried_object->taxonomy));

            foreach ([...$ancestors, $this->queried_object->term_id] as $term_id) {
                $term = get_term((int) $term_id, $this->queried_object->taxonomy);
                if (!$term instanceof WP_Term) continue;

                $link = get_term_link($term);
                $this->add_item(is_wp_error($link) ? '' : $link, $term->name, $term->term_id);
            }

            return $this->breadcrumbs;
        }

        $this->add_post($this->queried_object_id);
        return $this->breadcrumbs;
    }
}
