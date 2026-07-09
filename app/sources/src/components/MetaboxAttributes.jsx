import {useRef, useState} from 'react'
import {apiGet, getPostId} from '../api.js'

const selectCls =
    "rounded-md border border-gray-300 bg-white pr-7 px-3 py-2 text-sm text-gray-900 " +
    "focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"

/** Поле выбора термов: чипы + поиск через REST. */
function TermsField({taxonomy, terms, onChange}) {
    const [query, setQuery] = useState('')
    const [options, setOptions] = useState([])
    const [open, setOpen] = useState(false)
    const timer = useRef(null)

    const search = (s) => {
        if (!taxonomy) return setOptions([])
        const selected = new Set(terms.map((t) => t.id))
        apiGet('/product/attribute-terms', {taxonomy, s, post_id: getPostId()})
            .then((list) => setOptions((list || []).filter((t) => !selected.has(t.id))))
            .catch(() => setOptions([]))
    }

    const onInput = (v) => {
        setQuery(v)
        clearTimeout(timer.current)
        timer.current = setTimeout(() => search(v), 250)
    }

    const add = (t) => {
        onChange([...terms, {id: t.id, canonical: t.canonical, name: t.name}])
        setQuery('')
        setOptions([])
    }

    return (
        <div className="relative">
            <div className="flex flex-wrap items-center gap-1.5 rounded-md border border-gray-300 bg-white px-2 py-1.5">
                {terms.map((t) => (
                    <span key={t.id} className="inline-flex items-center gap-1 rounded-full border border-blue-200 bg-blue-50 px-2 py-0.5 text-xs text-blue-800">
                        {t.name}
                        <button type="button" className="text-blue-800/60 hover:text-red-600" onClick={() => onChange(terms.filter((x) => x.id !== t.id))}>×</button>
                    </span>
                ))}
                <input
                    className="min-w-[90px] flex-1 border-0 p-0 text-sm focus:ring-0"
                    placeholder={taxonomy ? 'Поиск значений…' : 'Сначала выберите атрибут'}
                    disabled={!taxonomy}
                    value={query}
                    onChange={(e) => onInput(e.target.value)}
                    onFocus={() => { setOpen(true); search(query) }}
                    onClick={() => { setOpen(true); search(query) }}
                    onBlur={() => setTimeout(() => setOpen(false), 150)}
                />
            </div>

            {open && options.length > 0 && (
                <div className="absolute inset-x-0 top-full z-10 mt-1 max-h-56 overflow-auto rounded-md border border-gray-200 bg-white shadow-lg">
                    {options.map((t) => (
                        <div key={t.id} className="cursor-pointer px-3 py-1.5 text-sm hover:bg-blue-50"
                             onMouseDown={(e) => { e.preventDefault(); add(t) }}>
                            {t.name}
                        </div>
                    ))}
                </div>
            )}
        </div>
    )
}

/** Назначение атрибутов товара: таксономия + термы + флаги (в вариациях / основная). */
export default function MetaboxAttributes({value = [], setValue, taxChoices = {}}) {
    const used = new Set(value.map((r) => r.taxonomy).filter(Boolean))
    const updateRow = (i, patch) => setValue(value.map((r, idx) => (idx === i ? {...r, ...patch} : r)))
    const removeRow = (i) => setValue(value.filter((_, idx) => idx !== i))
    const add = () => setValue([...value, {taxonomy: '', terms: [], in_variations: true, main: false}])

    return (
        <div className="flex flex-col gap-3">
            {value.map((row, i) => (
                <div key={i} className="flex flex-wrap items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-3">
                    <select className={selectCls + " w-[200px]"} value={row.taxonomy}
                            onChange={(e) => updateRow(i, {taxonomy: e.target.value, terms: []})}>
                        <option value="">— Атрибут —</option>
                        {Object.entries(taxChoices).map(([tax, label]) => (
                            <option key={tax} value={tax} disabled={used.has(tax) && tax !== row.taxonomy}>{label}</option>
                        ))}
                    </select>

                    <div className="min-w-[220px] flex-1">
                        <TermsField taxonomy={row.taxonomy} terms={row.terms} onChange={(terms) => updateRow(i, {terms})}/>
                    </div>

                    <div className="flex flex-col gap-1.5 pt-0.5">
                        <label className="flex items-center gap-2 text-xs text-gray-600">
                            <input type="checkbox" className="accent-blue-600" checked={row.in_variations}
                                   onChange={(e) => updateRow(i, {in_variations: e.target.checked})}/>
                            Учитывать в вариациях
                        </label>
                        <label className="flex items-center gap-2 text-xs text-gray-600">
                            <input type="checkbox" className="accent-blue-600" checked={row.main}
                                   onChange={(e) => updateRow(i, {main: e.target.checked})}/>
                            Основная характеристика
                        </label>
                    </div>

                    <button type="button" title="Удалить" onClick={() => removeRow(i)}
                            className="ml-auto flex h-7 w-7 items-center justify-center rounded text-base text-red-600 hover:bg-red-50">×</button>
                </div>
            ))}

            <div>
                <button type="button" onClick={add}
                        className="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-blue-400 hover:text-blue-600">
                    Добавить атрибут
                </button>
            </div>
        </div>
    )
}
