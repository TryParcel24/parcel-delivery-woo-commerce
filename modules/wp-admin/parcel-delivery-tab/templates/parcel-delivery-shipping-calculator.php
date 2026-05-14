<style>
/* Inline critical styles for the Shipping Calculator screen. Embedded in the
   template so they always apply, even if style.css is cached or fails to load
   on a particular request. */
#mainform > p.submit { display: none !important; }
.pdsc-wrap { max-width: 1200px; margin: 0 0 20px; }
.pdsc-wrap h2.pdsc-title { font-size: 22px; font-weight: 600; margin: 0 0 6px; color: #1d2327; }
.pdsc-wrap p.pdsc-sub { color: #50575e; margin: 0 0 20px; max-width: 900px; }
.pdsc-toolbar { position: sticky; top: 32px; z-index: 5; display: flex; justify-content: space-between; align-items: center; gap: 12px; background: #fff; border: 1px solid #dcdcde; border-radius: 10px; padding: 12px 16px; margin: 0 0 18px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
.pdsc-toolbar-left { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.pdsc-toolbar .pdsc-method { display: inline-flex; border: 1px solid #dcdcde; border-radius: 6px; overflow: hidden; }
.pdsc-toolbar .pdsc-method label { padding: 7px 16px; cursor: pointer; font-weight: 500; color: #50575e; background: #fff; border-right: 1px solid #dcdcde; }
.pdsc-toolbar .pdsc-method label:last-child { border-right: 0; }
.pdsc-toolbar .pdsc-method input { display: none; }
.pdsc-toolbar .pdsc-method input:checked + label { background: #2271b1; color: #fff; }
.pdsc-toolbar .pdsc-actions { display: inline-flex; gap: 8px; flex-wrap: wrap; }
.pdsc-toolbar .pdsc-actions .actionbtn,
.pdsc-toolbar .pdsc-actions .button { border-radius: 6px; }
.pdsc-toolbar .pdsc-actions .pdsc-save { background: #00a32a; border-color: #00a32a; color: #fff; }
.pdsc-toolbar .pdsc-actions .pdsc-save:hover { background: #007a1f; border-color: #007a1f; }
.pdsc-cards { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 16px; margin: 0 0 18px; }
.pdsc-card { background: #fff; border: 1px solid #dcdcde; border-radius: 10px; padding: 18px 20px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
.pdsc-card h4 { margin: 0 0 14px; padding: 0 0 10px; border-bottom: 1px solid #f0f0f1; font-size: 14px; font-weight: 600; color: #1d2327; }
.pdsc-card .row { display: grid; grid-template-columns: 150px minmax(0, 1fr); align-items: center; gap: 10px; margin: 10px 0; }
.pdsc-card .row label { color: #50575e; font-size: 13px; margin: 0; }
.pdsc-card .row input,
.pdsc-card .row select { width: 100%; max-width: 100%; min-height: 32px; padding: 4px 10px; border: 1px solid #8c8f94; border-radius: 4px; background: #fff; }
.pd_shipcal_blocks { background: #fff; border: 1px solid #dcdcde; border-radius: 10px; box-shadow: 0 1px 2px rgba(0,0,0,.04); max-height: 600px; overflow: auto; margin: 0; }
.pd_shipcal_blocks_table_container { width: 100%; }
.pd_table_toolbar { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 10px; padding: 12px 14px; border-bottom: 1px solid #f0f0f1; background: #fbfbfb; position: sticky; top: 0; z-index: 3; }
.pd_table_search { flex: 1 1 260px; max-width: 360px; min-height: 32px; padding: 4px 10px; border: 1px solid #8c8f94; border-radius: 6px; }
.pd_table_pager { display: inline-flex; align-items: center; gap: 8px; }
.pd_page_info { color: #50575e; font-size: 13px; min-width: 180px; text-align: center; }
table.pd_shipcal_blocks_table { width: 100%; border-collapse: separate; border-spacing: 0; margin: 0; }
table.pd_shipcal_blocks_table thead th { position: sticky; top: 53px; z-index: 2; background: #f6f7f7; text-transform: uppercase; font-size: 11px; letter-spacing: .6px; color: #50575e; padding: 10px 12px; border-bottom: 1px solid #c3c4c7; text-align: left; }
table.pd_shipcal_blocks_table td { padding: 10px 12px; border-bottom: 1px solid #f0f0f1; vertical-align: middle; }
table.pd_shipcal_blocks_table tbody tr:hover { background: #f6fbff; }
.pdInputDiv input.price { width: 120px; min-height: 32px; padding-left: 38px; border-color: #8c8f94; border-radius: 4px; }
.pdInputDiv span.pdCurrency { left: 8px; top: 50%; transform: translateY(-50%); color: #646970; font-size: 12px; position: absolute; }
.pdInputDiv { position: relative; display: inline-block; }
/* Hide legacy buttons that we now render in the sticky toolbar */
.pd_shipcal_method:not(.pdsc-method) { display: none !important; }
.pd_shipcal_action { display: none !important; }
.pd_hide { display: none !important; }
@media (max-width: 1000px) { .pdsc-cards { grid-template-columns: 1fr; } }
</style>

<div class="pd_shipcal_header">

    <h2><?= __( 'Parcel Delivery Shipping Calculator', 'woocommerce' ) ?></h2>

    <p><?= __( 'A Shipping Zone is a geographic region where a certain set of shipping methods are offered.Woocommerce will match a customer to a single zone using their shipping address and present the shipping methods within that zone to them.', 'woocommerce' ) ?></p>

</div>

<?php
// Surface a clear message when blocks could not be loaded from the Parcel API
// (missing keys, network failure, etc.) so the admin understands why the
// blocks table is empty and can act on it.
$pd_blocks_status = ( isset( $blocks_status ) && is_array( $blocks_status ) ) ? $blocks_status : array();
$pd_has_blocks    = ! empty( $pd_blocks_status['status'] ) && ! empty( $pd_blocks_status['block_data'] ) && is_array( $pd_blocks_status['block_data'] );
$pd_blocks_msg    = isset( $pd_blocks_status['message'] ) ? (string) $pd_blocks_status['message'] : '';
$pd_all_blocks    = $pd_has_blocks ? $pd_blocks_status['block_data'] : array();
$pd_manual_saved  = get_option( 'pd_manual_blocks_info' );
$pd_api_saved     = get_option( 'pd_api_blocks_info' );
$pd_manual_saved  = is_array( $pd_manual_saved ) ? $pd_manual_saved : array();
$pd_api_saved     = is_array( $pd_api_saved ) ? $pd_api_saved : array();
$pd_pickup_saved  = get_option( 'pd_pickup_location' );
$pd_pickup_saved  = is_object( $pd_pickup_saved ) ? (array) $pd_pickup_saved : ( is_array( $pd_pickup_saved ) ? $pd_pickup_saved : array() );
$pd_pickup_lat    = isset( $pd_pickup_saved['pickup_latitude'] ) ? $pd_pickup_saved['pickup_latitude'] : '';
$pd_pickup_lng    = isset( $pd_pickup_saved['pickup_longitude'] ) ? $pd_pickup_saved['pickup_longitude'] : '';
$pd_pickup_addr1  = isset( $pd_pickup_saved['pickup_address1'] ) ? $pd_pickup_saved['pickup_address1'] : '';
$pd_pickup_addr2  = isset( $pd_pickup_saved['pickup_address2'] ) ? $pd_pickup_saved['pickup_address2'] : '';
$pd_pickup_block_obj = isset( $pd_pickup_saved['pickup_block_json'] ) ? $pd_pickup_saved['pickup_block_json'] : null;
$pd_pickup_block_id  = '';
if ( is_object( $pd_pickup_block_obj ) && isset( $pd_pickup_block_obj->id ) ) {
    $pd_pickup_block_id = $pd_pickup_block_obj->id;
} elseif ( is_array( $pd_pickup_block_obj ) && isset( $pd_pickup_block_obj['id'] ) ) {
    $pd_pickup_block_id = $pd_pickup_block_obj['id'];
}
$pd_vehicle_saved = get_option( 'pd_delivery_vehicle' );
$pd_vehicle_saved = $pd_vehicle_saved ? (string) $pd_vehicle_saved : 'bike';
$pd_rows_data     = array(
    'manual' => array(),
    'api'    => array(),
);
$pd_find_saved = function ( $saved_blocks, $block_id ) {
    foreach ( $saved_blocks as $saved_block ) {
        if ( isset( $saved_block->id ) && (string) $saved_block->id === (string) $block_id ) {
            return $saved_block;
        }
        if ( is_array( $saved_block ) && isset( $saved_block['id'] ) && (string) $saved_block['id'] === (string) $block_id ) {
            return (object) $saved_block;
        }
    }
    return null;
};
$pd_build_rows = function ( $method, $saved_blocks ) use ( $pd_all_blocks, $pd_find_saved, &$pd_rows_data ) {
    foreach ( $pd_all_blocks as $block ) {
        $block       = is_array( $block ) ? (object) $block : $block;
        $block_id    = isset( $block->id ) ? $block->id : '';
        $saved_block = $pd_find_saved( $saved_blocks, $block_id );
        $name        = isset( $block->name ) && $block->name !== '' ? (string) $block->name : ( isset( $block->nameAr ) ? (string) $block->nameAr : (string) $block_id );
        $row         = array(
            'id'         => $block_id,
            '_id'        => isset( $block->_id ) ? $block->_id : '',
            'name'       => $name,
            'nameAr'     => isset( $block->nameAr ) ? $block->nameAr : '',
            'latitude'   => isset( $block->latitude ) ? $block->latitude : '',
            'longitude'  => isset( $block->longitude ) ? $block->longitude : '',
            'status'     => $saved_block && ! empty( $saved_block->status ),
            'min_order'  => $saved_block && isset( $saved_block->min_order ) ? (float) $saved_block->min_order : 0,
            'del_charge' => $saved_block && isset( $saved_block->del_charge ) ? (float) $saved_block->del_charge : 0,
        );
        $pd_rows_data[ $method ][] = $row;
        ?>
        <tr id="<?php echo esc_attr( $row['id'] ); ?>" data-_id="<?php echo esc_attr( $row['_id'] ); ?>" data-method="<?php echo esc_attr( $method ); ?>">
            <td><div class="pdBlockNo"><span><?php echo esc_html( $row['id'] ); ?></span></div></td>
            <td><div class="pdBlockName"><span><?php echo esc_html( $row['name'] ); ?></span></div></td>
            <td><div class="pdMinOrder pdInputDiv"><span class="pdCurrency"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span><input type="number" min="0" step="1" max class="input-text price" name="<?php echo esc_attr( $method ); ?>_block_min_order" value="<?php echo esc_attr( $row['min_order'] ); ?>" onwheel="return false;"></div></td>
            <td><div class="pdBlockCharge pdInputDiv"><span class="pdCurrency"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span><input type="number" step="1" max class="input-text price" name="<?php echo esc_attr( $method ); ?>_block_charge" value="<?php echo esc_attr( $row['del_charge'] ); ?>" onwheel="return false;"></div></td>
            <td><div class="pdStatus"><label class="pdSwitch pdSwitchFlat"><input class="pdSwitchInput <?php echo esc_attr( $method ); ?>BlockStatus" name="<?php echo esc_attr( $method ); ?>_block_status" <?php checked( $row['status'] ); ?> type="checkbox" /><span class="pdSwitchLabel" data-on="On" data-off="Off"></span> <span class="pdSwitchHandle"></span> </label></div></td>
        </tr>
        <?php
    }
};
?>
<div class="pd_shipcal_blocks_notice <?php echo $pd_has_blocks ? 'pd_hide' : ''; ?>"<?php echo $pd_has_blocks ? ' style="display:none;"' : ''; ?>>
    <div class="notice notice-warning inline">
        <p>
            <strong><?php echo esc_html__( 'No blocks were loaded from the Parcel API.', 'parcel-delivery' ); ?></strong>
            <?php if ( $pd_blocks_msg !== '' ) : ?>
                <br><em><?php echo esc_html( $pd_blocks_msg ); ?></em>
            <?php endif; ?>
            <br>
            <?php echo esc_html__( 'Verify your Client Key / Client Secret Key under the API Keys tab, then click Reload blocks below.', 'parcel-delivery' ); ?>
        </p>
        <p>
            <button type="button" class="button button-secondary pd_reload_blocks">
                <?php echo esc_html__( 'Reload blocks', 'parcel-delivery' ); ?>
            </button>
            <span class="pd_reload_blocks_status" style="margin-inline-start:8px;"></span>
        </p>
    </div>
</div>

<div class="pd_shipcal_container">

    <div class="pd_shipcal_method pd_parent">

        <div class="pd_shipcal_single_method">

            <input type="radio" name="pd_shipping_calculator_method" id="pd_manual" value="manual" <?= ($shipcal_method == 'api') ? "" : "checked" ;  ?>>

            <label for="pd_manual">Manual</label>

        </div>

        <div class="pd_shipcal_single_method">

            <input type="radio" name="pd_shipping_calculator_method" id="pd_api" value="api" <?= ($shipcal_method == 'api') ? "checked": "" ;  ?>>

            <label for="pd_api">API</label>

        </div>

    </div>

    <div class="pd_shipcal_manual_method pd_shipcal_method_wrap  <?= ($shipcal_method == 'api') ? 'pd_hide' : ''; ?>">

        <!-- <div class="pd_shipcal_block pd_parent">

            <select name="pd_shipping_calculator_block" id="pd_manual_block" data-method="manual"></select>

        </div> -->

        <div class="pd_shipcal_manual_action pd_shipcal_action">

            <div class="save_changes single_action">

                <button type="button" class="actionbtn saveChangesBtn">Save changes</button>

            </div>

            <div class="setStatusForAll single_action">

                <button type="button" class="actionbtn headerActionBtn" data-popup_id="setStatusManualPopup">Set status</button>

                <div id='setStatusManualPopup' class='white-popup mfp-hide manualStatusPopup'>

                    <h3>Status</h3><p>set status on/off for all blocks</p>

                    <div class="popup-input"><div class="pdStatus"><label class="pdSwitch pdSwitchFlat"><input class="pdSwitchInput" name="status_for_all" class="manualBlockStatus" type="checkbox" /><span class="pdSwitchLabel" data-on="On" data-off="Off"></span> <span class="pdSwitchHandle"></span> </label></div></div>

                    <div class='popup-action'><a href='javascript:void(0);' class='popup-btn save_btn set_status' data-method="manual">Yes</a><a href='javascript:void(0);' class='popup-btn cancel_btn' data-method="manual">No</a></div>

                </div>

            </div>

            <div class="setChargeForAll single_action">

                <button type="button" class="actionbtn headerActionBtn" data-popup_id="setChargeManualPopup">Set delivery charge</button>

                <div id='setChargeManualPopup' class='white-popup mfp-hide manualChargePopup'>

                    <h3>Delivery Charge</h3><p>set delivery charge for all blocks</p>

                    <div class="popup-input"><input type="number" name="delivery_charge_for_all" id="delivery_charge_for_all"></div>

                    <div class='popup-action'><a href='javascript:void(0);' class='popup-btn save_btn set_charge' data-method="manual">Yes</a><a href='javascript:void(0);' class='popup-btn cancel_btn' data-method="manual">No</a></div>

                </div>

            </div>

            

        </div>

        <div class="pd_shipcal_blocks">

            <div class="pd_shipcal_blocks_table_container manual_blocks_table_container"<?php echo $pd_has_blocks ? ' style="display:block;"' : ''; ?>>

                <table class="pd_shipcal_blocks_table manual_blocks_table custom-pagination cell-border" >

                    <thead>

                        <tr>

                            <!-- <th>S.N.</th> -->

                            <th>Block No.</th>

                            <th data-orderable="false">Block Name</th>

                            <th data-orderable="false">Minimum order</th>

                            <th data-orderable="false">Delivery Charge</th>

                            <th data-orderable="false">Status</th>

                        </tr>

                    </thead>

                    <tbody>
                        <?php $pd_build_rows( 'manual', $pd_manual_saved ); ?>
                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <div class="pd_shipcal_api_method pd_shipcal_method_wrap <?php if($shipcal_method != 'api') echo 'pd_hide'; ?>">

        <!-- <div class="pd_shipcal_block pd_parent">

            <select name="pd_shipping_calculator_block" id="pd_api_block" data-method="api"></select>

        </div> -->

        <div class="pd_shipcal_api_action pd_shipcal_action">
            <div class="save_changes single_action">

                <button type="button" class="actionbtn saveChangesBtn">Save changes</button>

            </div>

            <div class="setStatusForAll single_action">

                <button type="button" class="actionbtn headerActionBtn" data-popup_id="setStatusApiPopup">Set status</button>

                <div id='setStatusApiPopup' class='white-popup mfp-hide apiStatusPopup'>

                    <h3>Status</h3><p>set status on/off for all blocks</p>

                    <div class="popup-input"><div class="pdStatus"><label class="pdSwitch pdSwitchFlat"><input class="pdSwitchInput" name="status_for_all" class="apiBlockStatus" type="checkbox" /><span class="pdSwitchLabel" data-on="On" data-off="Off"></span> <span class="pdSwitchHandle"></span> </label></div></div>

                    <div class='popup-action'><a href='javascript:void(0);' class='popup-btn save_btn set_status' data-method="api">Yes</a><a href='javascript:void(0);' class='popup-btn cancel_btn' data-method="api">No</a></div>

                </div>

            </div>

            <div class="setChargeForAll single_action">

                <button type="button" class="actionbtn headerActionBtn" data-popup_id="setChargeApiPopup">Set additional delivery charge</button>

                <div id='setChargeApiPopup' class='white-popup mfp-hide apiChargePopup'>

                    <h3>Delivery Charge</h3><p>set delivery charge for all blocks</p>

                    <div class="popup-input"><input type="number" name="delivery_charge_for_all" id="delivery_charge_for_all"></div>

                    <div class='popup-action'><a href='javascript:void(0);' class='popup-btn save_btn set_charge' data-method="api">Yes</a><a href='javascript:void(0);' class='popup-btn cancel_btn' data-method="api">No</a></div>

                </div>

            </div>

            

        </div>

        <div class="pd_shipcal_blocks">

            <div class="pd_shipcal_blocks_table_container api_blocks_table_container"<?php echo $pd_has_blocks ? ' style="display:block;"' : ''; ?>>

                <table class="pd_shipcal_blocks_table api_blocks_table custom-pagination cell-border" >

                    <thead>

                        <tr>

                            <!-- <th>S.N.</th> -->

                            <th>Block No.</th>

                            <th data-orderable="false">Block Name</th>

                            <th data-orderable="false">Minimum order</th>

                            <th data-orderable="false">Additional Delivery Charge</th>

                            <th data-orderable="false">Status</th>

                        </tr>

                    </thead>

                    <tbody>
                        <?php $pd_build_rows( 'api', $pd_api_saved ); ?>
                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <hr>



    <div class="pd_parent">

        <div class="pd_parent pickup_location">

            <h4>Pickup Location</h4>

            <div class="">

                <label for="pickup_lat">Latitude (enter valid value)</label>

                <input type="number" name="pickup_lat" id="pickup_lat" value="<?php echo esc_attr( $pd_pickup_lat ); ?>">

            </div>

            <div class="">

                <label for="pickup_long">Longitude (enter valid value)</label>

                <input type="number" name="pickup_long" id="pickup_long" value="<?php echo esc_attr( $pd_pickup_lng ); ?>">

            </div>

            <div class="">

                <label for="pickup_address1">Address Line 1</label>

                <input type="text" name="pickup_address1" id="pickup_address1" value="<?php echo esc_attr( $pd_pickup_addr1 ); ?>">

            </div>

            <div class="">

                <label for="pickup_address2">Address Line 2</label>

                <input type="text" name="pickup_address2" id="pickup_address2" value="<?php echo esc_attr( $pd_pickup_addr2 ); ?>">

            </div>

            <div class="">

                <label for="pickup_block">Block</label>

                <select name="pickup_block" id="pickup_block">
                    <option value=""><?php echo esc_html__( 'Select block', 'parcel-delivery' ); ?></option>
                    <?php foreach ( $pd_all_blocks as $pd_pickup_block ) : ?>
                        <?php
                        $pd_pickup_block = is_array( $pd_pickup_block ) ? (object) $pd_pickup_block : $pd_pickup_block;
                        $pd_pickup_id    = isset( $pd_pickup_block->id ) ? $pd_pickup_block->id : '';
                        $pd_pickup_name  = isset( $pd_pickup_block->name ) && $pd_pickup_block->name !== '' ? (string) $pd_pickup_block->name : ( isset( $pd_pickup_block->nameAr ) ? (string) $pd_pickup_block->nameAr : (string) $pd_pickup_id );
                        ?>
                        <option value="<?php echo esc_attr( $pd_pickup_id ); ?>"<?php selected( (string) $pd_pickup_id, (string) $pd_pickup_block_id ); ?>><?php echo esc_html( $pd_pickup_name ); ?></option>
                    <?php endforeach; ?>
                </select>

            </div>

        </div>

    </div>
    <hr>
    <div class="pd_parent">

        <div class="pd_parent delivery_vehicle">

            <h4>Delivery Vehicle</h4>

            <div class="">

                <select name="delivery_vehicle" id="delivery_vehicle">
                    <option value="bike" <?php selected( 'bike', $pd_vehicle_saved ); ?>>Bike</option>
                    <option value="van" <?php selected( 'van', $pd_vehicle_saved ); ?>>Van</option>
                    <option value="car" <?php selected( 'car', $pd_vehicle_saved ); ?>>Car</option>
                </select>

            </div>            

        </div>

    </div>

    <input type="hidden" name="pd_manual_blocks_info" value="<?php echo esc_attr( wp_json_encode( $pd_rows_data['manual'] ) ); ?>" class="final_blocks final_blocks_manual">
    <input type="hidden" name="pd_api_blocks_info" value="<?php echo esc_attr( wp_json_encode( $pd_rows_data['api'] ) ); ?>" class="final_blocks final_blocks_api">
    <input type="hidden" name="pdsc_method" value="<?php echo esc_attr( $shipcal_method == 'api' ? 'api' : 'manual' ); ?>" class="pdsc_method_value">

</div> 

<script>
/**
 * Lightweight handlers for the Shipping Calculator screen.
 * Runs independently of DataTables / Magnific Popup so the admin tools keep
 * working even if a CDN asset fails to load.
 */
(function () {
    if (typeof jQuery === 'undefined') { return; }
    jQuery(function ($) {
        var $container = $('.pd_shipcal_container');
        if (!$container.length) { return; }
        var $form = $container.closest('form');

        // Build a professional sticky toolbar (Save + method switch) at the top
        // of the container and wrap Pickup Location + Delivery Vehicle in a
        // two-column "settings card" layout above the blocks table.
        var initialMethod = $('input[name="pd_shipping_calculator_method"]:checked').val() || 'manual';
        var $toolbar = $(
            '<div class="pdsc-toolbar">' +
                '<div class="pdsc-toolbar-left">' +
                    '<strong>Shipping method:</strong>' +
                    '<div class="pdsc-method">' +
                        '<input type="radio" id="pdsc_manual" name="pdsc_method" value="manual"' + (initialMethod === 'manual' ? ' checked' : '') + '><label for="pdsc_manual">Manual</label>' +
                        '<input type="radio" id="pdsc_api" name="pdsc_method" value="api"' + (initialMethod === 'api' ? ' checked' : '') + '><label for="pdsc_api">API</label>' +
                    '</div>' +
                '</div>' +
                '<div class="pdsc-actions">' +
                    '<button type="button" class="button actionbtn pdsc-set-status">Set status</button>' +
                    '<button type="button" class="button actionbtn pdsc-set-charge">Set delivery charge</button>' +
                    '<button type="button" class="button button-primary actionbtn pdsc-save">Save changes</button>' +
                '</div>' +
            '</div>'
        );
        $container.prepend($toolbar);

        // Cards row for Pickup Location and Delivery Vehicle.
        var $pickup   = $container.find('.pickup_location').closest('.pd_parent').last();
        var $vehicle  = $container.find('.delivery_vehicle').closest('.pd_parent').last();
        if ($pickup.length && $vehicle.length) {
            $pickup.addClass('pdsc-card').children('.pickup_location').children('div').addClass('row');
            $vehicle.addClass('pdsc-card').children('.delivery_vehicle').children('div').addClass('row');
            var $cards = $('<div class="pdsc-cards"></div>').append($pickup).append($vehicle);
            $toolbar.after($cards);
            $container.find('hr').remove();
        }

        function currentMethod() {
            return $('input[name="pdsc_method"]:checked').val() || 'manual';
        }

        function buildPickupLocationJson() {
            // The PHP save handler expects pd_pickup_location as a JSON blob.
            // We assemble it here from the individual fields the admin filled in.
            var blockId = $('#pickup_block').val() || '';
            var pickupBlockJson = null;
            try {
                var allBlocks = (window.pdVar && pdVar.pd_all_blocks_info && pdVar.pd_all_blocks_info.block_data) || [];
                for (var i = 0; i < allBlocks.length; i++) {
                    if (String(allBlocks[i].id) === String(blockId)) {
                        pickupBlockJson = allBlocks[i];
                        break;
                    }
                }
            } catch (err) {}
            return {
                pickup_block_json: pickupBlockJson,
                pickup_latitude:   $('input[name="pickup_lat"]').val()       || '',
                pickup_longitude:  $('input[name="pickup_long"]').val()      || '',
                pickup_address1:   $('input[name="pickup_address1"]').val()  || '',
                pickup_address2:   $('input[name="pickup_address2"]').val()  || ''
            };
        }

        function syncPickupAndVehicleHiddenInputs() {
            // Mirror the visible fields into the field names PHP actually reads.
            var $f = $form && $form.length ? $form : $('form#mainform');
            if (!$f.length) { return; }
            function ensureHidden(name, value) {
                var $h = $f.find('input[type="hidden"][name="' + name + '"]').first();
                if (!$h.length) {
                    $h = $('<input type="hidden" />').attr('name', name).appendTo($f);
                }
                $h.val(value);
            }
            ensureHidden('pd_pickup_location', JSON.stringify(buildPickupLocationJson()));
            ensureHidden('pd_delivery_vehicle', $('#delivery_vehicle').val() || '');
            var method = currentMethod();
            $('input[name="pd_shipping_calculator_method"][value="' + method + '"]').prop('checked', true);
            $('input.pdsc_method_value').val(method);
            ensureHidden('pdsc_method', method);
            ensureHidden('pd_shipping_calculator_method', method);
        }

        function doSave() {
            flushStoreToHidden();
            syncPickupAndVehicleHiddenInputs();
            var $f = $form && $form.length ? $form : $('form#mainform');
            if (!$f.length) { $f = $('form').first(); }
            if (!$f.length) { window.alert('Cannot find the settings form to submit.'); return; }
            if (!$f.find('input[name="save"]').length) {
                $f.append('<input type="hidden" name="save" value="Save changes" />');
            }
            // Disable the "unsaved changes" guard WooCommerce attaches to the
            // settings form so the user does not get a "Leave site?" prompt
            // when we submit programmatically.
            window.onbeforeunload = null;
            try { $(window).off('beforeunload'); } catch (err) {}
            $('body').removeClass('unsaved-changes');
            $f.removeClass('unsaved-changes');
            // Use native submit so jQuery-only handlers cannot cancel it.
            $f[0].submit();
        }

        function doSetStatus() {
            var method = currentMethod();
            var enable = window.confirm('Enable ALL ' + method.toUpperCase() + ' blocks?\nOK = Enable, Cancel = Disable.');
            // Update the store for ALL blocks (including non-visible pages)
            blockStore[method].forEach(function (entry) { entry.status = enable; });
            // Update visible DOM inputs
            $('.' + method + '_blocks_table tbody input[name="' + method + '_block_status"]').prop('checked', enable);
            flushStoreToHidden();
        }

        function doSetCharge() {
            var method = currentMethod();
            var value = window.prompt('Set delivery charge for ALL ' + method.toUpperCase() + ' blocks:', '0');
            if (value === null) { return; }
            value = parseFloat(value);
            if (isNaN(value) || value < 0) { return; }
            // Update the store for ALL blocks (including non-visible pages)
            blockStore[method].forEach(function (entry) { entry.del_charge = value; });
            // Update visible DOM inputs
            $('.' + method + '_blocks_table tbody input[name="' + method + '_block_charge"]').val(value);
            flushStoreToHidden();
        }

        function applyMethodVisibility(m) {
            $('.pd_shipcal_manual_method').toggleClass('pd_hide', m !== 'manual');
            $('.pd_shipcal_api_method').toggleClass('pd_hide', m !== 'api');
        }
        // Initial visibility (in case the server-rendered classes drifted).
        applyMethodVisibility(initialMethod);

        // Method radios in the toolbar mirror the real radios and swap tables.
        $toolbar.on('change', 'input[name="pdsc_method"]', function () {
            var m = $(this).val();
            $('input[name="pd_shipping_calculator_method"][value="' + m + '"]').prop('checked', true);
            $('input.pdsc_method_value').val(m);
            applyMethodVisibility(m);
        });

        // Bind toolbar buttons DIRECTLY so we never depend on the legacy
        // headerActionBtn handler (which depends on Magnific Popup and may
        // throw if the CDN script failed to load).
        $toolbar.on('click', '.pdsc-save', function (e) { e.preventDefault(); doSave(); });
        $toolbar.on('click', '.pdsc-set-status', function (e) { e.preventDefault(); doSetStatus(); });
        $toolbar.on('click', '.pdsc-set-charge', function (e) { e.preventDefault(); doSetCharge(); });

        // Also keep the legacy in-page buttons working for safety.
        $container.on('click', '.saveChangesBtn', function (e) { e.preventDefault(); doSave(); });
        $container.on('click', '[data-popup_id="setStatusManualPopup"], [data-popup_id="setStatusApiPopup"]', function (e) { e.preventDefault(); e.stopImmediatePropagation(); doSetStatus(); });
        $container.on('click', '[data-popup_id="setChargeManualPopup"], [data-popup_id="setChargeApiPopup"]', function (e) { e.preventDefault(); e.stopImmediatePropagation(); doSetCharge(); });

        // --- Block data store ---------------------------------------------------
        // We keep a JS copy of every block's data so we never have to read
        // from the DOM (which is unreliable because DataTables detaches
        // non-visible rows).  The store is initialised from the server-
        // rendered hidden inputs and updated incrementally on every change.
        var blockStore = { manual: [], api: [] };
        ['manual', 'api'].forEach(function (method) {
            try {
                var raw = $('input.final_blocks_' + method).val();
                blockStore[method] = raw ? JSON.parse(raw) : [];
            } catch (e) { blockStore[method] = []; }
        });

        function flushStoreToHidden() {
            ['manual', 'api'].forEach(function (method) {
                $('input.final_blocks_' + method).val(JSON.stringify(blockStore[method]));
            });
        }

        // Expose on window so script.js can call these instead of appending
        // duplicate hidden inputs (which break when JSON contains quotes).
        window.pdFlushStore   = flushStoreToHidden;
        window.pdSyncPickup   = syncPickupAndVehicleHiddenInputs;

        // When a single table input changes, update only the affected row.
        $container.on('change', '.pd_shipcal_blocks_table input', function () {
            var $input = $(this);
            var $row   = $input.closest('tr');
            var rowId  = parseInt($row.attr('id'), 10);
            var method = $row.attr('data-method') || ($row.closest('.manual_blocks_table').length ? 'manual' : 'api');
            var store  = blockStore[method];
            var entry  = null;
            for (var i = 0; i < store.length; i++) {
                if (store[i].id === rowId) { entry = store[i]; break; }
            }
            if (!entry) {
                entry = {
                    id:         rowId,
                    _id:        $row.attr('data-_id') || '',
                    name:       $row.find('.pdBlockName span').text(),
                    status:     false,
                    min_order:  0,
                    del_charge: 0
                };
                store.push(entry);
            }
            var inputName = $input.attr('name') || '';
            if (inputName.indexOf('_block_status') !== -1) {
                entry.status = $input.is(':checked');
            } else if (inputName.indexOf('_block_min_order') !== -1) {
                entry.min_order = parseFloat($input.val()) || 0;
            } else if (inputName.indexOf('_block_charge') !== -1) {
                entry.del_charge = parseFloat($input.val()) || 0;
            }
            flushStoreToHidden();
        });

        // Search + pagination for each blocks table.
        $('.pd_shipcal_blocks_table_container').each(function () {
            var $wrap = $(this);
            if ($wrap.find('.pd_table_toolbar').length) { return; }
            var $table = $wrap.find('table.pd_shipcal_blocks_table');
            var $tbody = $table.find('tbody');
            var pageSize = 25;
            var $toolbar = $(
                '<div class="pd_table_toolbar">' +
                    '<input type="search" class="pd_table_search" placeholder="Search block name or number..." />' +
                    '<div class="pd_table_pager">' +
                        '<button type="button" class="button pd_prev_page">&laquo; Prev</button>' +
                        '<span class="pd_page_info"></span>' +
                        '<button type="button" class="button pd_next_page">Next &raquo;</button>' +
                    '</div>' +
                '</div>'
            );
            $wrap.prepend($toolbar);
            var currentPage = 1;
            var filterText = '';

            function refresh() {
                var $rows = $tbody.find('tr');
                var $matched = $rows.filter(function () {
                    if (!filterText) { return true; }
                    return $(this).text().toLowerCase().indexOf(filterText) !== -1;
                });
                $rows.hide();
                var total = $matched.length;
                var pages = Math.max(1, Math.ceil(total / pageSize));
                if (currentPage > pages) { currentPage = pages; }
                var start = (currentPage - 1) * pageSize;
                $matched.slice(start, start + pageSize).show();
                $toolbar.find('.pd_page_info').text(
                    total === 0
                        ? 'No matching blocks'
                        : 'Page ' + currentPage + ' of ' + pages + ' (' + total + ' blocks)'
                );
                $toolbar.find('.pd_prev_page').prop('disabled', currentPage <= 1);
                $toolbar.find('.pd_next_page').prop('disabled', currentPage >= pages);
            }
            $toolbar.find('.pd_table_search').on('input', function () {
                filterText = $(this).val().toLowerCase();
                currentPage = 1;
                refresh();
            });
            $toolbar.find('.pd_prev_page').on('click', function () { currentPage = Math.max(1, currentPage - 1); refresh(); });
            $toolbar.find('.pd_next_page').on('click', function () { currentPage = currentPage + 1; refresh(); });
            refresh();
        });
    });
})();
</script>





