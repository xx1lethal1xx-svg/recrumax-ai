<?php
/**
 * AI Suite – Candidates Core (Enterprise ready)
 *
 * Acest fișier a fost introdus pentru:
 * - a oferi un punct central pentru extensii pe candidați (meta schema, validări, export, AI)
 * - a evita lipsa include-ului din loader.php (stabilitate)
 *
 * În patch-urile următoare putem extinde aici:
 * - parsing CV (AI)
 * - scoring & matching
 * - import/export avansat
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'ai_suite_candidate_meta_schema' ) ) {
    function ai_suite_candidate_meta_schema() {
        /**
         * Meta standard pentru rmax_candidate. Cheile sunt folosite în UI și în index.
         */
        return apply_filters( 'ai_suite_candidate_meta_schema', array(
            'email'    => '_candidate_email',
            'phone'    => '_candidate_phone',
            'location' => '_candidate_location',
            'skills'   => '_candidate_skills',
            'cv_id'    => '_candidate_cv',
            'user_id'  => '_candidate_user_id',
        ) );
    }
}

if ( ! function_exists( 'ai_suite_candidate_update_meta' ) ) {
    function ai_suite_candidate_update_meta( $candidate_id, $data ) {
        $candidate_id = absint( $candidate_id );
        if ( ! $candidate_id || get_post_type( $candidate_id ) !== 'rmax_candidate' ) return false;
        if ( ! is_array( $data ) ) return false;

        $schema = ai_suite_candidate_meta_schema();

        if ( isset( $data['email'] ) ) {
            update_post_meta( $candidate_id, $schema['email'], sanitize_email( $data['email'] ) );
        }
        if ( isset( $data['phone'] ) ) {
            update_post_meta( $candidate_id, $schema['phone'], sanitize_text_field( $data['phone'] ) );
        }
        if ( isset( $data['location'] ) ) {
            update_post_meta( $candidate_id, $schema['location'], sanitize_text_field( $data['location'] ) );
        }
        if ( isset( $data['skills'] ) ) {
            update_post_meta( $candidate_id, $schema['skills'], sanitize_text_field( $data['skills'] ) );
        }

        // Keep candidate index in sync if present.
        if ( function_exists( 'ai_suite_candidate_index_upsert' ) ) {
            ai_suite_candidate_index_upsert( $candidate_id );
        }

        return true;
    }
}
