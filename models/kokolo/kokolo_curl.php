<?php
/**
* A helper class to communicate with Kokolo web services
*
* @author Pierre Labadille
*/
class KokoloCurl {
    
    /**
    * Call a function to send a curl call to the Kokolo webservices. Handle error and recall n time
    * in case of curl error before throwing an Exception.
    *
    * @param <string> containing json formated informations to send
    * @return <array> if array['error'] is true then array['desc'] contain the detail of the Kokolo error. Else array['desc'] contain the webservices response.
    * @throws <Exception> if CURL return error MAX_ATTEMPT time.
    */
    public function send_request( string $request ) {
        $max_attempt = 3;
        for ( $i=0; $i < $max_attempt; $i++ ) {
            try {
                $response = $this->curl_call( $request );
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
    private function curl_call( $request ) {
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

}