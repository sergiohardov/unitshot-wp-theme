const inputCls =
    "w-full rounded-md border border-gray-300 bg-white pr-7 px-2.5 py-1.5 text-sm text-gray-900 " +
    "focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"

const MAX_GENERATED = 200

/** Подпись комбинации (для дедупа при генерации). */
const signature = (axes, attrs) =>
    axes.map((ax) => ax.taxonomy + '=' + (attrs?.[ax.taxonomy] ?? '')).join('|')

/**
 * Вариации (только variable). Оси берём из назначенных атрибутов с галочкой
 * «в вариациях». Можно сгенерировать все комбинации или добавлять вручную.
 */
export default function MetaboxVariations({attributes = [], variations = [], setVariations, defaults = {}, setDefaults, taxChoices = {}, wildcard = '*', currency = ''}) {
    const cur = currency ? ', ' + currency : ''
    const axes = attributes
        .filter((a) => a.in_variations && a.taxonomy && a.terms?.length)
        .map((a) => ({taxonomy: a.taxonomy, label: taxChoices[a.taxonomy] || a.taxonomy, terms: a.terms}))

    if (!axes.length) {
        return <p className="text-sm text-gray-500"><em>Сначала добавьте атрибуты с галочкой «Учитывать в вариациях».</em></p>
    }

    const updateVar = (i, patch) => setVariations(variations.map((v, idx) => (idx === i ? {...v, ...patch} : v)))
    const setAxis = (i, tax, val) => updateVar(i, {attributes: {...variations[i].attributes, [tax]: val}})
    const removeVar = (i) => setVariations(variations.filter((_, idx) => idx !== i))
    const addVar = () => setVariations([...variations, {key: '', attributes: {}, price: '', sale_price: ''}])

    const generate = () => {
        const total = axes.reduce((n, ax) => n * Math.max(1, ax.terms.length), 1)
        if (total > MAX_GENERATED) {
            window.alert('Слишком много комбинаций (' + total + '). Уменьшите число значений.')
            return
        }
        let combos = [{}]
        axes.forEach((ax) => {
            const next = []
            combos.forEach((c) => ax.terms.forEach((t) => next.push({...c, [ax.taxonomy]: t.canonical ?? t.id})))
            combos = next
        })
        const have = new Set(variations.map((v) => signature(axes, v.attributes)))
        const added = combos
            .filter((c) => !have.has(signature(axes, c)))
            .map((c) => ({key: '', attributes: c, price: '', sale_price: ''}))
        if (added.length) setVariations([...variations, ...added])
    }

    return (
        <div className="flex flex-col gap-4">
            {/* Комбинация по умолчанию */}
            <div className="rounded-md border border-gray-200 bg-gray-50 p-3">
                <p className="mb-2 text-[13px] font-semibold text-gray-800">Комбинация по умолчанию</p>
                <div className="flex flex-wrap gap-3">
                    {axes.map((ax) => (
                        <label key={ax.taxonomy} className="flex flex-col gap-1">
                            <span className="text-[11px] uppercase tracking-wide text-gray-500">{ax.label}</span>
                            <select className={inputCls + " min-w-[150px]"} value={defaults?.[ax.taxonomy] ?? ''}
                                    onChange={(e) => setDefaults({...defaults, [ax.taxonomy]: e.target.value ? Number(e.target.value) : undefined})}>
                                <option value="">— не задано —</option>
                                {ax.terms.map((t) => <option key={t.id} value={t.canonical ?? t.id}>{t.name}</option>)}
                            </select>
                        </label>
                    ))}
                </div>
            </div>

            {/* Строки вариаций */}
            <div className="flex flex-col gap-2">
                {variations.map((v, i) => (
                    <div key={i} className="flex flex-wrap items-end gap-3 rounded-md border border-gray-200 bg-white p-3">
                        <div className="flex flex-1 flex-wrap gap-3">
                            {axes.map((ax) => (
                                <label key={ax.taxonomy} className="flex flex-col gap-1">
                                    <span className="text-[11px] uppercase tracking-wide text-gray-500">{ax.label}</span>
                                    <select className={inputCls + " min-w-[140px]"}
                                            value={v.attributes?.[ax.taxonomy] ?? ''}
                                            onChange={(e) => setAxis(i, ax.taxonomy, e.target.value === wildcard ? wildcard : (e.target.value ? Number(e.target.value) : ''))}>
                                        <option value="">— выберите —</option>
                                        {ax.terms.map((t) => <option key={t.id} value={t.canonical ?? t.id}>{t.name}</option>)}
                                        <option value={wildcard}>Любое</option>
                                    </select>
                                </label>
                            ))}
                        </div>

                        <label className="flex flex-col gap-1">
                            <span className="text-[11px] uppercase tracking-wide text-gray-500">Цена{cur}</span>
                            <input className={inputCls + " w-[110px]"} type="number" value={v.price ?? ''}
                                   onChange={(e) => updateVar(i, {price: e.target.value})}/>
                        </label>
                        <label className="flex flex-col gap-1">
                            <span className="text-[11px] uppercase tracking-wide text-gray-500">Скидка{cur}</span>
                            <input className={inputCls + " w-[110px]"} type="number" value={v.sale_price ?? ''}
                                   onChange={(e) => updateVar(i, {sale_price: e.target.value})}/>
                        </label>

                        <button type="button" title="Удалить" onClick={() => removeVar(i)}
                                className="flex h-8 w-8 items-center justify-center rounded text-base text-red-600 hover:bg-red-50">×</button>
                    </div>
                ))}
            </div>

            <div className="flex gap-2">
                <button type="button" onClick={addVar}
                        className="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:border-blue-400 hover:text-blue-600">
                    Добавить вариацию
                </button>
                <button type="button" onClick={generate}
                        className="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:border-blue-400 hover:text-blue-600">
                    Сгенерировать комбинации
                </button>
            </div>
        </div>
    )
}
