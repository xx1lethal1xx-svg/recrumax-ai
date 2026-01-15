<?php
/**
 * Runs history tab view.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo '<div class="ai-card">';
echo '<h2>' . esc_html__( 'Istoric rulări', 'ai-suite' ) . '</h2>';

$runs = get_option( AI_SUITE_OPTION_RUNS, array() );
if ( ! is_array( $runs ) ) {
    $runs = array();
}

// Display notice.
if ( isset( $_GET['notice'] ) && 'cleared' === $_GET['notice'] ) {
    echo '<div class="updated notice"><p>' . esc_html__( 'Istoricul rulărilor a fost curățat.', 'ai-suite' ) . '</p></div>';
}

// Clear history form.
echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-bottom:10px;">';
echo '<input type="hidden" name="action" value="ai_suite_clear_runs" />';
wp_nonce_field( 'ai_suite_clear_runs' );
echo '<button type="submit" class="button">' . esc_html__( 'Șterge istoricul', 'ai-suite' ) . '</button>';
echo '</form>';

if ( ! empty( $runs ) ) {
    echo '<table class="wp-list-table widefat striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__( 'Timp', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Bot', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Status', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Mesaj', 'ai-suite' ) . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';
    foreach ( array_reverse( $runs ) as $run ) {
        $time    = isset( $run['time'] ) ? esc_html( $run['time'] ) : '&ndash;';
        $bot     = isset( $run['bot_key'] ) ? esc_html( $run['bot_key'] ) : '&ndash;';
        $ok      = ( isset( $run['result']['ok'] ) && $run['result']['ok'] );
        $status  = $ok ? 'ok' : 'fail';
        $message = isset( $run['result']['message'] ) ? esc_html( $run['result']['message'] ) : '';
        echo '<tr>';
        echo '<td>' . $time . '</td>';
        echo '<td>' . $bot . '</td>';
        echo '<td>' . esc_html( $status ) . '</td>';
        echo '<td>' . $message . '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
} else {
    echo '<p>' . esc_html__( 'Nu există rulări încă.', 'ai-suite' ) . '</p>';
}

echo '</div>';