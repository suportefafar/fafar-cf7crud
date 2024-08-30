<?php

add_filter('wpcf7_form_elements', 'fafar_cf7crud_adding_hidden_fields');


add_filter('wpcf7_form_tag', 'fafar_cf7crud_populate_form_field');


function fafar_cf7crud_before_send_mail_update( $contact_form ) {

    /**
     * UPDATE SUBMISSION ROUTINE
     * **/

    global $wpdb;
    $cfdb                  = apply_filters( 'fafar_cf7crud_database', $wpdb ); // Caso queira mudar o banco de dados
    $table_name            = $cfdb->prefix . 'fafar_cf7crud_submissions';
    $upload_dir            = wp_upload_dir();
    $fafar_cf7crud_dirname = $upload_dir[ 'basedir' ] . '/fafar-cf7crud-uploads';
    $bytes                 = random_bytes(5);
    $unique_hash           = time().bin2hex($bytes);

    $submission   = WPCF7_Submission::get_instance();
    $contact_form = $submission->get_contact_form();
    $tags_names   = array();
    $strict_keys  = apply_filters('fafar_cf7crud_strict_keys', false);  

    if ( $submission ) {

        $allowed_tags = array();
        $bl   = array( '\"', "\'", '/', '\\', '"', "'" );
        $wl   = array( '&quot;', '&#039;', '&#047;', '&#092;', '&quot;', '&#039;' );

        if( $strict_keys ){
            $tags  = $contact_form->scan_form_tags();
            foreach( $tags as $tag ){
                if( ! empty($tag->name) ) $tags_names[] = $tag->name;
            }
            $allowed_tags = $tags_names;
        }

        $not_allowed_tags = apply_filters( 'fafar_cf7crud_not_allowed_tags', array( 'g-recaptcha-response' ) );
        $allowed_tags     = apply_filters( 'fafar_cf7crud_allowed_tags', $allowed_tags );
        $data             = $submission->get_posted_data();
        $files            = $submission->uploaded_files();
        $uploaded_files   = array();

        $unique_hash = $submission->get_posted_data( "fafar-cf7crud-submission-id" ) ?
                            $submission->get_posted_data( "fafar-cf7crud-submission-id" ) : $unique_hash;

        foreach ( $_FILES as $file_key => $file ) {

            array_push( $uploaded_files, $file_key );

        }
        foreach ( $files as $file_key => $file ) {

            $file = is_array( $file ) ? reset( $file ) : $file;
            if( empty( $file ) ) continue;
            copy( $file, $fafar_cf7crud_dirname . '/' . $unique_hash . '-' . $file_key . '-' . basename( $file ) );

        }

        $form_data = array();
        
        foreach ( $data as $key => $d ) {
            
            if( $strict_keys && !in_array( $key, $allowed_tags ) ) continue;

            if( $key == 'fafar-cf7crud-submission-id' ) continue;

            if( str_contains( $key, 'fafar-cf7crud-input-file-hidden-' ) ) continue;

            if ( !in_array( $key, $not_allowed_tags ) && !in_array( $key, $uploaded_files )  ) {

                $tmpD = $d;

                if ( ! is_array( $d ) ) {
                    $tmpD = str_replace( $bl, $wl, $tmpD );
                } else {

                    $tmpD = array_map( function($item) use($bl, $wl) {
                               return str_replace( $bl, $wl, $item ); 
                            }, $tmpD);
                }

                $key = sanitize_text_field( $key );
                $form_data[ $key ] = $tmpD;
            }
            if ( in_array( $key, $uploaded_files ) ) {

                $file = is_array( $files[ $key ] ) ? reset( $files[ $key ] ) : $files[ $key ];
                
                $file_name = empty( $file ) ? '' : $unique_hash . '-' . $key . '-' . basename( $file ); 
                
                $key = sanitize_text_field( $key );
                
                $form_data[ $key . 'fafarcf7crudfile' ] = $file_name;

                if( $file_name == '' ) {

                    $form_data[ $key . 'fafarcf7crudfile' ] = 
                        $submission->get_posted_data( 'fafar-cf7crud-input-file-hidden-' . $key ) ? 
                            $submission->get_posted_data( 'fafar-cf7crud-input-file-hidden-' . $key ) : "";

                }

            }
        }

        /* fafar_cf7crud before save data. */
        $form_data = apply_filters( 'fafar_cf7crud_before_update_data', $form_data );

        do_action( 'fafar_cf7crud_before_update', $form_data );

        $form_id         = $contact_form->id();
        $submission_data = json_encode( $form_data );

        $cfdb->update(
            $table_name,
            array(
                'submission_data' => $submission_data
            ),
            array(
                'submission_id' => $submission->get_posted_data( "fafar-cf7crud-submission-id" )
            )
        );

        /* fafar_cf7crud after save data */
        do_action( 'fafar_cf7crud_after_update_data', $submission->get_posted_data( "fafar-cf7crud-submission-id" ) );
    }
}


function fafar_cf7crud_get_file_attrs() {

    global $wpdb;

    $cfdb = apply_filters( 'fafar_cf7crud_database', $wpdb ); // Caso queira mudar o banco de dados

    $assistido = $cfdb->get_results("SELECT * FROM `" . $cfdb->prefix . 'fafar_cf7crud_submissions' . "` WHERE `submission_id` = '" . $_GET['id'] . "'" );

    $file_attrs = array();

	if ( !$assistido[0] )
        return $file_attrs;
	
	$form_data = json_decode( $assistido[0]->submission_data );

	foreach ( $form_data as $chave => $data ) {

        if ( strpos( $chave, 'fafarcf7crudfile' ) !== false ) {

            $file_attrs[ $chave ] = $data;

        }

    }

    return $file_attrs;
    
}

function fafar_cf7crud_get_input_value( $tag_name ) {

    global $wpdb;

    $cfdb = apply_filters( 'fafar_cf7crud_database', $wpdb ); // Caso queira mudar o banco de dados

    $assistido = $cfdb->get_results( "SELECT * FROM `" . $cfdb->prefix . 'fafar_cf7crud_submissions' . "` WHERE `submission_id` = '" . $_GET['id'] . "'" );

	if ( !$assistido[0] ) 
        return "";
	
	$form_data = json_decode( $assistido[0]->submission_data );

	foreach ( $form_data as $chave => $data ) {

        if( $chave === $tag_name ) {
            
            if( is_array( $data ) && !empty( $data ) ) return $data[0];
            else return $data;

        }

    }
}

function fafar_cf7crud_populate_form_field($tag) {
    
    if( is_admin() ) return $tag;

    if( !isset( $_GET['id'] ) ) return $tag;

    if( $tag['basetype'] == 'radio' ) {

        $input_value = fafar_cf7crud_get_input_value( $tag['name'] );

        foreach ($tag['values'] as $key => $value) {

            if ($value == $input_value) {

                $tag['options'][] = 'default:' . ($key + 1);
                break;
            }
        }

    } else if( $tag['basetype'] == 'select' ) {

        $input_value = fafar_cf7crud_get_input_value( $tag['name'] );

        foreach ($tag['values'] as $key => $value) {

            if ($value == $input_value) {

                $tag['options'][] = 'default:' . ($key + 1);
                break;
            }
        }

    } else if( $tag['basetype'] == 'file' ) {

        $input_value = fafar_cf7crud_get_input_value( $tag['name'] . 'fafarcf7crudfile' );

        $tag['raw_values'] = (array) $input_value;

    } else {

        $input_value = fafar_cf7crud_get_input_value( $tag['name'] );

        $tag['values'] = (array) $input_value;

    }

    return $tag;
}

function fafar_cf7crud_get_input_file_attr_value( $key_attr, $file_attrs ) {

    foreach ( $file_attrs as $key => $value) {

        if( $key_attr == $key ) return $value;

    }

    return '';
}

function fafar_cf7crud_get_custom_input_file( $input_file_str, $file_attrs ) {

    // Get name attr from stock file input
    preg_match( '/name="[\S]+"/', $input_file_str, $matches );
    $name_attr = str_replace( 'name="' , '', $matches[0] );
    $name_attr = str_replace( '"' , '', $name_attr );

    // Set attr as database saved
    $key_attr_with_file_db_sufix = $name_attr . 'fafarcf7crudfile';

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

function fafar_cf7crud_adding_hidden_fields($content) {
    
    if( is_admin() ) 
        return $content;

    $file_attrs = array();

    if( isset( $_GET['id'] ) )
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

    // if( ! empty($_GET) ) {
    //     foreach ( $_GET as $key => $value) {

    //         if( $key == 'id' ) continue;

    //         $content .= "<input class='wpcf7-form-control wpcf7-hidden' value='" . $value . "' type='hidden' name='" . $key . "' />";

    //     }
    // }

    // Adding Hidden Submission ID Field
    if( isset( $_GET['id'] ) )
        $content .= "<input class='wpcf7-form-control wpcf7-hidden' value='" . $_GET['id'] . "' type='hidden' name='fafar-cf7crud-submission-id' />";

	return $content;
}

