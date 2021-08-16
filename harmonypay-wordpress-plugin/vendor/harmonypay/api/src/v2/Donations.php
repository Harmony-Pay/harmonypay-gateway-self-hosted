<?php

namespace harmonypay\api\v2;

/**
	@brief		Donation handling.
	@since		2021-08-12 18:51:13
**/
class Donations
	extends Component
{
	/**
		@brief		Send a donation info/address to the server.
		@since		2021-08-12 18:51:13
	**/
	public function generate( $donation_info )
	{
		$json = $this->api()->send_post_with_account( 'donation/generate', [ 'donation_info' => $donation_info ] );
		if ( ! isset( $json->result ) )
			throw new Exception( 'Invalid JSON from API.' );
		if ( $json->result !== 'ok' )
			throw new Exception( $json->message );
		return $json->message;
	}
}
