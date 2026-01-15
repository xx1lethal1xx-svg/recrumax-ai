<?php
/**
 * Template for a single job listing.
 *
 * Premium public job detail – scoped styling via assets/jobboard.css
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$job_id = get_queried_object_id();

// JobPosting schema (basic)
$company_name = get_post_meta( $job_id, '_job_company_name', true );
$company_name = $company_name ? $company_name : get_bloginfo( 'name' );
$loc_name = '';
$loc_terms = wp_get_post_terms( $job_id, 'job_location', array( 'fields' => 'names' ) );
if ( ! empty( $loc_terms ) && ! is_wp_error( $loc_terms ) ) {
    $loc_name = implode( ', ', $loc_terms );
}

$schema = array(
    '@context' => 'https://schema.org',
    '@type'    => 'JobPosting',
    'title'    => get_the_title( $job_id ),
    'description' => wp_strip_all_tags( get_post_field( 'post_content', $job_id ) ),
    'datePosted'  => get_the_date( 'c', $job_id ),
    'hiringOrganization' => array(
        '@type' => 'Organization',
        'name'  => $company_name,
    ),
);
if ( $loc_name ) {
    $schema['jobLocation'] = array(
        '@type' => 'Place',
        'address' => array(
            '@type' => 'PostalAddress',
            'addressLocality' => $loc_name,
        ),
    );
}
echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>';

$is_ro = ( function_exists( 'aisuite_is_ro_locale' ) && aisuite_is_ro_locale() );

// Tax meta pills
$dept_names = wp_get_post_terms( $job_id, 'job_department', array( 'fields' => 'names' ) );
$loc_names  = wp_get_post_terms( $job_id, 'job_location', array( 'fields' => 'names' ) );

$back_url = get_post_type_archive_link( 'rmax_job' );
if ( ! $back_url ) {
    $back_url = home_url( '/' );
}

echo '<div class="ai-jobboard-single ai-suite-public">';

echo '  <section class="ai-suite-hero ai-suite-hero--single">';
echo '    <div class="ai-container">';
echo '      <div class="ai-suite-single-head">';
echo '        <a class="ai-back" href="' . esc_url( $back_url ) . '">' . esc_html( $is_ro ? 'Înapoi la joburi' : 'Back to jobs' ) . '</a>';
echo '        <h1 class="ai-suite-h1 ai-suite-h1--single">' . esc_html( get_the_title( $job_id ) ) . '</h1>';
if ( function_exists( 'aisuite_is_job_featured' ) && aisuite_is_job_featured( $job_id ) ) {
    echo '        <div class="ai-single-featured"><span class="ai-featured-badge">' . esc_html( $is_ro ? 'Promovat' : 'Sponsored' ) . '</span></div>';
}
echo '        <div class="ai-suite-meta">';
if ( $company_name ) {
    echo '          <span class="ai-meta-pill">' . esc_html( $company_name ) . '</span>';
}
if ( ! empty( $dept_names ) && ! is_wp_error( $dept_names ) ) {
    echo '          <span class="ai-meta-pill">' . esc_html( implode( ', ', $dept_names ) ) . '</span>';
}
if ( ! empty( $loc_names ) && ! is_wp_error( $loc_names ) ) {
    echo '          <span class="ai-meta-pill">' . esc_html( implode( ', ', $loc_names ) ) . '</span>';
}
echo '          <span class="ai-meta-pill ai-meta-pill-accent">' . esc_html( $is_ro ? 'Publicat' : 'Posted' ) . ': ' . esc_html( get_the_date( '', $job_id ) ) . '</span>';
echo '        </div>';
echo '        <div class="ai-suite-hero-actions">';
echo '          <a class="ai-btn ai-btn-primary ai-btn-wide" href="#apply">' . esc_html( $is_ro ? 'Aplică acum' : 'Apply now' ) . '</a>';
echo '          <a class="ai-btn ai-btn-ghost ai-btn-wide" href="#details">' . esc_html( $is_ro ? 'Vezi detalii' : 'See details' ) . '</a>';
echo '        </div>';
echo '      </div>';
echo '    </div>';
echo '  </section>';

// Loop content
if ( have_posts() ) {
    while ( have_posts() ) {
        the_post();

        echo '  <div class="ai-container">';

        echo '    <div id="details" class="ai-job-layout">';

        echo '      <div class="ai-job-main">';
        echo '        <div class="ai-card ai-card--content">';
        echo '          <h2 class="ai-suite-h2">' . esc_html( $is_ro ? 'Descriere job' : 'Job description' ) . '</h2>';
        echo '          <div class="ai-job-content">';
        the_content();
        echo '          </div>';
        echo '        </div>';
        echo '      </div>';

        echo '      <aside class="ai-job-aside">';
        echo '        <div class="ai-card ai-card--sticky">';
        echo '          <div class="ai-card-title">' . esc_html( $is_ro ? 'Aplicare rapidă' : 'Quick apply' ) . '</div>';
        echo '          <div class="ai-card-text">' . esc_html( $is_ro ? 'Completează formularul și atașează CV-ul.' : 'Fill the form and attach your CV.' ) . '</div>';
        echo '          <a class="ai-btn ai-btn-primary ai-btn-wide" href="#apply">' . esc_html( $is_ro ? 'Aplică' : 'Apply' ) . '</a>';
        echo '        </div>';
        echo '      </aside>';

        echo '    </div>';

        echo '    <div id="apply" class="ai-apply-section">';
        echo '      <div class="ai-card ai-card--apply">';
        echo '        <h2 class="ai-suite-h2">' . esc_html( $is_ro ? 'Aplică pentru acest job' : 'Apply for this job' ) . '</h2>';
        echo do_shortcode( '[ai_suite_apply_form job_id="' . get_the_ID() . '"]' );
        echo '      </div>';
        echo '    </div>';

        echo '  </div>';
    }
}

echo '</div>';

get_footer();
