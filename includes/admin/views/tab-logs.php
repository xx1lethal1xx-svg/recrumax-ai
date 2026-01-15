<?php
/**
 * Logs tab view.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo '<div class="ai-card">';
echo '<h2>' . esc_html__( 'Jurnal activitate', 'ai-suite' ) . '</h2>';
echo '<p class="description">' . esc_html__( 'Vezi loguri (erori, acțiuni și evenimente).', 'ai-suite' ) . '</p>';

$level = isset( $_GET['level'] ) ? sanitize_key( wp_unslash( $_GET['level'] ) ) : '';
$q     = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
$q     = trim( $q );

$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=ai_suite_export_logs_json' ), 'ai_suite_export_logs_json' );

// Filters + export
echo '<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between;margin:12px 0 10px;">';
  echo '<form method="get" action="" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:0;">';
    echo '<input type="hidden" name="page" value="ai-suite" />';
    echo '<input type="hidden" name="tab" value="logs" />';
    echo '<select name="level" style="min-width:160px;border-radius:12px;">';
      echo '<option value="">' . esc_html__( 'Toate nivelurile', 'ai-suite' ) . '</option>';
      $levels = array( 'info' => 'INFO', 'warning' => 'WARNING', 'error' => 'ERROR' );
      foreach ( $levels as $k => $lbl ) {
          echo '<option value="' . esc_attr( $k ) . '" ' . selected( $level, $k, false ) . '>' . esc_html( $lbl ) . '</option>';
      }
    echo '</select>';
    echo '<input type="search" name="q" value="' . esc_attr( $q ) . '" placeholder="' . esc_attr__( 'Caută în loguri...', 'ai-suite' ) . '" class="regular-text" style="min-width:260px;border-radius:12px;" />';
    echo '<button class="button" type="submit">' . esc_html__( 'Filtrează', 'ai-suite' ) . '</button>';
    if ( $level || $q ) {
        echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=ai-suite&tab=logs' ) ) . '">' . esc_html__( 'Reset', 'ai-suite' ) . '</a>';
    }
  echo '</form>';

  echo '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">';
    echo '<a class="button ais-btn" href="' . esc_url( $export_url ) . '"><span class="dashicons dashicons-download"></span> ' . esc_html__( 'Export JSON', 'ai-suite' ) . '</a>';
  echo '</div>';
echo '</div>';

$logs = get_option( AI_SUITE_OPTION_LOGS, array() );
if ( ! is_array( $logs ) ) {
    $logs = array();
}

// Apply filters (level/q)
if ( ( $level || $q ) && ! empty( $logs ) ) {
    $logs = array_values( array_filter( $logs, function( $row ) use ( $level, $q ) {
        $lvl = isset( $row['level'] ) ? sanitize_key( (string) $row['level'] ) : '';
        if ( $level && $lvl !== $level ) {
            return false;
        }
        if ( $q ) {
            $hay = wp_json_encode( $row, JSON_UNESCAPED_UNICODE );
            if ( stripos( (string) $hay, (string) $q ) === false ) {
                return false;
            }
        }
        return true;
    } ) );
}

// Notice.
if ( isset( $_GET['notice'] ) && 'cleared' === sanitize_key( wp_unslash( $_GET['notice'] ) ) ) {
    echo '<div class="updated notice"><p>' . esc_html__( 'Jurnalul a fost curățat.', 'ai-suite' ) . '</p></div>';
}

// Clear logs button.
echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:10px;">';
echo '<input type="hidden" name="action" value="ai_suite_clear_logs" />';
wp_nonce_field( 'ai_suite_clear_logs' );
echo '<button type="submit" class="button">' . esc_html__( 'Șterge jurnalul', 'ai-suite' ) . '</button>';
echo '</form>';

if ( ! empty( $logs ) ) {
    echo '<table class="wp-list-table widefat striped">';
    echo '<thead><tr>';
    echo '<th style="width:170px;">' . esc_html__( 'Timp', 'ai-suite' ) . '</th>';
    echo '<th style="width:90px;">' . esc_html__( 'Nivel', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Mesaj', 'ai-suite' ) . '</th>';
    echo '<th style="width:360px;">' . esc_html__( 'Context', 'ai-suite' ) . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';
    foreach ( array_reverse( $logs ) as $log ) {
        $time    = isset( $log['time'] ) ? esc_html( $log['time'] ) : '&ndash;';
        $lvl     = isset( $log['level'] ) ? esc_html( $log['level'] ) : '&ndash;';
        $message = isset( $log['message'] ) ? esc_html( $log['message'] ) : '';
        $context = isset( $log['context'] ) ? maybe_serialize( $log['context'] ) : '';
        echo '<tr>';
        echo '<td>' . $time . '</td>';
        echo '<td><span class="ais-pill">' . $lvl . '</span></td>';
        echo '<td>' . $message . '</td>';
        echo '<td><pre style="white-space:pre-wrap;max-width:340px;margin:0;">' . esc_html( $context ) . '</pre></td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
} else {
    echo '<p>' . esc_html__( 'Nu există înregistrări încă.', 'ai-suite' ) . '</p>';
}

echo '</div>';
