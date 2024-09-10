<?php

add_filter( 'wpcf7_form_tag', 'fafar_cf7crud_populate_input_value_dynamically' );
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
     * Generating unique hash for submission 'id' 
     * if not passed
     */
    $bytes       = random_bytes(5);
    $unique_hash = $submission->get_posted_data( "fafar-cf7crud-submission-id" ); // Update routine diff


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
        if ( ! empty($file) ) {

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
        if ( ! empty($tag->name) ) $tags_names[] = $tag->name;
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
         * Jump's at 'fafar-cf7crud-submission-id' because is just used to retrieve 
         * the submission ID.
         * Update routine diff
        */
        if ( $key == 'fafar-cf7crud-submission-id' ) continue;

        /**
         * Jump's at 'fafar-cf7crud-object-name' to use it as column on DB
        */
        if ( $key === 'fafar-cf7crud-object-name' ) continue;

        /**
         * Jump's field whitch $key do not appears on 
         * original form WPCF7_FormTag Object.
         */
        if ( ! in_array( $key, $allowed_tags ) ) continue;

        /**
         * Jump's field whitch $key do appears on 
         * $not_allowed_fields array.
         */
        if ( in_array( $key, $not_allowed_fields ) ) continue;

        /**
         * FILES HANDLER
         * If $files_to_database[$key] is set, 
         * stores on $form_data.
         */
        if ( isset( $files_to_database[$key] ) ) {

            $file_prefix = 'fafar-cf7crud-file-';

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

    /**
     *  This filter hook gives the oportunity to make a 
     *  another check/validation. 
     */
    $form_data = apply_filters('fafar_cf7crud_before_update', $form_data);

    if ( isset( $form_data["error_msg"] ) ) {
        add_filter("wpcf7_ajax_json_echo", function ($response, $result) {
            $response["status"] = "mail_sent_ng";
            $response["message"] = $form_data["error_msg"];
            return $response;
        }, 10, 2);
        return false;
    }

    $form_post_id      = $contact_form->id();
    $form_data_as_json = json_encode( $form_data );

    $fafar_cf7crud_db->update(
        $table_name,
        array(
            'data' => $form_data_as_json
        ),
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

    $option_display_value = get_tag_option_value( $tag, 'fa-crud-display' );

    if ( $option_display_value === false ) return $tag;

    /**
     * Column Filter Part
    */
    $option_column_filter_value = get_tag_option_value( $tag, 'fa-crud-column-filter' ); 
    $query_filter_column_part = '';
    if ( $option_column_filter_value !== false ) {

        // Expected: fa-crud-column-filter:COLUMN_1|VALUE_1:COLUMN_2|VALUE_2
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
            if ( $key === 'form_id' || $key === 'is_active' )
                $comparison = '`' . $key . '` = ' . $value ." ";

            array_push( $where_column_arr, $comparison );
    
        }

        if ( count( $where_column_arr ) > 0 ) 
            $query_filter_column_part = implode( " AND ", $where_column_arr );

    }

    /**
     * JSON Filter Part
    */
    $option_json_filter_value = get_tag_option_value( $tag, 'fa-crud-json-filter' );  
    $query_filter_json_part = '';
    if ( $option_json_filter_value !== false ) {

        // Expected: fa-crud-column-filter:PROP_JSON_1|VALUE_1:PROP_JSON_2|VALUE_2
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

    //print_r("<br/> // || // <br/>");
    //print_r($query);

    $submissions = $fafar_cf7crud_db->get_results( $query );

    if ( empty( $submissions ) ) return $tag;

    // Expected: fa-crud-display:LABEL_PROP|VALUE_PROP
    $option_display_value = explode( ":", $option_display_value )[1];
    $label_prop = explode( "|", $option_display_value )[0];
    $value_prop = explode( "|", $option_display_value )[1];

    foreach ( $submissions as $submission ){

        $submission_decoded = fafar_cf7crud_join_submission_props( $submission );
        
        if( ! isset( $submission_decoded[$label_prop] ) || 
            ! isset( $submission_decoded[$value_prop] ) ) continue;

        $raw_value = $submission_decoded[$label_prop] . "|" . $submission_decoded[$value_prop];
        array_push( $tag['raw_values'], $raw_value );

        $value = $submission_decoded[$label_prop];
        array_push( $tag['values'], $value );

    }

    //print_r( "<br><br>" );
    //print_r( $tag );

    return $tag;

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

    $submission_decoded = (array) json_decode( $submission->data );
    
    $submission_decoded["id"]           = $submission->id;
    $submission_decoded["form_id"]      = $submission->form_id;
    $submission_decoded["object_name"]  = $submission->object_name;
    $submission_decoded["is_active"]    = $submission->is_active;
    $submission_decoded["updated_at"]   = $submission->updated_at;
    $submission_decoded["created_at"]   = $submission->created_at;

    return $submission_decoded;
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

        $input_value = fafar_cf7crud_get_submission_data_prop( 'fafar-cf7crud-file-' . $tag['name'] );

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

    $fafar_cf7crud_db = apply_filters( 'fafar_cf7crud_database', $wpdb ); // Caso queira mudar o banco de dados

    $assistido = $fafar_cf7crud_db->get_results("SELECT * FROM `" . $fafar_cf7crud_db->prefix . 'fafar_cf7crud_submissions' . "` WHERE `id` = '" . $_GET['id'] . "'" );

    $file_attrs = array();

	if ( !$assistido[0] )
        return $file_attrs;
	
	$form_data = json_decode( $assistido[0]->data );

	foreach ( $form_data as $chave => $data ) {

        if ( strpos( $chave, 'fafar-cf7crud-file-' ) !== false ) {

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
    
        foreach ($tag['values'] as $tag_key => $tag_value) {

            if ($value == $tag_value) {

                array_push( $default_arr, ($tag_key + 1) );
                break;

            }

        }

    }

    $default = 'default: ' . implode( "_", $default_arr );
    
    $default_option_index = get_tag_option_value( $tag, 'default', true );
    
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

    $fafar_cf7crud_db = apply_filters( 'fafar_cf7crud_database', $wpdb );

    $query = "SELECT * FROM `" . 
        $fafar_cf7crud_db->prefix . "fafar_cf7crud_submissions`" .
        " WHERE `id` = '" . $_GET['id'] . "'";

    $submission = $fafar_cf7crud_db->get_results( $query );

	if ( !$submission[0] ) 
        return [];
	
	$data_json = json_decode( $submission[0]->data );

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
function get_tag_option_value( $tag, $option, $return_option_index = false ) {

    foreach ( $tag['options'] as $index => $value ) {

        /**
         * We can assume that every option has a ':'
         * to separate the value, if has any.
         * At least the ones we at threating here has.
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

    $option_index = get_tag_option_value( $tag, $option, true );

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
    $key_attr_with_file_db_sufix = 'fafar-cf7crud-file-' . $name_attr;

    // Get current attr value: String | ""
    $value_attr = fafar_cf7crud_get_input_file_attr_value( $key_attr_with_file_db_sufix, $file_attrs );

    // Building fafar cf7crud file input with custom label and data attr
    $custom_input_file  = "<div class='fafar-cf7crud-input-document-container'>";
    $custom_input_file .= "<button type='button' class='fafar-cf7crud-input-document-button' data-file-input-button='" . $name_attr . "'>";
    $custom_input_file .= "<span class='dashicons dashicons-upload'></span>";
    $custom_input_file .= "Arquivo";
    $custom_input_file .= "</button>";
    $custom_input_file .= "<span class='fafar-cf7crud-input-document-name' data-file-input-label='" . $name_attr . "'>";
    $custom_input_file .= ( $value_attr ) ? $value_attr : "Selecione um arquivo";
    $custom_input_file .= "</span>";
    $custom_input_file .= "</div>";

    // Setting value attr of stock file input
    $input_file_str = preg_replace( '/\/?>/', ' value="' . $value_attr . '" />', $input_file_str );

    // Setting custom class
    $input_file_str = preg_replace( '/class=\"/', ' class="fafar-cf7crud-stock-file-input ', $input_file_str );

    // Building a hidden input to store the file names
    $input_hidden_to_store_file_path = 
        "<input class='wpcf7-form-control wpcf7-hidden' name='fafar-cf7crud-input-file-hidden-" . $name_attr . "' value='" . ( ( $value_attr ) ? $value_attr : "" ) . "' type='hidden' />";


    return $input_file_str . $custom_input_file . $input_hidden_to_store_file_path;
}
