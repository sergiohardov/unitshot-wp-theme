<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Blocks;

use UnitshotTheme\DTO\ImageDTO;

class ClientLogosController extends AbstractController
{
    protected function get_template_path(): string
    {
        return '@blocks/client-logos.twig';
    }

    protected function prepare_fields(array $fields): array
    {
        return [
            'block_id'  => !empty($fields['block_id'] ?? null)  ? $fields['block_id']   : '',
            'title'     => !empty($fields['title'] ?? null)     ? $fields['title']      : '',
            'gallery'   => is_array($fields['gallery'] ?? null) ? $fields['gallery']    : [],
        ];
    }

    public function get_context(): array
    {
        return [
            'block_id'  => $this->fields['block_id'],
            'title'     => $this->fields['title'],
            'clients'   => array_map(function ($image_id) {
                return new ImageDTO($image_id);
            }, $this->fields['gallery']),
        ];
    }
}

