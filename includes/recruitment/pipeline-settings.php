<?php
/**
 * AI Suite – Pipeline Settings (Enterprise)
 *
 * Permite companiei:
 * - redenumire coloane pipeline (statusuri standard)
 * - ascundere statusuri (ex: rejected)
 *
 * Datele sunt salvate în post_meta pe companie:
 * - _company_pipeline_labels (array status_key => label)
 * - _company_pipeline_hidden (array of keys)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'ai_suite_pipeline_default_labels' ) ) {
    function ai_suite_pipeline_default_labels() {
        // Default status labels (fallback)
        return array(
            'nou'        => __( 'Nou', 'ai-suite' ),
            'in_analiza' => __( 'În evaluare', 'ai-suite' ),
            'interviu'   => __( 'Interviu', 'ai-suite' ),
            'acceptat'   => __( 'Acceptat', 'ai-suite' ),
            'respins'    => __( 'Respins', 'ai-suite' ),
        );
    }
}

if ( ! function_exists( 'ai_suite_get_company_pipeline_settings' ) ) {
    function ai_suite_get_company_pipeline_settings( $company_id ) {
        $company_id = absint( $company_id );
        $defaults = ai_suite_pipeline_default_labels();

        $labels = get_post_meta( $company_id, '_company_pipeline_labels', true );
        $hidden = get_post_meta( $company_id, '_company_pipeline_hidden', true );

        if ( ! is_array( $labels ) ) $labels = array();
        if ( ! is_array( $hidden ) ) $hidden = array();

        // sanitize keys
        $out_labels = array();
        foreach ( $defaults as $k => $v ) {
            $val = isset( $labels[ $k ] ) ? (string) $labels[ $k ] : (string) $v;
            $val = wp_strip_all_tags( $val );
            $val = trim( $val );
            if ( $val === '' ) $val = (string) $v;
            $out_labels[ $k ] = $val;
        }

        $out_hidden = array();
        foreach ( $hidden as $k ) {
            $k = sanitize_key( $k );
            if ( isset( $defaults[ $k ] ) ) $out_hidden[] = $k;
        }

        return array(
            'labels' => $out_labels,
            'hidden' => array_values( array_unique( $out_hidden ) ),
        );
    }
}

if ( ! function_exists( 'ai_suite_save_company_pipeline_settings' ) ) {
    function ai_suite_save_company_pipeline_settings( $company_id, $labels, $hidden ) {
        $company_id = absint( $company_id );
        if ( ! $company_id ) return false;

        $defaults = ai_suite_pipeline_default_labels();
        if ( ! is_array( $labels ) ) $labels = array();
        if ( ! is_array( $hidden ) ) $hidden = array();

        $out_labels = array();
        foreach ( $defaults as $k => $v ) {
            if ( isset( $labels[ $k ] ) ) {
                $val = wp_strip_all_tags( (string) $labels[ $k ] );
                $val = trim( $val );
                if ( $val !== '' ) {
                    $out_labels[ $k ] = $val;
                }
            }
        }

        $out_hidden = array();
        foreach ( $hidden as $k ) {
            $k = sanitize_key( $k );
            if ( isset( $defaults[ $k ] ) ) $out_hidden[] = $k;
        }

        update_post_meta( $company_id, '_company_pipeline_labels', $out_labels );
        update_post_meta( $company_id, '_company_pipeline_hidden', array_values( array_unique( $out_hidden ) ) );

        return true;
    }
}

if ( ! function_exists( 'ai_suite_pipeline_labels_for_company' ) ) {
    function ai_suite_pipeline_labels_for_company( $company_id ) {
        $settings = ai_suite_get_company_pipeline_settings( $company_id );
        return isset( $settings['labels'] ) ? (array) $settings['labels'] : ai_suite_pipeline_default_labels();
    }
}

if ( ! function_exists( 'ai_suite_pipeline_hidden_for_company' ) ) {
    function ai_suite_pipeline_hidden_for_company( $company_id ) {
        $settings = ai_suite_get_company_pipeline_settings( $company_id );
        return isset( $settings['hidden'] ) ? (array) $settings['hidden'] : array();
    }
}

// AJAX: get / save settings
add_action( 'wp_ajax_ai_suite_pipeline_settings_get', function() {
    if ( function_exists( 'ai_suite_portal_ajax_guard' ) ) {
        ai_suite_portal_ajax_guard( 'company' );
    }

    $company_id = 0;
    if ( function_exists( 'AI_Suite_Portal_Frontend' ) ) {
        $uid = AI_Suite_Portal_Frontend::effective_user_id();
        $company_id = AI_Suite_Portal_Frontend::get_company_id_for_user( $uid );
    }
    if ( ! $company_id ) $company_id = (int) get_user_meta( get_current_user_id(), '_ai_suite_company_id', true );

    if ( ! $company_id ) {
        wp_send_json( array( 'ok' => false, 'message' => __( 'Companie lipsă.', 'ai-suite' ) ) );
    }

    if ( function_exists( 'ai_suite_company_members_can_manage' ) ) {
        if ( ! ai_suite_company_members_can_manage( $company_id ) ) {
            wp_send_json( array( 'ok' => false, 'message' => __( 'Nu ai drepturi.', 'ai-suite' ) ), 403 );
        }
    } else {
        if ( ! current_user_can( 'manage_options' ) && ! ( function_exists( 'aisuite_current_user_is_company' ) && aisuite_current_user_is_company() ) ) {
            wp_send_json( array( 'ok' => false, 'message' => __( 'Nu ai drepturi.', 'ai-suite' ) ), 403 );
        }
    }

    $defaults = ai_suite_pipeline_default_labels();
    $settings = ai_suite_get_company_pipeline_settings( $company_id );

    wp_send_json( array(
        'ok'       => true,
        'defaults' => $defaults,
        'labels'   => $settings['labels'],
        'hidden'   => $settings['hidden'],
    ) );
} );

add_action( 'wp_ajax_ai_suite_pipeline_settings_save', function() {
    if ( function_exists( 'ai_suite_portal_ajax_guard' ) ) {
        ai_suite_portal_ajax_guard( 'company' );
    }

    $labels = isset( $_POST['labels'] ) ? (array) $_POST['labels'] : array();
    $hidden = isset( $_POST['hidden'] ) ? (array) $_POST['hidden'] : array();

    // sanitize deep arrays
    $labels_s = array();
    foreach ( $labels as $k => $v ) {
        $labels_s[ sanitize_key( $k ) ] = wp_strip_all_tags( (string) wp_unslash( $v ) );
    }
    $hidden_s = array();
    foreach ( $hidden as $k ) {
        $hidden_s[] = sanitize_key( (string) wp_unslash( $k ) );
    }

    $company_id = 0;
    if ( function_exists( 'AI_Suite_Portal_Frontend' ) ) {
        $uid = AI_Suite_Portal_Frontend::effective_user_id();
        $company_id = AI_Suite_Portal_Frontend::get_company_id_for_user( $uid );
    }
    if ( ! $company_id ) $company_id = (int) get_user_meta( get_current_user_id(), '_ai_suite_company_id', true );

    if ( ! $company_id ) {
        wp_send_json( array( 'ok' => false, 'message' => __( 'Companie lipsă.', 'ai-suite' ) ) );
    }

    if ( function_exists( 'ai_suite_company_members_can_manage' ) ) {
        if ( ! ai_suite_company_members_can_manage( $company_id ) ) {
            wp_send_json( array( 'ok' => false, 'message' => __( 'Nu ai drepturi.', 'ai-suite' ) ), 403 );
        }
    } else {
        if ( ! current_user_can( 'manage_options' ) && ! ( function_exists( 'aisuite_current_user_is_company' ) && aisuite_current_user_is_company() ) ) {
            wp_send_json( array( 'ok' => false, 'message' => __( 'Nu ai drepturi.', 'ai-suite' ) ), 403 );
        }
    }

    ai_suite_save_company_pipeline_settings( $company_id, $labels_s, $hidden_s );

    wp_send_json( array( 'ok' => true ) );
} );
