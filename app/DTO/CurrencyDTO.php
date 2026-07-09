<?php declare(strict_types=1);

namespace UnitshotTheme\DTO;

class CurrencyDTO
{
    function __construct(
        public string $code,
        public string $symbol,
        public float  $value,
        public bool   $is_default,
    )
    {
    }

    public static function from_field(array $field): self
    {
        return new self(
            !empty($field['code'])                  ? $field['code']            : '',
            !empty($field['symbol'])                ? $field['symbol']          : '',
            is_numeric($field['value'] ?? null)     ? (float)$field['value']    : 1,
            is_bool($field['is_default'] ?? null)   ? $field['is_default']      : false,
        );
    }

    public static function get_default(): self
    {
        return new self('EUR', '€', 1, true);
    }
}
