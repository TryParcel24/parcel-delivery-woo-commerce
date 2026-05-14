jQuery(document).ready(function(){

    // Classic checkout: trigger shipping recalculation when block changes
    jQuery('#billing_block').on('change', function(){
        jQuery('body').trigger('update_checkout');
    });

    // Block checkout: the Additional Checkout Fields API handles shipping
    // recalculation server-side via on_additional_field_set / on_store_api_customer_update.
    // No client-side trigger is needed for block checkout.

});