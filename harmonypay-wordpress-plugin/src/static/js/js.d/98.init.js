harmonypay_convert_data( 'harmonypay_checkout_data', function( data )
{
	harmonypay_checkout_javascript( data );
} );
$( 'form.plainview_form_auto_tabs' ).plainview_form_auto_tabs();
$( '.hrp_donations' ).harmonypay_donations_javascript();

$( 'form#currencies' ).harmonypay_new_currency();

/**
	@brief		Make the wallets sortable.
	@since		2018-10-17 17:38:58
**/
$( 'table.currencies tbody' ).harmonypay_sort_wallets();