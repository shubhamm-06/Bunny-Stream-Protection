jQuery(document).ready(function($) {
    const $tbody = $('#pb-library-rows');
    const template = $('#pb-row-template').html();

    // Add Row
    $('#pb-add-library').on('click', function() {
        $('.pb-no-libs').remove();
        const tempId = 'lib_' + Date.now();
        const row = template.replace(/__KEY__/g, tempId);
        $tbody.append(row);
    });

    // Remove Row
    $tbody.on('click', '.pb-remove-row', function() {
        if(confirm('Are you sure you want to remove this library?')) {
            $(this).closest('tr').remove();
            if ($tbody.children('tr').length === 0) {
                $tbody.append('<tr class="pb-no-libs"><td colspan="6">No libraries added yet. Click "Add Library" to start.</td></tr>');
            }
        }
    });

    // Key Input Sanitization
    $tbody.on('input', '.pb-key-input', function() {
        const val = $(this).val().toLowerCase().replace(/[^a-z0-9_]/g, '');
        $(this).val(val);
        const $row = $(this).closest('tr');
        $row.find('input[type="radio"]').val(val);
        $row.find('input[name*="libs["]').each(function() {
            $(this).attr('name', $(this).attr('name').replace(/libs\[.*?\]/, 'libs[' + val + ']'));
        });
    });

    // Copy Shortcode
    $(document).on('click', '.pb-copy-code', function() {
        const text = $(this).text().trim();
        const temp = $("<input>");
        $("body").append(temp);
        temp.val(text).select();
        document.execCommand("copy");
        temp.remove();
        
        const $el = $(this);
        const original = $el.text();
        $el.text('Copied!');
        setTimeout(() => $el.text(original), 1000);
    });

    // AJAX Check for Update (No Redirect)
    $('.pb-check-update-btn').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const originalText = $btn.text();
        
        $btn.text('Checking...').prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'pb_force_update_check',
                nonce: pb_vars.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    if (response.data.update_available) {
                        window.location.reload(); // Reload to show the update notification if found
                    }
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('Update check failed. Please try again.');
            },
            complete: function() {
                $btn.text(originalText).prop('disabled', false);
            }
        });
    });
});