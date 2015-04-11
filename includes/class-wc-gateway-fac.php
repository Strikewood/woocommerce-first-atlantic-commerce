<?php

use Omnipay\Omnipay;

if ( !defined('ABSPATH') ) exit;

/**
 * WC_Gateway_FirstAtlanticCommerce class
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_FirstAtlanticCommerce extends WC_Payment_Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->id                 = 'fac';
        $this->method_title       = __('First Atlantic Commerce', 'wc-gateway-fac');
        $this->method_description = __('First Atlantic Commerce works by adding credit card fields on the checkout and then sending the details to First Atlantic Commerce for verification.', 'wc-gateway-fac');
        $this->has_fields         = true;
        $this->supports           = [
            //'subscriptions',
            'products',
            'refunds',
            //'subscription_cancellation',
            //'subscription_reactivation',
            //'subscription_suspension',
            //'subscription_amount_changes',
            //'subscription_payment_method_change',
            //'subscription_date_changes',
            //'pre-orders',
            'default_credit_card_form'
        ];

        // Load the form fields
        $this->init_form_fields();

        // Load the settings
        $this->init_settings();

        // User defined settings
        $this->title             = $this->get_option('title');
        $this->description       = $this->get_option('description');
        $this->enabled           = $this->get_option('enabled');
        $this->testmode          = 'yes' === $this->get_option('testmode', 'no');
        $this->capture           = $this->get_option('capture', "yes") === "yes" ? true : false;
        //$this->saved_cards       = $this->get_option( 'saved_cards' ) === "yes" ? true : false;
        $this->merchant_id       = $this->testmode ? $this->get_option('test_merchant_id') : $this->get_option('merchant_id');
        $this->merchant_password = $this->testmode ? $this->get_option('test_merchant_password') : $this->get_option('merchant_password');

        // Hooks
        add_action('admin_notices', [$this, 'admin_notices']);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
    }

    /**
     * Notify of issues in wp-admin
     */
    public function admin_notices()
    {
        if ($this->enabled == 'no')
        {
            return;
        }

        // Check required fields
        if (!$this->merchant_id)
        {
            echo '<div class="error"><p>' . sprintf( __( 'First Atlantic Commerce error: Please enter your merchant id <a href="%s">here</a>', 'woocommerce-gateway-fac' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_fac' ) ) . '</p></div>';
            return;
        }
        elseif (!$this->merchant_password)
        {
            echo '<div class="error"><p>' . sprintf( __( 'First Atlantic Commerce error: Please enter your merchant password <a href="%s">here</a>', 'woocommerce-gateway-fac' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_fac' ) ) . '</p></div>';
            return;
        }

        // Check if enabled and force SSL is disabled
        if ( get_option('woocommerce_force_ssl_checkout') == 'no' ) {
            echo '<div class="error"><p>' . sprintf( __( 'First Atlantic Commerce is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout may not be secure! Please enable SSL and ensure your server has a valid SSL certificate - First Atlantic Commerce will only work in test mode.', 'woocommerce-gateway-fac' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '</p></div>';
            return;
        }
    }

    /**
     * Logging method
     *
     * @param  string $message
     *
     * @return void
     */
    public function log($message)
    {
        if ( empty($this->log) )
        {
            $this->log = new WC_Logger();
        }

        $this->log->add($this->id, $message);
    }

    /**
     * Check if the gateway is available for use
     *
     * @return bool
     */
    public function is_available()
    {
        $is_available = parent::is_available();

        // Only allow unencrypted connections when testing
        if (!is_ssl() && !$this->testmode)
        {
            $is_available = false;
        }

        // Required fields check
        if (!$this->merchant_id || !$this->merchant_password)
        {
            $is_available = false;
        }

        return $is_available;
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields()
    {
        $this->form_fields = apply_filters('wc_fac_settings', [
            'enabled' => [
                'title'       => __('Enable/Disable', 'woocommerce-gateway-fac'),
                'label'       => __('Enable FAC', 'woocommerce-gateway-fac'),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ],
            'title' => [
                'title'       => __('Title', 'woocommerce-gateway-fac'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-gateway-fac'),
                'default'     => __('Credit card', 'woocommerce-gateway-fac')
            ],
            'description' => [
                'title'       => __('Description', 'woocommerce-gateway-fac'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-gateway-fac'),
                'default'     => __('Pay with your credit card.', 'woocommerce-gateway-fac')
            ],
            'testmode' => [
                'title'       => __('Test mode', 'woocommerce-gateway-fac'),
                'label'       => __('Enable Test Mode', 'woocommerce-gateway-fac'),
                'type'        => 'checkbox',
                'description' => __('Place the payment gateway in test mode using test API credentials.', 'woocommerce-gateway-fac'),
                'default'     => 'yes'
            ],
            'merchant_id' => [
                'title'       => __('Live Merchant ID', 'woocommerce-gateway-fac'),
                'type'        => 'text',
                'description' => __('Get your API credentials from your merchant account.', 'woocommerce-gateway-fac'),
                'default'     => ''
            ],
            'merchant_password' => [
                'title'       => __('Live Merchant Password', 'woocommerce-gateway-fac'),
                'type'        => 'text',
                'description' => __('Get your API credentials from your merchant account.', 'woocommerce-gateway-fac'),
                'default'     => ''
            ],
            'test_merchant_id' => [
                'title'       => __('Test Merchant ID', 'woocommerce-gateway-fac'),
                'type'        => 'text',
                'description' => __('Get your API credentials from your merchant account.', 'woocommerce-gateway-fac'),
                'default'     => ''
            ],
            'test_merchant_password' => [
                'title'       => __('Test Merchant Password', 'woocommerce-gateway-fac'),
                'type'        => 'text',
                'description' => __('Get your API credentials from your merchant account.', 'woocommerce-gateway-fac'),
                'default'     => ''
            ],
            'capture' => [
                'title'       => __('Capture', 'woocommerce-gateway-fac'),
                'label'       => __('Capture charge immediately', 'woocommerce-gateway-fac'),
                'type'        => 'checkbox',
                'description' => __('Whether or not to immediately capture the charge. When unchecked, the charge issues an authorization and will need to be captured later. Uncaptured charges expire in 7 days.', 'woocommerce-gateway-fac'),
                'default'     => 'yes'
            ]/*,
            'saved_cards' => [
                'title'       => __('Saved cards', 'woocommerce-gateway-fac'),
                'label'       => __('Enable saved cards', 'woocommerce-gateway-fac'),
                'type'        => 'checkbox',
                'description' => __('If enabled, users will be able to pay with a saved card during checkout. Card details are saved on FAC servers, not on your store.', 'woocommerce-gateway-fac'),
                'default'     => 'no'
            ]*/
        ]);
    }

    /**
     * Setup the gateway object
     */
    public function setup_gateway()
    {
        $gateway = Omnipay::create('FirstAtlanticCommerce');

        $gateway->setMerchantId($this->merchant_id);
        $gateway->setMerchantPassword($this->merchant_password);

        if ($this->testmode)
        {
            $gateway->setTestMode(true);
        }

        return $gateway;
    }

    /**
     * Output payment fields
     *
     * @return void
     */
    public function payment_fields()
    {
        // Default credit card form
        $this->credit_card_form();
    }

    /**
     * Validate form fields
     *
     * @return bool
     */
    public function validate_fields()
    {
        $validated = true;

        if ( empty($_POST['fac-card-number']) )
        {
            wc_add_notice( $this->get_validation_error( __('Card Number', 'woocommerce-gateway-fac'), $_POST['fac-card-number'] ), 'error' );
            $validated = false;
        }
        if ( empty($_POST['fac-card-expiry']) )
        {
            wc_add_notice( $this->get_validation_error( __('Card Expiry', 'woocommerce-gateway-fac'), $_POST['fac-card-number'] ), 'error' );
            $validated = false;
        }
        if ( empty($_POST['fac-card-cvc']) )
        {
            wc_add_notice( $this->get_validation_error( __('Card Code', 'woocommerce-gateway-fac'), $_POST['fac-card-number'] ), 'error' );
            $validated = false;
        }

        return $validated;
    }

    /**
     * Get error message for form fields
     *
     * @param string $field
     * @param string $type
     * @return string
     */
    protected function get_validation_error($field, $type = 'undefined')
    {
        if ( $type === 'invalid' )
        {
            return sprintf( __( 'Please enter a valid %s.', 'woocommerce-gateway-fac' ), "<strong>$field</strong>" );
        }
        else
        {
            return sprintf( __( '%s is a required field.', 'woocommerce-gateway-fac' ), "<strong>$field</strong>" );
        }
    }

    /**
     * Can the order be processed?
     *
     * @param WC_Order $order
     *
     * @return bool
     */
    public function can_process_order($order)
    {
        return $order && $order->payment_method == 'fac';
    }

    /**
     * Process the payment and return the result
     *
     * @param int $order_id
     *
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = new WC_Order($order_id);

        if ( !$this->can_process_order($order) ) return;

        $transaction = $order->get_transaction_id();
        $captured    = get_post_meta($order_id, '_fac_captured', true);

        // Skip already captured transactions
        if ($captured) return;

        try
        {
            $gateway = $this->setup_gateway();

            $data = [
                'transactionId' => $order->get_order_number(),
                'amount'        => $this->get_order_total(),
                'currency'      => $order->order_currency
            ];

            // Already authorized transactions should be captured
            if ( $transaction && !$captured )
            {
                $response = $gateway->capture($data)->send();
            }
            else
            {
                $card_number = str_replace( [' ', '-'], '', wc_clean($_POST['fac-card-number']) );
                $card_cvv    = wc_clean($_POST['fac-card-cvc']);
                $card_expiry = preg_split('/\s?\/\s?/', wc_clean($_POST['fac-card-expiry']), 2);

                $data['card'] = [
                    'firstName'       => $order->billing_first_name,
                    'lastName'        => $order->billing_last_name,
                    'number'          => $card_number,
                    'expiryMonth'     => $card_expiry[0],
                    'expiryYear'      => $card_expiry[1],
                    'cvv'             => $card_cvv,
                    'billingAddress1' => $order->billing_address_1,
                    'billingAddress2' => $order->billing_address_2,
                    'billingCity'     => $order->billing_city,
                    'billingPostcode' => $order->billing_postcode,
                    'billingState'    => $order->billing_state,
                    'billingCountry'  => $order->billing_country,
                    'email'           => $order->billing_email
                ];

                // Capture in one pass if enabled, otherwise authorize
                if ($this->capture)
                {
                    $response = $gateway->purchase($data)->send();
                }
                else
                {
                    $response = $gateway->authorize($data)->send();
                }
            }

            if ( $response->isSuccessful() )
            {
                $reference = $response->getTransactionReference();

                // Captured transaction
                if ( ($transaction && !$captured) || (!$transaction && $this->capture) )
                {
                    // Store captured
                    update_post_meta($order_id, '_fac_captured', true);

                    // Complete payment
                    $order->payment_complete($reference);

                    // Add note to order
                    $order->add_order_note( sprintf( __('FAC transaction complete (ID: %s)', 'woocommerce-gateway-fac'), $reference ) );
                }
                // Authorized transaction
                else
                {
                    // Store captured
                    update_post_meta($order_id, '_transaction_id', $reference, true);
                    update_post_meta($order_id, '_fac_captured', false);

                    // Mark order as on-hold and add note
                    $order->update_status( 'on-hold', sprintf( __('FAC charge authorized (ID: %s). Process the order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-gateway-fac'), $reference ) );

                    // Reduce stock level
                    $order->reduce_order_stock();
                }

                // Clear cart
                WC()->cart->empty_cart();

                // Return thank you page redirect
                return [
                    'result'   => 'success',
                    'redirect' => $this->get_return_url($order)
                ];
            }
            else
            {
                throw new Exception( $response->getMessage(), $response->getCode() );
            }
        }
        catch (\Exception $e)
        {
            $message = 'Transaction Failed: '. $e->getCode() .' – '. $e->getMessage();

            $this->log($message);
            $order->add_order_note( __($message, 'woocommerce-gateway-fac') );

            // Friendly declined message
            if ( in_array( $e->getCode(), [2, 3, 4, 35, 38, 39] ) )
            {
                $message = __('Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction.', 'woocommerce') .' '. __('Please attempt your purchase again.', 'woocommerce');
            }

            // Friendly error message
            else
            {
                $message = __('Unfortunately your order cannot be processed as an error has occured.', 'woocommerce') .' '. __('Please attempt your purchase again.', 'woocommerce');
            }

            if ( !is_admin() || ( defined('DOING_AJAX') && DOING_AJAX ) )
            {
                wc_add_notice($message, 'error');
            }

            return;
        }
    }

    /**
     * Can the order be refunded?
     *
     * @param WC_Order $order
     *
     * @return bool
     */
    public function can_refund_order($order)
    {
        return $order && $order->payment_method == 'fac' && $order->get_transaction_id();
    }

    /**
     * Refund a charge
     *
     * @param int $order_id
     * @param float $amount
     *
     * @return bool
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);

        if ( !$this->can_refund_order($order) )
        {
            $this->log('Refund Failed: No transaction ID for FAC');
            return false;
        }

        $transaction = $order->get_transaction_id();
        $captured    = get_post_meta($order_id, '_fac_captured', true);

        if ( is_null($amount) )
        {
            $amount = $order->get_total();
        }

        try
        {
            $gateway = $this->setup_gateway();

            $data = [
                'transactionId' => $order->get_order_number(),
                'amount'        => $order->get_total()
            ];

            if ($captured)
            {
                $response = $gateway->refund($data)->send();
            }
            else
            {
                $response = $gateway->void($data)->send();
            }

            if ( $response->isSuccessful() )
            {
                $order->add_order_note( sprintf( __('Refunded %s', 'woocommerce-gateway-fac'), $data['amount'] ) );
                return true;
            }
            else
            {
                throw new Exception( $response->getMessage(), $response->getCode() );
            }
        }
        catch (\Exception $e)
        {
            $message = 'Refund Failed: '. $e->getCode() .' – '. $e->getMessage();

            $this->log($message);
            $order->add_order_note( __($message, 'woocommerce-gateway-fac') );

            return new WP_Error( 'fac-refund', __($e->getCode() .' – '.$e->getMessage(), 'woocommerce-gateway-fac') );
        }
    }
}
