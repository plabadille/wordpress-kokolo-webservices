<?php

if(function_exists("register_field_group"))
{
  register_field_group(array (
    'id' => 'acf_details-du-design-produit-kokolo',
    'title' => 'Détails du design produit Kokolo',
    'fields' => array (
      array (
        'key' => 'field_5a42762a74f97',
        'label' => 'Motif print avant',
        'name' => 'kokolo_front_print_design_image',
        'type' => 'image',
        'save_format' => 'object',
        'preview_size' => 'thumbnail',
        'library' => 'all',
        'instructions' => 'Motif HD à imprimer sur la face avant du produit. Ne pas ajouter si pas de motif avant.',
      ),
      array (
        'key' => 'field_5a42770274f98',
        'label' => 'Nombre de couleur print avant',
        'name' => 'kokolo_front_print_colors_count',
        'type' => 'number',
        'instructions' => 'Nombre de couleur du motif avant à imprimer (1-99). Si inconnu laissez la valeur par défaut.',
        'required' => 1,
        'default_value' => 99,
        'placeholder' => '',
        'prepend' => '',
        'append' => '',
        'min' => 1,
        'max' => 99,
        'step' => '',
      ),
      array (
        'key' => 'field_5a4277f474f9a',
        'label' => 'Motif print arrière',
        'name' => 'kokolo_back_print_design_image',
        'type' => 'image',
        'save_format' => 'object',
        'preview_size' => 'thumbnail',
        'library' => 'all',
        'instructions' => 'Motif HD à imprimer sur la face arrière du produit. Ne pas ajouter si pas de motif arrière.',
      ),
      array (
        'key' => 'field_5a4277a774f99',
        'label' => 'Nombre de couleur print arrière',
        'name' => 'kokolo_back_print_colors_count',
        'type' => 'number',
        'instructions' => 'Nombre de couleur du motif avant à imprimer (1-99). Si inconnu laissez la valeur par défaut.',
        'required' => 1,
        'default_value' => 99,
        'placeholder' => '',
        'prepend' => '',
        'append' => '',
        'min' => 1,
        'max' => 99,
        'step' => '',
      ),
    ),
    'location' => array (
      array (
        array (
          'param' => 'post_type',
          'operator' => '==',
          'value' => 'kokolo_design',
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
