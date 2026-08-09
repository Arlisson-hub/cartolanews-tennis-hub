/**
 * Admin JS: seletor de cor (wp-color-picker) e seletor de imagem
 * (wp.media) reutilizado em Jogadores/Lendas.
 */
(function ($) {
    'use strict';

    $(function () {
        if ($.fn.wpColorPicker) {
            $('.cnt-color-field').wpColorPicker();
        }

        $('[data-cnt-photo-picker]').each(function () {
            var wrapper = $(this);
            var input = wrapper.find('[data-cnt-photo-id]');
            var preview = wrapper.find('.cn-tennis-admin__photo-preview');
            var button = wrapper.find('[data-cnt-photo-select]');
            var frame = null;

            button.on('click', function (event) {
                event.preventDefault();
                if (!window.wp || !wp.media) {
                    return;
                }
                if (!frame) {
                    frame = wp.media({
                        title: 'Selecionar imagem',
                        button: { text: 'Usar esta imagem' },
                        library: { type: 'image' },
                        multiple: false,
                    });
                    frame.on('select', function () {
                        var attachment = frame.state().get('selection').first().toJSON();
                        input.val(attachment.id);
                        var url = (attachment.sizes && attachment.sizes.thumbnail) ? attachment.sizes.thumbnail.url : attachment.url;
                        preview.html('<img src="' + url + '" alt="">');
                    });
                }
                frame.open();
            });
        });
    });
})(jQuery);
