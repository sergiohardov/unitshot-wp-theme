<?php declare(strict_types=1);

namespace UnitshotTheme;

use UnitshotTheme\Enums\CustomOptionPageIdsEnum;

if (!defined("ABSPATH")) die;

/**
 * Интеграция темы с плагином BEA.
 *
 * Выполняет дополнительную настройку поведения плагина через хуки WordPress.
 * В частности, исключает служебные страницы настроек темы из обработки
 * и синхронизации, используя фильтр `bea.aofp.excluded_post_ids`.
 *
 * @package UnitshotTheme
 */
class Bea
{
    function __construct()
    {
        add_filter('bea.aofp.excluded_post_ids', [$this, 'exclude_options_page'], 10, 1);
    }

    /**
     * Исключает acf options страницы на которых не нужно выполнять polylang перевод
     *
     * Список исключений формируется на основе идентификаторов страниц
     * настроек, определённых в перечислении CustomOptionPageIdsEnum.
     *
     * @param array $ids
     * @return array
     */
    public function exclude_options_page(array $ids): array {
        $ids[] = CustomOptionPageIdsEnum::Currency->value;
        $ids[] = CustomOptionPageIdsEnum::APIKeys->value;

        return $ids;
    }
}
