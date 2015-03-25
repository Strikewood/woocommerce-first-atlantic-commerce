<?php
/*
 * Plugin Name: WooCommerce First Atlantic Commerce Gateway
 * Plugin URI:  https://github.com/Strikewood/woocommerce-first-atlantic-commerce
 * Description: First Atlantic Commerce gateway extension for WooCommerce.
 * Version:     0.1
 * Author:      Strikewood Studios
 * Author URI:  http://strikewood.com/
 * License:     MIT
 * License URI: https://github.com/Strikewood/woocommerce-first-atlantic-commerce/blob/master/LICENSE
 */

if ( !defined('ABSPATH') ) exit;

function woocommerce_init_fac_gateway()
{
    if ( !class_exists('WC_Payment_Gateway') ) return;

    // Localisation
    load_plugin_textdomain('wc-gateway-fac', false, dirname( plugin_basename(__FILE__) ) . '/languages');

    // Includes
    include_once('includes/class-wc-gateway-fac.php');

    // Register the gateway in WC
    function woocommerce_register_fac_gateway($methods)
    {
        $methods[] = 'WC_Gateway_FirstAtlanticCommerce';

        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'woocommerce_register_fac_gateway');

    function woocommerce_fac_process_payment($order_id)
    {
        $fac = new WC_Gateway_FirstAtlanticCommerce;

        $fac->process_payment($order_id);
    }

    function woocommerce_fac_process_refund($order_id)
    {
        $fac = new WC_Gateway_FirstAtlanticCommerce;

        $fac->process_refund($order_id);
    }

    // Actions to capture or void authorized transactions
    add_action('woocommerce_order_status_on-hold_to_processing', 'woocommerce_fac_process_payment');
    add_action('woocommerce_order_status_on-hold_to_completed', 'woocommerce_fac_process_payment');
    add_action('woocommerce_order_status_on-hold_to_cancelled', 'woocommerce_fac_process_refund');
    add_action('woocommerce_order_status_on-hold_to_refunded', 'woocommerce_fac_process_refund');
}
add_action('plugins_loaded', 'woocommerce_init_fac_gateway', 0);
