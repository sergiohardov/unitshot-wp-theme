import Alpine from "alpinejs";

/**
 * Стор корзины. Состояние приходит с сервера (UNITSHOT.cart.state) и
 * пересобирается из ответа на каждую мутацию - сервер единственный источник
 * правды (валидирует товар/вариацию, хранит httponly-куку). Клиент только шлёт
 * намерение и показывает то, что вернул сервер.
 */
export default function CartStore() {
    Alpine.store('cart', {
        items: [],
        count: 0,
        total: null,
        loading: false,

        init() {
            this.apply(this.cfg.state || {});
        },

        get cfg() {
            return (window.UNITSHOT && window.UNITSHOT.cart) || {};
        },

        get isEmpty() {
            return this.items.length === 0;
        },

        // Применить состояние, полученное с сервера.
        apply(state) {
            this.items = Array.isArray(state.items) ? state.items : [];
            this.count = Number(state.count || 0);
            this.total = state.total ?? null;
        },

        async post(endpoint, body) {
            if (!endpoint) return;
            this.loading = true;

            try {
                const res = await fetch(endpoint, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-WP-Nonce": this.cfg.nonce || "",
                    },
                    body: JSON.stringify({
                        ...body,
                        lang: (window.UNITSHOT && window.UNITSHOT.lang) || "",
                    }),
                });
                if (res.ok) this.apply(await res.json());
            } catch (e) {
                /* сетевая ошибка - молча игнорируем, состояние не трогаем */
            } finally {
                this.loading = false;
            }
        },

        // Добавить товар (id текущего языка). attrs - конкретная комбинация
        // { taxonomy: term_id } (пусто для простых/наборов).
        async add(productId, attrs, qty) {
            await this.post(this.cfg.add, {
                product_id: productId,
                attrs: attrs && typeof attrs === "object" ? attrs : {},
                qty: qty || 1,
            });
            Alpine.store("ModalStore").open("us-modal-cart");
        },

        // Изменить количество позиции (canonical id + ключ вариации).
        async setQty(canonical, variation, qty) {
            await this.post(this.cfg.update, { id: canonical, variation: variation || "", qty: Number(qty) || 0 });
        },

        async remove(canonical, variation) {
            await this.post(this.cfg.remove, { id: canonical, variation: variation || "" });
        },
    });
}
