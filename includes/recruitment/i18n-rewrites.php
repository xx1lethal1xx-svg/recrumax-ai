<?php
/**
 * AI Suite – Bilingual (RO/EN) rewrites for Job URLs.
 *
 * Adds RO alias routes (/joburi/) for CPT rmax_job and optionally swaps permalinks
 * depending on locale (ro_* => /joburi/, others => /jobs/).
 *
 * Safe + add-only.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'aisuite_is_ro_locale' ) ) {
    function aisuite_is_ro_locale() {
        // Prefer explicit UI language setting (ro/en/auto) stored in ai_suite_settings.
        $ui_lang = '';
        if ( function_exists( 'aisuite_get_settings' ) ) {
            $s = aisuite_get_settings();
            if ( is_array( $s ) && ! empty( $s['ui_language'] ) ) {
                $ui_lang = (string) $s['ui_language'];
            }
        }
        // Backward compatibility (older installs may use a standalone option).
        if ( ! $ui_lang ) {
            $ui_lang = (string) get_option( 'ai_suite_ui_language', '' );
        }
        $ui_lang = strtolower( trim( $ui_lang ) );

        if ( $ui_lang === 'ro' ) {
            return true;
        }
        if ( $ui_lang === 'en' ) {
            return false;
        }

        // Auto: detect from WP locale.
        $locale = function_exists( 'determine_locale' ) ? determine_locale() : ( function_exists( 'get_locale' ) ? get_locale() : '' );
        $locale = is_string( $locale ) ? $locale : '';
        return ( $locale === 'ro_RO' || strpos( $locale, 'ro_' ) === 0 );
    }
}


if ( ! function_exists( 'aisuite_job_base_slug' ) ) {
    function aisuite_job_base_slug() {
        // Default: EN base.
        $base = aisuite_is_ro_locale() ? 'joburi' : 'jobs';
        /**
         * Filter job base slug (e.g. to align with WPML/Polylang language).
         */
        return apply_filters( 'ai_suite_job_base_slug', $base );
    }
}

if ( ! function_exists( 'aisuite_register_job_ro_alias_rewrites' ) ) {
    function aisuite_register_job_ro_alias_rewrites() {
        // RO alias routes always available.
        add_rewrite_rule( '^joburi/?$', 'index.php?post_type=rmax_job', 'top' );
        add_rewrite_rule( '^joburi/([^/]+)/?$', 'index.php?post_type=rmax_job&name=$matches[1]', 'top' );
    }
    add_action( 'init', 'aisuite_register_job_ro_alias_rewrites', 11 );
}

if ( ! function_exists( 'aisuite_filter_job_permalinks_by_locale' ) ) {
    function aisuite_filter_job_permalinks_by_locale( $post_link, $post, $leavename, $sample ) {
        if ( ! $post || 'rmax_job' !== $post->post_type ) {
            return $post_link;
        }
        $base = aisuite_job_base_slug();
        // If base is jobs and link already contains /jobs/, keep.
        // If base is joburi, swap /jobs/ -> /joburi/.
        $post_link = str_replace( '/' . trailingslashit( 'jobs' ), '/' . trailingslashit( $base ), $post_link );
        return $post_link;
    }
    add_filter( 'post_type_link', 'aisuite_filter_job_permalinks_by_locale', 10, 4 );
}

if ( ! function_exists( 'aisuite_filter_job_archive_link_by_locale' ) ) {
    function aisuite_filter_job_archive_link_by_locale( $link, $post_type ) {
        if ( 'rmax_job' !== $post_type ) {
            return $link;
        }
        $base = aisuite_job_base_slug();
        $link = str_replace( '/' . trailingslashit( 'jobs' ), '/' . trailingslashit( $base ), $link );
        return $link;
    }
    add_filter( 'post_type_archive_link', 'aisuite_filter_job_archive_link_by_locale', 10, 2 );
}

if ( ! function_exists( 'aisuite_disable_canonical_for_job_alias' ) ) {
    function aisuite_disable_canonical_for_job_alias( $redirect_url, $requested_url ) {
        // Prevent WP from redirecting /joburi/* to /jobs/* when RO alias used.
        if ( is_string( $requested_url ) && false !== strpos( $requested_url, '/joburi/' ) ) {
            return false;
        }
        return $redirect_url;
    }
    add_filter( 'redirect_canonical', 'aisuite_disable_canonical_for_job_alias', 10, 2 );
}
