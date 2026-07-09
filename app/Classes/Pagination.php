<?php declare(strict_types=1);

namespace UnitshotTheme\Classes;

if (!defined("ABSPATH")) die;

/**
 * Данные пагинации для @components/pagination.twig.
 *
 * Строит «окно» номеров страниц с многоточиями (всегда первая/последняя + соседи
 * вокруг текущей), prev/next и флаги текущей страницы. Ссылки - через
 * get_pagenum_link (нативный формат WP /page/N/ или ?paged=N, сохраняет текущую
 * query-строку: активные фильтры/сортировку).
 *
 * @package UnitshotTheme
 */
class Pagination
{
    private int $total;
    private int $current;
    private int $edges;
    private int $around;

    /**
     * @param int      $total_pages Всего страниц (обычно WP_Query::$max_num_pages).
     * @param int|null $current     Текущая страница; null - берётся из query var WP.
     * @param int      $edges       Сколько страниц всегда показывать у краёв.
     * @param int      $around      Соседей вокруг текущей с каждой стороны.
     */
    public function __construct(int $total_pages, ?int $current = null, int $edges = 1, int $around = 1)
    {
        $this->total   = max(0, $total_pages);
        $this->current = $current ?? max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
        $this->edges   = $edges;
        $this->around  = $around;
    }

    /**
     * @return array{prev:string, next:string, pages:array<int, array{number?:int, link?:string, is_current?:bool, is_ellipsis?:bool}>}
     */
    public function build(): array
    {
        if ($this->total < 2) return ['prev' => '', 'next' => '', 'pages' => []];

        return [
            'prev'  => $this->current > 1 ? esc_url(get_pagenum_link($this->current - 1)) : '',
            'next'  => $this->current < $this->total ? esc_url(get_pagenum_link($this->current + 1)) : '',
            'pages' => $this->pages(),
        ];
    }

    /**
     * Окно номеров с эллипсисом: «1 2 3 … 60», «1 … 58 59 60», «1 … 29 30 31 … 60».
     *
     * @return array<int, array{number?:int, link?:string, is_current?:bool, is_ellipsis?:bool}>
     */
    private function pages(): array
    {
        // Собираем номера к показу (без дублей: края + окно вокруг текущей).
        $numbers = [];
        for ($i = 1; $i <= $this->edges; $i++) { $numbers[$i] = true; $numbers[$this->total - $i + 1] = true; }
        for ($i = $this->current - $this->around; $i <= $this->current + $this->around; $i++) {
            if ($i >= 1 && $i <= $this->total) $numbers[$i] = true;
        }
        $numbers = array_keys($numbers);
        sort($numbers);

        // Разрывы между номерами заменяем маркером эллипсиса.
        $pages = [];
        $prev  = 0;
        foreach ($numbers as $number) {
            if ($prev && $number - $prev > 1) $pages[] = ['is_ellipsis' => true];
            $pages[] = [
                'number'     => $number,
                'link'       => esc_url(get_pagenum_link($number)),
                'is_current' => $number === $this->current,
            ];
            $prev = $number;
        }

        return $pages;
    }
}
