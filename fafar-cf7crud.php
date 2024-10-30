<?php
/*
 * Plugin Name:       FAFAR Contact Form 7 CRUD
 * Plugin URI:        https://github.com/suportefafar/fafar-cf7crud
 * Description:       Salve e edite submissões do Contact Form 7. Nunca perca dados importantes. O plugin FAFAR Contact Form 7 CRUD é um complemento para o plugin Contact Form 7.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Suporte FAFAR UFMG
 * Author URI:        https://github.com/suportefafar
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://github.com/suportefafar/fafar-cf7crud
 * Text Domain:       fafar-cf7crud
 * Domain Path:       /languages
 * Requires Plugins:  WPCF7
 * GitHub Plugin URI: https://github.com/suportefafar/fafar-cf7crud
*/

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


/*
 * Define Constants
*/
define( 'FAFAR_CF7CRUD_DIR', trailingslashit( plugin_dir_path(__FILE__) ) );
define( 'FAFAR_CF7_FILE_PREFIX', 'fafar-cf7crud-file-' );

add_action('wp_enqueue_scripts', 'fafar_cf7crud_callback_for_setting_up_scripts');

register_activation_hook( __FILE__, 'fafar_cf7crud_on_activate' );

add_action( 'upgrader_process_complete', 'fafar_cf7crud_upgrade_function', 10, 2 );


function fafar_cf7crud_callback_for_setting_up_scripts() {

    wp_enqueue_style( 'fafar-cf7crud', plugin_dir_url( __FILE__ ) . 'public/css/fafar-cf7crud-public.css', array(), false, 'all' );

    wp_enqueue_script( 'fafar-cf7crud', plugin_dir_url( __FILE__ ) . 'public/js/fafar-cf7crud-public.js', array( 'jquery' ), false, false );

}


function fafar_cf7crud_create_table() {

    global $wpdb;
    /*
     * If other database should be used.
     */
    $fafar_cf7crud_db = apply_filters( 'fafar_cf7crud_set_database', $wpdb );
    $table_name       = $fafar_cf7crud_db->prefix . 'fafar_cf7crud_submissions';

    if( $fafar_cf7crud_db->get_var("SHOW TABLES LIKE '$table_name'") != $table_name ) {

        $charset_collate = $fafar_cf7crud_db->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id             VARCHAR(255) NOT NULL,
            data           JSON NOT NULL,
            form_id        VARCHAR(255) NOT NULL,
            object_name    VARCHAR(255),
            is_active      VARCHAR(255) NOT NULL DEFAULT '1',
            owner          VARCHAR(255),
            group_owner    VARCHAR(255),
            permissions    VARCHAR(255) NOT NULL DEFAULT '777',
            remote_ip      VARCHAR(255),
            submission_url VARCHAR(255),
            updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    $upload_dir    = wp_upload_dir();
    $fafar_cf7crud_dirname = $upload_dir['basedir'].'/fafar-cf7crud-uploads';
    if ( ! file_exists( $fafar_cf7crud_dirname ) ) {
        wp_mkdir_p( $fafar_cf7crud_dirname );
        $fp = fopen( $fafar_cf7crud_dirname.'/index.php', 'w');
        fwrite($fp, "<?php \n\t // Silence is golden.");
        fclose( $fp );
    }
    add_option( 'fafar_cf7crud_view_install_date', date('Y-m-d G:i:s'), '', 'yes');

}


function fafar_cf7crud_on_activate( $network_wide ){

    global $wpdb;

    /*
     * If other database should be used.
     */
    $fafar_cf7crud_db = apply_filters( 'fafar_cf7crud_set_database', $wpdb );
    $table_name       = $fafar_cf7crud_db->prefix . 'fafar_cf7crud_submissions';
    
    if ( is_multisite() && $network_wide ) {
        // Get all blogs in the network and activate plugin on each one
        $blog_ids = $fafar_cf7crud_db->get_col( "SELECT blog_id FROM $fafar_cf7crud_db->blogs" );
        foreach ( $blog_ids as $blog_id ) {
            switch_to_blog( $blog_id );
            fafar_cf7crud_create_table();
            restore_current_blog();
        }
    } else {
        fafar_cf7crud_create_table();
    }

}


function fafar_cf7crud_upgrade_function( $upgrader_object, $options ) {

    $upload_dir    = wp_upload_dir();
    $fafar_cf7crud_dirname = $upload_dir['basedir'].'/fafar-cf7crud-uploads';

    if ( file_exists( $fafar_cf7crud_dirname.'/index.php' ) ) return;
        
    if ( file_exists( $fafar_cf7crud_dirname ) ) {
        $fp = fopen( $fafar_cf7crud_dirname.'/index.php', 'w');
        fwrite($fp, "<?php \n\t // Silence is golden.");
        fclose( $fp );
    }

}


/*
 * Import general snippets
*/
require_once FAFAR_CF7CRUD_DIR . 'modules/general.php';

/*
 * Import router module
*/
require_once FAFAR_CF7CRUD_DIR . 'modules/router.php';
