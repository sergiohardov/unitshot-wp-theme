import {DndContext, closestCenter, PointerSensor, useSensor, useSensors} from '@dnd-kit/core'
import {SortableContext, arrayMove, rectSortingStrategy, useSortable} from '@dnd-kit/sortable'
import {CSS} from '@dnd-kit/utilities'

/** Одна миниатюра: перетаскивается за картинку, крестик - удалить. */
function Thumb({item, onRemove}) {
    const {attributes, listeners, setNodeRef, transform, transition, isDragging} = useSortable({id: String(item.id)})

    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.4 : 1,
    }

    return (
        <div
            ref={setNodeRef}
            style={style}
            className="relative h-[86px] w-[86px] overflow-hidden rounded-md border border-gray-200 bg-white"
        >
            {/* Драг-листенеры на картинке - клик по крестику не запускает перетаскивание. */}
            <img
                src={item.url}
                alt=""
                className="h-full w-full cursor-move touch-none object-cover select-none"
                {...attributes}
                {...listeners}
            />
            <button
                type="button"
                title="Удалить"
                onClick={() => onRemove(item.id)}
                className="absolute right-1 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-white/90 text-sm leading-none text-red-600 shadow hover:bg-white"
            >
                ×
            </button>
        </div>
    )
}

/**
 * Галерея товара. value: [{id, url}]. Выбор через wp.media, порядок -
 * перетаскиванием (dnd-kit). На сабмите уходит как часть state.
 */
function MetaboxGallery({value = [], setValue}) {
    const sensors = useSensors(useSensor(PointerSensor, {activationConstraint: {distance: 4}}))
    const ids = value.map((g) => String(g.id))

    const onDragEnd = ({active, over}) => {
        if (!over || active.id === over.id) return
        setValue(arrayMove(value, ids.indexOf(active.id), ids.indexOf(over.id)))
    }

    const remove = (id) => setValue(value.filter((g) => g.id !== id))

    const openMedia = () => {
        if (!window.wp || !window.wp.media) return

        const frame = window.wp.media({
            title: 'Галерея товара',
            multiple: true,
            library: {type: 'image'},
            button: {text: 'Добавить'},
        })

        frame.on('select', () => {
            const existing = new Set(value.map((g) => g.id))
            const added = []
            frame.state().get('selection').each((att) => {
                const a = att.toJSON()
                if (existing.has(a.id)) return
                added.push({id: a.id, url: a.sizes?.thumbnail?.url || a.url})
            })
            if (added.length) setValue([...value, ...added])
        })

        frame.open()
    }

    return (
        <div className="flex flex-col gap-3">
            {value.length > 0 && (
                <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                    <SortableContext items={ids} strategy={rectSortingStrategy}>
                        <div className="flex flex-wrap gap-2.5">
                            {value.map((item) => (
                                <Thumb key={item.id} item={item} onRemove={remove}/>
                            ))}
                        </div>
                    </SortableContext>
                </DndContext>
            )}

            <div>
                <button
                    type="button"
                    onClick={openMedia}
                    className="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:border-blue-400 hover:text-blue-600"
                >
                    Добавить изображения
                </button>
            </div>
        </div>
    )
}

export default MetaboxGallery
