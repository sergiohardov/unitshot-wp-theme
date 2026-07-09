import Alpine from 'alpinejs'
import Swiper from "swiper";
import {FreeMode, Navigation, Thumbs, Pagination} from "swiper/modules";
import {Fancybox} from "@fancyapps/ui";
import "swiper/css";
import 'swiper/css/free-mode';
import 'swiper/css/thumbs';
import "@fancyapps/ui/dist/fancybox/fancybox.css";

export default function ProductGalleryComponent() {
    Alpine.data('ProductGalleryComponent', () => ({
        init() {
            const thumbsEl      = this.$el.querySelector('.us-product-gallery__thumbs');
            const mainEl        = this.$el.querySelector('.us-product-gallery__main');
            const nextBtn       = this.$el.querySelector('.us-product-gallery__main__next');
            const prevBtn       = this.$el.querySelector('.us-product-gallery__main__prev');
            const paginationEl  = this.$el.querySelector('.us-product-gallery__pagination');

            // Список слайдов для лайтбокса собираем до инициализации Swiper -
            // иначе loop добавит клоны и в галерее появятся дубли.
            const lightboxSlides = [...mainEl.querySelectorAll('.us-product-gallery__main__slide__img')]
                .map((img) => ({ src: img.src, type: 'image', thumb: img.src, caption: img.alt || '' }));

            const thumbsSlider = new Swiper(thumbsEl, {
                loop: true,
                spaceBetween: 20,
                slidesPerView: 4,
                freeMode: true,
                watchSlidesProgress: true,
            });

            const mainSlider = new Swiper(mainEl, {
                modules: [FreeMode, Navigation, Thumbs, Pagination],
                loop: true,
                spaceBetween: 20,
                navigation: {
                    nextEl: nextBtn,
                    prevEl: prevBtn,
                },
                pagination: {
                    el: paginationEl,
                    clickable: true,
                    bulletClass: 'us-product-gallery__pagination-bullet',
                    bulletActiveClass: 'us-product-gallery__pagination-bullet--active',
                    lockClass: 'us-product-gallery__pagination--lock',
                },
                thumbs: {
                    swiper: thumbsSlider,
                },
            });

            // Клик по картинке основного слайдера открывает Fancybox с текущего
            // слайда. Стрелки навигации игнорируем (они вне .__slide), а Swiper
            // сам гасит клик после свайпа (preventClicks).
            mainEl.addEventListener('click', (e) => {
                if (!e.target.closest('.us-product-gallery__main__slide')) return;

                Fancybox.show(lightboxSlides, {
                    startIndex: mainSlider.realIndex,
                    Carousel: {
                        Thumbs: false,
                    },
                });
            });
        }
    }));
}
