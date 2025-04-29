<?php
/**
 * Plugin Name: WPost Generator
 * Description: Posts automaticos usando a AI
 * Version: 1.0
 * Author: John Amorim - WPlugin
 * Text Domain: wpost-generator
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

define( 'OAPG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OAPG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OAPG_DEBUG', true ); 

require_once OAPG_PLUGIN_DIR . 'includes/helpers.php';
require_once OAPG_PLUGIN_DIR . 'includes/api-integration.php';
require_once OAPG_PLUGIN_DIR . 'includes/cron-jobs.php';
require_once OAPG_PLUGIN_DIR . 'includes/admin-settings.php';

if ( defined( 'OAPG_DEBUG' ) && OAPG_DEBUG === true ) {
    require_once OAPG_PLUGIN_DIR . 'includes/debug-interface.php';
}

register_activation_hook( __FILE__, 'oapg_activate_plugin' );
function oapg_activate_plugin() {
    if ( ! wp_next_scheduled( 'oapg_generate_posts_event' ) ) {
        wp_schedule_event( time(), 'oapg_custom', 'oapg_generate_posts_event' );
    }
    
    $log_dir = OAPG_PLUGIN_DIR . 'logs';
    if ( ! file_exists( $log_dir ) ) {
        wp_mkdir_p( $log_dir );
    }

    if (get_option('oapg_ultimo_keyword_index') === false) {
        update_option('oapg_ultimo_keyword_index', -1);
    }
}

register_deactivation_hook( __FILE__, 'oapg_deactivate_plugin' );
function oapg_deactivate_plugin() {
    wp_clear_scheduled_hook( 'oapg_generate_posts_event' );
}
