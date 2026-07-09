<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Blocks;

use Timber\Timber;

/**
 * Абстрактный базовый контроллер для блоков Gutenberg / ACF.
 *
 * Определяет общий жизненный цикл блока: подготовка полей, сборка контекста
 * и рендеринг Twig-шаблона через Timber.
 *
 * Каждый конкретный контроллер блока обязан реализовать три метода:
 * - {@see prepare_fields()} — валидация и нормализация входных данных ACF
 * - {@see get_context()}    — формирование контекста для Twig-шаблона
 * - {@see get_template_path()} — путь к Twig-шаблону
 *
 */
abstract class AbstractController
{
    /**
     * Подготовленные и нормализованные поля блока.
     *
     * Заполняется в конструкторе через {@see prepare_fields()}.
     * Используется в {@see get_context()} дочерних классов.
     *
     * @var array<string, mixed>
     */
    protected array $fields;

    /**
     * @param array<string, mixed> $fields Сырые поля блока из ACF (get_fields() и т.п.)
     */
    function __construct(array $fields)
    {
        $this->fields = $this->prepare_fields($fields);
    }

    /**
     * Рендерит Twig-шаблон блока напрямую в буфер вывода.
     *
     * @return void
     */
    final public function render(): void
    {
        Timber::render($this->get_template_path(), $this->get_context());
    }

    /**
     * Компилирует Twig-шаблон блока и возвращает HTML как строку.
     *
     * Удобно использовать когда HTML нужно передать в контекст родительского шаблона.
     *
     * @return string Скомпилированный HTML блока
     */
    final public function compile(): string
    {
        return Timber::compile($this->get_template_path(), $this->get_context());
    }

    /**
     * Валидирует и нормализует сырые поля блока.
     *
     * Вызывается автоматически в конструкторе. Результат сохраняется в {@see $fields}.
     * Здесь следует задавать дефолтные значения и приводить типы,
     * чтобы {@see get_context()} всегда работал с предсказуемой структурой данных.
     *
     * @param  array<string, mixed> $fields Сырые поля из ACF
     * @return array<string, mixed>         Нормализованные поля
     */
    abstract protected function prepare_fields(array $fields): array;

    /**
     * Формирует контекст для Twig-шаблона.
     *
     * Использует нормализованные данные из {@see $fields}.
     * Здесь выполняются запросы к БД, маппинг DTO и прочая бизнес-логика блока.
     *
     * @return array<string, mixed> Контекст, передаваемый в Twig
     */
    abstract protected function get_context(): array;

    /**
     * Возвращает путь к Twig-шаблону блока.
     *
     * Поддерживает Timber-неймспейсы, например: "@blocks/my-block.twig"
     *
     * @return string Путь к шаблону
     */
    abstract protected function get_template_path(): string;
}
