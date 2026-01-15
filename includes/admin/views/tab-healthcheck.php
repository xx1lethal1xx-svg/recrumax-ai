<?php
/**
 * Healthcheck tab view.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo '<div class="ai-card">';
echo '<h2>' . esc_html__( 'Verificare sistem', 'ai-suite' ) . '</h2>';
echo '<p>' . esc_html__( 'Rulează verificări tehnice pentru cheile API, evenimentele cron, permisiuni și versiuni.', 'ai-suite' ) . '</p>';

echo '<button class="button button-primary" id="ai-run-healthcheck">' . esc_html__( 'Rulează verificarea', 'ai-suite' ) . '</button> ';
echo '<button class="button" id="ai-test-openai">' . esc_html__( 'Testează OpenAI', 'ai-suite' ) . '</button>';

echo '<div id="ai-healthcheck-result" class="ai-result" style="margin-top:16px;"></div>';
echo '</div>';