import {createRoot} from 'react-dom/client'
import ProductAttributes from '@components/ProductAttributes.jsx'
import {configureApi} from './../api.js'
import '../index.css'

/**
 * Точка входа React-острова страницы атрибутов товаров.
 *
 * PHP выводит:
 *   <div id="us-attributes-app"></div>
 *   <script type="application/json" id="us-attributes-data">{...}</script>
 */
const mount = document.getElementById('us-attributes-app')
const dataEl = document.getElementById('us-attributes-data')

if (mount && dataEl) {
    try {
        const dataJson = JSON.parse(dataEl.textContent)
        configureApi(dataJson.config)
        createRoot(mount).render(<ProductAttributes dataJson={dataJson}/>)
    } catch (e) {
        console.error('[us-attributes] невалидный JSON начальных данных', e)
    }
}
