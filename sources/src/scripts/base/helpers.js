/**
 * Форматирование цены на клиенте - зеркало серверного Helpers::format_price().
 *
 * Базовая цена приходит в валюте по умолчанию; текущую валюту (множитель + код)
 * отдаёт PHP через wp_localize_script (UNITSHOT.currency, см. Assets::localize).
 * Свитчер валюты делает жёсткий редирект, так что UNITSHOT.currency всегда актуален.
 */
export function formatPrice(price) {
    const UNITSHOT = window.UNITSHOT;
    if(UNITSHOT === undefined) return price;

    const currency = UNITSHOT.currency || { value: 1, code: '', symbol: '' };

    const amount = Number(price);
    if (!Number.isFinite(amount)) return '';

    const converted = Math.round(amount * Number(currency.value || 1));
    const formatted = converted.toLocaleString('ru-RU'); // пробел-разделитель тысяч

    return currency.code ? `${formatted} ${currency.code}` : formatted;
}
