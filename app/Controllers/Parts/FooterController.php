<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Parts;

use Timber\Timber;
use UnitshotTheme\Classes\Fields;
use UnitshotTheme\Classes\Helpers;
use UnitshotTheme\DTO\ImageDTO;
use UnitshotTheme\Enums\IconsEnum;
use UnitshotTheme\Theme;

class FooterController
{
    private array $general;

    public function __construct()
    {
        $this->general = Fields::get_settings_general();
    }

    private function get_logo(): array
    {
        $logo = new ImageDTO($this->general['logo']);
        return $logo->get_array();
    }

    private function get_numbers(): array {
        return array_map(fn($item) => $item['number'], $this->general['phone_numbers']);
    }

    private function get_socials(): array
    {
        return array_map(function ($social) {
            return [
                "icon" => IconsEnum::tryFrom($social['icon'])?->icon_filename() ?? '',
                "link" => $social['link'] ?? '',
            ];
        }, $this->general['social_medias']);
    }

    public function render(): bool|string
    {
        return Timber::compile('@parts/footer.twig', [
            'logo'      => $this->get_logo(),
            'menu_1'    => Theme::get_menu_data_by_location('footer_menu_1'),
            'menu_2'    => Theme::get_menu_data_by_location('footer_menu_2'),
            'numbers'   => $this->get_numbers(),
            'socials'   => $this->get_socials(),
            'email'     => $this->general['email'],
            'address'   => $this->general['address'],
        ]);
    }
}

