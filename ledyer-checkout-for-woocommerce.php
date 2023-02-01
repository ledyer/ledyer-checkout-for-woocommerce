<?php
/**
 * Plugin Name: Ledyer Checkout for WooCommerce
 * Plugin URI: https://github.com/ledyer/ledyer-checkout-for-woocommerce
 * Description: Ledyer Checkout payment gateway for WooCommerce.
 * Author: Maksimer/Ledyer
 * Author URI: https://www.maksimer.com/
 * Version: 1.4.0
 * Text Domain: ledyer-checkout-for-woocommerce
 * Domain Path: /languages
 *
 * WC requires at least: 3.2.0
 * WC tested up to: 4.7.0
 *
 * Copyright (c) 2017-2022 Maksimer
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

use Ledyer\Ledyer_Checkout_For_WooCommerce;

\defined( 'ABSPATH' ) || die();

require_once __DIR__ . '/classes/class-ledyer-singleton.php';
require_once __DIR__ . '/classes/class-ledyer-main.php';

/**
 * Required minimums and constants
 */
\define( 'LCO_WC_VERSION', Ledyer_Checkout_For_WooCommerce::VERSION );
\define( 'LCO_WC_MIN_PHP_VER', '5.6.0' );
\define( 'LCO_WC_MIN_WC_VER', '3.9.0' );
\define( 'LCO_WC_MAIN_FILE', __FILE__ );
\define( 'LCO_WC_PLUGIN_NAME', dirname( plugin_basename( LCO_WC_MAIN_FILE ) ) );
\define( 'LCO_WC_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
\define( 'LCO_WC_PLUGIN_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

function ledyer() {
	return Ledyer_Checkout_For_WooCommerce::instance();
}

ledyer();
