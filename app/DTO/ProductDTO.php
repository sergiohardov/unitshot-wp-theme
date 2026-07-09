<?php declare(strict_types=1);

namespace UnitshotTheme\DTO;

use UnitshotTheme\Enums\ProductTypesEnum;
use UnitshotTheme\Enums\ProductStatusesEnum;

/**
 * Объект товара под текущую (нативную, без ACF) модель данных.
 * Собирается в Helpers::get_product_dto_by_id() из _us_* meta и term-связей.
 */
class ProductDTO
{
    function __construct(
        public int                  $id,
        public string               $name,
        public string               $url,
        public ProductTypesEnum     $type,
        public ?string              $sku,
        public ?ProductStatusesEnum $status,
        public bool                 $in_stock,
        public ?float               $base_price,
        public ?float               $sale_price,
        public string               $description,
        public string               $short_description,
        public string               $model,
        /** @var ImageDTO[] */
        public array                $gallery,
        /** @var FileDTO[] */
        public array                $files,
        /** @var BrandDTO[] */
        public array                $brands,
        /** @var CategoryDTO[] */
        public array                $categories,
        /** @var array<int, array{taxonomy:string, label:string, values:string[], main:bool}> */
        public array                $attributes,
        /** @var array<int, array{taxonomy:string, label:string, terms:array<int, array{id:int, name:string}>}> */
        public array                $axes,
        /** @var array<int, array> Матрица вариаций (только для variable). */
        public array                $variations,
        /** @var array<string, int> Комбинация по умолчанию { taxonomy => term_id }. */
        public array                $default_attributes = [],
        /** @var array<int, array{product: self, variation_key: ?string, price: ?float, old_price: ?float}> Члены набора (complete). */
        public array                $set_items = [],
        /** @var array<int, array{label:string, value:string, main:bool}> Свободные параметры (taxonomy product_parameter + значение). */
        public array                $parameters = [],
    ) {
    }

    public function is_simple(): bool
    {
        return $this->type === ProductTypesEnum::Simple;
    }

    public function is_variable(): bool
    {
        return $this->type === ProductTypesEnum::Variable;
    }

    public function is_complete(): bool
    {
        return $this->type === ProductTypesEnum::Complete;
    }

    /**
     * Сумма «по отдельности» - зачёркнутая цена набора: Σ эффективных цен членов
     * (display_price уже учитывает variable: дефолт-комбинация, иначе min).
     */
    public function set_separate_total(): ?float
    {
        if (!$this->set_items) return null;

        $total = 0.0;
        foreach ($this->set_items as $item) {
            $total += (float) ($item['price'] ?? 0);
        }
        return $total > 0 ? $total : null;
    }

    /** Цена к оплате у простого товара: скидочная при наличии, иначе базовая. */
    public function current_price(): ?float
    {
        return is_numeric($this->sale_price) ? $this->sale_price : $this->base_price;
    }

    /** Старая (зачёркнутая) цена - только если задана скидка. */
    public function old_price(): ?float
    {
        return (is_numeric($this->sale_price) && is_numeric($this->base_price)) ? $this->base_price : null;
    }

    /**
     * Эффективная (к оплате) и старая (зачёркнутая) цена одной вариации.
     * Единый источник правила скидки (sale ?? regular; old только при обоих).
     *
     * @return array{price: ?float, old: ?float}
     */
    public function variation_effective(array $variation): array
    {
        $regular = is_numeric($variation['price'] ?? '') ? (float) $variation['price'] : null;
        $sale    = is_numeric($variation['sale_price'] ?? '') ? (float) $variation['sale_price'] : null;

        return [
            'price' => $sale ?? $regular,
            'old'   => ($sale !== null && $regular !== null) ? $regular : null,
        ];
    }

    /** Вариация по ключу (signature). */
    private function variation_by_key(string $key): ?array
    {
        foreach ($this->variations as $variation) {
            if ((string) ($variation['key'] ?? '') === $key) return $variation;
        }
        return null;
    }

    /** Эффективная цена вариации по ключу. */
    public function variation_price(string $key): ?float
    {
        $variation = $this->variation_by_key($key);
        return $variation ? $this->variation_effective($variation)['price'] : null;
    }

    /** Старая (зачёркнутая) цена вариации по ключу. */
    public function variation_old(string $key): ?float
    {
        $variation = $this->variation_by_key($key);
        return $variation ? $this->variation_effective($variation)['old'] : null;
    }

    /**
     * Варианты вариаций для выбора (в составе набора): [{key, label, price}].
     * label - имена термов комбинации (текущий язык), price - эффективная.
     *
     * @return array<int, array{key:string, label:string, price:?float}>
     */
    public function variation_options(): array
    {
        if (!$this->is_variable()) return [];

        $names = [];
        foreach ($this->axes as $axis) {
            foreach ($axis['terms'] as $term) {
                $names[$axis['taxonomy']][(int) $term['id']] = $term['name'];
            }
        }

        $out = [];
        foreach ($this->variations as $variation) {
            $key = (string) ($variation['key'] ?? '');
            if ($key === '') continue;

            $parts = [];
            foreach (($variation['attributes'] ?? []) as $taxonomy => $value) {
                $parts[] = $value === '*' ? 'Любое' : ($names[$taxonomy][(int) $value] ?? ('#' . $value));
            }

            $out[] = ['key' => $key, 'label' => implode(' / ', $parts), 'price' => $this->variation_effective($variation)['price']];
        }
        return $out;
    }

    /** Минимальная эффективная цена среди вариаций - для «от {min}». */
    public function min_variation_price(): ?float
    {
        $prices = [];
        foreach ($this->variations as $variation) {
            $eff = $this->variation_effective($variation)['price'];
            if ($eff !== null) $prices[] = $eff;
        }
        return $prices ? min($prices) : null;
    }

    /**
     * Вариация, подходящая под КОНКРЕТНУЮ комбинацию { taxonomy => term_id }.
     * Выбирает самую специфичную (меньше wildcard-осей); '*' матчит любое значение.
     * null - подходящей нет. Единый матчер: используется и для дефолт-комбинации,
     * и для позиции корзины (там комбинацию задаёт пользователь).
     *
     * @param array<string, int> $attrs
     */
    public function match_variation(array $attrs): ?array
    {
        $axes = array_map(fn ($axis) => $axis['taxonomy'], $this->axes);
        if (empty($axes)) return null;

        $best = null;
        $bestScore = -1;
        foreach ($this->variations as $variation) {
            $attrs_v = $variation['attributes'] ?? [];
            $ok = true;
            $score = 0;
            foreach ($axes as $taxonomy) {
                $val = $attrs_v[$taxonomy] ?? null;
                if ($val === '*') continue; // wildcard матчит любое
                if ((int) $val !== (int) ($attrs[$taxonomy] ?? 0)) { $ok = false; break; }
                $score++;
            }
            if ($ok && $score > $bestScore) { $best = $variation; $bestScore = $score; }
        }
        return $best;
    }

    /**
     * Вариация по умолчанию: подходящая под default_attributes (все оси заданы).
     * null - дефолт не задан/не покрывает все оси/не нашёлся.
     */
    public function default_variation(): ?array
    {
        $axes = array_map(fn ($axis) => $axis['taxonomy'], $this->axes);
        if (empty($axes) || empty($this->default_attributes)) return null;

        foreach ($axes as $taxonomy) {
            if (empty($this->default_attributes[$taxonomy])) return null; // не все оси заданы
        }

        return $this->match_variation($this->default_attributes);
    }

    /**
     * Подпись КОНКРЕТНОЙ комбинации: имена термов по осям (текущий язык), напр.
     * «Чорний / 180В». В отличие от подписи вариации, wildcard-оси здесь нет -
     * значение по каждой оси берётся из выбора пользователя.
     *
     * @param array<string, int> $attrs
     */
    public function attributes_label(array $attrs): string
    {
        $names = [];
        foreach ($this->axes as $axis) {
            foreach ($axis['terms'] as $term) {
                $names[$axis['taxonomy']][(int) $term['id']] = (string) $term['name'];
            }
        }

        $parts = [];
        foreach ($this->axes as $axis) {
            $taxonomy = $axis['taxonomy'];
            $id       = (int) ($attrs[$taxonomy] ?? 0);
            if ($id <= 0) continue;

            $parts[] = $names[$taxonomy][$id] ?? ('#' . $id);
        }
        return implode(' / ', $parts);
    }

    /** Цена для вывода: простой - своя; вариативный - дефолт-вариация, иначе min. */
    public function display_price(): ?float
    {
        if (!$this->is_variable()) return $this->current_price();

        $variation = $this->default_variation();
        return $variation ? $this->variation_effective($variation)['price'] : $this->min_variation_price();
    }

    /** Старая (зачёркнутая) цена для вывода. */
    public function display_old_price(): ?float
    {
        if ($this->is_complete()) return $this->set_separate_total(); // набор: Σ цен членов
        if (!$this->is_variable()) return $this->old_price();

        $variation = $this->default_variation();
        return $variation ? $this->variation_effective($variation)['old'] : null;
    }

    /** Нужен ли префикс «от» (вариативный без заданной дефолт-комбинации). */
    public function price_is_from(): bool
    {
        return $this->is_variable() && $this->default_variation() === null;
    }

    /**
     * Процент скидки между зачёркнутой и текущей ценой (целое); 0 - скидки нет.
     * Работает для всех типов: simple/variable (скидка), complete (выгода набора).
     */
    public function discount_percent(): int
    {
        $old = $this->display_old_price();
        $new = $this->display_price();

        if (!is_numeric($old) || !is_numeric($new) || $old <= 0 || $new >= $old) return 0;

        return (int) round(($old - $new) / $old * 100);
    }
}
