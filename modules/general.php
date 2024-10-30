<?php

add_filter( 'wpcf7_form_hidden_fields', 'fafar_cf7crud_add_hidden_url_field' );

add_filter( 'wpcf7_form_hidden_fields', 'fafar_cf7crud_add_hidden_ip_field' );

add_filter( 'wpcf7_form_tag', 'fafar_cf7crud_populate_input_value_dynamically' );

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

/*
 * Called on 'wpcf7_form_tag' filter hook.
 * This set a value to the inputs 'value' prop.
 * 
 * Ex: <input value="VALUE_TO_BE_SET" />
 * 
 * Works on 'checkbox', 'radio' and 'select' input tags, too.
 *
 * @since 1.0.0
 * @param  CF7 Tag Object $tag  CF7 tag object from contact-form-7/includes/form-tags-manager.php
 * @return CF7 Tag Object $tag  CF7 tag object from contact-form-7/includes/form-tags-manager.php
*/
function fafar_cf7crud_populate_input_value_dynamically( $tag ) { 

    global $wpdb;
    /*
     * If other database should be used.
     */
    $fafar_cf7crud_db = apply_filters( 'fafar_cf7crud_set_database', $wpdb );
    $table_name       = $fafar_cf7crud_db->prefix . 'fafar_cf7crud_submissions';
    
    if ( is_admin() ) return $tag;

    $option_display_value = fafar_cf7crud_get_tag_option_value( $tag, 'far_crud_display' );

    if ( $option_display_value === false ) return $tag;

    /**
     * Column Filter Part
    */
    $option_column_filter_value = fafar_cf7crud_get_tag_option_value( $tag, 'far_crud_column_filter' ); 
    $query_filter_column_part = '';
    if ( $option_column_filter_value !== false ) {

        // Expected: far_crud_column_filter:COLUMN_1|VALUE_1:COLUMN_2|VALUE_2
        $filter_pairs     = explode( ":", $option_column_filter_value );
        $filter_pairs     = array_slice( $filter_pairs, 1 );
        $where_column_arr = array();
        foreach ( $filter_pairs as $filter ) {
    
            if ( count( explode( "|", $filter ) ) != 2 ) continue;

            $key   = explode( "|", $filter )[0];
            $value = explode( "|", $filter )[1];

            if( $value === 'this' ) {

                $submission_decoded = fafar_cf7crud_get_current_submission( true );

                if( $submission_decoded === false || ! isset( $submission_decoded[$key] ) ) continue;

                $value = $submission_decoded[$key];

            }

            $comparison = '`' . $key . '` = "' . $value . '"';

            array_push( $where_column_arr, $comparison );
    
        }

        if ( count( $where_column_arr ) > 0 ) 
            $query_filter_column_part = implode( " AND ", $where_column_arr );

    }

    /**
     * JSON Filter Part
    */
    $option_json_filter_value = fafar_cf7crud_get_tag_option_value( $tag, 'far_crud_json_filter' );  
    $query_filter_json_part = '';
    if ( $option_json_filter_value !== false ) {

        // Expected: far_crud_json_filter:PROP_JSON_1|VALUE_1:PROP_JSON_2|VALUE_2
        $filter_pairs   = explode( ":", $option_json_filter_value );
        $filter_pairs   = array_slice( $filter_pairs, 1 );
        $where_json_arr = array();
        foreach ( $filter_pairs as $filter ) {
    
            if ( count( explode( "|", $filter ) ) != 2 ) continue;

            $key   = explode( "|", $filter )[0];
            $value = explode( "|", $filter )[1];

            if( $value === 'this' ) {

                $submission_decoded = fafar_cf7crud_get_current_submission( true );

                if( $submission_decoded === false || ! isset( $submission_decoded[$key] ) ) continue;
                
                $value = $submission_decoded[$key];

            }

            $where_json_arr[$key] = $value;
    
        }

        if ( count( $where_column_arr ) > 0 ) 
            $query_filter_json_part = 'JSON_CONTAINS( data, \'' . json_encode( $where_json_arr ) . '\')';

    }

    $query = 'SELECT * FROM `' . $table_name . '`';

    if ( $query_filter_column_part !== "" &&
            $query_filter_json_part !== "" ) {
            
        $query .= ' WHERE ' . $query_filter_column_part . ' AND ' . $query_filter_json_part;

    } else if ( $query_filter_column_part !== "" ) {

        $query .= ' WHERE ' . $query_filter_column_part;

    } else if ( $query_filter_json_part !== "" ) {

        $query .= ' WHERE ' . $query_filter_json_part;

    }

    $submissions = $fafar_cf7crud_db->get_results( $query );

    if ( empty( $submissions ) ) return $tag;

    // Expected: far_crud_display:LABEL_PROP|VALUE_PROP
    $option_display_value = explode( ":", $option_display_value )[1];
    $label_prop = explode( "|", $option_display_value )[0];
    $value_prop = explode( "|", $option_display_value )[1];

    $select_one_text = '';
    array_push( $tag['values'], $select_one_text );
    array_push( $tag['raw_values'], $select_one_text );
    array_push( $tag['labels'], $select_one_text );
    array_push( $tag['options'], 'first_as_label' );

    $pipes_text = array();
    foreach ( $submissions as $submission ) {

        $submission_decoded = fafar_cf7crud_join_submission_props( $submission );
        
        if( ! isset( $submission_decoded[$label_prop] ) || 
            ! isset( $submission_decoded[$value_prop] ) ) continue;

        $raw_value = $submission_decoded[$label_prop] . "|" . $submission_decoded[$value_prop];
        array_push( $tag['raw_values'], $raw_value );

        $value = $submission_decoded[$label_prop];
        array_push( $tag['values'], $value );

        array_push( $tag['labels'], $value );

        array_push( $pipes_text, $raw_value );

    }

    $tag['pipes'] = new WPCF7_Pipes( $pipes_text );

    // print_r( "<br><br>" );
    // print_r( $tag );

    return $tag;

}

/*
 * Function gets a certain value(or key) of option of a tag.
 * 
 * $tag['options'] = array( [0] => 'default: 1', ... )
 * 
 * @since 1.0.0
 * @param CF7 Tag Object $tag     CF7 tag object.
 * @param string $option     The name of the option.
 * @param bool $return_option_index     If 'true', returns the index.
 * @return string | number | false
*/
function fafar_cf7crud_get_tag_option_value( $tag, $option, $return_option_index = false ) {

    foreach ( $tag['options'] as $index => $value ) {

        /**
         * We can assume that every option has a ':'
         * to separate the value, if has any.
         * At least the ones we are threating here has.
         * 
         * Ex: 'default: 1'
         * 
         * So....
        */
        $key_option = explode( ":", $option )[0];

        if ( str_contains( $value, $key_option ) ) {
            
            if ( $return_option_index )
                return $index;
            else
                return $value;
            
        }

    } 

    return false;

}

/*
 * This function gets the current submission on db.
 * 
 * if $decode equals to true, then uses 'fafar_cf7crud_join_submission_props' 
 * function.
 *
 * @since 1.0.0
 * @return mixed|array $submission Return from $wpdb->get_results
*/
function fafar_cf7crud_get_current_submission( $decode = false ) {

    global $wpdb;
    /*
     * If other database should be used.
     */
    $fafar_cf7crud_db = apply_filters( 'fafar_cf7crud_set_database', $wpdb );
    $table_name       = $fafar_cf7crud_db->prefix . 'fafar_cf7crud_submissions';

    if( ! isset( $_GET['id'] ) ) return false;
    
    $query = "SELECT * FROM `" . 
                $table_name . 
                "` WHERE `id` = '" . 
                $_GET['id'] . 
                "'";  

    $submission = $fafar_cf7crud_db->get_results( $query );

    return ( $decode ) ? 
        fafar_cf7crud_join_submission_props( $submission[0] ) : 
        $submission_decoded[0];
}

/*
 * This function join all submissions properties(columns and json) 
 * from $wpdb->get_results in one php array.
 *
 * @since 1.0.0
 * @param mixed $submission Return from $wpdb->get_results
 * @return array $submission_decoded  Submission decoded
*/
function fafar_cf7crud_join_submission_props( $submission ) {
    
    if ( ! $submission->data ) {

        return $submission;

    }

    $submission_joined = json_decode( $submission->data, true );

    foreach ( $submission as $key => $value ) {
    
        if ( $key === 'data' )
            continue;

        $submission_joined[$key] = $value ?? '--';
        
    }

    return $submission_joined;
}