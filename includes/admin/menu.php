<?php
/**
 * Admin menu for AI Suite.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'ai_suite_admin_menu' ) ) {
    /**
     * Register the plugin menu in WP admin.
     */
    function ai_suite_admin_menu() {
        add_menu_page(
            __( 'AI Suite', 'ai-suite' ),
            __( 'AI Suite', 'ai-suite' ),
            ( function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options' ),
            'ai-suite',
            'ai_suite_render_admin_page',
            'dashicons-robot',
            58
        );
    }
    add_action( 'admin_menu', 'ai_suite_admin_menu' );
}