<?php

namespace harmonypay\ecommerce\woocommerce;

use Exception;

/**
	@brief		Handle checkouts in WooCommerce.
	@since		2017-12-08 16:30:20
**/
class WooCommerce
	extends \harmonypay\ecommerce\Ecommerce
{
	/**
		@brief		The ID of the gateway.
		@since		2017-12-08 16:45:27
	**/
	public static $gateway_id = 'harmonypay';

	/**
		@brief		Init!
		@since		2017-12-07 19:34:05
	**/
	public function _construct()
	{
		$this->add_action( 'harmonypay_hourly' );
		$this->add_action( 'harmonypay_cancel_payment' );
		$this->add_action( 'harmonypay_complete_payment' );
		$this->add_action( 'harmonypay_refund_payment' );
		$this->add_action( 'template_redirect' );
		//$this->add_action( 'before_woocommerce_pay' );
		$this->add_action( 'wcs_new_order_created' );
		$this->add_filter( 'wcs_renewal_order_meta' );
		$this->add_filter( 'woocommerce_get_price_html', 'hpc_woocommerce_get_price_html', 100, 2 );
		$this->add_filter( 'woocommerce_cart_totals_order_total_html', 'hpc_woocommerce_cart_totals_order_total_html', 100 );
		$this->add_filter( 'woocommerce_get_order_item_totals', 'hpc_woocommerce_get_order_item_totals', 10, 3 );
		$this->add_action( 'woocommerce_admin_order_data_after_order_details' );
		$this->add_action( 'woocommerce_checkout_create_order', 10, 2 );
		$this->add_action( 'woocommerce_checkout_update_order_meta' );
		$this->add_filter( 'woocommerce_currencies' );
		$this->add_filter( 'woocommerce_currency_symbol', 10, 2 );
		$this->add_filter( 'woocommerce_get_checkout_payment_url', 10, 2 );
		$this->add_action( 'woocommerce_order_status_cancelled' );
		$this->add_action( 'woocommerce_order_status_refunded' );
		$this->add_action( 'woocommerce_order_status_completed' );
		$this->add_filter( 'woocommerce_payment_gateways' );
		$this->add_action( 'woocommerce_review_order_before_payment' );
		$this->add_action( 'woocommerce_sections_general' );
	}


	/**
		@brief		On the order received page, shows the total in fiat+crypto or just crypto.
		@since		2018-11-07 18:21:07
	**/
	function hpc_woocommerce_get_order_item_totals( $total_rows, $order, $tax_display )
	{
		// Display the total in ETH
		$currency_id = 'ONE';

		// Is MCC installed?
		if ( ! function_exists( 'harmonypay' ) )
			return $total_rows;

		if ( ! isset( $total_rows[ 'order_total' ] ) )
			return $total_rows;

		// Retrieve all of our currencies.
		$currencies = HarmonyPay()->currencies();
		$currency = $currencies->get( $currency_id );
		// Is this currency known?
		if ( ! $currency )
			return $total_rows;

		$total = $order->get_total();

		$new_price = $currency->convert( get_woocommerce_currency(), $total );
		$new_total = $new_price  . ' ' . $currency_id;

		// This displays FIAT / CRYPTO.
		$total_rows[ 'order_total' ][ 'value' ] .= ' / ' . $new_total;

		// This displays only CRYPTO.
		// $total_rows[ 'order_total' ][ 'value' ] = $new_total;

		return $total_rows;
	}
	

	// Show product prices in fiat + crypto
	function hpc_woocommerce_get_price_html( $text, $product )
	{
		// Is MCC installed?
		if ( ! function_exists( 'harmonypay' ) )
			return $text;
		// Retrieve all of our currencies.
		$currencies = HarmonyPay()->currencies();
		// Change this to your preferred currency symbol
		$show_currency = 'ONE';
		$currency = $currencies->get( $show_currency );
		// Is this currency known?
		if ( ! $currency )
			return $text;
		$new_price = $currency->convert( get_woocommerce_currency(), $product->get_price() );
		return $text . ' | ' . $new_price  . ' ' . $show_currency;
	}


	/**
		@brief		Display the cart total in the normal currency and / or crypto.
		@since		2018-11-07 15:18:24
	**/
	function hpc_woocommerce_cart_totals_order_total_html( $text )
	{
		// Display the cart total in ETH
		$currency_id = 'ONE';
		
		// Is MCC installed?
		if ( ! function_exists( 'harmonypay' ) )
			return $text;
		// Retrieve all of our currencies.
		$currencies = HarmonyPay()->currencies();
		$currency = $currencies->get( $currency_id );
		// Is this currency known?
		if ( ! $currency )
			return $text;
		// We need the total without any symbols.
		$total = WC()->cart->get_total();
		// BTC is an html entity that needs to go.
		$total = html_entity_decode( $total );
		// Extract only numbers.
		$total = preg_replace( '/[^0-9\.\,]+/', '', $total );
		$new_price = $currency->convert( get_woocommerce_currency(), $total );
		$new_total = $new_price  . ' ' . $currency_id;
		
		// Uncomment this line to only display the total in crypto.
		//return $new_total;
		
		// This displays FIAT / CRYPTO
		return $text . ' / ' . $new_total;
	}


	/**
		@brief		If this is an MCC order, redirect to order received immediately.
		@since		2019-12-11 22:56:26
	**/
	public function before_woocommerce_pay()
	{
		// Extract the order ID.
		global $wp;
		$order_id = intval( $wp->query_vars['order-pay'] );

		// Order must be valid.
		$order = new \WC_Order( $order_id );
		if ( ! $order )
			return;

		// Is this an mcc transaction?
		$hrp_currency_id = $order->get_meta( '_hrp_currency_id' );
		if ( ! $hrp_currency_id )
			return;

		// And now redirect the buyer to the correct page.
		$url = $order->get_checkout_order_received_url();

		wp_redirect( $url );
		exit;
	}

	/**
		@brief		Check to see if WC has the correct amount of decimals set.
		@since		2018-06-14 12:43:58
	**/
	public function check_decimal_setting()
	{
		$wc_currency = get_woocommerce_currency();
		$currency = HarmonyPay()->currencies()->get( $wc_currency );
		if ( ! $currency )
			return;
		// Get the WC decimal precision.
		$wc_decimals = get_option( 'woocommerce_price_num_decimals' );
		if ( $wc_decimals == $currency->decimal_precision )
			return;
		throw new Exception( sprintf( "Since you are using virtual currency %s as your WooCommerce currency, please change the decimal precision from %s to match MyCyyptoCheckout's: %s", $wc_currency, $wc_decimals, $currency->decimal_precision ) );
	}

	/**
		@brief		Is MCC available for payments on this WC installation?
		@return		True if avaiable, else an exception containing the reason why it is not.
		@since		2017-12-23 08:56:28
	**/
	public function is_available_for_payment()
	{
		$account = HarmonyPay()->api()->account();
		$account->is_available_for_payment();

		// We need to be able to convert this currency.
		$wc_currency = get_woocommerce_currency();

		// Do we know about this virtual currency?
		$wallet = HarmonyPay()->wallets()->get_dustiest_wallet( $wc_currency );
		if ( ! $wallet )
			if ( ! $account->get_physical_exchange_rate( $wc_currency ) )
				throw new Exception( sprintf( 'Your WooCommerce installation is using an unknown currency: %s', $wc_currency ) );

		return true;
	}

	/**
		@brief		Hourly cron.
		@since		2017-12-24 12:10:14
	**/
	public function harmonypay_hourly()
	{
		if ( ! function_exists( 'WC' ) )
			return;
		try
		{
			HarmonyPay()->api()->payments()->send_unsent_payments();
		}
		catch( Exception $e )
		{
			$this->debug( $e->getMessage() );
		}
	}

	/**
		@brief		Payment was abanadoned.
		@since		2018-01-06 15:59:11
	**/
	public function harmonypay_cancel_payment( $action )
	{
		$this->do_with_payment_action( $action, function( $action, $order_id )
		{
			if ( ! function_exists( 'WC' ) )
				return;

			$order = wc_get_order( $order_id );
			if ( ! $order )
				return;

			// Consider this action finished as soon as we find the order.
			$action->applied++;

			// Only cancel is the order is unpaid.
			if ( $order->get_status() != 'pending' )
				return HarmonyPay()->debug( 'WC order %d on blog %d is not unpaid. Can not cancel.', $order_id, get_current_blog_id() );

			HarmonyPay()->debug( 'Marking WC payment %s on blog %d as cancelled.', $order_id, get_current_blog_id() );
			$order->update_status( 'cancelled', 'Payment timed out.' );
			do_action( 'woocommerce_cancelled_order', $order->get_id() );
		} );
	}


	/**
		@brief		Refund payment.
		@since		2018-01-06 15:59:11
	**/
	public function harmonypay_refund_payment( $payment, $refund_reason = '' )
	{
		$this->do_with_payment_action( $payment, function( $action, $order_id )
		{
			if ( ! function_exists( 'WC' ) )
				return;

			$order = wc_get_order( $order_id );
			if ( ! $order )
				return;

			// Consider this action finished as soon as we find the order.
			$action->applied++;
			$payment = $action->payment;

			// Only cancel is the order is unpaid.
			if ( $order->get_status() == 'refunded' )
				return HarmonyPay()->debug( 'WC order %d on blog %d has been already refunded.', $order_id, get_current_blog_id() );
			
			// Get Items
			$order_items = $order->get_items();
			
			// Refund Amount
			$refund_amount = 0;

			// Prepare line items which we are refunding
			$line_items = array();

			if ( $order_items ) {
				foreach( $order_items as $item_id => $item ) {
				  
				  $item_meta = $order->get_item_meta( $item_id );
				  
				  $tax_data = $item_meta['_line_tax_data'];
				  
				  $refund_tax = 0;
			
				  if( is_array( $tax_data[0] ) ) {
			
					$refund_tax = array_map( 'wc_format_decimal', $tax_data[0] );
			
				  }
			
				  $refund_amount = wc_format_decimal( $refund_amount ) + wc_format_decimal( $item_meta['_line_total'][0] );
			
				  $line_items[ $item_id ] = array( 
					'qty' => $item_meta['_qty'][0], 
					'refund_total' => wc_format_decimal( $item_meta['_line_total'][0] ), 
					'refund_tax' =>  $refund_tax );
				  
				}
			}
			  
			// Order Items were processed. We can now create a refund
			$refund = wc_create_refund( array(
				'amount'         => $refund_amount,
				'reason'         => $refund_reason,
				'order_id'       => $order_id,
				'line_items'     => $line_items,
				'refund_payment' => true
				));

			HarmonyPay()->debug( 'Marking WC payment %s on blog %d as refunded.', $order_id, get_current_blog_id() );
			$order->update_status( 'refunded', 'Payment Refunded.' );
			do_action( 'woocommerce_refunded_order', $order->get_id(), $refund->get_id() );
			return $refund;
		} );
	}

	/**
		@brief		harmonypay_complete_payment
		@since		2017-12-26 10:17:13
	**/
	public function harmonypay_complete_payment( $payment )
	{
		$this->do_with_payment_action( $payment, function( $action, $order_id )
		{
			if ( ! function_exists( 'WC' ) )
				return;

			$order = wc_get_order( $order_id );
			if ( ! $order )
				return;

			// Consider this action finished as soon as we find the order.
			$action->applied++;

			$payment = $action->payment;

			HarmonyPay()->debug( 'Marking WC payment %s on blog %d as paid.', $order_id, get_current_blog_id() );
			$order->payment_complete( $payment->transaction_id );

			// Since WC is not yet loaded properly, we have to load the gateway settings ourselves.
			$options = get_option( 'woocommerce_harmonypay_settings', true );
			$options = maybe_unserialize( $options );
			if ( isset( $options[ 'payment_complete_status' ] ) )
				if ( $options[ 'payment_complete_status' ] != '' )
				{
					// The default is '', which means don't do anything.
					HarmonyPay()->debug( 'Marking WC payment %s on blog %d as %s.',
						$order_id,
						get_current_blog_id(),
						$options[ 'payment_complete_status' ]
					);
					$order->set_status( $options[ 'payment_complete_status' ] );
					$order->save();
				}
		} );
	}

	/**
		@brief		Maybe redirect to the order recieved page for Waves transactions.
		@since		2019-07-27 19:43:13
	**/
	public function template_redirect()
	{
		if ( ! isset( $_GET[ 'txId' ] ) )	// This is what waves adds.
			return;
		if ( count( $_GET ) !== 1 )			// The waves payment API strips out every parameter.
			return;
		if ( ! is_order_received_page() )	// It at least returns the buyer to the order received page.
			return;

		// Extract the order ID.
		global $wp;
		$order_id = intval( $wp->query_vars['order-received'] );

		// Order must be valid.
		$order = new \WC_Order( $order_id );
		if ( ! $order )
			return;

		// Is this an mcc transaction?
		$hrp_currency_id = $order->get_meta( '_hrp_currency_id' );
		if ( ! $hrp_currency_id )
			return;

		// And now redirect the buyer to the correct page.
		$url = $order->get_checkout_order_received_url();

		wp_redirect( $url );
		exit;
	}

	/**
		@brief		Since sub orders are cloned, we need to remove the payment info.
		@since		2020-03-18 20:21:01
	**/
	public function wcs_new_order_created( $order )
	{
		HarmonyPay()->debug( 'Deleting payment ID for subscription order %s', $order->get_id() );
		$order->delete_meta_data( '_hrp_payment_id' );
		$order->save();
		return $order;
	}

	/**
		@brief		Remove our MCC meta since WCS is nice enough to copy ALL meta from old, expired orders.
		@since		2020-03-20 15:40:02
	**/
	public function wcs_renewal_order_meta( $order_meta )
	{
		HarmonyPay()->debug( 'Order meta %s', $order_meta );
		foreach( $order_meta as $index => $meta )
		{
			// Remove all MCC meta.
			if ( strpos( $meta[ 'meta_key' ], '_hrp_' ) === 0 )
				unset( $order_meta[ $index ] );
		}
		HarmonyPay()->debug( 'Order meta %s', $order_meta );
		return $order_meta;
	}

	/**
		@brief		woocommerce_admin_order_data_after_order_details
		@since		2017-12-14 20:35:48
	**/
	public function woocommerce_admin_order_data_after_order_details( $order )
	{
		if ( $order->get_payment_method() != static::$gateway_id )
			return;

		$amount = $order->get_meta( '_hrp_amount' );

		$r = '';
		$r .= sprintf( '<h3>%s</h3>',
			__( 'HarmonyPay details', 'woocommerce' )
		);

		$attempts = $order->get_meta( '_hrp_attempts' );
		$payment_id = $order->get_meta( '_hrp_payment_id' );

		if ( $payment_id > 0 )
		{
			if ( $payment_id == 1 )
				$payment_id = __( 'Test', 'harmonypay' );
			$r .= sprintf( '<p class="form-field form-field-wide">%s</p>',
				// Expecting 123 BTC to xyzabc
				sprintf( __( 'HarmonyPay payment ID: %s', 'harmonypay'),
					$payment_id
				)
			);
		}
		else
		{
			if ( $attempts > 0 )
				$r .= sprintf( '<p class="form-field form-field-wide">%s</p>',
					sprintf( __( '%d attempts made to contact the API server.', 'harmonypay'),
						$attempts
					)
				);
		}

		if ( $order->is_paid() )
			$r .= sprintf( '<p class="form-field form-field-wide">%s</p>',
				// Received 123 BTC to xyzabc
				sprintf( __( 'Received %s&nbsp;%s<br/>to %s', 'harmonypay'),
					$amount,
					$order->get_meta( '_hrp_currency_id' ),
					$order->get_meta( '_hrp_to' )
				)
			);
		else
		{
			$r .= sprintf( '<p class="form-field form-field-wide">%s</p>',
				// Expecting 123 BTC to xyzabc
				sprintf( __( 'Expecting %s&nbsp;%s<br/>to %s', 'harmonypay'),
					$amount,
					$order->get_meta( '_hrp_currency_id' ),
					$order->get_meta( '_hrp_to' )
				)
			);
		}

		echo $r;
	}

	/**
		@brief		Cancel an order on the server.
		@since		2018-03-25 22:28:25
	**/
	public function woocommerce_order_status_cancelled( $order_id )
	{
		$order = wc_get_order( $order_id );
		$payment_id = $order->get_meta( '_hrp_payment_id' );
		if ( $payment_id < 2 )		// 1 is for test mode.
			return;
		HarmonyPay()->debug( 'Cancelling payment %d for order %s', $payment_id, $order_id );
		HarmonyPay()->api()->payments()->cancel( $payment_id );
	}

	/**
		@brief		Refund an order on the server.
		@since		2019-04-22 11:50:06
	**/
	public function woocommerce_order_status_refunded( $order_id )
	{
		$order = wc_get_order( $order_id );
		$payment_id = $order->get_meta( '_hrp_payment_id' );
		if ( $payment_id < 2 )		// 1 is for test mode.
			return;
		HarmonyPay()->debug( 'Refunding payment %d for order %s', $payment_id, $order_id );
		HarmonyPay()->api()->payments()->refund( $payment_id );
	}

	/**
		@brief		Complete an order on the server.
		@since		2019-04-22 11:50:06
	**/
	public function woocommerce_order_status_completed( $order_id )
	{
		$order = wc_get_order( $order_id );
		$payment_id = $order->get_meta( '_hrp_payment_id' );
		if ( $payment_id < 2 )		// 1 is for test mode.
			return;
		HarmonyPay()->debug( 'Completing payment %d for order %s', $payment_id, $order_id );
		HarmonyPay()->api()->payments()->complete( $payment_id );
	}

	/**
		@brief		Add the meta fields.
		@since		2017-12-10 21:35:29
	**/
	public function woocommerce_checkout_create_order( $order, $data )
	{
		if ( $order->get_payment_method() != static::$gateway_id )
			return;

		$account = HarmonyPay()->api()->account();
		$available_for_payment = $account->is_available_for_payment();

		HarmonyPay()->debug( 'Creating order! Available: %d', $available_for_payment );

		$currency_id = sanitize_text_field( $_POST[ 'hrp_currency_id' ] );

		// Get the gateway instance.
		$gateway = \WC_Gateway_HarmonyPay::instance();

		// All of the below is just to calculate the amount.
		$mcc = HarmonyPay();

		$order_total = $order->get_total();
		$currencies = $mcc->currencies();
		$currency = $currencies->get( $currency_id );
		$wallet = $mcc->wallets()->get_dustiest_wallet( $currency_id );
		$address = $wallet->get_address();
		$wallet->use_it();
		$mcc->wallets()->save();

		$woocommerce_currency = get_woocommerce_currency();
		$amount = $mcc->markup_amount( [
			'amount' => $order_total,
			'currency_id' => $currency_id,
		] );
		HarmonyPay()->debug( 'Marking up total: %s %s -> %s', $order_total, $woocommerce_currency, $amount );
		$amount = $currency->convert( $woocommerce_currency, $amount );
		if ( $amount == 0 )
		{

			$account = HarmonyPay()->api()->account();
			HarmonyPay()->debug( 'Error with conversion! %s', $account );
		}
		else
			HarmonyPay()->debug( 'Conversion: %s', $amount );
		$next_amount = $amount;
		$precision = $currency->get_decimal_precision();

		$next_amount = $currency->find_next_available_amount( $next_amount );
		$next_amounts = [ $next_amount ];

		// Increase the next amount.
		$spread = intval( $gateway->get_option( 'payment_amount_spread' ) );
		for( $counter = 0; $counter < $spread ; $counter++ )
		{
			// Help find_next_available_amount by increasing the value by 1.
			$next_amount = HarmonyPay()->increase_floating_point_number( $next_amount, $precision );
			// And now find the next amount.
			$next_amounts []= $next_amount;
		}

		HarmonyPay()->debug( 'Next amounts: %s', $next_amounts );

		// Select a next amount at random.
		$amount = $next_amounts[ array_rand( $next_amounts ) ];

		HarmonyPay()->debug( 'Amount selected: %s', $amount );

		// Are we paying in the same currency as the native currency?
		if ( $currency_id == get_woocommerce_currency() )
		{
			// Make sure the order total matches our expected amount.
			$order->set_total( $amount );
			$order->save();
		}

		$payment = HarmonyPay()->api()->payments()->create_new();
		$payment->amount = $amount;
		$payment->currency_id = $currency_id;

		$test_mode = $gateway->get_option( 'test_mode' );
		if ( $test_mode == 'yes' )
		{
			$mcc->debug( 'WooCommerce gateway is in test mode.' );
			$payment_id = 1;		// Nobody will ever have 1 again, so it's safe to use.
		}
		else
			$payment_id = 0;		// 0 = not sent.
		$order->update_meta_data( '_hrp_payment_id', $payment_id );

		// Save the non-default payment timeout hours.
		$payment->timeout_hours = intval( $gateway->get_option( 'payment_timeout_hours' ) );

		$wallet->apply_to_payment( $payment );
		HarmonyPay()->autosettlements()->apply_to_payment( $payment );

		HarmonyPay()->debug( 'Payment as created: %s', $payment );

		// This stuff should be handled by the Payment object, but the order doesn't exist yet...
		$order->update_meta_data( '_hrp_amount', $payment->amount );
		$order->update_meta_data( '_hrp_confirmations', $payment->confirmations );
		$order->update_meta_data( '_hrp_created_at', $payment->created_at );
		$order->update_meta_data( '_hrp_currency_id', $payment->currency_id );
		$order->update_meta_data( '_hrp_payment_timeout_hours', $payment->timeout_hours );
		$order->update_meta_data( '_hrp_to', $payment->to );
		$order->update_meta_data( '_hrp_payment_data', $payment->data );

		$action = HarmonyPay()->new_action( 'woocommerce_create_order' );
		$action->order = $order;
		$action->payment = $payment;
		$action->execute();

		// We want to keep the account locked, but still enable the is_available gateway check to work for the rest of this session.
		$this->__just_used = true;
	}

	/**
		@brief		Maybe send this order to the API.
		@since		2017-12-25 16:21:06
	**/
	public function woocommerce_checkout_update_order_meta( $order_id )
	{
		$order = wc_get_order( $order_id );
		if ( $order->get_payment_method() != static::$gateway_id )
			return;
		if ( $order->get_meta( '_hrp_payment_id' ) != 0 )
			return;
		do_action( 'harmonypay_send_payment', $order_id );
		do_action( 'harmonypay_woocommerce_order_created', $order );

		$gateway = \WC_Gateway_HarmonyPay::instance();
		$send_new_order_invoice = $gateway->get_option( 'send_new_order_invoice' );
		if ( $send_new_order_invoice != 'no' )
			WC()->mailer()->customer_invoice( $order );
	}

	/**
		@brief		woocommerce_currencies
		@since		2021-05-02 10:54:15
	**/
	public function woocommerce_currencies( $currencies )
	{
		$wallets = harmonypay()->wallets();
		$hrp_currencies = harmonypay()->currencies();
		foreach( $wallets as $wallet )
		{
			$currency_id = $wallet->get_currency_id();
			$name = $hrp_currencies->get( $currency_id )->get_name();
			$currencies[ $currency_id ] = $name;
		}
		return $currencies;
	}

	/**
		@brief		woocommerce_currency_symbol
		@since		2021-05-02 11:00:05
	**/
	public function woocommerce_currency_symbol( $currency_symbol, $currency )
	{
		$hrp_currencies = harmonypay()->currencies();
		if ( ! $hrp_currencies->has( $currency ) )
			return $currency_symbol;
		return $currency;
	}

	/**
		@brief		woocommerce_get_checkout_payment_url
		@since		2018-06-12 21:05:04
	**/
	public function woocommerce_get_checkout_payment_url( $url, $order )
	{
		// We only override the payment URL for orders that are handled by us.
		if ( $order->get_meta( '_hrp_payment_id' ) < 1 )
			return $url;
		return $order->get_checkout_order_received_url();
	}

	/**
		@brief		woocommerce_sections_general
		@since		2018-06-14 15:10:12
	**/
	public function woocommerce_sections_general()
	{
		try
		{
			HarmonyPay()->woocommerce->check_decimal_setting();
		}
		catch ( Exception $e )
		{
			echo HarmonyPay()->error_message_box()->text( $e->getMessage() );
		}
	}

	/**
		@brief		woocommerce_payment_gateways
		@since		2017-12-08 16:31:34
	**/
	public function woocommerce_payment_gateways( $gateways )
	{
		require_once( __DIR__ . '/WC_Gateway_HarmonyPay.php' );
		$gateways []= 'WC_Gateway_HarmonyPay';
		return $gateways;
	}

	/**
		@brief		Apply a width fix for some themes. Otherwise the width (incl amount) gets way too long.
		@since		2018-03-12 19:09:01
	**/
	public function woocommerce_review_order_before_payment()
	{
		echo '<style>.wc_payment_method #hrp_currency_id_field select#hrp_currency_id { width: 100%; }</style>';
	}
}
