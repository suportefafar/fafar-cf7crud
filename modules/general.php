<?php

add_filter( 'wpcf7_form_hidden_fields', 'fafar_cf7crud_add_hidden_url_field' );

add_filter( 'wpcf7_form_hidden_fields', 'fafar_cf7crud_add_hidden_ip_field' );

function fafar_cf7crud_add_hidden_url_field( $fields ) {
    
    $fields['far_db_column_submission_url'] = fafar_cf7crud_get_url();
  
    return $fields;
  
  }
  

function fafar_cf7crud_add_hidden_ip_field( $fields ) {
    
  $fields['far_db_column_remote_ip'] = fafar_cf7crud_get_ip_address();

  return $fields;

}

function fafar_cf7crud_get_url() {

    $http_host = sanitize_text_field( $_SERVER['HTTP_HOST'] );
    $request_uri = sanitize_text_field( $_SERVER['REQUEST_URI'] );

    $actual_link = ( empty( $_SERVER['HTTPS'] ) ? 'http' : 'https' ) . '://' . $http_host . $request_uri;

    return $actual_link;

}

function fafar_cf7crud_get_ip_address(){
    foreach (array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR') as $key){
        if (array_key_exists($key, $_SERVER) === true){
            foreach (explode(',', $_SERVER[$key]) as $ip){
                $ip = trim($ip); // just to be safe

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false){
                    return $ip;
                }
            }
        }
    }
}