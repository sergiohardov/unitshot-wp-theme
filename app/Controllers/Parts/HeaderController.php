<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Parts;

use Timber\Timber;
use UnitshotTheme\Classes\Cookies;
use UnitshotTheme\Classes\Fields;
use UnitshotTheme\DTO\BrandDTO;
use UnitshotTheme\DTO\ImageDTO;
use UnitshotTheme\Enums\IconsEnum;
use UnitshotTheme\Enums\TaxonomiesEnum;
use UnitshotTheme\Polylang;
use UnitshotTheme\Rest;
use UnitshotTheme\Theme;

class HeaderController
{
    private array $general;
    private array $currency;

    public function __construct()
    {
        $this->general  = Fields::get_settings_general();
        $this->currency = Fields::get_settings_currency();
    }

    private function get_logo(): array
    {
        $logo = new ImageDTO($this->general['logo']);
        return $logo->get_array();
    }

    private function get_settings(): array
    {
        $languages = Polylang::language_switcher();

        $currencies = array_map(function ($currency) {
            return [
                "title"         => $currency['code'] ?: '?',
                "link"          => get_rest_url(null, Rest::REST_NAMESPACE . Rest::REST_ROUTE_CURRENCY . '?currency=' . $currency['code']),
                "is_current"    => Cookies::get_currency() === $currency['code'],
            ];
        }, $this->currency['currencies']);

        return [$currencies, $languages];
    }

    private function get_socials(): array
    {
        return array_map(function ($social) {
            return [
                "icon" => IconsEnum::tryFrom($social['icon'])?->icon_filename() ?? '',
                "title" => $social['title'] ?? '',
                "link" => $social['link'] ?? '',
            ];
        }, $this->general['social_medias']);
    }

    private function get_brands(): array
    {
        $menu_data = Theme::get_menu_data_by_location('brands_menu');

        return array_map(function ($item) {
            $term = get_term_by('id', $item['object_id'], TaxonomiesEnum::ProductBrand->value);
            if(!is_a($term, 'WP_Term')) return [];

            $brand = BrandDTO::from_wp_term($term);

            return [
                "link" => $item['link'],
                "image" => $brand->image->get_array(),
            ];
        }, $menu_data['menu']);
    }

    private function get_phone_number(): string
    {
        $numbers = $this->general['phone_numbers'];
        return $numbers[0]['number'] ?? '';
    }

    public function render(): bool|string
    {
        return Timber::compile('@parts/header.twig', [
            'logo'          => $this->get_logo(),
            'settings'      => $this->get_settings(),
            'top_menu'      => Theme::get_menu_data_by_location('top_menu')['menu'],
            'number'        => $this->get_phone_number(),
            'socials'       => $this->get_socials(),
            'categories'    => Theme::get_menu_data_by_location('catalog_menu')['menu'],
            'brands'        => $this->get_brands(),
        ]);
    }
}
