(function () {
    if (typeof acf === 'undefined') return;

    const CAT_FIELD_KEY = 'field_6a0b20e031504';
    const PROD_FIELD_KEY = 'field_6a0b246919050';

    // При смене категории — сбрасываем поле продуктов
    acf.addAction(`ready_field/key=${CAT_FIELD_KEY}`, function (field) {
        field.on('change', () => {
            const $group = field.$el.closest('.acf-field-group, .acf-fields');
            const $prodEl = $group.find(`[data-key="${PROD_FIELD_KEY}"]`);
            const prodField = acf.getField($prodEl);

            if (!prodField) return;

            prodField.val(null);
            prodField.$el.find('select').val(null).trigger('change');
        });
    });

    // При AJAX-запросе поля продуктов — передаём category_id
    acf.addAction(`ready_field/key=${PROD_FIELD_KEY}`, function () {
        acf.addFilter('select2_ajax_data', function (data, args, $input, field) {
            if (!field || field.get('key') !== PROD_FIELD_KEY) return data;

            const $group = field.$el.closest('.acf-field-group, .acf-fields');
            const $catEl = $group.find(`[data-key="${CAT_FIELD_KEY}"]`);
            const catField = acf.getField($catEl);

            if (!catField) return data;

            const catId = catField.val();
            if (catId) data.category_id = catId;

            return data;
        });
    });

})();
