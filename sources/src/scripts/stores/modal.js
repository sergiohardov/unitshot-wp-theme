import Alpine from "alpinejs";

export default function ModalStore() {
    Alpine.store('ModalStore', {
        active: null, // ID открытого окна (например, 'us-modal-call')

        open(id) {
            this.active = id;
            document.body.classList.add('scroll-lock');
        },

        close() {
            this.active = null;
            document.body.classList.remove('scroll-lock');
        }
    });
}
