<?php declare(strict_types=1);

namespace UnitshotTheme\Classes;

if (!defined("ABSPATH")) die;

/**
 * Сообщение уведомления (заказ, обратный звонок и т.п.).
 *
 * Билдер: наполняется данными (строки label:value, позиции-списком, итог) и
 * отдаёт готовые тела для каналов - email_html() и telegram_text(). Единое место
 * вёрстки и экранирования, чтобы каждый тип уведомления не повторял эту логику.
 * Доставку делает Notifier.
 *
 * @package UnitshotTheme
 */
class Notification
{
    private string $title;

    /** @var array<int, array{0:string, 1:string}> строки label => value */
    private array $rows = [];

    /** @var string[] позиции списком (напр. товары заказа) */
    private array $items = [];

    /** @var array{0:string, 1:string}|null выделенный итог (напр. сумма) */
    private ?array $total = null;

    public function __construct(string $title)
    {
        $this->title = $title;
    }

    /** Строка label: value. Пустые значения пропускаются. */
    public function row(string $label, string $value): self
    {
        if (trim($value) !== '') $this->rows[] = [$label, $value];
        return $this;
    }

    /** Позиция списка (буллет). */
    public function item(string $text): self
    {
        $this->items[] = $text;
        return $this;
    }

    /** Выделенный итог внизу. */
    public function total(string $label, string $value): self
    {
        $this->total = [$label, $value];
        return $this;
    }

    public function email_html(): string
    {
        $rows = '';
        foreach ($this->rows as [$label, $value]) {
            $rows .= sprintf('<b>%s:</b> %s<br>', esc_html($label), esc_html($value));
        }

        $items = '';
        foreach ($this->items as $text) {
            $items .= sprintf('<li>%s</li>', esc_html($text));
        }

        return sprintf(
            '<h2>%s</h2>%s%s%s',
            esc_html($this->title),
            $rows !== '' ? "<p>{$rows}</p>" : '',
            $items !== '' ? "<ul>{$items}</ul>" : '',
            $this->total ? sprintf('<p><b>%s: %s</b></p>', esc_html($this->total[0]), esc_html($this->total[1])) : ''
        );
    }

    public function telegram_text(): string
    {
        $esc   = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); // под parse_mode=HTML
        $parts = ['<b>' . $esc($this->title) . '</b>'];

        if ($this->rows) {
            $parts[] = implode("\n", array_map(fn($r) => sprintf('<b>%s:</b> %s', $esc($r[0]), $esc($r[1])), $this->rows));
        }

        if ($this->items) {
            $parts[] = implode("\n", array_map(fn($t) => '• ' . $esc($t), $this->items));
        }

        if ($this->total) {
            $parts[] = sprintf('<b>%s: %s</b>', $esc($this->total[0]), $esc($this->total[1]));
        }

        return implode("\n\n", $parts);
    }
}
