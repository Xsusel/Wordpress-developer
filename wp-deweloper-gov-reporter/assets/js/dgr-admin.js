/**
 * DGR Admin JS - NIP lookup, auto-calculations, dependencies editor, status selector
 */
jQuery(document).ready(function($) {

    /* =========================================
       NIP LOOKUP (Biała Lista VAT API)
       ========================================= */

    function initNipLookup(nipInputSelector, opts) {
        var $nipInput = $(nipInputSelector);
        if (!$nipInput.length) return;

        var $wrapper = $nipInput.parent();
        var $btn = $('<button type="button" class="button dgr-nip-lookup-btn">' + dgr_admin.i18n.lookup + '</button>');
        var $status = $('<span class="dgr-nip-status"></span>');
        $nipInput.after($status).after($btn);

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

                        if (opts.nameField && data.name) {
                            $(opts.nameField).val(data.name).css('background-color', '#eaffea').delay(1500).queue(function(next) {
                                $(this).css('background-color', '');
                                next();
                            });
                        }

                        if (opts.addressField && data.address) {
                            $(opts.addressField).val(data.address).css('background-color', '#eaffea').delay(1500).queue(function(next) {
                                $(this).css('background-color', '');
                                next();
                            });
                        }

                        if (opts.krsField && data.krs) {
                            $(opts.krsField).val(data.krs);
                        }

                        if (opts.regonField && data.regon) {
                            $(opts.regonField).val(data.regon);
                        }

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

        $nipInput.on('blur', function() {
            var nip = $(this).val().replace(/[\s\-]/g, '');
            if (/^\d{10}$/.test(nip) && opts.nameField && !$(opts.nameField).val()) {
                $btn.trigger('click');
            }
        });
    }

    // --- Investment Metabox NIP Lookup ---
    var $invNipInput = $('#dgr_investment_nip');
    if ($invNipInput.length) {
        initNipLookup('#dgr_investment_nip', {
            nameField: null,
            addressField: '#dgr_investment_address'
        });

        var origBtn = $invNipInput.siblings('.dgr-nip-lookup-btn');
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

                        if (data.address) {
                            // Try to parse address into parts
                            var address = data.address;
                            $('#dgr_investment_address').val(address).css('background-color', '#eaffea').delay(1500).queue(function(next) {
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

        $invNipInput.on('blur', function() {
            var nip = $(this).val().replace(/[\s\-]/g, '');
            var $title = $('#title');
            if (/^\d{10}$/.test(nip) && (!$('#dgr_investment_address').val() || ($title.length && !$title.val()))) {
                origBtn.trigger('click');
            }
        });
    }

    // --- Settings Page NIP Lookup ---
    initNipLookup('input[name="dgr_developer_nip"]', {
        nameField: 'input[name="dgr_developer_name"]',
        addressField: null
    });


    /* =========================================
       UNIT: AUTO-CALCULATE PRICE PER M²
       ========================================= */

    var $priceTotal = $('#dgr_unit_price_total');
    var $priceM2 = $('#dgr_unit_price_m2');
    var $area = $('#dgr_unit_area');

    if ($priceTotal.length && $priceM2.length && $area.length) {
        function autoCalcPriceM2() {
            var total = parseFloat($priceTotal.val());
            var areaVal = parseFloat($area.val());

            if (total > 0 && areaVal > 0) {
                var calculated = Math.round((total / areaVal) * 100) / 100;
                $priceM2.val(calculated);
                $priceM2.addClass('dgr-auto-calculated');
                $('.dgr-auto-calc-hint').show();
            }
        }

        // Auto-calc when price or area changes, but only if price/m2 is empty or was auto-calculated
        $priceTotal.on('input', function() {
            if (!$priceM2.val() || $priceM2.hasClass('dgr-auto-calculated')) {
                autoCalcPriceM2();
            }
        });

        $area.on('input', function() {
            if (!$priceM2.val() || $priceM2.hasClass('dgr-auto-calculated')) {
                autoCalcPriceM2();
            }
        });

        // If user manually edits price/m², remove auto-calc class
        $priceM2.on('input', function() {
            $priceM2.removeClass('dgr-auto-calculated');
            $('.dgr-auto-calc-hint').hide();
        });
    }


    /* =========================================
       UNIT: STATUS SELECTOR (Radio Cards)
       ========================================= */

    $('.dgr-status-option input[type="radio"]').on('change', function() {
        var $parent = $(this).closest('.dgr-status-selector');
        $parent.find('.dgr-status-option').removeClass('active');
        $(this).closest('.dgr-status-option').addClass('active');
    });


    /* =========================================
       UNIT: VISUAL DEPENDENCIES EDITOR
       ========================================= */

    var $depsBody = $('#dgr-deps-body');
    var $depsHidden = $('#dgr_unit_dependencies');

    if ($depsBody.length) {
        var depRowTemplate =
            '<tr class="dgr-dep-row">' +
                '<td>' +
                    '<select class="dgr-dep-type">' +
                        '<option value="miejsce_postojowe">' + dgr_admin.i18n.dep_parking + '</option>' +
                        '<option value="komorka_lokatorska">' + dgr_admin.i18n.dep_storage + '</option>' +
                        '<option value="garaz">' + dgr_admin.i18n.dep_garage + '</option>' +
                        '<option value="rowerownia">' + dgr_admin.i18n.dep_bike + '</option>' +
                        '<option value="inne">' + dgr_admin.i18n.dep_other + '</option>' +
                    '</select>' +
                '</td>' +
                '<td><input type="number" step="0.01" min="0" class="dgr-dep-price" value=""></td>' +
                '<td><button type="button" class="button dgr-dep-remove" title="' + dgr_admin.i18n.dep_remove + '"><span class="dashicons dashicons-trash"></span></button></td>' +
            '</tr>';

        // Add row
        $('#dgr-dep-add').on('click', function() {
            $depsBody.append(depRowTemplate);
            updateDepsJSON();
        });

        // Remove row
        $depsBody.on('click', '.dgr-dep-remove', function() {
            $(this).closest('.dgr-dep-row').remove();
            updateDepsJSON();
        });

        // Update hidden field on any change
        $depsBody.on('change input', '.dgr-dep-type, .dgr-dep-price', function() {
            updateDepsJSON();
        });

        function updateDepsJSON() {
            var deps = [];
            $depsBody.find('.dgr-dep-row').each(function() {
                var typ = $(this).find('.dgr-dep-type').val();
                var cena = parseFloat($(this).find('.dgr-dep-price').val());

                if (typ) {
                    var dep = { typ: typ };
                    if (!isNaN(cena) && cena > 0) {
                        dep.cena = cena;
                    }
                    deps.push(dep);
                }
            });

            $depsHidden.val(deps.length > 0 ? JSON.stringify(deps) : '');
        }
    }

});
