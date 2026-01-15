<?php
/**
 * Settings tab view.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo '<div class="ai-card">';
echo '<h2>' . esc_html__( 'Setări', 'ai-suite' ) . '</h2>';

$settings = aisuite_get_settings();
$openai   = ! empty( $settings['openai_api_key'] ) ? $settings['openai_api_key'] : '';
$openai_model = ! empty( $settings['openai_model'] ) ? (string) $settings['openai_model'] : 'gpt-4.1-mini';
$admin_email = ! empty( $settings['notificari_admin_email'] ) ? $settings['notificari_admin_email'] : '';
$upload_mb   = isset( $settings['limita_upload_mb'] ) ? (int) $settings['limita_upload_mb'] : 8;


// Demo notices
if ( isset( $_GET['demo_notice'] ) ) {
    $dn = sanitize_text_field( wp_unslash( $_GET['demo_notice'] ) );
    echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'Demo:', 'ai-suite' ) . '</strong> ' . esc_html( $dn ) . '</p></div>';
}


$send_cand   = isset( $settings['trimite_email_candidat'] ) ? (int) $settings['trimite_email_candidat'] : 1;
$tpl_admin_new = ! empty( $settings['email_tpl_admin_new_application'] ) ? (string) $settings['email_tpl_admin_new_application'] : '';
$tpl_cand_confirm = ! empty( $settings['email_tpl_candidate_confirmation'] ) ? (string) $settings['email_tpl_candidate_confirmation'] : '';
$tpl_cand_feedback = ! empty( $settings['email_tpl_candidate_feedback'] ) ? (string) $settings['email_tpl_candidate_feedback'] : '';

// v1.9.2: AI queue settings
$queue_enabled = isset( $settings['ai_queue_enabled'] ) ? (int) $settings['ai_queue_enabled'] : 1;
$auto_score_on_apply = isset( $settings['ai_auto_score_on_apply'] ) ? (int) $settings['ai_auto_score_on_apply'] : 1;
$auto_summary_on_apply = isset( $settings['ai_auto_summary_on_apply'] ) ? (int) $settings['ai_auto_summary_on_apply'] : 1;
$ai_email_status_enabled = isset( $settings['ai_email_status_enabled'] ) ? (int) $settings['ai_email_status_enabled'] : 1;
$ai_email_use_ai = isset( $settings['ai_email_use_ai'] ) ? (int) $settings['ai_email_use_ai'] : 1;

// Notice.
if ( isset( $_GET['notice'] ) && 'saved' === $_GET['notice'] ) {
    echo '<div class="updated notice"><p>' . esc_html__( 'Setările au fost salvate.', 'ai-suite' ) . '</p></div>';
}

echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
echo '<input type="hidden" name="action" value="ai_suite_save_settings" />';
wp_nonce_field( 'ai_suite_save_settings' );

echo '<table class="form-table">';
echo '<tr>';
echo '<th scope="row">' . esc_html__( 'Cheie API OpenAI', 'ai-suite' ) . '</th>';
echo '<td><input type="text" name="openai_api_key" value="' . esc_attr( $openai ) . '" class="regular-text" /></td>';
echo '</tr>';

echo '<tr>';
echo '<th scope="row">' . esc_html__( 'Model OpenAI', 'ai-suite' ) . '</th>';
echo '<td>';
echo '<input type="text" name="openai_model" value="' . esc_attr( $openai_model ) . '" class="regular-text" placeholder="gpt-4.1-mini" />';
echo '<p class="description">' . esc_html__( 'Exemple: gpt-4.1-mini, gpt-4.1. Dacă nu știi, lasă default.', 'ai-suite' ) . '</p>';
echo '</td>';
echo '</tr>';

echo '<tr>';
echo '<th scope="row">' . esc_html__( 'Email notificări admin (aplicații)', 'ai-suite' ) . '</th>';
echo '<td>';
echo '<input type="email" name="notificari_admin_email" value="' . esc_attr( $admin_email ) . '" class="regular-text" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '" />';
echo '<p class="description">' . esc_html__( 'Dacă e gol, se folosește emailul admin din WordPress.', 'ai-suite' ) . '</p>';
echo '</td>';
echo '</tr>';

echo '<tr>';
echo '<th scope="row">' . esc_html__( 'Limită upload CV (MB)', 'ai-suite' ) . '</th>';
echo '<td>';
echo '<input type="number" min="1" max="25" name="limita_upload_mb" value="' . esc_attr( $upload_mb ) . '" class="small-text" />';
echo '<p class="description">' . esc_html__( 'Recomandat: 8MB. Interval: 1–25MB.', 'ai-suite' ) . '</p>';
echo '</td>';
echo '</tr>';


echo '<tr>';
echo '<th scope="row">' . esc_html__( 'Mod Demo (date demo)', 'ai-suite' ) . '</th>';
echo '<td>';
echo '<label><input type="checkbox" name="demo_enabled" value="1" ' . checked( ! empty( $settings['demo_enabled'] ), true, false ) . ' /> ' . esc_html__( 'Activează modul demo (permite generare date demo)', 'ai-suite' ) . '</label>';
echo '<p class="description">' . esc_html__( 'Când este activ, poți genera/șterge date demo din acest tab. Datele demo sunt marcate și nu afectează datele reale.', 'ai-suite' ) . '</p>';
echo '</td>';
echo '</tr>';



// v1.9.2: AI Queue + automatizări
echo '<tr>';
echo '<th scope="row">' . esc_html__( 'AI Queue (asincron)', 'ai-suite' ) . '</th>';
echo '<td>';
echo '<label><input type="checkbox" name="ai_queue_enabled" value="1" ' . checked( 1, (int) $queue_enabled, false ) . ' /> ' . esc_html__( 'Activează coada AI (recomandat)', 'ai-suite' ) . '</label>';
echo '<p class="description">' . esc_html__( 'Când este activă, task-urile AI rulează în fundal (WP-Cron) pentru a evita timeouts.', 'ai-suite' ) . '</p>';
echo '</td>';
echo '</tr>';

echo '<tr>';
echo '<th scope="row">' . esc_html__( 'Auto-scoring la aplicare', 'ai-suite' ) . '</th>';
echo '<td>';
echo '<label><input type="checkbox" name="ai_auto_score_on_apply" value="1" ' . checked( 1, (int) $auto_score_on_apply, false ) . ' /> ' . esc_html__( 'Calculează automat scor AI după aplicare (în coadă)', 'ai-suite' ) . '</label>';
echo '</td>';
echo '</tr>';

echo '<tr>';
echo '<th scope="row">' . esc_html__( 'Auto-sumar candidat', 'ai-suite' ) . '</th>';
echo '<td>';
echo '<label><input type="checkbox" name="ai_auto_summary_on_apply" value="1" ' . checked( 1, (int) $auto_summary_on_apply, false ) . ' /> ' . esc_html__( 'Generează sumar AI pentru candidat (în coadă)', 'ai-suite' ) . '</label>';
echo '</td>';
echo '</tr>';

echo '<tr>';
echo '<th scope="row">' . esc_html__( 'Email automat la schimbare status', 'ai-suite' ) . '</th>';
echo '<td>';
echo '<label><input type="checkbox" name="ai_email_status_enabled" value="1" ' . checked( 1, (int) $ai_email_status_enabled, false ) . ' /> ' . esc_html__( 'Trimite automat candidatului email când îi schimbi statusul', 'ai-suite' ) . '</label>';
echo '<br /><label style="display:inline-block;margin-top:6px;"><input type="checkbox" name="ai_email_use_ai" value="1" ' . checked( 1, (int) $ai_email_use_ai, false ) . ' /> ' . esc_html__( 'Folosește OpenAI pentru feedback email (dacă există cheie API)', 'ai-suite' ) . '</label>';
echo '</td>';
echo '</tr>';

echo '<tr>';
echo '<th scope="row">' . esc_html__( 'Trimite email candidat (confirmare)', 'ai-suite' ) . '</th>';
echo '<td>';
echo '<label><input type="checkbox" name="trimite_email_candidat" value="1" ' . checked( 1, $send_cand, false ) . ' /> ' . esc_html__( 'Da, trimite confirmare după aplicare', 'ai-suite' ) . '</label>';
echo '<p class="description">' . esc_html__( 'Recomandat: activ. Poți dezactiva dacă folosești un sistem extern de email.', 'ai-suite' ) . '</p>';
echo '</td>';
echo '</tr>';

echo '<tr>';
echo '<th scope="row">' . esc_html__( 'Template email admin: aplicație nouă', 'ai-suite' ) . '</th>';
echo '<td>';
echo '<textarea name="email_tpl_admin_new_application" rows="7" class="large-text code">' . esc_textarea( $tpl_admin_new ) . '</textarea>';
echo '<p class="description">' . esc_html__( 'Token-uri disponibile: {JOB_TITLE}, {CANDIDATE_NAME}, {EMAIL}, {PHONE}, {MESSAGE}, {CV_URL}, {ADMIN_URL}', 'ai-suite' ) . '</p>';
echo '</td>';
echo '</tr>';

echo '<tr>';
echo '<th scope="row">' . esc_html__( 'Template email candidat: confirmare', 'ai-suite' ) . '</th>';
echo '<td>';
echo '<textarea name="email_tpl_candidate_confirmation" rows="7" class="large-text code">' . esc_textarea( $tpl_cand_confirm ) . '</textarea>';
echo '<p class="description">' . esc_html__( 'Token-uri: {JOB_TITLE}, {CANDIDATE_NAME}, {EMAIL}, {PHONE}, {MESSAGE}, {CV_URL}', 'ai-suite' ) . '</p>';
echo '</td>';
echo '</tr>';

echo '<tr>';
echo '<th scope="row">' . esc_html__( 'Template email candidat: feedback', 'ai-suite' ) . '</th>';
echo '<td>';
echo '<textarea name="email_tpl_candidate_feedback" rows="7" class="large-text code">' . esc_textarea( $tpl_cand_feedback ) . '</textarea>';
echo '<p class="description">' . esc_html__( 'Token-uri: {JOB_TITLE}, {CANDIDATE_NAME}, {STATUS}, {NOTE}', 'ai-suite' ) . '</p>';
echo '</td>';
echo '</tr>';

echo '</table>';

echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Salvează setările', 'ai-suite' ) . '</button></p>';
echo '</form>';


echo '<div class="ai-card" style="margin-top:14px;">';
echo '<h3>' . esc_html__( 'Date Demo (Jobs / Candidați / Aplicații)', 'ai-suite' ) . '</h3>';
echo '<p class="description">' . esc_html__( 'Folosește aceste butoane pentru a genera rapid conținut demonstrativ. Datele sunt marcate cu rmax_is_demo=1 și pot fi șterse fără a atinge date reale.', 'ai-suite' ) . '</p>';

echo '<div style="display:flex;gap:10px;flex-wrap:wrap;">';

echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0;">';
echo '<input type="hidden" name="action" value="ai_suite_seed_demo" />';
wp_nonce_field( 'ai_suite_demo_actions' );
echo '<input type="hidden" name="force" value="0" />';
echo '<button type="submit" class="button button-primary">' . esc_html__( 'Generează date demo', 'ai-suite' ) . '</button>';
echo '</form>';

echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0;">';
echo '<input type="hidden" name="action" value="ai_suite_seed_demo" />';
wp_nonce_field( 'ai_suite_demo_actions' );
echo '<input type="hidden" name="force" value="1" />';
echo '<button type="submit" class="button">' . esc_html__( 'Regenerează (forțat)', 'ai-suite' ) . '</button>';
echo '</form>';

echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0;" onsubmit="return confirm(\'' . esc_js( __( 'Sigur vrei să ștergi doar datele DEMO?', 'ai-suite' ) ) . '\');">';
echo '<input type="hidden" name="action" value="ai_suite_clear_demo" />';
wp_nonce_field( 'ai_suite_demo_actions' );
echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Șterge date demo', 'ai-suite' ) . '</button>';
echo '</form>';

echo '</div>';
echo '</div>';

// Demo portal accounts (WP Users) for quick preview.
$demo_accounts = function_exists( 'aisuite_get_demo_portal_accounts' ) ? aisuite_get_demo_portal_accounts() : array();
$portal_login_url = '';
$p_login = get_page_by_path( 'portal-login' );
if ( $p_login && isset( $p_login->ID ) ) {
    $portal_login_url = get_permalink( $p_login->ID );
}
$portal_login_url = $portal_login_url ? $portal_login_url : wp_login_url();

echo '<div class="ai-card" style="margin-top:14px;">';
echo '<h3>' . esc_html__( 'Conturi demo (Portal login)', 'ai-suite' ) . '</h3>';
echo '<p class="description">' . esc_html__( 'Creează rapid 2 conturi WordPress pentru preview: un candidat și o companie. Parolele sunt stocate doar pentru demo (admin-only). Nu folosi pe un site public.', 'ai-suite' ) . '</p>';

echo '<div style="display:flex;gap:10px;flex-wrap:wrap;">';

echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0;">';
echo '<input type="hidden" name="action" value="ai_suite_demo_users_create" />';
wp_nonce_field( 'ai_suite_demo_actions' );
echo '<input type="hidden" name="force" value="0" />';
echo '<button type="submit" class="button button-primary">' . esc_html__( 'Creează conturi demo', 'ai-suite' ) . '</button>';
echo '</form>';

echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0;">';
echo '<input type="hidden" name="action" value="ai_suite_demo_users_create" />';
wp_nonce_field( 'ai_suite_demo_actions' );
echo '<input type="hidden" name="force" value="1" />';
echo '<button type="submit" class="button">' . esc_html__( 'Resetează parole (forțat)', 'ai-suite' ) . '</button>';
echo '</form>';

echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0;" onsubmit="return confirm(\'' . esc_js( __( 'Ștergi conturile demo? (doar candidate_demo și company_demo)', 'ai-suite' ) ) . '\');">';
echo '<input type="hidden" name="action" value="ai_suite_demo_users_clear" />';
wp_nonce_field( 'ai_suite_demo_actions' );
echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Șterge conturi demo', 'ai-suite' ) . '</button>';
echo '</form>';

echo '</div>';

echo '<div style="margin-top:10px;">';
echo '<p><strong>' . esc_html__( 'Login URL:', 'ai-suite' ) . '</strong> <a href="' . esc_url( $portal_login_url ) . '" target="_blank" rel="noopener">' . esc_html( $portal_login_url ) . '</a></p>';

if ( ! empty( $demo_accounts['candidate']['user_login'] ) || ! empty( $demo_accounts['company']['user_login'] ) ) {
    echo '<div class="notice notice-info inline" style="padding:10px 12px;">';
    echo '<p style="margin:0 0 6px;"><strong>' . esc_html__( 'Credențiale demo (copiere rapidă)', 'ai-suite' ) . '</strong></p>';
    echo '<pre style="margin:0;white-space:pre-wrap;">';
    if ( ! empty( $demo_accounts['candidate'] ) ) {
        echo "CANDIDAT\n";
        echo 'user: ' . ( isset( $demo_accounts['candidate']['user_login'] ) ? $demo_accounts['candidate']['user_login'] : '' ) . "\n";
        echo 'email: ' . ( isset( $demo_accounts['candidate']['user_email'] ) ? $demo_accounts['candidate']['user_email'] : '' ) . "\n";
        echo 'pass: ' . ( isset( $demo_accounts['candidate']['password'] ) ? $demo_accounts['candidate']['password'] : '' ) . "\n\n";
    }
    if ( ! empty( $demo_accounts['company'] ) ) {
        echo "COMPANIE\n";
        echo 'user: ' . ( isset( $demo_accounts['company']['user_login'] ) ? $demo_accounts['company']['user_login'] : '' ) . "\n";
        echo 'email: ' . ( isset( $demo_accounts['company']['user_email'] ) ? $demo_accounts['company']['user_email'] : '' ) . "\n";
        echo 'pass: ' . ( isset( $demo_accounts['company']['password'] ) ? $demo_accounts['company']['password'] : '' ) . "\n";
    }
    echo '</pre>';
    echo '</div>';
} else {
    echo '<p class="description">' . esc_html__( 'Nu există încă conturi demo create.', 'ai-suite' ) . '</p>';
}

echo '</div>';
echo '</div>';

echo '</div>';