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
        $(this).closest('tr').remove();
        if ($tbody.children('tr').length === 0) {
            $tbody.append('<tr class="pb-no-libs"><td colspan="5">No libraries added yet.</td></tr>');
        }
    });

    // Sync Key Input to Radio Value
    $tbody.on('input', '.pb-key-input', function() {
        const val = $(this).val().toLowerCase().replace(/[^a-z0-9_]/g, '');
        $(this).val(val);
        const $row = $(this).closest('tr');
        $row.find('input[type="radio"]').val(val);
        
        // Update names for post processing
        $row.find('input[name*="libs["]').each(function() {
            const currentName = $(this).attr('name');
            const newName = currentName.replace(/libs\[.*?\]/, 'libs[' + val + ']');
            $(this).attr('name', newName);
        });
    });
});