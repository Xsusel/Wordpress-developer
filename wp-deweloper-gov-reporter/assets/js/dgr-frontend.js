jQuery(document).ready(function($) {
    $('.dgr-filter-form').on('submit', function(e) {
        e.preventDefault();

        var form = $(this);
        var wrapper = form.closest('.dgr-unit-list-wrapper');
        var resultsContainer = wrapper.find('.dgr-results');

        var data = {
            action: 'dgr_filter_units',
            nonce: dgr_ajax.nonce,
            rooms_min: form.find('input[name="rooms_min"]').val(),
            rooms_max: form.find('input[name="rooms_max"]').val(),
            area_min: form.find('input[name="area_min"]').val(),
            area_max: form.find('input[name="area_max"]').val(),
            status: form.find('select[name="status"]').val(),
            // If the shortcode had an investment_id, we need to pass it.
            // Currently the shortcode renders a fresh form. We should add a hidden input for investment_id if present.
            investment_id: form.find('input[name="investment_id"]').val()
        };

        // Add loading state
        resultsContainer.css('opacity', '0.5');

        $.get(dgr_ajax.ajaxurl, data, function(response) {
            if (response.success) {
                resultsContainer.html(response.data);
            } else {
                resultsContainer.html('<p>Error loading results.</p>');
            }
            resultsContainer.css('opacity', '1');
        });
    });
});
