<?php declare(strict_types=1);

namespace UnitshotTheme\Enums;

enum ProductStatusesEnum: string
{
    case InStockEurope  = 'in-stock-europe';
    case ToOrder        = 'to-order';

    public function status_label(): string
    {
        return match ($this) {
            self::InStockEurope => pll__('На складі в Європі'),
            self::ToOrder       => pll__('Під замовлення'),
        };
    }

    public static function group(): array
    {
        return array_column(self::cases(), 'name', 'value');
    }

    public static function group_labels(): array
    {
        $cases  = self::cases();
        $result = [];

        foreach ($cases as $case) $result[$case->value] = $case->tryFrom($case->value)->status_label();

        return $result;
    }
}
