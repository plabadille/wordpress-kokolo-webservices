<?php
/**
* Class to prepare data to send to Kokolo Webservice and format response
* Based on Kokolo specs v1_6 : All Kokolo function are listed below.
*
* @author Pierre Labadille
* @
*/
class KokoloWebServices {

    private $kokolo_curl;

    public function __construct() {
        $this->kokolo_curl = new KokoloCurl();
    }

    /**
    * Return a ready to send request for Kokolo webservice.
    *
    * @param $kokolo_function <string> The name of the Kokolo function you want to call
    * @param $parameters <Array> The parameters the Kokolo function need
    * @return <string> the json encoded request
    */
    private function build_request( string $kokolo_function, Array $parameters = [] ) {
        date_default_timezone_set('UTC');
        $date = date( 'YmdHis' );
        $key = md5( KOKOLO_CLIENT_ID . $date . KOKOLO_PASS );
        
        $has_param = !empty($parameters);

        if ( $has_param ) {
            $parameters_query = http_build_query( $parameters, '', '&' );
            $function = $kokolo_function . "?" . $parameters_query;
        } else {
            $function = $kokolo_function;
        }

        $request = array(
            'id' => KOKOLO_CLIENT_ID,
            'dt' => $date,
            'key' => $key,
            'version' => KOKOLO_VERSION,
            'function' => $function,
        );

        return json_encode( $request );
    }

    /**
    * Format a response from Kokolo webservices and handle error.
    *
    * @param $response <Array> The response from Kokolo webservice provided by KokoloCurl->send_request($request)
    * @return <Array> The right formated response
    * @throws Exception if the Kokolo webservices have returned an error
    */
    private function decode_response( Array $response ) {
        if ( $response['error'] == true ) {
            throw new Exception( (string) $response['desc'] );
        }

        if ( empty( $response['body'] ) ) {
            return [];
        }

        if ( count( $response['body'] ) === 1 ) {
            return reset( $response['body'] );
        }

        $formated_response = [];
        foreach ( $response['body'] as $value ) {
            $formated_response[] = $value;
        }

        return $formated_response;
    }

    /**
    * Retrieve informations related to the Kokolo webservices
    *
    * @return <Array> : [
    *   'nb_req_current_month' => <int> number of request made to Kokolo during the current month,
    *   'nb_req_current_year' => <int> number of request made to Kokolo during the current year,
    *   'ws_version' => <string> Kokolo webservices version,
    *   'ws_doc' => <string> Kokolo specs webservices url,
    * ]
    */
    public function ws_select() {
        $kokolo_function = 'ws_select';

        $request = $this->build_request( $kokolo_function );
        $response = $this->kokolo_curl->send_request( $request );

        return $this->decode_response( $response );
    }

    /**
    * Add/Update a design in Kokolo side
    *
    * @param $design_id <string> : unic id of the design to update/create
    * @param $print_list <Array> : [
    *   [
    *     'url_hd_design' => <string> url_encode,
    *     'position' => <string> 'F' | 'B' #(Front ou Back),
    *     'color_nb' => <int> default = 99 ; between 1 and 99,
    *   ],
    * ]
    * @throws Exception if parameters are not well formated
    */
    public function design_update( string $design_id, Array $print_list ) {
        array_walk(
            $print_list,
            function( &$print, $key ) {
                if ( 
                    !array_key_exists( 'color_nb', $print ) ||
                    empty ( $print['url_hd_design'] ) ||
                    empty ( $print['position'] ) ||
                    !in_array( $print['position'], ['F', 'B'] )
                ) {
                    throw new Exception("The content/format of the array received is not the one expected : " . (string) $print, 1);
                }

                if ( empty(  $print['color_nb'] ) ) {
                    $print['color_nb'] = 99; #default value
                }
            }
        );
        
        $formated_print_list = implode( 
            ',',
            array_map(
                function( $print ) {
                    return implode( '|', $print );
                },
                $print_list
            )
        );

        $kokolo_function = 'design_update';
        $parameters = [
            'design_name' => $design_id,
            'nb_print' => count( $print_list ),
            'print_list' => $formated_print_list,
        ];

        $request = $this->build_request( $kokolo_function, $parameters );
        $response = $this->kokolo_curl->send_request( $request );

        $this->decode_response( $response );
    }

    /**
    * Create order header in Kokolo.
    *
    * @param $order_id <string> The id of the order
    */
    public function order_insert(string $order_id) {
        $kokolo_function = 'order_insert';
        $parameters = [
            'order_num' => $order_id,
        ];

        $request = $this->build_request( $kokolo_function, $parameters );
        $response = $this->kokolo_curl->send_request( $request );

        $this->decode_response( $response );
    }

    /**
    * Update order header in Kokolo : close an order from our side.
    *
    * @param $order_id <string> The id of the order
    */
    public function order_update(string $order_id) {
        $order_state = "ready"; //other status are not permited by Kokolo webservices.

        $kokolo_function = 'order_update';
        $parameters = [
            'order_num' => $order_id,
            'order_state' => $order_state,
        ];

        $request = $this->build_request( $kokolo_function, $parameters );
        $response = $this->kokolo_curl->send_request( $request );

        $this->decode_response( $response );
    }

    /**
    * Retrieve orders headers informations
    *
    * @param $order_num <string> if empty retrieve the last 100 orders.
    * @return <array> : [
    *   [
    *     'order_state' => <string> status of the order,
    *     'dt_add' => <string> : DateTime<"yyyymmddhhmmss"> Creation date,
    *     'nb_lines' => <int> count of the lines in the order,
    *   ],
    * ]
    */
    public function order_select(string $order_num="") {
        $kokolo_function = 'order_select';
        $parameters = [
            'order_num' => $order_num,
        ];

        $request = $this->build_request( $kokolo_function, $parameters );
        $response = $this->kokolo_curl->send_request( $request );

        return $this->decode_response( $response );
    }

    /**
    * Add a product to an open order
    *
    * @param $order_num <string> The order id to append the product
    * @param $product_details <Array> : [
    *   'order_line_id' => <string> Order line id (unic by product),
    *   'order_line_ref' => <string> Client order reference,
    *   'support' => <string> Support type,
    *   'design' => <string> Id of the design,
    *   'size' => <string> Size of the product,
    *   'color' => <string> Product color,
    *   'weight' => <Int> Product weight in g,
    *   'qty' => <Int> Wanted quantity,
    *   'url_screen' => <string> Product image url,
    *   'delivery' => <string> ’std’ | ‘soc’ <- std = la Poste and soc Colissimo,
    *   'first' => <string> Firstname,
    *   'last' => <string> Lastname,
    *   'adress1' => <string> Adress 1,
    *   'adress2' => <string> Adress 2 (Optional),
    *   'postcode' => <string> Postcode,
    *   'city' => <string> City,
    *   'iso_country' => <string> Country ISO code,
    *   'mail' => <string> Customer email,
    * ]
    * @throws Exception if required parameters are not well formated
    */
    public function product_insert(string $order_num, Array $product_details) {
        $given_keys = array_keys( $product_details );
        $required_keys = ['order_line_id', 'order_line_ref', 'support', 'design', 'size', 'color', 'weight', 'qty', 'url_screen', 'delivery', 'first', 'last', 'adress1', 'postcode', 'city', 'iso_country', 'mail'];

        foreach ($required_keys as $key) {
            if ( !in_array( $key, $given_key ) ) {
                throw new Exception("The required key : " . $key . " is missing.", 1);
            }
            if ( empty( $product_details[$key] ) ) {
                throw new Exception("The required key : " . $key . " is empty.", 1);
            }
        }

        if ( !in_array( $product_details['delivery'], ["std", "soc"] ) ) {
            throw new Exception("Unexpected value for key 'delivery', expected 'std' or 'soc' but get " . $product_details['delivery'], 1);
        }

        $kokolo_function = 'product_insert';
        $parameters = $product_details;

        $request = $this->build_request( $kokolo_function, $parameters );
        $response = $this->kokolo_curl->send_request( $request );

        $this->decode_response( $response );
    }

    /**
    * Remove product(s) from a Kololo open order
    *
    * @param $order_num <string> The order id to remove the product(s)
    * @param $order_line_id <string> If set remove the matching product from the Kololo order
    * @param $order_line_ref <string> If set remove all products belonging to this customer id
    * @throws Exception if both $order_line_id and $order_line_ref are unset/set at the same time.
    */
    public function product_delete(string $order_num, string $order_line_id="", string $order_line_ref="") {
        if ( empty( $order_line_id ) && empty( $order_line_ref ) ) {
            throw new Exception("Both order_line_id and order_line_ref are empty but it's required to have one of the two set.", 1);
        }
        if ( !empty( $order_line_id ) && !empty( $order_line_ref ) ) {
            throw new Exception("Both order_line_id and order_line_ref are set but it's required to have one of the two unset.", 1);
        }

        $kokolo_function = 'product_delete';
        $parameters = [
            'order_num' => $order_num,
            'order_line_id' => $order_line_id,
            'order_line_ref' => $order_line_ref,
        ];

        $request = $this->build_request( $kokolo_function, $parameters );
        $response = $this->kokolo_curl->send_request( $request );

        $this->decode_response( $response );
    }

    /**
    * Retrieve product(s) from a Kololo order
    *
    * If $order_line_id and $order_line_ref are not set all products from order are retrieved
    *
    * @param $order_num <string> The order id to retrieve product(s)
    * @param $order_line_id <string> If set retrieve the matching product from the Kololo order
    * @param $order_line_ref <string> If set retrieve all products belonging to this customer id
    * @param $level_detail <string> See below in the code the possibilities
    * @param $state <string> Filter result by state. See below in the code the possibilities
    * @return <Array> check detail in code below
    * @throws Exception if state or level_detail value are unknown.
    */
    public function product_select(string $order_num, string $order_line_id="", string $order_line_ref="", string $level_detail, string $state) {
        $defined_level_details = [
            'full', #retrieve all informations of all products
            /*
            [
               'order_line_id' => <string> Id de ligne de commande (unique par article),
               'order_line_ref' => <string> Référence commande du client final (telle qu’elle lui a été communiqué : unique par destinataire),
               'support' => <string> Support type,
               'design' => <string> id of the design,
               'size' => <string> size of the product,
               'color' => <string> Product color,
               'weight' => <Int> Product weight in g,
               'price' => <Dec> TTC Price/unit,
               'url_screen' => <string> Product image url,
               'urls_hd' => <Sring> Design files urls,
               'delivery' => <string> ’std’ | ‘soc’ <- std = la Poste and soc Colissimo,
               'company' => <string>
               'first' => <string> Firstname,
               'last' => <string> Lastname,
               'adress1' => <string> Adress 1,
               'adress2' => <string> Adress 2 (Optional),
               'postcode' => <string> Postcode,
               'city' => <string> City,
               'iso_country' => <string> Country ISO code,
               'mail' =>  <string> Customer email,
            ]
            */
            'state', #retrieve the state of each product and delivery data
            /*
            [
               'order_line_id' => <string> Id de ligne de commande (unique par article),
               'order_line_ref' => <string> Référence commande du client final (telle qu’elle lui a été communiqué : unique par destinataire),
               'state' => <Sring> Status (see below),
               'delivery' => <string> ’std’ | ‘soc’ <- std = la Poste and soc Colissimo,
               'dt_prod' => <string> YYYYMMDDHHMMSS date when product production started
               'dt_delivery' => <string> YYYYMMDDHHMMSS date when delivery started,
               'colissimo_num' => <string> colissimo tracking number,
            ]
            */
            'product', #retrieve references informations about products (size, color, support...)
            /*
            [
               'support' => <string> Support type,
               'design' => <string> id of the design,
               'size' => <string> size of the product,
               'color' => <string> Product color,
               'weight' => <Int> Product weight in g,
               'price' => <Dec> TTC Price/unit,
               'qty' => <Int> wanted quantity,
            ]
            */
        ];
        $defined_states = [
            'all', #all products
            'waiting', #untreated
            'production', #production in progress
            'delivery', #delivery in progress
        ];

        if (!in_array($level_detail, $defined_level_details)) {
            throw new Exception("The given detail level : '" . $level_detail . "' is not defined. Defined detail level : " . implode(', ', $defined_level_details) . '.', 1);
        }
        if (!in_array($state, $defined_states)) {
            throw new Exception("The given state : '" . $state . "' is not defined. Defined states : " . implode(', ', $defined_states) . '.', 1);
        }

        $kokolo_function = 'product_select';
        $parameters = [
            'order_num' => $order_num,
            'order_line_id' => $order_line_id,
            'order_line_ref' => $order_line_ref,
            'level_detail' => $level_detail,
            'state' => $state,
        ];

        $request = $this->build_request( $kokolo_function, $parameters );
        $response = $this->kokolo_curl->send_request( $request );

        $this->decode_response( $response );
    }

}
