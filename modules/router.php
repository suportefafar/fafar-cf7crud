<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/*
 * Import modules
*/
require_once trailingslashit( plugin_dir_path(__FILE__) ) . 'general.php';
require_once trailingslashit( plugin_dir_path(__FILE__) ) . 'create.php';
require_once trailingslashit( plugin_dir_path(__FILE__) ) . 'read.php';
require_once trailingslashit( plugin_dir_path(__FILE__) ) . 'update.php';
require_once trailingslashit( plugin_dir_path(__FILE__) ) . 'delete.php';


/*
 * Using 'wpcf7_before_send_mail' hook for creating or updating submissions.
 * We use 3 parameters available: 
 *      - $this->contact_form (Current instance of WPCF7_ContactForm);
 *      - &$abort (bool);
 *      - $this (Current instance of WPCF7_Submission);
 * add_action( $hook_name:string, $callback:callable, $priority:integer, $accepted_args:integer )
*/
add_action( 'wpcf7_before_send_mail', 'fafar_cf7crud_before_send_mail_handler', 10, 3 );


/*
 * Function to decide with is a creater or updater form
 *
 * @since 1.0.0
 * @param array $contact_form     Input form data.
 * @return null
*/
function fafar_cf7crud_before_send_mail_handler( $contact_form, $abort, $submission ) {

    if( $submission->get_posted_data( 'fafar_cf7crud_create_submission' ) !== null ) {

        $abort = ! fafar_cf7crud_before_send_mail_create( $contact_form, $submission );
        
    } else if( $submission->get_posted_data( 'fafar_cf7crud_update_submission' ) !== null || 
                $submission->get_posted_data( 'fafar_cf7crud_submission_id' ) !== null ) {

        $abort = ! fafar_cf7crud_before_send_mail_update( $contact_form, $submission );
        
    } else {
        
        $abort = ! fafar_cf7crud_before_send_mail_create( $contact_form, $submission );

    }

    $abort = true;

}
