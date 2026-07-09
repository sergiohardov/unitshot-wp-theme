const selectCls =
    "rounded-md border border-gray-300 bg-white pr-7 px-2.5 py-1.5 text-sm text-gray-900 " +
    "focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"

const inputCls =
    "w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 " +
    "focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"

/**
 * Свободные параметры товара: параметр (терм таксономии «Параметры») + текстовое
 * значение + флаг «основной». value: [{term, value, main}]. terms: [{id, name}].
 * Имена параметров переводятся в нативном экране таксономии; значение — общее.
 */
export default function MetaboxParameters({value = [], setValue, terms = []}) {
    const used = new Set(value.map((r) => Number(r.term)).filter(Boolean))

    const update = (i, patch) => setValue(value.map((r, idx) => (idx === i ? {...r, ...patch} : r)))
    const remove = (i) => setValue(value.filter((_, idx) => idx !== i))
    const add = () => setValue([...value, {term: '', value: '', main: false}])

    return (
        <div className="flex flex-col gap-3">
            {value.map((row, i) => (
                <div key={i} className="flex flex-wrap items-center gap-3 rounded-md border border-gray-200 bg-gray-50 p-3">
                    <select className={selectCls + " w-[200px]"} value={row.term || ''}
                            onChange={(e) => update(i, {term: e.target.value ? Number(e.target.value) : ''})}>
                        <option value="">— Параметр —</option>
                        {terms.map((t) => (
                            <option key={t.id} value={t.id} disabled={used.has(t.id) && Number(row.term) !== t.id}>{t.name}</option>
                        ))}
                    </select>

                    <input className={inputCls + " min-w-[200px] flex-1"} placeholder="Значение (напр. 2.5)"
                           value={row.value ?? ''} onChange={(e) => update(i, {value: e.target.value})}/>

                    <label className="flex items-center gap-2 text-xs text-gray-600">
                        <input type="checkbox" className="accent-blue-600" checked={!!row.main}
                               onChange={(e) => update(i, {main: e.target.checked})}/>
                        Основная характеристика
                    </label>

                    <button type="button" title="Удалить" onClick={() => remove(i)}
                            className="flex h-7 w-7 items-center justify-center rounded text-base text-red-600 hover:bg-red-50">×</button>
                </div>
            ))}

            <div>
                <button type="button" onClick={add}
                        className="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-blue-400 hover:text-blue-600">
                    Добавить параметр
                </button>
            </div>
        </div>
    )
}
