<?php
/**
 * Uninstall callback for AI Suite.
 *
 * Removes options and scheduled events.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove plugin options.
delete_option( 'ai_suite_settings' );
delete_option( 'ai_suite_registry' );
delete_option( 'ai_suite_logs' );
delete_option( 'ai_suite_runs' );
delete_option( 'ai_suite_installed_ver' );
delete_option( 'ai_suite_fb_settings' );

// Clear scheduled events.
wp_clear_scheduled_hook( 'ai_suite_cron_48h' );
wp_clear_scheduled_hook( 'aisuite_featured_jobs_cleanup' );

// Drop Facebook leads table (best-effort).
global $wpdb;
if ( isset( $wpdb ) && is_object( $wpdb ) ) {
    $t = $wpdb->prefix . 'ai_suite_fb_leads';
    $wpdb->query( "DROP TABLE IF EXISTS {$t}" );
}
