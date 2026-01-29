<?php
/**
 * AI Suite – Job Posting PRO (Company Portal)
 *
 * Permite companiilor (conturi de tip companie) să:
 *  - listeze joburile proprii
 *  - creeze/editeze joburi
 *  - publice/draft
 *  - șteargă joburi
 *
 * ADD-ONLY. Sigur + nonce + ownership check.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'AI_Suite_Job_Posting_Pro' ) ) {
    final class AI_Suite_Job_Posting_Pro {

        public static function boot() {
            add_action( 'wp_ajax_ai_suite_company_jobs_list', array( __CLASS__, 'ajax_jobs_list' ) );
            add_action( 'wp_ajax_ai_suite_company_job_get', array( __CLASS__, 'ajax_job_get' ) );
            add_action( 'wp_ajax_ai_suite_company_job_save', array( __CLASS__, 'ajax_job_save' ) );
            add_action( 'wp_ajax_ai_suite_company_job_toggle_status', array( __CLASS__, 'ajax_job_toggle_status' ) );
            add_action( 'wp_ajax_ai_suite_company_job_delete', array( __CLASS__, 'ajax_job_delete' ) );
            add_action( 'wp_ajax_ai_suite_company_job_promote', array( __CLASS__, 'ajax_job_promote' ) );
        }

        // -----------------
        // Helpers
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
            if ( function_exists( 'ai_suite_portal_ajax_guard' ) ) {
                ai_suite_portal_ajax_guard( 'company' );
            }
            $uid = function_exists( 'ai_suite_portal_effective_user_id' ) ? ai_suite_portal_effective_user_id() : get_current_user_id();
            $is_company = function_exists( 'ai_suite_portal_user_can' )
                ? ai_suite_portal_user_can( 'company', $uid )
                : user_can( $uid, 'rmax_company_access' );
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
            return absint( $company_id );
        }

        private static function company_job_ids( $company_id ) {
            $company_id = absint( $company_id );
            if ( ! $company_id ) { return array(); }
            if ( function_exists( 'aisuite_company_get_job_ids' ) ) {
                return (array) aisuite_company_get_job_ids( $company_id );
            }
            $jobs = get_posts( array(
                'post_type'      => 'rmax_job',
                'posts_per_page' => 500,
                'post_status'    => array( 'publish', 'draft', 'pending' ),
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

        private static function job_belongs_to_company( $job_id, $company_id ) {
            $job_id = absint( $job_id );
            if ( ! $job_id || get_post_type( $job_id ) !== 'rmax_job' ) {
                return false;
            }
            $cid = (string) get_post_meta( $job_id, '_job_company_id', true );
            return (string) $company_id === (string) $cid;
        }

        private static function job_applications_count( $job_id ) {
            $job_id = absint( $job_id );
            if ( ! $job_id ) { return 0; }
            $q = new WP_Query( array(
                'post_type'      => 'rmax_application',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'   => '_application_job_id',
                        'value' => (string) $job_id,
                    ),
                ),
            ) );
            return absint( $q->found_posts );
        }

        
        private static function get_company_promo_credits( $company_id ) {
            $company_id = absint( $company_id );
            if ( ! $company_id ) { return 0; }
            $credits = (int) get_post_meta( $company_id, '_company_promo_credits', true );
            if ( $credits < 0 ) { $credits = 0; }
            return $credits;
        }

        private static function set_company_promo_credits( $company_id, $credits ) {
            $company_id = absint( $company_id );
            if ( ! $company_id ) { return; }
            $credits = (int) $credits;
            if ( $credits < 0 ) { $credits = 0; }
            update_post_meta( $company_id, '_company_promo_credits', $credits );
        }

        private static function promo_feature_days() { return 7; }

private static function format_job( $job_id ) {
            $job_id = absint( $job_id );
            if ( ! $job_id || get_post_type( $job_id ) !== 'rmax_job' ) {
                return null;
            }
            $post = get_post( $job_id );
            if ( ! $post ) { return null; }

            $dept_terms = wp_get_post_terms( $job_id, 'job_department', array( 'fields' => 'names' ) );
            $loc_terms  = wp_get_post_terms( $job_id, 'job_location', array( 'fields' => 'names' ) );

            return array(
                'id'             => $job_id,
                'title'          => get_the_title( $job_id ),
                'content'        => (string) $post->post_content,
                'status'         => (string) $post->post_status,
                'permalink'      => get_permalink( $job_id ),
                'department'     => is_array( $dept_terms ) && ! empty( $dept_terms ) ? (string) $dept_terms[0] : '',
                'location'       => is_array( $loc_terms ) && ! empty( $loc_terms ) ? (string) $loc_terms[0] : '',
                'salaryMin'      => (string) get_post_meta( $job_id, '_job_salary_min', true ),
                'salaryMax'      => (string) get_post_meta( $job_id, '_job_salary_max', true ),
                'employmentType' => (string) get_post_meta( $job_id, '_job_employment_type', true ),
                'applications'   => self::job_applications_count( $job_id ),
                'featured'       => ( function_exists( 'aisuite_is_job_featured' ) ? (bool) aisuite_is_job_featured( $job_id ) : (bool) get_post_meta( $job_id, '_rmax_featured', true ) ),
                'featuredUntil'  => ( function_exists( 'aisuite_get_job_featured_until' ) ? (int) aisuite_get_job_featured_until( $job_id ) : (int) get_post_meta( $job_id, '_rmax_featured_until', true ) ),
                'created'        => get_post_time( 'U', true, $job_id ),
            );
        }

        private static function set_term_by_name( $job_id, $taxonomy, $name ) {
            $name = trim( (string) $name );
            if ( $name === '' ) {
                wp_set_object_terms( $job_id, array(), $taxonomy, false );
                return;
            }
            $term = term_exists( $name, $taxonomy );
            if ( ! $term ) {
                $created = wp_insert_term( $name, $taxonomy );
                if ( is_wp_error( $created ) ) {
                    // Best effort: ignore, don't fail save.
                    return;
                }
                $term_id = isset( $created['term_id'] ) ? absint( $created['term_id'] ) : 0;
            } else {
                $term_id = is_array( $term ) && isset( $term['term_id'] ) ? absint( $term['term_id'] ) : absint( $term );
            }
            if ( $term_id ) {
                wp_set_object_terms( $job_id, array( $term_id ), $taxonomy, false );
            }
        }

        // -----------------
        // AJAX
        // -----------------

        public static function ajax_jobs_list() {
            $company_id = self::require_company_context();

            // Auto top-up lunar (best-effort)
            if ( function_exists( 'aisuite_company_promo_topup_maybe' ) ) { aisuite_company_promo_topup_maybe( $company_id, false ); }

            $ids = self::company_job_ids( $company_id );
            $jobs = array();
            foreach ( $ids as $jid ) {
                $j = self::format_job( $jid );
                if ( $j ) { $jobs[] = $j; }
            }

            $now = time();
            usort( $jobs, function( $a, $b ) use ( $now ) {
                $fa = ! empty( $a['featuredUntil'] ) && (int) $a['featuredUntil'] > $now;
                $fb = ! empty( $b['featuredUntil'] ) && (int) $b['featuredUntil'] > $now;
                if ( $fa && ! $fb ) { return -1; }
                if ( ! $fa && $fb ) { return 1; }
                return (int) $b['created'] <=> (int) $a['created'];
            } );

            self::json_ok( array(
                'jobs'          => $jobs,
                'promo_credits' => self::get_company_promo_credits( $company_id ),
                'promo_days'    => self::promo_feature_days(),
                'promo_monthly_allowance' => function_exists( 'aisuite_company_promo_monthly_allowance' ) ? aisuite_company_promo_monthly_allowance( $company_id ) : 0,
                'promo_last_topup_ym'     => (string) get_post_meta( $company_id, '_company_promo_last_topup_ym', true ),
            ) );
        }

        public static function ajax_job_get() {
            $company_id = self::require_company_context();
            $job_id = isset( $_POST['jobId'] ) ? absint( wp_unslash( $_POST['jobId'] ) ) : 0;
            if ( ! $job_id || ! self::job_belongs_to_company( $job_id, $company_id ) ) {
                self::json_error( __( 'Job invalid sau fără acces.', 'ai-suite' ), 403 );
            }
            $job = self::format_job( $job_id );
            self::json_ok( array( 'job' => $job ) );
        }

        public static function ajax_job_save() {
            $company_id = self::require_company_context();

            $job_id = isset( $_POST['jobId'] ) ? absint( wp_unslash( $_POST['jobId'] ) ) : 0;

            $title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
            $content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';

            $department = isset( $_POST['department'] ) ? sanitize_text_field( wp_unslash( $_POST['department'] ) ) : '';
            $location   = isset( $_POST['location'] ) ? sanitize_text_field( wp_unslash( $_POST['location'] ) ) : '';

            $salary_min = isset( $_POST['salaryMin'] ) ? sanitize_text_field( wp_unslash( $_POST['salaryMin'] ) ) : '';
            $salary_max = isset( $_POST['salaryMax'] ) ? sanitize_text_field( wp_unslash( $_POST['salaryMax'] ) ) : '';
            $type       = isset( $_POST['employmentType'] ) ? sanitize_text_field( wp_unslash( $_POST['employmentType'] ) ) : '';

            $status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'draft';
            if ( ! in_array( $status, array( 'draft', 'publish' ), true ) ) {
                $status = 'draft';
            }

            if ( $title === '' ) {
                self::json_error( __( 'Titlul este obligatoriu.', 'ai-suite' ) );
            }

            // Subscription gating: active jobs limit (publish only)
            if ( 'publish' === $status && function_exists( 'ai_suite_company_limit' ) ) {
                $limit = (int) ai_suite_company_limit( $company_id, 'active_jobs' );
                if ( $limit > 0 ) {
                    global $wpdb;
                    $exclude = $job_id ? (int) $job_id : 0;
                    $cnt = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT(1) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_job_company_id' AND m.meta_value=%s WHERE p.post_type='rmax_job' AND p.post_status='publish' AND p.ID<>%d",
                        (string) $company_id,
                        $exclude
                    ) );
                    if ( $cnt >= $limit ) {
                        self::json_error( __( 'Ai atins limita de joburi active pentru planul tău. Upgrade pentru mai multe joburi.', 'ai-suite' ), 402 );
                    }
                }
            }


            // Create or update
            if ( $job_id ) {
                if ( ! self::job_belongs_to_company( $job_id, $company_id ) ) {
                    self::json_error( __( 'Nu ai acces la acest job.', 'ai-suite' ), 403 );
                }
                $res = wp_update_post( array(
                    'ID'           => $job_id,
                    'post_title'   => $title,
                    'post_content' => $content,
                    'post_status'  => $status,
                ), true );
                if ( is_wp_error( $res ) ) {
                    self::json_error( $res->get_error_message(), 500 );
                }
            } else {
                $job_id = wp_insert_post( array(
                    'post_type'    => 'rmax_job',
                    'post_status'  => $status,
                    'post_title'   => $title,
                    'post_content' => $content,
                    'post_author'  => get_current_user_id(),
                ), true );
                if ( is_wp_error( $job_id ) ) {
                    self::json_error( $job_id->get_error_message(), 500 );
                }
                update_post_meta( $job_id, '_job_company_id', (string) $company_id );
            }

            // Meta
            update_post_meta( $job_id, '_job_salary_min', $salary_min );
            update_post_meta( $job_id, '_job_salary_max', $salary_max );
            update_post_meta( $job_id, '_job_employment_type', $type );

            // Taxonomies (best effort)
            if ( taxonomy_exists( 'job_department' ) ) {
                self::set_term_by_name( $job_id, 'job_department', $department );
            }
            if ( taxonomy_exists( 'job_location' ) ) {
                self::set_term_by_name( $job_id, 'job_location', $location );
            }

            $job = self::format_job( $job_id );
            self::json_ok( array( 'job' => $job ) );
        }

        public static function ajax_job_toggle_status() {
            $company_id = self::require_company_context();
            $job_id = isset( $_POST['jobId'] ) ? absint( wp_unslash( $_POST['jobId'] ) ) : 0;
            $status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
            if ( ! $job_id || ! self::job_belongs_to_company( $job_id, $company_id ) ) {
                self::json_error( __( 'Job invalid sau fără acces.', 'ai-suite' ), 403 );
            }
            if ( ! in_array( $status, array( 'draft', 'publish' ), true ) ) {
                self::json_error( __( 'Status invalid.', 'ai-suite' ) );
            }

            if ( 'publish' === $status && function_exists( 'ai_suite_company_limit' ) ) {
                $limit = (int) ai_suite_company_limit( $company_id, 'active_jobs' );
                if ( $limit > 0 ) {
                    global $wpdb;
                    $cnt = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT(1) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key='_job_company_id' AND m.meta_value=%s WHERE p.post_type='rmax_job' AND p.post_status='publish' AND p.ID<>%d",
                        (string) $company_id,
                        (int) $job_id
                    ) );
                    if ( $cnt >= $limit ) {
                        self::json_error( __( 'Ai atins limita de joburi active pentru planul tău. Upgrade pentru a publica.', 'ai-suite' ), 402 );
                    }
                }
            }

            $res = wp_update_post( array( 'ID' => $job_id, 'post_status' => $status ), true );
            if ( is_wp_error( $res ) ) {
                self::json_error( $res->get_error_message(), 500 );
            }
            self::json_ok();
        }

        
        public static function ajax_job_promote() {
            $company_id = self::require_company_context();
            $job_id = isset( $_POST['jobId'] ) ? absint( wp_unslash( $_POST['jobId'] ) ) : 0;
            if ( ! $job_id || ! self::job_belongs_to_company( $job_id, $company_id ) ) {
                self::json_error( __( 'Job invalid sau fără acces.', 'ai-suite' ), 403 );
            }

            // Auto top-up lunar (best-effort)
            if ( function_exists( 'aisuite_company_promo_topup_maybe' ) ) { aisuite_company_promo_topup_maybe( $company_id, false ); }

            if ( ! function_exists( 'aisuite_set_job_featured' ) ) {
                self::json_error( __( 'Funcția Featured Jobs nu este disponibilă.', 'ai-suite' ), 500 );
            }

            $days = self::promo_feature_days();
            $now  = time();
            $until = $now + ( (int) $days * DAY_IN_SECONDS );

            // If already featured, extend without consuming credit.
            $already_until = function_exists( 'aisuite_get_job_featured_until' ) ? (int) aisuite_get_job_featured_until( $job_id ) : (int) get_post_meta( $job_id, '_rmax_featured_until', true );
            if ( $already_until && $already_until > $now ) {
                $until = (int) $already_until + ( (int) $days * DAY_IN_SECONDS );
                aisuite_set_job_featured( $job_id, true, $until );

                if ( function_exists( 'aisuite_log' ) ) {
                    aisuite_log( 'info', __( 'Promovare extinsă din portal', 'ai-suite' ), array( 'company_id' => $company_id, 'job_id' => $job_id, 'until' => $until ) );
                }

                self::json_ok( array(
                    'job'           => self::format_job( $job_id ),
                    'promo_credits' => self::get_company_promo_credits( $company_id ),
                    'promo_days'    => $days,
                    'promo_monthly_allowance' => function_exists( 'aisuite_company_promo_monthly_allowance' ) ? aisuite_company_promo_monthly_allowance( $company_id ) : 0,
                    'note'          => __( 'Promovare extinsă.', 'ai-suite' ),
                ) );
            }

            // Need credits
            $credits = self::get_company_promo_credits( $company_id );
            if ( $credits < 1 ) {
                self::json_error( __( 'Nu ai credite de promovare disponibile. Contactează administratorul pentru încărcare.', 'ai-suite' ), 402 );
            }

            aisuite_set_job_featured( $job_id, true, $until );
            self::set_company_promo_credits( $company_id, $credits - 1 );

            if ( function_exists( 'aisuite_log' ) ) {
                aisuite_log( 'info', __( 'Job promovat din portal', 'ai-suite' ), array( 'company_id' => $company_id, 'job_id' => $job_id, 'until' => $until ) );
            }

            self::json_ok( array(
                'job'           => self::format_job( $job_id ),
                'promo_credits' => self::get_company_promo_credits( $company_id ),
                'promo_days'    => $days,
                'promo_monthly_allowance' => function_exists( 'aisuite_company_promo_monthly_allowance' ) ? aisuite_company_promo_monthly_allowance( $company_id ) : 0,
            ) );
        }

        public static function ajax_job_delete() {
            $company_id = self::require_company_context();
            $job_id = isset( $_POST['jobId'] ) ? absint( wp_unslash( $_POST['jobId'] ) ) : 0;
            if ( ! $job_id || ! self::job_belongs_to_company( $job_id, $company_id ) ) {
                self::json_error( __( 'Job invalid sau fără acces.', 'ai-suite' ), 403 );
            }
            $res = wp_trash_post( $job_id );
            if ( ! $res ) {
                self::json_error( __( 'Nu am putut șterge jobul.', 'ai-suite' ), 500 );
            }
            self::json_ok();
        }
    }
}

add_action( 'init', function() {
    if ( class_exists( 'AI_Suite_Job_Posting_Pro' ) ) {
        AI_Suite_Job_Posting_Pro::boot();
    }
}, 16 );
