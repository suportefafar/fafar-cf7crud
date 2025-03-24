<?php

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

add_filter('wpcf7_form_tag', 'fafar_cf7crud_pre_set_input_value');
add_filter('wpcf7_form_elements', 'fafar_cf7crud_create_custom_file_type_input');

/**
 * Handles form submission updates for CF7 CRUD operations.
 *
 * @since 1.0.0
 * @param WPCF7_ContactForm $contact_form Form object.
 * @param WPCF7_Submission  $submission  Submission object.
 * @return bool Operation status.
 */
function fafar_cf7crud_before_send_mail_update( $contact_form, $submission ) {
    global $wpdb;

    // Validate submission object
    if (!$submission) {
        return fafar_cf7crud_abort_submission(
            $contact_form,
            __('Objeto não encontrado!', 'fafar-cf7crud')
        );
    }

    // Check for submission ID
    $submission_id = $submission->get_posted_data( 'fafar_cf7crud_submission_id' );
    if ( empty( $submission_id ) ) {
        return fafar_cf7crud_abort_submission(
            $contact_form,
            __('ID de objeto não encontrado!', 'fafar-cf7crud')
        );
    }

    // Apply filters for database and upload directory
    $db = apply_filters( 'fafar_cf7crud_set_database', $wpdb );
    $table_name = $db->prefix . 'fafar_cf7crud_submissions';
    $upload_dir = apply_filters(
        'fafar_cf7crud_set_upload_dir_path',
        wp_upload_dir()['basedir'] . '/fafar-cf7crud-uploads/'
    );

    // Process uploaded files
    $file_mappings = fafar_cf7crud_process_uploaded_files(
        $submission->uploaded_files(),
        $submission_id,
        $upload_dir
    );

    $posted_data = $submission->get_posted_data();
    // Prepare form data
    $form_data = fafar_cf7crud_prepare_form_data(
        $posted_data,
        $contact_form->scan_form_tags(),
        $file_mappings
    );

    // Build the record for update
    $record = [
        'id'      => $submission_id,
        'data'    => json_encode($form_data),
        'form_id' => $contact_form->id(),
    ];

    // Map form fields to database columns
    $record = fafar_cf7crud_insert_common_columns( $record, $posted_data, $db, $table_name );

    // Apply filters for additional validation
    $record = apply_filters( 'fafar_cf7crud_before_update', $record, $submission_id, $contact_form );

    if ( empty( $record ) ) {
        return fafar_cf7crud_abort_submission(
            $contact_form,
            __('Falha na validação de dados', 'fafar-cf7crud'),
        );
    }
    
    if ( isset( $record['error_msg'] ) ) {
        return fafar_cf7crud_abort_submission(
            $contact_form,
            __($record['error_msg'], 'fafar-cf7crud'),
        );
    }

    if (
        ! empty( $record['far_prevent_submit'] ) || 
        isset( $posted_data['far_prevent_submit'] )
    ) return true;

    // Update the database
    if ($db->update( $table_name, $record, ['id' => $submission_id] ) === false) {
        return fafar_cf7crud_abort_submission(
            $contact_form,
            __('Falha na atualização', 'fafar-cf7crud')
        );
    }

    do_action('fafar_cf7crud_after_update', $submission_id);
    return true;
}


/**
 * Sets input values for CF7 form tags.
 *
 * @param array $tag CF7 form tag.
 * @return array Modified CF7 form tag.
 */
function fafar_cf7crud_pre_set_input_value( $tag ) {
    if ( is_admin() || !isset( $_GET['id'] ) ) {
        return $tag;
    }

    $value = fafar_cf7crud_get_submission_data_prop( $tag['name'] );
    if ( empty( $value ) ) {
        return $tag;
    }

    if ( in_array( $tag['basetype'], ['radio', 'select', 'checkbox'] ) ) {
        $tag = fafar_cf7crud_set_tag_default_options( $tag, $value );
    } else {
        $tag['values'] = (array) $value;
    }

    return $tag;
}

/**
 * Replaces CF7 file inputs with custom file inputs.
 *
 * @param string $content Form content.
 * @return string Modified form content.
 */
function fafar_cf7crud_create_custom_file_type_input( $content ) {
    if ( is_admin() ) {
        return $content;
    }

    $file_attrs = isset( $_GET['id'] ) ? fafar_cf7crud_get_file_attrs() : [];

    preg_match_all( '/<input[^>]*type="file"[^>]*\/?>/', $content, $matches );
    foreach ( $matches[0] as $input ) {
        $content = str_replace(
            $input,
            fafar_cf7crud_get_custom_input_file( $input, $file_attrs ),
            $content
        );
    }

    return $content;
}

/**
 * Generates a custom file input HTML.
 *
 * @param string $input      Original file input HTML.
 * @param array  $file_attrs File attributes.
 * @return string Custom file input HTML.
 */
function fafar_cf7crud_get_custom_input_file( $input_file_str, $file_attrs ) {

    // Pegando o 'name' no input
    preg_match( '/name="[\S]+"/', $input_file_str, $matches );
    $input_name = str_replace( 'name="' , '', $matches[0] );
    $input_name = str_replace( '"' , '', $input_name );

    // Forma o nome de um campo de arquivo possível guardado no BD
    $db_file_column = 'fafar_cf7crud_file_' . $input_name;

    // Pega o valor: String | ""
    $value_attr = fafar_cf7crud_get_input_file_attr_value( $db_file_column, $file_attrs );

    // Building fafar cf7crud file input with custom label and data attr
    $custom_input_file  = '<div class="fafar-cf7crud-input-document-container">
                                <button type="button" class="fafar-cf7crud-input-document-button" data-file-input-button="' . esc_attr( $input_name ) . '">
                                <span class="dashicons dashicons-upload"></span>
                                Arquivo
                                </button>
                                <span class="fafar-cf7crud-input-document-name" data-file-input-label="' . esc_attr( $input_name ) . '">
                                ' . esc_html( ( $value_attr ?? "Selecione um arquivo" ) ) . '
                                </span>
                            </div>';

    // Setting value attr of stock file input
    $input_file_str = preg_replace( '/\/?>/', ' value="' . esc_attr( $value_attr ) . '" />', $input_file_str );

    // Setting custom class
    $input_file_str = preg_replace( '/class=\"/', ' class="fafar-cf7crud-stock-file-input ', $input_file_str );

    // Building a hidden input to store the file names
    $input_hidden_to_store_file_path = 
        "<input class='wpcf7-form-control wpcf7-hidden' name='fafar_cf7crud_input_file_hidden_" . esc_attr( $input_name ) . "' value='" . esc_html( ( $value_attr ?? "" ) ) . "' type='hidden' />";

    return $input_file_str . $custom_input_file . $input_hidden_to_store_file_path;
}

function fafar_cf7crud_get_input_file_attr_value( $key_attr, $file_attrs ) {

    foreach ( $file_attrs as $key => $value) {

        if ( $key_attr == $key ) return $value;

    }

    return '';
}

/**
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

/**
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