<?php
/**
 * AI Suite – Demo/Seed data (Recruitment)
 *
 * NOTE:
 * - Add-only mindset: we keep this file as the single source for demo seeding.
 * - We always tag seeded content with meta: rmax_is_demo = 1
 * - We never hard-delete non-demo content.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'aisuite_demo_is_enabled' ) ) {
    function aisuite_demo_is_enabled() {
        $settings = function_exists( 'aisuite_get_settings' ) ? aisuite_get_settings() : array();
        return ! empty( $settings['demo_enabled'] );
    }
}

if ( ! function_exists( 'aisuite_demo_safe_title' ) ) {
    function aisuite_demo_safe_title( $title, $prefix = 'DEMO' ) {
        $title = trim( (string) $title );
        if ( $title === '' ) {
            $title = 'Item';
        }
        return sprintf( '%s — %s', $prefix, $title );
    }
}

if ( ! function_exists( 'aisuite_seed_demo_data' ) ) {
    /**
     * Seed demo jobs, candidates and applications.
     *
     * @param bool $force If true, re-generate even if demo data already exists.
     * @return array Summary counts.
     */
    function aisuite_seed_demo_data( $force = false ) {

        // Ensure CPTs are registered (important when called during activation).
        if ( function_exists( 'aisuite_register_recruitment_cpts' ) ) {
            aisuite_register_recruitment_cpts();
        }
        // v1.7.7: ensure company CPT exists for demo seeding.
        if ( function_exists( 'aisuite_register_company_cpt' ) ) {
            aisuite_register_company_cpt();
        }

        $summary = array(
            'jobs_created'         => 0,
            'candidates_created'   => 0,
            'applications_created' => 0,
            'jobs_existing'        => 0,
            'candidates_existing'  => 0,
            'applications_existing'=> 0,
        );

        // If demo content exists and not forcing, do nothing.
        $demo_existing = get_posts( array(
            'post_type'      => array( 'rmax_job', 'rmax_candidate', 'rmax_application' ),
            'posts_per_page' => 1,
            'post_status'    => 'any',
            'meta_key'       => 'rmax_is_demo',
            'meta_value'     => '1',
            'fields'         => 'ids',
        ) );

        if ( ! empty( $demo_existing ) && ! $force ) {
            // Count existing demo items (for UI feedback).
            $summary['jobs_existing'] = (int) wp_count_posts( 'rmax_job' )->publish;
            $summary['candidates_existing'] = (int) wp_count_posts( 'rmax_candidate' )->publish;
            $summary['applications_existing'] = (int) wp_count_posts( 'rmax_application' )->publish;
            return $summary;
        }

        // v1.7.7: Create/reuse a demo company and attach demo jobs to it.
        $demo_company_id = 0;
        if ( post_type_exists( 'rmax_company' ) ) {
            $existing_company = get_posts( array(
                'post_type'      => 'rmax_company',
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'meta_key'       => '_demo_key',
                'meta_value'     => 'company_1',
                'fields'         => 'ids',
            ) );
            if ( ! empty( $existing_company ) ) {
                $demo_company_id = (int) $existing_company[0];
            } else {
                $demo_company_id = (int) wp_insert_post( array(
                    'post_type'   => 'rmax_company',
                    'post_status' => 'publish',
                    'post_title'  => aisuite_demo_safe_title( 'Companie Demo (Client)', 'DEMO COMPANY' ),
                    'meta_input'  => array(
                        'rmax_is_demo'            => '1',
                        '_demo_key'               => 'company_1',
                        '_company_contact_email'  => 'client.demo@example.com',
                        '_company_team_emails'    => array( 'client.demo@example.com' ),
                        '_company_max_team'       => 3,
                    ),
                ) );
            }
        }

        // Create or reuse 3 demo jobs.
        $jobs_payload = array(
            array(
                'title'   => 'Specialist Reparații Caroserie (Autoschade)',
                'content' => 'Căutăm un specialist în reparații caroserie pentru proiecte stabile în Olanda. Cazare, transport, contract clar.',
                'dept'    => 'Auto',
                'loc'     => 'Olanda',
            ),
            array(
                'title'   => 'Sudor MIG/MAG – Producție industrială',
                'content' => 'Post stabil, lucrări în hală, program fix. Experiența pe S355 constituie avantaj.',
                'dept'    => 'Industrial',
                'loc'     => 'Baia Mare',
            ),
            array(
                'title'   => 'Operator CNC – Debitare / Prelucrări',
                'content' => 'Căutăm operator CNC pentru debitare și prelucrări. Se oferă instruire pe utilaje.',
                'dept'    => 'CNC',
                'loc'     => 'Cluj',
            ),
        );

        $job_ids = array();

        foreach ( $jobs_payload as $i => $job ) {
            $existing = get_posts( array(
                'post_type'      => 'rmax_job',
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'meta_key'       => '_demo_key',
                'meta_value'     => 'job_' . ( $i + 1 ),
                'fields'         => 'ids',
            ) );

            if ( ! empty( $existing ) ) {
                $job_ids[] = (int) $existing[0];
                continue;
            }

            $job_id = wp_insert_post( array(
                'post_type'    => 'rmax_job',
                'post_status'  => 'publish',
                'post_title'   => aisuite_demo_safe_title( $job['title'], 'DEMO JOB' ),
                'post_content' => $job['content'],
                'meta_input'   => array(
                    'rmax_is_demo' => '1',
                    '_demo_key'    => 'job_' . ( $i + 1 ),
                    '_job_status'  => 'open',
                    // v1.7.7: attach to demo company when available.
                    '_job_company_id' => $demo_company_id ? (string) $demo_company_id : '',
                ),
            ) );

            if ( $job_id && ! is_wp_error( $job_id ) ) {
                $job_ids[] = (int) $job_id;
                $summary['jobs_created']++;

                // Set taxonomies if available.
                if ( taxonomy_exists( 'job_department' ) && ! empty( $job['dept'] ) ) {
                    wp_set_object_terms( $job_id, array( $job['dept'] ), 'job_department', false );
                }
                if ( taxonomy_exists( 'job_location' ) && ! empty( $job['loc'] ) ) {
                    wp_set_object_terms( $job_id, array( $job['loc'] ), 'job_location', false );
                }
            }
        }

        // v1.7.7: ensure re-used demo jobs are attached to demo company as well.
        if ( $demo_company_id && ! empty( $job_ids ) ) {
            foreach ( $job_ids as $jid ) {
                $jid = (int) $jid;
                if ( 'rmax_job' !== get_post_type( $jid ) ) {
                    continue;
                }
                $current = (string) get_post_meta( $jid, '_job_company_id', true );
                if ( $current !== (string) $demo_company_id ) {
                    update_post_meta( $jid, '_job_company_id', (string) $demo_company_id );
                }
            }
        }

        // Create 4 demo candidates.
        $candidates_payload = array(
            array( 'name' => 'Ionuț Pop',      'email' => 'ionut.pop@example.com',      'phone' => '0740 000 111' ),
            array( 'name' => 'Mihai Dobre',    'email' => 'mihai.dobre@example.com',    'phone' => '0740 000 222' ),
            array( 'name' => 'Andreea Marin',  'email' => 'andreea.marin@example.com',  'phone' => '0740 000 333' ),
            array( 'name' => 'Cristina Ilie',  'email' => 'cristina.ilie@example.com',  'phone' => '0740 000 444' ),
        );

        $candidate_ids = array();

        foreach ( $candidates_payload as $i => $cand ) {
            $existing = get_posts( array(
                'post_type'      => 'rmax_candidate',
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'meta_key'       => '_demo_key',
                'meta_value'     => 'cand_' . ( $i + 1 ),
                'fields'         => 'ids',
            ) );

            if ( ! empty( $existing ) ) {
                $candidate_ids[] = (int) $existing[0];
                continue;
            }

            $cand_id = wp_insert_post( array(
                'post_type'   => 'rmax_candidate',
                'post_status' => 'publish',
                'post_title'  => aisuite_demo_safe_title( $cand['name'], 'DEMO CAND' ),
                'meta_input'  => array(
                    'rmax_is_demo'      => '1',
                    '_demo_key'         => 'cand_' . ( $i + 1 ),
                    '_candidate_email'  => $cand['email'],
                    '_candidate_phone'  => $cand['phone'],
                    '_candidate_source' => 'Demo',
                ),
            ) );

            if ( $cand_id && ! is_wp_error( $cand_id ) ) {
                $candidate_ids[] = (int) $cand_id;
                $summary['candidates_created']++;
            }
        }

        // Create 6 demo applications across jobs.
        $statuses = array( 'new', 'review', 'interview', 'offer', 'rejected' );
        $tags     = array( 'rapid', 'senior', 'recomandat', 'follow-up' );

        $app_pairs = array(
            array( 0, 0, 'new' ),
            array( 0, 1, 'review' ),
            array( 1, 2, 'interview' ),
            array( 1, 3, 'offer' ),
            array( 2, 1, 'rejected' ),
            array( 2, 0, 'review' ),
        );

        foreach ( $app_pairs as $idx => $pair ) {
            $job_index  = (int) $pair[0];
            $cand_index = (int) $pair[1];
            $status     = sanitize_key( $pair[2] );

            $job_id  = isset( $job_ids[ $job_index ] ) ? (int) $job_ids[ $job_index ] : 0;
            $cand_id = isset( $candidate_ids[ $cand_index ] ) ? (int) $candidate_ids[ $cand_index ] : 0;

            if ( $job_id <= 0 || $cand_id <= 0 ) {
                continue;
            }

            $existing = get_posts( array(
                'post_type'      => 'rmax_application',
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'meta_key'       => '_demo_key',
                'meta_value'     => 'app_' . ( $idx + 1 ),
                'fields'         => 'ids',
            ) );

            if ( ! empty( $existing ) ) {
                continue;
            }

            $title = sprintf(
                'Aplicație: %s → %s',
                wp_strip_all_tags( get_the_title( $cand_id ) ),
                wp_strip_all_tags( get_the_title( $job_id ) )
            );

            $app_id = wp_insert_post( array(
                'post_type'   => 'rmax_application',
                'post_status' => 'publish',
                'post_title'  => aisuite_demo_safe_title( $title, 'DEMO APP' ),
                'meta_input'  => array(
                    'rmax_is_demo'                 => '1',
                    '_demo_key'                    => 'app_' . ( $idx + 1 ),
                    '_application_job_id'          => $job_id,
                    '_application_candidate_id'    => $cand_id,
                    '_application_status'          => $status,
                    '_application_message'         => 'Acesta este un mesaj demo. Candidat interesat și disponibil rapid.',
                    '_application_tags'            => $tags[ $idx % count( $tags ) ],
                    '_application_score'           => (string) ( 72 + ( $idx * 3 ) ),
                    '_application_created_by'      => 0,
                ),
            ) );

            if ( $app_id && ! is_wp_error( $app_id ) ) {
                $summary['applications_created']++;

                // Timeline starter
                if ( function_exists( 'ai_suite_add_application_timeline' ) ) {
                    ai_suite_add_application_timeline( $app_id, 'seeded', array( 'note' => 'Aplicatie demo generata.' ) );
                }
            }
        }

        return $summary;
    }
}

if ( ! function_exists( 'aisuite_clear_demo_data' ) ) {
    /**
     * Delete demo-only data (jobs/candidates/applications tagged with rmax_is_demo = 1).
     *
     * @return array Summary counts.
     */
    function aisuite_clear_demo_data() {
        $summary = array(
            'jobs_deleted'         => 0,
            'candidates_deleted'   => 0,
            'applications_deleted' => 0,
        );

        $types = array( 'rmax_application', 'rmax_candidate', 'rmax_job' );

        foreach ( $types as $pt ) {
            $ids = get_posts( array(
                'post_type'      => $pt,
                'posts_per_page' => 300,
                'post_status'    => 'any',
                'meta_key'       => 'rmax_is_demo',
                'meta_value'     => '1',
                'fields'         => 'ids',
            ) );

            foreach ( $ids as $id ) {
                $ok = wp_delete_post( (int) $id, true );
                if ( $ok ) {
                    if ( 'rmax_job' === $pt ) {
                        $summary['jobs_deleted']++;
                    } elseif ( 'rmax_candidate' === $pt ) {
                        $summary['candidates_deleted']++;
                    } elseif ( 'rmax_application' === $pt ) {
                        $summary['applications_deleted']++;
                    }
                }
            }
        }

        return $summary;
    }
}

/**
 * Activation: seed demo ONLY if enabled.
 */
if ( ! function_exists( 'aisuite_demo_seed_on_activation' ) ) {
    function aisuite_demo_seed_on_activation() {
        if ( aisuite_demo_is_enabled() ) {
            aisuite_seed_demo_data( false );
        }
    }
}
register_activation_hook( AI_SUITE_FILE, 'aisuite_demo_seed_on_activation' );

/**
 * Demo users for portals (WP Users) – optional.
 *
 * Why:
 * - Seeded demo data creates CPT posts (jobs/candidates/applications), but does not create WordPress users.
 * - For quick preview on staging, we provide one-click demo accounts (candidate + company).
 */
if ( ! function_exists( 'aisuite_seed_demo_portal_users' ) ) {
    function aisuite_seed_demo_portal_users( $force = false ) {
        // Ensure demo content exists so we can link users to CPT entities.
        if ( function_exists( 'aisuite_seed_demo_data' ) ) {
            aisuite_seed_demo_data( false );
        }

        $summary = array(
            'candidate_created' => 0,
            'company_created'   => 0,
            'candidate_user_id' => 0,
            'company_user_id'   => 0,
        );

        $opt_key  = 'ai_suite_demo_accounts';
        $current  = get_option( $opt_key, array() );
        $accounts = is_array( $current ) ? $current : array();

        // Create or reset candidate user.
        $cand_login = 'candidate_demo';
        $cand_email = 'candidate.demo@example.com';
        $cand_pass  = wp_generate_password( 14, true, true );

        $cand_user_id = username_exists( $cand_login );
        if ( ! $cand_user_id ) {
            $cand_user_id = email_exists( $cand_email );
        }
        if ( $cand_user_id && $force ) {
            // Reset password.
            wp_set_password( $cand_pass, (int) $cand_user_id );
        }
        if ( ! $cand_user_id ) {
            $cand_user_id = wp_insert_user( array(
                'user_login'   => $cand_login,
                'user_pass'    => $cand_pass,
                'user_email'   => $cand_email,
                'display_name' => 'Demo Candidate',
                'role'         => 'aisuite_candidate',
            ) );
            if ( ! is_wp_error( $cand_user_id ) ) {
                $summary['candidate_created'] = 1;
            } else {
                $cand_user_id = 0;
            }
        } else {
            // Ensure role.
            $u = get_user_by( 'id', (int) $cand_user_id );
            if ( $u instanceof WP_User && ! in_array( 'aisuite_candidate', (array) $u->roles, true ) ) {
                $u->add_role( 'aisuite_candidate' );
            }
        }

        // Create or reset company user.
        $comp_login = 'company_demo';
        $comp_email = 'company.demo@example.com';
        $comp_pass  = wp_generate_password( 14, true, true );

        $comp_user_id = username_exists( $comp_login );
        if ( ! $comp_user_id ) {
            $comp_user_id = email_exists( $comp_email );
        }
        if ( $comp_user_id && $force ) {
            wp_set_password( $comp_pass, (int) $comp_user_id );
        }
        if ( ! $comp_user_id ) {
            $comp_user_id = wp_insert_user( array(
                'user_login'   => $comp_login,
                'user_pass'    => $comp_pass,
                'user_email'   => $comp_email,
                'display_name' => 'Demo Company',
                'role'         => 'aisuite_company',
            ) );
            if ( ! is_wp_error( $comp_user_id ) ) {
                $summary['company_created'] = 1;
            } else {
                $comp_user_id = 0;
            }
        } else {
            $u = get_user_by( 'id', (int) $comp_user_id );
            if ( $u instanceof WP_User && ! in_array( 'aisuite_company', (array) $u->roles, true ) ) {
                $u->add_role( 'aisuite_company' );
            }
        }

        // Link candidate user to first demo candidate CPT.
        if ( $cand_user_id ) {
            $cand_posts = get_posts( array(
                'post_type'      => 'rmax_candidate',
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'meta_key'       => '_demo_key',
                'meta_value'     => 'cand_1',
                'fields'         => 'ids',
            ) );
            if ( ! empty( $cand_posts[0] ) ) {
                $cand_post_id = (int) $cand_posts[0];
                update_post_meta( $cand_post_id, '_candidate_user_id', (int) $cand_user_id );
                update_user_meta( (int) $cand_user_id, '_ai_suite_candidate_id', (int) $cand_post_id );
            }
        }

        // Link company user to demo company CPT (created by seeding).
        if ( $comp_user_id ) {
            $comp_posts = get_posts( array(
                'post_type'      => 'rmax_company',
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'meta_key'       => '_demo_key',
                'meta_value'     => 'company_1',
                'fields'         => 'ids',
            ) );
            if ( ! empty( $comp_posts[0] ) ) {
                $comp_post_id = (int) $comp_posts[0];
                update_post_meta( $comp_post_id, '_company_user_id', (int) $comp_user_id );
                update_user_meta( (int) $comp_user_id, '_ai_suite_company_id', (int) $comp_post_id );
            }
        }

        // Persist credentials for quick copy (admin-only; demo use).
        $accounts['candidate'] = array(
            'user_login' => $cand_login,
            'user_email' => $cand_email,
            'password'   => ( $force || empty( $accounts['candidate']['password'] ) ) ? $cand_pass : $accounts['candidate']['password'],
            'user_id'    => (int) $cand_user_id,
        );
        $accounts['company'] = array(
            'user_login' => $comp_login,
            'user_email' => $comp_email,
            'password'   => ( $force || empty( $accounts['company']['password'] ) ) ? $comp_pass : $accounts['company']['password'],
            'user_id'    => (int) $comp_user_id,
        );
        $accounts['updated_at'] = time();
        update_option( $opt_key, $accounts, false );

        $summary['candidate_user_id'] = (int) $cand_user_id;
        $summary['company_user_id']   = (int) $comp_user_id;

        return $summary;
    }
}

if ( ! function_exists( 'aisuite_get_demo_portal_accounts' ) ) {
    function aisuite_get_demo_portal_accounts() {
        $opt = get_option( 'ai_suite_demo_accounts', array() );
        return is_array( $opt ) ? $opt : array();
    }
}

if ( ! function_exists( 'aisuite_clear_demo_portal_users' ) ) {
    function aisuite_clear_demo_portal_users() {
        if ( ! function_exists( 'wp_delete_user' ) ) {
            require_once ABSPATH . 'wp-admin/includes/user.php';
        }

        $accounts = aisuite_get_demo_portal_accounts();
        $deleted = 0;

        foreach ( array( 'candidate', 'company' ) as $k ) {
            if ( ! empty( $accounts[ $k ]['user_id'] ) ) {
                $uid = (int) $accounts[ $k ]['user_id'];
                if ( $uid > 0 ) {
                    $ok = wp_delete_user( $uid );
                    if ( $ok ) {
                        $deleted++;
                    }
                }
            } elseif ( ! empty( $accounts[ $k ]['user_login'] ) ) {
                $uid = username_exists( (string) $accounts[ $k ]['user_login'] );
                if ( $uid ) {
                    $ok = wp_delete_user( (int) $uid );
                    if ( $ok ) {
                        $deleted++;
                    }
                }
            }
        }

        delete_option( 'ai_suite_demo_accounts' );

        return array( 'users_deleted' => (int) $deleted );
    }
}
