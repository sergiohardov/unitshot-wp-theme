/**
 * Файлы товара (PDF и пр.). value: [{id, name}]. Выбор через wp.media (любой тип).
 * Порядок не важен - простой список. На сабмите уходит как часть state.
 */
function MetaboxFiles({value = [], setValue}) {
    const remove = (id) => setValue(value.filter((f) => f.id !== id))

    const openMedia = () => {
        if (!window.wp || !window.wp.media) return

        // Без library.type — любые файлы (pdf, doc, …).
        const frame = window.wp.media({
            title: 'Файлы товара',
            multiple: true,
            button: {text: 'Добавить'},
        })

        frame.on('select', () => {
            const existing = new Set(value.map((f) => f.id))
            const added = []
            frame.state().get('selection').each((att) => {
                const a = att.toJSON()
                if (existing.has(a.id)) return
                added.push({id: a.id, name: a.title || a.filename || String(a.id)})
            })
            if (added.length) setValue([...value, ...added])
        })

        frame.open()
    }

    return (
        <div className="flex flex-col gap-3">
            {value.length > 0 && (
                <div className="flex flex-col gap-1.5">
                    {value.map((f) => (
                        <div
                            key={f.id}
                            className="flex items-center gap-2.5 rounded-md border border-gray-200 bg-gray-50 px-3 py-2"
                        >
                            <span aria-hidden="true">📄</span>
                            <span className="min-w-0 flex-1 truncate text-sm text-gray-800">{f.name}</span>
                            <button
                                type="button"
                                title="Удалить"
                                onClick={() => remove(f.id)}
                                className="flex h-6 w-6 items-center justify-center rounded text-base leading-none text-red-600 transition-colors hover:bg-red-50"
                            >
                                ×
                            </button>
                        </div>
                    ))}
                </div>
            )}

            <div>
                <button
                    type="button"
                    onClick={openMedia}
                    className="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-blue-400 hover:text-blue-600"
                >
                    Добавить файлы
                </button>
            </div>
        </div>
    )
}

export default MetaboxFiles
