/**
 * Одно поле параметра: text / number / checkbox / select / radio - единый стиль.
 * Для select/radio передаётся options: { value: label }.
 */
function MetaboxParam({type, label, value, setValue, checkboxText = '', options = {}, placeholder = ''}) {
    const inputClasses =
        "w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 " +
        "transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20";

    return (
        <div className="flex flex-col gap-1.5">
            {label && type !== 'checkbox' && (
                <label className="text-[12px] font-semibold text-gray-700">{label}</label>
            )}

            {type === 'text' && (
                <input
                    className={inputClasses}
                    type="text"
                    value={value ?? ''}
                    onChange={(e) => setValue(e.target.value)}
                />
            )}

            {type === 'number' && (
                <input
                    className={inputClasses}
                    type="number"
                    value={value ?? ''}
                    onChange={(e) => setValue(e.target.value === '' ? '' : Number(e.target.value))}
                />
            )}

            {type === 'select' && (
                <select
                    className={inputClasses}
                    value={value ?? ''}
                    onChange={(e) => setValue(e.target.value)}
                >
                    {placeholder && <option value="">{placeholder}</option>}
                    {Object.entries(options).map(([val, lbl]) => (
                        <option key={val} value={val}>{lbl}</option>
                    ))}
                </select>
            )}

            {type === 'radio' && (
                <div className="flex flex-col gap-1.5">
                    {Object.entries(options).map(([val, lbl]) => {
                        const active = value === val;
                        return (
                            <label
                                key={val}
                                className={
                                    "flex cursor-pointer items-center gap-2.5 rounded-md border px-3 py-2 text-sm transition-colors " +
                                    (active ? "border-blue-500 bg-blue-50 text-gray-900" : "border-gray-200 text-gray-700 hover:border-blue-300")
                                }
                            >
                                <input
                                    className="accent-blue-600"
                                    type="radio"
                                    checked={active}
                                    onChange={() => setValue(val)}
                                />
                                <span>{lbl}</span>
                            </label>
                        );
                    })}
                </div>
            )}

            {type === 'checkbox' && (
                <label className="flex cursor-pointer items-center gap-2.5">
                    <input
                        className="h-4 w-4 cursor-pointer accent-blue-600"
                        type="checkbox"
                        checked={!!value}
                        onChange={(e) => setValue(e.target.checked)}
                    />
                    <span className="text-sm text-gray-700">{checkboxText || label}</span>
                </label>
            )}
        </div>
    );
}

export default MetaboxParam;
