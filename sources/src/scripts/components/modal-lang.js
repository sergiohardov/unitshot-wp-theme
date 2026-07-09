import Alpine from "alpinejs";

/**
 * Модалка предложения украинской версии (RU-посетителю при первом визите).
 *
 * Данные считает сервер (lang_suggest) и кладёт в data-атрибуты корня модалки
 * (см. modal-lang.twig): data-suggest-show и data-suggest-url. На init, если
 * показывать - открывает модалку и ставит cookie «больше не показывать».
 */
export default function ModalLangComponent() {
    Alpine.data('ModalLangComponent', () => ({
        init() {
            if (this.$el.dataset.suggestShow !== '1') return;

            this.$store.ModalStore.open('us-modal-lang');
            document.cookie = 'us_lang_suggest=1;path=/;max-age=31536000';
        },

        accept() {
            window.location.href = this.$el.dataset.suggestUrl || '/';
        },

        decline() {
            this.$store.ModalStore.close();
        },
    }));
}
