/** Карточка-блок метабокса: шапка с заголовком + тело с опциональной подсказкой. */
function MetaboxBlock({title, hint, children}) {
    return (
        <section className="rounded-[10px] border border-gray-200 bg-white shadow-sm">
            <header className="border-b border-gray-200 bg-gray-50 px-4 py-3 text-[13px] font-semibold text-gray-900">
                {title}
            </header>

            <div className="flex flex-col gap-4 px-4 py-4">
                {hint && <p className="text-[12px] leading-snug text-gray-500">{hint}</p>}
                {children}
            </div>
        </section>
    );
}

export default MetaboxBlock;
