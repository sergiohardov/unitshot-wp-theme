import {useRef, useState} from 'react'
import {apiGet, getPostId} from '../api.js'

/**
 * Простой пикер товаров (поиск через REST /product/search).
 * value: [{id, title, sku}]. Используется, напр., для «похожих товаров».
 */
export default function MetaboxProducts({value = [], setValue, placeholder = 'Поиск товара по названию…'}) {
    const [query, setQuery] = useState('')
    const [options, setOptions] = useState([])
    const [open, setOpen] = useState(false)
    const timer = useRef(null)

    const remove = (id) => setValue(value.filter((it) => it.id !== id))

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

    const add = (p) => {
        setValue([...value, {id: p.id, title: p.title, sku: p.sku || ''}])
        setQuery('')
        setOptions([])
    }

    return (
        <div className="flex flex-col gap-3">
            {value.length > 0 && (
                <div className="flex flex-col gap-2">
                    {value.map((it) => (
                        <div key={it.id} className="flex flex-wrap items-center gap-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
                            <span className="text-sm font-medium text-gray-900">{it.title}</span>
                            {it.sku && <span className="text-xs text-gray-500">SKU: {it.sku}</span>}
                            <button type="button" title="Удалить" onClick={() => remove(it.id)}
                                    className="ml-auto flex h-6 w-6 items-center justify-center rounded text-base text-red-600 hover:bg-red-50">×</button>
                        </div>
                    ))}
                </div>
            )}

            <div className="relative">
                <input
                    className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                    placeholder={placeholder}
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
                                 onMouseDown={(e) => { e.preventDefault(); add(p) }}>
                                {p.title}{p.sku ? ' · SKU: ' + p.sku : ''}
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    )
}
