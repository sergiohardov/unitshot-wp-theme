<?php declare(strict_types=1);

namespace UnitshotTheme\Enums;

/**
 * Кастомные маршруты темы (которых нет среди обычных страниц/архивов).
 *
 * Единый источник slug'ов - чтобы пути не хардкодились по проекту: URL строится
 * из этого enum (см. Routing::url). При смене пути меняется только значение здесь.
 */
enum RoutesEnum: string
{
    case Checkout = 'checkout';
    case Feed     = 'google-merchant.xml';
}
