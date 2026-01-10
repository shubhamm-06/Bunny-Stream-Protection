(function($) {
    'use strict';

    const BunnyAdmin = {
        init: function() {
            this.render();
            this.bind();
        },

        render: function() {
            const $body = $('#library-rows');
            $body.empty();
            const libs = streamSafeData.settings.libraries || [];

            if (libs.length === 0) {
                this.addRow();
            } else {
                libs.forEach(lib => this.addRow(lib));
            }

            $('#global-expiry').val(streamSafeData.settings.expiry || 3600);
        },

        addRow: function(data = {key: '', id: '', secret: ''}) {
            const isDefault = data.key && data.key === streamSafeData.settings.default_lib;
            const displayKey = data.key || 'key';
            const shortcode = `[bunny_video id="VIDEO_ID" lib="${displayKey}"]`;
            
            const html = `
                <tr class="library-row">
                    <td style="text-align:center; vertical-align:middle;">
                        <input type="radio" name="default_lib_radio" value="${data.key}" ${isDefault ? 'checked' : ''}>
                    </td>
                    <td><input type="text" class="lib-key widefat" value="${data.key}" placeholder="e.g. primary"></td>
                    <td><input type="text" class="lib-id widefat" value="${data.id}" placeholder="Library ID"></td>
                    <td><input type="password" class="lib-secret widefat" value="${data.secret}" placeholder="Secret Key"></td>
                    <td style="vertical-align:middle;">
                        <code class="shortcode-display" style="font-size:11px; background:#f0f0f1; padding:4px 8px; border-radius:4px; display:inline-block; border:1px solid #dcdcde;">${shortcode}</code>
                    </td>
                    <td style="vertical-align:middle; text-align:center;">
                        <span class="row-delete dashicons dashicons-trash" title="Remove Library"></span>
                        <span class="row-copy dashicons dashicons-admin-page" title="Copy Shortcode Helper" style="margin-left:12px;"></span>
                    </td>
                </tr>
            `;
            $('#library-rows').append(html);
        },

        bind: function() {
            const self = this;

            // Add new row button
            $('#add-library').on('click', () => self.addRow());

            // Sync radio value with key input and update live shortcode display
            $(document).on('input', '.lib-key', function() {
                const val = $(this).val();
                $(this).closest('tr').find('input[name="default_lib_radio"]').val(val);
                $(this).closest('tr').find('.shortcode-display').text(`[bunny_video id="VIDEO_ID" lib="${val || 'key'}"]`);
            });

            // Delete row logic
            $(document).on('click', '.row-delete', function() {
                if ($('.library-row').length <= 1) {
                    alert('You must have at least one library configuration.');
                    return;
                }
                if (confirm('Are you sure you want to remove this library configuration?')) {
                    $(this).closest('tr').remove();
                }
            });

            // Shortcode copy helper
            $(document).on('click', '.row-copy', function() {
                const key = $(this).closest('tr').find('.lib-key').val() || 'LIB_KEY';
                const sc = `[bunny_video id="VIDEO_ID" lib="${key}"]`;
                
                const $temp = $('<input>').val(sc).appendTo('body').select();
                document.execCommand('copy');
                $temp.remove();
                
                const $btn = $(this);
                $btn.removeClass('dashicons-admin-page').addClass('dashicons-yes');
                setTimeout(() => $btn.removeClass('dashicons-yes').addClass('dashicons-admin-page'), 2000);
            });

            // Save Settings AJAX
            $('#save-stream-safe').on('click', function() {
                const $btn = $(this);
                const libraries = [];
                
                $('.library-row').each(function() {
                    const key = $(this).find('.lib-key').val();
                    if (key) {
                        libraries.push({
                            key: key,
                            id: $(this).find('.lib-id').val(),
                            secret: $(this).find('.lib-secret').val()
                        });
                    }
                });

                if (libraries.length === 0) {
                    alert('Please add at least one library.');
                    return;
                }

                const payload = {
                    action: 'bunny_stream_safe_save_settings',
                    nonce: streamSafeData.nonce,
                    libraries: libraries,
                    default_lib: $('input[name="default_lib_radio"]:checked').val(),
                    expiry: $('#global-expiry').val()
                };

                $btn.prop('disabled', true).text('Saving Settings...');
                
                $.post(streamSafeData.ajaxUrl, payload, (res) => {
                    if (res.success) {
                        $('#save-status').text('Settings saved successfully!')
                            .css('color', '#46b450')
                            .fadeIn().delay(3000).fadeOut();
                    } else {
                        $('#save-status').text('Error saving settings.')
                            .css('color', '#dc3232')
                            .show();
                    }
                }).always(() => {
                    $btn.prop('disabled', false).text('Save All Settings');
                });
            });
        }
    };

    $(document).ready(() => BunnyAdmin.init());

})(jQuery);