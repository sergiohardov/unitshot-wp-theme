import {useEditor, EditorContent} from '@tiptap/react'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'

/**
 * WYSIWYG-поле описания (замена wp_editor). Отдаёт HTML через setValue.
 * Контент инициализируется один раз; дальше правки идут из самого редактора.
 */
function MetaboxDescription({value, setValue}) {
    const editor = useEditor({
        extensions: [
            StarterKit,
            Link.configure({openOnClick: false, autolink: true}),
        ],
        content: value || '',
        onUpdate: ({editor}) => setValue(editor.isEmpty ? '' : editor.getHTML()), // Пустой редактор TipTap отдаёт «<p></p>» — нормализуем в '' (иначе мета непустая).
    })

    return (
        <div className="flex flex-col gap-1.5">
            <div className="us-rte overflow-hidden rounded-md border border-gray-300 bg-white">
                {editor && <Toolbar editor={editor}/>}
                <EditorContent editor={editor} className="px-3 py-2"/>
            </div>
        </div>
    )
}

/** Кнопка тулбара. type="button" обязателен - иначе сабмит формы поста. */
function ToolbarButton({onClick, active, disabled, title, children}) {
    return (
        <button
            type="button"
            title={title}
            disabled={disabled}
            onClick={onClick}
            className={
                "flex h-7 min-w-7 items-center justify-center rounded px-1.5 text-sm font-medium transition-colors " +
                (active ? "bg-blue-600 text-white" : "text-gray-600 hover:bg-gray-100") +
                (disabled ? " cursor-not-allowed opacity-40" : "")
            }
        >
            {children}
        </button>
    )
}

function Toolbar({editor}) {
    const setLink = () => {
        const prev = editor.getAttributes('link').href
        const url = window.prompt('Ссылка (URL)', prev || '')
        if (url === null) return
        if (url === '') {
            editor.chain().focus().unsetLink().run()
            return
        }
        editor.chain().focus().extendMarkRange('link').setLink({href: url}).run()
    }

    return (
        <div className="flex flex-wrap items-center gap-0.5 border-b border-gray-200 bg-gray-50 px-2 py-1.5">
            <ToolbarButton title="Жирный" active={editor.isActive('bold')} onClick={() => editor.chain().focus().toggleBold().run()}><b>B</b></ToolbarButton>
            <ToolbarButton title="Курсив" active={editor.isActive('italic')} onClick={() => editor.chain().focus().toggleItalic().run()}><i>I</i></ToolbarButton>
            <ToolbarButton title="Зачёркнутый" active={editor.isActive('strike')} onClick={() => editor.chain().focus().toggleStrike().run()}><s>S</s></ToolbarButton>

            <span className="mx-1 h-5 w-px bg-gray-200"/>

            <ToolbarButton title="Заголовок 2" active={editor.isActive('heading', {level: 2})} onClick={() => editor.chain().focus().toggleHeading({level: 2}).run()}>H2</ToolbarButton>
            <ToolbarButton title="Заголовок 3" active={editor.isActive('heading', {level: 3})} onClick={() => editor.chain().focus().toggleHeading({level: 3}).run()}>H3</ToolbarButton>

            <span className="mx-1 h-5 w-px bg-gray-200"/>

            <ToolbarButton title="Маркированный список" active={editor.isActive('bulletList')} onClick={() => editor.chain().focus().toggleBulletList().run()}>•</ToolbarButton>
            <ToolbarButton title="Нумерованный список" active={editor.isActive('orderedList')} onClick={() => editor.chain().focus().toggleOrderedList().run()}>1.</ToolbarButton>
            <ToolbarButton title="Цитата" active={editor.isActive('blockquote')} onClick={() => editor.chain().focus().toggleBlockquote().run()}>❝</ToolbarButton>
            <ToolbarButton title="Ссылка" active={editor.isActive('link')} onClick={setLink}>🔗</ToolbarButton>

            <span className="mx-1 h-5 w-px bg-gray-200"/>

            <ToolbarButton title="Отменить" disabled={!editor.can().undo()} onClick={() => editor.chain().focus().undo().run()}>↶</ToolbarButton>
            <ToolbarButton title="Повторить" disabled={!editor.can().redo()} onClick={() => editor.chain().focus().redo().run()}>↷</ToolbarButton>
        </div>
    )
}

export default MetaboxDescription
