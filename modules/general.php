<?php

add_filter( 'wpcf7_form_hidden_fields', 'fafar_cf7crud_add_hidden_url_field' );

add_filter( 'wpcf7_form_hidden_fields', 'fafar_cf7crud_add_hidden_ip_field' );

add_filter( 'wpcf7_form_tag', 'fafar_cf7crud_populate_input_value_dynamically' );

add_filter( 'wpcf7_form_tag', 'fafar_cf7crud_populate_input_by_shortcut' );

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
    $query_filter_column_part   = '';
    $submission_decoded         = fafar_cf7crud_get_current_submission();
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

function fafar_cf7crud_populate_input_by_shortcut( $tag ) {

    //     print_r( "<br><br>" );
    // print_r( $tag );

    // Array
    //     (
    //         [type] => checkbox
    //         [basetype] => checkbox
    //         [raw_name] => checkbox-317
    //         [name] => checkbox-317
    //         [options] => Array
    //             (
    //                 [0] => use_label_element
    //                 [1] => far-crud-shortcode:intranet_fafar_get_users_as_select_options
    //             )

    //         [raw_values] => Array
    //             (
    //             )

    //         [values] => Array
    //             (
    //             )

    //         [pipes] => WPCF7_Pipes Object
    //             (
    //                 [pipes:WPCF7_Pipes:private] => Array
    //                     (
    //                     )

    //             )

    //         [labels] => Array
    //             (
    //             )

    //         [attr] => 
    //         [content] => 
    //     )

    if ( is_admin() ) return $tag;

    if( empty( $tag['options'] ) ) return $tag;

    $contains_shortcode = array_filter( $tag['options'], function ( $value ) {
        return strpos( $value, 'far_crud_shortcode:' ) !== false;
    });
    
    if ( sizeof( $contains_shortcode ) != 1 ) return $tag;

    $shortcode_parts = explode( ":", reset( $contains_shortcode ) );

    if ( sizeof( $shortcode_parts ) != 2 ) return $tag;

    $shortcode_name = $shortcode_parts[1];

    if ( ! $shortcode_name ) return $tag;

    $json = do_shortcode( '[' . $shortcode_name . ']' );

    if ( ! is_string( $json ) ) return $tag;

    /*
     * Checks if we are NOT dealing with selectable field
     */
    $selectable_types = array( 'checkbox', 'select', 'radio' );
    if ( ! in_array( $tag['basetype'], $selectable_types ) ) {

        $str_value = $json;

        $tag['values'] = (array) $str_value;

        return $tag;

    }

    $arr = json_decode( $json, true );

    $pipes_text = array();
    foreach ( $arr as $k => $v ) {
        
        $raw_value = $v . "|" . $k;
        array_push( $tag['raw_values'], $raw_value );

        $value = $v;
        array_push( $tag['values'], $value );

        array_push( $tag['labels'], $value );

        array_push( $pipes_text, $raw_value );

    }

    $tag['pipes'] = new WPCF7_Pipes( $pipes_text );

    return $tag;

}

function fafar_cf7crud_populate_input_not_selectable( $tag ) { 

    $tag['values'] = (array) $input_value;

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
function fafar_cf7crud_get_current_submission() {

    if( ! isset( $_GET['id'] ) ) return false;

    return fafar_cf7crud_get_submission_by_id( $_GET['id'] );
}

function fafar_cf7crud_get_submission_by_id( $id, $decode = true ) {

    global $wpdb;
    /*
     * If other database should be used.
     */
    $fafar_cf7crud_db = apply_filters( 'fafar_cf7crud_set_database', $wpdb );
    $table_name       = $fafar_cf7crud_db->prefix . 'fafar_cf7crud_submissions';

    $query = "SELECT * FROM `" . $table_name . 
        "` WHERE `id` = '" . fafar_cf7crud_san( $id ) . "'";  

    $submission = $fafar_cf7crud_db->get_results( $query );

    return ( $decode ) ? 
        fafar_cf7crud_join_submission_props( $submission[0] ) : 
        $submission[0];

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


function fafar_cf7crud_check_read_permission( $submission, $user_id = null ) {
    
    $READ_DIGIT_VALUES = array( 4, 5, 6, 7 );

    return fafar_cf7crud_check_permissions( $submission, $READ_DIGIT_VALUES, $user_id );

}

function fafar_cf7crud_check_write_permission( $submission, $user_id = null ) {

    $WRITE_DIGIT_VALUES = array( 1, 3, 5, 7 );

    return fafar_cf7crud_check_permissions( $submission, $WRITE_DIGIT_VALUES, $user_id );

}

function fafar_cf7crud_check_exec_permission( $submission, $user_id = null ) {

    $EXEC_DIGIT_VALUES = array( 1, 3, 5, 7 );

    return fafar_cf7crud_check_permissions( $submission, $EXEC_DIGIT_VALUES, $user_id );

}

function fafar_cf7crud_check_permissions( $submission, $permission_digit_values, $user_id = null ) {

    $owner                              = (string) ( $submission['owner'] ?? 0 );
    $group_owner                        = (string) ( $submission['group_owner'] ?? 0 );
    $permissions                        = (string) ( $submission['permissions'] ?? '777' );

    $current_user_id                    = (string) ( $user_id ?? get_current_user_id() );
    $user_meta                          = get_userdata( $current_user_id );
    $user_roles                         = $user_meta->roles; // array( [0] => 'techs', ... )

    $OWNER_PERMISSION_DIGIT_INDEX       = 0;
    $OWNER_GROUP_PERMISSION_DIGIT_INDEX = 1;
    $OTHERS_PERMISSION_DIGIT_INDEX      = 2;

    /**
     * If the current user is the 'administrator', 
     * it gets instant permission.
    */
    if( in_array( 'administrator', $user_roles ) ) return true;

    // Permissions not set
    if ( ! $permissions ) return true;

    // Do not has restriction
    if ( $permissions === '777' ) return true;
    
    // Current user is the owner
    if ( $owner === $current_user_id ) {

        $permission_value = (int) str_split( $permissions )[$OWNER_PERMISSION_DIGIT_INDEX];
        return in_array( $permission_value, $permission_digit_values, true );

    }

    /**
     * Group permissions
     * If user is on $group_owner.
     * $user_roles. Array. array( [0] => 'techs', ... )
     */
    if ( in_array( strtolower( $group_owner ), $user_roles ) )
    {

        $permission_value = (int) str_split( $permissions )[$OWNER_GROUP_PERMISSION_DIGIT_INDEX];
        return in_array( $permission_value, $permission_digit_values, true );
    
    }

    // Others permissions
    $permission_value = (int) str_split( $permissions )[$OTHERS_PERMISSION_DIGIT_INDEX];
    return in_array( $permission_value, $permission_digit_values, true );

}

function fafar_cf7crud_san( $v ) {

    $v = ( is_array( $v ) ? $v[0] : $v );

    return sanitize_text_field( wp_unslash( $v ) );

}