import Swiper from "swiper";
import {Navigation, Pagination} from "swiper/modules";
import "swiper/css";

export default function SliderLayout() {
    Alpine.data('SliderLayout', (customOptions = {}) => ({
        init() {
            // Ищем элементы управления строго внутри текущего слайдера
            const sliderEl = this.$el.querySelector('.swiper');
            const prevEl = this.$el.querySelector('.us-slider__prev');
            const nextEl = this.$el.querySelector('.us-slider__next');
            const paginationEl = this.$el.querySelector('.us-slider__pagination');

            // Базовые настройки, которые есть у всех слайдеров
            const defaultOptions = {
                modules: [Navigation, Pagination],
                slidesPerView: 2,
                spaceBetween: 20,
                loop: false,
                pagination: {
                    el: paginationEl,
                    clickable: true,
                    bulletClass: 'us-slider__pagination-bullet',
                    bulletActiveClass: 'us-slider__pagination-bullet--active',
                    lockClass: 'us-slider__pagination--lock',
                },
                navigation: {
                    nextEl: nextEl,
                    prevEl: prevEl,
                    lockClass: 'us-slider__navigation--lock',
                }
            };

            // Сливаем базовые настройки с теми, что пришли из компонента
            const finalOptions = {...defaultOptions, ...customOptions};

            // Инициализируем Swiper
            new Swiper(sliderEl, finalOptions);
        }
    }));
}
