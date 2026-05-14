<?php

/* 
 *If this file is called directly, abort.
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('pd_init')) {
    function pd_init(){
        include(PARCEL_DELIVERY_DIR_PATH . 'modules/included_files.php');
    }
}
pd_init();