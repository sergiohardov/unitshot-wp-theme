/**
 * Тонкий клиент к REST темы (поиск термов/товаров/вариаций - GET; сохранения - POST).
 * Конфиг (restUrl + nonce) приходит из PHP-пейлоада, ставится один раз в entry.
 */
let cfg = {restUrl: '', nonce: '', postId: 0}

export function configureApi(next) {
    cfg = {...cfg, ...(next || {})}
}

export function getPostId() {
    return cfg.postId
}

export function apiGet(path, params = {}) {
    const qs = new URLSearchParams(
        Object.fromEntries(Object.entries(params).filter(([, v]) => v !== undefined && v !== null))
    ).toString()

    const url = cfg.restUrl + path + (qs ? '?' + qs : '')

    return fetch(url, {
        credentials: 'same-origin',
        headers: {'X-WP-Nonce': cfg.nonce},
    }).then((r) => {
        if (!r.ok) throw new Error('REST ' + r.status)
        return r.json()
    })
}

export function apiPost(path, body = {}) {
    return fetch(cfg.restUrl + path, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'X-WP-Nonce': cfg.nonce, 'Content-Type': 'application/json'},
        body: JSON.stringify(body),
    }).then((r) => {
        if (!r.ok) throw new Error('REST ' + r.status)
        return r.json()
    })
}
