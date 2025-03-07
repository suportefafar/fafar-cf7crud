<?php

/*
 * Function to sanitize value depending of the 
 * value type spected.
 *
 * @since 1.0.0
 * @param string $value   Value to be sanitize.
 * @param string $type    Type of sanitization.
 * @return $tring         Value sanitized.
*/
function fafar_cf7crud_sanitize( $value, $type = null ) {

    if( ! $value ) return "";

    if( str_contains( $type, 'far_san_lb_' ) )
        return sanitize_textarea_field( $value );

    else if( str_contains( $type, 'far_san_em_' ) )
        return sanitize_email( $value );

    else if( str_contains( $type, 'far_san_fi_' ) )
        return sanitize_file_name( $value );

    else if( str_contains( $type, 'far_san_key_' ) )
        return sanitize_key( $value );

    return sanitize_text_field( $value );
}

/*
 * Function to create a submission
 * Runs when at 'wpcf7_before_send_mail' action hook
 *
 * @since 1.0.0
 * @param WPCF7_ContactForm Object $contact_form     Input form data.
 * @return null
*/
function fafar_cf7crud_before_send_mail_create( $contact_form, $submission ) {

    global $wpdb;

    // TODO: update return msg method
    // Submission not found
    if ( ! $submission ) {
    
        $contact_form->skip_mail = true; // Skip sending the mail
        $submission->set_status( 'aborted' );
        $submission->set_response( $contact_form->filter_message(
            __( 'Submission not found!', 'fafar-cf7crud' ) )
        );
        return false;
    
    }

    /*
     * If other database should be used.
     */
    $fafar_cf7crud_db = apply_filters( 'fafar_cf7crud_set_database', $wpdb );
    $table_name       = $fafar_cf7crud_db->prefix . 'fafar_cf7crud_submissions';
    
    /*
     * Set the upload folder
     */
    $upload_dir                    = wp_upload_dir();
    $fafar_cf7crud_upload_dir_path = apply_filters( 
        'fafar_cf7crud_set_upload_dir_path', 
        $upload_dir[ 'basedir' ] . '/fafar-cf7crud-uploads/'
     );

    /*
     * Generating unique hash for submission 'id'2
     */
    $bytes       = random_bytes(5);
    $unique_hash = time().bin2hex($bytes); 

    /*
    *  CF7 uploads the files to it's own folder.
    *  Here we copy these files from CF7 upload folder to our upload folder.
    *    Array
    *     (
    *         [bill-doc] => Array
    *         (
    *               [0] => /var/www/html/wp-content/uploads/wpcf7_uploads/2084569911/Captura-de-tela-de-2024-08-30-19-31-14.png
    *         )
    *    
    *     )
    */
    $files_to_database = array();
    $cf7_uploaded_files = $submission->uploaded_files();
    foreach ($cf7_uploaded_files as $file_key => $file) {

        $file = is_array( $file ) ? reset( $file ) : $file;
        if( ! empty($file) ) {

            $filename = $unique_hash . '-' . $file_key . '-' . basename( $file );

            copy( 
                $file, // From
                $fafar_cf7crud_upload_dir_path . sanitize_file_name( $filename ) // To
            );

            $files_to_database[$file_key] = sanitize_file_name( $filename );

        }

    }
 
    /**
     * Tags filter
     * $contact_form->scan_form_tags( $cond ) : Array of WPCF7_FormTag Object
     *   $cond: array( 
     *            'type' => array( 'text*' ... )       Ex.: text, text*, select, select*, etc.
     *            'basetype' => array( 'text' ... )    Ex.: text, select, etc.
     *            'name' => array( 'car-model' ... )   Name input prop.
     *            'feature' => array( 'required' ... ) Ex.: 'required', 'placeholder', 'readonly', 'accepts_files', 'multiselect', etc. 
     *   )
     * 
     * 
     * $allowed_tags : array( 
     *   [0] => WPCF7_FormTag Object
     *    (
     *        [type] => text*
     *        [basetype] => text
     *        [raw_name] => your-name
     *        [name] => your-name
     *        [options] => Array
     *            (
     *                    [0] => autocomplete:name
     *            )
     *
     *        [raw_values] => Array
     *            (
     *
     *            )
     *
     *        [values] => Array
     *            (
     *
     *            )
     *
     *        [pipes] => WPCF7_Pipes Object
     *            (
     *                    [pipes:WPCF7_Pipes:private] => Array
     *                    (
     *
     *                    )
     *
     *            )
     *
     *        [labels] => Array
     *            (
     *
     *            )
     *
     *        [attr] => 
     *        [content] => 
     *    )
    */
    $allowed_tags = array();
    $tags_names   = array();
    $tags  = $contact_form->scan_form_tags();

    foreach( $tags as $tag ){
        if( ! empty($tag->name) ) $tags_names[] = $tag->name;
    }
    
    $allowed_tags       = apply_filters( 'fafar_cf7crud_allowed_tags', $tags_names );
    
    $not_allowed_fields = apply_filters( 'fafar_cf7crud_not_allowed_fields', array( 'g-recaptcha-response' ) );

    /**
     * $submission->get_posted_data() : 
     * Array (
     *       [your-name] => asdfasdfasdf
     *       [your-subject] => asdfasdfasdf
     *       [your-message] => asdfasdfasdf
     *       [fafar-cf7crud-input-file-hidden-bill-doc] => Captura de tela de 2024-08-28 16-39-26.png
     *       [bill-doc] => adf1eaf01842149c785a109ee87430eb
     *   )
     */
    $posted_data = $submission->get_posted_data();
    $form_data = array();
    foreach ($posted_data as $key => $value) {
        
        /**
         * Ignores 'far_db_column_COLUMN_NAME' to use it as column on DB
        */
        if( str_contains( $key, 'far_db_column_' ) ) continue;

        /**
         * Ignores tags that has 'far_ignore_field_' prefix
        */
        if( str_contains( $key, 'far_ignore_tag_' ) ) continue;
        
        /**
         * Ignores field whitch $key do not appears on 
         * original form WPCF7_FormTag Object.
         */
        if ( ! in_array( $key, $allowed_tags ) ) continue;

        /**
         * Ignores field whitch $key do appears on 
         * $not_allowed_fields array.
         */
        if ( in_array( $key, $not_allowed_fields ) ) continue;

        /**
         * FILES HANDLER
         * If $files_to_database[$key] is set, 
         * stores on $form_data.
         */
        if ( isset( $files_to_database[$key] ) ) {

            $file_prefix = 'fafar_cf7crud_file_';

            $form_data[sanitize_key( $file_prefix . $key )] = $files_to_database[$key];

            continue;

        }

        /**
         * Custom sanitize by it's custom prefix,
         * if set.
         */
        $tmpValue = "";
        if ( ! is_array($value) ) {

            $tmpValue = fafar_cf7crud_sanitize( $value, $key );
    
        } else {

            $tmpValue = array();
            foreach( $value as $index => $item ) {
                array_push($tmpValue, fafar_cf7crud_sanitize( $item, $key ));
            }           

        }

        /**
         * Sanitize $key data then add at $form_data.
         */

        $form_data[sanitize_key( $key )] = $tmpValue;

    }

    $form_post_id      = $contact_form->id();
    $form_data_as_json = json_encode( $form_data );

    $new_data = array(
        'id'          => $unique_hash,
        'data'        => $form_data_as_json,
        'form_id'     => $form_post_id
    );

    $columns = $fafar_cf7crud_db->get_results("SHOW COLUMNS FROM $table_name");

    foreach ($columns as $column) {

        $column_name = $column->Field;

        if ( isset( $posted_data['far_db_column_' . $column_name] ) &&
                $posted_data['far_db_column_' . $column_name] ) {
            $new_data[$column_name] = 
                fafar_cf7crud_sanitize( 
                    $posted_data['far_db_column_' . $column_name] 
                );
        }

    }

    /*
     *  This filter hook gives the oportunity to make a 
     *  another check/validation.
     */
    
     $new_data = apply_filters( 'fafar_cf7crud_before_create', $new_data, $contact_form );
     error_log("FAFAR CF7 ----------------------------------------->");
     error_log(print_r($new_data, true));

     if ( ! $new_data ) {
 
         add_filter('wpcf7_ajax_json_echo', function ($response, $result) {
             $response['status'] = 'mail_sent_ng';
             $response['message'] = 'Unknow error. Some function return null from "fafar_cf7crud_before_create" hook.';
             return $response;
         }, 10, 2);
 
         return false;
     }
 
     if ( isset( $new_data['error_msg'] ) ) {

        $error_msg = $new_data['error_msg'];
 
         add_filter('wpcf7_ajax_json_echo', function ($response, $result) use ($error_msg) {
             $response['status'] = 'mail_sent_ng';
             $response['message'] = $error_msg;
             return $response;
         }, 10, 2);
 
         return false;
     }
 
    if ( isset( $new_data['far_prevent_submit'] ) && 
         $new_data['far_prevent_submit'] == true )
        return true;

    if ( isset( $posted_data['far_prevent_submit'] ) ) return true;

    $fafar_cf7crud_db->insert( $table_name, $new_data );

    do_action( 'fafar_cf7crud_after_create', $unique_hash );

    return true;
}
