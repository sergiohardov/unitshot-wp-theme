# unitshot-theme

Кастомная WordPress-тема интернет-магазина (Timber/Twig + Alpine, ACF Pro, Polylang Pro). Без WooCommerce - товары на
своём CPT `product`, заказ уходит на email и в Telegram.

## Требования

- PHP **8.2+** (строгая типизация, enum, `match`)
- Node **≥ 24** (на старых версиях падает сборка Vite)
- Composer
- Плагины (проверяются в `functions.php`, без них тема не грузится):
    - **Advanced Custom Fields Pro**
    - **Polylang Pro**
    - **ACF Options for Polylang** (`acf-options-for-polylang`)

## Установка

```bash
composer install
cd sources && npm i && npm run build
```

## Разработка

```bash
cd sources
npm run watch
```

Сборка фронта кладётся в `assets/` (css, js, images, twig).

## Релиз

1. Слить всё в `main`, обновить локально.
2. Выполнить команду релиза:

```bash
composer release
```

3. Плашка обновления появляется в **Консоль -> Обновления**.

## Архитектура (`app/`)

Бутстрап в `functions.php`: инстанцируются классы-модули (`Twig`, `Acf`, `Types`, `Taxonomies`, `Rest`, `Routing`,
`Seo`, `Cron`, …).

Рендер - Timber: **Controller -> DTO -> Twig**.

- `Controllers/Pages` - страницы (каталог, товар, пост, checkout, контакты…)
- `Controllers/Blocks` - ACF-блоки (наследуют `AbstractController`)
- `Controllers/Parts` - шапка/футер
- `Classes/` - бизнес-логика (`Cart`, `Currency`, `Products`, `Helpers`, `Notifier`/`Notification`, `Breadcrumbs`,
  `ProductQuery`…)
- `DTO/` - объекты данных (`ProductDTO`, `ImageDTO`…)
- `Enums/` - единые источники ключей/слагов (`TypesEnum`, `TaxonomiesEnum`, `RoutesEnum`, `ProductFieldsEnum`…)

Добавить страницу/блок: контроллер + twig (`sources/src/twig/...`) + ACF-группа (`json-acf/`).

### Классы верхнего уровня

| Класс                      | Назначение                                                       |
|----------------------------|------------------------------------------------------------------|
| `Theme`                    | базовая настройка WP (меню, supports)                            |
| `Assets`                   | подключение css/js, объект `UNITSHOT` на фронт                   |
| `Twig`                     | функции/расширения Timber                                        |
| `Types` / `Taxonomies`     | CPT `product`, таксономии (бренд, категория, параметр, атрибуты) |
| `Admin`                    | админка, React-метабокс товара                                   |
| `Rest`                     | регистрация REST-роутов                                          |
| `Routing`                  | кастомные маршруты через `template_redirect`                     |
| `Seo`                      | meta title/description                                           |
| `Cron`                     | фоновые задачи (курсы валют, прогрев фида)                       |
| `Acf` / `Bea` / `Polylang` | ACF local JSON, переводимые options, мультиязычность             |

## Ключевые фичи

- **Товар** (`product`): типы simple / variable / complete, React-метабокс, денормализация цены в `_us_price_index` (
  фильтр/сортировка).
- **Атрибуты**: динамические таксономии, флаг «переводимый», значения - через String Translations.
- **Корзина**: server-authoritative, httponly-кука `us_cart`, канонические id, конкретная комбинация атрибутов; мутации
  через REST.
- **Кастомные маршруты** (`RoutesEnum`): `/checkout/`, `/google-merchant.xml`.
- **Google Merchant фид**: XML по языкам, обновление по Cron + ручной тумблер в настройках «Общие».
- **SEO**: meta на объект + глобальные шаблоны с тегами (`{page_title}`, `{site_name}`, `{separator}`…).
- **Заказ / звонок**: отправка на email + Telegram (`Notifier`/`Notification`).
- **Мультивалютность** (`Currency`): курсы через currencyapi, кука, Cron.

## Настройки (админка -> «Unitshot»)

Страницы: **Общие**, **Валюта**, **API Ключи**, **SEO**, **Страницы**.

Ключи на странице «API Ключи»: `currencyapi`, `googlemaps`, `telegram_token`, `telegram_chat_id`, `order_email` (куда
шлются заказы/звонки).

## ACF

Поля хранятся в `json-acf/` (local JSON). При добавлении новой группы/страницы её ключ нужно прописать в
`Acf::save_fields()`, иначе правки из админки не сохранятся обратно в JSON.

## REST API

Namespace `unitshot/v1`. Мутирующие роуты требуют nonce (`X-WP-Nonce`).

| Метод | Роут                                          | Авторизация |
|-------|-----------------------------------------------|-------------|
| GET   | `/set-currency`                               | public      |
| GET   | `/product/front-search`                       | public      |
| POST  | `/cart/add` · `/cart/update` · `/cart/remove` | nonce       |
| POST  | `/order/create`                               | nonce       |
| POST  | `/callback/create`                            | nonce       |

## Локализация

- Плагин **Polylang**.
- Особые строки (например атрибуты) - `pll_register_string()`.
- Статические строки на фронте/бекенде - `pll__()`, собираются в `languages/pll-strings.json`.
- Все строки - в **Языки -> Переводы**.
- После добавления новых строк:

```bash
composer collect-pll
```

## Подводные камни

- **Twig 3** (Timber 2.4): инлайновый `{% for x in y if ... %}` удалён -> `{% for x in y|filter(...) %}`.
- **Локаль украинского в Polylang** должна быть `uk`, а не `ua_UA` (для `ua_UA` в WP нет перевода -> даты/строки на
  английском).
- После изменения регистрации таксономий - **пересохранить permalinks** (Настройки -> Постоянные ссылки).
- Сборка требует **Node ≥ 24**.
