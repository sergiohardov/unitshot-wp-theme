import Alpine from "alpinejs";

/**
 * Страница оформления заказа.
 *
 * Состав заказа берётся из стора корзины ($store.cart) и отправляется на сервер
 * только как намерение - тело запроса содержит лишь контактные данные, корзину
 * сервер читает сам (Order::rest_create). На успех сервер очищает корзину и
 * возвращает её новое (пустое) состояние - применяем в стор.
 */
export default function CheckoutPage() {
    Alpine.data('CheckoutPage', () => ({
        customer: { phone: '', name: '', post: '', comment: '' },
        isSubmitting: false,
        isSend: false,
        error: '',

        async submitOrder() {
            // Защита от повторной отправки пока идёт запрос (не полагаемся на disabled).
            if (this.isSubmitting) return;

            this.error = '';

            if (!this.customer.name.trim() || !this.customer.phone.trim()) {
                this.error = "Будь ласка, заповніть ім'я та телефон";
                return;
            }
            if (Alpine.store('cart').isEmpty) {
                this.error = 'Кошик порожній';
                return;
            }

            const cfg = window.UNITSHOT || {};
            const endpoint = cfg.order && cfg.order.create;
            if (!endpoint) return;

            this.isSubmitting = true;
            try {
                const res = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': (cfg.cart && cfg.cart.nonce) || '',
                    },
                    body: JSON.stringify({ ...this.customer, lang: cfg.lang || '' }),
                });
                const data = res.ok ? await res.json() : {};

                if (data.ok) {
                    if (data.cart) Alpine.store('cart').apply(data.cart); // сервер очистил корзину
                    this.isSend = true;
                    this.customer = { phone: '', name: '', post: '', comment: '' };
                } else {
                    this.error = data.error === 'empty'
                        ? 'Кошик порожній'
                        : "Перевірте обов'язкові поля";
                }
            } catch (e) {
                this.error = 'Сталася помилка. Спробуйте пізніше.';
            } finally {
                this.isSubmitting = false;
            }
        },
    }));
}
