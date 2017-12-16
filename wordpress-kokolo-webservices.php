<?php
/*
Plugin Name:  Wordpress Kokolo Webservices
Description:  Let Wordpress&WooCommerce communicate with Kokolo Webservices
Author:       Pierre Labadille
*/

//wp direct access basic security
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
//file containing all credentials needed to call the kokolo webservices
require_once( 'kokolo-config.php' );

/**
* This action hook is called when the paiement is confirmed
* For test purpose you can comment the add_action('woocommerce_payment_complete') and uncomment the
* add_action( 'woocommerce_checkout_order_processed'). It do the job before the payment instead.
*
* @see http://woocommerce.wp-a2z.org/oik_hook/woocommerce_payment_complete/
* @call transmist_data_to_kokolo( $order_id )
* @return <null>
*/ 
#add_action( 'woocommerce_payment_complete', 'transmist_data_to_kokolo', 5, 1 ); #comment for debug
#add_action( 'woocommerce_checkout_order_processed', 'transmist_data_to_kokolo', 5, 1 ); #uncomment for debug

/**
* This function is used by the WooCommerce Hook upper.
* It will gather needed data and use the appropriate function bellow to send them to the kololo webservices.
*
* To gather order information we use :
* @see https://docs.woocommerce.com/wc-apidocs/class-WC_Order.html
* To gather specific item information we use :
* @see https://docs.woocommerce.com/wc-apidocs/class-WC_Order_Item.html
* @see https://docs.woocommerce.com/wc-apidocs/class-WC_Product.html
* @see https://docs.woocommerce.com/wc-apidocs/class-WC_Product_Variation.html
* @param <int> the kokolo order id, given by the hook
* @return <null>
*/
function transmist_data_to_kokolo( $order_id ){   
    $order = wc_get_order( $order_id );
    $user = $order->get_user();
    $order_details = array(
        'order_line_ref' => $order->get_order_number(),
        'delivery' => 'std',
        'first' => $order->get_shipping_first_name(),
        'last' => $order->get_shipping_last_name(),
        'adress1' => $order->get_shipping_address_1(),
        'adress2' => $order->get_shipping_address_2(),
        'postcode' => $order->get_shipping_postcode(),
        'city' => $order->get_shipping_city(),
        'iso_country' => get_post_meta( $order_id, '_shipping_country', true ),
        'mail' => $order->get_billing_email(),
    );
    $order_num_kokolo = uniqid();

    /** 1) First webservice call : order initialization (function is called order_insert in Kololo documentation) */
    try {
        order_insert_request_to_kokolo( $order_num_kokolo );
    } catch ( Exception $e ) {
        $error = "L'initialisation de la commande vers Kokolo a échouée. Détail de l'erreur renvoyée par Kokolo : " . $e;
        send_error_mail( $products_details, $order_num_kokolo, $error );
        return null;
    }

    $items = $order->get_items();

    $products_details = [];
    foreach ($items as $item) {
        $product_id = $item->get_product_id();
        $product = wc_get_product( $product_id );
        $variation = wc_get_product( $item->get_variation_id() );
        $variation_attributes = $variation->get_attributes();
        $weight = $product->get_weight();
        $url_screen = urlencode ( $variation->get_permalink() );

        $product_details = array(
            'order_line_id' => $variation->get_sku(), #String(20) Id de ligne de commande (unique par article)
            'order_line_ref' => $order_details['order_line_ref'], #String(20) Référence commande du client final (telle qu’elle lui a été communiqué : unique par destinataire)
            'support' => get_post_meta( $product_id, 'support', true), #String(50) Support
            'design' => get_post_meta( $product_id, 'design', true), #String(50) Motif
            'size' => array_key_exists( 'pa_tailles', $variation_attributes ) ? $variation_attributes['pa_tailles'] : 'default', #String(20) Taille
            'color' => get_post_meta( $product_id, 'color', true), #String(20) Couleur
            'weight' => !empty( $weight ) ? intval( $weight * 1000 ) : 0, #Int(4) Poids unitaire exprimé en grammes
            'qty' => $item->get_quantity(), #Int(4) Si la quantité est supérieure à 1, le système créé x lignes de commandes (kEtiq gère les produits à la SKU)
            'url_screen' => $url_screen, #String(250) Url de la maquette du produit
            'delivery' => $order_details['delivery'], #String(3) ’std’ | ‘soc’ <- std = la Poste and soc Colissimo
            'first' => $order_details['first'], #String(50) Prénom
            'last' => $order_details['last'], #String(50) Nom
            'adress1' => $order_details['adress1'], #String(100) Adresse
            'adress2' => $order_details['adress2'], #String(100) Complément d’adresse [Optionnel]
            'postcode' => $order_details['postcode'], #String(10) CP
            'city' => $order_details['city'], #String(50) Ville
            'iso_country' => $order_details['iso_country'], #String(2) Code ISO du Pays
            'mail' => $order_details['mail'], #String(250) Adresse email du destinataire
        );

        /** 2) Second to n webservices call : add a product to the order (function is called insert_product in Kololo documentation) */
        $products_details[] = $product_details;
        try {
            product_insert_to_kokolo( $product_details, $order_num_kokolo );
        } catch ( Exception $e ) {
            $error = "L'envois du produit" . $product->name() . "à Kokolo a échoué. Détail de l'erreur renvoyée par Kokolo : " . $e;
            send_error_mail( $products_details, $order_num_kokolo, $error );
            return null;
        }
    }

    /** 3) n+1 webservice call : we check if all product has been added to the Kololo order (function is called order_select in Kololo documentation) */
    try {
        $is_kokolo_order_completed = kokolo_order_is_complete( $order_num_kokolo, $products_details );
    } catch ( Exception $e ) {
        $error = "La création de la commande et l'ajout des produits a correctement fonctionnée mais la vérification de son contenu chez Kokolo a entrainé une erreur. Détail de l'erreur renvoyée par Kokolo : " . $e;
        send_error_mail( $products_details, $order_num_kokolo, $error );
        return null;
    }

    if ( $is_kokolo_order_completed ) {
        /** 4) final webservice call : we tell Kololo our order is complete (function is called order_update in Kololo documentation) */
        try {
            confirm_kokolo_order( $order_num_kokolo );
        } catch ( Exception $e ) {
            $error = "Une erreur est survenue lors de demande de finalisation de commande chez Kokolo, il ne faut théoriquement que leur demander de valider la commande manuellement. Détail de l'erreur renvoyée par Kokolo : " . $e;
            send_error_mail( $products_details, $order_num_kokolo, $error );
            return null;
        }
        send_success_mail( $products_details );
    } else {
        send_error_mail( $products_details, $order_num_kokolo, "Unknow error : tout a visiblement fonctionné mais le nombre de ligne dans la commande chez Kokolo ne correspond pas à la quantité de produits effectivement commandé." );
    }
}

/**
* This function is used to generate needed kokolo credentials
*
* @param <null>
* @return <array> kokolo_credentials
* @throws <Exception> if kokolo-config.php constant are not defined or empty
*/
function kokolo_credentials() {
    date_default_timezone_set('UTC');
    $date = date( 'YmdHis' );

    return array(
        'id' => KOKOLO_CLIENT_ID,
        'key' => md5( KOKOLO_CLIENT_ID . $date . KOKOLO_PASS ),
        'date' => $date,
        'version' => KOKOLO_VERSION,
    );
}

/**
* Prepare an order initialization call to Kokolo and call a function to send it
*
* @param <int> the kokolo order id
* @return <null>
* @throws <Exception> if the Kokolo webservice response contain an error
*/
function order_insert_request_to_kokolo( $order_num ) {
    $credentials = kokolo_credentials();

    $function = 'order_insert?order_num=' . $order_num;

    $request = array(
        'id' => $credentials['id'],
        'dt' => $credentials['date'],
        'key' => $credentials['key'],
        'version' => $credentials['version'],
        'function' => $function,
    );

    $json = json_encode( $request );

    $result = send_request( $json );

    if ( $result['error'] == true ) {
        throw new Exception( (string) $result['desc'] );
    }
}

/**
* Prepare a call to add a product to the Kokolo order and call a function to send it
*
* @param <Array> containing the informations needed for one product
* @param <int> the kokolo order id
* @return <null>
* @throws <Exception> if the Kokolo webservice response contain an error
*/
function product_insert_to_kokolo( $product_detail, $order_num ) {
    $credentials = kokolo_credentials();

    $function = 'product_insert?order_num=' . $order_num;
    $function .= '&order_line_id=' . $product_detail['order_line_id'];
    $function .= '&order_line_ref=' . $product_detail['order_line_ref']; 
    $function .= '&support=' . $product_detail['support'];
    $function .= '&design=' . $product_detail['design'];
    $function .= '&size=' . $product_detail['size'];
    $function .= '&color=' . $product_detail['color'];
    $function .= '&weight=' . $product_detail['weight'];
    $function .= '&qty=' . $product_detail['qty'];
    $function .= '&url_screen=' . $product_detail['url_screen'];
    $function .= '&delivery=' . $product_detail['delivery'];
    $function .= '&first=' . $product_detail['first'];
    $function .= '&last=' . $product_detail['last'];
    $function .= '&adress1=' . $product_detail['adress1'];
    $function .= '&adress2=' . $product_detail['adress2'];
    $function .= '&postcode=' . $product_detail['postcode']; 
    $function .= '&city=' . $product_detail['city'];
    $function .= '&iso_country=' . $product_detail['iso_country'];
    $function .= '&mail=' . $product_detail['mail'];

    $request = array(
        'id' => $credentials['id'],
        'dt' => $credentials['date'],
        'key' => $credentials['key'],
        'version' => $credentials['version'],
        'function' => $function,
    );

    $json = json_encode( $request );

    $result = send_request( $json );

    if ( $result['error'] == true ) {
        throw new Exception( (string) $result['desc'] );
    }
}

/**
* Prepare a call to validate/close the Kokolo order and call a function to send it
*
* @param <Array> containing the informations needed for all the order products
* @param <int> the kokolo order id
* @return <null>
* @throws <Exception> if the Kokolo webservice response contain an error
*/
function kokolo_order_is_complete( $order_num, $products_details ) {
    $credentials = kokolo_credentials();
    $function = 'order_select?order_num=' . $order_num;

    $request = array(
        'id' => $credentials['id'],
        'dt' => $credentials['date'],
        'key' => $credentials['key'],
        'version' => $credentials['version'],
        'function' => $function,
    );

    $json = json_encode( $request );

    $result = send_request( $json, true );

    if ( $result['error'] == true ) {
        throw new Exception( (string) $result['desc'] );
    }

    $order = $result['desc'];
    $order_line = array_key_exists('nb_lines', $order['body']['_1']) ? $order['body']['_1']['nb_lines'] : 0;

    $products_number = 0;
    foreach ( $products_details as $product_details ) {
        $products_number += $product_details['qty'];
    }

    return $products_number == $order_line;
}

/**
* Check if the order from Kokolo is equal to our order
*
* @param <Array> containing the informations needed for all the order products
* @param <int> the kokolo order id
* @return <bool>
* @throws <Exception> if the Kokolo webservice response contain an error
*/
function confirm_kokolo_order( $order_num ) {
    $credentials = kokolo_credentials();
    $function = 'order_update?order_num=' . $order_num . '&order_state=ready';

    $request = array(
        'id' => $credentials['id'],
        'dt' => $credentials['date'],
        'key' => $credentials['key'],
        'version' => $credentials['version'],
        'function' => $function,
    );

    $json = json_encode($request);

    $result = send_request( $json );

    if ( $result['error'] == true ) {
       throw new Exception( (string) $result['desc'] );
    }
}

/**
* Call a function to send a curl call to the Kokolo webservices. Handle error and recall n time
* in case of curl error before throwing an Exception.
*
* @param <string> containing json formated informations to send
* @return <array> if array['error'] is true then array['desc'] contain the detail of the Kokolo error. Else array['desc'] contain the webservices response.
* @throws <Exception> if CURL return error MAX_ATTEMPT time.
*/
function send_request( $request ) {
    $max_attempt = 3;
    for ( $i=0; $i < $max_attempt; $i++ ) {
        try {
            $response = curl_call( $request );
        } catch ( Exception $e ) {
            sleep( 5 );
            if ( $i == $max_attempt-1 ) {
                throw new Exception( "Curl error remain after " . $max_attempt . ". " . $e->getMessage() );
            } 
            continue;
        }
        break;
    }

    if ( intval( $response['head']['err'] ) > 0 ) {
        return [
            'error' => true,
            'desc' => $response['head']['err_desc'],
        ];
    }

    return [
        'error' => false,
        'desc' => $response,
    ];
}

/**
* Send a request using curl to Kokolo webservices
*
* @param <string> containing json formated informations to send
* @return <array> the json decoded response from Kokolo webservices
* @throws <Exception> if a CURL error occured
* @throws <Exception> if kokolo-config.php constants are not defined or empty
*/
function curl_call( $request ) {
    $curl = curl_init();
    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => KOKOLO_URL,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_POSTFIELDS => array('param' => $request)
        )
    );

    $response = curl_exec( $curl );
    $err = curl_error( $curl );

    curl_close( $curl );

    if ( $err ) {
        throw new Exception( "Curl Error #:" . $err );
    }

    return json_decode( $response, true );
}

/**
* Send a mail to the admin with all data that should be send to the Kokolo API and the detail of the error.
*
* @param <Array> containing the informations needed for all the order products* @param <int> the kokolo order id
* @param <string> the reported error
* @throws <Exception> if kokolo-config.php constants are not defined or empty
*/
function send_error_mail( $products_details, $order_num, $err ) {
    $to = KOKOLO_ADMIN_EMAIL;
    $subject = "Erreur pendant l'envois de données de commande à Kokolo";
    $order_info_str = products_details_to_string( $products_details );

    $message = <<<EOT
        Bonjour,

        L'envois de données d'une commande payée à Kokolo a échoué. Voici les données requise par Kokolo à leurs transmettre :
        Numéro de commande Kotolo : {$order_num}
        {$order_info_str}

        Voici le message d'erreur retourné par Kokolo/nous :

        {$err}
EOT;

    $sent = wp_mail( $to, $subject, $message );
}

/**
* Send a mail to the admin with all data sent to the Kokolo API.
*
* @param <Array> containing the informations needed for all the order products
* @throws <Exception> if kokolo-config.php constants are not defined or empty
*/
function send_success_mail( $products_details ) {
    $to = KOKOLO_ADMIN_EMAIL;
    $subject = "Succès de l'envois de données de commande à Kokolo";
    $order_info_str = products_details_to_string( $products_details );

    $message = <<<EOT
        Bonjour,

        Voici les données de commande correctement transmise à Kokolo :

        {$order_info_str}
EOT;

    $sent = wp_mail( $to, $subject, $message );
}

/**
* Transform all the order information from array to string.
*
* @param <Array> containing the informations needed for all the order products
* @return <string>
*/
function products_details_to_string( $products_details ) {
    $order_info_str = 'order_line_ref : ' . (string) $products_details[0]['order_line_ref'] . "\n";
    $order_info_str .= 'delivery : ' . (string) $products_details[0]['delivery'] . "\n";
    $order_info_str .= 'first : ' . (string) $products_details[0]['first'] . "\n";
    $order_info_str .= 'last : ' . (string) $products_details[0]['last'] . "\n";
    $order_info_str .= 'adress1 : ' . (string) $products_details[0]['adress1'] . "\n";
    $order_info_str .= 'adress2 : ' . (string) $products_details[0]['adress2'] . "\n";
    $order_info_str .= 'postcode : ' . (string) $products_details[0]['postcode'] . "\n";
    $order_info_str .= 'city : ' . (string) $products_details[0]['city'] . "\n";
    $order_info_str .= 'iso_country : ' . (string) $products_details[0]['iso_country'] . "\n";
    $order_info_str .= 'mail : ' . (string) $products_details[0]['mail'] . "\n\n";

    $i = 1;
    foreach ($products_details as $product_details) {
        $order_info_str .= 'Produit numéro ' . (string) $i . " : \n";
        $order_info_str .= 'order_line_id : ' . (string) $product_details['order_line_id'] . "\n";
        $order_info_str .= 'support : ' . (string) $product_details['support'] . "\n";
        $order_info_str .= 'design : ' . (string) $product_details['design'] . "\n";
        $order_info_str .= 'size : ' . (string) $product_details['size'] . "\n";
        $order_info_str .= 'color : ' . (string) $product_details['color'] . "\n";
        $order_info_str .= 'weight : ' . (string) $product_details['weight'] . "\n";
        $order_info_str .= 'qty : ' . (string) $product_details['qty'] . "\n";
        $order_info_str .= 'url_screen : ' . (string) $product_details['url_screen'] . "\n\n";
        $i++;
    }

    return $order_info_str;
}
