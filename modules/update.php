<?php

add_filter( 'wpcf7_form_tag', 'fafar_cf7crud_pre_set_input_value' );

add_filter( 'wpcf7_form_elements', 'fafar_cf7crud_create_custom_file_type_input' );
add_filter( 'wpcf7_form_elements', 'fafar_cf7crud_add_submission_id_hidden' );

/*
 * Called on 'wpcf7_before_send_mail' filter hook
 * Function to update a submission
 * Runs when at 'wpcf7_before_send_mail' action hook
 *
 * @since 1.0.0
 * @param WPCF7_ContactForm Object $contact_form     Input form data.
 * @return null
*/
function fafar_cf7crud_before_send_mail_update( $contact_form, $submission ) {

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

    // Update routine diff
    if ( ! $submission->get_posted_data( "fafar-cf7crud-submission-id" )) {
        
        $contact_form->skip_mail = true; // Skip sending the mail
        $submission->set_status( 'aborted' );
        $submission->set_response( $contact_form->filter_message(
            __( 'Submission ID not found!', 'fafar-cf7crud' ) 
            )
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
     * Get submission ID form field previously set by 'fafar_cf7crud_add_submission_id_hidden()'
     */
    $unique_hash = $submission->get_posted_data( "fafar-cf7crud-submission-id" );

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
         * Ignores 'fafar-cf7crud-submission-id' because is just used to retrieve 
         * the submission ID.
         * Update routine diff
        */
        if ( $key == 'fafar-cf7crud-submission-id' ) continue;

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
    $new_data = apply_filters( 'fafar_cf7crud_before_update', $new_data, $contact_form );

    if ( ! $new_data ) {

        add_filter('wpcf7_ajax_json_echo', function ($response, $result) {
            $response['status'] = 'mail_sent_ng';
            $response['message'] = 'Unknow error. Some function return null from "fafar_cf7crud_before_update" hook.';
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

    $fafar_cf7crud_db->update(
        $table_name,
        $new_data,
        array(
            'id' => $unique_hash
        )
    );

    do_action( 'fafar_cf7crud_after_update', $unique_hash );

    return true;
}

/*
 * Called on 'wpcf7_form_tag' filter hook.
 * This set a value to the inputs 'value' prop.
 * Ex: <input value="VALUE_TO_BE_SET" />
 * 
 * Works on 'checkbox', 'radio' and 'select' input tags, too.
 *
 * @since 1.0.0
 * @param  CF7 Tag Object $tag  CF7 tag object from contact-form-7/includes/form-tags-manager.php
 * @return CF7 Tag Object $tag  CF7 tag object from contact-form-7/includes/form-tags-manager.php
*/
function fafar_cf7crud_pre_set_input_value( $tag ) {
    
    if ( is_admin() ) return $tag;


    if ( ! isset( $_GET['id'] ) ) return $tag;

    if ( $tag['basetype'] == 'file' ) {

        $input_value = fafar_cf7crud_get_submission_data_prop( 'fafar_cf7crud_file_' . $tag['name'] );

        $tag['raw_values'] = $input_value;
        return $tag;

    }


    $input_value = fafar_cf7crud_get_submission_data_prop( $tag['name'] );  
    if ( empty( $input_value ) ) return $tag;

    if ( $tag['basetype'] == 'radio' ) {

        $tag = fafar_cf7crud_set_tag_default_options( $tag, $input_value );

    } else if ( $tag['basetype'] == 'select' ) {

        $tag = fafar_cf7crud_set_tag_default_options( $tag, $input_value );

    } else if ( $tag['basetype'] == 'checkbox' ) {

        $tag = fafar_cf7crud_set_tag_default_options( $tag, $input_value );

    } else {

        $input_value = fafar_cf7crud_get_submission_data_prop( $tag['name'] );

        $tag['values'] = (array) $input_value;

    }

    return $tag;
}

/*
 * Called by 'wpcf7_form_elements' filter hook.
 * It just adds a hidden input with the id of the submission, 
 * on the end of the HTML form.
 *
 * @since 1.0.0
 * @param  string $content  HTML form string
 * @return string $content  HTML form string
*/
function fafar_cf7crud_add_submission_id_hidden($content) {

    if ( is_admin() ) 
        return $content;

    // Adding Hidden Submission ID Field
    if ( isset( $_GET['id'] ) )
        $content .= "<input class='wpcf7-form-control wpcf7-hidden' type='hidden' name='fafar-cf7crud-submission-id' value='" . $_GET['id'] . "' />";

	return $content;

}

/*
 * Called by 'wpcf7_form_elements' filter hook.
 * It changes the stock file input for a custom.
 * This is necessary cause when 'value' file input property 
 * is set by other way then manually by the user, the name of 
 * the file does not appears. This is a default HTML tag behavior.
 *
 * @since 1.0.0
 * @param  string $content  HTML form string
 * @return string $content  HTML form string
*/
function fafar_cf7crud_create_custom_file_type_input( $content ) {

    if ( is_admin() ) 
        return $content;

    $file_attrs = array();

    if ( isset( $_GET['id'] ) )
        $file_attrs = fafar_cf7crud_get_file_attrs();

    // Creating a pattern
    $startPattern = '/<input[^>]*';
    $type = 'type="file"';
    $endPattern = '[^>]*\/?>/';
        
    $pattern = $startPattern . $type . $endPattern;
        
    // Perform the regex match
    if ( preg_match_all( $pattern, $content, $input_file_matches ) ) {
        // If has at least one
        foreach( $input_file_matches[0] as $input_file_match ) {

            // Add's a custom file input, after the original file input(hidden by css)
            $content = str_replace( $input_file_match, fafar_cf7crud_get_custom_input_file( $input_file_match, $file_attrs ), $content );
                
        }
        
    }

	return $content;
}

function fafar_cf7crud_get_file_attrs() {

    global $wpdb;

   /*
    * If other database should be used.
    */
    $fafar_cf7crud_db = apply_filters( 'fafar_cf7crud_set_database', $wpdb );
    $table_name       = $fafar_cf7crud_db->prefix . 'fafar_cf7crud_submissions';

    $submissions = $fafar_cf7crud_db->get_results(
        "SELECT * FROM `" . $table_name . "` WHERE `id` = '" . $_GET['id'] . "'" 
    );

    $file_attrs = array();

	if ( !$submissions[0] )
        return $file_attrs;
	
	$form_data = json_decode( $submissions[0]->data );

	foreach ( $form_data as $chave => $data ) {

        if ( strpos( $chave, 'fafar_cf7crud_file_' ) !== false ) {

            $file_attrs[ $chave ] = $data;

        }

    }

    return $file_attrs;
    
}

/*
 * This function set the 'default: x' 'options' on cf7 tag.
 * Ex: [radio 'default: 1' 'Yes' 'No']
 * If exists, updates;
 * If not, creates;
 *
 * @since 1.0.0
 * @param CF7 Tag Object $tag     CF7 tag object.
 * @param array          $values  Values to compare and set as default.
 * @return $tag
*/
function fafar_cf7crud_set_tag_default_options( $tag, $values ) { 

    $default_arr = array();
    foreach ($values as $value) {
    
        foreach ($tag['raw_values'] as $tag_key => $tag_value) {
            
            $field_value = $tag_value;

            if ( str_contains( $tag_value , '|' ) ) {

                $field_value = explode( '|', $tag_value )[1];

            }

            if ( $value == $field_value ) {

                array_push( $default_arr, ($tag_key + 1) );
                break;

            }

        }

    }

    $default = 'default: ' . implode( "_", $default_arr );
    
    $default_option_index = fafar_cf7crud_get_tag_option_value( $tag, 'default', true );
    
    if ( $default_option_index ) {

        $tag['options'][$default_option_index] = $default;
        return $tag;

    }

    array_push( $tag['options'], $default );
    return $tag;

}

/*
 * Function to get value from a data.JSON_PROP
 *
 * @since 1.0.0
 * @param string $json_prop     A prop of data submission json.
 * @return array
*/
function fafar_cf7crud_get_submission_data_prop( $json_prop ) {

    global $wpdb;

   /*
    * If other database should be used.
    */
    $fafar_cf7crud_db = apply_filters( 'fafar_cf7crud_set_database', $wpdb );
    $table_name       = $fafar_cf7crud_db->prefix . 'fafar_cf7crud_submissions';

    $query = "SELECT * FROM `" . 
        $table_name .
        "` WHERE `id` = '" . $_GET['id'] . "'";

    $submissions = $fafar_cf7crud_db->get_results( $query );

	if ( !$submissions[0] ) 
        return [];
	
	$data_json = json_decode( $submissions[0]->data );

	foreach ( $data_json as $key => $value ) {

        if ( $key ===  $json_prop ) {
            // TODO: escape text
            if ( ! empty( $value ) ) 
                if ( is_array( $value ) ) return $value;
                else return (array) $value;

        }

    }

    return [];
}

/*
 * Function sets a value to certain option of a tag.
 * 
 * $tag['options'] = array( [0] => 'default: 1', ... )
 * 
 * @since 1.0.0
 * @param  CF7 Tag Object $tag     CF7 tag object.
 * @param  string $option     The name of the option.
 * @param  mixed $value     Value to set to option.
 * @return CF7 Tag Object $tag     CF7 tag object.
*/
function set_tag_option_value( $tag, $option, $value ) {

    $option_index = fafar_cf7crud_get_tag_option_value( $tag, $option, true );

    if ( ! $option_index ) return $tag;

    $tag['options'][$index] = $value;

    return $tag;

}

function fafar_cf7crud_get_input_file_attr_value( $key_attr, $file_attrs ) {

    foreach ( $file_attrs as $key => $value) {

        if ( $key_attr == $key ) return $value;

    }

    return '';
}

function fafar_cf7crud_get_custom_input_file( $input_file_str, $file_attrs ) {

    // Get name attr from stock file input
    preg_match( '/name="[\S]+"/', $input_file_str, $matches );
    $name_attr = str_replace( 'name="' , '', $matches[0] );
    $name_attr = str_replace( '"' , '', $name_attr );

    // Set attr as database saved
    $key_attr_with_file_db_sufix = 'fafar_cf7crud_file_' . $name_attr;

    // Get current attr value: String | ""
    $value_attr = fafar_cf7crud_get_input_file_attr_value( $key_attr_with_file_db_sufix, $file_attrs );

    // Building fafar cf7crud file input with custom label and data attr
    $custom_input_file  = "<div class='fafar-cf7crud-input-document-container'>";
    $custom_input_file .= "<button type='button' class='fafar-cf7crud-input-document-button' data-file-input-button='" . $name_attr . "'>";
    $custom_input_file .= "<span class='dashicons dashicons-upload'></span>";
    $custom_input_file .= "Arquivo";
    $custom_input_file .= "</button>";
    $custom_input_file .= "<span class='fafar-cf7crud-input-document-name' data-file-input-label='" . $name_attr . "'>";
    $custom_input_file .= ( $value_attr ?? "Selecione um arquivo" );
    $custom_input_file .= "</span>";
    $custom_input_file .= "</div>";

    // Setting value attr of stock file input
    $input_file_str = preg_replace( '/\/?>/', ' value="' . $value_attr . '" />', $input_file_str );

    // Setting custom class
    $input_file_str = preg_replace( '/class=\"/', ' class="fafar-cf7crud-stock-file-input ', $input_file_str );

    // Building a hidden input to store the file names
    $input_hidden_to_store_file_path = 
        "<input class='wpcf7-form-control wpcf7-hidden' name='fafar_cf7crud_input_file_hidden_" . $name_attr . "' value='" . ( $value_attr ?? "" ) . "' type='hidden' />";


    return $input_file_str . $custom_input_file . $input_hidden_to_store_file_path;
}
