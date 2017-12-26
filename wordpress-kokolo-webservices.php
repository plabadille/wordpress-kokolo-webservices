<?php
/*
Plugin Name:  Wordpress Kokolo Webservices
Description:  Let Wordpress&WooCommerce communicate with Kokolo Webservices
Author:       Pierre Labadille
*/

//wp direct access basic security
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// ENV
require_once( 'kokolo-config.php' );

// Hooks

// Functions

// Entity declaration
    // Custom post types (Require CPT UI plugin)
    require_once( 'custom-post-types/product_design.php' );

    // Custom fields (Require ACF)
    require_once( 'custom-fields/product_details.php' );
    require_once( 'custom-fields/product_design_details.php' );

// Models
    // Kokolo specific models
    require_once( 'models/kokolo/kokolo_curl.php' );
    require_once( 'models/kokolo/kokolo_web_services.php' );

    // Entities
