<?php
/**
 * Applications management tab (v1.5).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Vizualizare detaliu aplicație.
$view_app_id = isset( $_GET['app_id'] ) ? absint( wp_unslash( $_GET['app_id'] ) ) : 0;
if ( $view_app_id && 'rmax_application' === get_post_type( $view_app_id ) ) {
    require AI_SUITE_DIR . 'includes/admin/views/tab-application-view.php';
    return;
}

$statuses = function_exists( 'ai_suite_app_statuses' ) ? (array) ai_suite_app_statuses() : array(
    'pending' => __( 'În așteptare', 'ai-suite' ),
);

$filter_job_id = isset( $_GET['job_id'] ) ? absint( wp_unslash( $_GET['job_id'] ) ) : 0;
$filter_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
$view_mode     = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'tabel';
$search        = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
$tag_filter    = isset( $_GET['tag'] ) ? sanitize_text_field( wp_unslash( $_GET['tag'] ) ) : '';

echo '<div class="ai-card">';
echo '<h2>' . esc_html__( 'Aplicații', 'ai-suite' ) . '</h2>';

// View switch
$base_args = array(
    'page'   => 'ai-suite',
    'tab'    => 'applications',
    'job_id' => $filter_job_id,
    'status' => $filter_status,
    'q'      => $search,
    'tag'    => $tag_filter,
);
$url_table  = add_query_arg( array_merge( $base_args, array( 'view' => 'tabel' ) ), admin_url( 'admin.php' ) );
$url_kanban = add_query_arg( array_merge( $base_args, array( 'view' => 'kanban' ) ), admin_url( 'admin.php' ) );

echo '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin:8px 0 14px;">';
echo '<a class="button ' . ( 'tabel' === $view_mode ? 'button-primary' : '' ) . '" href="' . esc_url( $url_table ) . '">' . esc_html__( 'Tabel', 'ai-suite' ) . '</a>';
echo '<a class="button ' . ( 'kanban' === $view_mode ? 'button-primary' : '' ) . '" href="' . esc_url( $url_kanban ) . '">' . esc_html__( 'Kanban', 'ai-suite' ) . '</a>';
echo '<span class="description">' . esc_html__( 'Kanban permite mutarea aplicațiilor între statusuri prin drag & drop.', 'ai-suite' ) . '</span>';
echo '</div>';

// Notices
$notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';
if ( 'bulk_ok' === $notice ) {
    echo '<div class="updated notice"><p>' . esc_html__( 'Statusul aplicațiilor a fost actualizat.', 'ai-suite' ) . '</p></div>';
} elseif ( 'bulk_missing' === $notice ) {
    echo '<div class="error notice"><p>' . esc_html__( 'Selectează aplicații și alege un status.', 'ai-suite' ) . '</p></div>';
}

// Filter bar
echo '<form method="get" action="">';
echo '<input type="hidden" name="page" value="ai-suite" />';
echo '<input type="hidden" name="tab" value="applications" />';
echo '<input type="hidden" name="view" value="' . esc_attr( $view_mode ) . '" />';

// Jobs dropdown
$jobs = get_posts( array(
    'post_type'      => 'rmax_job',
    'posts_per_page' => 200,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
) );

echo '<div style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin:12px 0 16px;">';
echo '<div><label><strong>' . esc_html__( 'Job', 'ai-suite' ) . '</strong></label><br />';
echo '<select name="job_id">';
echo '<option value="0">' . esc_html__( 'Toate joburile', 'ai-suite' ) . '</option>';
foreach ( $jobs as $job ) {
    echo '<option value="' . esc_attr( $job->ID ) . '" ' . selected( $filter_job_id, (int) $job->ID, false ) . '>' . esc_html( $job->post_title ) . '</option>';
}
echo '</select></div>';

// Status dropdown
echo '<div><label><strong>' . esc_html__( 'Status', 'ai-suite' ) . '</strong></label><br />';
echo '<select name="status">';
echo '<option value="">' . esc_html__( 'Toate statusurile', 'ai-suite' ) . '</option>';
foreach ( $statuses as $k => $label ) {
    echo '<option value="' . esc_attr( $k ) . '" ' . selected( $filter_status, (string) $k, false ) . '>' . esc_html( $label ) . '</option>';
}
echo '</select></div>';

// Căutare
echo '<div><label><strong>' . esc_html__( 'Căutare', 'ai-suite' ) . '</strong></label><br />';
echo '<input type="text" name="q" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Nume candidat / titlu...', 'ai-suite' ) . '" />';
echo '</div>';

// Filtru tag
echo '<div><label><strong>' . esc_html__( 'Tag', 'ai-suite' ) . '</strong></label><br />';
echo '<input type="text" name="tag" value="' . esc_attr( $tag_filter ) . '" placeholder="ex: urgent" />';
echo '</div>';

echo '<div><button type="submit" class="button">' . esc_html__( 'Filtrează', 'ai-suite' ) . '</button></div>';

// Export CSV
$export_url = wp_nonce_url(
    add_query_arg(
        array(
            'action' => 'ai_suite_export_applications_csv',
            'job_id' => $filter_job_id,
            'status' => $filter_status,
            'q'      => $search,
            'tag'    => $tag_filter,
        ),
        admin_url( 'admin-post.php' )
    ),
    'ai_suite_export_csv'
);

echo '<div><a class="button button-secondary" href="' . esc_url( $export_url ) . '">' . esc_html__( 'Export CSV', 'ai-suite' ) . '</a></div>';
echo '</div>';
echo '</form>';

// Query applications
$meta_query = array();
if ( $filter_job_id ) {
    $meta_query[] = array( 'key' => '_application_job_id', 'value' => (string) $filter_job_id );
}
if ( $filter_status ) {
    $meta_query[] = array( 'key' => '_application_status', 'value' => $filter_status );
}

if ( $tag_filter ) {
    // Tags are stored as an array; we use LIKE on serialized data.
    $meta_query[] = array(
        'key'     => '_application_tags',
        'value'   => $tag_filter,
        'compare' => 'LIKE',
    );
}


// AISUITE_SCOPE_RECRUITER_APPS: restrict applications to assigned companies for Recruiter/Manager.
if ( ! current_user_can( 'manage_ai_suite' ) && function_exists( 'aisuite_get_assigned_company_ids' ) && ( function_exists( 'aisuite_current_user_is_recruiter' ) && ( aisuite_current_user_is_recruiter() || aisuite_current_user_is_manager() ) ) ) {
    $assigned_company_ids = aisuite_get_assigned_company_ids( get_current_user_id() );
    if ( empty( $assigned_company_ids ) ) {
        $meta_query[] = array( 'key' => '_application_company_id', 'value' => array( 0 ), 'compare' => 'IN' );
    } else {
        $meta_query[] = array( 'key' => '_application_company_id', 'value' => $assigned_company_ids, 'compare' => 'IN' );
    }
}

$query = new WP_Query( array(
    'post_type'      => 'rmax_application',
    'posts_per_page' => 50,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
    'meta_query'     => $meta_query,
    's'              => $search,
) );

// Kanban view
if ( 'kanban' === $view_mode ) {
    if ( $query->have_posts() ) {
        $by_status = array();
        foreach ( $statuses as $k => $lab ) {
            $by_status[ $k ] = array();
        }

        while ( $query->have_posts() ) {
            $query->the_post();
            $application_id = get_the_ID();
            $candidate_id   = (int) get_post_meta( $application_id, '_application_candidate_id', true );
            $job_id         = (int) get_post_meta( $application_id, '_application_job_id', true );
            $status         = (string) get_post_meta( $application_id, '_application_status', true );
            if ( ! isset( $by_status[ $status ] ) ) {
                $by_status[ $status ] = array();
            }

            $tags = get_post_meta( $application_id, '_application_tags', true );
            if ( ! is_array( $tags ) ) {
                $tags = array();
            }

            $view_url = add_query_arg( array(
                'page'   => 'ai-suite',
                'tab'    => 'applications',
                'app_id' => $application_id,
            ), admin_url( 'admin.php' ) );

            $by_status[ $status ][] = array(
                'id'        => $application_id,
                'candidate' => $candidate_id ? get_the_title( $candidate_id ) : __( 'Candidat', 'ai-suite' ),
                'job'       => $job_id ? get_the_title( $job_id ) : '',
                'date'      => get_the_date( 'Y-m-d', $application_id ),
                'tags'      => $tags,
                'url'       => $view_url,
            );
        }
        wp_reset_postdata();

        echo '<div class="ai-kanban" data-nonce="' . esc_attr( wp_create_nonce( 'ai_suite_nonce' ) ) . '">';
        foreach ( $statuses as $k => $lab ) {
            echo '<div class="ai-kanban-col">';
            echo '<div class="ai-kanban-head">' . esc_html( $lab ) . ' <span class="ai-kanban-count">' . esc_html( (string) count( $by_status[ $k ] ) ) . '</span></div>';
            echo '<div class="ai-kanban-list" data-status="' . esc_attr( $k ) . '">';
            if ( empty( $by_status[ $k ] ) ) {
                echo '<div class="ai-kanban-empty">' . esc_html__( '—', 'ai-suite' ) . '</div>';
            } else {
                foreach ( $by_status[ $k ] as $item ) {
                    $tag_txt = ! empty( $item['tags'] ) ? implode( ', ', array_map( 'sanitize_text_field', $item['tags'] ) ) : '';
                    echo '<div class="ai-kanban-card" data-app-id="' . esc_attr( $item['id'] ) . '">';
                    echo '<a class="ai-kanban-title" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['candidate'] ) . '</a>';
                    echo '<div class="ai-kanban-meta">' . esc_html( $item['job'] ) . '</div>';
                    echo '<div class="ai-kanban-meta">' . esc_html( $item['date'] ) . '</div>';
                    if ( $tag_txt ) {
                        echo '<div class="ai-kanban-tags">' . esc_html( $tag_txt ) . '</div>';
                    }
                    echo '</div>';
                }
            }
            echo '</div>'; // list
            echo '</div>'; // col
        }
        echo '</div>'; // kanban
        echo '<p class="description" style="margin-top:10px;">' . esc_html__( 'Mută cardurile între coloane. Statusul se salvează automat.', 'ai-suite' ) . '</p>';
    } else {
        echo '<p>' . esc_html__( 'Nu au fost găsite aplicații.', 'ai-suite' ) . '</p>';
    }

    echo '</div>';
    return;
}

if ( $query->have_posts() ) {
    // Bulk form
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="ai_suite_bulk_update_applications" />';
    wp_nonce_field( 'ai_suite_bulk_apps' );

    echo '<div style="display:flex;gap:10px;align-items:center;margin:10px 0 10px;flex-wrap:wrap;">';
    echo '<label><strong>' . esc_html__( 'Acțiune bulk:', 'ai-suite' ) . '</strong></label>';
    echo '<select name="new_status">';
    echo '<option value="">' . esc_html__( 'Alege status', 'ai-suite' ) . '</option>';
    foreach ( $statuses as $k => $label ) {
        echo '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>';
    }
    echo '</select>';
    echo '<button type="submit" class="button button-primary">' . esc_html__( 'Aplică', 'ai-suite' ) . '</button>';
    echo '<span class="description">' . esc_html__( 'Bifează aplicațiile din tabel, apoi setează statusul.', 'ai-suite' ) . '</span>';
    echo '</div>';

    echo '<table class="wp-list-table widefat striped">';
    echo '<thead><tr>';
    echo '<th style="width:28px;"><input type="checkbox" onclick="jQuery(\"input.ai-suite-app-check\").prop(\"checked\", this.checked);" /></th>';
    echo '<th>' . esc_html__( 'Candidat', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Email', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Telefon', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Job', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Status', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'CV', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Dată', 'ai-suite' ) . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    while ( $query->have_posts() ) {
        $query->the_post();
        $application_id = get_the_ID();
        $candidate_id   = (int) get_post_meta( $application_id, '_application_candidate_id', true );
        $job_id         = (int) get_post_meta( $application_id, '_application_job_id', true );
        $status         = (string) get_post_meta( $application_id, '_application_status', true );
        $status_label   = isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;

        $email = $candidate_id ? (string) get_post_meta( $candidate_id, '_candidate_email', true ) : '';
        $tel   = $candidate_id ? (string) get_post_meta( $candidate_id, '_candidate_phone', true ) : '';
        $cv_id = (int) get_post_meta( $application_id, '_application_cv', true );
        if ( ! $cv_id && $candidate_id ) {
            $cv_id = (int) get_post_meta( $candidate_id, '_candidate_cv', true );
        }
        $cv_url = $cv_id ? wp_get_attachment_url( $cv_id ) : '';

        echo '<tr>';
        echo '<td><input class="ai-suite-app-check" type="checkbox" name="application_ids[]" value="' . esc_attr( $application_id ) . '" /></td>';
        $view_url = add_query_arg( array(
            'page'   => 'ai-suite',
            'tab'    => 'applications',
            'app_id' => $application_id,
        ), admin_url( 'admin.php' ) );

        echo '<td>';
        if ( $candidate_id ) {
            echo '<a href="' . esc_url( $view_url ) . '">' . esc_html( get_the_title( $candidate_id ) ) . '</a>';
        } else {
            echo '<a href="' . esc_url( $view_url ) . '">' . esc_html__( 'Vezi aplicația', 'ai-suite' ) . '</a>';
        }
        echo '</td>';
        echo '<td>' . esc_html( $email ) . '</td>';
        echo '<td>' . esc_html( $tel ) . '</td>';
        echo '<td>' . esc_html( $job_id ? get_the_title( $job_id ) : '' ) . '</td>';
        echo '<td><span class="ai-badge">' . esc_html( $status_label ) . '</span></td>';
        echo '<td>' . ( $cv_url ? '<a href="' . esc_url( $cv_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Deschide', 'ai-suite' ) . '</a>' : '—' ) . '</td>';
        echo '<td>' . esc_html( get_the_date( 'Y-m-d H:i' ) ) . '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';

    echo '</form>';

    wp_reset_postdata();
} else {
    echo '<p>' . esc_html__( 'Nu au fost găsite aplicații.', 'ai-suite' ) . '</p>';
}

echo '</div>';
