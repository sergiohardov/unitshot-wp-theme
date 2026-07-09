<?php declare(strict_types=1);

namespace UnitshotTheme\Enums;

enum IconsEnum: string
{
    case Instagram  = 'instagram';
    case Telegram   = 'telegram';
    case Tiktok     = 'tiktok';
    case Viber      = 'viber';
    case Whatsapp   = 'whatsapp';

    /**
     * Название файлов иконок в assets/images/icons
     *
     * В последствии вызывается на фронте через функцию {{ svg_icon('social-instagram') }}
     *
     * @return string
     */
    public function icon_filename(): string
    {
        return match ($this) {
            self::Instagram     => 'social-instagram',
            self::Telegram      => 'social-telegram',
            self::Tiktok        => 'social-tiktok',
            self::Viber         => 'social-viber',
            self::Whatsapp      => 'social-whatsapp',
        };
    }

    public static function group(): array
    {
        return array_column(self::cases(), 'name', 'value');
    }
}
