jQuery(function ($) {
    function cobru_submit_error(error_message, $) {
        console.log('cobru_submit_error:');
        console.log(error_message);
        jQuery('.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message').remove();

        let checkout_form = jQuery('form.checkout');
        checkout_form.prepend('<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>');
        checkout_form.removeClass('processing').unblock();
        checkout_form.find('.input-text, select, input:checkbox').trigger('validate').blur();

        if (typeof checkout_form.scroll_to_notices === "function") {
            checkout_form.scroll_to_notices();
        } else {
            if (typeof $.scroll_to_notices === "function") {
                $.scroll_to_notices();
            }
        }

        jQuery(document.body).trigger('checkout_error');
    }

    function cobru_is_valid_json(raw_json) {
        console.log("raw_json: " + raw_json);
        try {
            var json = jQuery.parseJSON(raw_json);

            return (json && 'object' === typeof json);
        } catch (e) {
            return false;
        }
    }


    let checkout_form = $('form.checkout');

    checkout_form.on('checkout_place_order_cobru', function () {

        let data_serialized = $(this).serialize();
        console.log(data_serialized);

        $(this).addClass('processing');

        var form_data = $(this).data();

        if (1 !== form_data['blockUI.isBlocked']) {
            $(this).block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        }

        // ajaxSetup is global, but we use it to ensure JSON is valid once returned.
        $.ajaxSetup({
            dataFilter: function (raw_response, dataType) {
                // We only want to work with JSON
                console.log(raw_response);
                if ('json' !== dataType) {
                    return raw_response;
                }

                if (cobru_is_valid_json(raw_response)) {
                    return raw_response;
                } else {
                    // Attempt to fix the malformed JSON
                    var maybe_valid_json = raw_response.match(/{"result.*}/);

                    if (null === maybe_valid_json) {
                        console.log('Unable to fix malformed JSON');
                    } else if (checkout_form.is_valid_json(maybe_valid_json[0])) {
                        console.log('Fixed malformed JSON. Original:');
                        console.log(raw_response);
                        raw_response = maybe_valid_json[0];
                    } else {
                        console.log('Unable to fix malformed JSON');
                    }
                }

                return raw_response;
            }
        });

        $.ajax({
            type: 'POST',
            url: wc_checkout_params.checkout_url,
            data: data_serialized,
            dataType: 'json',
            success: function (result) {
                try {
                    if ('success' === result.result) {

                        $('#frm-cobru').attr('action', result.cobruUrl);
                        $('#frm-cobru input[name=name]').val(result.name);
                        $('#frm-cobru input[name=redirect_url]').val(result.return);
                        $('#frm-cobru input[name=callback_url]').val(result.callbackUrl);
                        $('#frm-cobru input[name=email]').val(result.email);
                        $('#frm-cobru input[name=phone]').val(result.phone);
                        $('#frm-cobru').submit();

                        return false;
                    } else if ('failure' === result.result) {
                        throw 'Result failure';
                    } else {
                        throw 'Invalid response';
                    }
                } catch (err) {

                    if (true === result.reload) {
                        window.location.reload();
                        return;
                    }

                    // Trigger update in case we need a fresh nonce
                    if (true === result.refresh) {
                        $(document.body).trigger('update_checkout');
                    }

                    // Add new errors
                    if (result.messages) {
                        cobru_submit_error(result.messages, $);
                    } else {
                        cobru_submit_error('<div class="woocommerce-error from-success">' + wc_checkout_params.i18n_checkout_error + '</div>', $);
                    }
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.log(jqXHR);
                console.log(textStatus);
                console.log(errorThrown);
                cobru_submit_error('<div class="woocommerce-error from-error">' + errorThrown + '</div>', $);
            }
        });

        return false;
    });
});