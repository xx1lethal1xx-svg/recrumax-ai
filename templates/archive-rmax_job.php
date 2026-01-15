<?php
/**
 * Template for the job archive (list of jobs).
 *
 * Premium public job board (SaaS look) – scoped styling via assets/jobboard.css
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$is_ro = ( function_exists( 'aisuite_is_ro_locale' ) && aisuite_is_ro_locale() );
$title = $is_ro ? 'Joburi disponibile' : 'Available jobs';
$subtitle = $is_ro
    ? 'Caută rapid jobul potrivit: cuvinte cheie, departament, locație și sortare.'
    : 'Find the right job fast: keywords, department, location and sorting.';

echo '<div class="ai-jobboard-archive ai-suite-public">';

echo '  <section class="ai-suite-hero ai-suite-hero--archive">';
echo '    <div class="ai-container">';
echo '      <div class="ai-suite-hero-grid">';
echo '        <div class="ai-suite-hero-copy">';
echo '          <div class="ai-suite-badge">' . esc_html( $is_ro ? 'RecruMax • Job Board' : 'RecruMax • Job Board' ) . '</div>';
echo '          <h1 class="ai-suite-h1">' . esc_html( $title ) . '</h1>';
echo '          <p class="ai-suite-subtitle">' . esc_html( $subtitle ) . '</p>';
echo '        </div>';
echo '        <div class="ai-suite-hero-preview" aria-hidden="true">';
echo '          <div class="ai-preview-card">';
echo '            <div class="ai-preview-top">';
echo '              <div class="ai-dot"></div><div class="ai-dot"></div><div class="ai-dot"></div>';
echo '              <div class="ai-preview-title">' . esc_html( $is_ro ? 'Filtre inteligente' : 'Smart filters' ) . '</div>';
echo '            </div>';
echo '            <div class="ai-preview-row"><span class="ai-pill">' . esc_html( $is_ro ? 'Departament' : 'Department' ) . '</span><span class="ai-pill">' . esc_html( $is_ro ? 'Locație' : 'Location' ) . '</span><span class="ai-pill ai-pill-accent">AI</span></div>';
echo '            <div class="ai-preview-cta">' . esc_html( $is_ro ? 'Aplicare rapidă cu CV' : 'Quick CV apply' ) . '</div>';
echo '          </div>';
echo '        </div>';
echo '      </div>';
echo '    </div>';
echo '  </section>';

echo '  <div class="ai-container">';
// List jobs using the shortcode with default per_page.
echo do_shortcode( '[ai_suite_jobs per_page="12" show_filters="1" show_save="1"]' );
echo '  </div>';

echo '</div>';

get_footer();
