<?php

/*
 * Using the cf7 'wpcf7_form_hidden_fields' hook.
 * This hook passes a array, where each of it's keys is the 
 * hidden input 'name' and it's values is the input's value
 * Ex: 
 *    $hiddens['codigo'] = 123
 *    is
 *    <input type='hidden' name='codigo' value='123'>
*/
add_filter('wpcf7_form_hidden_fields', 'fafar_cf7crud_add_hidden_nonce_input');

/*
 * Function waits a array whitch each key is the name of a 
 * hidden input, and it's respective value is it's values.
 * Ex: 
 *    $hiddens['codigo'] = 123
 *    is
 *    <input type='hidden' name='codigo' value='123'>
 * 
 * Then creates a nonce to be the new input hidden value 
 * using the action name.
 *
 * @since 1.0.0
 * @param array $hiddens     Hidden inputs array.
 * @return array
*/
function fafar_cf7crud_add_hidden_nonce_input( $hiddens ) {

    $hiddens['fafar-cf7crud-nonce'] = wp_create_nonce( 'fafar-cf7crud-create-submission-nonce' );

    return $hiddens;

}


/*
 * Function to create a submission
 * Runs when at 'wpcf7_before_send_mail' action hook
 *
 * @since 1.0.0
 * @param array $contact_form     Input form data.
 * @return null
*/
function fafar_cf7crud_before_send_mail_create( $contact_form ) {

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


    // Submission not found
    if ( ! $submission ) {

        $contact_form->skip_mail = true; // Skip sending the mail
        $submission->add_error( __( 'Submission not found!', 'fafar-cf7crud' ) );

    }


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

        
        // Submission forbidden
        if( ! wp_verify_nonce( $data['fafar-cf7crud-nonce'], 'fafar-cf7crud-create-submission-nonce' ) ) {

            $contact_form->skip_mail = true; // Skip sending the mail
            $submission->add_error( __( 'Forbidden submission!', 'fafar-cf7crud' ) ); //Dando erro nessa linha

        }


        foreach ( $_FILES as $file_key => $file ) {
            array_push( $uploaded_files, $file_key );
        }
        foreach ( $files as $file_key => $file ) {
            $file = is_array( $file ) ? reset( $file ) : $file;
            if( empty($file) ) continue;
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
        $form_data = apply_filters( 'fafar_cf7crud_before_save_data', $form_data );

        do_action( 'fafar_cf7crud_before_save', $form_data );

        $form_id         = $contact_form->id();
        $submission_data = json_encode( $form_data );

        $cfdb->insert( $table_name, array(
            'id'      => $unique_hash,
            'form_id' => $form_id,
            'data'    => $submission_data,
        ) );

        /* fafar_cf7crud after save data */
        $insert_id = $cfdb->insert_id;
        do_action( 'fafar_cf7crud_after_save_data', $insert_id );


}
