<?php declare(strict_types=1);

namespace UnitshotTheme\DTO;

/**
 * Прикреплённый к товару файл (PDF и пр.) для блока «ФАЙЛИ».
 * Собирается фабрикой from_attachment() из id вложения.
 */
class FileDTO
{
    public function __construct(
        public readonly int    $id,
        public readonly string $url,
        public readonly string $name,
        public readonly string $extension,
    ) {
    }

    public static function from_attachment(int $id): ?self
    {
        $url = wp_get_attachment_url($id);
        if (!is_string($url) || $url === '') return null;

        // Человекочитаемое имя - заголовок вложения; фолбэк - имя файла.
        $title = get_the_title($id);
        $name  = is_string($title) && $title !== '' ? $title : basename($url);
        $ext   = strtolower((string) pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return new self(id: $id, url: $url, name: $name, extension: $ext);
    }

    public function get_array(): array
    {
        return [
            'id'        => $this->id,
            'url'       => $this->url,
            'name'      => $this->name,
            'extension' => $this->extension,
        ];
    }
}
