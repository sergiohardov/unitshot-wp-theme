import {useState} from 'react'
import MetaboxBlock from "@components/MetaboxBlock.jsx";
import MetaboxParam from "@components/MetaboxParam.jsx";
import MetaboxDescription from "@components/MetaboxDescription.jsx";
import MetaboxGallery from "@components/MetaboxGallery.jsx";
import MetaboxFiles from "@components/MetaboxFiles.jsx";
import MetaboxAttributes from "@components/MetaboxAttributes.jsx";
import MetaboxVariations from "@components/MetaboxVariations.jsx";
import MetaboxSet from "@components/MetaboxSet.jsx";
import MetaboxProducts from "@components/MetaboxProducts.jsx";
import MetaboxParameters from "@components/MetaboxParameters.jsx";

/**
 * Корневой компонент метабокса.
 *
 * state — редактируемые поля; в скрытый инпут us_product_state уходит ТОЛЬКО он
 * (нативный сабмит -> Products::save_state в admin_save_post). options - справочники.
 *
 * Раскладка: контент слева, «Основные параметры» - sticky-aside справа.
 * На узком контейнере (@container) - одна колонка, параметры сверху.
 */
export default function ProductMetabox({dataJson}) {
    const [state, setState] = useState(dataJson.state ?? {})
    const options = dataJson.options ?? {}
    const config = dataJson.config ?? {}

    const currency = options.currency ?? ''
    const field_key = config.field_key ?? ''

    // value может быть значением или апдейтером (prev) => next - поддерживаем оба.
    const update = (key) => (value) => setState((s) => ({
        ...s,
        [key]: typeof value === 'function' ? value(s[key]) : value,
    }))
    const withCur = (label) => (currency ? `${label}, ${currency}` : label)
    const isSimple = state.type === 'simple'
    const isVariable = state.type === 'variable'
    const isComplete = state.type === 'complete'

    return (
        <div className="us-product-app @container text-[13px] text-gray-800">
            <input type="hidden" name={field_key} value={JSON.stringify(state)}/>

            <div className="grid grid-cols-1 gap-[18px] @3xl:grid-cols-[minmax(0,1fr)_320px]">
                {/* ---- Левая колонка (контент) ---- */}
                <div className="order-2 flex flex-col gap-[18px] @3xl:order-1">
                    <MetaboxBlock title="Краткое описание" hint="Выводится рядом с ценой и кнопкой «В корзину».">
                        <MetaboxDescription value={state.short_description} setValue={update('short_description')}/>
                    </MetaboxBlock>

                    <MetaboxBlock title="Полное описание" hint="Выводится в блоке «Опис товару».">
                        <MetaboxDescription value={state.description} setValue={update('description')}/>
                    </MetaboxBlock>

                    <MetaboxBlock title="Галерея" hint="Перетаскиванием меняется порядок фотографий.">
                        <MetaboxGallery value={state.gallery} setValue={update('gallery')}/>
                    </MetaboxBlock>

                    <MetaboxBlock title="Файлы" hint="Доп. материалы (PDF и др.). На фронте — по клику скачиваются.">
                        <MetaboxFiles value={state.files} setValue={update('files')}/>
                    </MetaboxBlock>

                    {(isSimple || isVariable) && <MetaboxBlock title="Атрибуты" hint="Назначаются для фильтрации; могут участвовать в вариациях и выводиться в характеристиках.">
                        <MetaboxAttributes value={state.attributes} setValue={update('attributes')} taxChoices={options.tax_choices ?? {}}/>
                    </MetaboxBlock>}

                    {isVariable && <MetaboxBlock title="Вариации">
                        <MetaboxVariations
                            attributes={state.attributes ?? []}
                            variations={state.variations ?? []}
                            setVariations={update('variations')}
                            defaults={state.default_attributes ?? {}}
                            setDefaults={update('default_attributes')}
                            taxChoices={options.tax_choices ?? {}}
                            wildcard={options.wildcard ?? '*'}
                            currency={currency}
                        />
                    </MetaboxBlock>}

                    {(isSimple || isVariable) && <MetaboxBlock title="Параметры" hint="Свободные характеристики: параметр + текстовое значение.">
                        <MetaboxParameters value={state.parameters} setValue={update('parameters')} terms={options.parameters ?? []}/>
                    </MetaboxBlock>}

                    {isComplete && <MetaboxBlock title="Состав комплекта" hint="Для вариативного товара выберите вариацию для комплекта.">
                        <MetaboxSet value={state.set_items} setValue={update('set_items')} currency={currency}/>
                    </MetaboxBlock>}

                    <MetaboxBlock title="Похожие товары" hint="Показываются в блоке «Схожі товари» на странице этого товара.">
                        <MetaboxProducts value={state.popular_products} setValue={update('popular_products')}/>
                    </MetaboxBlock>
                </div>

                {/* ---- Правый sticky-aside (основные параметры) ---- */}
                <aside className="order-1 @3xl:order-2 @3xl:sticky @3xl:top-8 @3xl:self-start">
                    <MetaboxBlock title="Основные параметры" hint="Поля зависят от выбранного типа товара">
                        <MetaboxParam type="radio" label="Тип товара" options={options.types ?? {}} value={state.type} setValue={update('type')}/>
                        <MetaboxParam type="text" label="SKU" value={state.sku} setValue={update('sku')}/>
                        <MetaboxParam type="checkbox" label="Наличие" checkboxText="В наличии" value={state.in_stock} setValue={update('in_stock')}/>
                        <MetaboxParam type="select" label="Статус" placeholder="—" options={options.statuses ?? {}} value={state.status} setValue={update('status')}/>

                        {(isSimple || isVariable) && <MetaboxParam type="text" label="Модель" value={state.model} setValue={update('model')}/>}
                        {(isSimple || isComplete) && <MetaboxParam type="number" label={withCur(isComplete ? "Цена комплекта" : "Цена")} value={state.base_price} setValue={update('base_price')}/>}
                        {isSimple && <MetaboxParam type="number" label={withCur("Скидочная цена")} value={state.sale_price} setValue={update('sale_price')}/>}
                    </MetaboxBlock>
                </aside>
            </div>
        </div>
    )
}
