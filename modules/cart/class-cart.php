<?php

class Cart
{
    protected static $_instance = null;
    public $glSettings = array();

    public static function get_instance()
    {
        if (is_null(self::$_instance)) {
          self::$_instance = new self();
        }
        return self::$_instance;
    }



    public function __construct() {

        $this->glSettings = $this->get_general_option();

        $this->setup_hooks();

    }

    public function get_general_option(){

        $data = array();
        return $data;
    }



    /*

     * this function adds hooks only for this plugin

     */

    public function setup_hooks()

    {            

        add_action( 'wp_enqueue_scripts', array($this,'enqueue_style_script') );

        // NOTE: We intentionally do NOT disable the WooCommerce shipping
        // calculator on the cart page. Merchants want shoppers to be able to
        // estimate shipping using the default country/state/postcode form on
        // the cart, before reaching the block-based selector on checkout.

    }


    public function enqueue_style_script(){

        // Only load styles and scripts on the cart page

        if ( is_cart() ) {

            // *************************  stylesheets ******************/

            wp_enqueue_style('pd_cart_style', PARCEL_DELIVERY_DIR_URL . '/modules/cart/css/style.css', array(), PARCEL_DELIVERY_VERSION, 'all');

            // ************************  stylesheets ******************/

            // ********************** javascript ********************* /

            wp_enqueue_script('pd_cart_script', PARCEL_DELIVERY_DIR_URL . '/modules/cart/js/script.js', array('jquery'), PARCEL_DELIVERY_VERSION, true);

            // ********************** javascript  ********************* /


        }

    }

} // class ends

function pd_cart_object()
{
    return Cart::get_instance();
}
pd_cart_object();