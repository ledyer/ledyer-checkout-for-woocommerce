if [ -z "$1" ]; then
    wp option update "porterbuddy_checkout_template" "new_universal_shipping"
else
    wp option update "porterbuddy_checkout_template" $1
fi
