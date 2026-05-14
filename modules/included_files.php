<?php

/**

 * @class    Included_files

 * @category Class

 * @author   Codingkart

 **/

class Included_files

{

    public function __construct()

    {

        require_once 'helper.php';

        require_once 'global-features/class-global-features.php';

        require_once 'wp-admin/general-settings/class-general-settings.php';

        require_once 'wp-admin/order-settings/class-order-settings.php';

        require_once 'wp-admin/parcel-delivery-tab/class-parcel-delivery-tab.php';

        require_once 'checkout/class-checkout.php';

        require_once 'order-details/class-order-details.php';

        require_once 'cart/class-cart.php';

        require_once 'shipping-method/class-shipping-method.php';

    } 

}

new Included_files();

