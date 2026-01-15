<?php
/**
 * AI Suite – ATS PRO (Company portal): Candidate search, Shortlist, Pipeline.
 *
 * ADD-ONLY.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'AI_Suite_ATS_Pro' ) ) {
    final class AI_Suite_ATS_Pro {

        public static function boot() {
            // AJAX (logged-in company users only).
            add_action( 'wp_ajax_ai_suite_candidate_search', array( __CLASS__, 'ajax_candidate_search' ) );
            add_action( 'wp_ajax_ai_suite_shortlist_get', array( __CLASS__, 'ajax_shortlist_get' ) );
            add_action( 'wp_ajax_ai_suite_shortlist_add', array( __CLASS__, 'ajax_shortlist_add' ) );
            add_action( 'wp_ajax_ai_suite_shortlist_remove', array( __CLASS__, 'ajax_shortlist_remove' ) );
            add_action( 'wp_ajax_ai_suite_shortlist_update', array( __CLASS__, 'ajax_shortlist_update' ) );

            add_action( 'wp_ajax_ai_suite_pipeline_list', array( __CLASS__, 'ajax_pipeline_list' ) );
            add_action( 'wp_ajax_ai_suite_pipeline_move', array( __CLASS__, 'ajax_pipeline_move' ) );
            add_action( 'wp_ajax_ai_suite_pipeline_bulk_move', array( __CLASS__, 'ajax_pipeline_bulk_move' ) );
            add_action( 'wp_ajax_ai_suite_pipeline_add_note', array( __CLASS__, 'ajax_pipeline_add_note' ) );
            add_action( 'wp_ajax_ai_suite_pipeline_activity', array( __CLASS__, 'ajax_pipeline_activity' ) );

            // PATCH51: Advanced bulk actions + Drawer details
            add_action( 'wp_ajax_ai_suite_pipeline_get', array( __CLASS__, 'ajax_pipeline_get' ) );
            add_action( 'wp_ajax_ai_suite_pipeline_team_list', array( __CLASS__, 'ajax_pipeline_team_list' ) );
            add_action( 'wp_ajax_ai_suite_pipeline_bulk_assign', array( __CLASS__, 'ajax_pipeline_bulk_assign' ) );
            add_action( 'wp_ajax_ai_suite_pipeline_bulk_tag', array( __CLASS__, 'ajax_pipeline_bulk_tag' ) );
            add_action( 'wp_ajax_ai_suite_pipeline_bulk_schedule', array( __CLASS__, 'ajax_pipeline_bulk_schedule' ) );
            add_action( 'wp_ajax_ai_suite_pipeline_bulk_email', array( __CLASS__, 'ajax_pipeline_bulk_email' ) );
            // Exports (CSV) – gated by plan feature "exports"
            add_action( 'wp_ajax_ai_suite_export_shortlist_csv', array( __CLASS__, 'ajax_export_shortlist_csv' ) );
            add_action( 'wp_ajax_ai_suite_export_pipeline_csv', array( __CLASS__, 'ajax_export_pipeline_csv' ) );
            // PATCH55: KPI per recruiter + Saved Views + Smart Search
            add_action( 'wp_ajax_ai_suite_ats_kpi_recruiters', array( __CLASS__, 'ajax_kpi_recruiters' ) );
            add_action( 'wp_ajax_ai_suite_ats_saved_views_get', array( __CLASS__, 'ajax_saved_views_get' ) );
            add_action( 'wp_ajax_ai_suite_ats_saved_views_save', array( __CLASS__, 'ajax_saved_views_save' ) );
            add_action( 'wp_ajax_ai_suite_ats_saved_views_delete', array( __CLASS__, 'ajax_saved_views_delete' ) );
            add_action( 'wp_ajax_ai_suite_ats_smart_search', array( __CLASS__, 'ajax_smart_search' ) );
        }

        // -----------------
        // Security helpers
        // -----------------

        private static function json_error( $msg, $code = 400 ) {
            wp_send_json( array( 'ok' => false, 'message' => (string) $msg ), $code );
        }

        private static function json_ok( $data = array() ) {
            if ( ! is_array( $data ) ) { $data = array( 'data' => $data ); }
            $data['ok'] = true;
            wp_send_json( $data );
        }

        private static function require_company_context() {
            if ( ! is_user_logged_in() ) {
                self::json_error( __( 'Neautorizat.', 'ai-suite' ), 401 );
            }
            $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
            if ( ! $nonce || ! wp_verify_nonce( $nonce, 'ai_suite_portal_nonce' ) ) {
                self::json_error( __( 'Nonce invalid.', 'ai-suite' ), 403 );
            }
            $uid = function_exists( 'ai_suite_portal_effective_user_id' ) ? ai_suite_portal_effective_user_id() : get_current_user_id();
            $is_company = false;
            if ( function_exists( 'aisuite_user_has_role' ) ) {
                $is_company = aisuite_user_has_role( $uid, 'aisuite_company' );
            } elseif ( function_exists( 'aisuite_current_user_is_company' ) && (int) $uid === (int) get_current_user_id() ) {
                $is_company = aisuite_current_user_is_company();
            }
            if ( ! $is_company && ! current_user_can( 'manage_options' ) ) {
                self::json_error( __( 'Doar conturile de companie pot folosi acest modul.', 'ai-suite' ), 403 );
            }
            if ( ! class_exists( 'AI_Suite_Portal_Frontend' ) ) {
                self::json_error( __( 'Portal indisponibil.', 'ai-suite' ), 500 );
            }
            $company_id = AI_Suite_Portal_Frontend::get_company_id_for_user( $uid );
            if ( ! $company_id || get_post_type( $company_id ) !== 'rmax_company' ) {
                self::json_error( __( 'Profil companie lipsă.', 'ai-suite' ), 404 );
            }

            // Premium gate: ATS board actions require Pro/Enterprise (feature: ats)
            if ( function_exists( 'ai_suite_company_has_feature' ) && ! ai_suite_company_has_feature( $company_id, 'ats' ) ) {
                self::json_error( __( 'ATS este disponibil doar pe planurile Pro/Enterprise. Upgrade required.', 'ai-suite' ), 402 );
            }
            return $company_id;
        }

        private static function current_effective_user_id() {
            if ( function_exists( 'ai_suite_portal_effective_user_id' ) ) {
                return absint( ai_suite_portal_effective_user_id() );
            }
            return get_current_user_id();
        }

        private static function get_saved_views_key( $company_id ) {
            return 'ai_suite_ats_saved_views_' . absint( $company_id );
        }

        private static function sanitize_saved_view_filters( $filters ) {
            $filters = is_array( $filters ) ? $filters : array();
            return array(
                'jobId'       => isset( $filters['jobId'] ) ? sanitize_text_field( wp_unslash( $filters['jobId'] ) ) : '',
                'recruiterId' => isset( $filters['recruiterId'] ) ? sanitize_text_field( wp_unslash( $filters['recruiterId'] ) ) : '',
                'tag'         => isset( $filters['tag'] ) ? sanitize_text_field( wp_unslash( $filters['tag'] ) ) : '',
                'mine'        => ! empty( $filters['mine'] ) ? 1 : 0,
                'group'       => ! empty( $filters['group'] ) ? 1 : 0,
                'swim'        => ! empty( $filters['swim'] ) ? 1 : 0,
            );
        }

        private static function get_accept_status_keys() {
            $statuses = function_exists( 'ai_suite_app_statuses' ) ? (array) ai_suite_app_statuses() : array();
            $keys = array();
            foreach ( array( 'acceptat', 'accepted', 'hired', 'offer' ) as $k ) {
                if ( isset( $statuses[ $k ] ) ) {
                    $keys[] = $k;
                }
            }
            return $keys ? $keys : array( 'acceptat', 'accepted' );
        }

        private static function get_app_accept_time( $app_id, array $accept_keys ) {
            $history = get_post_meta( $app_id, '_application_status_history', true );
            if ( ! is_array( $history ) ) {
                return 0;
            }
            $time = 0;
            foreach ( $history as $entry ) {
                if ( empty( $entry['to'] ) ) {
                    continue;
                }
                $to = sanitize_key( (string) $entry['to'] );
                if ( in_array( $to, $accept_keys, true ) ) {
                    $time = max( $time, absint( $entry['time'] ) );
                }
            }
            return $time;
        }

        private static function get_company_team_users( $company_id ) {
            $users = array();
            if ( function_exists( 'ai_suite_company_members_get' ) ) {
                $rows = ai_suite_company_members_get( $company_id );
                foreach ( (array) $rows as $row ) {
                    if ( empty( $row['user_id'] ) || ( isset( $row['status'] ) && $row['status'] !== 'active' ) ) {
                        continue;
                    }
                    $users[] = absint( $row['user_id'] );
                }
            }
            if ( empty( $users ) ) {
                $owner = absint( get_post_meta( $company_id, '_company_owner_user', true ) );
                if ( $owner ) {
                    $users[] = $owner;
                }
            }
            return array_values( array_unique( array_filter( $users ) ) );
        }

        private static function get_team_user_label( $user_id ) {
            $user = get_user_by( 'id', $user_id );
            if ( ! $user ) {
                return array( 'name' => 'User #' . absint( $user_id ), 'email' => '' );
            }
            $name = $user->display_name ? $user->display_name : trim( $user->user_firstname . ' ' . $user->user_lastname );
            if ( ! $name ) {
                $name = $user->user_email;
            }
            return array( 'name' => $name, 'email' => $user->user_email );
        }

        private static function maybe_log( $level, $message, $context = array() ) {
            if ( function_exists( 'aisuite_log' ) ) {
                aisuite_log( $level, $message, is_array( $context ) ? $context : array() );
            }
        }

        public static function ajax_kpi_recruiters() {
            $company_id = self::require_company_context();
            $cache_key = 'ai_suite_kpi_recruiter_' . absint( $company_id );
            $cached = get_transient( $cache_key );
            if ( is_array( $cached ) ) {
                self::json_ok( array( 'kpis' => $cached, 'cached' => true ) );
            }

            $team_ids = self::get_company_team_users( $company_id );
            if ( empty( $team_ids ) ) {
                self::json_ok( array( 'kpis' => array(), 'cached' => false ) );
            }

            $job_ids = self::company_job_ids( $company_id );
            $apps = array();
            if ( $job_ids ) {
                $apps = get_posts( array(
                    'post_type'      => 'rmax_application',
                    'post_status'    => 'publish',
                    'posts_per_page' => 300,
                    'fields'         => 'ids',
                    'meta_query'     => array(
                        array(
                            'key'     => '_application_job_id',
                            'value'   => array_map( 'strval', array_map( 'absint', $job_ids ) ),
                            'compare' => 'IN',
                        ),
                    ),
                ) );
            }

            $statuses = function_exists( 'ai_suite_app_statuses' ) ? (array) ai_suite_app_statuses() : array();
            $accept_keys = self::get_accept_status_keys();
            $rows = array();
            $map = array();
            foreach ( $team_ids as $uid ) {
                $label = self::get_team_user_label( $uid );
                $rows[ $uid ] = array(
                    'userId'       => $uid,
                    'name'         => $label['name'],
                    'email'        => $label['email'],
                    'total'        => 0,
                    'accepted'     => 0,
                    'acceptRate'   => 0,
                    'timeToHire'   => 0,
                    'stageCounts'  => array(),
                );
                $map[ $uid ] = 0;
            }

            $time_acc = array();
            foreach ( $team_ids as $uid ) {
                $time_acc[ $uid ] = array( 'sum' => 0, 'count' => 0 );
            }

            foreach ( (array) $apps as $app_id ) {
                $assigned = absint( get_post_meta( $app_id, '_application_assigned_user', true ) );
                if ( ! $assigned || ! isset( $rows[ $assigned ] ) ) {
                    continue;
                }
                $rows[ $assigned ]['total']++;
                $status = sanitize_key( (string) get_post_meta( $app_id, '_application_status', true ) );
                if ( $status ) {
                    if ( ! isset( $rows[ $assigned ]['stageCounts'][ $status ] ) ) {
                        $rows[ $assigned ]['stageCounts'][ $status ] = 0;
                    }
                    $rows[ $assigned ]['stageCounts'][ $status ]++;
                }

                if ( in_array( $status, $accept_keys, true ) ) {
                    $rows[ $assigned ]['accepted']++;
                    $accept_time = self::get_app_accept_time( $app_id, $accept_keys );
                    if ( $accept_time ) {
                        $app_time = get_post_time( 'U', true, $app_id );
                        if ( $app_time ) {
                            $time_acc[ $assigned ]['sum'] += max( 0, ( $accept_time - $app_time ) );
                            $time_acc[ $assigned ]['count']++;
                        }
                    }
                }
            }

            foreach ( $rows as $uid => &$row ) {
                $total = max( 0, (int) $row['total'] );
                $acc = max( 0, (int) $row['accepted'] );
                $row['acceptRate'] = $total > 0 ? round( ( $acc / $total ) * 100, 1 ) : 0;
                if ( ! empty( $time_acc[ $uid ]['count'] ) ) {
                    $avg = $time_acc[ $uid ]['sum'] / $time_acc[ $uid ]['count'];
                    $row['timeToHire'] = round( $avg / DAY_IN_SECONDS, 1 );
                } else {
                    $row['timeToHire'] = 0;
                }
                // Ensure all statuses exist for UI consistency.
                foreach ( $statuses as $k => $label ) {
                    if ( ! isset( $row['stageCounts'][ $k ] ) ) {
                        $row['stageCounts'][ $k ] = 0;
                    }
                }
            }
            unset( $row );

            $rows = array_values( $rows );
            set_transient( $cache_key, $rows, 5 * MINUTE_IN_SECONDS );

            self::maybe_log( 'info', 'ATS KPI recruiter loaded', array(
                'company_id' => $company_id,
                'user_id'    => get_current_user_id(),
                'rows'       => count( $rows ),
                'cached'     => false,
            ) );

            self::json_ok( array( 'kpis' => $rows, 'cached' => false ) );
        }

        public static function ajax_saved_views_get() {
            $company_id = self::require_company_context();
            $uid = self::current_effective_user_id();
            $meta_key = self::get_saved_views_key( $company_id );
            $views = get_user_meta( $uid, $meta_key, true );
            if ( ! is_array( $views ) ) {
                $views = array();
            }
            self::json_ok( array( 'views' => array_values( $views ) ) );
        }

        public static function ajax_saved_views_save() {
            $company_id = self::require_company_context();
            $uid = self::current_effective_user_id();
            $meta_key = self::get_saved_views_key( $company_id );
            $views = get_user_meta( $uid, $meta_key, true );
            if ( ! is_array( $views ) ) {
                $views = array();
            }

            $name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
            $id   = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
            $filters = isset( $_POST['filters'] ) ? (array) $_POST['filters'] : array();
            if ( $name === '' ) {
                self::json_error( __( 'Numele view-ului este obligatoriu.', 'ai-suite' ), 422 );
            }

            $filters = self::sanitize_saved_view_filters( $filters );
            if ( ! $id ) {
                $id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : (string) ( time() . '-' . wp_rand( 1000, 9999 ) );
            }

            $views[ $id ] = array(
                'id'        => $id,
                'name'      => $name,
                'filters'   => $filters,
                'updatedAt' => time(),
            );

            if ( count( $views ) > 20 ) {
                $views = array_slice( $views, -20, 20, true );
            }

            update_user_meta( $uid, $meta_key, $views );

            self::maybe_log( 'info', 'ATS saved view saved', array(
                'company_id' => $company_id,
                'user_id'    => $uid,
                'view_id'    => $id,
            ) );

            self::json_ok( array( 'views' => array_values( $views ), 'id' => $id ) );
        }

        public static function ajax_saved_views_delete() {
            $company_id = self::require_company_context();
            $uid = self::current_effective_user_id();
            $meta_key = self::get_saved_views_key( $company_id );
            $views = get_user_meta( $uid, $meta_key, true );
            if ( ! is_array( $views ) ) {
                $views = array();
            }
            $id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
            if ( ! $id || ! isset( $views[ $id ] ) ) {
                self::json_error( __( 'View inexistent.', 'ai-suite' ), 404 );
            }
            unset( $views[ $id ] );
            update_user_meta( $uid, $meta_key, $views );

            self::maybe_log( 'info', 'ATS saved view deleted', array(
                'company_id' => $company_id,
                'user_id'    => $uid,
                'view_id'    => $id,
            ) );

            self::json_ok( array( 'views' => array_values( $views ) ) );
        }

        public static function ajax_smart_search() {
            $company_id = self::require_company_context();
            $q = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
            $q = trim( $q );
            if ( mb_strlen( $q ) < 2 ) {
                self::json_ok( array( 'results' => array() ) );
            }

            $results = array();
            $job_ids = self::company_job_ids( $company_id );

            // Candidates
            $cand_posts = get_posts( array(
                'post_type'      => 'rmax_candidate',
                'post_status'    => 'publish',
                'posts_per_page' => 5,
                's'              => $q,
                'fields'         => 'ids',
            ) );
            foreach ( (array) $cand_posts as $cid ) {
                $cand = self::format_candidate( $cid );
                if ( ! $cand ) {
                    continue;
                }
                $hay = strtolower( $cand['name'] . ' ' . $cand['email'] . ' ' . $cand['phone'] . ' ' . $cand['skills'] . ' ' . $cand['location'] );
                if ( strpos( $hay, strtolower( $q ) ) === false ) {
                    continue;
                }
                $results[] = array(
                    'type'     => 'candidate',
                    'id'       => $cand['id'],
                    'title'    => $cand['name'],
                    'subtitle' => $cand['location'],
                );
            }

            // Jobs (company only)
            if ( $job_ids ) {
                $job_posts = get_posts( array(
                    'post_type'      => 'rmax_job',
                    'post_status'    => 'publish',
                    'posts_per_page' => 5,
                    'post__in'       => array_map( 'absint', $job_ids ),
                    's'              => $q,
                ) );
                foreach ( (array) $job_posts as $job ) {
                    $results[] = array(
                        'type'     => 'job',
                        'id'       => absint( $job->ID ),
                        'title'    => $job->post_title,
                        'subtitle' => __( 'Job companie', 'ai-suite' ),
                    );
                }
            }

            // Applications (company jobs only)
            if ( $job_ids ) {
                $app_posts = get_posts( array(
                    'post_type'      => 'rmax_application',
                    'post_status'    => 'publish',
                    'posts_per_page' => 6,
                    'fields'         => 'ids',
                    'meta_query'     => array(
                        array(
                            'key'     => '_application_job_id',
                            'value'   => array_map( 'strval', array_map( 'absint', $job_ids ) ),
                            'compare' => 'IN',
                        ),
                    ),
                ) );
                foreach ( (array) $app_posts as $app_id ) {
                    $cand_id = absint( get_post_meta( $app_id, '_application_candidate_id', true ) );
                    $cand = $cand_id ? self::format_candidate( $cand_id ) : null;
                    $job_id = absint( get_post_meta( $app_id, '_application_job_id', true ) );
                    $title = $cand && ! empty( $cand['name'] ) ? $cand['name'] : __( 'Aplicație', 'ai-suite' );
                    $job_title = $job_id ? get_the_title( $job_id ) : '';
                    $hay = strtolower( $title . ' ' . $job_title );
                    if ( strpos( $hay, strtolower( $q ) ) === false ) {
                        continue;
                    }
                    $results[] = array(
                        'type'     => 'application',
                        'id'       => $app_id,
                        'jobId'    => $job_id,
                        'title'    => $title,
                        'subtitle' => $job_title,
                    );
                }
            }

            self::maybe_log( 'info', 'ATS smart search executed', array(
                'company_id' => $company_id,
                'user_id'    => get_current_user_id(),
                'q'          => $q,
                'results'    => count( $results ),
            ) );

            self::json_ok( array( 'results' => array_slice( $results, 0, 15 ) ) );
        }

        private static function company_job_ids( $company_id ) {
            $company_id = absint( $company_id );
            if ( ! $company_id ) { return array(); }
            if ( function_exists( 'aisuite_company_get_job_ids' ) ) {
                return (array) aisuite_company_get_job_ids( $company_id );
            }
            $jobs = get_posts( array(
                'post_type'      => 'rmax_job',
                'posts_per_page' => 200,
                'post_status'    => 'any',
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'   => '_job_company_id',
                        'value' => (string) $company_id,
                    ),
                ),
            ) );
            return array_map( 'absint', (array) $jobs );
        }

        private static function format_candidate( $candidate_id ) {
            $candidate_id = absint( $candidate_id );
            if ( ! $candidate_id || get_post_type( $candidate_id ) !== 'rmax_candidate' ) {
                return null;
            }
            $name  = get_the_title( $candidate_id );
            $email = (string) get_post_meta( $candidate_id, '_candidate_email', true );
            $phone = (string) get_post_meta( $candidate_id, '_candidate_phone', true );
            $skills = (string) get_post_meta( $candidate_id, '_candidate_skills', true );
            $loc    = (string) get_post_meta( $candidate_id, '_candidate_location', true );
            $cv_id  = absint( get_post_meta( $candidate_id, '_candidate_cv', true ) );
            $cv_url = $cv_id ? wp_get_attachment_url( $cv_id ) : '';

            return array(
                'id'       => $candidate_id,
                'name'     => $name,
                'email'    => sanitize_email( $email ),
                'phone'    => sanitize_text_field( $phone ),
                'skills'   => sanitize_text_field( $skills ),
                'location' => sanitize_text_field( $loc ),
                'cvUrl'    => esc_url_raw( $cv_url ),
            );
        }

        // -----------------
        // Shortlist storage
        // -----------------

        private static function get_shortlist( $company_id ) {
            $raw = get_post_meta( $company_id, '_company_shortlist', true );
            if ( ! is_array( $raw ) ) {
                $raw = array();
            }
            // Normalize keys.
            $out = array();
            foreach ( $raw as $cid => $row ) {
                $cid = absint( $cid );
                if ( ! $cid ) { continue; }
                $row = is_array( $row ) ? $row : array();
                $out[ $cid ] = array(
                    'added' => isset( $row['added'] ) ? absint( $row['added'] ) : 0,
                    'tags'  => isset( $row['tags'] ) ? sanitize_text_field( $row['tags'] ) : '',
                    'note'  => isset( $row['note'] ) ? sanitize_textarea_field( $row['note'] ) : '',
                );
            }
            return $out;
        }

        private static function set_shortlist( $company_id, array $shortlist ) {
            update_post_meta( $company_id, '_company_shortlist', $shortlist );
        }

        // -----------------
        // AJAX: Candidate search
        // -----------------

        public static function ajax_candidate_search() {
            $company_id = self::require_company_context();

            $q = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
            $q = trim( $q );

            $loc = isset( $_POST['loc'] ) ? sanitize_text_field( wp_unslash( $_POST['loc'] ) ) : '';
            $loc = trim( $loc );

            $has_cv = isset( $_POST['hasCv'] ) ? absint( wp_unslash( $_POST['hasCv'] ) ) : 0;

            // Fast index search (optional)
            $indexed_ids = array();
            if ( function_exists( 'ai_suite_candidate_index_search' ) ) {
                $indexed_ids = ai_suite_candidate_index_search( $q, $loc, $has_cv, 60 );
            }

            $args = array(
                'post_type'      => 'rmax_candidate',
                'post_status'    => 'publish',
                'posts_per_page' => 50,
                'orderby'        => 'date',
                'order'          => 'DESC',
            );

            // Use WP built-in search over title.
            if ( $q !== '' ) {
                $args['s'] = $q;
            }

            if ( ! empty( $indexed_ids ) ) {
                $args['post__in'] = array_map( 'absint', $indexed_ids );
                $args['orderby'] = 'post__in';
                $args['posts_per_page'] = min( 60, count( $args['post__in'] ) );
                unset( $args['meta_query'] );
                unset( $args['s'] );
            }

            $posts = get_posts( $args );
            $list  = array();

            foreach ( $posts as $p ) {
                $cid = absint( $p->ID );
                $cand = self::format_candidate( $cid );
                if ( ! $cand ) { continue; }

                if ( $q !== '' ) {
                    $hay = strtolower( $cand['name'] . ' ' . $cand['email'] . ' ' . $cand['phone'] . ' ' . $cand['skills'] . ' ' . $cand['location'] );
                    if ( strpos( $hay, strtolower( $q ) ) === false ) {
                        continue;
                    }
                }

                if ( $loc !== '' ) {
                    if ( strpos( strtolower( (string) $cand['location'] ), strtolower( $loc ) ) === false ) {
                        continue;
                    }
                }

                if ( $has_cv ) {
                    if ( empty( $cand['cvUrl'] ) ) {
                        continue;
                    }
                }

                $list[] = $cand;
            }

            // Mark shortlisted.
            $shortlist = self::get_shortlist( $company_id );
            foreach ( $list as &$row ) {
                $cid = absint( $row['id'] );
                $row['isShortlisted'] = isset( $shortlist[ $cid ] );
            }

            self::json_ok( array( 'candidates' => $list ) );
        }

        // -----------------
        // AJAX: Shortlist
        // -----------------

        public static function ajax_shortlist_get() {
            $company_id = self::require_company_context();
            $shortlist = self::get_shortlist( $company_id );

            $items = array();
            foreach ( $shortlist as $cid => $meta ) {
                $cand = self::format_candidate( $cid );
                if ( ! $cand ) { continue; }
                $cand['added'] = isset( $meta['added'] ) ? absint( $meta['added'] ) : 0;
                $cand['tags']  = isset( $meta['tags'] ) ? (string) $meta['tags'] : '';
                $cand['note']  = isset( $meta['note'] ) ? (string) $meta['note'] : '';
                $items[] = $cand;
            }

            usort( $items, function( $a, $b ) {
                return (int) $b['added'] <=> (int) $a['added'];
            } );

            self::json_ok( array( 'items' => $items ) );
        }

        public static function ajax_shortlist_add() {
            $company_id = self::require_company_context();
            $candidate_id = isset( $_POST['candidateId'] ) ? absint( wp_unslash( $_POST['candidateId'] ) ) : 0;
            if ( ! $candidate_id || get_post_type( $candidate_id ) !== 'rmax_candidate' ) {
                self::json_error( __( 'Candidat invalid.', 'ai-suite' ) );
            }

            $shortlist = self::get_shortlist( $company_id );
            if ( ! isset( $shortlist[ $candidate_id ] ) ) {
                $shortlist[ $candidate_id ] = array( 'added' => time(), 'tags' => '', 'note' => '' );
                self::set_shortlist( $company_id, $shortlist );
            }

            if ( function_exists( 'ai_suite_log_activity' ) ) {
                ai_suite_log_activity( 'shortlist_add', array(
                    'role' => 'company',
                    'company_id' => $company_id,
                    'candidate_id' => $candidate_id,
                    'details' => array( 'source' => 'portal' ),
                ) );
            }
            self::json_ok();
        }

        public static function ajax_shortlist_remove() {
            $company_id = self::require_company_context();
            $candidate_id = isset( $_POST['candidateId'] ) ? absint( wp_unslash( $_POST['candidateId'] ) ) : 0;
            $shortlist = self::get_shortlist( $company_id );
            if ( $candidate_id && isset( $shortlist[ $candidate_id ] ) ) {
                unset( $shortlist[ $candidate_id ] );
                self::set_shortlist( $company_id, $shortlist );
            }
            if ( function_exists( 'ai_suite_log_activity' ) ) {
                ai_suite_log_activity( 'shortlist_remove', array(
                    'role' => 'company',
                    'company_id' => $company_id,
                    'candidate_id' => $candidate_id,
                    'details' => array( 'source' => 'portal' ),
                ) );
            }
            self::json_ok();
        }

        public static function ajax_shortlist_update() {
            $company_id = self::require_company_context();
            $candidate_id = isset( $_POST['candidateId'] ) ? absint( wp_unslash( $_POST['candidateId'] ) ) : 0;
            if ( ! $candidate_id ) {
                self::json_error( __( 'Candidat invalid.', 'ai-suite' ) );
            }

            $tags = isset( $_POST['tags'] ) ? sanitize_text_field( wp_unslash( $_POST['tags'] ) ) : '';
            $note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

            $shortlist = self::get_shortlist( $company_id );
            if ( ! isset( $shortlist[ $candidate_id ] ) ) {
                $shortlist[ $candidate_id ] = array( 'added' => time(), 'tags' => '', 'note' => '' );
            }
            $shortlist[ $candidate_id ]['tags'] = $tags;
            $shortlist[ $candidate_id ]['note'] = $note;

            self::set_shortlist( $company_id, $shortlist );
            self::json_ok();
        }

        // -----------------
        // AJAX: Pipeline
        // -----------------

        public static function ajax_pipeline_list() {
            $company_id = self::require_company_context();

            $job_ids = self::company_job_ids( $company_id );

            $filter_job_id = isset( $_POST['jobId'] ) ? absint( wp_unslash( $_POST['jobId'] ) ) : 0;
            if ( $filter_job_id ) {
                if ( ! in_array( $filter_job_id, array_map( 'absint', $job_ids ), true ) ) {
                    self::json_error( __( 'Job invalid sau fără acces.', 'ai-suite' ), 403 );
                }
                $job_ids = array( $filter_job_id );
            }
            if ( empty( $job_ids ) ) {
                self::json_ok( array( 'columns' => array(), 'counts' => array() ) );
            }

            $apps = get_posts( array(
                'post_type'      => 'rmax_application',
                'post_status'    => 'publish',
                'posts_per_page' => 200,
                'orderby'        => 'date',
                'order'          => 'DESC',
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'     => '_application_job_id',
                        'value'   => array_map( 'strval', array_map( 'absint', $job_ids ) ),
                        'compare' => 'IN',
                    ),
                ),
            ) );

            $statuses = function_exists( 'ai_suite_app_statuses' ) ? (array) ai_suite_app_statuses() : array(
                'nou' => 'Nou', 'in_analiza' => 'In analiza', 'interviu' => 'Interviu', 'respins' => 'Respins', 'acceptat' => 'Acceptat'
            );


            // Company pipeline settings (labels + hidden columns)
            if ( $company_id && function_exists( 'ai_suite_pipeline_labels_for_company' ) ) {
                $labels = (array) ai_suite_pipeline_labels_for_company( $company_id );
                $hidden = function_exists( 'ai_suite_pipeline_hidden_for_company' ) ? (array) ai_suite_pipeline_hidden_for_company( $company_id ) : array();
                $hidden_map = array();
                foreach ( $hidden as $hk ) { $hidden_map[ sanitize_key( $hk ) ] = true; }

                $tmp = array();
                foreach ( $statuses as $k => $v ) {
                    $k = sanitize_key( $k );
                    if ( isset( $hidden_map[ $k ] ) ) continue;
                    $tmp[ $k ] = ( isset( $labels[ $k ] ) && $labels[ $k ] ) ? (string) $labels[ $k ] : (string) $v;
                }
                $statuses = $tmp;
            }

            $cols = array();
            $counts = array();
            foreach ( $statuses as $key => $label ) {
                $cols[ $key ] = array(
                    'key'   => $key,
                    'label' => (string) $label,
                    'items' => array(),
                );
                $counts[ $key ] = 0;
            }

            foreach ( $apps as $app_id ) {
                $app_id = absint( $app_id );
                $status = (string) get_post_meta( $app_id, '_application_status', true );
                if ( ! isset( $cols[ $status ] ) ) {
                    $cols[ $status ] = array( 'key' => $status, 'label' => $status, 'items' => array() );
                }

                $cand_id = absint( get_post_meta( $app_id, '_application_candidate_id', true ) );
                $job_id  = absint( get_post_meta( $app_id, '_application_job_id', true ) );

                $cand = $cand_id ? self::format_candidate( $cand_id ) : null;

                // PATCH51: extra fields for ATS Board advanced actions.
                $assigned_user = absint( get_post_meta( $app_id, '_application_assigned_user', true ) );
                $tags_raw      = get_post_meta( $app_id, '_application_tags', true );
                $tags_arr      = array();
                if ( is_array( $tags_raw ) ) {
                    foreach ( $tags_raw as $t ) {
                        $t = sanitize_text_field( $t );
                        if ( $t !== '' ) { $tags_arr[] = $t; }
                    }
                } elseif ( is_string( $tags_raw ) && $tags_raw !== '' ) {
                    // support comma-separated
                    $parts = array_map( 'trim', explode( ',', (string) $tags_raw ) );
                    foreach ( $parts as $t ) { if ( $t !== '' ) { $tags_arr[] = sanitize_text_field( $t ); } }
                }
                $interview_at   = (string) get_post_meta( $app_id, '_application_interview_at', true );
                $interview_note = (string) get_post_meta( $app_id, '_application_interview_note', true );

                $cols[ $status ]['items'][] = array(
                    'id'        => $app_id,
                    'title'     => get_the_title( $app_id ),
                    'status'    => $status,
                    'jobId'     => $job_id,
                    'jobTitle'  => $job_id ? get_the_title( $job_id ) : '',
                    'candidate' => $cand,
                    'assignedUserId' => $assigned_user,
                    'tags'      => $tags_arr,
                    'interviewAt' => $interview_at,
                    'interviewNote' => sanitize_text_field( $interview_note ),
                    'created'   => get_post_time( 'U', true, $app_id ),
                );

                if ( isset( $counts[ $status ] ) ) {
                    $counts[ $status ]++;
                } else {
                    $counts[ $status ] = 1;
                }
            }

            // Return columns as ordered list.
            $out = array_values( $cols );
            self::json_ok( array( 'columns' => $out, 'counts' => $counts ) );
        }

        public static function ajax_pipeline_move() {
            $company_id = self::require_company_context();

            $app_id = isset( $_POST['applicationId'] ) ? absint( wp_unslash( $_POST['applicationId'] ) ) : 0;
            $to     = isset( $_POST['toStatus'] ) ? sanitize_key( wp_unslash( $_POST['toStatus'] ) ) : '';
            if ( ! $app_id || get_post_type( $app_id ) !== 'rmax_application' ) {
                self::json_error( __( 'Aplicație invalidă.', 'ai-suite' ) );
            }
            if ( $to === '' ) {
                self::json_error( __( 'Status invalid.', 'ai-suite' ) );
            }

            // Ownership check: application job belongs to company.
            $job_id = absint( get_post_meta( $app_id, '_application_job_id', true ) );
            if ( ! $job_id || get_post_type( $job_id ) !== 'rmax_job' ) {
                self::json_error( __( 'Job invalid.', 'ai-suite' ) );
            }
            $job_company_id = (string) get_post_meta( $job_id, '_job_company_id', true );
            if ( (string) $company_id !== (string) $job_company_id ) {
                self::json_error( __( 'Nu ai acces la această aplicație.', 'ai-suite' ), 403 );
            }

            $from = (string) get_post_meta( $app_id, '_application_status', true );

            if ( function_exists( 'ai_suite_application_can_transition' ) && ! ai_suite_application_can_transition( $from, $to ) ) {
                self::json_error( __( 'Tranziție de status nepermisă.', 'ai-suite' ), 409 );
            }

            update_post_meta( $app_id, '_application_status', $to );

            if ( function_exists( 'ai_suite_application_record_status_change' ) ) {
                ai_suite_application_record_status_change( $app_id, $from, $to, 'company_portal', array( 'company_id' => $company_id ) );
            }

            if ( function_exists( 'ai_suite_log_activity' ) ) {
                $cand = absint( get_post_meta( $app_id, '_application_candidate_id', true ) );
                ai_suite_log_activity( 'pipeline_move', array(
                    'role' => 'company',
                    'company_id' => $company_id,
                    'candidate_id' => $cand,
                    'application_id' => $app_id,
                    'details' => array( 'from' => $from, 'to' => $to ),
                ) );
            }
            self::json_ok();
        }

        public static function ajax_pipeline_bulk_move() {
            $company_id = self::require_company_context();

            $ids = isset( $_POST['applicationIds'] ) ? (array) wp_unslash( $_POST['applicationIds'] ) : array();
            $to  = isset( $_POST['toStatus'] ) ? sanitize_key( wp_unslash( $_POST['toStatus'] ) ) : '';

            $ids = array_values( array_filter( array_map( 'absint', $ids ) ) );

            if ( empty( $ids ) ) {
                self::json_error( __( 'Nu ai selectat nicio aplicație.', 'ai-suite' ) );
            }
            if ( $to === '' ) {
                self::json_error( __( 'Status invalid.', 'ai-suite' ) );
            }

            $moved = 0;
            $errors = array();

            foreach ( $ids as $app_id ) {
                if ( ! $app_id || get_post_type( $app_id ) !== 'rmax_application' ) {
                    $errors[] = array( 'id' => $app_id, 'message' => __( 'Aplicație invalidă.', 'ai-suite' ) );
                    continue;
                }

                $job_id = absint( get_post_meta( $app_id, '_application_job_id', true ) );
                if ( ! $job_id || get_post_type( $job_id ) !== 'rmax_job' ) {
                    $errors[] = array( 'id' => $app_id, 'message' => __( 'Job invalid.', 'ai-suite' ) );
                    continue;
                }
                $job_company_id = (string) get_post_meta( $job_id, '_job_company_id', true );
                if ( (string) $company_id !== (string) $job_company_id ) {
                    $errors[] = array( 'id' => $app_id, 'message' => __( 'Nu ai acces la această aplicație.', 'ai-suite' ) );
                    continue;
                }

                $from = (string) get_post_meta( $app_id, '_application_status', true );
                if ( function_exists( 'ai_suite_application_can_transition' ) && ! ai_suite_application_can_transition( $from, $to ) ) {
                    $errors[] = array( 'id' => $app_id, 'message' => __( 'Tranziție de status nepermisă.', 'ai-suite' ) );
                    continue;
                }

                update_post_meta( $app_id, '_application_status', $to );

                if ( function_exists( 'ai_suite_application_record_status_change' ) ) {
                    ai_suite_application_record_status_change( $app_id, $from, $to, 'company_portal_bulk', array( 'company_id' => $company_id ) );
                } elseif ( function_exists( 'ai_suite_application_add_timeline' ) ) {
                    ai_suite_application_add_timeline( $app_id, 'status_change', array( 'from' => $from, 'to' => $to, 'context' => 'company_portal_bulk', 'company_id' => $company_id ) );
                }

                $moved++;
            }

            wp_send_json( array(
                'ok' => true,
                'moved' => $moved,
                'errors' => $errors,
            ) );
        }

        public static function ajax_pipeline_add_note() {
            $company_id = self::require_company_context();

            $app_id = isset( $_POST['applicationId'] ) ? absint( wp_unslash( $_POST['applicationId'] ) ) : 0;
            $text   = isset( $_POST['text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['text'] ) ) : '';

            if ( ! $app_id || get_post_type( $app_id ) !== 'rmax_application' ) {
                self::json_error( __( 'Aplicație invalidă.', 'ai-suite' ) );
            }
            $text = trim( (string) $text );
            if ( $text === '' ) {
                self::json_error( __( 'Nota este goală.', 'ai-suite' ) );
            }

            $job_id = absint( get_post_meta( $app_id, '_application_job_id', true ) );
            $job_company_id = $job_id ? (string) get_post_meta( $job_id, '_job_company_id', true ) : '';
            if ( (string) $company_id !== (string) $job_company_id ) {
                self::json_error( __( 'Nu ai acces la această aplicație.', 'ai-suite' ), 403 );
            }

            if ( function_exists( 'ai_suite_application_add_note' ) ) {
                ai_suite_application_add_note( $app_id, $text, array( 'context' => 'company_portal', 'company_id' => $company_id ) );
            } else {
                // Fallback: timeline only.
                if ( function_exists( 'ai_suite_application_add_timeline' ) ) {
                    ai_suite_application_add_timeline( $app_id, 'note', array( 'text' => $text, 'context' => 'company_portal', 'company_id' => $company_id ) );
                }
            }

            if ( function_exists( 'ai_suite_application_add_timeline' ) ) {
                ai_suite_application_add_timeline( $app_id, 'note', array( 'text' => $text, 'context' => 'company_portal', 'company_id' => $company_id ) );
            }

            wp_send_json( array( 'ok' => true ) );
        }

        public static function ajax_pipeline_activity() {
            $company_id = self::require_company_context();

            $app_id = isset( $_POST['applicationId'] ) ? absint( wp_unslash( $_POST['applicationId'] ) ) : 0;
            if ( ! $app_id || get_post_type( $app_id ) !== 'rmax_application' ) {
                self::json_error( __( 'Aplicație invalidă.', 'ai-suite' ) );
            }

            $job_id = absint( get_post_meta( $app_id, '_application_job_id', true ) );
            $job_company_id = $job_id ? (string) get_post_meta( $job_id, '_job_company_id', true ) : '';
            if ( (string) $company_id !== (string) $job_company_id ) {
                self::json_error( __( 'Nu ai acces la această aplicație.', 'ai-suite' ), 403 );
            }

            $timeline = get_post_meta( $app_id, '_application_timeline', true );
            $notes    = get_post_meta( $app_id, '_application_notes', true );
            if ( ! is_array( $timeline ) ) { $timeline = array(); }
            if ( ! is_array( $notes ) ) { $notes = array(); }

            // last 25
            $timeline = array_slice( $timeline, -25 );
            $notes    = array_slice( $notes, -25 );

            wp_send_json( array(
                'ok' => true,
                'timeline' => $timeline,
                'notes' => $notes,
            ) );
        }


        // ------------------------------------------------------------------
        // PATCH51: Drawer details + advanced bulk actions (assign/tag/schedule/email)
        // ------------------------------------------------------------------

        private static function ensure_application_owned_by_company( $app_id, $company_id ) {
            $app_id = absint( $app_id );
            $company_id = absint( $company_id );
            if ( ! $app_id || get_post_type( $app_id ) !== 'rmax_application' ) {
                return new WP_Error( 'invalid_app', __( 'Aplicație invalidă.', 'ai-suite' ) );
            }
            $job_id = absint( get_post_meta( $app_id, '_application_job_id', true ) );
            if ( ! $job_id || get_post_type( $job_id ) !== 'rmax_job' ) {
                return new WP_Error( 'invalid_job', __( 'Job invalid.', 'ai-suite' ) );
            }
            $job_company_id = (string) get_post_meta( $job_id, '_job_company_id', true );
            if ( (string) $company_id !== (string) $job_company_id ) {
                return new WP_Error( 'forbidden', __( 'Nu ai acces la această aplicație.', 'ai-suite' ) );
            }
            return true;
        }

        private static function normalize_tags( $tag_str ) {
            $tag_str = is_array( $tag_str ) ? implode( ',', $tag_str ) : (string) $tag_str;
            $tag_str = trim( $tag_str );
            if ( $tag_str === '' ) return array();
            $parts = array_map( 'trim', preg_split( '/[,\n]+/', $tag_str ) );
            $out = array();
            foreach ( $parts as $p ) {
                $p = sanitize_text_field( $p );
                if ( $p !== '' ) { $out[] = $p; }
            }
            // de-dupe
            $out = array_values( array_unique( $out ) );
            return $out;
        }

        private static function add_timeline_safe( $app_id, $event, $data = array() ) {
            if ( function_exists( 'ai_suite_application_add_timeline' ) ) {
                ai_suite_application_add_timeline( $app_id, $event, $data );
                return;
            }
            // Fallback: store in _application_timeline.
            $tl = get_post_meta( $app_id, '_application_timeline', true );
            if ( ! is_array( $tl ) ) { $tl = array(); }
            $tl[] = array(
                'time'  => time(),
                'event' => sanitize_key( $event ),
                'data'  => is_array( $data ) ? $data : array(),
            );
            // keep reasonable size
            if ( count( $tl ) > 200 ) { $tl = array_slice( $tl, -200 ); }
            update_post_meta( $app_id, '_application_timeline', $tl );
        }

        public static function ajax_pipeline_get() {
            $company_id = self::require_company_context();

            $app_id = isset( $_POST['applicationId'] ) ? absint( wp_unslash( $_POST['applicationId'] ) ) : 0;
            if ( ! $app_id ) {
                self::json_error( __( 'Aplicație invalidă.', 'ai-suite' ) );
            }
            $own = self::ensure_application_owned_by_company( $app_id, $company_id );
            if ( is_wp_error( $own ) ) {
                self::json_error( $own->get_error_message(), ( $own->get_error_code() === 'forbidden' ? 403 : 400 ) );
            }

            $cand_id = absint( get_post_meta( $app_id, '_application_candidate_id', true ) );
            $job_id  = absint( get_post_meta( $app_id, '_application_job_id', true ) );

            $data = array(
                'id' => $app_id,
                'title' => get_the_title( $app_id ),
                'status' => (string) get_post_meta( $app_id, '_application_status', true ),
                'jobId' => $job_id,
                'jobTitle' => $job_id ? get_the_title( $job_id ) : '',
                'candidate' => $cand_id ? self::format_candidate( $cand_id ) : null,
                'assignedUserId' => absint( get_post_meta( $app_id, '_application_assigned_user', true ) ),
                'tags' => self::normalize_tags( get_post_meta( $app_id, '_application_tags', true ) ),
                'interviewAt' => (string) get_post_meta( $app_id, '_application_interview_at', true ),
                'interviewNote' => (string) get_post_meta( $app_id, '_application_interview_note', true ),
            );

            $timeline = get_post_meta( $app_id, '_application_timeline', true );
            $notes    = get_post_meta( $app_id, '_application_notes', true );
            if ( ! is_array( $timeline ) ) { $timeline = array(); }
            if ( ! is_array( $notes ) ) { $notes = array(); }
            $data['timeline'] = array_slice( $timeline, -30 );
            $data['notes']    = array_slice( $notes, -30 );

            self::json_ok( array( 'application' => $data ) );
        }

        public static function ajax_pipeline_team_list() {
            $company_id = self::require_company_context();

            $out = array();
            // Include current user (owner) always.
            $uid = function_exists( 'ai_suite_portal_effective_user_id' ) ? ai_suite_portal_effective_user_id() : get_current_user_id();
            $u = get_user_by( 'id', absint( $uid ) );
            if ( $u ) {
                $out[] = array( 'userId' => (int) $u->ID, 'name' => (string) $u->display_name, 'email' => (string) $u->user_email, 'role' => 'owner' );
            }

            if ( function_exists( 'ai_suite_company_members_get' ) ) {
                $rows = (array) ai_suite_company_members_get( $company_id );
                foreach ( $rows as $r ) {
                    if ( empty( $r['user_id'] ) ) continue;
                    if ( ! empty( $r['status'] ) && $r['status'] !== 'active' && $r['status'] !== 'accepted' ) continue;
                    $mid = absint( $r['user_id'] );
                    if ( ! $mid ) continue;
                    if ( $u && (int) $u->ID === $mid ) continue;
                    $mu = get_user_by( 'id', $mid );
                    if ( ! $mu ) continue;
                    $out[] = array(
                        'userId' => (int) $mu->ID,
                        'name'   => (string) $mu->display_name,
                        'email'  => (string) $mu->user_email,
                        'role'   => ! empty( $r['member_role'] ) ? sanitize_key( $r['member_role'] ) : 'recruiter',
                    );
                }
            }

            // De-duplicate
            $seen = array();
            $uniq = array();
            foreach ( $out as $row ) {
                $k = (string) ( $row['userId'] ?? 0 );
                if ( ! $k || isset( $seen[ $k ] ) ) continue;
                $seen[ $k ] = true;
                $uniq[] = $row;
            }

            self::json_ok( array( 'members' => $uniq ) );
        }

        public static function ajax_pipeline_bulk_assign() {
            $company_id = self::require_company_context();

            $ids = isset( $_POST['applicationIds'] ) ? (array) wp_unslash( $_POST['applicationIds'] ) : array();
            $ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
            $user_id = isset( $_POST['userId'] ) ? absint( wp_unslash( $_POST['userId'] ) ) : 0;

            if ( empty( $ids ) ) {
                self::json_error( __( 'Nu ai selectat nicio aplicație.', 'ai-suite' ) );
            }
            if ( ! $user_id ) {
                self::json_error( __( 'Recruiter invalid.', 'ai-suite' ) );
            }

            $assigned = 0;
            $errors = array();
            foreach ( $ids as $app_id ) {
                $own = self::ensure_application_owned_by_company( $app_id, $company_id );
                if ( is_wp_error( $own ) ) {
                    $errors[] = array( 'id' => $app_id, 'message' => $own->get_error_message() );
                    continue;
                }
                update_post_meta( $app_id, '_application_assigned_user', $user_id );
                self::add_timeline_safe( $app_id, 'assigned', array( 'user_id' => $user_id, 'context' => 'company_portal_bulk' ) );
                $assigned++;
            }

            wp_send_json( array( 'ok' => true, 'assigned' => $assigned, 'errors' => $errors ) );
        }

        public static function ajax_pipeline_bulk_tag() {
            $company_id = self::require_company_context();

            $ids = isset( $_POST['applicationIds'] ) ? (array) wp_unslash( $_POST['applicationIds'] ) : array();
            $ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
            $tag_str = isset( $_POST['tags'] ) ? wp_unslash( $_POST['tags'] ) : '';
            $tags = self::normalize_tags( $tag_str );

            if ( empty( $ids ) ) {
                self::json_error( __( 'Nu ai selectat nicio aplicație.', 'ai-suite' ) );
            }
            if ( empty( $tags ) ) {
                self::json_error( __( 'Tag-urile sunt goale.', 'ai-suite' ) );
            }

            $updated = 0;
            $errors = array();
            foreach ( $ids as $app_id ) {
                $own = self::ensure_application_owned_by_company( $app_id, $company_id );
                if ( is_wp_error( $own ) ) {
                    $errors[] = array( 'id' => $app_id, 'message' => $own->get_error_message() );
                    continue;
                }
                $existing = self::normalize_tags( get_post_meta( $app_id, '_application_tags', true ) );
                $merged = array_values( array_unique( array_merge( $existing, $tags ) ) );
                update_post_meta( $app_id, '_application_tags', $merged );
                self::add_timeline_safe( $app_id, 'tagged', array( 'tags' => $tags, 'context' => 'company_portal_bulk' ) );
                $updated++;
            }
            wp_send_json( array( 'ok' => true, 'updated' => $updated, 'errors' => $errors ) );
        }

        public static function ajax_pipeline_bulk_schedule() {
            $company_id = self::require_company_context();

            $ids = isset( $_POST['applicationIds'] ) ? (array) wp_unslash( $_POST['applicationIds'] ) : array();
            $ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
            $dt  = isset( $_POST['interviewAt'] ) ? sanitize_text_field( wp_unslash( $_POST['interviewAt'] ) ) : '';
            $note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

            if ( empty( $ids ) ) {
                self::json_error( __( 'Nu ai selectat nicio aplicație.', 'ai-suite' ) );
            }
            if ( $dt === '' ) {
                self::json_error( __( 'Data/ora interviului este invalidă.', 'ai-suite' ) );
            }

            $scheduled = 0;
            $errors = array();
            foreach ( $ids as $app_id ) {
                $own = self::ensure_application_owned_by_company( $app_id, $company_id );
                if ( is_wp_error( $own ) ) {
                    $errors[] = array( 'id' => $app_id, 'message' => $own->get_error_message() );
                    continue;
                }
                update_post_meta( $app_id, '_application_interview_at', $dt );
                update_post_meta( $app_id, '_application_interview_note', $note );
                self::add_timeline_safe( $app_id, 'interview_scheduled', array( 'at' => $dt, 'note' => $note, 'context' => 'company_portal_bulk' ) );
                $scheduled++;
            }
            wp_send_json( array( 'ok' => true, 'scheduled' => $scheduled, 'errors' => $errors ) );
        }

        public static function ajax_pipeline_bulk_email() {
            $company_id = self::require_company_context();

            $ids = isset( $_POST['applicationIds'] ) ? (array) wp_unslash( $_POST['applicationIds'] ) : array();
            $ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
            $subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
            $body    = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';

            if ( empty( $ids ) ) {
                self::json_error( __( 'Nu ai selectat nicio aplicație.', 'ai-suite' ) );
            }
            if ( $subject === '' || $body === '' ) {
                self::json_error( __( 'Subiectul și mesajul sunt obligatorii.', 'ai-suite' ) );
            }

            $sent = 0;
            $errors = array();
            foreach ( $ids as $app_id ) {
                $own = self::ensure_application_owned_by_company( $app_id, $company_id );
                if ( is_wp_error( $own ) ) {
                    $errors[] = array( 'id' => $app_id, 'message' => $own->get_error_message() );
                    continue;
                }
                $cand_id = absint( get_post_meta( $app_id, '_application_candidate_id', true ) );
                $email = $cand_id ? (string) get_post_meta( $cand_id, '_candidate_email', true ) : '';
                $email = sanitize_email( $email );
                if ( ! $email ) {
                    $errors[] = array( 'id' => $app_id, 'message' => __( 'Email candidat lipsă.', 'ai-suite' ) );
                    continue;
                }

                $ok = wp_mail( $email, $subject, $body );
                if ( ! $ok ) {
                    $errors[] = array( 'id' => $app_id, 'message' => __( 'Eroare la trimiterea emailului.', 'ai-suite' ) );
                    continue;
                }

                self::add_timeline_safe( $app_id, 'email_sent', array( 'to' => $email, 'subject' => $subject, 'context' => 'company_portal_bulk' ) );
                $sent++;
            }

            wp_send_json( array( 'ok' => true, 'sent' => $sent, 'errors' => $errors ) );
        }

    }
}

add_action( 'init', function() {
    if ( class_exists( 'AI_Suite_ATS_Pro' ) ) {
        AI_Suite_ATS_Pro::boot();
    }
}, 15 );
