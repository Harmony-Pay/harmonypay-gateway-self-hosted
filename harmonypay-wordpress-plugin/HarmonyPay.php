<?php
/*
Author:				@harmonypay
Author Email:		contact@harmonypay
Author URI:			https://harmonypay.com
Description:		Cryptocurrency payment gateway for WooCommerce and Easy Digital Downloads.
Plugin Name:		HarmonyPay
Plugin URI:			https://harmonypay.com
Text Domain:		harmonypay
Version:			0.3
WC tested up to:	5.0.0
*/

namespace harmonypay
{
	require_once( __DIR__ . '/vendor/autoload.php' );

	class HarmonyPay
		extends \plainview\sdk_mcc\wordpress\base
	{
		/**
			@brief		Plugin version.
			@since		2018-03-14 19:04:03
		**/
		public $plugin_version = HARMONYPAY_PLUGIN_VERSION;

		use \plainview\sdk_mcc\wordpress\traits\debug;

		use admin_trait;
		use api_trait;
		use autosettlement_trait;
		use currencies_trait;
		use donations_trait;
		use wallets_trait;
		use menu_trait;
		use misc_methods_trait;
		use qr_code_trait;
		use payment_timer_trait;

		/**
			@brief		Constructor.
			@since		2017-12-07 19:31:43
		**/
		public function _construct()
		{
			$this->init_admin_trait();
			$this->init_api_trait();
			$this->init_currencies_trait();
			$this->init_donations_trait();
			$this->init_menu_trait();
			$this->init_misc_methods_trait();
			$this->easy_digital_downloads = new ecommerce\easy_digital_downloads\Easy_Digital_Downloads();
			$this->woocommerce = new ecommerce\woocommerce\WooCommerce();

			if ( defined( 'WP_CLI' ) && WP_CLI )
			{
				$cli = new cli\HarmonyPay();
				\WP_CLI::add_command( 'harmonypay', $cli );
			}

			if ( ! defined( 'HARMONYPAY_API_URL' ) ) {
				$gateway_api_url = $this->get_site_option( 'gateway_api_url', 'https://api.harmonypay.test/api/v1/' );
				define('HARMONYPAY_API_URL', $gateway_api_url);
			}

		}
	}
}

namespace
{
	define( 'HARMONYPAY_PLUGIN_VERSION', 0.3 );

	/**
		@brief		Return the instance of MCC.
		@since		2014-10-18 14:48:37
	**/
	function HarmonyPay()
	{
		return harmonypay\HarmonyPay::instance();
	}

	$harmonypay = new harmonypay\HarmonyPay( __FILE__ );
}
