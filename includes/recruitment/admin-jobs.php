<?php
/**
 * Admin handlers for Jobs.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add job action.
add_action( 'admin_post_ai_suite_add_job', function() {
    if ( ! ( function_exists( 'aisuite_current_user_can_manage_recruitment' ) ? aisuite_current_user_can_manage_recruitment() : current_user_can( 'manage_ai_suite' ) ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_add_job' );
    $title  = isset( $_POST['job_title'] ) ? sanitize_text_field( wp_unslash( $_POST['job_title'] ) ) : '';
    $status = isset( $_POST['job_status'] ) ? sanitize_key( wp_unslash( $_POST['job_status'] ) ) : 'open';
    if ( $title ) {
        $post_id = wp_insert_post( array(
            'post_type'   => 'rmax_job',
            'post_title'  => $title,
            'post_status' => 'publish',
        ) );
        if ( $post_id ) {
            update_post_meta( $post_id, '_job_status', $status );
        }
    }
    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=jobs' ) );
    exit;
} );

// Bulk status update for jobs.
add_action( 'admin_post_ai_suite_jobs_bulk_status', function() {
    if ( ! ( function_exists( 'aisuite_current_user_can_manage_recruitment' ) ? aisuite_current_user_can_manage_recruitment() : current_user_can( 'manage_ai_suite' ) ) ) {
        wp_die( __( 'Neautorizat', 'ai-suite' ) );
    }
    check_admin_referer( 'ai_suite_jobs_bulk_status' );
    $ids    = isset( $_POST['job_ids'] ) ? (array) wp_unslash( $_POST['job_ids'] ) : array();
    $status = isset( $_POST['bulk_status'] ) ? sanitize_key( wp_unslash( $_POST['bulk_status'] ) ) : '';
    foreach ( $ids as $id ) {
        $job_id = intval( $id );
        if ( $job_id > 0 ) {
            update_post_meta( $job_id, '_job_status', $status );
        }
    }
    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=jobs' ) );
    exit;
} );