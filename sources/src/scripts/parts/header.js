import Alpine from 'alpinejs'

export default function HeaderPart() {
    Alpine.data('HeaderPart', () => ({
        isMenuOpen: false,
        isSearchOpen: false,

        searchQuery: '',     // Для поиска (> 3 символов)
        searchResults: [],   // Результаты живого поиска (REST)
        searchLoading: false,
        _searchTimer: null,  // debounce
        _searchSeq: 0,       // защита от гонки ответов

        activeMenuIndex: 0,  // Активный пункт меню (по дефолту первый)

        // оверлей, если открыто хоть что-то
        get isOverlayActive() {
            return this.isMenuOpen || this.isSearchOpen;
        },

        get searchConfig() {
            return (window.UNITSHOT && window.UNITSHOT.search) || {};
        },

        // Достаточно ли символов для поиска (порог из локализации, дефолт 3).
        get canSearch() {
            return this.searchQuery.trim().length > (this.searchConfig.min ?? 3);
        },

        // Ссылка на страницу полной выдачи (?s=...).
        get searchMoreUrl() {
            return (this.searchConfig.page || '/') + '?s=' + encodeURIComponent(this.searchQuery.trim());
        },

        init() {
            // следим за геттером isOverlayActive, rак только он меняется, блокируем/разблокируем скролл у body
            this.$watch('isOverlayActive', (value) => {
                document.body.classList.toggle('scroll-lock', value);
            });

            // Живой поиск: дебаунсим ввод, чистим результаты пока символов мало.
            this.$watch('searchQuery', () => {
                clearTimeout(this._searchTimer);

                if (!this.canSearch) {
                    this.searchResults = [];
                    this.searchLoading = false;
                    return;
                }

                this._searchTimer = setTimeout(() => this.runSearch(), 300);
            });
        },

        async runSearch() {
            const { endpoint } = this.searchConfig;
            const lang = (window.UNITSHOT && window.UNITSHOT.lang) || '';
            if (!endpoint) return;

            const seq = ++this._searchSeq;
            this.searchLoading = true;

            const url = endpoint
                + '?s=' + encodeURIComponent(this.searchQuery.trim())
                + (lang ? '&lang=' + encodeURIComponent(lang) : '');

            try {
                const res  = await fetch(url, { headers: { Accept: 'application/json' } });
                const data = res.ok ? await res.json() : [];

                if (seq !== this._searchSeq) return; // устаревший ответ - игнорируем
                this.searchResults = Array.isArray(data) ? data : [];
            } catch (e) {
                if (seq === this._searchSeq) this.searchResults = [];
            } finally {
                if (seq === this._searchSeq) this.searchLoading = false;
            }
        },

        // Переход на страницу полной выдачи (Enter / клик по лупе).
        submitSearch() {
            if (this.canSearch) window.location.href = this.searchMoreUrl;
        },

        closeAll() {
            this.isMenuOpen = false;
            this.isSearchOpen = false;
        },

        toggleMenu() {
            this.isMenuOpen = !this.isMenuOpen;

            if (this.isMenuOpen) {
                this.isSearchOpen = false;
            }
        },

        toggleSearch() {
            this.isSearchOpen = !this.isSearchOpen;

            if (this.isSearchOpen) {
                this.isMenuOpen = false;
            }
        },

        // Логика меню (Ховер на десктопе, Клик по стрелке на мобилке)
        hoverMenu(index) {
            if (window.innerWidth >= 768) {
                this.activeMenuIndex = index;
            }
        },

        clickMenuArrow(index, event) {
            if (window.innerWidth < 768) {
                event.preventDefault();
                event.stopPropagation();
                // Если кликаем по уже активному — закрываем его, иначе открываем новый
                this.activeMenuIndex = this.activeMenuIndex === index ? null : index;
            }
        }
    }));
}
