import {createRoot} from 'react-dom/client'
import ProductMetabox from '@components/ProductMetabox.jsx'
import {configureApi} from './../api.js'
import '../index.css'

/**
 * Точка входа React-острова метабокса товара.
 *
 * PHP выводит:
 *   <div id="us-product-app"></div>
 *   <script type="application/json" id="us-product-data">{...}</script>
 *
 * Начальные данные читаем из JSON-тега (позже можно заменить на wp_localize_script).
 */
const mount = document.getElementById('us-product-app')
const dataEl = document.getElementById('us-product-data')

if (mount && dataEl) {
    try {
        const dataJson = JSON.parse(dataEl.textContent)
        configureApi(dataJson.config)
        createRoot(mount).render(<ProductMetabox dataJson={dataJson}/>)
    } catch (e) {
        console.error('[us-product] невалидный JSON начальных данных', e)
    }
}



