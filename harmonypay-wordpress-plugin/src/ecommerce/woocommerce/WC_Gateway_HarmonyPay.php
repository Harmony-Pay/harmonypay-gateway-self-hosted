<?php

/**
	@brief		The gateway itself.
	@since		2017-12-08 16:36:26
**/
class WC_Gateway_HarmonyPay extends \WC_Payment_Gateway
{
	/**
		@brief		Constructor.
		@since		2017-12-15 08:06:14
	**/
	public function __construct()
	{
		$this->id					= \harmonypay\ecommerce\woocommerce\WooCommerce::$gateway_id;
		$this->method_title			= $this->get_method_title();
		$this->method_description	= $this->get_method_description();
		$this->has_fields			= true;

		$this->init_form_fields();
		$this->init_settings();

		$this->title = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		if ( $this->get_option( 'send_new_order_email' ) == 'yes' )
			add_action( 'woocommerce_checkout_order_processed', [ $this, 'woocommerce_checkout_order_processed' ], 20, 1 );

		add_action( 'harmonypay_generate_checkout_javascript_data', [ $this, 'harmonypay_generate_checkout_javascript_data' ] );
		add_action( 'woocommerce_email_before_order_table', [ $this, 'woocommerce_email_before_order_table' ], 10, 3 );
		add_filter( 'woocommerce_gateway_icon', [ $this, 'woocommerce_gateway_icon' ], 10, 2 );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'post_process_admin_options' ] );
		add_action( 'woocommerce_thankyou_harmonypay', [ $this, 'woocommerce_thankyou_harmonypay' ] );
	}

	/**
		@brief		Return the form fields used for the settings.
		@since		2017-12-30 21:14:39
	**/
	public function get_form_fields()
	{
		$r = [];
		$strings = HarmonyPay()->gateway_strings();

		$r[ 'enabled' ] = [
			'title'       => __( 'Enable/Disable', 'woocommerce' ),
			'label'       => __( 'Enable HarmonyPay', 'harmonypay' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		];
		$r[ 'test_mode' ] = [
			'title'       => __( 'Test mode', 'harmonypay' ),
			'label'       => __( 'Allow purchases to be made without sending any payment information to the HarmonyPay API server. No payments will be processed in this mode.', 'harmonypay' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		];
		$r[ 'send_new_order_email' ] = [
			'title'       => __( 'Send new order e-mail', 'harmonypay' ),
			'label'       => __( 'Send the new order e-mail before the order is paid. Normally, the new order e-mail is only sent when then order has been paid.', 'harmonypay' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		];		
		$r[ 'send_new_order_invoice' ] = [
			'title'       => __( 'Send invoice', 'harmonypay' ),
			'label'       => __( 'Send an e-mail invoice to the customer after purchase with the order details and payment instructions.', 'harmonypay' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		];
		$r[ 'email_instructions' ] = [
			'title'       => __( 'E-mail Instructions', 'harmonypay' ),
			'type'        => 'textarea',
			'description' => $strings->get( 'email_payment_instructions_description' ),
			'default' => $strings->get( 'email_payment_instructions' ),
		];
		$r[ 'online_instructions' ] = [
			'title'       => __( 'Online instructions', 'harmonypay' ),
			'type'        => 'textarea',
			'description' => $strings->get( 'online_payment_instructions_description' ),
			'default' => $strings->get( 'online_payment_instructions' ),
		];
		$r[ 'hide_woocommerce_order_overview' ] = [
			'title'			=> __( 'Hide order overview', 'harmonypay' ),
			'type'			=> 'checkbox',
			'default'     => 'yes',
			'description'	=> __( 'The order overview is usually placed above crypto payment instructions. Use this option to hide the overview and show the payment instructions higher up.', 'harmonypay' ),
		];
		$r[ 'title' ] = [
			'title' => __( 'Payment type name', 'harmonypay' ),
			'type' => 'text',
			'description' => __( 'This is the name of the payment option the user will see during checkout.', 'harmonypay' ),
			'default' => $strings->get( 'gateway_name' )
		];
		$r[ 'currency_selection_text' ] = [
			'title' => __( 'Text for currency selection', 'harmonypay' ),
			'type' => 'text',
			'description' => __( 'This is the text for the currency selection input.', 'harmonypay' ),
			'default' => $strings->get( 'currency_selection_text' ),
		];
		$r[ 'colorize_icons' ] = [
			'default'     => '',
			'description'	=> __( 'Show the cryptocurrency icons on the checkout page in various colors instead of the default black.', 'harmonypay' ),
			'options' => [
				// WooCommerce checkout icon coloring
				''  => __( 'Black', 'harmonypay' ),
				// WooCommerce checkout icon coloring
				'color'  => __( 'Brand colors', 'harmonypay' ),
				// WooCommerce checkout icon coloring
				'orange'  => __( 'Orange', 'harmonypay' ),
				// WooCommerce checkout icon coloring
				'white'  => __( 'White', 'harmonypay' ),
			],
			'title'			=> __( 'Colorize icons', 'harmonypay' ),
			'type'			=> 'select',
		];
		$r[ 'payment_complete_status' ] = [
			'title' => __( 'Payment complete status', 'harmonypay' ),
			'type' => 'select',
			'options' => [
				// Order status
				'wc-completed'  => __( 'Completed', 'woocommerce' ),
				// Order status
				'wc-on-hold'    => __( 'On hold', 'woocommerce' ),
				// Order status
				'wc-pending'    => __( 'Pending payment', 'woocommerce' ),
				// Order status
				''				=> __( 'Processing', 'woocommerce' ),
			],
			'description' => __( 'After payment is complete, change the order to this status.', 'harmonypay' ),
			'default' => '',
		];
		$r[ 'payment_timeout_hours' ] = [
			'title' => __( 'Payment timeout', 'harmonypay' ),
			'type' => 'number',
			'description' => __( 'How many hours to wait for the payment to come through before marking the order as abandoned.', 'harmonypay' ),
			'default' => 2,
			'custom_attributes' =>
			[
				'max' => 72,
				'min' => 1,
				'step' => 1,
			],
		];
		$r[ 'payment_amount_spread' ] = [
			'title' => __( 'Payment amount spread', 'harmonypay' ),
			'type' => 'number',
			'description' => __( 'If you are anticipating several purchases a second with the same currency, increase this amount to 100 or more to help prevent duplicate amount payments by slightly increasing the payment at random.', 'harmonypay' ),
			'default' => 0,
			'custom_attributes' =>
			[
				'max' => 1000,
				'min' => 0,
				'step' => 1,
			],
		];
		$r[ 'reset_to_defaults' ] = [
			'title'			=> __( 'Reset to defaults', 'harmonypay' ),
			'type'			=> 'checkbox',
			'default'     => 'no',
			'description'	=> __( 'If you wish to reset all of these settings to the defaults, check this box and save your changes.', 'harmonypay' ),
		];

    	return $r;
	}

	/**
		@brief		Return the description of this gateway.
		@since		2017-12-30 21:40:51
	**/
	public function get_method_description()
	{
		$r = __( 'Accept cryptocurrency payments directly into your wallet using the HarmonyPay service.', 'harmonypay' );

		try
		{
			HarmonyPay()->woocommerce->is_available_for_payment();
			$r .= HarmonyPay()->wallets()->build_enabled_string();
		}
		catch ( Exception $e )
		{
			$r .= "\n\n<em>" . __( 'You cannot currently accept any payments using this service:', 'harmonypay' ) . '</em> ' . $e->getMessage();
		}

		$r .= "\n" . sprintf( __( '%sConfigure your wallets here.%s', 'harmonypay' ),
			'<a href="options-general.php?page=harmonypay&tab=currencies">',
			'</a>'
		);


		$r = __( 'Accept cryptocurrency payments directly into your wallet using the HarmonyPay service.', 'harmonypay' );

		try
		{
			HarmonyPay()->woocommerce->check_decimal_setting();
		}
		catch ( Exception $e )
		{
			$r .= HarmonyPay()->error_message_box()->text( $e->getMessage() );
		}

		return $r;
	}

	/**
		@brief		Return the title of this gateway.
		@since		2017-12-30 21:43:30
	**/
	public function get_method_title()
	{
		return 'HarmonyPay';
	}

	/**
		@brief		Get the possible wallets as an array of select options.
		@since		2018-03-16 03:30:21
	**/
	public function get_wallet_options()
	{
		$cart = WC()->cart;
		$total = 0;

		if ( is_object( $cart ) )
			if ( ! $cart->is_empty() )
			{
				$total = $cart->get_total();
				// BTC is an html entity that needs to go.
				$total = html_entity_decode( $total );
				// Extract only numbers.
				$total = preg_replace( '/[^0-9\.\,]+/', '', $total );
			}

		// No total? Perhaps we are on the pay-for-order page.
		if ( $total == 0 )
		{
			if ( isset( $_REQUEST[ 'pay_for_order' ] ) )
			{
				$order = wc_get_order_id_by_order_key( $_REQUEST[ 'key' ] );
				if ( $order )
				{
					$order = new \WC_Order( $order );
					$total = $order->get_total();
				}
			}
		}

		return HarmonyPay()->get_checkout_wallet_options( [
			'amount' => $total,
			'original_currency' => get_woocommerce_currency(),
		] );

	}

	/**
		@brief		Init the form fields.
		@since		2017-12-09 22:05:11
	**/
	public function init_form_fields()
	{
		$this->form_fields = $this->get_form_fields();
	}

	/**
		@brief		Return our instance.
		@since		2018-02-09 18:42:13
	**/
	public static function instance()
	{
		$gateways = \WC_Payment_Gateways::instance();
		$gateway = $gateways->payment_gateways();
		return $gateway[ 'harmonypay' ];
	}

	/**
		@brief		Is this available?
		@since		2017-12-08 17:20:48
	**/
	public function is_available()
	{
		$mcc = HarmonyPay();
		// This is to keep the account locked, but still enable checkouts, since this is called twice during the checkout process.
		if ( isset( $mcc->woocommerce->__just_used ) )
			return true;

		if ( $this->get_option( 'enabled' ) != 'yes' )
			return;

		try
		{
			$mcc->woocommerce->is_available_for_payment();
			return true;
		}
		catch ( Exception $e )
		{
			return false;
		}
	}

	/**
		@brief		Add our stuff to the checkout data.
		@since		2018-04-25 15:58:40
	**/
	public function harmonypay_generate_checkout_javascript_data( $action )
	{
		$payment = $this->__current_payment;
		HarmonyPay()->api()->payments()->add_to_checkout_javascript_data( $action, $payment );

		// This is unique for WooCommerce.
		if ( $this->get_option( 'hide_woocommerce_order_overview' ) )
			$action->data->set( 'hide_woocommerce_order_overview', true );

		return $action;
	}

	/**
		@brief		Show the extra MCC payment fields on the checkout form.
		@since		2017-12-14 19:16:46
	**/
	function payment_fields()
	{
		HarmonyPay()->enqueue_js();
		HarmonyPay()->enqueue_css();

		$options = $this->get_wallet_options();

		$action = HarmonyPay()->new_action( 'woocommerce_payment_fields_wallet_options' );
		$action->options = $options;
		$action->execute();
		$options = $action->options;

		$currencies = array_keys( $options );

		woocommerce_form_field( 'hrp_currency_id',
		[
			'type' => 'select',
			'class' => [ 'hrp_currency' ],
			'default' => reset( $currencies ),
			'label' => esc_html__( $this->get_option( 'currency_selection_text' ) ),
			'options' => $options,
			'required' => true,
		] );
	}

	/**
		@brief		Internal method.
		@since		2018-01-26 14:00:58
	**/
	function process_payment( $order_id )
	{
		global $woocommerce;
		$order = new WC_Order( $order_id );

		// Mark as on-hold (we're awaiting the payment)
		$order->update_status( 'pending', __( 'Awaiting cryptocurrency payment', 'harmonypay' ) );

		// Reduce stock levels
		wc_reduce_stock_levels( $order_id );

		if ( isset( $_POST[ 'hrp_currency_id' ] ) )
			if ( $order->get_meta( '_hrp_payment_id' ) < 1 )
			{
				HarmonyPay()->debug( 'Intercepted _POST.' );
				$wc = HarmonyPay()->woocommerce;
				$wc->woocommerce_checkout_create_order( $order, [] );
				$order->save();
				// This is in order to send the payment to the API.
				$wc->woocommerce_checkout_update_order_meta( $order->get_id() );
				//HarmonyPay()->woocommerce->woocommerce_checkout_create_order( $order, [] );
				//$order->save();
			}

		HarmonyPay()->check_for_valid_payment_id( [
			'post_id' => $order_id,
		] );

		// Remove cart
		$woocommerce->cart->empty_cart();

		// Return thankyou redirect
		return [
			'result' => 'success',
			'redirect' => $this->get_return_url( $order )
		];
	}

	/**
		@brief		Handle the resetting of the settings.
		@since		2017-12-30 21:24:38
	**/
	public function post_process_admin_options()
	{
		$this->process_admin_options();

		$reset = $this->get_option( 'reset_to_defaults' );
		if ( $reset != 'yes' )
			return;
		// Reset all of the settings!
		update_option( $this->get_option_key(), apply_filters( 'woocommerce_settings_api_sanitized_fields_' . $this->id, [] ) );
	}

	/**
		@brief		Prevent the online payment instructions from losing its data-HTML.
		@since		2018-03-23 10:26:07
	**/
	public function validate_textarea_field( $key, $value )
	{
		if ( in_array( $key, [ 'online_instructions' ] ) )
			return trim( stripslashes( $value ) );
		return $this->validate_text_field( $key, $value );
	}

	/**
		@brief		woocommerce_email_before_order_table
		@since		2017-12-10 21:53:27
	**/
	public function woocommerce_email_before_order_table( $order, $sent_to_admin, $plain_text = false )
	{
		if ( $sent_to_admin )
			return;

		if ( $order->get_payment_method() != $this->id )
			return;

		// If paid, do not do anything.
		if ( ! $order->needs_payment() )
			return;

		$instructions = $this->get_option( 'email_instructions' );
		$payment = HarmonyPay()->api()->payments()->generate_payment_from_order( $order->get_id() );
		$instructions = HarmonyPay()->api()->payments()->replace_shortcodes( $payment, $instructions );
		echo wpautop( wptexturize( $instructions ) ) . PHP_EOL;
	}

	/**
		@brief		Override the img tag with a div with fonts.
		@since		2018-10-18 17:11:54
	**/
	public function woocommerce_gateway_icon( $icon, $id )
	{
		if ( $id != $this->id )
			return $icon;

		$color = '';
		$value = $this->get_option( 'colorize_icons' );
		switch ( $value )
		{
			case 'color':
			case 'yes':
				$color = "color";
				break;
			case 'orange':
			case 'white':
				$color = $value;
		}
		$r = sprintf( '<div class="hrp_currency_icons %s">', $color );

		$wallet_options = $this->get_wallet_options();
		$handled_currencies = [];
		foreach( $wallet_options as $currency_id => $ignore )
		{
			// Have we already handled this currency?
			if ( isset( $handled_currencies[ $currency_id ] ) )
				continue;

			$handled_currencies[ $currency_id ] = true;

			$r .= sprintf( '<i class="mcc-%s"></i>', $currency_id );
		}

		$r .= '</div>';

		return $r;
	}

	/**
		@brief		woocommerce_thankyou_harmonypay
		@since		2017-12-10 21:44:51
	**/
	public function woocommerce_thankyou_harmonypay( $order_id )
	{
		$order = wc_get_order( $order_id );
		HarmonyPay()->enqueue_js();
		HarmonyPay()->enqueue_css();
		$instructions = $this->get_option( 'online_instructions' );
		$payment = HarmonyPay()->api()->payments()->generate_payment_from_order( $order_id );
		$this->__current_payment = $payment;
		if ( ! $order->needs_payment() )
			$payment->paid = $order->is_paid();
		$instructions = HarmonyPay()->api()->payments()->replace_shortcodes( $payment,  $instructions );
		if ( ! $instructions )
			return;

		echo HarmonyPay()->generate_checkout_js();

		if ( ! $order->needs_payment() )
			return;

		echo wpautop( wptexturize( $instructions ) );
	}

	/**
		@brief		Send the new order e-mail when the order is placed.
		@see		https://stackoverflow.com/questions/45375143/send-an-email-notification-to-the-admin-for-pending-order-status-in-woocommerce
		@since		2021-06-30 23:06:57
	**/
	public function woocommerce_checkout_order_processed( $order_id )
	{
		// Get an instance of the WC_Order object
		$order = wc_get_order( $order_id );

		// Only for "pending" order status
		if( ! $order->has_status( 'pending' ) )
			return;

		// Send "New Email" notification
		WC()->mailer()
			->get_emails()['WC_Email_New_Order']
			->trigger( $order_id );
	}


}
