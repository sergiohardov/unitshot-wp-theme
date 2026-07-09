import {useRef, useState} from 'react'
import {apiGet, getPostId} from '../api.js'

const selectCls =
    "rounded-md border border-gray-300 bg-white pr-7 px-2.5 py-1.5 text-sm text-gray-900 " +
    "focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"

/**
 * Состав комплекта (только complete). Поиск товаров через REST; для вариативного
 * члена выбираем вариацию (её цена идёт в сумму). value: [{id,title,sku,is_variable,variations,variation,price}].
 */
export default function MetaboxSet({value = [], setValue, currency = ''}) {
    const fmt = (n) => (Number.isFinite(Number(n)) ? Math.round(Number(n)).toLocaleString('ru-RU') + (currency ? ' ' + currency : '') : '—')

    const [query, setQuery] = useState('')
    const [options, setOptions] = useState([])
    const [open, setOpen] = useState(false)
    const timer = useRef(null)

    const update = (i, patch) => setValue(value.map((it, idx) => (idx === i ? {...it, ...patch} : it)))
    const remove = (i) => setValue(value.filter((_, idx) => idx !== i))

    const search = (s) => {
        const have = new Set(value.map((it) => it.id))
        apiGet('/product/search', {s, post_id: getPostId()})
            .then((list) => setOptions((list || []).filter((p) => !have.has(p.id))))
            .catch(() => setOptions([]))
    }

    const onInput = (v) => {
        setQuery(v)
        clearTimeout(timer.current)
        timer.current = setTimeout(() => search(v), 250)
    }

    const addProduct = (p) => {
        const item = {
            id: p.id, title: p.title, sku: p.sku || '',
            is_variable: !!p.is_variable, variations: [], variation: '', price: p.price ?? null,
        }
        setValue([...value, item])
        setQuery('')
        setOptions([])

        if (p.is_variable) {
            apiGet('/product/variations', {post_id: p.id}).then((vs) => {
                const list = vs || []
                const first = list[0]
                setValue((cur) => cur.map((it) => it.id === p.id
                    ? {...it, variations: list, variation: first?.key ?? '', price: first?.price ?? null}
                    : it))
            }).catch(() => {})
        }
    }

    const onVariationChange = (i, key) => {
        const v = (value[i].variations || []).find((x) => x.key === key)
        update(i, {variation: key, price: v?.price ?? null})
    }

    const sum = value.reduce((s, it) => s + (Number(it.price) || 0), 0)

    return (
        <div className="flex flex-col gap-3">
            {value.length > 0 && (
                <div className="flex flex-col gap-2">
                    {value.map((it, i) => (
                        <div key={it.id} className="flex flex-wrap items-center gap-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
                            <span className="text-sm font-medium text-gray-900">{it.title}</span>

                            {it.is_variable && (
                                <select className={selectCls} value={it.variation}
                                        onChange={(e) => onVariationChange(i, e.target.value)}>
                                    {(it.variations || []).map((v) => <option key={v.key} value={v.key}>{v.label}</option>)}
                                </select>
                            )}

                            {it.sku && <span className="text-xs text-gray-500">SKU: {it.sku}</span>}

                            <span className="ml-auto text-sm text-gray-600">{fmt(it.price)}</span>
                            <button type="button" title="Удалить" onClick={() => remove(i)}
                                    className="flex h-6 w-6 items-center justify-center rounded text-base text-red-600 hover:bg-red-50">×</button>
                        </div>
                    ))}
                </div>
            )}

            <div className="relative">
                <input
                    className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                    placeholder="Поиск товара по названию…"
                    value={query}
                    onChange={(e) => onInput(e.target.value)}
                    onFocus={() => { setOpen(true); search(query) }}
                    onClick={() => { setOpen(true); search(query) }}
                    onBlur={() => setTimeout(() => setOpen(false), 150)}
                />
                {open && options.length > 0 && (
                    <div className="absolute inset-x-0 top-full z-10 mt-1 max-h-60 overflow-auto rounded-md border border-gray-200 bg-white shadow-lg">
                        {options.map((p) => (
                            <div key={p.id} className="cursor-pointer px-3 py-1.5 text-sm hover:bg-blue-50"
                                 onMouseDown={(e) => { e.preventDefault(); addProduct(p) }}>
                                {p.title}{p.sku ? ' · SKU: ' + p.sku : ''}{p.price != null ? ' — ' + fmt(p.price) : ''}
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <p className="text-sm text-gray-600">
                Сумма по отдельности (зачёркнутая цена набора): <strong className="text-gray-900">{fmt(sum)}</strong>
            </p>
        </div>
    )
}
