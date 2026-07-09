<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Blocks;

use UnitshotTheme\Classes\Fields;
use UnitshotTheme\Enums\IconsEnum;

class FeedbackInfoController extends AbstractController
{
    protected function get_template_path(): string
    {
        return '@blocks/feedback-info.twig';
    }

    protected function prepare_fields(array $fields): array
    {
        return [
            'block_id'      => !empty($fields['block_id'] ?? null)      ? $fields['block_id']       : '',
            'title'         => !empty($fields['title'] ?? null)         ? $fields['title']          : '',
            'description'   => !empty($fields['description'] ?? null)   ? $fields['description']    : '',
        ];
    }

    public function get_context(): array
    {
        $general_settings = Fields::get_settings_general();

        return [
            'block_id'      => $this->fields['block_id'],
            'title'         => $this->fields['title'],
            'description'   => $this->fields['description'],
            'socials'       => array_map(function ($social) {
                return [
                    'link' => $social['link'] ?? '',
                    'icon' => IconsEnum::tryFrom($social['icon'] ?? null)?->icon_filename() ?? '',
                ];
            }, $general_settings['social_medias']),
            'numbers'       => array_map(function ($number) {
                return $number['number'] ?? '';
            }, $general_settings['phone_numbers']),
        ];
    }
}

