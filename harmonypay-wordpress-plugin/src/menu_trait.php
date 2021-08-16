<?php

namespace harmonypay;

/**
	@brief		Handles the setup of menus.
	@since		2017-12-09 07:05:04
**/
trait menu_trait
{
	/**
		@brief		Init!
		@since		2017-12-07 19:34:05
	**/
	public function init_menu_trait()
	{
		$this->add_action( 'admin_menu' );
		$this->add_action( 'network_admin_menu' );
	}

	/**
		@brief		Admin menu callback.
		@since		2017-12-07 19:35:46
	**/
	public function admin_menu()
	{
		$this->enqueue_js();

		// For normal admin.
		add_submenu_page(
			'options-general.php',
			// Page heading
			__( 'HarmonyPay Settings', 'harmonypay' ),
			// Menu item name
			__( 'HarmonyPay', 'harmonypay' ),
			'manage_options',
			'harmonypay',
			[ &$this, 'admin_menu_tabs' ]
		);

	}

	public function admin_menu_tabs()
	{
		$tabs = $this->tabs();

		if ( ! defined( 'HARMONYPAY_DISABLE_WALLET_EDITOR' ) )
		{
			$tabs->tab( 'currencies' )
				->callback_this( 'admin_currencies' )
				// Tab heading
				->heading( __( 'HarmonyPay Currencies', 'harmonypay' ) )
				// Name of tab
				->name( __( 'Currencies', 'harmonypay' ) );

			if ( $tabs->get_is( 'edit_wallet' ) )
			{
				$wallet_id = $_GET[ 'wallet_id' ];
				$wallets = $this->wallets();
				$wallet = $wallets->get( $wallet_id );
				$tabs->tab( 'edit_wallet' )
					->callback_this( 'admin_edit_wallet' )
					// Editing BTC wallet
					->heading( sprintf(  __( 'Editing %s wallet', 'harmonypay' ), $wallet->get_currency_id() ) )
					// Name of tab
					->name( __( 'Edit wallet', 'harmonypay' ) )
					->parameters( $wallet_id );
			}
		}

		$tabs->tab( 'account' )
			->callback_this( 'admin_account' )
			// Tab heading
			->heading( __( 'HarmonyPay Account', 'harmonypay' ) )
			// Name of tab
			->name( __( 'Account', 'harmonypay' ) );

		$tabs->tab( 'autosettlements' )
			->callback_this( 'autosettlement_admin' )
			// Tab heading
			->heading( __( 'HarmonyPay Autosettlement Settings', 'harmonypay' ) )
			// Name of tab
			->name( __( 'Autosettlements', 'harmonypay' ) );

		if ( $tabs->get_is( 'autosettlement_edit' ) )
		{
			$autosettlement_id = $_GET[ 'autosettlement_id' ];
			$autosettlements = $this->autosettlements();
			$autosettlement = $autosettlements->get( $autosettlement_id );
			$tabs->tab( 'autosettlement_edit' )
				->callback_this( 'autosettlement_edit' )
				// Editing autosettlement TYPE
				->heading( sprintf( __( 'Editing autosettlement %s', 'harmonypay' ), $autosettlement->get_type() ) )
				// Name of tab
				->name( __( 'Edit autosettlement', 'harmonypay' ) )
				->parameters( $autosettlement_id );
		}

		$tabs->tab( 'donations' )
			->callback_this( 'admin_donations' )
			// Tab heading
			->heading( __( 'HarmonyPay Donations', 'harmonypay' ) )
			// Name of tab
			->name( __( 'Donations', 'harmonypay' ) );

		if ( $this->is_network )
			$tabs->tab( 'local_settings' )
				->callback_this( 'admin_local_settings' )
				// Tab heading
				->heading( __( 'HarmonyPay Local Settings', 'harmonypay' ) )
				// Name of tab
				->name( __( 'Local Settings', 'harmonypay' ) );

		$tabs->tab( 'global_settings' )
			->callback_this( 'admin_global_settings' )
			// Tab heading
			->heading( __( 'HarmonyPay Global Settings', 'harmonypay' ) )
			// Name of tab
			->name( __( 'Global Settings', 'harmonypay' ) );

		$tabs->tab( 'tools' )
			->callback_this( 'admin_tools' )
			// Tab heading
			->heading( __( 'HarmonyPay Tools', 'harmonypay' ) )
			// Name of tab
			->name( __( 'Tools', 'harmonypay' ) );

		$tabs->tab( 'uninstall' )
			->callback_this( 'admin_uninstall' )
			// Tab heading
			->heading( __( 'Uninstall HarmonyPay', 'harmonypay' ) )
			// Name of tab
			->name( __( 'Uninstall', 'harmonypay' ) )
			->sort_order( 90 );		// Always last.

		echo $tabs->render();
	}

	/**
		@brief		network_admin_menu
		@since		2017-12-30 20:51:49
	**/
	public function network_admin_menu()
	{
		add_submenu_page(
			'settings.php',
			// Page heading
			__( 'HarmonyPay Settings', 'harmonypay' ),
			// Menu item name
			__( 'HarmonyPay', 'harmonypay' ),
			'manage_options',
			'harmonypay',
			[ &$this, 'admin_menu_tabs' ]
		);
	}

}
