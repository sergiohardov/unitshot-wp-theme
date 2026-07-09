import {useState} from 'react'
import MetaboxBlock from '@components/MetaboxBlock.jsx'
import {apiPost} from '../api.js'

const inputCls =
    "w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 " +
    "transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"

const slugify = (v) => v.toLowerCase().replace(/[^a-z0-9_-]/g, '')

/** Страница регистрации атрибутов товаров: список + live-сохранение через REST. */
export default function ProductAttributes({dataJson}) {
    const options = dataJson.options ?? {}
    const hasPll = !!options.has_pll
    const slugMax = options.slug_max ?? 28
    const prefix = options.taxonomy_prefix ?? ''

    const [rows, setRows] = useState(dataJson.state?.attributes ?? [])
    const [saving, setSaving] = useState(false)
    const [message, setMessage] = useState(null) // {type:'success'|'error', text?, list?}

    const updateRow = (i, patch) => setRows(rows.map((r, idx) => (idx === i ? {...r, ...patch} : r)))
    const removeRow = (i) => setRows(rows.filter((_, idx) => idx !== i))
    const addRow = () => setRows([...rows, {name: '', slug: '', translatable: false, taxonomy: '', edit_link: '', is_new: true}])

    const save = () => {
        setSaving(true)
        setMessage(null)
        apiPost('/product-attributes/save', {
            attributes: rows.map((r) => ({name: r.name, slug: r.slug, translatable: !!r.translatable})),
        })
            .then((res) => {
                if (res.ok) {
                    setRows(res.attributes ?? [])
                    setMessage({type: 'success', text: 'Сохранено.'})
                } else {
                    setMessage({type: 'error', list: res.errors ?? ['Не удалось сохранить.']})
                }
            })
            .catch(() => setMessage({type: 'error', list: ['Ошибка сети.']}))
            .finally(() => setSaving(false))
    }

    return (
        <div className="us-attributes-app @container mt-2 max-w-[920px] text-[13px] text-gray-800">
            <MetaboxBlock title="Атрибуты товаров"
                          hint="Ярлык существующих атрибутов менять нельзя - от него зависит таксономия и привязанные значения. Удаление строки убирает атрибут (термы остаются в БД).">
                <div className="flex flex-col gap-3">
                    {rows.map((row, i) => (
                        <AttributeRow
                            key={i}
                            row={row}
                            prefix={prefix}
                            slugMax={slugMax}
                            hasPll={hasPll}
                            onChange={(patch) => updateRow(i, patch)}
                            onRemove={() => removeRow(i)}
                        />
                    ))}

                    {rows.length === 0 && <p className="text-sm text-gray-500">Атрибуты ещё не созданы.</p>}
                </div>

                {message && (
                    <div className={"rounded-md border px-3 py-2 text-sm " +
                        (message.type === 'success'
                            ? "border-green-200 bg-green-50 text-green-700"
                            : "border-red-200 bg-red-50 text-red-700")}>
                        {message.text
                            ? message.text
                            : <ul className="list-disc pl-4">{message.list.map((t, k) => <li key={k}>{t}</li>)}</ul>}
                    </div>
                )}

                <div className="flex items-center justify-between gap-3">
                    <button type="button" onClick={addRow}
                            className="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-blue-400 hover:text-blue-600">
                        Добавить атрибут
                    </button>

                    <button type="button" onClick={save} disabled={saving}
                            className="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:opacity-60">
                        {saving ? 'Сохранение…' : 'Сохранить'}
                    </button>
                </div>
            </MetaboxBlock>
        </div>
    )
}

/** Одна строка-атрибут: название, ярлык (slug у существующих заблокирован), флаг перевода. */
function AttributeRow({row, prefix, slugMax, hasPll, onChange, onRemove}) {
    return (
        <div className="flex flex-wrap items-start gap-3 rounded-md border border-gray-200 bg-gray-50 p-3">
            <div className="min-w-[160px] flex-1">
                <label className="mb-1 block text-[12px] font-semibold text-gray-700">Название</label>
                <input className={inputCls} value={row.name} placeholder="Цвет"
                       onChange={(e) => onChange({name: e.target.value})}/>
            </div>

            <div className="w-[210px]">
                <label className="mb-1 block text-[12px] font-semibold text-gray-700">Ярлык</label>
                <input
                    className={inputCls + (row.is_new ? '' : ' cursor-not-allowed bg-gray-100 text-gray-500')}
                    value={row.slug}
                    placeholder="color"
                    maxLength={slugMax}
                    readOnly={!row.is_new}
                    title={row.is_new ? '' : 'Ярлык существующего атрибута менять нельзя'}
                    onChange={(e) => onChange({slug: slugify(e.target.value)})}
                />
                <p className="mt-1 font-mono text-[11px] text-gray-400">{prefix}{row.slug || '{ярлык}'}</p>
            </div>

            <div className="ml-auto flex items-center gap-4 pt-7">
                {hasPll && (
                    <label className="flex items-center gap-2 text-xs text-gray-600">
                        <input type="checkbox" className="accent-blue-600" checked={!!row.translatable}
                               onChange={(e) => onChange({translatable: e.target.checked})}/>
                        Переводимый
                    </label>
                )}
                {!row.is_new && row.edit_link && (
                    <a href={row.edit_link} className="text-xs font-medium text-blue-600 hover:underline">Значения</a>
                )}
                <button type="button" title="Удалить" onClick={onRemove}
                        className="flex h-7 w-7 items-center justify-center rounded text-base text-red-600 hover:bg-red-50">×</button>
            </div>
        </div>
    )
}


