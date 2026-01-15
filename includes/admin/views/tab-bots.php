<?php
/**
 * Bots management tab.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo '<div class="ai-card">';
echo '<h2>' . esc_html__( 'Boți AI', 'ai-suite' ) . '</h2>';

echo '<p class="ais-subtitle">' . esc_html__( 'Activează/dezactivează boți, rulează manual și verifică statusul.', 'ai-suite' ) . '</p>';

echo '<div class="ais-actions" style="display:flex;gap:10px;flex-wrap:wrap;margin:10px 0 14px;">';
  // Bulk enable/disable (admin only)
  if ( current_user_can( 'manage_ai_suite' ) ) {
      $bulk_url = admin_url( 'admin-post.php?action=ai_suite_toggle_all_bots' );
      echo '<form method="post" action="' . esc_url( $bulk_url ) . '" style="display:inline-flex;gap:8px;align-items:center;margin:0;">';
        wp_nonce_field( 'ai_suite_toggle_all_bots' );
        echo '<input type="hidden" name="mode" value="enable" />';
        echo '<button type="submit" class="button ais-btn"><span class="dashicons dashicons-yes"></span> ' . esc_html__( 'Activează toți', 'ai-suite' ) . '</button>';
      echo '</form>';

      echo '<form method="post" action="' . esc_url( $bulk_url ) . '" style="display:inline-flex;gap:8px;align-items:center;margin:0;">';
        wp_nonce_field( 'ai_suite_toggle_all_bots' );
        echo '<input type="hidden" name="mode" value="disable" />';
        echo '<button type="submit" class="button ais-btn"><span class="dashicons dashicons-no"></span> ' . esc_html__( 'Dezactivează toți', 'ai-suite' ) . '</button>';
      echo '</form>';
  }

  echo '<button type="button" class="button button-primary ai-run-bots-all" data-label="' . esc_attr__( 'Rulează toți boții activi', 'ai-suite' ) . '" data-running="' . esc_attr__( 'Rulează...', 'ai-suite' ) . '">';
    echo '<span class="dashicons dashicons-controls-play"></span> ' . esc_html__( 'Rulează toți boții activi', 'ai-suite' );
  echo '</button>';
echo '</div>';


$bots = class_exists( 'AI_Suite_Registry' ) ? AI_Suite_Registry::get_all() : array();

if ( ! empty( $bots ) ) {
    echo '<table class="wp-list-table widefat striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__( 'Bot', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Activat', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Ultima rulare', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Ultimul status', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Acțiuni', 'ai-suite' ) . '</th>';
    echo '</tr></thead>';

    echo '<tbody>';
    foreach ( $bots as $key => $bot ) {
        $enabled     = ! empty( $bot['enabled'] );
        $last_run    = ! empty( $bot['last_run'] ) ? esc_html( $bot['last_run'] ) : '&ndash;';
        $last_status = ! empty( $bot['last_status'] ) ? esc_html( $bot['last_status'] ) : '&ndash;';

        echo '<tr>';
        echo '<td>' . esc_html( $bot['label'] ) . '</td>';
        echo '<td>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="ai_suite_toggle_bot" />';
        echo '<input type="hidden" name="bot_key" value="' . esc_attr( $key ) . '" />';
        wp_nonce_field( 'ai_suite_toggle_bot' );
        echo '<label>';
        echo '<input type="checkbox" name="enabled" value="1" ' . checked( $enabled, true, false ) . ' onchange="this.form.submit();" />';
        echo ' ' . esc_html__( 'Activat', 'ai-suite' );
        echo '</label>';
        echo '</form>';
        echo '</td>';
        echo '<td>' . $last_run . '</td>';
        echo '<td>' . $last_status . '</td>';
        echo '<td>';
        echo '<button class="button ai-run-bot" data-bot="' . esc_attr( $key ) . '">' . esc_html__( 'Rulează', 'ai-suite' ) . '</button>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
} else {
    echo '<p>' . esc_html__( 'Niciun bot înregistrat.', 'ai-suite' ) . '</p>';
}

echo '<div id="ai-bots-result" class="ai-result" style="margin-top:16px;"></div>';

echo '</div>';