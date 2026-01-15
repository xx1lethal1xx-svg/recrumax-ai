<?php
/**
 * AI Suite – Dashboard PRO (Patch #5)
 *
 * Scop:
 * - KPI reale (joburi/candidați/aplicații)
 * - Activitate recentă (audit basic)
 * - Top candidați (pre-AI)
 * - System status + quick actions
 *
 * ADD-ONLY: nu schimbă modele de date, doar consumă CPT/meta existente.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'aisuite_dashboard_pro_get_counts' ) ) {
    /**
     * KPI counts (fail-safe).
     */
    function aisuite_dashboard_pro_get_counts() {
        $counts = array(
            'jobs_active'      => 0,
            'candidates_total' => 0,
            'applications_total' => 0,
            'applications_by_status' => array(),
            'avg_apps_per_job' => 0,
        );

        // Jobs active (published).
        $job_counts = wp_count_posts( 'rmax_job' );
        if ( $job_counts && isset( $job_counts->publish ) ) {
            $counts['jobs_active'] = (int) $job_counts->publish;
        }

        // Candidates total (any status, but UI uses publish).
        $cand_counts = wp_count_posts( 'rmax_candidate' );
        if ( $cand_counts ) {
            $total = 0;
            foreach ( (array) $cand_counts as $st => $nr ) {
                $total += (int) $nr;
            }
            $counts['candidates_total'] = (int) $total;
        }

        // Applications total.
        $app_counts = wp_count_posts( 'rmax_application' );
        if ( $app_counts ) {
            $total = 0;
            foreach ( (array) $app_counts as $st => $nr ) {
                $total += (int) $nr;
            }
            $counts['applications_total'] = (int) $total;
        }

        // Status distribution based on registered status list, fallback to common keys.
        $statuses = array();
        if ( function_exists( 'ai_suite_app_statuses' ) ) {
            $statuses = (array) ai_suite_app_statuses();
        }
        if ( empty( $statuses ) ) {
            $statuses = array(
                'nou'        => __( 'Nou', 'ai-suite' ),
                'in_analiza' => __( 'În analiză', 'ai-suite' ),
                'interviu'   => __( 'Interviu', 'ai-suite' ),
                'acceptat'   => __( 'Acceptat', 'ai-suite' ),
                'respins'    => __( 'Respins', 'ai-suite' ),
            );
        }

        foreach ( $statuses as $key => $label ) {
            $counts['applications_by_status'][ (string) $key ] = 0;
        }

        // Count per status using lightweight queries.
        foreach ( array_keys( $counts['applications_by_status'] ) as $st_key ) {
            $q = new WP_Query( array(
                'post_type'      => 'rmax_application',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array(
                        'key'   => '_application_status',
                        'value' => $st_key,
                    ),
                ),
            ) );
            // found_posts includes total.
            $counts['applications_by_status'][ $st_key ] = isset( $q->found_posts ) ? (int) $q->found_posts : 0;
            wp_reset_postdata();
        }

        // Average apps per job.
        $jobs_active = max( 0, (int) $counts['jobs_active'] );
        if ( $jobs_active > 0 ) {
            $counts['avg_apps_per_job'] = round( (float) $counts['applications_total'] / (float) $jobs_active, 2 );
        }

        return $counts;
    }
}

if ( ! function_exists( 'aisuite_dashboard_pro_recent_activity' ) ) {
    /**
     * Recent activity list.
     */
    function aisuite_dashboard_pro_recent_activity( $limit = 10 ) {
        $limit = max( 1, min( 30, (int) $limit ) );

        $items = array();
        $types = array(
            'rmax_application' => __( 'Aplicație', 'ai-suite' ),
            'rmax_candidate'   => __( 'Candidat', 'ai-suite' ),
            'rmax_job'         => __( 'Job', 'ai-suite' ),
        );

        $posts = get_posts( array(
            'post_type'      => array_keys( $types ),
            'post_status'    => 'any',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        foreach ( $posts as $p ) {
            $pt = (string) $p->post_type;
            $label = isset( $types[ $pt ] ) ? $types[ $pt ] : $pt;
            $items[] = array(
                'id'    => (int) $p->ID,
                'type'  => $pt,
                'label' => $label,
                'title' => $p->post_title,
                'date'  => $p->post_date,
                'url'   => get_edit_post_link( $p->ID, 'raw' ),
            );
        }

        return $items;
    }
}

if ( ! function_exists( 'aisuite_dashboard_pro_top_candidates' ) ) {
    /**
     * Top candidates based on application score.
     */
    function aisuite_dashboard_pro_top_candidates( $limit = 6 ) {
        $limit = max( 3, min( 20, (int) $limit ) );

        $apps = get_posts( array(
            'post_type'      => 'rmax_application',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'meta_key'       => '_application_score',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ) );

        $rows = array();
        foreach ( $apps as $app_id ) {
            $app_id = (int) $app_id;
            $score = (string) get_post_meta( $app_id, '_application_score', true );
            $cand_id = (int) get_post_meta( $app_id, '_application_candidate_id', true );
            $job_id  = (int) get_post_meta( $app_id, '_application_job_id', true );

            $rows[] = array(
                'application_id' => $app_id,
                'score'          => $score !== '' ? $score : '—',
                'candidate_id'   => $cand_id,
                'candidate_name' => $cand_id ? get_the_title( $cand_id ) : __( 'Candidat necunoscut', 'ai-suite' ),
                'job_id'         => $job_id,
                'job_title'      => $job_id ? get_the_title( $job_id ) : __( 'Job necunoscut', 'ai-suite' ),
                'url'            => admin_url( 'admin.php?page=ai-suite&tab=applications&app_id=' . $app_id ),
            );
        }

        /**
         * Hook: permite AI/alte module să ajusteze lista.
         */
        $rows = apply_filters( 'aisuite_dashboard_top_candidates', $rows );

        return $rows;
    }
}

if ( ! function_exists( 'aisuite_dashboard_pro_system_status' ) ) {
    /**
     * System status checks (non-fatal).
     */
    function aisuite_dashboard_pro_system_status() {
        $checks = array();

        $checks[] = array(
            'label' => __( 'Versiune plugin', 'ai-suite' ),
            'value' => defined( 'AI_SUITE_VER' ) ? AI_SUITE_VER : '—',
            'ok'    => true,
        );

        // Demo flag.
        $demo = 0;
        if ( function_exists( 'aisuite_get_settings' ) ) {
            $s = (array) aisuite_get_settings();
            $demo = ! empty( $s['demo_enabled'] ) ? 1 : 0;
        }
        $checks[] = array(
            'label' => __( 'Mod demo', 'ai-suite' ),
            'value' => $demo ? __( 'Activ', 'ai-suite' ) : __( 'Inactiv', 'ai-suite' ),
            'ok'    => true,
        );

        // CPTs exist.
        $cpts = array(
            'rmax_job'         => __( 'CPT Joburi', 'ai-suite' ),
            'rmax_candidate'   => __( 'CPT Candidați', 'ai-suite' ),
            'rmax_application' => __( 'CPT Aplicații', 'ai-suite' ),
        );
        foreach ( $cpts as $pt => $label ) {
            $obj = post_type_exists( $pt );
            $checks[] = array(
                'label' => $label,
                'value' => $obj ? __( 'OK', 'ai-suite' ) : __( 'Lipsește', 'ai-suite' ),
                'ok'    => (bool) $obj,
            );
        }

        // Capability.
        $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_ai_suite';
        $checks[] = array(
            'label' => __( 'Permisiune (capability)', 'ai-suite' ),
            'value' => $cap,
            'ok'    => true,
        );

        return $checks;
    }
}

if ( ! function_exists( 'aisuite_dashboard_pro_render' ) ) {
    /**
     * Render Dashboard PRO markup.
     */
    function aisuite_dashboard_pro_render() {
        if ( ! current_user_can( function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options' ) ) {
            return;
        }

        $counts   = aisuite_dashboard_pro_get_counts();
        $activity = aisuite_dashboard_pro_recent_activity( 10 );
        $top      = aisuite_dashboard_pro_top_candidates( 6 );
        $checks   = aisuite_dashboard_pro_system_status();

        // URLs.
        $url_jobs         = admin_url( 'admin.php?page=ai-suite&tab=jobs' );
        $url_candidates   = admin_url( 'admin.php?page=ai-suite&tab=candidates' );
        $url_apps         = admin_url( 'admin.php?page=ai-suite&tab=applications' );
        $url_settings     = admin_url( 'admin.php?page=ai-suite&tab=settings' );

        // Export URLs.
        $export_jobs_url = wp_nonce_url(
            add_query_arg( array( 'action' => 'ai_suite_export_jobs_csv' ), admin_url( 'admin-post.php' ) ),
            'ai_suite_export_jobs_csv'
        );
        $export_cands_url = wp_nonce_url(
            add_query_arg( array( 'action' => 'ai_suite_export_candidates_csv' ), admin_url( 'admin-post.php' ) ),
            'ai_suite_export_candidates_csv'
        );
        $export_apps_url = wp_nonce_url(
            add_query_arg( array( 'action' => 'ai_suite_export_applications_csv' ), admin_url( 'admin-post.php' ) ),
            'ai_suite_export_csv'
        );

        echo '<div class="ai-dashboard-grid">';

        // KPI cards.
        echo '<a class="ai-kpi" href="' . esc_url( $url_jobs ) . '">';
        echo '<div class="ai-kpi-label">' . esc_html__( 'Joburi active', 'ai-suite' ) . '</div>';
        echo '<div class="ai-kpi-value">' . esc_html( (string) (int) $counts['jobs_active'] ) . '</div>';
        echo '</a>';

        echo '<a class="ai-kpi" href="' . esc_url( $url_candidates ) . '">';
        echo '<div class="ai-kpi-label">' . esc_html__( 'Candidați', 'ai-suite' ) . '</div>';
        echo '<div class="ai-kpi-value">' . esc_html( (string) (int) $counts['candidates_total'] ) . '</div>';
        echo '</a>';

        echo '<a class="ai-kpi" href="' . esc_url( $url_apps ) . '">';
        echo '<div class="ai-kpi-label">' . esc_html__( 'Aplicații', 'ai-suite' ) . '</div>';
        echo '<div class="ai-kpi-value">' . esc_html( (string) (int) $counts['applications_total'] ) . '</div>';
        echo '</a>';

        echo '<div class="ai-kpi">';
        echo '<div class="ai-kpi-label">' . esc_html__( 'Medie aplicații / job', 'ai-suite' ) . '</div>';
        echo '<div class="ai-kpi-value">' . esc_html( (string) $counts['avg_apps_per_job'] ) . '</div>';
        echo '</div>';

        echo '</div>';

        // Layout 2 columns.
        echo '<div class="ai-dashboard-cols">';

        // Left column.
        echo '<div class="ai-dashboard-col">';

        // Status distribution.
        echo '<div class="ai-card">';
        echo '<h2 style="margin:0 0 10px;">' . esc_html__( 'Distribuție status aplicații', 'ai-suite' ) . '</h2>';
        echo '<div class="ai-status-grid">';
        $status_labels = function_exists( 'ai_suite_app_statuses' ) ? (array) ai_suite_app_statuses() : array();
        foreach ( (array) $counts['applications_by_status'] as $k => $nr ) {
            $lab = isset( $status_labels[ $k ] ) ? $status_labels[ $k ] : $k;
            $link = add_query_arg( array( 'page' => 'ai-suite', 'tab' => 'applications', 'status' => $k ), admin_url( 'admin.php' ) );
            echo '<a class="ai-status-pill" href="' . esc_url( $link ) . '">';
            echo '<span class="ai-status-name">' . esc_html( $lab ) . '</span>';
            echo '<span class="ai-status-count">' . esc_html( (string) (int) $nr ) . '</span>';
            echo '</a>';
        }
        echo '</div>';
        echo '</div>';

        // Activity.
        echo '<div class="ai-card">';
        echo '<h2 style="margin:0 0 10px;">' . esc_html__( 'Activitate recentă', 'ai-suite' ) . '</h2>';
        if ( empty( $activity ) ) {
            echo '<p class="description">' . esc_html__( 'Nu există activitate recentă. Poți genera date demo din Setări.', 'ai-suite' ) . '</p>';
        } else {
            echo '<ul class="ai-activity">';
            foreach ( $activity as $it ) {
                $dt = ! empty( $it['date'] ) ? mysql2date( 'd.m.Y H:i', $it['date'] ) : '';
                $title = $it['title'] ? $it['title'] : __( '(fără titlu)', 'ai-suite' );
                echo '<li>';
                echo '<span class="ai-activity-type">' . esc_html( $it['label'] ) . '</span> ';
                if ( ! empty( $it['url'] ) ) {
                    echo '<a href="' . esc_url( $it['url'] ) . '">' . esc_html( $title ) . '</a>';
                } else {
                    echo esc_html( $title );
                }
                if ( $dt ) {
                    echo ' <span class="ai-activity-date">' . esc_html( $dt ) . '</span>';
                }
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';

        echo '</div>'; // left col

        // Right column.
        echo '<div class="ai-dashboard-col">';

        // Top candidates.
        echo '<div class="ai-card">';
        echo '<h2 style="margin:0 0 10px;">' . esc_html__( 'Top candidați (scor)', 'ai-suite' ) . '</h2>';
        if ( empty( $top ) ) {
            echo '<p class="description">' . esc_html__( 'Nu există aplicații cu scor. Generează demo sau completează scoruri pe aplicații.', 'ai-suite' ) . '</p>';
        } else {
            echo '<table class="widefat striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__( 'Candidat', 'ai-suite' ) . '</th>';
            echo '<th>' . esc_html__( 'Job', 'ai-suite' ) . '</th>';
            echo '<th style="width:90px;">' . esc_html__( 'Scor', 'ai-suite' ) . '</th>';
            echo '</tr></thead><tbody>';
            foreach ( $top as $row ) {
                echo '<tr>';
                echo '<td><a href="' . esc_url( $row['url'] ) . '">' . esc_html( $row['candidate_name'] ) . '</a></td>';
                echo '<td>' . esc_html( $row['job_title'] ) . '</td>';
                echo '<td><span class="ai-badge">' . esc_html( (string) $row['score'] ) . '</span></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';

        // System status.
        echo '<div class="ai-card">';
        echo '<h2 style="margin:0 0 10px;">' . esc_html__( 'Status sistem', 'ai-suite' ) . '</h2>';
        echo '<ul class="ai-sys">';
        foreach ( $checks as $c ) {
            $cls = ! empty( $c['ok'] ) ? 'ai-ok' : 'ai-bad';
            echo '<li><span class="' . esc_attr( $cls ) . '">●</span> <strong>' . esc_html( $c['label'] ) . ':</strong> ' . esc_html( $c['value'] ) . '</li>';
        }
        echo '</ul>';
        echo '</div>';

        // Quick actions.
        echo '<div class="ai-card">';
        echo '<h2 style="margin:0 0 10px;">' . esc_html__( 'Acțiuni rapide', 'ai-suite' ) . '</h2>';
        echo '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">';
        echo '<a class="button button-primary" href="' . esc_url( $url_settings ) . '">' . esc_html__( 'Setări', 'ai-suite' ) . '</a>';
        echo '<a class="button" href="' . esc_url( $export_jobs_url ) . '">' . esc_html__( 'Export CSV Joburi', 'ai-suite' ) . '</a>';
        echo '<a class="button" href="' . esc_url( $export_cands_url ) . '">' . esc_html__( 'Export CSV Candidați', 'ai-suite' ) . '</a>';
        echo '<a class="button" href="' . esc_url( $export_apps_url ) . '">' . esc_html__( 'Export CSV Aplicații', 'ai-suite' ) . '</a>';
        echo '</div>';
        echo '<p class="description" style="margin-top:10px;">' . esc_html__( 'Pentru generare/ștergere date demo, folosește secțiunea din Setări (include protecție nonce).', 'ai-suite' ) . '</p>';
        echo '</div>';

        echo '</div>'; // right col

        echo '</div>'; // cols
    }
}
