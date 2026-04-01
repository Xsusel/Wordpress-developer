/**
 * DGR Admin JS - NIP lookup and auto-fill
 */
jQuery(document).ready(function($) {

    /**
     * NIP Lookup via AJAX using Biała Lista VAT API (Ministry of Finance)
     * Works on both: Investment metabox and Settings page
     */
    function initNipLookup(nipInputSelector, opts) {
        var $nipInput = $(nipInputSelector);
        if (!$nipInput.length) return;

        // Add lookup button next to NIP field
        var $wrapper = $nipInput.parent();
        var $btn = $('<button type="button" class="button dgr-nip-lookup-btn" style="margin-left: 8px; vertical-align: middle;">' + dgr_admin.i18n.lookup + '</button>');
        var $status = $('<span class="dgr-nip-status" style="margin-left: 8px; font-style: italic;"></span>');
        $nipInput.after($status).after($btn);

        // Lookup on button click
        $btn.on('click', function(e) {
            e.preventDefault();
            var nip = $nipInput.val().replace(/[\s\-]/g, '');

            if (!/^\d{10}$/.test(nip)) {
                $status.text(dgr_admin.i18n.invalid_nip).css('color', '#dc3232');
                return;
            }

            $btn.prop('disabled', true);
            $status.text(dgr_admin.i18n.searching).css('color', '#666');

            $.ajax({
                url: dgr_admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'dgr_lookup_nip',
                    nonce: dgr_admin.nonce,
                    nip: nip
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var data = response.data;

                        // Fill name field
                        if (opts.nameField && data.name) {
                            $(opts.nameField).val(data.name);
                            $(opts.nameField).css('background-color', '#eaffea').delay(1500).queue(function(next) {
                                $(this).css('background-color', '');
                                next();
                            });
                        }

                        // Fill address field
                        if (opts.addressField && data.address) {
                            $(opts.addressField).val(data.address);
                            $(opts.addressField).css('background-color', '#eaffea').delay(1500).queue(function(next) {
                                $(this).css('background-color', '');
                                next();
                            });
                        }

                        // Fill KRS if available
                        if (opts.krsField && data.krs) {
                            $(opts.krsField).val(data.krs);
                        }

                        // Fill REGON if available
                        if (opts.regonField && data.regon) {
                            $(opts.regonField).val(data.regon);
                        }

                        // Show VAT status
                        var statusText = data.name;
                        if (data.status_vat) {
                            statusText += ' (VAT: ' + data.status_vat + ')';
                        }
                        $status.text(statusText).css('color', '#46b450');

                    } else {
                        var msg = (response.data && response.data.message) ? response.data.message : dgr_admin.i18n.not_found;
                        $status.text(msg).css('color', '#dc3232');
                    }
                },
                error: function() {
                    $status.text(dgr_admin.i18n.error).css('color', '#dc3232');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        });

        // Auto-lookup on blur if field has 10 digits and name field is empty
        $nipInput.on('blur', function() {
            var nip = $(this).val().replace(/[\s\-]/g, '');
            if (/^\d{10}$/.test(nip) && opts.nameField && !$(opts.nameField).val()) {
                $btn.trigger('click');
            }
        });
    }

    // --- Investment Metabox ---
    initNipLookup('#dgr_investment_nip', {
        nameField: null,  // Investment title is the WP post title, handled separately
        addressField: '#dgr_investment_address'
    });

    // Also fill the post title if it's empty (for new investments)
    // The investment name should come from the company name
    var $invNipInput = $('#dgr_investment_nip');
    if ($invNipInput.length) {
        var origBtn = $invNipInput.siblings('.dgr-nip-lookup-btn');
        // Override: also fill post title
        origBtn.off('click').on('click', function(e) {
            e.preventDefault();
            var nip = $invNipInput.val().replace(/[\s\-]/g, '');

            if (!/^\d{10}$/.test(nip)) {
                $invNipInput.siblings('.dgr-nip-status').text(dgr_admin.i18n.invalid_nip).css('color', '#dc3232');
                return;
            }

            origBtn.prop('disabled', true);
            $invNipInput.siblings('.dgr-nip-status').text(dgr_admin.i18n.searching).css('color', '#666');

            $.ajax({
                url: dgr_admin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'dgr_lookup_nip',
                    nonce: dgr_admin.nonce,
                    nip: nip
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var data = response.data;

                        // Fill address
                        if (data.address) {
                            $('#dgr_investment_address').val(data.address).css('background-color', '#eaffea').delay(1500).queue(function(next) {
                                $(this).css('background-color', '');
                                next();
                            });
                        }

                        // Fill post title if empty
                        var $titleField = $('#title');
                        if ($titleField.length && !$titleField.val() && data.name) {
                            $titleField.val(data.name).css('background-color', '#eaffea').delay(1500).queue(function(next) {
                                $(this).css('background-color', '');
                                next();
                            });
                        }

                        var statusText = data.name || '';
                        if (data.status_vat) {
                            statusText += ' (VAT: ' + data.status_vat + ')';
                        }
                        $invNipInput.siblings('.dgr-nip-status').text(statusText).css('color', '#46b450');

                    } else {
                        var msg = (response.data && response.data.message) ? response.data.message : dgr_admin.i18n.not_found;
                        $invNipInput.siblings('.dgr-nip-status').text(msg).css('color', '#dc3232');
                    }
                },
                error: function() {
                    $invNipInput.siblings('.dgr-nip-status').text(dgr_admin.i18n.error).css('color', '#dc3232');
                },
                complete: function() {
                    origBtn.prop('disabled', false);
                }
            });
        });

        // Auto-lookup on blur for investment NIP
        $invNipInput.on('blur', function() {
            var nip = $(this).val().replace(/[\s\-]/g, '');
            var $title = $('#title');
            if (/^\d{10}$/.test(nip) && (!$('#dgr_investment_address').val() || ($title.length && !$title.val()))) {
                origBtn.trigger('click');
            }
        });
    }

    // --- Settings Page ---
    initNipLookup('input[name="dgr_developer_nip"]', {
        nameField: 'input[name="dgr_developer_name"]',
        addressField: null
    });
});
