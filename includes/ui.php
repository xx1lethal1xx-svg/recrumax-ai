<?php
/**
 * AI Suite – UI Helpers (Admin + Portal)
 *
 * Purpose:
 * - keep markup consistent across tabs/pages
 * - make it easy to add new modules with a clean, premium layout
 *
 * NOTE: This file contains only helper functions and safe hooks.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'ai_suite_ui_tabs' ) ) {
    /**
     * Allow modules to add admin tabs.
     *
     * @param array $tabs
     * @return array
     */
    function ai_suite_ui_tabs( $tabs ) {
        /**
         * Filter: ai_suite_admin_tabs
         *
         * Usage:
         * add_filter('ai_suite_admin_tabs', function($tabs){
         *   $tabs['new_tab'] = ['label' => 'Nou'];
         *   return $tabs;
         * });
         */
        return apply_filters( 'ai_suite_admin_tabs', $tabs );
    }
}

if ( ! function_exists( 'ai_suite_ui_card_open' ) ) {
    function ai_suite_ui_card_open( $title = '', $subtitle = '' ) {
        echo '<section class="ais-card">';
        if ( $title !== '' ) {
            echo '<div class="ais-card__head">';
            echo '<h2 class="ais-h2">' . esc_html( $title ) . '</h2>';
            if ( $subtitle !== '' ) {
                echo '<p class="ais-muted">' . esc_html( $subtitle ) . '</p>';
            }
            echo '</div>';
        }
        echo '<div class="ais-card__body">';
    }
}

if ( ! function_exists( 'ai_suite_ui_card_close' ) ) {
    function ai_suite_ui_card_close() {
        echo '</div></section>';
    }
}

if ( ! function_exists( 'ai_suite_ui_notice' ) ) {
    function ai_suite_ui_notice( $type, $message ) {
        $type = in_array( $type, array( 'ok', 'warn', 'err', 'info' ), true ) ? $type : 'info';
        echo '<div class="ais-notice ais-notice--' . esc_attr( $type ) . '">';
        echo wp_kses_post( $message );
        echo '</div>';
    }
}


// === Toolbar & Global Search (v3.6.0) ===
if ( ! function_exists( 'ai_suite_ui_user_can_recruitment' ) ) {
    function ai_suite_ui_user_can_recruitment() {
        if ( current_user_can( 'manage_ai_suite' ) ) {
            return true;
        }
        if ( function_exists( 'aisuite_current_user_is_manager' ) && aisuite_current_user_is_manager() ) {
            return true;
        }
        if ( function_exists( 'aisuite_current_user_is_recruiter' ) && aisuite_current_user_is_recruiter() ) {
            return true;
        }
        return false;
    }
}

if ( ! function_exists( 'ai_suite_ui_toolbar' ) ) {
    /**
     * Renders a consistent toolbar for ALL admin tabs:
     * - global search (AJAX)
     * - refresh
     * - contextual exports (CSV/JSON)
     * - quick links to portal pages
     *
     * @param string $active_tab
     */
    function ai_suite_ui_toolbar( $active_tab = '' ) {
        if ( ! ai_suite_ui_user_can_recruitment() ) {
            return;
        }

        $active_tab = sanitize_key( (string) $active_tab );

        $exports = array(
            'jobs'          => array( 'action' => 'ai_suite_export_jobs_csv',          'label' => __( 'Export Joburi CSV', 'ai-suite' ) ),
            'candidates'    => array( 'action' => 'ai_suite_export_candidates_csv',    'label' => __( 'Export Candidați CSV', 'ai-suite' ) ),
            'applications'  => array( 'action' => 'ai_suite_export_applications_csv',  'label' => __( 'Export Aplicații CSV', 'ai-suite' ) ),
            'companies'     => array( 'action' => 'ai_suite_export_companies_csv',     'label' => __( 'Export Companii CSV', 'ai-suite' ) ),
            'facebook_leads'=> array( 'action' => 'ai_suite_export_fb_leads_csv',      'label' => __( 'Export Leads CSV', 'ai-suite' ) ),
            'ai_queue'      => array( 'action' => 'ai_suite_export_ai_queue_csv',     'label' => __( 'Export Coada AI CSV', 'ai-suite' ) ),
            'logs'          => array( 'action' => 'ai_suite_export_logs_json',         'label' => __( 'Export Loguri JSON', 'ai-suite' ) ),
            'runs'          => array( 'action' => 'ai_suite_export_runs_csv',          'label' => __( 'Export Rulări CSV', 'ai-suite' ) ),
        );

        $export = isset( $exports[ $active_tab ] ) ? $exports[ $active_tab ] : null;

        $base_admin = admin_url( 'admin.php?page=ai-suite&tab=' . rawurlencode( $active_tab ) );
        $portal_jobs = function_exists( 'home_url' ) ? home_url( '/joburi/' ) : '';
        $portal_cand = function_exists( 'home_url' ) ? home_url( '/inregistrare-candidat/' ) : '';
        $portal_comp = function_exists( 'home_url' ) ? home_url( '/inregistrare-companie/' ) : '';

        $safe_mode = function_exists( 'aisuite_is_safe_mode' ) && aisuite_is_safe_mode();

        echo '<div class="ais-toolbar" role="region" aria-label="' . esc_attr__( 'Acțiuni rapide', 'ai-suite' ) . '">';
          echo '<div class="ais-toolbar__left">';
            echo '<div class="ais-search">';
              echo '<input type="search" id="ais-global-search" class="ais-input" placeholder="' . esc_attr__( 'Caută rapid (joburi, candidați, aplicații, companii)...', 'ai-suite' ) . '" autocomplete="off" />';
              echo '<div id="ais-global-search-pop" class="ais-searchpop" aria-hidden="true"></div>';
            echo '</div>';

            echo '<a class="button ais-btn" href="' . esc_url( $base_admin ) . '"><span class="dashicons dashicons-update"></span> ' . esc_html__( 'Refresh', 'ai-suite' ) . '</a>';

            if ( $export && ! empty( $export['action'] ) ) {
                $export_url = wp_nonce_url( admin_url( 'admin-post.php?action=' . $export['action'] ), $export['action'] );
                echo '<a class="button ais-btn" href="' . esc_url( $export_url ) . '"><span class="dashicons dashicons-download"></span> ' . esc_html( $export['label'] ) . '</a>';
            }

            if ( $safe_mode ) {
                $tools_url = admin_url( 'admin.php?page=ai-suite&tab=tools' );
                echo '<a class="button ais-btn ais-btn--warn" href="' . esc_url( $tools_url ) . '"><span class="dashicons dashicons-shield"></span> ' . esc_html__( 'Safe Mode activ', 'ai-suite' ) . '</a>';
            }
          echo '</div>';

          echo '<div class="ais-toolbar__right">';
            if ( $portal_jobs ) {
                echo '<a class="button ais-btn" target="_blank" rel="noopener" href="' . esc_url( $portal_jobs ) . '"><span class="dashicons dashicons-external"></span> ' . esc_html__( 'Joburi public', 'ai-suite' ) . '</a>';
            }
            if ( $portal_cand ) {
                echo '<a class="button ais-btn" target="_blank" rel="noopener" href="' . esc_url( $portal_cand ) . '"><span class="dashicons dashicons-admin-users"></span> ' . esc_html__( 'Candidat', 'ai-suite' ) . '</a>';
            }
            if ( $portal_comp ) {
                echo '<a class="button ais-btn" target="_blank" rel="noopener" href="' . esc_url( $portal_comp ) . '"><span class="dashicons dashicons-building"></span> ' . esc_html__( 'Companie', 'ai-suite' ) . '</a>';
            }
          echo '</div>';
        echo '</div>';
    }
}
