<?php
/*
 * Plugin Name:       FAFAR Contact Form 7 CRUD
 * Plugin URI:        https://github.com/suportefafar/fafar-cf7crud
 * Description:       Salve e edite submissões do Contact Form 7. Nunca perca dados importantes. O plugin FAFAR Contact Form 7 CRUD é um complemento para o plugin Contact Form 7.
 * Version:           1.0.0.1
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

/*
 * This protect the plugin file from direct access
*/
if ( ! defined( 'WPINC' ) ) die;

if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * Define Constants
*/
define( 'FAFAR_CF7CRUD_DIR', trailingslashit( plugin_dir_path(__FILE__) ) );
define( 'FAFAR_CF7_FILE_PREFIX', 'fafar-cf7crud-file-' );


add_action('wp_enqueue_scripts', 'fafar_cf7crud_callback_for_setting_up_scripts');


register_activation_hook( __FILE__, 'fafar_cf7crud_on_activate' );


add_action( 'upgrader_process_complete', 'fafar_cf7crud_upgrade_function', 10, 2 );


register_deactivation_hook( __FILE__, 'fafar_cf7crud_on_deactivate' );


function fafar_cf7crud_callback_for_setting_up_scripts() {

    wp_register_style('fafar-cf7crud', plugins_url( 'css/main.css', __FILE__ ) );

    wp_enqueue_style( 'fafar-cf7crud' );

    wp_register_script( 'fafar-cf7crud', plugins_url( 'js/main.js', __FILE__ ) );

    wp_enqueue_script( 'fafar-cf7crud' );

}


function fafar_cf7crud_create_table(){

    global $wpdb;
    $cfdb       = apply_filters( 'fafar_cf7crud_database', $wpdb );
    $table_name = $cfdb->prefix . 'fafar_cf7crud_submissions';

    if( $cfdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name ) {

        $charset_collate = $cfdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id VARCHAR(255) NOT NULL,
            form_id INT(20) NOT NULL,
            object_name VARCHAR(50),
            data JSON NOT NULL,
            is_active INT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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

    $cfdb = apply_filters( 'fafar_cf7crud_database', $wpdb ); // Caso queira mudar o banco de dados
    
    if ( is_multisite() && $network_wide ) {
        // Get all blogs in the network and activate plugin on each one
        $blog_ids = $cfdb->get_col( "SELECT blog_id FROM $cfdb->blogs" );
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


function fafar_cf7crud_on_deactivate() {

	// Remove custom capability from all roles
	global $wp_roles;

	foreach( array_keys( $wp_roles->roles ) as $role ) {
		$wp_roles->remove_cap( $role, 'fafar_cf7crud_access' );
	}
}


/*
 * Import router module
*/
require_once FAFAR_CF7CRUD_DIR . 'modules/router.php';