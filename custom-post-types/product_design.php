<?php

function cptui_register_my_cpts_kokolo_design() {

  /**
   * Post Type: Products Design.
   */

  $labels = array(
    "name" => __( "Products Design", "float" ),
    "singular_name" => __( "Product Design", "float" ),
    "menu_name" => __( "Products Design", "float" ),
  );

  $args = array(
    "label" => __( "Products Design", "float" ),
    "labels" => $labels,
    "description" => "Product design for to communicate to the Kokolo webservices",
    "public" => true,
    "publicly_queryable" => true,
    "show_ui" => true,
    "show_in_rest" => false,
    "rest_base" => "",
    "has_archive" => false,
    "show_in_menu" => true,
    "exclude_from_search" => false,
    "capability_type" => "post",
    "map_meta_cap" => true,
    "hierarchical" => false,
    "rewrite" => array( "slug" => "kokolo_design", "with_front" => true ),
    "query_var" => true,
    "supports" => array( "title" ),
  );

  register_post_type( "kokolo_design", $args );
}

add_action( 'init', 'cptui_register_my_cpts_kokolo_design' );
