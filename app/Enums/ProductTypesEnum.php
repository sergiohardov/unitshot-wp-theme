<?php declare(strict_types=1);

namespace UnitshotTheme\Enums;

enum ProductTypesEnum: string
{
    case Simple     = 'simple';
    case Variable   = 'variable';
    case Complete   = 'complete';

    public function get_label(): string
    {
        return match ($this) {
            self::Simple    => 'Простой',
            self::Variable  => 'Вариативный',
            self::Complete  => 'Комплектный',
        };
    }

    public static function group_labels(): array
    {
        $result = [];

        foreach (self::cases() as $case) $result[$case->value] = $case->get_label();

        return $result;
    }
}

