<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Handles form submission data for CF7 CRUD operations
 * 
 * @since 1.0.0
 * @param WPCF7_ContactForm $contact_form Form object
 * @param WPCF7_Submission  $submission  Submission object
 * @return bool Operation status
 */
function fafar_cf7crud_before_send_mail_create($contact_form, $submission) {
    global $wpdb;

    // Validate submission object
    if ( ! $submission || ! $contact_form instanceof WPCF7_ContactForm ) {
        return fafar_cf7crud_abort_submission(
            $contact_form, 
            __('Dados inválidos', 'fafar-cf7crud')
        );
    }

    // Setup database and storage
    $db         = apply_filters( 'fafar_cf7crud_set_database', $wpdb );
    $table_name = $db->prefix . 'fafar_cf7crud_submissions';

    try {
        // Set up the upload directory
        $upload_dir = fafar_cf7crud_setup_upload_directory();
    } catch (RuntimeException $e) {
        return fafar_cf7crud_abort_submission( $contact_form, $e->getMessage() );
    }

    // Generate unique submission ID
    $submission_id = generate_submission_id();

    // Handle file uploads
    try {
        $file_mappings = fafar_cf7crud_process_uploaded_files(
            $submission->uploaded_files(),
            $submission_id,
            $upload_dir['basedir'],
        );
    } catch ( RuntimeException $e ) {
        return fafar_cf7crud_abort_submission( $contact_form, $e->getMessage() );
    }
    
    $posted_data = $submission->get_posted_data();

    // Process form data
    $form_data = fafar_cf7crud_prepare_form_data(
        $posted_data,
        $contact_form->scan_form_tags(),
        $file_mappings,
    );
    
    error_log( print_r( $form_data, true ) );


    // Serialize form data for storage
    $encoded_data = json_encode($form_data);

    // Build the record
    $record = [
        'id'      => $submission_id,
        'form_id' => $contact_form->id(),
        'data'    => $encoded_data,
    ];

    $record = fafar_cf7crud_insert_common_columns(
        $record,
        $posted_data,
        $db,
        $table_name,
    );

    // Validate before insertion
    $record = apply_filters( 'fafar_cf7crud_before_create', $record, $contact_form );
    
    if ( empty( $record ) ) {
        return fafar_cf7crud_abort_submission(
            $contact_form,
            __('Falha na validação de campos', 'fafar-cf7crud'),
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

    // Insert into database
    if ( $db->insert( $table_name, $record ) === false ) {
        return fafar_cf7crud_abort_submission(
            $contact_form,
            __('Falha ao salvar', 'fafar-cf7crud')
        );
    }

    do_action( 'fafar_cf7crud_after_create', $submission_id );

    return true;
}

/**
 * Generates a unique submission ID.
 *
 * @since 1.1.0
 * @return string Unique submission ID.
 */
function generate_submission_id(): string {
    // Generate a random string using bin2hex and random_bytes
    $random_bytes = random_bytes( 5 ); // 5 bytes = 10 characters in hex
    $random_hex   = bin2hex( $random_bytes );

    // Combine the current timestamp with the random hex string
    $unique_id = time() . $random_hex;

    return $unique_id;
}