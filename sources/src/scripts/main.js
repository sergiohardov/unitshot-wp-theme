import '@styles/main.scss'

import Alpine from 'alpinejs'
import { formatPrice } from "@scripts/base/helpers.js";
import ModalStore from "@scripts/stores/modal.js";
import CartStore from "@scripts/stores/cart.js";
import ProductSetComponent from "@scripts/components/product-set.js";
import ProductPageComponent from "@scripts/components/product-page.js";
import ProductGalleryComponent from "@scripts/components/product-gallery.js";
import RangeComponent from "@scripts/components/range.js";
import HeaderPart from "@scripts/parts/header.js";
import CheckoutPage from "@scripts/pages/checkout.js";
import ModalCallComponent from "@scripts/components/modal-call.js";
import ModalLangComponent from "@scripts/components/modal-lang.js";
import SliderLayout from "@scripts/layouts/slider.js";
import SliderReviews from "@scripts/blocks/slider-reviews.js";
import ContactPage from "@scripts/pages/contact.js";

window.Alpine = Alpine

// Магия $formatPrice - чтобы Alpine-выражения в шаблонах (напр. x-text в корзине)
// форматировали цены той же логикой, что и сервер (валюта из UNITSHOT.currency),
// без записи функции в глобальный window.
Alpine.magic('formatPrice', () => formatPrice)

/* Stores */
ModalStore()
CartStore()

/* Layouts */
SliderLayout()

/* Components */
ProductSetComponent()
ProductPageComponent()
ProductGalleryComponent()
RangeComponent()
ModalCallComponent();
ModalLangComponent();

/* Blocks */
SliderReviews()

/* Parts */
HeaderPart()

/* Pages */
CheckoutPage()
ContactPage()

Alpine.start()
