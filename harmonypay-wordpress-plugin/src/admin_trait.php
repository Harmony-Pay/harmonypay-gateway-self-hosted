<?php

namespace harmonypay;

use Exception;

/**
	@brief		Handles admin things such as settings and currencies.
	@since		2017-12-09 07:05:04
**/
trait admin_trait
{
	/**
		@brief		Do some activation.
		@since		2017-12-09 07:12:19
	**/
	public function activate()
	{
		global $wpdb;

		// Rename the wallets key.
		if ( $this->is_network )
			$wpdb->update( $wpdb->sitemeta, [ 'meta_key' => 'harmonypay\HarmonyPay_wallets' ], [ 'meta_key' => 'harmonypay\HarmonyPay_' ] );
		else
			$wpdb->update( $wpdb->options, [ 'option_name' => 'HarmonyPay_wallets' ], [ 'option_name' => 'HarmonyPay_' ] );

		wp_schedule_event( time(), 'hourly', 'harmonypay_hourly' );

		// We need to run this as soon as the plugin is active.
		$next = wp_next_scheduled( 'harmonypay_retrieve_account' );
		wp_unschedule_event( $next, 'harmonypay_retrieve_account' );
		wp_schedule_single_event( time(), 'harmonypay_retrieve_account' );
	}

	/**
		@brief		Admin the account.
		@since		2017-12-11 14:20:17
	**/
	public function admin_account()
	{
		$form = $this->form();
		$form->id( 'account' );
		$r = '';

		if ( ! function_exists('curl_version') )
			$r .= $this->error_message_box()->_( __( 'Your PHP CURL module is missing. HarmonyPay may not work 100% well.', 'harmonypay' ) );

		/*$public_listing = $form->checkbox( 'public_listing' )
			->checked( $this->get_site_option( 'public_listing' ) )
			->description( __( 'Check the box and refresh your account if you want your webshop listed in the upcoming store directory on harmonypay.com. Your store name and URL will be listed.', 'harmonypay' ) )
			->label( __( 'Be featured in the MCC store directory?', 'harmonypay' ) );*/

		$retrieve_account = $form->primary_button( 'retrieve_account' )
			->value( __( 'Refresh your account data', 'harmonypay' ) );

		if ( $this->debugging() )
			$delete_account = $form->secondary_button( 'delete_account' )
				->value( __( 'Delete account data', 'harmonypay' ) );

		if ( $form->is_posting() )
		{
			$form->post();
			$form->use_post_values();

			if ( $this->debugging() )
				if ( $delete_account->pressed() )
				{
					$this->api()->account()->delete();
				}

			if ( $retrieve_account->pressed() )
			{
				/*if ( $public_listing->is_checked() )
					HarmonyPay()->update_site_option( 'public_listing', true );
				else
					HarmonyPay()->delete_site_option( 'public_listing' );
				*/

				$result = $this->harmonypay_retrieve_account();
				HarmonyPay()->debug($result);
				if ( $result )
				{
					$r .= $this->info_message_box()->_( __( 'Account data refreshed!', 'harmonypay' ) );
					// Another safeguard to ensure that unsent payments are sent as soon as possible.
					try
					{
						HarmonyPay()->api()->payments()->send_unsent_payments();
					}
					catch( Exception $e )
					{
						$r .= $this->error_message_box()->_( $e->getMessage() );
					}
				}
				else
					$r .= $this->error_message_box()->_( __( 'Error refreshing your account data. Please enable debug mode to find the error.', 'harmonypay' ) );
			}
		}

		$account = $this->api()->account();

		if ( ! $account->is_valid() )
			$r .= $this->admin_account_invalid();
		else
			$r .= $this->admin_account_valid( $account );

		$r .= $form->open_tag();
		$r .= $form->display_form_table();
		$r .= $form->close_tag();

		echo $r;
	}

	/**
		@brief		Show the invalid account text.
		@since		2017-12-12 11:07:42
	**/
	public function admin_account_invalid()
	{
		$r = '';
		$r .= wpautop( __( 'It appears as if HarmonyPay was unable to retrieve your account data from the API server.', 'harmonypay' ) );
		$r .= wpautop( __( 'Click the Refresh your account data button below to try and retrieve your account data again.', 'harmonypay' ) );
		return $r;
	}

	/**
		@brief		Show the valid account text.
		@since		2017-12-12 11:07:42
	**/
	public function admin_account_valid( $account )
	{
		$r = '';

		try
		{
			$this->api()->account()->is_available_for_payment();
		}
		catch ( Exception $e )
		{
			$message = sprintf( '%s: %s',
				__( 'Payments using HarmonyPay are currently not available', 'harmonypay' ),
				$e->getMessage()
			);
			$r .= $this->error_message_box()->_( $message );
		}

		$table = $this->table();
		$table->caption()->text( __( 'Your HarmonyPay account details', 'harmonypay' ) );

		$row = $table->head()->row()->hidden();
		// Table column name
		$row->th( 'key' )->text( __( 'Key', 'harmonypay' ) );
		// Table column name
		$row->td( 'details' )->text( __( 'Details', 'harmonypay' ) );

		if ( $this->debugging() )
		{
			$row = $table->head()->row();
			$row->th( 'key' )->text( __( 'API key', 'harmonypay' ) );
			$row->td( 'details' )->text( $account->get_domain_key() );

		}

		$row = $table->head()->row();
		$row->th( 'key' )->text( __( 'Server name', 'harmonypay' ) );
		$row->td( 'details' )->text( $this->get_client_url() );

		$row = $table->head()->row();
		$row->th( 'key' )->text( __( 'Account data refreshed', 'harmonypay' ) );
		$row->td( 'details' )->text( static::wordpress_ago( $account->data->updated ) );

		if ( $account->has_license() )
		{
		/*	$row = $table->head()->row();
			$text =  __( 'Your license expires', 'harmonypay' );
			$row->th( 'key' )->text( $text );
			$time = $account->get_license_valid_until();
			$text = sprintf( '%s (%s)',
				$this->local_date( $time ),
				human_time_diff( $time )
			);
			$row->td( 'details' )->text( $text );*/
		}
		else
		{
		}

		/*$row = $table->head()->row();
		if ( $account->has_license() )
			$text =  __( 'Extend your license', 'harmonypay' );
		else
			$text =  __( 'Purchase a license for unlimited payments', 'harmonypay' );
		$row->th( 'key' )->text( $text );
		$url = $this->api()->get_purchase_url();
		$text = $account->has_license() ? __( 'Extend my license', 'harmonypay' ) : __( 'Add an unlimited license to my cart', 'harmonypay' );
		$url = sprintf( '<a href="%s">%s</a> &rArr;',
			$url,
			$text
		);
		$row->td( 'details' )->text( $url );

		$row = $table->head()->row();
		$row->th( 'key' )->text( __( 'Payments remaining this month', 'harmonypay' ) );
		$row->td( 'details' )->text( $account->get_payments_left_text() );*/

		$row = $table->head()->row();
		$row->th( 'key' )->text( __( 'Payments processed', 'harmonypay' ) );
		$row->td( 'details' )->text( $account->get_payments_used() );

		$row = $table->head()->row();
		$row->th( 'key' )->text( __( 'Physical currency exchange rates updated', 'harmonypay' ) );
		$row->td( 'details' )->text( static::wordpress_ago( $account->data->physical_exchange_rates->timestamp ) );

		$row = $table->head()->row();
		$row->th( 'key' )->text( __( 'Cryptocurrency exchange rates updated', 'harmonypay' ) );
		$row->td( 'details' )->text( static::wordpress_ago( $account->data->virtual_exchange_rates->timestamp ) );

		$wallets = $this->wallets();
		if ( count( $wallets ) > 0 )
		{
			$currencies = $this->currencies();
			$exchange_rates = [];
			foreach( $wallets as $index => $wallet )
			{
				$id = $wallet->get_currency_id();
				if ( isset( $exchange_rates[ $id ] ) )
					continue;
				$currency = $currencies->get( $id );
				if ( $currency )
					$exchange_rates[ $id ] = sprintf( '1 USD = %s %s', $currency->convert( 'USD', 1 ), $id );
				else
					$exchange_rates[ $id ] = sprintf( 'Currency %s is no longer available!', $id );
			}
			ksort( $exchange_rates );
			$exchange_rates = implode( "\n", $exchange_rates );
			$exchange_rates = wpautop( $exchange_rates );
		}
		else
			$exchange_rates = 'n/a';

		$row = $table->head()->row();
		$row->th( 'key' )->text( __( 'Exchange rates for your currencies', 'harmonypay' ) );
		$row->td( 'details' )->text( $exchange_rates );

		if ( $this->debugging() )
		{
			if ( count( (array)$account->data->payment_amounts ) > 0 )
			{
				$row = $table->head()->row();
				$row->th( 'key' )->text( __( 'Reserved amounts', 'harmonypay' ) );
				$text = '';
				$payment_amounts = (array) $account->data->payment_amounts;
				ksort( $payment_amounts );
				foreach( $payment_amounts as $currency_id => $amounts )
				{
					$amounts = (array)$amounts;
					ksort( $amounts );
					$amounts = implode( ', ', array_keys( $amounts ) );
					$text .= sprintf( '<p>%s: %s</p>', $currency_id, $amounts );
				}
				$row->td( 'details' )->text( $text );

				$row = $table->head()->row();
				$row->th( 'key' )->text( __( 'Next scheduled account data update', 'harmonypay' ) );
				$next = wp_next_scheduled( 'harmonypay_retrieve_account' );
				$row->td( 'details' )->text( date( 'Y-m-d H:i:s', $next ) );
			}
		}

		$r .= $table;

		return $r;
	}

	/**
		@brief		Admin the currencies.
		@since		2017-12-09 07:06:56
	**/
	public function admin_currencies()
	{
		$form = $this->form();
		$form->id( 'currencies' );
		$r = '';

		wp_enqueue_script( 'jquery-ui-sortable' );

		$account = $this->api()->account();
		if ( ! $account->is_valid() )
		{
			$r .= $this->error_message_box()->_( __( 'You cannot modify your currencies until you have a valid account. Please see the Accounts tab.', 'harmonypay' ) );
			echo $r;
			return;
		}

		$table = $this->table();
		$table->css_class( 'currencies' );

		$table->data( 'nonce', wp_create_nonce( 'harmonypay_sort_wallets' ) );

		$table->bulk_actions()
			->form( $form )
			// Bulk action for wallets
			->add( __( 'Delete', 'harmonypay' ), 'delete' )
			// Bulk action for wallets
			->add( __( 'Disable', 'harmonypay' ), 'disable' )
			// Bulk action for wallets
			->add( __( 'Enable', 'harmonypay' ), 'enable' )
			// Bulk action for wallets
			->add( __( 'Mark as used', 'harmonypay' ), 'mark_as_used' )
			// Bulk action for wallets
			->add( __( 'Reset sorting', 'harmonypay' ), 'reset_sorting' );

		// Assemble the current wallets into the table.
		$row = $table->head()->row();
		$table->bulk_actions()->cb( $row );
		// Table column name
		$row->th( 'currency' )->text( __( 'Currency', 'harmonypay' ) );
		// Table column name
		$row->th( 'wallet' )->text( __( 'Wallet', 'harmonypay' ) );
		// Table column name
		$row->th( 'details' )->text( __( 'Details', 'harmonypay' ) );

		$wallets = $this->wallets();

		foreach( $wallets as $index => $wallet )
		{
			$row = $table->body()->row();
			$row->data( 'index', $index );
			$table->bulk_actions()->cb( $row, $index );
			$currency = $this->currencies()->get( $wallet->get_currency_id() );

			// If the currency is no longer available, delete the wallet.
			if ( ! $currency )
			{
				$wallets->forget( $index );
				$wallets->save();
				continue;
			}

			$currency_text = sprintf( '%s %s', $currency->get_name(), $currency->get_id() );
			$row->td( 'currency' )->text( $currency_text );

			// Address
			$url = add_query_arg( [
				'tab' => 'edit_wallet',
				'wallet_id' => $index,
			] );
			$url = sprintf( '<a href="%s" title="%s">%s</a>',
				$url,
				__( 'Edit this currency', 'harmonypay' ),
				$wallet->get_address()
			);
			$row->td( 'wallet' )->text( $url );

			// Details
			$details = $wallet->get_details();
			$details = implode( "\n", $details );
			$row->td( 'details' )->text( wpautop( $details ) );
		}

		$fs = $form->fieldset( 'fs_add_new' );
		// Fieldset legend
		$fs->legend->label( __( 'Add new currency / wallet', 'harmonypay' ) );

		$wallet_currency = $fs->select( 'currency' )
			->css_class( 'currency_id' )
			->description( __( 'Which currency shall the new wallet belong to?', 'harmonypay' ) )
			// Input label
			->label( __( 'Currency', 'harmonypay' ) );
		$this->currencies()->add_to_select_options( $wallet_currency );

		$text = __( 'The address of your wallet to which you want to receive funds.', 'harmonypay' );
		$text .= ' ';
		$text .= __( 'If your currency has HD wallet support, you can add your public key when editing the wallet.', 'harmonypay' );
		$wallet_address = $fs->text( 'wallet_address' )
			->description( $text )
			// Input label
			->label( __( 'Address', 'harmonypay' ) )
			->required()
			->size( 64, 128 )
			->trim();

		// This is an ugly hack for Monero. Ideally it would be hidden away in the wallet settings, but for the user it's much nicer here.
		$wallet_address = $fs->text( 'wallet_address' )
			->description( $text )
			// Input label
			->label( __( 'Address', 'harmonypay' ) )
			->required()
			->size( 64, 128 )
			->trim();

		$monero_private_view_key = $fs->text( 'monero_private_view_key' )
			->css_class( 'only_for_currency XMR' )
			->description( __( 'Your private view key that is used to see the amounts in private transactions to your wallet.', 'harmonypay' ) )
			// Input label
			->label( __( 'Monero private view key', 'harmonypay' ) )
			->placeholder( '157e74dc4e2961c872f87aaf43461f6d0f596f2f116a51fbace1b693a8e3020a' )
			->size( 64, 64 )
			->trim();

		$save = $form->primary_button( 'save' )
			->value( __( 'Save settings', 'harmonypay' ) );

		if ( $form->is_posting() )
		{
			$form->post();
			$form->use_post_values();

			$reshow = false;

			if ( $table->bulk_actions()->pressed() )
			{
				switch ( $table->bulk_actions()->get_action() )
				{
					case 'delete':
						$ids = $table->bulk_actions()->get_rows();
						foreach( $ids as $id )
							$wallets->forget( $id );
						$wallets->save();
						$r .= $this->info_message_box()->_( __( 'The selected wallets have been deleted.', 'harmonypay' ) );
					break;
					case 'disable':
						$ids = $table->bulk_actions()->get_rows();
						foreach( $ids as $id )
						{
							$wallet = $wallets->get( $id );
							$wallet->set_enabled( false );
						}
						$wallets->save();
						$r .= $this->info_message_box()->_( __( 'The selected wallets have been disabled.', 'harmonypay' ) );
					break;
					case 'enable':
						$ids = $table->bulk_actions()->get_rows();
						foreach( $ids as $id )
						{
							$wallet = $wallets->get( $id );
							$wallet->set_enabled( true );
						}
						$wallets->save();
						$r .= $this->info_message_box()->_( __( 'The selected wallets have been enabled.', 'harmonypay' ) );
					break;
					case 'mark_as_used':
						$ids = $table->bulk_actions()->get_rows();
						foreach( $ids as $id )
						{
							$wallet = $wallets->get( $id );
							$wallet->use_it();
						}
						$wallets->save();
						$r .= $this->info_message_box()->_( __( 'The selected wallets have been marked as used.', 'harmonypay' ) );
					break;
					case 'reset_sorting':
						$ids = $table->bulk_actions()->get_rows();
						foreach( $ids as $id )
						{
							$wallet = $wallets->get( $id );
							$wallet->set_order();
						}
						$wallets->save();
						$r .= $this->info_message_box()->_( __( 'The selected wallets have had their sort order reset.', 'harmonypay' ) );
					break;
				}
				$reshow = true;
			}

			if ( $save->pressed() )
			{
				try
				{
					$wallet = $wallets->new_wallet();
					$wallet->address = $wallet_address->get_filtered_post_value();

					$chosen_currency = $wallet_currency->get_filtered_post_value();
					$currency = $this->currencies()->get( $chosen_currency );
					$currency->validate_address( $wallet->address );

					if ( $currency->supports( 'monero_private_view_key' ) )
						$wallet->set( 'monero_private_view_key', $form->input( 'monero_private_view_key' )->get_filtered_post_value() );

					$wallet->currency_id = $chosen_currency;

					$index = $wallets->add( $wallet );
					$wallets->save();

					$r .= $this->info_message_box()->_( __( 'Settings saved!', 'harmonypay' ) );
					$reshow = true;
				}
				catch ( Exception $e )
				{
					$r .= $this->error_message_box()->_( $e->getMessage() );
				}
			}

			if ( $reshow )
			{
				echo $r;
				$_POST = [];
				$function = __FUNCTION__;
				echo $this->$function();
				return;
			}
		}

		$r .= wpautop( __( 'This table shows the currencies you have setup. To edit a currency, click the address. To sort them, drag the currency name up or down.', 'harmonypay' ) );

		$r .= wpautop( __( 'If you have several wallets of the same currency, they will be used in sequential order.', 'harmonypay' ) );

		$wallets_text = sprintf(
			// perhaps <a>we can ...you</a>
			__( "If you don't have a wallet address to use, perhaps %swe can recommend some wallets for you%s?", 'harmonypay' ),
			'<a href="https://harmonypay.com/doc/recommended-wallets-exchanges/" target="_blank">',
			'</a>'
		);

		if ( count( $wallets ) < 1 )
			$wallets_text = '<strong>' . $wallets_text . '</strong>';
		$r .= wpautop( $wallets_text );

		// WooCommerce message
		if ( class_exists( 'woocommerce' ) )
		{
			$home_url = home_url();
			$woo_text = sprintf(
				// perhaps <a>WooCommerce Settings</a>
				__( "After adding currencies, visit the %sWooCommerce Settings%s to enable the gateway and more.", 'harmonypay' ),
				'<a href="' . esc_url( $home_url ) . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=harmonypay">',
				'</a>'
			);
			$r .= wpautop( $woo_text );
		}

		// EDD message
		if ( class_exists( 'Easy_Digital_Downloads' ) )
		{
			$home_url = home_url();
			$edd_text = sprintf(
				// perhaps <a>Easy Digital Downloads Settings</a>
				__( "After adding currencies, visit the %sEasy Digital Downloads Settings%s to enable the gateway and more.", 'harmonypay' ),
				'<a href="' . esc_url( $home_url ) . '/wp-admin/edit.php?post_type=download&page=edd-settings&tab=gateways">',
				'</a>'
			);
			$r .= wpautop( $edd_text );
		}

		$r .= $this->h2( __( 'Current currencies / wallets', 'harmonypay' ) );

		$r .= $form->open_tag();
		$r .= $table;
		$r .= $form->close_tag();
		$r .= $form->open_tag();
		$r .= $form->display_form_table();
		$r .= $form->close_tag();

		$this->enqueue_css();

		echo $r;
	}

	/**
		@brief		Edit this wallet.
		@since		2017-12-09 20:44:32
	**/
	public function admin_edit_wallet( $wallet_id )
	{
		$wallets = $this->wallets();
		if ( ! $wallets->has( $wallet_id ) )
		{
			echo 'Invalid wallet ID!';
			return;
		}
		$this->enqueue_css();
		$wallet = $wallets->get( $wallet_id );

		$currencies = $this->currencies();
		$currency = $currencies->get( $wallet->get_currency_id() );
		$form = $this->form();
		$form->id( 'edit_wallet' );
		$r = '';

		$length = $currency->get_address_length();
		if ( is_array( $length ) )
		{
			// Figure out the max length.
			$max = 0;
			foreach( $length as $int )
				$max = max( $max, $int );
			$length = $max;
		}

		$fs = $form->fieldset( 'fs_basic' );
		// Fieldset legend
		$fs->legend->label( __( 'Basic settings', 'harmonypay' ) );

		$wallet_label = $fs->text( 'wallet_label' )
			->description( __( 'Describe the wallet to yourself.', 'harmonypay' ) )
			// Input label
			->label( __( 'Label', 'harmonypay' ) )
			->size( 32 )
			->stripslashes()
			->trim()
			->value( $wallet->get_label() );

		$wallet_address = $fs->text( 'wallet_address' )
			->description( __( 'The address of your wallet to which you want to receive funds.', 'harmonypay' ) )
			// Input label
			->label( __( 'Address', 'harmonypay' ) )
			->required()
			->size( $length, $length )
			->trim()
			->value( $wallet->get_address() );

		$ens_address =( $currency->id == 'ETH' || $currency->id == 'ONE' || isset( $currency->erc20 ) );
		if ( $ens_address )
		{
			$ens_address_input = $fs->text( 'ens_address' )
				->description( __( 'The ENS address of your wallet to which you want to receive funds. The resolving address must match your normal address.', 'harmonypay' ) )
				// Input label
				->label( __( 'ENS Address', 'harmonypay' ) )
				->size( 32 )
				->trim()
				->value( $wallet->get( 'ens_address' ) );
		}

		$wallet_enabled = $fs->checkbox( 'wallet_enabled' )
			->checked( $wallet->enabled )
			->description( __( 'Is this wallet enabled and ready to receive funds?', 'harmonypay' ) )
			// Input label
			->label( __( 'Enabled', 'harmonypay' ) );

		if ( $currency->supports( 'confirmations' ) )
			$confirmations = $fs->number( 'confirmations' )
				->description( __( 'How many confirmations needed to regard orders as paid. 1 is the default. Only some blockchains support 0-conf (mempool) such as BCH, BTC, BTG, DASH, DCR, DGB, ECA, GRS, LTC, SMART, VIA, XVG, ZEC.', 'harmonypay' ) )
				// Input label
				->label( __( 'Confirmations', 'harmonypay' ) )
				->min( 0, 100 )
				->value( $wallet->confirmations );

		if ( $currency->supports( 'btc_hd_public_key' ) )
		{
			if ( ! function_exists( 'gmp_abs' ) )
				$form->markup( 'm_btc_hd_public_key' )
					->markup( __( 'This wallet supports HD public keys, but your system is missing the required PHP GMP library.', 'harmonypay' ) );
			else
			{
				$fs = $form->fieldset( 'fs_btc_hd_public_key' );
				// Fieldset legend
				$fs->legend->label( __( 'HD wallet settings', 'harmonypay' ) );

				$pubs = 'xpub/ypub/zpub';
				if ( $currency->supports( 'btc_hd_public_key_pubs' ) )
					$pubs = implode( '/', $currency->supports->btc_hd_public_key_pubs );

				$btc_hd_public_key = $fs->text( 'btc_hd_public_key' )
					->description( __( sprintf( 'If you have an HD wallet and want to generate a new address after each purchase, enter your %s public key here.', $pubs ), 'harmonypay' ) )
					// Input label
					->label( __( 'HD public key', 'harmonypay' ) )
					->trim()
					->maxlength( 128 )
					->value( $wallet->get( 'btc_hd_public_key' ) );

				$path = $wallet->get( 'btc_hd_public_key_generate_address_path', 0 );
				$btc_hd_public_key_generate_address_path = $fs->number( 'btc_hd_public_key_generate_address_path' )
					->description( __( "The index of the next public wallet address to use. The default is 0 and gets increased each time the wallet is used. This is related to your wallet's gap length.", 'harmonypay' ) )
					// Input label
					->label( __( 'Wallet index', 'harmonypay' ) )
					->min( 0 )
					->value( $path );

				try
				{
					$new_address = $currency->btc_hd_public_key_generate_address( $wallet );
				}
				catch ( Exception $e )
				{
					$new_address = $e->getMessage();
				}
				$fs->markup( 'm_btc_hd_public_key_generate_address_path' )
					->p( __( 'The address at index %d is %s.', 'harmonypay' ), $path, $new_address );

				$circa_amount = $fs->number( 'circa_amount' )
					->description( __( "When using an HD wallet, you can accept amounts that are lower than requested.", 'harmonypay' ) )
					->label( __( 'Payment tolerance percent', 'harmonypay' ) )
					->min( 0 )
					->max( 100 )
					->value( $wallet->get( 'circa_amount' ) );
			}
		}

		if ( $currency->supports( 'monero_private_view_key' ) )
		{
			$monero_private_view_key = $fs->text( 'monero_private_view_key' )
				->description( __( 'Your private view key that is used to see the amounts in private transactions to your wallet.', 'harmonypay' ) )
				// Input label
				->label( __( 'Monero private view key', 'harmonypay' ) )
				->required()
				->size( 64, 64 )
				->trim()
				->value( $wallet->get( 'monero_private_view_key' ) );
		}

		if ( $this->is_network && is_super_admin() )
			$wallet->add_network_fields( $form );

		$save = $form->primary_button( 'save' )
			->value( __( 'Save settings', 'harmonypay' ) );

		if ( $form->is_posting() )
		{
			$form->post();
			$form->use_post_values();

			$reshow = false;

			if ( $save->pressed() )
			{
				try
				{
					$wallet->address = $wallet_address->get_filtered_post_value();

					$currency = $this->currencies()->get( $wallet->get_currency_id() );
					$currency->validate_address( $wallet->address );

					$wallet->enabled = $wallet_enabled->is_checked();
					if ( $currency->supports( 'confirmations' ) )
						$wallet->confirmations = $confirmations->get_filtered_post_value();

					if ( $ens_address )
						$wallet->set( 'ens_address', $ens_address_input->get_filtered_post_value() );

					if ( $currency->supports( 'btc_hd_public_key' ) )
						if ( function_exists( 'gmp_abs' ) )
						{
							$public_key = $btc_hd_public_key->get_filtered_post_value();
							$public_key = trim( $public_key );
							$wallet->set( 'btc_hd_public_key', $public_key );
							if ( $public_key != '' )
							{
								// Check that the currency accepts this pub type.
								if ( $currency->supports( 'btc_hd_public_key_pubs' ) )
								{
									$pubs = implode( '/', $currency->supports->btc_hd_public_key_pubs );
									$pub_type = substr( $public_key, 0, 4 );
									if ( ! in_array( $pub_type, $currency->supports->btc_hd_public_key_pubs ) )
										throw new Exception( sprintf( 'This public key type is not supported. Please use only: %s', implode( ' or ', $currency->supports->btc_hd_public_key_pubs ) ) );
								}
								$wallet->set( 'circa_amount', $circa_amount->get_filtered_post_value() );
								$wallet->set( 'btc_hd_public_key_generate_address_path', $btc_hd_public_key_generate_address_path->get_filtered_post_value() );
							}

						}

					$wallet->maybe_parse_network_form_post( $form );

					$wallet->set_label( $wallet_label->get_filtered_post_value() );

					if ( $currency->supports( 'monero_private_view_key' ) )
					{
						foreach( [ 'monero_private_view_key' ] as $key )
							$wallet->set( $key, $$key->get_filtered_post_value() );
					}

					$wallets->save();

					$r .= $this->info_message_box()->_( __( 'Settings saved!', 'harmonypay' ) );
					$reshow = true;
				}
				catch ( Exception $e )
				{
					$r .= $this->error_message_box()->_( $e->getMessage() );
				}
			}

			if ( $reshow )
			{
				echo $r;
				$_POST = [];
				$function = __FUNCTION__;
				echo $this->$function( $wallet_id );
				return;
			}
		}

		$r .= $form->open_tag();
		$r .= $form->display_form_table();
		$r .= $form->close_tag();

		echo $r;
	}

	/**
		@brief		Local settings.
		@since		2018-04-26 16:14:39
	**/
	public function admin_local_settings()
	{
		$form = $this->form();
		$form->css_class( 'plainview_form_auto_tabs' );
		$form->local_settings = true;
		$r = '';

		$fs = $form->fieldset( 'fs_qr_code' );
		// Label for fieldset
		$fs->legend->label( __( 'QR code', 'harmonypay' ) );

		$this->add_qr_code_inputs( $fs );

		$fs = $form->fieldset( 'fs_payment_timer' );
		// Label for fieldset
		$fs->legend->label( __( 'Payment timer', 'harmonypay' ) );

		$this->add_payment_timer_inputs( $fs );

		$save = $form->primary_button( 'save' )
			->value( __( 'Save settings', 'harmonypay' ) );

		if ( $form->is_posting() )
		{
			$form->post();
			$form->use_post_values();

			$this->save_qr_code_inputs( $form );
			$this->save_payment_timer_inputs( $form );

			$r .= $this->info_message_box()->_( __( 'Settings saved!', 'harmonypay' ) );

			echo $r;
			$_POST = [];
			$function = __FUNCTION__;
			echo $this->$function();
			return;
		}

		$r .= $form->open_tag();
		$r .= $form->display_form_table();
		$r .= $form->close_tag();

		echo $r;
	}

	/**
		@brief		Show the settings.
		@since		2017-12-09 07:14:33
	**/
	public function admin_global_settings()
	{
		$form = $this->form();
		$form->css_class( 'plainview_form_auto_tabs' );
		$r = '';

		$fs = $form->fieldset( 'fs_gateway_api_url' );
		// Label for fieldset
		$fs->legend->label( __( 'HarmonyPay Gateway API URL', 'harmonypay' ) );

		$fs->markup( 'm_gateway_api_url' )
			->p( __( 'Define your HarmonyPay Gateway API URL here.<br/> eg. <strong>http://YOUR-DOMAIN-NAME/api/v1/</strong>', 'harmonypay' ) );

		$gateway_api_url = $fs->text( 'gateway_api_url' )
			->description( __( 'Your HarmonyPay Gateway API URL.', 'harmonypay' ) )
			// Input label
			->label( __( 'Gateway API URL', 'harmonypay' ) )
			->required()
			->size( 64, 64 )
			->trim()
			->value( $this->get_site_option( 'gateway_api_url' ) );

		$fs = $form->fieldset( 'fs_gateway_fees' );
		// Label for fieldset
		$fs->legend->label( __( 'Gateway fees', 'harmonypay' ) );

		$fs->markup( 'm_gateway_fees' )
			->p( __( 'If you wish to charge (or discount) visitors for using HarmonyPay as the payment gateway, you can enter the fixed or percentage amounts in the boxes below. The cryptocurrency checkout price will be modified in accordance with the combined values below. These are applied to the original currency.', 'harmonypay' ) );

		$markup_amount = $fs->number( 'markup_amount' )
			// Input description.
			->description( __( 'If you wish to mark your prices up (or down) when using cryptocurrency, enter the fixed amount in this box.', 'harmonypay' ) )
			// Input label.
			->label( __( 'Markup amount', 'harmonypay' ) )
			->max( 1000 )
			->min( -1000 )
			->step( 0.01 )
			->size( 6, 6 )
			->value( $this->get_site_option( 'markup_amount' ) );

		$markup_percent = $fs->number( 'markup_percent' )
			// Input description.
			->description( __( 'If you wish to mark your prices up (or down) when using cryptocurrency, enter the percentage in this box.', 'harmonypay' ) )
			// Input label.
			->label( __( 'Markup %%', 'harmonypay' ) )
			->max( 1000 )
			->min( -100 )
			->step( 0.01 )
			->size( 6, 6 )
			->value( $this->get_site_option( 'markup_percent' ) );

		$fs = $form->fieldset( 'fs_qr_code' );
		// Label for fieldset
		$fs->legend->label( __( 'QR code', 'harmonypay' ) );

		if ( $this->is_network )
			$form->global_settings = true;
		else
			$form->local_settings = true;

		$this->add_qr_code_inputs( $fs );

		$fs = $form->fieldset( 'fs_payment_timer' );
		// Label for fieldset
		$fs->legend->label( __( 'Payment timer', 'harmonypay' ) );

		if ( $this->is_network )
			$form->global_settings = true;
		else
			$form->local_settings = true;

		$this->add_payment_timer_inputs( $fs );

		$this->add_debug_settings_to_form( $form );

		$save = $form->primary_button( 'save' )
			->value( __( 'Save settings', 'harmonypay' ) );

		if ( $form->is_posting() )
		{
			$form->post();
			$form->use_post_values();

			$this->update_site_option( 'gateway_api_url', $gateway_api_url->get_filtered_post_value() );
			$this->update_site_option( 'markup_amount', $markup_amount->get_filtered_post_value() );
			$this->update_site_option( 'markup_percent', $markup_percent->get_filtered_post_value() );

			$this->save_payment_timer_inputs( $form );
			$this->save_qr_code_inputs( $form );

			$this->save_debug_settings_from_form( $form );
			$r .= $this->info_message_box()->_( __( 'Settings saved!', 'harmonypay' ) );

			echo $r;
			$_POST = [];
			$function = __FUNCTION__;
			echo $this->$function();
			return;
		}

		$r .= $form->open_tag();
		$r .= $form->display_form_table();
		$r .= $form->close_tag();

		echo $r;
	}

	/**
		@brief		Tools
		@since		2017-12-30 23:02:12
	**/
	public function admin_tools()
	{
		$form = $this->form();
		$form->css_class( 'plainview_form_auto_tabs' );
		$r = '';

		$form->markup( 'm_hourly_cron' )
			->p( __( 'The hourly run cron job will do things like update the account information, exchange rates, send unsent data to the API server, etc.', 'harmonypay' ) );

		$hourly_cron = $form->secondary_button( 'hourly_cron' )
			->value( __( 'Run hourly cron job', 'harmonypay' ) );

		$form->markup( 'm_test_communication' )
			->p( __( "Test the communication with the API server. If it doesn't work, then there is a conflict with another plugin or the theme.", 'harmonypay' ) );

		$test_communication = $form->secondary_button( 'test_communication' )
			->value( __( 'Test communication', 'harmonypay' ) );

		$form->markup( 'm_show_expired_license_notifications' )
			->p(  __( 'Make all expired license notifications appear again.', 'harmonypay' ) );

		$show_expired_license_notifications = $form->secondary_button( 'show_expired_license_notifications' )
			->value( __( 'Reset expired license notifications', 'harmonypay' ) );

		if ( $form->is_posting() )
		{
			$form->post();
			$form->use_post_values();

			if ( $hourly_cron->pressed() )
			{
				do_action( 'harmonypay_hourly' );
				$r .= $this->info_message_box()->_( __( 'HarmonyPay hourly cron job run.', 'harmonypay' ) );
			}

			if ( $test_communication->pressed() )
			{
				$result = $this->api()->test_communication();
				if ( $result->result == 'ok' )
					$r .= $this->info_message_box()->_( __( 'Success! %s', 'harmonypay' ), $result->message );
				else
					$r .= $this->error_message_box()->_( __( 'Communications failure: %s', 'harmonypay' ),
						$result->message
					);
			}

			if ( $show_expired_license_notifications->pressed() )
			{
				$this->update_site_option( 'expired_license_nag_dismissals', [] );
				$r .= $this->info_message_box()->_( __( 'Notifications reset! The next time your account is refreshed, you might see an expired license notification. ', 'harmonypay' ) );
			}

			echo $r;
			$_POST = [];
			$function = __FUNCTION__;
			echo $this->$function();
			return;
		}

		$r .= $form->open_tag();
		$r .= $form->display_form_table();
		$r .= $form->close_tag();

		echo $r;
	}

	/**
		@brief		Deactivation.
		@since		2017-12-14 08:36:14
	**/
	public function deactivate()
	{
		wp_clear_scheduled_hook( 'harmonypay_hourly' );
	}

	/**
		@brief		init_admin_trait
		@since		2017-12-25 18:25:53
	**/
	public function init_admin_trait()
	{
		$this->add_action( 'harmonypay_hourly' );

		// The plugin table.
		$this->add_filter( 'network_admin_plugin_action_links', 'plugin_action_links', 10, 4 );
		$this->add_filter( 'plugin_action_links', 'plugin_action_links', 10, 4 );

		// Sort the wallets.
		$this->add_action( 'wp_ajax_harmonypay_sort_wallets' );

		// Display the expired warning?
		$this->expired_license()->show();
	}

	/**
		@brief		Our hourly cron.
		@since		2017-12-22 07:49:38
	**/
	public function harmonypay_hourly()
	{
		// Schedule an account retrieval sometime.
		// The timestamp shoule be anywhere between soon and 50 minutes later.
		$timestamp = rand( 5, 15 ) * 60;
		$timestamp = time() + $timestamp;
		$this->debug( 'Scheduled for %s', $this->local_datetime( $timestamp ) );
		wp_schedule_single_event( $timestamp, 'harmonypay_retrieve_account' );
	}

	/**
		@brief		Modify the plugin links in the plugins table.
		@since		2017-12-30 20:49:13
	**/
	public function plugin_action_links( $links, $plugin_name )
	{
		if ( $plugin_name != 'harmonypay/HarmonyPay.php' )
			return $links;
		if ( is_network_admin() )
			$url = network_admin_url( 'settings.php?page=harmonypay' );
		else
			$url = admin_url( 'options-general.php?page=harmonypay' );
		$links []= sprintf( '<a href="%s">%s</a>',
			$url,
			__( 'Settings', 'harmonypay' )
		);
		return $links;
	}

	/**
		@brief		Allow the user to sort the wallets via ajax.
		@since		2018-10-17 18:54:22
	**/
	public function wp_ajax_harmonypay_sort_wallets()
	{
		if ( ! isset( $_REQUEST[ 'nonce' ] ) )
			wp_die( 'No nonce.' );
		$nonce = $_REQUEST[ 'nonce' ];

		if ( ! wp_verify_nonce( $nonce, 'harmonypay_sort_wallets' ) )
			wp_die( 'Invalid nonce.' );

		// Load the wallets.
		$wallets = $this->wallets();

		foreach( $wallets as $wallet_id => $wallet )
		{
			foreach( $_POST[ 'wallets' ] as $wallet_order => $post_wallet_id )
			{
				if ( $wallet_id != $post_wallet_id )
					continue;
				$wallet->set_order( $wallet_order );
			}
		}

		$wallets->save();
	}
}
