<?php
/**
 * Tab: Portal client (admin) (v1.7.7)
 *
 * MVP: portal intern (admin) – filtrează joburi/aplicații pe companie.
 * În patch-uri viitoare îl facem „extern” (frontend) cu rol/caps + login.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! ( function_exists( 'aisuite_current_user_can_manage_recruitment' ) ? aisuite_current_user_can_manage_recruitment() : current_user_can( 'manage_ai_suite' ) ) ) {
    echo '<div class="ai-card"><p>' . esc_html__( 'Neautorizat.', 'ai-suite' ) . '</p></div>';
    return;
}

$company_id = isset( $_GET['company_id'] ) ? absint( wp_unslash( $_GET['company_id'] ) ) : 0;

$companies = get_posts( array(
    'post_type'      => 'rmax_company',
    'posts_per_page' => 200,
    'post_status'    => array( 'publish', 'draft', 'private' ),
    'orderby'        => 'date',
    'order'          => 'DESC',
) );

echo '<div class="ai-card">';
echo '<h2 style="margin-top:0">' . esc_html__( 'Portal client', 'ai-suite' ) . '</h2>';
echo '<p class="description">' . esc_html__( 'Vizualizare premium: joburi + aplicații filtrate pe companie. Folosește pentru feedback rapid și monitorizarea progresului.', 'ai-suite' ) . '</p>';

if ( empty( $companies ) ) {
    echo '<p>' . esc_html__( 'Nu există companii. Creează una în tabul Companii.', 'ai-suite' ) . '</p>';
    echo '</div>';
    return;
}

// Selector companie.
echo '<form method="get" action="' . esc_url( admin_url( 'admin.php' ) ) . '">';
echo '<input type="hidden" name="page" value="ai-suite" />';
echo '<input type="hidden" name="tab" value="portal" />';
echo '<label><strong>' . esc_html__( 'Selectează companie:', 'ai-suite' ) . '</strong></label><br />';
echo '<select name="company_id" style="min-width:320px;">';
echo '<option value="0">' . esc_html__( '— Alege —', 'ai-suite' ) . '</option>';
foreach ( $companies as $c ) {
    $cid = (int) $c->ID;
    echo '<option value="' . esc_attr( (string) $cid ) . '" ' . selected( $company_id, $cid, false ) . '>' . esc_html( get_the_title( $cid ) ) . '</option>';
}
echo '</select> ';
echo '<button class="button" type="submit">' . esc_html__( 'Deschide', 'ai-suite' ) . '</button>';
echo '</form>';
echo '</div>';

if ( ! $company_id || 'rmax_company' !== get_post_type( $company_id ) ) {
    echo '<div class="ai-card"><p>' . esc_html__( 'Alege o companie pentru a vedea joburile și aplicațiile.', 'ai-suite' ) . '</p></div>';
    return;
}

$meta = function_exists( 'aisuite_company_get_meta' ) ? (array) aisuite_company_get_meta( $company_id ) : array();
$contact_email = ! empty( $meta['email'] ) ? (string) $meta['email'] : '';

$job_ids = function_exists( 'aisuite_company_get_job_ids' ) ? (array) aisuite_company_get_job_ids( $company_id ) : array();

echo '<div class="ai-card">';
echo '<h3 style="margin-top:0;">' . esc_html( get_the_title( $company_id ) ) . '</h3>';
if ( $contact_email ) {
    echo '<p class="description">' . esc_html__( 'Email contact:', 'ai-suite' ) . ' <strong>' . esc_html( $contact_email ) . '</strong></p>';
}
echo '<p class="description">' . sprintf( esc_html__( 'Joburi atașate: %d', 'ai-suite' ), (int) count( $job_ids ) ) . '</p>';
echo '</div>';

if ( empty( $job_ids ) ) {
    $fix_url = admin_url( 'admin.php?page=ai-suite&tab=companies#company-' . $company_id );
    echo '<div class="ai-card"><p>' . esc_html__( 'Nu există joburi atașate. Mergi la Companii și atașează joburile.', 'ai-suite' ) . ' <a href="' . esc_url( $fix_url ) . '">' . esc_html__( 'Deschide Companii', 'ai-suite' ) . '</a></p></div>';
    return;
}

$jobs = get_posts( array(
    'post_type'      => 'rmax_job',
    'posts_per_page' => 200,
    'post_status'    => array( 'publish', 'draft', 'private' ),
    'post__in'       => array_map( 'absint', $job_ids ),
    'orderby'        => 'date',
    'order'          => 'DESC',
) );

$statuses = function_exists( 'ai_suite_app_statuses' ) ? (array) ai_suite_app_statuses() : array();

foreach ( $jobs as $job ) {
    $jid = (int) $job->ID;
    $apps = get_posts( array(
        'post_type'      => 'rmax_application',
        'posts_per_page' => 200,
        'post_status'    => array( 'publish', 'draft', 'private' ),
        'meta_query'     => array(
            array(
                'key'   => '_application_job_id',
                'value' => (string) $jid,
            ),
        ),
        'orderby'        => 'date',
        'order'          => 'DESC',
    ) );

    echo '<div class="ai-card">';
    echo '<div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;">';
    echo '<h3 style="margin:0">' . esc_html( get_the_title( $jid ) ) . '</h3>';
    echo '<span class="ai-badge">' . sprintf( esc_html__( '%d aplicații', 'ai-suite' ), (int) count( $apps ) ) . '</span>';
    echo '</div>';

    if ( empty( $apps ) ) {
        echo '<p class="description">' . esc_html__( 'Nicio aplicație încă.', 'ai-suite' ) . '</p>';
        echo '</div>';
        continue;
    }

    echo '<div style="overflow:auto;">';
    echo '<table class="widefat striped" style="min-width:980px;">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__( 'Candidat', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Status', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Scor', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Tags', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Acțiuni', 'ai-suite' ) . '</th>';
    echo '</tr></thead><tbody>';

    foreach ( $apps as $app ) {
        $app_id = (int) $app->ID;
        $cid    = (int) get_post_meta( $app_id, '_application_candidate_id', true );
        $st     = (string) get_post_meta( $app_id, '_application_status', true );
        $score  = (string) get_post_meta( $app_id, '_application_score', true );
        $tags   = get_post_meta( $app_id, '_application_tags', true );
        if ( ! is_array( $tags ) ) {
            $tags = array();
        }

        $label = isset( $statuses[ $st ] ) ? (string) $statuses[ $st ] : $st;
        $view_url = admin_url( 'admin.php?page=ai-suite&tab=applications&app_id=' . $app_id );

        echo '<tr>';
        echo '<td>' . esc_html( $cid ? get_the_title( $cid ) : ( $app->post_title ? $app->post_title : '#' . $app_id ) ) . '</td>';
        echo '<td><span class="ai-badge">' . esc_html( $label ) . '</span></td>';
        echo '<td>' . esc_html( $score !== '' ? $score : '—' ) . '</td>';
        echo '<td>' . esc_html( ! empty( $tags ) ? implode( ', ', $tags ) : '—' ) . '</td>';
        echo '<td>';
        echo '<a class="button" href="' . esc_url( $view_url ) . '">' . esc_html__( 'Deschide', 'ai-suite' ) . '</a> ';

        // Tranziții rapide (folosim handlerul existent) – doar dacă fluxul permite.
        $flow = function_exists( 'ai_suite_application_status_flow' ) ? (array) ai_suite_application_status_flow() : array();
        $next = isset( $flow[ $st ] ) ? (array) $flow[ $st ] : array();
        if ( ! empty( $next ) ) {
            foreach ( $next as $to ) {
                if ( empty( $statuses[ $to ] ) ) {
                    continue;
                }
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;">';
                echo '<input type="hidden" name="action" value="ai_suite_transition_application_status" />';
                echo '<input type="hidden" name="app_id" value="' . esc_attr( (string) $app_id ) . '" />';
                echo '<input type="hidden" name="new_status" value="' . esc_attr( (string) $to ) . '" />';
                wp_nonce_field( 'ai_suite_transition_status_' . $app_id );
                echo '<button type="submit" class="button">' . esc_html( $statuses[ $to ] ) . '</button>';
                echo '</form> ';
            }
        }

        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
    echo '</div>';
}
