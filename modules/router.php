<?php


/*
 * Import modules
*/
require_once trailingslashit( plugin_dir_path(__FILE__) ) . 'create.php';
require_once trailingslashit( plugin_dir_path(__FILE__) ) . 'read.php';
require_once trailingslashit( plugin_dir_path(__FILE__) ) . 'update.php';
require_once trailingslashit( plugin_dir_path(__FILE__) ) . 'delete.php';


/*
 * Using 'wpcf7_before_send_mail' hook for creating or updating submissions
*/
add_action( 'wpcf7_before_send_mail', 'fafar_cf7crud_before_send_mail_handler' );


/*
 * Function to decide with is a creater or updater form
 *
 * @since 1.0.0
 * @param array $contact_form     Input form data.
 * @return null
*/
function fafar_cf7crud_before_send_mail_handler( $contact_form ) {

    $submission   = WPCF7_Submission::get_instance();

    if( $submission->get_posted_data( 'fafar-cf7crud-submission-id' ) !== null ) {

        fafar_cf7crud_before_send_mail_update( $contact_form );
        
    } else {
        
        fafar_cf7crud_before_send_mail_create( $contact_form );

    }

}
