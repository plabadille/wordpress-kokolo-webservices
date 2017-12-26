<?php

if(function_exists("register_field_group"))
{
  register_field_group(array (
    'id' => 'acf_informations-requises-par-kokolo',
    'title' => 'Informations requises par Kokolo',
    'fields' => array (
      array (
        'key' => 'field_5a427b9feec41',
        'label' => 'Design produit',
        'name' => 'kokolo_design_product_post',
        'type' => 'post_object',
        'instructions' => 'Choisissez le design souhaité pour cet article. Si le design souhaité n\'est pas disponible, veuillez le créer via Products Design accessible via le menu principal.',
        'required' => 1,
        'post_type' => array (
          0 => 'kokolo_design',
        ),
        'taxonomy' => array (
          0 => 'all',
        ),
        'allow_null' => 0,
        'multiple' => 0,
      ),
      array (
        'key' => 'field_5a427c30eec42',
        'label' => 'Couleur du produit',
        'name' => 'kokolo_product_color',
        'type' => 'text',
        'instructions' => 'Couleur de fond du produit en fonction de ce qui a été défini avec Kokolo.',
        'required' => 1,
        'default_value' => '',
        'placeholder' => 'vert',
        'prepend' => '',
        'append' => '',
        'formatting' => 'html',
        'maxlength' => '',
      ),
    ),
    'location' => array (
      array (
        array (
          'param' => 'post_type',
          'operator' => '==',
          'value' => 'product',
          'order_no' => 0,
          'group_no' => 0,
        ),
      ),
    ),
    'options' => array (
      'position' => 'normal',
      'layout' => 'no_box',
      'hide_on_screen' => array (
      ),
    ),
    'menu_order' => 0,
  ));
}
