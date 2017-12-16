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

    // Custom fields (Require ACF)

// Models
    // Kokolo specific models
    require_once( 'models/kokolo/kokolo_curl.php' );
    require_once( 'models/kokolo/kokolo_web_services.php' );

    // Entities
