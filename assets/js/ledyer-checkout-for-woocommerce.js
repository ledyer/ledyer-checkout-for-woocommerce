/* global lco_params */

jQuery(function ($) {
  // Check if we have params.
  if ("undefined" === typeof lco_params) {
    return false;
  }

  var lco_wc = {
    bodyEl: $("body"),
    checkoutFormSelector: $("form.checkout"),

    // Payment method.
    paymentMethodEl: $('input[name="payment_method"]'),
    paymentMethod: "",
    selectAnotherSelector: "#ledyer-checkout-select-other",

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
    shippingFirstNameExists: false,
    shippingLastNameExists: false,

    /**
     * Triggers on document ready.
     */
    documentReady: function () {
      if (0 < lco_wc.paymentMethodEl.length) {
        lco_wc.paymentMethod = lco_wc.paymentMethodEl.filter(":checked").val();
      } else {
        lco_wc.paymentMethod = "lco";
      }

      if ("lco" === lco_wc.paymentMethod) {
        $("#ship-to-different-address-checkbox").prop("checked", true);
      }

      if (!lco_params.pay_for_order) {
        lco_wc.moveExtraCheckoutFields();
      }
    },

    /**
     * Suspends the Ledyer Iframe
     */
    lcoSuspend: function () {
      if (window.ledyer) {
        window.ledyer.api.suspend();
      }
    },

    /**
     * Resumes the LCO Iframe
     */
    lcoResume: function () {
      if (window.ledyer) {
        window.ledyer.api.resume();
      }
    },

    /**
     * When the customer changes from LCO to other payment methods.
     * @param {Event} e
     */
    changeFromLco: function (e) {
      e.preventDefault();

      $(lco_wc.checkoutFormSelector).block({
        message: null,
        overlayCSS: {
          background: "#fff",
          opacity: 0.6,
        },
      });

      $.ajax({
        type: "POST",
        dataType: "json",
        data: {
          lco: false,
          nonce: lco_params.change_payment_method_nonce,
        },
        url: lco_params.change_payment_method_url,
        success: function (data) {},
        error: function (data) {},
        complete: function (data) {
          window.location.href = data.responseJSON.data.redirect;
        },
      });
    },

    /**
     * When the customer changes to LCO from other payment methods.
     */
    maybeChangeToLco: function () {
      if (!lco_wc.preventPaymentMethodChange) {
        if ("lco" === $(this).val()) {
          $(".woocommerce-info").remove();

          $(lco_wc.checkoutFormSelector).block({
            message: null,
            overlayCSS: {
              background: "#fff",
              opacity: 0.6,
            },
          });

          $.ajax({
            type: "POST",
            data: {
              lco: true,
              nonce: lco_params.change_payment_method_nonce,
            },
            dataType: "json",
            url: lco_params.change_payment_method_url,
            success: function (data) {},
            error: function (data) {},
            complete: function (data) {
              window.location.href = data.responseJSON.data.redirect;
            },
          });
        }
      }
    },

    /**
     * Moves all non-standard fields to the extra checkout fields.
     */
    moveExtraCheckoutFields: () => {
      // Move additional fields to the specified div
      const wooAdditionalFields = document.querySelector(
        ".woocommerce-additional-fields"
      );
      const lcoExtraCheckoutFields = document.querySelector(
        "#lco-extra-checkout-fields"
      );

      if (!wooAdditionalFields || !lcoExtraCheckoutFields) return;

      lcoExtraCheckoutFields.appendChild(wooAdditionalFields);

      // Select all relevant input, select, and textarea elements within the form
      const formElements = document.querySelectorAll(
        'form[name="checkout"] input, form[name="checkout"] select, textarea'
      );
      let checkoutAddOnsMoved = false;

      formElements.forEach((element) => {
        const name = element.name.replace(/\[\]/g, "\\[\\]"); // Escape any empty "array" keys to prevent errors.

        // Don't do anything if this field is inside the order review
        if (
          document
            .querySelector("table.woocommerce-checkout-review-order-table")
            .contains(element)
        )
          return;

        //  Don't do anything if this is a standard Woo field
        if (lco_params.standard_woo_checkout_fields.includes(name)) return;

        const parentElement =
          document.querySelector(`p#${name}_field`)?.parentNode || null;

        // Move non-standard Woo fields to our div
        if (parentElement && parentElement.id === "wc_checkout_add_ons") {
          // Check if this is an add on field
          if (!checkoutAddOnsMoved) {
            checkoutAddOnsMoved = true;
            const checkoutAddOns = document.querySelector(
              "div#wc_checkout_add_ons"
            );
            if (checkoutAddOns) {
              lcoExtraCheckoutFields.appendChild(checkoutAddOns);
            }
          }
        } else if (parentElement) {
          [
            "shipping_phone",
            "shipping_email",
            "shipping_first_name",
            "shipping_last_name",
          ].forEach((fieldName) => {
            if (name === fieldName) {
              lco_wc[`${fieldName}Exists`] = true;
            }
          });
          lcoExtraCheckoutFields.appendChild(parentElement);
        } else {
          const closestP =
            document.querySelector(`input[name="${name}"]`)?.closest("p") ||
            null;
          if (closestP) {
            lcoExtraCheckoutFields.appendChild(closestP);
          }
        }
      });
    },

    /**
     * Updates the cart in case of a change in product quantity.
     */
    updateCart: function () {
      lco_wc.lcoSuspend(true);
      $.ajax({
        type: "POST",
        url: lco_params.update_cart_url,
        data: {
          checkout: $("form.checkout").serialize(),
          nonce: lco_params.update_cart_nonce,
        },
        dataType: "json",
        success: function (data) {},
        error: function (data) {},
        complete: function (data) {
          $("body").trigger("update_checkout");
          lco_wc.lcoResume();
        },
      });
    },

    /**
     * Gets the Ledyer order and starts the order submission
     */
    getLedyerOrder: function () {
      lco_wc.preventPaymentMethodChange = true;
      $(".woocommerce-checkout-review-order-table").block({
        message: null,
        overlayCSS: {
          background: "#fff",
          opacity: 0.6,
        },
      });

      var ajax = $.ajax({
        type: "POST",
        url: lco_params.get_ledyer_order_url,
        data: {
          nonce: lco_params.get_ledyer_order_nonce,
        },
        dataType: "json",
        success: function (data) {
          lco_wc.setCustomerData(data.data);

          // Check Terms checkbox, if it exists.
          if (0 < $("form.checkout #terms").length) {
            $("form.checkout #terms").prop("checked", true);
          }
        },
        error: function (data) {},
        complete: function (data) {},
      });

      return ajax;
    },

    /**
     * Sets the customer data.
     * @param {array} data
     */
    setCustomerData: (data) => {
      // Helper function to set value
      const setValue = (selector, value) => {
        const element = document.querySelector(selector);
        if (element) {
          element.value = value || "";
          if (selector === "#billing_email") {
            element.dispatchEvent(new Event("change"));
            element.dispatchEvent(new Event("blur"));
          }
        }
      };

      // Set billing address fields
      if (data?.billing_address) {
        setValue(
          "#billing_first_name",
          data.billing_address.billing_first_name
        );
        setValue("#billing_last_name", data.billing_address.billing_last_name);
        setValue("#billing_company", data.billing_address.billing_company);
        setValue("#billing_address_1", data.billing_address.billing_address_1);
        setValue("#billing_address_2", data.billing_address.billing_address_2);
        setValue("#billing_city", data.billing_address.billing_city);
        setValue("#billing_postcode", data.billing_address.billing_postcode);
        setValue("#billing_phone", data.billing_address.billing_phone);
        setValue("#billing_email", data.billing_address.billing_email);
        setValue(
          "#billing_country",
          data.billing_address.billing_country?.toUpperCase()
        );
        setValue("#billing_state", data.billing_address.billing_state);
      }

      // Set shipping address fields
      if (data?.shipping_address) {
        const shipDifferentAddressCheckbox = document.querySelector(
          "#ship-to-different-address-checkbox"
        );
        if (shipDifferentAddressCheckbox) {
          shipDifferentAddressCheckbox.checked = true;
        }

        setValue(
          "#shipping_first_name",
          data.shipping_address.shipping_first_name
        );
        setValue(
          "#shipping_last_name",
          data.shipping_address.shipping_last_name
        );
        setValue("#shipping_company", data.shipping_address.shipping_company);
        setValue(
          "#shipping_address_1",
          data.shipping_address.shipping_address_1
        );
        setValue(
          "#shipping_address_2",
          data.shipping_address.shipping_address_2
        );
        setValue("#shipping_city", data.shipping_address.shipping_city);
        setValue("#shipping_postcode", data.shipping_address.shipping_postcode);
        setValue(
          "#shipping_country",
          data.shipping_address.shipping_country?.toUpperCase()
        );
        setValue("#shipping_state", data.shipping_address.shipping_state);

        // Extra shipping fields
        if (lco_wc.shippingEmailExists)
          setValue("#shipping_email", data.shipping_address.shipping_email);
        if (lco_wc.shippingPhoneExists)
          setValue("#shipping_phone", data.shipping_address.shipping_phone);
        if (lco_wc.shippingFirstNameExists)
          setValue(
            "#shipping_first_name",
            data.shipping_address.shipping_first_name
          );
        if (lco_wc.shippingLastNameExists)
          setValue(
            "#shipping_last_name",
            data.shipping_address.shipping_last_name
          );
      }
    },

    /**
     * Logs the message to the ledyer checkout log in WooCommerce.
     * @param {string} message
     */
    logToFile: function (message) {
      $.ajax({
        url: lco_params.log_to_file_url,
        type: "POST",
        dataType: "json",
        data: {
          message: message,
          nonce: lco_params.log_to_file_nonce,
        },
      });
    },

    /**
     * Logs messages to the console.
     * @param {string} message
     */
    log: function (message) {
      if (lco_params.logging) {
        console.log(message);
      }
    },

    /**
     * Fails the Ledyer order.
     * @param {string} error_message
     */
    failOrder: function (
      error_message = "Kunde inte slutföra ordern. Var god försök igen. Om problemet kvarstår, vänligen kontakta kundsupport.",
      alert = null
    ) {
      window.ledyer.api.clientValidation({
        shouldProceed: false,
        message: {
          title: null, // Use default title in Ledyer checkout
          body: error_message,
        },
      });

      const className = lco_params.pay_for_order
        ? "div.woocommerce-notices-wrapper"
        : "form.checkout";

      // Update the checkout and reenable the form.
      $("body").trigger("update_checkout");

      const alert_message = alert ?? error_message;
      const error_div = `<div class="woocommerce-error">${alert_message}</div>`;

      // Print error messages, and trigger checkout_error, and scroll to notices.
      $(
        ".woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message"
      ).remove();
      $(className).prepend(
        `<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout"> ${error_div} </div>`
      );
      $(className).removeClass("processing").unblock();
      $(className)
        .find(".input-text, select, input:checkbox")
        .trigger("validate")
        .blur();
      $(document.body).trigger("checkout_error", [alert_message]);
      $("html, body").animate(
        {
          scrollTop: $(className).offset().top - 100,
        },
        1000
      );
    },

    cleanupForm: function (formElement) {
      const $inputs = formElement.find("input, select, textarea");
      // remove inputs with empty values
      const $inputsWithValue = $inputs.filter(function () {
        return $(this).val() !== "";
      });
      const serializedData = $inputsWithValue.serialize();

      return serializedData;
    },

    /**
     * Places the Ledyer order
     * @param {string} should_validate
     */
    placeLedyerOrder: function (should_validate = false) {
      lco_wc.blocked = true;
      lco_wc.getLedyerOrder().done(function (response) {
        if (response.success) {
          $(".woocommerce-checkout-review-order-table").block({
            message: null,
            overlayCSS: {
              background: "#fff",
              opacity: 0.6,
            },
          });

          sessionStorage.removeItem("ledyerWooRedirectUrl");

          $.ajax({
            type: "POST",
            url: lco_params.submit_order,
            data: lco_wc.cleanupForm($("form.checkout")),
            dataType: "json",
            success: function (data) {
              // data is an object with the following properties:
              // { result: "success" | "failure"; refresh: "boolean", reload: boolean, messages: string; }
              try {
                if ("success" === data.result) {
                  lco_wc.logToFile(
                    "Successfully created order in WooCommerce."
                  );
                  const url = new URL(data.redirect);
                  sessionStorage.setItem("ledyerWooRedirectUrl", url);

                  if (should_validate) {
                    window.ledyer.api.clientValidation({
                      shouldProceed: true,
                    });
                    // Ledyer will respond with a new event when order is complete
                    // So don't redirect just yet,
                    // eventually redirection will happen in ledyerCheckoutOrderComplete
                    return;
                  }
                  window.location.href = url.toString();
                } else {
                  throw "Result failed";
                }
              } catch (err) {
                if (data.messages) {
                  lco_wc.logToFile("Checkout error | " + data.messages);
                  lco_wc.failOrder(
                    "Vänligen kontrollera att alla uppgifter är korrekt ifyllda.",
                    data.messages
                  );
                } else {
                  lco_wc.logToFile("Checkout error | No message" + err);
                  lco_wc.failOrder();
                }
              }
            },
            error: function (data) {
              try {
                lco_wc.logToFile("AJAX error | " + JSON.stringify(data));
              } catch (e) {
                lco_wc.logToFile("AJAX error | Failed to parse error message.");
              }
              lco_wc.failOrder();
            },
          });
        } else {
          lco_wc.logToFile("Failed to get the order from Ledyer.");
          lco_wc.failOrder();
        }
      });
    },

    /**
     * Initiates the script.
     */
    init: function () {
      $(document).ready(lco_wc.documentReady);

      if (0 < $("form.checkout #terms").length) {
        $("form.checkout #terms").prop("checked", true);
      }

      lco_wc.bodyEl.on("update_checkout", lco_wc.lcoSuspend);
      lco_wc.bodyEl.on("updated_checkout", lco_wc.lcoResume);
      lco_wc.bodyEl.on("change", "input.qty", lco_wc.updateCart);
      lco_wc.bodyEl.on(
        "change",
        'input[name="payment_method"]',
        lco_wc.maybeChangeToLco
      );
      lco_wc.bodyEl.on(
        "click",
        lco_wc.selectAnotherSelector,
        lco_wc.changeFromLco
      );

      $(document).on("ledyerCheckoutOrderComplete", function (event) {
        lco_wc.logToFile("ledyerCheckoutOrderComplete from Ledyer triggered");
        if (!lco_params.pay_for_order) {
          const redirectUrl = sessionStorage.getItem("ledyerWooRedirectUrl");
          if (redirectUrl) {
            // This means that placeLedyerOrder was called successfully already
            // (Due to an earlier call caused by client validation)
            window.location.href = redirectUrl;
          }
        }
      });

      $(document).on("ledyerCheckoutOrderPending", function (event) {
        lco_wc.logToFile("ledyerCheckoutOrderPending from Ledyer triggered");
        if (!lco_params.pay_for_order) {
          lco_wc.placeLedyerOrder();
        }
      });

      $(document).on(
        "ledyerCheckoutWaitingForClientValidation",
        function (event) {
          lco_wc.logToFile(
            "ledyerCheckoutWaitingForClientValidation from Ledyer triggered"
          );

          if (lco_params.pay_for_order) {
            window.ledyer.api.clientValidation({
              shouldProceed: true,
            });
          } else {
            lco_wc.placeLedyerOrder(true);
          }
        }
      );
    },
  };

  lco_wc.init();
});
