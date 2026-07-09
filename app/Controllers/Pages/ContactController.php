<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Pages;

use Timber\Timber;
use UnitshotTheme\Classes\Breadcrumbs;
use UnitshotTheme\Classes\Fields;
use UnitshotTheme\Enums\IconsEnum;

class ContactController
{
    private array $general;
    private array $fields;

    function __construct()
    {
        $this->general  = Fields::get_settings_general();
        $this->fields   = Fields::get_contact_page();
    }

    private function get_contacts(): array
    {
        $number = fn($a) => is_array($a) ? array_map(fn($item) => $item['number'] ?? null, $a) : [];
        $social = fn($a) => is_array($a) ? array_map(fn($item) => ['icon' => IconsEnum::tryFrom($item['icon'] ?? '')->icon_filename(), 'link' => $item['link'] ?? ''], $a) : [];

        return array_map(function ($contact) use ($number, $social) {
            return [
                'title'     => !empty($contact['title'] ?? null) ? $contact['title'] : '',
                'type'      => !empty($contact['type'] ?? null) ? $contact['type'] : 'text',
                'content'   => !empty($contact['content'] ?? null) ? $contact['content'] : '',
                'numbers'   => $number($this->general['phone_numbers']),
                'socials'   => $social($this->general['social_medias']),
                'email'     => $this->general['email'],
            ];
        }, $this->fields['contacts']);
    }

    private function get_map(): array
    {
        $apikeys    = Fields::get_settings_api_keys();
        $map        = $this->fields['map'];

        return [
            'lat'   => is_numeric($map['lat'] ?? null) ? (float)$map['lat'] : 0,
            'lng'   => is_numeric($map['lng'] ?? null) ? (float)$map['lng'] : 0,
            'zoom'  => 15,
            'api'   => $apikeys['googlemaps'],
        ];
    }

    public function render(): void
    {
        Timber::render("@pages/contact.twig", [
            'breadcrumbs'   => (new Breadcrumbs())->for_page(),
            'title'         => get_the_title(),
            'description'   => $this->fields['description'],
            'contacts'      => $this->get_contacts(),
            'map'           => $this->get_map(),
        ]);
    }
}
