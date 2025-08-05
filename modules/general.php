<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

add_filter( 'wpcf7_form_hidden_fields', 'fafar_cf7crud_add_submission_id_hidden', 9 );

add_filter( 'wpcf7_form_hidden_fields', 'fafar_cf7crud_add_hidden_url_field' );

add_filter( 'wpcf7_form_hidden_fields', 'fafar_cf7crud_add_hidden_ip_field' );

add_filter( 'wpcf7_form_tag', 'fafar_cf7crud_populate_input_value_dynamically' );

add_filter( 'wpcf7_form_tag', 'fafar_cf7crud_populate_input_by_shortcut' ); 


/**
 * Called by 'wpcf7_form_elements' filter hook.
 * It just adds a hidden input with the id of the submission, 
 * on the end of the HTML form.
 *
 * @since 1.0.0
 * @param  string $content  HTML form string
 * @return string $content  HTML form string
 */
function fafar_cf7crud_add_submission_id_hidden( $content ) {

    if ( is_admin() ) 
        return $content;

    if ( ! isset( $_GET['id'] ) )
        return $content;

    $fields['fafar_cf7crud_submission_id'] = fafar_cf7crud_sanitize( $_GET['id'] ); 
  
    return $fields;

}

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

/**
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

    // Gambiarra: o filter usado nessa função roda também na submissão, mas sem $_GET['id']
    // o que acaba dando um erro bizarro.
    if ( empty( $_GET['id'] ) ) return $tag;

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

    
    // JSON Filter Part
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

    return $tag;

}

function fafar_cf7crud_populate_input_by_shortcut( $tag ) {

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

    // Checks if we are NOT dealing with selectable field
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

/** 
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

/**
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
        "` WHERE `id` = '" . fafar_cf7crud_sanitize( $id ) . "'";  

    $submission = $fafar_cf7crud_db->get_results( $query );

    return ( $decode ) ? 
        fafar_cf7crud_join_submission_props( $submission[0] ) : 
        $submission[0];

}

/**
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
    // Caso receba um objeto submission com o valor do owner substituito pelos dados do owner
    $owner                              = (string) ( isset( $submission['owner']['ID'] ) ? $submission['owner']['ID'] : $submission['owner'] );
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

/**
 * Sanitizes input values based on specified name prefix type
 * 
 * @since 1.0.0
 * @param mixed  $value Value to sanitize
 * @param string $type  Sanitization type identifier
 * @return mixed Sanitized value
 */
function fafar_cf7crud_sanitize( $value, $type = null ) {
    if ( empty( $value ) ) {
        return '';
    }

    $sanitization_map = [
        'far_san_lb_'  => 'sanitize_textarea_field',
        'far_san_em_'  => 'sanitize_email',
        'far_san_fi_'  => 'sanitize_file_name',
        'far_san_key_' => 'sanitize_key',
    ];

    if( $type ) {
        foreach ( $sanitization_map as $prefix => $function ) {
            if ( str_contains( $type, $prefix ) ) {
                return is_array( $value ) 
                    ? array_map( $function, $value ) 
                    : call_user_func( $function, $value );
            }
        }
    }

    return is_array( $value )
        ? array_map( 'sanitize_text_field', $value )
        : sanitize_text_field( $value );
}

/**
 * Aborts the submission with error message
 * 
 * @since 1.1.0
 * @param WPCF7_ContactForm $form    Form object.
 * @param string            $message Error message.
 * @return bool Always returns false.
 */
function fafar_cf7crud_abort_submission( WPCF7_ContactForm $form, string $message ): bool {
    add_filter( 'wpcf7_ajax_json_echo', function ( $response ) use ( $message ) {
        return array_merge( $response, [
            'status' => 'mail_sent_ng',
            'message' => esc_html( $message )
        ] );
    } );

    $form->skip_mail = true;

    return false;
}

/**
 * Prepares form data for database storage.
 *
 * @since 1.1.0
 * @param array $posted_data   Raw form data from CF7.
 * @param array $form_tags     CF7 form tags for validation.
 * @param array $file_mappings File mappings for uploaded files.
 * @return array Prepared form data.
 */
function fafar_cf7crud_prepare_form_data( array $posted_data, array $form_tags, array $file_mappings ): array {
    
    $prepared_data = [];

    // Get allowed tags and not allowed fields
    $allowed_tags = array();
    $tags_names   = array();

    foreach( $form_tags as $tag ){
        if( ! empty($tag->name) ) $tags_names[] = $tag->name; // $tag->name contém o valor da prop name de cada input
    }
    
    $allowed_tags       = apply_filters( 'fafar_cf7crud_allowed_tags', $tags_names );
    $not_allowed_fields = apply_filters( 'fafar_cf7crud_not_allowed_fields', array( 'g-recaptcha-response' ) );

    foreach ($form_tags as $tag) {
        $name = $tag->name;
        // $type = $tag->type; // hidden, text*, text

        // Skip if the field is not in the posted data, not allowed, or explicitly excluded
        if (
            ! isset( $posted_data[$name] ) || 
            ! in_array( $name, $allowed_tags ) || 
            in_array( $name, $not_allowed_fields ) || 
            str_contains( $name, 'far_ignore_field_' ) || 
            str_contains( $name, 'far_db_column_' ) || 
            $name === 'fafar_cf7crud_submission_id'
        ) {
            continue;
        }

        // Sanitize the value based on the field type
        $value = fafar_cf7crud_sanitize( $posted_data[$name], $name );

        // Add file mappings if the field is a file upload
        if ( isset( $file_mappings[$name] ) ) {
            $value       = $file_mappings[$name];
            $file_prefix = 'fafar_cf7crud_file_';
            $name        = sanitize_key( $file_prefix . $name );
        }

        $prepared_data[sanitize_key( $name )] = $value;
    }

    return $prepared_data;
}

/**
 * Builds a database record for insertion by mapping form fields to database columns.
 *
 * @since 1.1.0
 * @param array  $record        Array of data columns to insert into the database.
 * @param array  $posted_data   Raw form data from CF7.
 * @param wpdb   $db            WordPress database object.
 * @param string $table_name    Database table name.
 * @return array Database record with mapped columns.
 */
function fafar_cf7crud_insert_common_columns( array $record, array $posted_data, wpdb $db, string $table_name ): array {
    // Get the list of columns in the table
    $columns = $db->get_col("DESC $table_name", 0);

    // Check if columns were retrieved successfully
    if ( empty( $columns ) ) {
        return $record; // Return the original record if no columns are found
    }

    // Iterate through the columns and map form data to database columns
    foreach ( $columns as $column_name ) {
        // Check if the form field exists for the column
        $form_field_key = 'far_db_column_' . $column_name;
        if ( isset( $posted_data[$form_field_key] ) ) {
            // Sanitize and add the form data to the record
            $record[$column_name] = fafar_cf7crud_sanitize( $posted_data[$form_field_key] );
        }
    }

    return $record;
}

/**
 * Sets up and validates the upload directory for CF7 CRUD submissions.
 * 
 * @since 1.1.0
 * @return array Upload directory information.
 * @throws RuntimeException If directory creation fails or directory is not writable.
 */
function fafar_cf7crud_setup_upload_directory(): array {
    // Get the default WordPress upload directory
    $upload_dir = wp_upload_dir();

    // Define the custom directory name
    $custom_dir = 'fafar-cf7crud-uploads';

    // Build the full path to the custom directory
    $custom_path = trailingslashit( $upload_dir['basedir'] ) . $custom_dir;

    // Apply filters to allow customization of the directory path
    $custom_path = apply_filters( 'fafar_cf7crud_upload_directory_path', $custom_path );

    // Create the directory if it doesn't exist
    if ( ! file_exists( $custom_path ) ) {
        if ( ! wp_mkdir_p( $custom_path ) ) {
            throw new RuntimeException(
                __( 'Failed to create upload directory.', 'fafar-cf7crud' )
            );
        }
    }

    // Ensure the directory is writable
    if ( ! is_writable( $custom_path ) ) {
        throw new RuntimeException(
            __( 'Upload directory is not writable.', 'fafar-cf7crud' )
        );
    }

    return [
        'basedir' => $custom_path,
        'baseurl' => trailingslashit( $upload_dir['baseurl'] ) . $custom_dir,
    ];
}

/**
 * Processes uploaded files and moves them to storage.
 *
 * @since 1.1.0
 * @param array  $files         Uploaded files from CF7.
 * @param string $submission_id Unique submission ID.
 * @param string $upload_path   Path to the upload directory.
 * @return array File mappings.
 * @throws RuntimeException If a file is missing or cannot be copied.
 */
function fafar_cf7crud_process_uploaded_files( array $files, string $submission_id, string $upload_path ): array {

    $mappings = [];
    
    $abort_on_missing_file = apply_filters( 'fafar_cf7crud_abort_on_missing_file', true );

    foreach ( $files as $field => $paths ) {
        $paths = is_array( $paths ) ? $paths : [$paths];
        
        foreach ( $paths as $source ) {
            if ( ! file_exists( $source ) ) {
                error_log( 'Missing file: ' . $source );
                if ( $abort_on_missing_file ) {
                    throw new RuntimeException(
                        __('A required file is missing.', 'fafar-cf7crud')
                    );
                } else {
                    continue;
                }
            }

            $filename = sprintf('%s-%s-%s',
                $submission_id,
                sanitize_key( $field ),
                sanitize_file_name( basename( $source ) )
            );

            $destination = trailingslashit( $upload_path ) . $filename;
            
            if ( ! copy( $source, $destination ) ) {
                throw new RuntimeException(
                    __('File upload failed', 'fafar-cf7crud')
                );
            }

            $mappings[$field][] = $filename;
        }
    }
    
    return $mappings;
}
