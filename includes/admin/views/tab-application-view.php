<?php
/**
 * Vizualizare detaliu aplicație (v1.6).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$application_id = isset( $view_app_id ) ? absint( $view_app_id ) : 0;
if ( ! $application_id ) {
    echo '<div class="ai-card"><p>' . esc_html__( 'Aplicație invalidă.', 'ai-suite' ) . '</p></div>';
    return;
}

$candidate_id = (int) get_post_meta( $application_id, '_application_candidate_id', true );
$job_id       = (int) get_post_meta( $application_id, '_application_job_id', true );
$status       = (string) get_post_meta( $application_id, '_application_status', true );
$message      = (string) get_post_meta( $application_id, '_application_message', true );
$cv_id        = (int) get_post_meta( $application_id, '_application_cv', true );
if ( ! $cv_id && $candidate_id ) {
    $cv_id = (int) get_post_meta( $candidate_id, '_candidate_cv', true );
}
$cv_url = $cv_id ? wp_get_attachment_url( $cv_id ) : '';

$email = $candidate_id ? (string) get_post_meta( $candidate_id, '_candidate_email', true ) : '';
$phone = $candidate_id ? (string) get_post_meta( $candidate_id, '_candidate_phone', true ) : '';

$statuses = function_exists( 'ai_suite_app_statuses' ) ? (array) ai_suite_app_statuses() : array();
$status_label = isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;

$tags = get_post_meta( $application_id, '_application_tags', true );
if ( ! is_array( $tags ) ) {
    $tags = array();
}

$notes = get_post_meta( $application_id, '_application_notes', true );
if ( ! is_array( $notes ) ) {
    $notes = array();
}

$timeline = get_post_meta( $application_id, '_application_timeline', true );
if ( ! is_array( $timeline ) ) {
    $timeline = array();
}

$status_history = get_post_meta( $application_id, '_application_status_history', true );
if ( ! is_array( $status_history ) ) {
    $status_history = array();
}

$back_url = admin_url( 'admin.php?page=ai-suite&tab=applications' );

echo '<div class="ai-card">';
echo '<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;">';
echo '<h2 style="margin:0;">' . esc_html__( 'Aplicație', 'ai-suite' ) . ' #' . esc_html( (string) $application_id ) . '</h2>';
echo '<a class="button" href="' . esc_url( $back_url ) . '">' . esc_html__( 'Înapoi la listă', 'ai-suite' ) . '</a>';
echo '</div>';

// Notices
$notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';
if ( 'saved' === $notice ) {
    echo '<div class="updated notice"><p>' . esc_html__( 'Modificările au fost salvate.', 'ai-suite' ) . '</p></div>';
} elseif ( 'note_added' === $notice ) {
    echo '<div class="updated notice"><p>' . esc_html__( 'Notița a fost adăugată.', 'ai-suite' ) . '</p></div>';
} elseif ( 'feedback_sent' === $notice ) {
    echo '<div class="updated notice"><p>' . esc_html__( 'Feedback-ul a fost trimis către candidat.', 'ai-suite' ) . '</p></div>';
} elseif ( 'note_deleted' === $notice ) {
    echo '<div class="updated notice"><p>' . esc_html__( 'Notița a fost ștearsă.', 'ai-suite' ) . '</p></div>';
} elseif ( 'invalid_transition' === $notice ) {
    echo '<div class="error notice"><p>' . esc_html__( 'Tranziție invalidă de status. Respectă fluxul recomandat.', 'ai-suite' ) . '</p></div>';
} elseif ( 'error' === $notice ) {
    echo '<div class="error notice"><p>' . esc_html__( 'A apărut o eroare. Verifică datele și încearcă din nou.', 'ai-suite' ) . '</p></div>';
}

echo '<hr />';

echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;">';

// Stânga: detalii
echo '<div>';
echo '<h3 style="margin-top:0;">' . esc_html__( 'Detalii candidat', 'ai-suite' ) . '</h3>';
echo '<p><strong>' . esc_html__( 'Nume:', 'ai-suite' ) . '</strong> ' . esc_html( $candidate_id ? get_the_title( $candidate_id ) : '—' ) . '</p>';
echo '<p><strong>' . esc_html__( 'Email:', 'ai-suite' ) . '</strong> ' . esc_html( $email ) . '</p>';
echo '<p><strong>' . esc_html__( 'Telefon:', 'ai-suite' ) . '</strong> ' . esc_html( $phone ) . '</p>';
echo '<p><strong>' . esc_html__( 'Job:', 'ai-suite' ) . '</strong> ' . esc_html( $job_id ? get_the_title( $job_id ) : '—' ) . '</p>';
echo '<p><strong>' . esc_html__( 'Status:', 'ai-suite' ) . '</strong> <span class="ai-badge">' . esc_html( $status_label ) . '</span></p>';
echo '<p><strong>' . esc_html__( 'CV:', 'ai-suite' ) . '</strong> ' . ( $cv_url ? '<a href="' . esc_url( $cv_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Deschide', 'ai-suite' ) . '</a>' : '—' ) . '</p>';
echo '</div>';

// Dreapta: acțiuni
echo '<div>';
echo '<h3 style="margin-top:0;">' . esc_html__( 'Acțiuni rapide', 'ai-suite' ) . '</h3>';

// Tranziții rapide (flow) – nu modifică tags.
$flow = function_exists( 'ai_suite_application_status_flow' ) ? (array) ai_suite_application_status_flow() : array();
$next = isset( $flow[ $status ] ) ? (array) $flow[ $status ] : array();
if ( ! empty( $next ) ) {
    echo '<div class="ai-flow">';
    echo '<div class="ai-flow-title">' . esc_html__( 'Tranziții rapide', 'ai-suite' ) . '</div>';
    echo '<div class="ai-flow-actions">';
    foreach ( $next as $to ) {
        if ( empty( $statuses[ $to ] ) ) {
            continue;
        }
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ai-flow-form">';
        echo '<input type="hidden" name="action" value="ai_suite_transition_application_status" />';
        echo '<input type="hidden" name="app_id" value="' . esc_attr( $application_id ) . '" />';
        echo '<input type="hidden" name="new_status" value="' . esc_attr( $to ) . '" />';
        wp_nonce_field( 'ai_suite_transition_status_' . $application_id );
        echo '<button type="submit" class="button ai-flow-btn">' . esc_html( $statuses[ $to ] ) . '</button>';
        echo '</form>';
    }
    echo '</div>';
    echo '</div>';
}

// Update status + tags
echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:12px;">';
echo '<input type="hidden" name="action" value="ai_suite_update_application" />';
echo '<input type="hidden" name="app_id" value="' . esc_attr( $application_id ) . '" />';
wp_nonce_field( 'ai_suite_update_application_' . $application_id );

echo '<label><strong>' . esc_html__( 'Schimbă status:', 'ai-suite' ) . '</strong></label><br />';
echo '<select name="new_status" style="min-width:240px;">';
foreach ( $statuses as $k => $lab ) {
    echo '<option value="' . esc_attr( $k ) . '" ' . selected( $status, (string) $k, false ) . '>' . esc_html( $lab ) . '</option>';
}
echo '</select>';

echo '<div style="height:10px;"></div>';
echo '<label><strong>' . esc_html__( 'Etichete (tags):', 'ai-suite' ) . '</strong></label><br />';
echo '<input type="text" name="tags" class="regular-text" value="' . esc_attr( implode( ', ', $tags ) ) . '" placeholder="ex: urgent, english, senior" />';
echo '<p class="description">' . esc_html__( 'Separă etichetele prin virgulă.', 'ai-suite' ) . '</p>';

echo '<button type="submit" class="button button-primary">' . esc_html__( 'Salvează', 'ai-suite' ) . '</button>';
echo '</form>';

// Add note
echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
echo '<input type="hidden" name="action" value="ai_suite_add_application_note" />';
echo '<input type="hidden" name="app_id" value="' . esc_attr( $application_id ) . '" />';
wp_nonce_field( 'ai_suite_add_note_' . $application_id );
echo '<label><strong>' . esc_html__( 'Adaugă notiță internă:', 'ai-suite' ) . '</strong></label>';
echo '<textarea name="note" rows="3" class="large-text" placeholder="' . esc_attr__( 'Ex: Sunat, programat interviu, a cerut salariu X...', 'ai-suite' ) . '"></textarea>';
echo '<button type="submit" class="button">' . esc_html__( 'Adaugă notiță', 'ai-suite' ) . '</button>';
echo '</form>';

echo '</div>';
echo '</div>'; // grid

echo '<hr />';
echo '<h3>' . esc_html__( 'Mesaj candidat', 'ai-suite' ) . '</h3>';
echo '<div style="background:#fff;border:1px solid #e5e5e5;border-radius:8px;padding:12px;">' . nl2br( esc_html( $message ) ) . '</div>';

// Feedback către candidat
$settings = function_exists( 'aisuite_get_settings' ) ? (array) aisuite_get_settings() : array();
$tpl_feedback = ! empty( $settings['email_tpl_candidate_feedback'] ) ? (string) $settings['email_tpl_candidate_feedback'] : "Salut, {CANDIDATE_NAME}!\n\nStatus aplicație: {STATUS_LABEL}\n\nFeedback: {FEEDBACK}\n\nMulțumim!\n";

echo '<hr />';
echo '<h3>' . esc_html__( 'Trimite feedback candidat', 'ai-suite' ) . '</h3>';
echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
echo '<input type="hidden" name="action" value="ai_suite_send_application_feedback" />';
echo '<input type="hidden" name="app_id" value="' . esc_attr( $application_id ) . '" />';
wp_nonce_field( 'ai_suite_send_feedback_' . $application_id );
echo '<p><strong>' . esc_html__( 'Către:', 'ai-suite' ) . '</strong> ' . esc_html( $email ? $email : '—' ) . '</p>';
echo '<label><strong>' . esc_html__( 'Subiect:', 'ai-suite' ) . '</strong></label><br />';
echo '<input type="text" name="subject" class="regular-text" value="' . esc_attr( sprintf( __( 'Actualizare aplicație – %s', 'ai-suite' ), ( $job_id ? get_the_title( $job_id ) : '' ) ) ) . '" />';
echo '<div style="height:10px;"></div>';
echo '<label><strong>' . esc_html__( 'Mesaj (poți edita):', 'ai-suite' ) . '</strong></label>';
echo '<textarea name="feedback" rows="6" class="large-text code">' . esc_textarea( $tpl_feedback ) . '</textarea>';
echo '<p class="description">' . esc_html__( 'Token-uri: {JOB_TITLE}, {CANDIDATE_NAME}, {STATUS_LABEL}, {FEEDBACK}', 'ai-suite' ) . '</p>';
echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Trimite feedback', 'ai-suite' ) . '</button>';
echo '</form>';

// Notes list
echo '<hr />';
echo '<h3>' . esc_html__( 'Notițe interne', 'ai-suite' ) . '</h3>';
if ( empty( $notes ) ) {
    echo '<p>' . esc_html__( 'Nu există notițe încă.', 'ai-suite' ) . '</p>';
} else {
    echo '<ul style="margin-left:18px;">';
    $notes = array_reverse( $notes );
    foreach ( $notes as $n ) {
        $t = ! empty( $n['time'] ) ? (int) $n['time'] : 0;
        $txt = ! empty( $n['text'] ) ? (string) $n['text'] : '';
        $uid = ! empty( $n['user'] ) ? (int) $n['user'] : 0;
        $user_label = $uid ? (string) ( get_userdata( $uid ) ? get_userdata( $uid )->display_name : '' ) : '';
        $note_id = ! empty( $n['id'] ) ? (string) $n['id'] : '';
        $del = '';
        if ( $note_id ) {
            $del_url = wp_nonce_url(
                admin_url( 'admin-post.php?action=ai_suite_delete_application_note&app_id=' . $application_id . '&note_id=' . rawurlencode( $note_id ) ),
                'ai_suite_delete_note_' . $application_id
            );
            $del = ' <a href="' . esc_url( $del_url ) . '" class="ai-note-del" onclick="return confirm(\'' . esc_js( __( 'Ștergi această notiță?', 'ai-suite' ) ) . '\');">' . esc_html__( 'Șterge', 'ai-suite' ) . '</a>';
        }
        echo '<li><strong>' . esc_html( $t ? date_i18n( 'Y-m-d H:i', $t ) : '' ) . ':</strong> ' . esc_html( $txt );
        if ( $user_label ) {
            echo ' <span class="ai-note-meta">(' . esc_html( $user_label ) . ')</span>';
        }
        echo $del . '</li>';
    }
    echo '</ul>';
}

// Istoric status (audit log)
echo '<hr />';
echo '<h3>' . esc_html__( 'Istoric status (audit)', 'ai-suite' ) . '</h3>';
if ( empty( $status_history ) ) {
    echo '<p>' . esc_html__( 'Nu există încă intrări de istoric.', 'ai-suite' ) . '</p>';
} else {
    echo '<ul style="margin-left:18px;">';
    $status_history = array_reverse( $status_history );
    foreach ( $status_history as $h ) {
        $t = ! empty( $h['time'] ) ? (int) $h['time'] : 0;
        $from = ! empty( $h['from'] ) ? (string) $h['from'] : '';
        $to = ! empty( $h['to'] ) ? (string) $h['to'] : '';
        $uid = ! empty( $h['user'] ) ? (int) $h['user'] : 0;
        $ctx = ! empty( $h['context'] ) ? (string) $h['context'] : '';
        $user_label = $uid ? (string) ( get_userdata( $uid ) ? get_userdata( $uid )->display_name : '' ) : '';
        $from_l = isset( $statuses[ $from ] ) ? $statuses[ $from ] : $from;
        $to_l   = isset( $statuses[ $to ] ) ? $statuses[ $to ] : $to;
        echo '<li><strong>' . esc_html( $t ? date_i18n( 'Y-m-d H:i', $t ) : '' ) . ':</strong> ' . esc_html( $from_l . ' → ' . $to_l );
        if ( $user_label ) {
            echo ' <span class="ai-note-meta">(' . esc_html( $user_label ) . ')</span>';
        }
        if ( $ctx ) {
            echo ' <span class="ai-note-meta">[' . esc_html( $ctx ) . ']</span>';
        }
        echo '</li>';
    }
    echo '</ul>';
}

// Timeline
echo '<hr />';
echo '<h3>' . esc_html__( 'Timeline', 'ai-suite' ) . '</h3>';
if ( empty( $timeline ) ) {
    echo '<p>' . esc_html__( 'Nu există evenimente încă.', 'ai-suite' ) . '</p>';
} else {
    echo '<ul style="margin-left:18px;">';
    $timeline = array_reverse( $timeline );
    foreach ( $timeline as $ev ) {
        $t = ! empty( $ev['time'] ) ? (int) $ev['time'] : 0;
        $e = ! empty( $ev['event'] ) ? (string) $ev['event'] : '';
        echo '<li><strong>' . esc_html( $t ? date_i18n( 'Y-m-d H:i', $t ) : '' ) . ':</strong> ' . esc_html( $e ) . '</li>';
    }
    echo '</ul>';
}

echo '</div>';
