import Alpine from 'alpinejs'

export default function ModalCallComponent() {
    Alpine.data('ModalCallComponent', () => ({
        isSended: false,
        isLoading: false,
        errors: {}, // Храним ошибки полей
        formData: {
            phone: '',
            username: '',
            comment: ''
        },

        async submit() {
            if (this.isSended) {
                this.$store.ModalStore.close();
                return;
            }

            // Защита от повторной отправки пока идёт запрос (не полагаемся на disabled).
            if (this.isLoading) return;

            // Простая валидация
            this.errors = {};
            if (!this.formData.phone.trim()) this.errors.phone = true;
            if (!this.formData.username.trim()) this.errors.username = true;

            if (Object.keys(this.errors).length > 0) return;

            const cfg = window.UNITSHOT || {};
            const endpoint = cfg.callback && cfg.callback.create;
            if (!endpoint) return;

            this.isLoading = true;

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': (cfg.cart && cfg.cart.nonce) || '',
                    },
                    body: JSON.stringify(this.formData),
                });
                const data = response.ok ? await response.json() : {};

                if (data.ok) {
                    this.isSended = true;
                } else if (data.error === 'required') {
                    if (!this.formData.phone.trim()) this.errors.phone = true;
                    if (!this.formData.username.trim()) this.errors.username = true;
                }
            } catch (error) {
                console.error('Ошибка:', error);
            } finally {
                this.isLoading = false;
            }
        }
    }));
}
