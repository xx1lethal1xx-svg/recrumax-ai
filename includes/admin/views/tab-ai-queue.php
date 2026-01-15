<?php
/**
 * Admin Tab: AI Queue (v1.8.1)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_ai_suite' ) ) {
    echo '<div class="notice notice-error"><p>' . esc_html__( 'Neautorizat.', 'ai-suite' ) . '</p></div>';
    return;
}

global $wpdb;

$table = function_exists( 'ai_suite_queue_table_name' ) ? ai_suite_queue_table_name() : '';
$table_exists = false;
if ( $table ) {
    $like = $wpdb->esc_like( $table );
    $table_exists = (bool) $wpdb->get_var( "SHOW TABLES LIKE '{$like}'" );
}

echo '<div class="ai-card">';
echo '<h2 style="margin-top:0;">' . esc_html__( 'Coada AI (Task-uri asincrone)', 'ai-suite' ) . '</h2>';
echo '<p class="description">' . esc_html__( 'Aici vezi și rulezi task-urile AI (scoring candidat, sumar, email feedback). Task-urile sunt procesate periodic prin WP-Cron, dar le poți rula și manual.', 'ai-suite' ) . '</p>';

if ( ! $table_exists ) {
    echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Tabela AI Queue lipsește.', 'ai-suite' ) . '</strong> ' . esc_html__( 'Apasă „Instalează/Repair” ca să o creezi.', 'ai-suite' ) . '</p></div>';
    echo '<p><button class="button button-primary" id="ai-queue-install">' . esc_html__( 'Instalează / Repair', 'ai-suite' ) . '</button></p>';
    echo '<div id="ai-queue-result"></div>';
    echo '</div>';
    return;
}

// Statistici
$counts = array( 'pending' => 0, 'running' => 0, 'done' => 0, 'failed' => 0 );
foreach ( array_keys( $counts ) as $st ) {
    $counts[ $st ] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(1) FROM {$table} WHERE status=%s", $st ) );
}

echo '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:10px 0 14px;">';
foreach ( $counts as $st => $cnt ) {
    $label = strtoupper( $st );
    if ( $st === 'pending' ) { $label = __( 'În așteptare', 'ai-suite' ); }
    if ( $st === 'running' ) { $label = __( 'În lucru', 'ai-suite' ); }
    if ( $st === 'done' )    { $label = __( 'Finalizate', 'ai-suite' ); }
    if ( $st === 'failed' )  { $label = __( 'Eșuate', 'ai-suite' ); }
    echo '<div class="ai-kpi"><div class="ai-kpi-num">' . esc_html( (string) $cnt ) . '</div><div class="ai-kpi-label">' . esc_html( $label ) . '</div></div>';
}
echo '</div>';

echo '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin:12px 0;">';
echo '<button class="button button-primary" id="ai-queue-run">' . esc_html__( 'Rulează worker acum', 'ai-suite' ) . '</button>';
echo '<button class="button" id="ai-queue-refresh">' . esc_html__( 'Refresh listă', 'ai-suite' ) . '</button>';
echo '<button class="button" id="ai-queue-purge">' . esc_html__( 'Curăță finalizate (14 zile+)', 'ai-suite' ) . '</button>';
echo '<span class="description" style="margin-left:6px;">' . esc_html__( 'Tipic: worker rulează la fiecare ~2 minute (WP-Cron).', 'ai-suite' ) . '</span>';
echo '</div>';
echo '<div id="ai-queue-result"></div>';

// Listă
$items = function_exists( 'ai_suite_queue_list' ) ? ai_suite_queue_list( array( 'limit' => 60 ) ) : array();

echo '<h3 style="margin-top:18px;">' . esc_html__( 'Ultimele task-uri', 'ai-suite' ) . '</h3>';

if ( empty( $items ) ) {
    echo '<p>' . esc_html__( 'Nu există task-uri în coadă.', 'ai-suite' ) . '</p>';
    echo '</div>';
    return;
}

echo '<table class="widefat striped">';
echo '<thead><tr>';
echo '<th style="width:70px;">' . esc_html__( 'ID', 'ai-suite' ) . '</th>';
echo '<th style="width:160px;">' . esc_html__( 'Tip', 'ai-suite' ) . '</th>';
echo '<th style="width:110px;">' . esc_html__( 'Status', 'ai-suite' ) . '</th>';
echo '<th style="width:80px;">' . esc_html__( 'Încercări', 'ai-suite' ) . '</th>';
echo '<th style="width:180px;">' . esc_html__( 'Run at (GMT)', 'ai-suite' ) . '</th>';
echo '<th>' . esc_html__( 'Payload / Rezultat', 'ai-suite' ) . '</th>';
echo '<th style="width:150px;">' . esc_html__( 'Acțiuni', 'ai-suite' ) . '</th>';
echo '</tr></thead><tbody>';

foreach ( $items as $it ) {
    $id = (int) ( $it['id'] ?? 0 );
    $type = (string) ( $it['type'] ?? '' );
    $status = (string) ( $it['status'] ?? '' );
    $attempts = (int) ( $it['attempts'] ?? 0 );
    $max_attempts = (int) ( $it['max_attempts'] ?? 3 );
    $run_at = (string) ( $it['run_at'] ?? '' );
    $payload = (string) ( $it['payload'] ?? '' );
    $result = (string) ( $it['result'] ?? '' );
    $err = (string) ( $it['last_error'] ?? '' );

    $mini = '';
    if ( $result ) {
        $mini = $result;
    } elseif ( $err ) {
        $mini = 'ERROR: ' . $err;
    } else {
        $mini = $payload;
    }
    $mini = wp_strip_all_tags( $mini );
    if ( strlen( $mini ) > 220 ) {
        $mini = substr( $mini, 0, 220 ) . '…';
    }

    echo '<tr>';
    echo '<td><strong>' . esc_html( (string) $id ) . '</strong></td>';
    echo '<td>' . esc_html( $type ) . '</td>';
    echo '<td>' . esc_html( $status ) . '</td>';
    echo '<td>' . esc_html( $attempts . '/' . $max_attempts ) . '</td>';
    echo '<td>' . esc_html( $run_at ) . '</td>';
    echo '<td><code style="white-space:pre-wrap;display:block;max-width:520px;">' . esc_html( $mini ) . '</code></td>';
    echo '<td>';
    if ( $status === 'failed' || $status === 'done' ) {
        echo '<button class="button ai-queue-retry" data-id="' . esc_attr( (string) $id ) . '">' . esc_html__( 'Retry', 'ai-suite' ) . '</button> ';
    }
    echo '<button class="button ai-queue-delete" data-id="' . esc_attr( (string) $id ) . '">' . esc_html__( 'Șterge', 'ai-suite' ) . '</button>';
    echo '</td>';
    echo '</tr>';
}

echo '</tbody></table>';
echo '</div>';
