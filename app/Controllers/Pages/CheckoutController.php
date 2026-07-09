<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Pages;

use Timber\Timber;
use UnitshotTheme\Classes\Breadcrumbs;

/**
 * Страница оформления заказа (кастомный маршрут /checkout/, см. Routing).
 *
 * Сам состав заказа на фронте берётся из стора корзины ($store.cart), отправка -
 * через REST (Order::rest_create), который читает корзину сервером. Контроллер
 * отдаёт только обвязку страницы (крошки, заголовок).
 *
 * @package UnitshotTheme
 */
class CheckoutController
{
    public function render(): void
    {
        Timber::render('@pages/checkout.twig', [
            'breadcrumbs' => (new Breadcrumbs())->for_custom(pll__('Оформлення замовлення')),
            'title'       => pll__('Оформлення замовлення'),
        ]);
    }
}
