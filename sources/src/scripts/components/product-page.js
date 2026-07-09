import Alpine from "alpinejs";
import { formatPrice } from "@scripts/base/helpers.js";

/**
 * Резолвер вариаций товара.
 *
 * Данные приходят JSON-ом в .us-variations-data внутри x-data:
 *   { axes: ['product_attribute_color', ...],
 *     variations: [ { attributes: {tax: id|'*'}, price, old_price } ] }
 *
 * Резолвер отвечает только за цену (она per-variation). Артикул, наличие и статус —
 * уровень товара (единые), выводятся сервером, в этот JSON не входят.
 *
 * id осей и значения комбинаций - КАНОНИЧНЫЕ (default-language), поэтому совпадают
 * независимо от языка страницы.
 */
export default function ProductPageComponent() {
    Alpine.data('ProductPage', () => ({
        productId: 0,
        isVariable: false,
        cartQty: 1, // имя НЕ 'qty': иначе совпадёт с локальным qty виджета quantity.twig и x-model замкнётся на него
        axes: [],
        variations: [],
        selected: {},

        init() {
            let defaults = {};
            const dataEl = this.$el.querySelector('.us-variations-data');
            if (dataEl) {
                try {
                    const data = JSON.parse(dataEl.textContent);
                    this.productId = Number(data.product_id) || 0;
                    this.isVariable = !!data.is_variable;
                    this.axes = Array.isArray(data.axes) ? data.axes : [];
                    this.variations = Array.isArray(data.variations) ? data.variations : [];
                    defaults = data.defaults || {};
                } catch (e) {
                    /* битый JSON - оставляем пустым */
                }
            }
            // Предвыбор комбинации по умолчанию (если задана).
            this.axes.forEach((tax) => {
                this.selected[tax] = defaults[tax] != null ? Number(defaults[tax]) : null;
            });
        },

        // Можно ли добавить: у вариативного нужна полная (сматченная) комбинация.
        get canAddToCart() {
            return !this.isVariable || this.matched !== null;
        },

        // Добавить в корзину: вариативный - КОНКРЕТНЫЙ выбор по всем осям (не ключ
        // сматченной вариации, иначе на wildcard-оси терялся бы выбор пользователя).
        addToCart() {
            if (!this.canAddToCart) return;
            const attrs = this.isVariable ? { ...this.selected } : {};
            Alpine.store('cart').add(this.productId, attrs, this.cartQty);
        },

        /* Клик по значению оси: повторный клик снимает выбор. */
        select(taxonomy, id) {
            this.selected[taxonomy] = this.selected[taxonomy] === id ? null : id;
        },

        isSelected(taxonomy, id) {
            return this.selected[taxonomy] === id;
        },

        /* Совместима ли вариация с выбором (по заданным осям; '*' матчит любое). */
        matches(variation, selection) {
            return this.axes.every((tax) => {
                const sel = selection[tax];
                if (sel === null || sel === undefined) return true;
                const v = variation.attributes[tax];
                return v === '*' || Number(v) === Number(sel);
            });
        },

        /* Доступно ли значение оси с учётом выбора по другим осям (для гашения). */
        isAvailable(taxonomy, id) {
            const trial = Object.assign({}, this.selected, { [taxonomy]: id });
            return this.variations.some((v) => this.matches(v, trial));
        },

        get allSelected() {
            return this.axes.length > 0 && this.axes.every((tax) => this.selected[tax] !== null);
        },

        /* Сматченная вариация: самая специфичная (меньше wildcard), при равенстве - первая. */
        get matched() {
            if (!this.allSelected) return null;
            let best = null;
            let bestScore = -1;
            this.variations.forEach((v) => {
                if (!this.matches(v, this.selected)) return;
                const score = this.axes.reduce((acc, tax) => acc + (v.attributes[tax] === '*' ? 0 : 1), 0);
                if (score > bestScore) { best = v; bestScore = score; }
            });
            return best;
        },

        get minPrice() {
            const prices = this.variations.map((v) => v.price).filter((p) => p !== null && p !== undefined);
            return prices.length ? Math.min(...prices) : null;
        },

        get priceText() {
            const v = this.matched;
            if (v && v.price !== null && v.price !== undefined) return formatPrice(v.price);
            return this.minPrice !== null ? 'від ' + formatPrice(this.minPrice) : '';
        },

        get oldPriceText() {
            const v = this.matched;
            return v && v.old_price !== null && v.old_price !== undefined ? formatPrice(v.old_price) : '';
        },
    }));
}
