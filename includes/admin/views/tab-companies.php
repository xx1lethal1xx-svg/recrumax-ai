<?php
/**
 * Tab: Companii (v1.7.7)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! ( function_exists( 'aisuite_current_user_can_manage_recruitment' ) ? aisuite_current_user_can_manage_recruitment() : current_user_can( 'manage_ai_suite' ) ) ) {
    echo '<div class="ai-card"><p>' . esc_html__( 'Neautorizat.', 'ai-suite' ) . '</p></div>';
    return;
}

$notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';
if ( 'saved' === $notice ) {
    echo '<div class="updated notice"><p>' . esc_html__( 'Compania a fost salvată.', 'ai-suite' ) . '</p></div>';
} elseif ( 'jobs_attached' === $notice ) {
    echo '<div class="updated notice"><p>' . esc_html__( 'Joburile au fost atașate companiei.', 'ai-suite' ) . '</p></div>';
} elseif ( 'error' === $notice ) {
    echo '<div class="error notice"><p>' . esc_html__( 'A apărut o eroare.', 'ai-suite' ) . '</p></div>';
}

$q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
$q = trim( $q );

echo '<div class="ai-card">';
echo '<h2 style="margin-top:0">' . esc_html__( 'Companii', 'ai-suite' ) . '</h2>';
echo '<p class="description">' . esc_html__( 'Gestionează clienții/companiile și atașează joburile. Portalul client îți arată aplicațiile filtrate pe companie.', 'ai-suite' ) . '</p>';

$add_url = admin_url( 'post-new.php?post_type=rmax_company' );
echo '<p><a class="button button-primary" href="' . esc_url( $add_url ) . '">' . esc_html__( 'Adaugă companie', 'ai-suite' ) . '</a></p>';
echo '</div>';

echo '<div class="ai-card">';
echo '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between;">';
  echo '<form method="get" action="" style="display:flex;gap:8px;align-items:center;margin:0;flex-wrap:wrap;">';
    echo '<input type="hidden" name="page" value="ai-suite" />';
    echo '<input type="hidden" name="tab" value="companies" />';
    echo '<input type="search" name="q" value="' . esc_attr( $q ) . '" placeholder="' . esc_attr__( 'Caută companie / email...', 'ai-suite' ) . '" class="regular-text" style="min-width:260px;border-radius:12px;" />';
    echo '<button class="button" type="submit">' . esc_html__( 'Caută', 'ai-suite' ) . '</button>';
    if ( $q !== '' ) { echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=ai-suite&tab=companies' ) ) . '">' . esc_html__( 'Reset', 'ai-suite' ) . '</a>'; }
  echo '</form>';
  $export_url = wp_nonce_url( admin_url( 'admin-post.php?action=ai_suite_export_companies_csv' ), 'ai_suite_export_companies_csv' );
  echo '<a class="button ais-btn" href="' . esc_url( $export_url ) . '"><span class="dashicons dashicons-download"></span> ' . esc_html__( 'Export CSV', 'ai-suite' ) . '</a>';
echo '</div>';
echo '</div>';

$company_args = array(
    'post_type'      => 'rmax_company',
    'posts_per_page' => 100,
    'post_status'    => array( 'publish', 'draft', 'private' ),
    'orderby'        => 'date',
    'order'          => 'DESC',
);

if ( $q !== '' ) {
    $company_args['s'] = $q;
}


if ( ! current_user_can( 'manage_ai_suite' ) && function_exists( 'aisuite_get_assigned_company_ids' ) && ( function_exists( 'aisuite_current_user_is_recruiter' ) && ( aisuite_current_user_is_recruiter() || aisuite_current_user_is_manager() ) ) ) {
    $assigned_company_ids = aisuite_get_assigned_company_ids( get_current_user_id() );
    // If no companies are assigned, show an empty list.
    $company_args['post__in'] = ! empty( $assigned_company_ids ) ? $assigned_company_ids : array( 0 );
}

$companies = get_posts( $company_args );


if ( empty( $companies ) ) {
    echo '<div class="ai-card"><p>' . esc_html__( 'Nu există companii încă. Apasă „Adaugă companie”.', 'ai-suite' ) . '</p></div>';
    return;
}

// Listă joburi pentru select (atașare).
$all_jobs = get_posts( array(
    'post_type'      => 'rmax_job',
    'posts_per_page' => 300,
    'post_status'    => array( 'publish', 'draft', 'private' ),
    'orderby'        => 'date',
    'order'          => 'DESC',
) );

foreach ( $companies as $c ) {
    $company_id = (int) $c->ID;
    $meta = function_exists( 'aisuite_company_get_meta' ) ? (array) aisuite_company_get_meta( $company_id ) : array();
    $email = ! empty( $meta['email'] ) ? (string) $meta['email'] : '';
    $team  = ! empty( $meta['team'] ) && is_array( $meta['team'] ) ? $meta['team'] : array();
    $max_team = ! empty( $meta['max_team'] ) ? (int) $meta['max_team'] : 3;

    $job_ids = function_exists( 'aisuite_company_get_job_ids' ) ? (array) aisuite_company_get_job_ids( $company_id ) : array();
    $jobs_count = count( $job_ids );

    $portal_url = admin_url( 'admin.php?page=ai-suite&tab=portal&company_id=' . $company_id );
    $edit_url   = get_edit_post_link( $company_id, '' );

    echo '<div class="ai-card" id="company-' . esc_attr( (string) $company_id ) . '">';
    echo '<div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center;">';
    echo '<h3 style="margin:0">' . esc_html( get_the_title( $company_id ) ) . '</h3>';
    echo '<div style="display:flex;gap:8px;flex-wrap:wrap;">';
    echo '<a class="button" href="' . esc_url( $portal_url ) . '">' . esc_html__( 'Deschide portal', 'ai-suite' ) . '</a>';
    if ( $edit_url ) {
        echo '<a class="button" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Editează (WP)', 'ai-suite' ) . '</a>';
    }
    echo '</div>';
    echo '</div>';

    echo '<p class="description" style="margin-top:6px;">' . sprintf( esc_html__( 'Joburi atașate: %d', 'ai-suite' ), (int) $jobs_count ) . '</p>';

    // Form: meta companie.
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:12px;">';
    echo '<input type="hidden" name="action" value="ai_suite_company_save" />';
    echo '<input type="hidden" name="company_id" value="' . esc_attr( (string) $company_id ) . '" />';
    wp_nonce_field( 'ai_suite_company_save_' . $company_id );
    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:start;">';
    echo '<div>';
    echo '<label><strong>' . esc_html__( 'Email contact', 'ai-suite' ) . '</strong></label><br />';
    echo '<input type="email" name="contact_email" class="regular-text" value="' . esc_attr( $email ) . '" placeholder="ex: hr@companie.ro" />';
    echo '<p class="description">' . esc_html__( 'Folosit ulterior pentru notificări și portal extern.', 'ai-suite' ) . '</p>';
    echo '</div>';
    echo '<div>';
    echo '<label><strong>' . esc_html__( 'Max. useri companie', 'ai-suite' ) . '</strong></label><br />';
    echo '<select name="max_team" style="min-width:120px;">';
    for ( $i = 1; $i <= 3; $i++ ) {
        echo '<option value="' . esc_attr( (string) $i ) . '" ' . selected( $max_team, $i, false ) . '>' . esc_html( (string) $i ) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">' . esc_html__( 'Limită recomandată pentru MVP (extensibil).', 'ai-suite' ) . '</p>';
    echo '</div>';
    echo '</div>';
    $promo_credits = isset( $meta['promo_credits'] ) ? (int) $meta['promo_credits'] : (int) get_post_meta( $company_id, '_company_promo_credits', true );
    if ( $promo_credits < 0 ) { $promo_credits = 0; }
    echo '<div style="margin-top:10px;">';
    echo '<label><strong>' . esc_html__( 'Credite promovare joburi', 'ai-suite' ) . '</strong></label><br />';
    echo '<input type="number" min="0" step="1" name="promo_credits" value="' . esc_attr( (string) $promo_credits ) . '" style="width:180px;" />';
    echo '<p class="description">' . esc_html__( '1 credit = 7 zile promovare. Compania poate promova joburile din portal (Featured).', 'ai-suite' ) . '</p>';
    echo '</div>';


    // Billing / Buyer details (Patch48)
    $billing_name    = (string) get_post_meta( $company_id, '_company_billing_name', true );
    $billing_cui     = (string) get_post_meta( $company_id, '_company_billing_cui', true );
    $billing_reg     = (string) get_post_meta( $company_id, '_company_billing_reg', true );
    $billing_address = (string) get_post_meta( $company_id, '_company_billing_address', true );
    $billing_city    = (string) get_post_meta( $company_id, '_company_billing_city', true );
    $billing_country = (string) get_post_meta( $company_id, '_company_billing_country', true );
    $billing_email   = (string) get_post_meta( $company_id, '_company_billing_email', true );
    $billing_phone   = (string) get_post_meta( $company_id, '_company_billing_phone', true );
    $billing_contact = (string) get_post_meta( $company_id, '_company_billing_contact', true );
    $billing_vat     = (int) get_post_meta( $company_id, '_company_billing_vat', true );

    echo '<hr style="margin:14px 0;border:none;border-top:1px solid #e5e7eb" />';
    echo '<h4 style="margin:0 0 8px">📄 ' . esc_html__( 'Date facturare (cumpărător)', 'ai-suite' ) . '</h4>'; 
    echo '<p class="description" style="margin-top:-4px">' . esc_html__( 'Aceste date apar pe facturile HTML generate automat în portal (Istoric plăți & facturi).', 'ai-suite' ) . '</p>';

    echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;align-items:start;max-width:880px;">';

    echo '<div>';
    echo '<label><strong>' . esc_html__( 'Denumire firmă / persoană', 'ai-suite' ) . '</strong></label><br />';
    echo '<input type="text" name="billing_name" class="regular-text" value="' . esc_attr( $billing_name ) . '" placeholder="SC Exemplu SRL" />';
    echo '</div>';

    echo '<div>';
    echo '<label><strong>' . esc_html__( 'Email facturare', 'ai-suite' ) . '</strong></label><br />';
    echo '<input type="email" name="billing_email" class="regular-text" value="' . esc_attr( $billing_email ) . '" placeholder="billing@firma.ro" />';
    echo '</div>';

    echo '<div>';
    echo '<label><strong>' . esc_html__( 'CUI / CIF', 'ai-suite' ) . '</strong></label><br />';
    echo '<input type="text" name="billing_cui" class="regular-text" value="' . esc_attr( $billing_cui ) . '" placeholder="RO123456" />';
    echo '</div>';

    echo '<div>';
    echo '<label><strong>' . esc_html__( 'Nr. Reg. Com.', 'ai-suite' ) . '</strong></label><br />';
    echo '<input type="text" name="billing_reg" class="regular-text" value="' . esc_attr( $billing_reg ) . '" placeholder="J00/000/2026" />';
    echo '</div>';

    echo '<div style="grid-column:1/-1">';
    echo '<label><strong>' . esc_html__( 'Adresă', 'ai-suite' ) . '</strong></label><br />';
    echo '<input type="text" name="billing_address" class="large-text" value="' . esc_attr( $billing_address ) . '" placeholder="Str. Exemplu nr. 1" />';
    echo '</div>';

    echo '<div>';
    echo '<label><strong>' . esc_html__( 'Oraș', 'ai-suite' ) . '</strong></label><br />';
    echo '<input type="text" name="billing_city" class="regular-text" value="' . esc_attr( $billing_city ) . '" placeholder="Baia Mare" />';
    echo '</div>';

    echo '<div>';
    echo '<label><strong>' . esc_html__( 'Țară', 'ai-suite' ) . '</strong></label><br />';
    echo '<input type="text" name="billing_country" class="regular-text" value="' . esc_attr( $billing_country ) . '" placeholder="RO" />';
    echo '</div>';

    echo '<div>';
    echo '<label><strong>' . esc_html__( 'Telefon', 'ai-suite' ) . '</strong></label><br />';
    echo '<input type="text" name="billing_phone" class="regular-text" value="' . esc_attr( $billing_phone ) . '" placeholder="+40..." />';
    echo '</div>';

    echo '<div>';
    echo '<label><strong>' . esc_html__( 'Persoană contact', 'ai-suite' ) . '</strong></label><br />';
    echo '<input type="text" name="billing_contact" class="regular-text" value="' . esc_attr( $billing_contact ) . '" placeholder="Nume Prenume" />';
    echo '</div>';

    echo '<div style="grid-column:1/-1">';
    echo '<label style="display:flex;align-items:center;gap:10px">';
    echo '<input type="checkbox" name="billing_vat" value="1" ' . checked( 1, $billing_vat ? 1 : 0, false ) . ' /> ';
    echo '<strong>' . esc_html__( 'Plătitor TVA', 'ai-suite' ) . '</strong>';
    echo '</label>';
    echo '</div>';

    echo '</div>';

    echo '<div style="margin-top:10px;">';
    echo '<label><strong>' . esc_html__( 'Team emails (separate prin virgulă)', 'ai-suite' ) . '</strong></label>';
    echo '<input type="text" name="team_emails" class="large-text" value="' . esc_attr( implode( ', ', $team ) ) . '" placeholder="ex: manager@x.ro, hr@x.ro" />';
    echo '</div>';
    echo '<p style="margin-top:10px;"><button type="submit" class="button button-primary">' . esc_html__( 'Salvează', 'ai-suite' ) . '</button></p>';
    echo '</form>';

    // Form: atașare joburi.
    echo '<hr />';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="ai_suite_company_attach_jobs" />';
    echo '<input type="hidden" name="company_id" value="' . esc_attr( (string) $company_id ) . '" />';
    wp_nonce_field( 'ai_suite_attach_jobs_' . $company_id );
    echo '<label><strong>' . esc_html__( 'Atașează joburi la această companie', 'ai-suite' ) . '</strong></label>';
    echo '<p class="description">' . esc_html__( 'Selectează joburile care aparțin companiei. Portalul client va lista automat aplicațiile pe aceste joburi.', 'ai-suite' ) . '</p>';
    echo '<select name="job_ids[]" multiple size="6" class="large-text" style="max-width:720px;">';
    foreach ( $all_jobs as $j ) {
        $jid = (int) $j->ID;
        $selected = in_array( $jid, $job_ids, true ) ? ' selected' : '';
        echo '<option value="' . esc_attr( (string) $jid ) . '"' . $selected . '>' . esc_html( get_the_title( $jid ) ) . '</option>';
    }
    echo '</select>';
    echo '<p style="margin-top:10px;"><button type="submit" class="button">' . esc_html__( 'Atașează joburi', 'ai-suite' ) . '</button></p>';
    echo '</form>';

    echo '</div>';
}
