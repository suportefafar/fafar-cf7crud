<?php

/*
 * Checking write permissions
 */
add_filter( 'fafar_cf7crud_before_update', 'fafar_cf7crud_check_for_update_permission', 1, 1 );


function fafar_cf7crud_check_for_update_permission( $new_data ) {

    $submission_decoded = fafar_cf7crud_get_submission_by_id( $new_data['id'] );

    if( ! fafar_cf7crud_check_write_permission( $submission_decoded ) ) {

        return array( 'error_msg' => 'Permission denied for updating!', 'far_prevent_submit' => true );

    }

    return $new_data;

}