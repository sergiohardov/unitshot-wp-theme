import Alpine from "alpinejs";
import Swiper from "swiper";
import {Navigation} from "swiper/modules";
import {formatPrice} from "@scripts/base/helpers.js";
import "swiper/css";

/**
 * Слайдер наборов на странице товара-члена. Один слайд = один набор.
 * Снизу показываем цену активного набора (фиксированную) и ссылку на его страницу;
 * никакого суммирования - цена комплекта берётся из data-атрибутов активного слайда.
 */
export default function ProductSetComponent() {
    Alpine.data('ProductSetComponent', () => ({
        price: 0,
        old: 0,
        url: '#',

        init() {
            const slider = this.$el.querySelector('.us-product-set__slider');
            if (!slider) return;

            new Swiper(slider, {
                modules: [Navigation],
                loop: true,
                slidesPerView: 1,
                spaceBetween: 0,
                navigation: {
                    nextEl: this.$el.querySelector('.us-product-set__slider__next'),
                    prevEl: this.$el.querySelector('.us-product-set__slider__prev'),
                },
                on: {
                    init: (swiper) => this.syncFromSlide(swiper),
                    slideChange: (swiper) => this.syncFromSlide(swiper),
                },
            });
        },

        // Цена/ссылка активного набора из data-атрибутов слайда.
        syncFromSlide(swiper) {
            const slide = swiper.slides[swiper.activeIndex];
            if (!slide) return;

            this.price = Number(slide.getAttribute('data-us-set-price')) || 0;
            this.old = Number(slide.getAttribute('data-us-set-old')) || 0;
            this.url = slide.getAttribute('data-us-set-url') || '#';
        },

        get priceText() {
            return formatPrice(this.price);
        },

        // Зачёркнутая (сумма товаров по отдельности) - только если она выгоднее.
        get oldPriceText() {
            return this.old > this.price ? formatPrice(this.old) : '';
        },
    }));
}
