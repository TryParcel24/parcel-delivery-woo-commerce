<?php

class OrderDetails

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



    public function __construct()
    {

        $this->glSettings = $this->get_general_option();

        $this->setup_hooks();
    }



    public function get_general_option()
    {

        $data = array();

        return $data;
    }



    /*

     * this function adds hooks only for this plugin

     */

    public function setup_hooks()

    {

        add_action('wp_enqueue_scripts', array($this, 'enqueue_style_script'));

        add_action('woocommerce_order_details_after_customer_details', array($this, 'add_tracking_map_after_customer_details'));
    }



    public function enqueue_style_script()
    {

        // Only load styles and scripts on the order-details page

        if (is_page('my-account') || is_wc_endpoint_url('order-received')) {

            // *************************  stylesheets ******************/

            wp_enqueue_style('order_details_style', PARCEL_DELIVERY_DIR_URL . '/modules/order-details/css/style.css', array(), PARCEL_DELIVERY_VERSION, 'all');

            // ************************  stylesheets ******************/

            // ********************** javascript ********************* /

            wp_enqueue_script('order_details_script', PARCEL_DELIVERY_DIR_URL . '/modules/order-details/js/script.js', array('jquery'), PARCEL_DELIVERY_VERSION, true);

            // ********************** javascript  ********************* /



            wp_localize_script(
                'order_details_script',
                'orderVar',
                array(

                    'adminurl' => admin_url() . 'admin-ajax.php',

                )

            );
        }
    }



    public function add_tracking_map_after_customer_details($order)
    {

        $delivery_task_status = $order->get_meta('_delivery_task_status');

        if ('' === $delivery_task_status && ! $order->meta_exists('_delivery_task_status')) {

            // No delivery task has been created for this order yet.
            return;

        } else {

            $google_maps_api_key = trim((string) get_option('pd_google_maps_api_key'));

            if ('' === $google_maps_api_key) {
                // No API key configured: skip rendering the map gracefully.
                return;
            }

            $billing_block = $order->get_meta('_billing_block');

            $pickup_location = global_features_object()->get_pickup_location($order->get_meta('_pickup_location'));

            $pickup_latitude = $pickup_location['pickup_latitude'];

            $pickup_longitude = $pickup_location['pickup_longitude'];

            $pickup_full_address = $pickup_location['full_address'];

            $delivary_data = global_features_object()->ck_get_block_data_by_id($billing_block);

            $delivary_latitude = $delivary_data['latitude'];

            $delivary_longitude = $delivary_data['longitude'];

            $billing_address_1  = $order->get_billing_address_1();

            $billing_address_2  = $order->get_billing_address_2();

            $billing_city       = $order->get_billing_city();

            $billing_state      = $order->get_billing_state();

            $billing_postcode   = $order->get_billing_postcode();

            $billing_country    = $order->get_billing_country();

            $billing_full_address = $billing_address_1 . ' ' . $billing_address_2 . ' ' . $billing_city . ' ' . $billing_state . ' ' . $billing_postcode . ' ' . $delivary_data['name'] . ' ' . $billing_country;

            ?>

            <h2 class="woocommerce-column__title">Track Your Order</h2>

            <script type="text/javascript" src="<?php echo esc_url( add_query_arg( array( 'libraries' => 'places', 'key' => $google_maps_api_key ), 'https://maps.googleapis.com/maps/api/js' ) ); ?>"></script>

            <script>
                (function(){
                    var pdMapData = <?php echo wp_json_encode( array(
                        'delivery' => array(
                            'lat'     => (float) $delivary_latitude,
                            'lng'     => (float) $delivary_longitude,
                            'label'   => 'Delivery Point',
                            'address' => (string) $billing_full_address,
                        ),
                        'pickup' => array(
                            'lat'     => (float) $pickup_latitude,
                            'lng'     => (float) $pickup_longitude,
                            'label'   => 'Pickup Point',
                            'address' => (string) $pickup_full_address,
                        ),
                    ) ); ?>;

                    function escapeHtml(s) {
                        return String(s)
                            .replace(/&/g, '&amp;')
                            .replace(/</g, '&lt;')
                            .replace(/>/g, '&gt;')
                            .replace(/"/g, '&quot;')
                            .replace(/'/g, '&#039;');
                    }

                    window.initMap = function() {

                    var map;

                    var bounds = new google.maps.LatLngBounds();

                    var mapOptions = {

                        mapTypeId: 'roadmap'

                    };

                    // Display a map on the web page

                    map = new google.maps.Map(document.getElementById("mapCanvas"), mapOptions);

                    map.setTilt(50);

                    // Multiple markers location, latitude, and longitude

                    var markers = [

                        [pdMapData.delivery.label, pdMapData.delivery.lat, pdMapData.delivery.lng],
                        [pdMapData.pickup.label,   pdMapData.pickup.lat,   pdMapData.pickup.lng]

                    ];

                    // Info window content

                    var infoWindowContent = [

                        ['<div class="info_content"><h3>' + escapeHtml(pdMapData.delivery.address) + '</h3></div>'],

                        ['<div class="info_content"><h3>' + escapeHtml(pdMapData.pickup.address)   + '</h3></div>']

                    ];

                    // Add multiple markers to map

                    var infoWindow = new google.maps.InfoWindow(),
                        marker, i;

                    // Place each marker on the map  

                    for (i = 0; i < markers.length; i++) {

                        var position = new google.maps.LatLng(markers[i][1], markers[i][2]);

                        bounds.extend(position);

                        marker = new google.maps.Marker({

                            position: position,

                            map: map,

                            title: markers[i][0]

                        });

                        // Add info window to marker    

                        google.maps.event.addListener(marker, 'click', (function(marker, i) {

                            return function() {

                                infoWindow.setContent(infoWindowContent[i][0]);

                                infoWindow.open(map, marker);

                            }

                        })(marker, i));

                        // Center the map to fit all markers on the screen

                        map.fitBounds(bounds);

                    }

                    // Set zoom level

                    var boundsListener = google.maps.event.addListener((map), 'bounds_changed', function(event) {

                        this.setZoom(14);

                        google.maps.event.removeListener(boundsListener);

                    });

                };

                    // Load initialize function when the Google Maps script fires it,
                    // or immediately on DOM ready as a fallback.
                    if (document.readyState === 'complete' || document.readyState === 'interactive') {
                        setTimeout(function(){ if (window.google && window.google.maps) { window.initMap(); } }, 0);
                    } else {
                        document.addEventListener('DOMContentLoaded', function(){
                            if (window.google && window.google.maps) { window.initMap(); }
                        });
                    }
                })();
            </script>

            <div id="mapCanvas"></div>

            <style type="text/css">
                #mapCanvas {

                    width: 100%;

                    height: 400px;

                }
            </style>

            <?php

        }
    }
} // class ends



function pd_order_details_object()

{

    return OrderDetails::get_instance();
}



pd_order_details_object();
