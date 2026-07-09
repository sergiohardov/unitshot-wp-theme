/** @type {import('tailwindcss').Config} */
export default {
  // !important на утилитах: wp-admin грузит стили БЕЗ CSS-слоёв, а unlayered
  // всегда бьёт layered (@layer utilities). important переворачивает приоритет,
  // и наши классы выигрывают у форм-стилей админки внутри острова.
  important: true,
}
