import { Fancybox } from "@fancyapps/ui";
import "@fancyapps/ui/dist/fancybox/fancybox.css";

/** Fancybox-тип слайда по типу отзыва (самохостинг-видео ждёт "html5video"). */
const fancyboxType = (reviewType) => (reviewType === 'video' ? 'html5video' : 'image');

/** Экранирование для вставки в HTML подписи Fancybox. */
const escapeHtml = (value) =>
    String(value).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

/** HTML подписи под отзывом: имя + должность из карточки слайда. */
const buildCaption = (name, position) => {
    let html = '';
    if (name) html += `<span class="us-review-caption__name">${escapeHtml(name)}</span>`;
    if (position) html += `<span class="us-review-caption__position">${escapeHtml(position)}</span>`;

    return html ? `<div class="us-review-caption">${html}</div>` : '';
};

/**
 * Собирает галерею всех отзывов слайдера (в порядке data-review-index).
 *
 * Дедуп через Map по индексу нужен, потому что Swiper с loop клонирует слайды,
 * а у каждого слайда два триггера (картинка и имя) - все они несут одинаковый
 * data-review-index.
 */
const collectGallery = (root) => {
    const byIndex = new Map();

    root.querySelectorAll('[data-review-src][data-review-index]').forEach((el) => {
        const index = Number(el.dataset.reviewIndex);
        if (byIndex.has(index)) return;

        byIndex.set(index, {
            src: el.dataset.reviewSrc,
            type: fancyboxType(el.dataset.reviewType),
            caption: buildCaption(el.dataset.reviewName, el.dataset.reviewPosition),
        });
    });

    return [...byIndex.entries()].sort((a, b) => a[0] - b[0]).map(([, item]) => item);
};

/**
 * Открытие отзыва в Fancybox по клику на изображение слайда или его заголовок.
 * Открывается галерея всех отзывов этого слайдера со стартом на кликнутом -
 * внутри Fancybox можно листать на следующий/предыдущий отзыв.
 *
 * Делегированный листенер (а не Alpine @click) намеренно: Swiper с loop
 * клонирует слайды уже после инициализации Alpine, поэтому директивы на клонах
 * не навешиваются - всплытие клика ловим на document и читаем data-атрибуты с
 * кликнутого (возможно, клонированного) элемента.
 */
export default function SliderReviews() {
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('.us-slider-reviews [data-review-src]');
        if (!trigger) return;

        e.preventDefault();

        const gallery = collectGallery(trigger.closest('.us-slider-reviews'));
        const startIndex = Number(trigger.dataset.reviewIndex) || 0;

        Fancybox.show(gallery, {
            startIndex,
            Carousel: {
                Thumbs: false,
            },
        });
    });
}
