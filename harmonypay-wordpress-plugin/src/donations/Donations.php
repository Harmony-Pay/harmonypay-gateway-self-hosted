<?php

namespace harmonypay\donations;

/**
	@brief		A collection of donation settings.
	@since		2019-02-21 19:30:16
**/
class Donations
	extends \harmonypay\Collection
{
	use \plainview\sdk_mcc\wordpress\object_stores\Site_Option;

    	/**
		@brief		Return the container that stores this object.
		@since		2019-02-21 19:43:03
	**/
	public static function store_container()
	{
		return HarmonyPay();
	}

	/**
	@brief		Convenience method to return a new donation object.
	@since		2019-02-21 19:55:58
	**/
	public function new_donations()
	{
		return $this;
	}

	/**
		@brief		Return the storage key.
		@details	Key / ID.
		@since		2019-02-21 19:43:03
	**/
	public static function store_key()
	{
		return 'donations';
	}

	/**
	@brief		Run the diagnostic tests for this donation.
	@details	Try and communicate with the donation servive.
	@throws		Exception
	@since		2019-02-21 20:29:01
	**/
	public function generate($donation)
	{
		//$donation = new \harmonypay\api\v2\Donation();
		//$donation->set_type( $this->get_type() );
		//foreach( $this as $key => $value )
		//	$donation->$key = $value;
		return HarmonyPay()->api()->donations()->generate( $donation );
	}

}