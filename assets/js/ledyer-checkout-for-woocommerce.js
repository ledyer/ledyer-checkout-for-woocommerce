/* global lco_params */

jQuery( function( $ ) {

    // Check if we have params.
    if ( 'undefined' === typeof lco_params ) {
        return false;
    }

    var lco_wc = {
        bodyEl: $( 'body' ),
        checkoutFormSelector: $( 'form.checkout' ),

        // Payment method.
        paymentMethodEl: $( 'input[name="payment_method"]' ),
        paymentMethod: '',
        selectAnotherSelector: '#ledyer-checkout-select-other',

        // Form fields.
        shippingUpdated: false,
        blocked: false,

        preventPaymentMethodChange: false,

        timeout: null,
        interval: null,

        // True or false if we need to update the Ledyer order. Set to false on initial page load.
        ledyerUpdateNeeded: false,
        shippingEmailExists: false,
        shippingPhoneExists: false,

        /**
         * Triggers on document ready.
         */
        documentReady: function() {
            lco_wc.log( lco_params );
            if ( 0 < lco_wc.paymentMethodEl.length ) {
                lco_wc.paymentMethod = lco_wc.paymentMethodEl.filter( ':checked' ).val();
            } else {
                lco_wc.paymentMethod = 'lco';
            }

            if( 'lco' ===  lco_wc.paymentMethod ){
                $( '#ship-to-different-address-checkbox' ).prop( 'checked', true);
            }

            if( ! lco_params.pay_for_order ) {
                lco_wc.moveExtraCheckoutFields();
            }
        },

        /**
         * Suspends the Ledyer Iframe
         */
        lcoSuspend: function() {
            if (  window.ledyer ) {
                window.ledyer.api.suspend();
                console.log('suspend');
            }
        },

        /**
         * Resumes the LCO Iframe
         */
        lcoResume: function() {
            var isBlocked = $('form.checkout').find( '.blockUI' );

            if ( window.ledyer ) {
                //console.log( $('form.checkout').find( '.blockUI' ) );
                window.ledyer.api.resume();
                console.log('resume');
            }
        },

        /**
         * When the customer changes from LCO to other payment methods.
         * @param {Event} e
         */
        changeFromLco: function( e ) {
            e.preventDefault();

            $( lco_wc.checkoutFormSelector ).block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });

            $.ajax({
                type: 'POST',
                dataType: 'json',
                data: {
                    lco: false,
                    nonce: lco_params.change_payment_method_nonce
                },
                url: lco_params.change_payment_method_url,
                success: function( data ) {},
                error: function( data ) {},
                complete: function( data ) {
                    lco_wc.log( data.responseJSON );
                    window.location.href = data.responseJSON.data.redirect;
                }
            });
        },

        /**
         * When the customer changes to LCO from other payment methods.
         */
        maybeChangeToLco: function() {
            if ( ! lco_wc.preventPaymentMethodChange ) {
                lco_wc.log( $( this ).val() );

                if ( 'lco' === $( this ).val() ) {
                    $( '.woocommerce-info' ).remove();

                    $( lco_wc.checkoutFormSelector ).block({
                        message: null,
                        overlayCSS: {
                            background: '#fff',
                            opacity: 0.6
                        }
                    });

                    $.ajax({
                        type: 'POST',
                        data: {
                            lco: true,
                            nonce: lco_params.change_payment_method_nonce
                        },
                        dataType: 'json',
                        url: lco_params.change_payment_method_url,
                        success: function( data ) {},
                        error: function( data ) {},
                        complete: function( data ) {
                            lco_wc.log( data.responseJSON );
                            window.location.href = data.responseJSON.data.redirect;
                        }
                    });
                }
            }
        },

        /**
         * Moves all non standard fields to the extra checkout fields.
         */
        moveExtraCheckoutFields: function() {

            // Move order comments.
            $( '.woocommerce-additional-fields' ).appendTo( '#lco-extra-checkout-fields' );
            var form = $( 'form[name="checkout"] input, form[name="checkout"] select, textarea' );
            var checkout_add_ons_moved = false;
            for ( i = 0; i < form.length; i++ ) {
                var name = form[i].name.replace('[]', '\\[\\]'); // Escape any empty "array" keys to prevent errors.
                // Check if field is inside the order review.
                if( $( 'table.woocommerce-checkout-review-order-table' ).find( form[i] ).length ) {
                    continue;
                }

                // Check if this is a standard field.
                if ( -1 === $.inArray( name, lco_params.standard_woo_checkout_fields ) ) {
                    // This is not a standard Woo field, move to our div.
                    if ( 'wc_checkout_add_ons' === $( 'p#' + name + '_field' ).parent().attr('id') ) { // Check if this is an add on field.
                        if( ! checkout_add_ons_moved ) {
                            checkout_add_ons_moved = true;
                            $( 'div#wc_checkout_add_ons' ).appendTo( '#lco-extra-checkout-fields' );
                        }
                    } else if ( 0 < $( 'p#' + name + '_field' ).length ) {
                        if (name === 'shipping_phone') {
                            lco_wc.shippingPhoneExists = true;
                        }
                        if (name === 'shipping_email') {
                            lco_wc.shippingEmailExists =  true;
                        }
                        $( 'p#' + name + '_field' ).appendTo( '#lco-extra-checkout-fields' );
                    } else {
                        $( 'input[name="' + name + '"]' ).closest( 'p' ).appendTo( '#lco-extra-checkout-fields' );
                    }
                }
            }
        },

        /**
         * Updates the cart in case of a change in product quantity.
         */
        updateCart: function() {
            lco_wc.lcoSuspend( true );
            $.ajax({
                type: 'POST',
                url: lco_params.update_cart_url,
                data: {
                    checkout: $( 'form.checkout' ).serialize(),
                    nonce: lco_params.update_cart_nonce
                },
                dataType: 'json',
                success: function( data ) {
                },
                error: function( data ) {
                },
                complete: function( data ) {
                    $( 'body' ).trigger( 'update_checkout' );
                    lco_wc.lcoResume();
                }
            });
        },

        /**
         * Gets the Ledyer order and starts the order submission
         */
        getLedyerOrder: function() {
            console.log( 'getLedyerOrder' );
            lco_wc.preventPaymentMethodChange = true;
            $( '.woocommerce-checkout-review-order-table' ).block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });

            var ajax = $.ajax({
                type: 'POST',
                url: lco_params.get_ledyer_order_url,
                data: {
                    nonce: lco_params.get_ledyer_order_nonce
                },
                dataType: 'json',
                success: function( data ) {
                    lco_wc.setCustomerData( data.data );

                    // Check Terms checkbox, if it exists.
                    if ( 0 < $( 'form.checkout #terms' ).length ) {
                        $( 'form.checkout #terms' ).prop( 'checked', true );
                    }
                    console.log( 'success' );
                },
                error: function( data ) {
                    console.log( 'error' );
                },
                complete: function( data ) {
                }
            });

            return ajax;
        },


        /**
         * Sets the customer data.
         * @param {array} data
         */
        setCustomerData: function( data ) {
            lco_wc.log( data );
            if ( 'billing_address' in data && data.billing_address !== null ) {
                // Billing fields.
                'billing_first_name' in data.billing_address ? $( '#billing_first_name' ).val(data.billing_address.billing_first_name ) : '';
                'billing_last_name' in data.billing_address ?  $( '#billing_last_name' ).val( data.billing_address.billing_last_name ) : '';
                'billing_company' in data.billing_address ?  $( '#billing_company' ).val( data.billing_address.billing_company ) : '';
                'billing_address_1' in data.billing_address  ?  $( '#billing_address_1' ).val( data.billing_address.billing_address_1 ) : '';
                'billing_address_2' in data.billing_address ?   $( '#billing_address_2' ).val( data.billing_address.billing_address_2 ) : '';
                'billing_city' in data.billing_address ?  $( '#billing_city' ).val( data.billing_address.billing_city ) : '';
                'billing_postcode' in data.billing_address ?  $( '#billing_postcode' ).val( data.billing_address.billing_postcode ) : '';
                'billing_phone' in data.billing_address ?  $( '#billing_phone' ).val( data.billing_address.billing_phone ) : '';
                'billing_email' in data.billing_address ?  $( '#billing_email' ).val( data.billing_address.billing_email ) : '';
                'billing_country' in data.billing_address ?  $( '#billing_country' ).val( data.billing_address.billing_country.toUpperCase() ) : '';
                'billing_state' in data.billing_address ?   $( '#billing_state' ).val( data.billing_address.billing_state ) : '';
                // Trigger changes
                $('#billing_email').change();
                $('#billing_email').blur();
            }

            if ( 'shipping_address' in data && data.shipping_address !== null ) {
                $( '#ship-to-different-address-checkbox' ).prop( 'checked', true);

                // Shipping fields.
                'shipping_first_name' in data.shipping_address ?  $( '#shipping_first_name' ).val( data.shipping_address.shipping_first_name ) : '';
                'shipping_last_name' in data.shipping_address ? $( '#shipping_last_name' ).val( data.shipping_address.shipping_last_name ) : '';
                'shipping_company' in data.shipping_address ? $( '#shipping_company' ).val( data.shipping_address.shipping_company ) : '';
                'shipping_address_1' in data.shipping_address ? $( '#shipping_address_1' ).val( data.shipping_address.shipping_address_1 ) : '';
                'shipping_address_2' in data.shipping_address ?  $( '#shipping_address_2' ).val( data.shipping_address.shipping_address_2 ) : '';
                'shipping_city' in data.shipping_address ?  $( '#shipping_city' ).val( data.shipping_address.shipping_city ) : '';
                'shipping_postcode' in data.shipping_address ?  $( '#shipping_postcode' ).val( data.shipping_address.shipping_postcode ) : '';
                'shipping_country' in data.shipping_address ?  $( '#shipping_country' ).val( data.shipping_address.shipping_country.toUpperCase() ) : '';
                'shipping_state' in data.shipping_address ?  $( '#shipping_state' ).val( data.shipping_address.shipping_state ) : '';

                // extra shipping fields (email, phone).
                if (lco_wc.shippingEmailExists === true && $('#shipping_email')) {
                    $( '#shipping_email' ).val( ( ( 'shipping_email' in data.shipping_address ) ? data.shipping_address.shipping_email : '' ) );
                }
                if (lco_wc.shippingPhoneExists === true && $('#shipping_phone')) {
                    $( '#shipping_phone' ).val( ( ( 'shipping_phone' in data.shipping_address ) ? data.shipping_address.shipping_phone : '' ) );
                }
            }
        },

        /**
         * Logs the message to the ledyer checkout log in WooCommerce.
         * @param {string} message
         */
        logToFile: function( message ) {
            $.ajax(
                {
                    url: lco_params.log_to_file_url,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        message: message,
                        nonce: lco_params.log_to_file_nonce
                    }
                }
            );
        },

        /**
         * Logs messages to the console.
         * @param {string} message
         */
        log: function( message ) {
            if ( lco_params.logging ) {
                console.log( message );
            }
        },

        placeLedyerOrder: function( order_in_sessions = false ) {
            lco_wc.blocked = true;
            lco_wc.getLedyerOrder().done( function(response) {
                if(response.success ) {
                    $( '.woocommerce-checkout-review-order-table' ).block({
                        message: null,
                        overlayCSS: {
                            background: '#fff',
                            opacity: 0.6
                        }
                    });
                    $.ajax({
                        type: 'POST',
                        url: lco_params.submit_order,
                        data: $('form.checkout').serialize(),
                        dataType: 'json',
                        success: function( data ) {
                            try {
                                if ( 'success' === data.result ) {
                                    lco_wc.logToFile( 'Successfully placed order.' );
                                    let url = new URL(data.redirect);

                                    if( order_in_sessions ) {
                                        url.searchParams.append('lco_pending', 'yes');
                                    } else {
                                        url.searchParams.append('lco_pending', 'no');
                                    }

                                    window.location.href = url.toString();
                                    //callback({ should_proceed: true });
                                } else {
                                    //console.log(data, 33);
                                    throw 'Result failed';
                                }
                            } catch ( err ) {
                                if ( data.messages )  {
                                    lco_wc.logToFile( 'Checkout error | ' + data.messages );
                                } else {
                                    lco_wc.logToFile( 'Checkout error | No message' + err );
                                }
                            }
                        },
                        error: function( data ) {
                            try {
                                lco_wc.logToFile( 'AJAX error | ' + JSON.stringify(data) );
                            } catch( e ) {
                                lco_wc.logToFile( 'AJAX error | Failed to parse error message.' );
                            }
                        }
                    });
                } else {
                    lco_wc.logToFile( 'Failed to get the order from Ledyer.' );
                }
            });
        },

        /**
         * Initiates the script.
         */
        init: function() {
            $( document ).ready( lco_wc.documentReady );

            //lco_wc.bodyEl.on( 'update_checkout', lco_wc.triggerBillingText );
            //lco_wc.bodyEl.on('update_ledyer_order', lco_wc.lcoSuspend());
            //lco_wc.bodyEl.on('updated_ledyer_order', lco_wc.lcoResume());

            if ( 0 < $( 'form.checkout #terms' ).length ) {
                $( 'form.checkout #terms' ).prop( 'checked', true );
            }

            //lco_wc.bodyEl.on( 'change', 'input[type="text"]', lco_wc.triggerBillingText );
            //lco_wc.bodyEl.on( 'update_lco_text_fields', lco_wc.lcoSuspend );
            //lco_wc.bodyEl.on( 'updated_lco_text_fields', lco_wc.lcoResume );
            //lco_wc.bodyEl.on( 'updated_checkout', function( data ) {  console.log(data); } );
            lco_wc.bodyEl.on( 'update_checkout',  lco_wc.lcoSuspend );
            lco_wc.bodyEl.on( 'updated_checkout', lco_wc.lcoResume );
            //lco_wc.bodyEl.on( 'updated_checkout',  lco_wc.updateLedyerOrder );


            //lco_wc.bodyEl.on( 'updated_checkout', lco_wc.maybeDisplayShippingPrice );
            lco_wc.bodyEl.on( 'change', 'input.qty', lco_wc.updateCart );
            lco_wc.bodyEl.on( 'change', 'input[name="payment_method"]', lco_wc.maybeChangeToLco );
            lco_wc.bodyEl.on( 'click', lco_wc.selectAnotherSelector, lco_wc.changeFromLco );


            $( document ).on('ledyerCheckoutOrderComplete', function ( event ) {
                lco_wc.logToFile( 'ledyerCheckoutOrderComplete from Ledyer triggered' );
                if( lco_params.pay_for_order ) {
                    console.log('should_proceed: true');
                    //callback({ should_proceed: true });
                } else {
                    lco_wc.placeLedyerOrder( false );
                }
            });

            $( document ).on('ledyerCheckoutOrderPending', function ( event ) {
                lco_wc.logToFile( 'ledyerCheckoutOrderPending from Ledyer triggered' );
                if( lco_params.pay_for_order ) {
                    console.log('should_proceed: true');
                    //callback({ should_proceed: true });
                } else {
                    lco_wc.placeLedyerOrder( true );
                }
            });
        },
    }

    lco_wc.init();
});
