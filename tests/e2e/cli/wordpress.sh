echo Installing woocommerce
wp core download
wp config create
wp core install --title="${WP_SITE_TITLE}" --admin_user=${WP_ADMIN_USER} --admin_password=${WP_ADMIN_PASS} \
  --admin_email=${WP_EMAIL} --skip-email --url=${SITEHOST}

echo Installing plugin and themes

wp plugin install woocommerce --activate --version=${WC_VERSION}
wp theme install storefront
wp theme activate storefront
wp plugin activate ${PLUGIN_NAME}


echo Installing $PLUGIN_NAME options
echo "The script you are running has basename `basename "$0"`, dirname `dirname "$0"`"

wp option update "woocommerce_store_address" "Ringvägen 100" --autoload=yes
wp option update "woocommerce_store_city" "Stockholm" --autoload=yes
wp option update "woocommerce_default_country" "SE" --autoload=yes
wp option update "woocommerce_store_postcode" "11860" --autoload=yes
wp option update "woocommerce_store_phone_number" "55555555" --autoload=yes
wp option update "ledyer_dev_public_token" "${LEDYER_DEV_MERCHANT_ID}" --autoload=yes
wp option update "ledyer_dev_api_key" "${LEDYER_DEV_SHARED_SECRET}" --autoload=yes

wp option delete "woocommerce_lco_settings"
echo "The script you are running has basename `basename "$0"`, dirname `dirname "$0"`"
wp option add "woocommerce_lco_settings" --format=json < $(dirname "$0")/ledyer_config.json
wp option patch update  "woocommerce_cod_settings" "enabled" "no"


wp option set woocommerce_currency "NOK"
wp option set woocommerce_product_type "physical"
wp option set woocommerce_allow_tracking "no"
wp option set --format=json woocommerce_stripe_settings '{"enabled":"no","create_account":false,"email":false}'
wp option set --format=json woocommerce_ppec_paypal_settings '{"reroute_requests":false,"email":false}'
wp option set --format=json woocommerce_cheque_settings '{"enabled":"no"}'
wp option set --format=json woocommerce_bacs_settings '{"enabled":"no"}'
wp option set --format=json woocommerce_cod_settings '{"enabled":"yes"}'

wp wc --user=admin tool run install_pages

wp wc shipping_zone create --name=Norway --user=admin
wp wc shipping_zone_method create 1 --method_id=porter_buddy --user=admin

echo Creating products
wp wc product create --name="Product 1" --type=simple --sku="PRODUCT-1" --regular_price=200 --user=admin
wp wc product create --name="Product 2" --type=simple --sku="PRODUCT-2" --regular_price=300 --user=admin