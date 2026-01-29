<?php
/**
 * Frontend integration for AI Suite.
 *
 * Provides template overrides for job listings and single job pages,
 * shortcodes for displaying jobs and application forms,
 * handling application form submissions, and enqueuing frontend assets.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AI_Suite_Frontend' ) ) {
    /**
     * Class AI_Suite_Frontend
     *
     * Handles all frontend functionality for the recruitment module.
     */
    final class AI_Suite_Frontend {
        /**
         * Boot the frontend features.
         *
         * Hooks into WordPress to register templates, assets, shortcodes and form actions.
         */
        public static function boot() {
            // PRO layer for applications (validări, upload securizat, emailuri).
            $pro = trailingslashit( AI_SUITE_DIR ) . 'includes/recruitment/applications-pro.php';
            if ( file_exists( $pro ) ) {
                require_once $pro;
            }

            // Override templates for job archive and single job.
            add_filter( 'template_include', [ __CLASS__, 'template' ], 50 );

            // Enqueue styles when needed on the front end.
            add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

            // Shortcodes for job list and application form.
            add_shortcode( 'ai_suite_jobs', [ __CLASS__, 'jobs_shortcode' ] );
            add_shortcode( 'ai_suite_apply_form', [ __CLASS__, 'apply_form_shortcode' ] );
            add_shortcode( 'ai_suite_landing', [ __CLASS__, 'landing_shortcode' ] );


            // Saved jobs (candidate)
            add_action( 'wp_ajax_ai_suite_toggle_save_job', array( __CLASS__, 'ajax_toggle_save_job' ) );
            add_action( 'wp_ajax_ai_suite_get_saved_jobs', array( __CLASS__, 'ajax_get_saved_jobs' ) );

            // Handle application form submissions (logged in and anonymous).
            add_action( 'admin_post_ai_suite_submit_application', [ __CLASS__, 'handle_apply_form' ] );
            add_action( 'admin_post_nopriv_ai_suite_submit_application', [ __CLASS__, 'handle_apply_form' ] );
        }

        /**
         * Override the template for job archive and single job pages.
         *
         * @param string $template The path to the template to include.
         * @return string
         */
        public static function template( $template ) {
            if ( is_singular( 'rmax_job' ) ) {
                $tpl = trailingslashit( AI_SUITE_DIR ) . 'templates/single-rmax_job.php';
                if ( file_exists( $tpl ) ) {
                    return $tpl;
                }
            }
            if ( is_post_type_archive( 'rmax_job' ) ) {
                $tpl = trailingslashit( AI_SUITE_DIR ) . 'templates/archive-rmax_job.php';
                if ( file_exists( $tpl ) ) {
                    return $tpl;
                }
            }
            return $template;
        }

        /**
         * Enqueue frontend assets for the job board when appropriate.
         */
        public static function enqueue_assets() {
            // Determine if current page needs jobboard assets.
            $needs_assets = false;
            if ( is_singular( 'rmax_job' ) || is_post_type_archive( 'rmax_job' ) ) {
                $needs_assets = true;
            } else {
                // Check if current page content contains our shortcodes.
                $post_id = get_queried_object_id();
                if ( $post_id ) {
                    $content = get_post_field( 'post_content', $post_id );
                    if ( $content && is_string( $content ) ) {
                        if ( has_shortcode( $content, 'ai_suite_jobs' ) || has_shortcode( $content, 'ai_suite_apply_form' ) || has_shortcode( $content, 'ai_suite_landing' ) ) {
                            $needs_assets = true;
                        }
                    }
                }
            }
            if ( ! $needs_assets ) {
                return;
            }
            // Enqueue our CSS file.
            wp_enqueue_style( 'ai-suite-jobboard', AI_SUITE_URL . 'assets/jobboard.css', [], AI_SUITE_VER );
            wp_enqueue_style( 'ai-suite-premium', AI_SUITE_URL . 'assets/premium/aisuite-premium.css', array('ai-suite-jobboard'), AI_SUITE_VER );

            // JS for saved jobs / UX enhancements.
            wp_enqueue_script( 'ai-suite-jobboard-market', AI_SUITE_URL . 'assets/jobboard-market.js', array( 'jquery' ), AI_SUITE_VER, true );
            wp_localize_script( 'ai-suite-jobboard-market', 'AISuiteJobboard', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'ai_suite_jobboard' ),
                'isRo'    => ( function_exists( 'aisuite_is_ro_locale' ) && aisuite_is_ro_locale() ) ? 1 : 0,
            ) );
        }

        /**
         * Shortcode to list available jobs.
         *
         * Usage: [ai_suite_jobs per_page="10"]
         *
         * @param array $atts Shortcode attributes.
         * @return string HTML output.
         */
        

public static function jobs_shortcode( $atts = [] ) {
    $atts = shortcode_atts( array(
        'per_page' => 12,
        'show_filters' => 1,
        'show_save' => 1,
    ), $atts, 'ai_suite_jobs' );

    $per_page     = max( 1, intval( $atts['per_page'] ) );
    $show_filters = (int) $atts['show_filters'] === 1;
    $show_save    = (int) $atts['show_save'] === 1;

    // Read filters from query string.
    $q  = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
    $dep = isset( $_GET['dep'] ) ? sanitize_key( wp_unslash( $_GET['dep'] ) ) : '';
    $loc = isset( $_GET['loc'] ) ? sanitize_key( wp_unslash( $_GET['loc'] ) ) : '';
    $sort = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : 'new';

    $paged = 1;
    if ( isset( $_GET['pg'] ) ) {
        $paged = max( 1, intval( $_GET['pg'] ) );
    } elseif ( get_query_var( 'paged' ) ) {
        $paged = max( 1, intval( get_query_var( 'paged' ) ) );
    }

    $tax_query = array();
    if ( $dep ) {
        $tax_query[] = array(
            'taxonomy' => 'job_department',
            'field'    => 'slug',
            'terms'    => array( $dep ),
        );
    }
    if ( $loc ) {
        $tax_query[] = array(
            'taxonomy' => 'job_location',
            'field'    => 'slug',
            'terms'    => array( $loc ),
        );
    }
    if ( count( $tax_query ) > 1 ) {
        $tax_query['relation'] = 'AND';
    }

    $orderby = 'date';
    $order   = 'DESC';
    if ( 'old' === $sort ) {
        $order = 'ASC';
    } elseif ( 'title' === $sort ) {
        $orderby = 'title';
        $order   = 'ASC';
    }

    $args = array(
        'post_type'      => 'rmax_job',
        'posts_per_page' => $per_page,
        'post_status'    => 'publish',
        'orderby'        => $orderby,
        'order'          => $order,
        'paged'          => $paged,
        's'              => $q ? $q : '',
    );

    if ( ! empty( $tax_query ) ) {
        $args['tax_query'] = $tax_query;
    }

$now = time();

// Featured / Sponsored jobs (MVP): show them in a dedicated section and avoid duplicates in the main list.
$featured_query = null;
$featured_args = $args;
$featured_args['posts_per_page'] = min( 6, $per_page );
$featured_args['paged'] = 1;
$featured_args['meta_key'] = '_rmax_featured_until';
$featured_args['orderby'] = array(
    'meta_value_num' => 'DESC',
    'date'           => 'DESC',
);
$featured_args['meta_query'] = array(
    array(
        'key'     => '_rmax_featured',
        'value'   => '1',
        'compare' => '=',
    ),
    array(
        'relation' => 'OR',
        array(
            'key'     => '_rmax_featured_until',
            'compare' => 'NOT EXISTS',
        ),
        array(
            'key'     => '_rmax_featured_until',
            'value'   => $now,
            'type'    => 'NUMERIC',
            'compare' => '>=',
        ),
    ),
);

$featured_query = new WP_Query( $featured_args );

// Exclude active featured jobs from normal results (so they don't appear twice).
$args['meta_query'] = array(
    'relation' => 'OR',
    array(
        'key'     => '_rmax_featured',
        'compare' => 'NOT EXISTS',
    ),
    array(
        'key'     => '_rmax_featured',
        'value'   => '1',
        'compare' => '!=',
    ),
    array(
        'relation' => 'AND',
        array(
            'key'     => '_rmax_featured',
            'value'   => '1',
            'compare' => '=',
        ),
        array(
            'key'     => '_rmax_featured_until',
            'value'   => $now,
            'type'    => 'NUMERIC',
            'compare' => '<',
        ),
    ),
);

    $query = new WP_Query( $args );

    $is_candidate = function_exists( 'aisuite_current_user_is_candidate' ) ? aisuite_current_user_is_candidate() : false;
    $is_ro = ( function_exists( 'aisuite_is_ro_locale' ) && aisuite_is_ro_locale() );
    $saved = array();
    if ( $is_candidate ) {
        $saved = (array) get_user_meta( get_current_user_id(), '_ai_suite_saved_jobs', true );
        $saved = array_map( 'intval', $saved );
    }

    ob_start();

    echo '<div class="ai-jobboard-wrap" data-ai-suite-jobboard="1">';

    if ( $show_filters ) {
        $deps = get_terms( array( 'taxonomy' => 'job_department', 'hide_empty' => false ) );
        $locs = get_terms( array( 'taxonomy' => 'job_location', 'hide_empty' => false ) );

        echo '<form class="ai-jobboard-filters" method="get">';
        echo '<div class="ai-jobboard-filter-row">';
        echo '<input type="text" name="q" placeholder="' . esc_attr( $is_ro ? 'Caută joburi (cuvinte cheie)' : 'Search jobs (keywords)' ) . '" value="' . esc_attr( $q ) . '" />';
        echo '<select name="dep">';
        echo '<option value="">' . esc_html( $is_ro ? 'Departament' : 'Department' ) . '</option>';
        if ( ! is_wp_error( $deps ) ) {
            foreach ( $deps as $t ) {
                echo '<option value="' . esc_attr( $t->slug ) . '"' . selected( $dep, $t->slug, false ) . '>' . esc_html( $t->name ) . '</option>';
            }
        }
        echo '</select>';

        echo '<select name="loc">';
        echo '<option value="">' . esc_html( $is_ro ? 'Locație' : 'Location' ) . '</option>';
        if ( ! is_wp_error( $locs ) ) {
            foreach ( $locs as $t ) {
                echo '<option value="' . esc_attr( $t->slug ) . '"' . selected( $loc, $t->slug, false ) . '>' . esc_html( $t->name ) . '</option>';
            }
        }
        echo '</select>';

        echo '<select name="sort">';
        echo '<option value="new"' . selected( $sort, 'new', false ) . '>' . esc_html( $is_ro ? 'Cele mai noi' : 'Newest' ) . '</option>';
        echo '<option value="old"' . selected( $sort, 'old', false ) . '>' . esc_html( $is_ro ? 'Cele mai vechi' : 'Oldest' ) . '</option>';
        echo '<option value="title"' . selected( $sort, 'title', false ) . '>' . esc_html( $is_ro ? 'Alfabetic' : 'A–Z' ) . '</option>';
        echo '</select>';

        echo '<button type="submit" class="ai-jobboard-btn">' . esc_html( $is_ro ? 'Caută' : 'Search' ) . '</button>';
        echo '</div>';
        echo '</form>';
    }

$has_featured = ( $featured_query instanceof WP_Query ) && $featured_query->have_posts();

if ( $has_featured ) {
    echo '<section class="ai-featured-section">';
    echo '  <div class="ai-featured-head">';
    echo '    <h2 class="ai-featured-title">' . esc_html( $is_ro ? 'Joburi promovate' : 'Featured jobs' ) . '</h2>';
    echo '    <p class="ai-featured-subtitle">' . esc_html( $is_ro ? 'Aceste anunțuri sunt promovate pentru vizibilitate extra.' : 'These jobs are sponsored for extra visibility.' ) . '</p>';
    echo '  </div>';
    echo '  <div class="ai-jobboard-grid ai-jobboard-grid--featured">';
    while ( $featured_query->have_posts() ) {
        $featured_query->the_post();
        $jid = get_the_ID();
        $permalink = get_permalink( $jid );

        $dept_names = wp_get_post_terms( $jid, 'job_department', array( 'fields' => 'names' ) );
        $loc_names  = wp_get_post_terms( $jid, 'job_location', array( 'fields' => 'names' ) );

        echo '<article class="ai-job-card ai-job-card--featured">';
        echo '<div class="ai-job-card-top">';
        echo '<div class="ai-job-card-title-row">';
        echo '<span class="ai-featured-badge">' . esc_html( $is_ro ? 'Promovat' : 'Sponsored' ) . '</span>';
        echo '<h3 class="ai-job-card-title"><a href="' . esc_url( $permalink ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
        echo '</div>';
        echo '<div class="ai-job-card-meta">';
        if ( ! empty( $dept_names ) && ! is_wp_error( $dept_names ) ) {
            echo '<span class="ai-job-meta-pill">' . esc_html( implode( ', ', $dept_names ) ) . '</span>';
        }
        if ( ! empty( $loc_names ) && ! is_wp_error( $loc_names ) ) {
            echo '<span class="ai-job-meta-pill">' . esc_html( implode( ', ', $loc_names ) ) . '</span>';
        }
        echo '</div>';
        echo '</div>';

        $excerpt = has_excerpt() ? get_the_excerpt() : wp_trim_words( wp_strip_all_tags( get_the_content() ), 26, '…' );
        echo '<p class="ai-job-card-excerpt">' . esc_html( $excerpt ) . '</p>';

        echo '<div class="ai-job-card-actions">';
        echo '<a class="ai-jobboard-btn ai-jobboard-btn-primary" href="' . esc_url( $permalink ) . '#apply">' . esc_html( $is_ro ? 'Aplică' : 'Apply' ) . '</a>';

        if ( $show_save ) {
            if ( $is_candidate ) {
                $is_saved = in_array( (int) $jid, $saved, true );
                $label = $is_saved ? ( $is_ro ? 'Salvat' : 'Saved' ) : ( $is_ro ? 'Salvează' : 'Save' );
                $cls = $is_saved ? 'ai-jobboard-btn ai-jobboard-btn-ghost is-saved' : 'ai-jobboard-btn ai-jobboard-btn-ghost';
                echo '<button type="button" class="' . esc_attr( $cls ) . '" data-save-job="' . esc_attr( $jid ) . '">' . esc_html( $label ) . '</button>';
            } else {
                echo '<span class="ai-job-save-hint">' . esc_html( $is_ro ? 'Autentifică-te ca să salvezi joburi' : 'Log in to save jobs' ) . '</span>';
            }
        }

        echo '</div>';
        echo '</article>';
    }
    echo '  </div>';
    echo '</section>';

    wp_reset_postdata();
}

    if ( $query->have_posts() ) {
        echo '<div class="ai-jobboard-grid">';
        while ( $query->have_posts() ) {
            $query->the_post();
            $jid = get_the_ID();
            $permalink = get_permalink( $jid );

            $dept_names = wp_get_post_terms( $jid, 'job_department', array( 'fields' => 'names' ) );
            $loc_names  = wp_get_post_terms( $jid, 'job_location', array( 'fields' => 'names' ) );

            $is_featured = function_exists( 'aisuite_is_job_featured' ) ? aisuite_is_job_featured( $jid ) : false;
            $card_class = $is_featured ? 'ai-job-card ai-job-card--featured' : 'ai-job-card';
            echo '<article class="' . esc_attr( $card_class ) . '">';
            echo '<div class="ai-job-card-top">';
            echo '<div class="ai-job-card-title-row">';
            if ( $is_featured ) { echo '<span class="ai-featured-badge">' . esc_html( $is_ro ? 'Promovat' : 'Sponsored' ) . '</span>'; }
            echo '<h3 class="ai-job-card-title"><a href="' . esc_url( $permalink ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
            echo '</div>';
            echo '<div class="ai-job-card-meta">';
            if ( ! empty( $dept_names ) && ! is_wp_error( $dept_names ) ) {
                echo '<span class="ai-job-meta-pill">' . esc_html( implode( ', ', $dept_names ) ) . '</span>';
            }
            if ( ! empty( $loc_names ) && ! is_wp_error( $loc_names ) ) {
                echo '<span class="ai-job-meta-pill">' . esc_html( implode( ', ', $loc_names ) ) . '</span>';
            }
            echo '</div>';
            echo '</div>';

            $excerpt = has_excerpt() ? get_the_excerpt() : wp_trim_words( wp_strip_all_tags( get_the_content() ), 26, '…' );
            echo '<p class="ai-job-card-excerpt">' . esc_html( $excerpt ) . '</p>';

            echo '<div class="ai-job-card-actions">';
            echo '<a class="ai-jobboard-btn ai-jobboard-btn-primary" href="' . esc_url( $permalink ) . '#apply">' . esc_html( $is_ro ? 'Aplică' : 'Apply' ) . '</a>';

            if ( $show_save ) {
                if ( $is_candidate ) {
                    $is_saved = in_array( (int) $jid, $saved, true );
                    $label = $is_saved ? esc_html( $is_ro ? 'Salvat' : 'Saved' ) : esc_html( $is_ro ? 'Salvează' : 'Save' );
                    $cls = $is_saved ? 'ai-job-save ai-job-save-saved' : 'ai-job-save';
                    echo '<button type="button" class="' . esc_attr( $cls ) . '" data-job-id="' . esc_attr( $jid ) . '">' . $label . '</button>';
                } else {
                    // Guest / non-candidate: show hint.
                    echo '<span class="ai-job-save-hint">' . esc_html( $is_ro ? 'Autentifică-te ca să salvezi joburi' : 'Log in to save jobs' ) . '</span>';
                }
            }

            echo '</div>';
            echo '</article>';
        }
        echo '</div>';

        // Pagination.
        $total_pages = max( 1, (int) $query->max_num_pages );
        if ( $total_pages > 1 ) {
            $base_url = remove_query_arg( 'pg' );
            echo '<div class="ai-jobboard-pagination">';
            for ( $p = 1; $p <= $total_pages; $p++ ) {
                $url = add_query_arg( 'pg', $p, $base_url );
                $active = ( $p === $paged ) ? ' ai-page-active' : '';
                echo '<a class="ai-page-link' . esc_attr( $active ) . '" href="' . esc_url( $url ) . '">' . esc_html( (string) $p ) . '</a>';
            }
            echo '</div>';
        }

        wp_reset_postdata();
    } else {
        if ( ! $has_featured ) {
            echo '<div class="ai-jobboard-empty">' . esc_html( $is_ro ? 'Nu am găsit joburi care să corespundă filtrelor.' : 'No jobs match your filters.' ) . '</div>';
        }
    }

    echo '</div>'; // wrap

    return ob_get_clean();
}


public static function landing_shortcode( $atts = [] ) {
    $is_ro = ( function_exists( 'aisuite_is_ro_locale' ) && aisuite_is_ro_locale() );
    $jobs_url = get_post_type_archive_link( 'rmax_job' );
    if ( ! $jobs_url ) {
        $jobs_url = home_url( '/' );
    }

    // Prefer stable slugs created by the installer (more reliable than searching by shortcode).
    $portal_url     = '';
    $candidates_url = '';
    $companies_url  = '';

    $p_portal = get_page_by_path( 'portal' );
    if ( $p_portal && isset( $p_portal->ID ) ) {
        $portal_url = get_permalink( $p_portal->ID );
    }
    if ( ! $portal_url ) {
        $p_login = get_page_by_path( 'portal-login' );
        if ( $p_login && isset( $p_login->ID ) ) {
            $portal_url = get_permalink( $p_login->ID );
        }
    }

    $p_c = get_page_by_path( 'inregistrare-candidat' );
    if ( $p_c && isset( $p_c->ID ) ) {
        $candidates_url = get_permalink( $p_c->ID );
    }
    $p_co = get_page_by_path( 'inregistrare-companie' );
    if ( $p_co && isset( $p_co->ID ) ) {
        $companies_url = get_permalink( $p_co->ID );
    }

    // Fallback: try to locate pages by shortcode content.
    if ( ! $portal_url || ! $candidates_url || ! $companies_url ) {
        $find = function( $needle ) {
            $q = new WP_Query( array(
                'post_type'      => array( 'page' ),
                'post_status'    => array( 'publish' ),
                'posts_per_page' => 1,
                's'              => $needle,
            ) );
            if ( $q->have_posts() ) {
                $q->the_post();
                $pid = get_the_ID();
                wp_reset_postdata();
                return get_permalink( $pid );
            }
            wp_reset_postdata();
            return '';
        };
        if ( ! $portal_url ) {
            $portal_url = $find( 'ai_suite_portal_hub' );
        }
        if ( ! $candidates_url ) {
            $candidates_url = $find( 'ai_suite_candidate_register' );
        }
        if ( ! $companies_url ) {
            $companies_url = $find( 'ai_suite_company_register' );
        }
    }

    $portal_url = $portal_url ? $portal_url : wp_login_url();
    $title = $is_ro ? 'Recrutează mai rapid și mai inteligent cu AI' : 'Hire faster and smarter with AI';
    $subtitle = $is_ro
        ? 'Platformă completă pentru recrutori: job board modern, aplicații centralizate și un flux de lucru clar.'
        : 'A complete platform for recruiters: modern job board, centralized applications, and a clear hiring workflow.';
    $cta_primary = $is_ro ? 'Vezi joburi' : 'View jobs';
    $cta_secondary = $is_ro ? 'Intră în portal' : 'Go to portal';

    ob_start();
    echo '<div class="ai-suite-landing ai-suite-public">';
    echo '  <section class="ai-suite-hero">';
    echo '    <div class="ai-container">';
    echo '      <div class="ai-suite-hero-grid">';
    echo '        <div class="ai-suite-hero-copy">';
    echo '          <div class="ai-suite-badge">' . esc_html( $is_ro ? 'RecruMax • Job Board + ATS' : 'RecruMax • Job Board + ATS' ) . '</div>';
    echo '          <h1 class="ai-suite-h1">' . esc_html( $title ) . '</h1>';
    echo '          <p class="ai-suite-subtitle">' . esc_html( $subtitle ) . '</p>';
    echo '          <div class="ai-suite-hero-actions">';
    echo '            <a class="ai-btn ai-btn-primary ai-btn-wide" href="' . esc_url( $jobs_url ) . '">' . esc_html( $cta_primary ) . '</a>';
    echo '            <a class="ai-btn ai-btn-ghost ai-btn-wide" href="' . esc_url( $portal_url ) . '">' . esc_html( $cta_secondary ) . '</a>';
    echo '          </div>';
    echo '          <div class="ai-suite-hero-kpis">';
    echo '            <div class="ai-kpi"><div class="ai-kpi-num">2 min</div><div class="ai-kpi-label">' . esc_html( $is_ro ? 'Publici un job' : 'Post a job' ) . '</div></div>';
    echo '            <div class="ai-kpi"><div class="ai-kpi-num">1 inbox</div><div class="ai-kpi-label">' . esc_html( $is_ro ? 'Toate aplicațiile' : 'All applications' ) . '</div></div>';
    echo '            <div class="ai-kpi"><div class="ai-kpi-num">AI</div><div class="ai-kpi-label">' . esc_html( $is_ro ? 'Scor & sumar CV' : 'CV summary & scoring' ) . '</div></div>';
    echo '          </div>';
    echo '        </div>';
    echo '        <div class="ai-suite-hero-preview" aria-hidden="true">';
    echo '          <div class="ai-preview-card">';
    echo '            <div class="ai-preview-top">';
    echo '              <div class="ai-dot"></div><div class="ai-dot"></div><div class="ai-dot"></div>';
    echo '              <div class="ai-preview-title">' . esc_html( $is_ro ? 'Joburi deschise' : 'Open jobs' ) . '</div>';
    echo '            </div>';
    echo '            <div class="ai-preview-row"><span class="ai-pill">Auto</span><span class="ai-pill">Olanda</span><span class="ai-pill ai-pill-accent">Nou</span></div>';
    echo '            <div class="ai-preview-item"><div class="ai-preview-name">' . esc_html( $is_ro ? 'Vopsitor auto' : 'Car painter' ) . '</div><div class="ai-preview-meta">€ / săpt</div></div>';
    echo '            <div class="ai-preview-item"><div class="ai-preview-name">' . esc_html( $is_ro ? 'Tinichigiu' : 'Panel beater' ) . '</div><div class="ai-preview-meta">Full-time</div></div>';
    echo '            <div class="ai-preview-item"><div class="ai-preview-name">' . esc_html( $is_ro ? 'Polisher' : 'Polisher' ) . '</div><div class="ai-preview-meta">Start rapid</div></div>';
    echo '            <div class="ai-preview-cta">' . esc_html( $is_ro ? 'Aplicare rapidă' : 'Quick apply' ) . '</div>';
    echo '          </div>';
    echo '        </div>';
    echo '      </div>';
    echo '    </div>';
    echo '  </section>';

    echo '  <section class="ai-suite-section">';
    echo '    <div class="ai-container">';
    echo '      <div class="ai-suite-section-head">';
    echo '        <h2 class="ai-suite-h2">' . esc_html( $is_ro ? 'Tot ce ai nevoie, într-un singur loc' : 'Everything you need, in one place' ) . '</h2>';
    echo '        <p class="ai-suite-lead">' . esc_html( $is_ro ? 'Postezi joburi, primești aplicații și gestionezi candidații fără haos.' : 'Post jobs, receive applications, and manage candidates without chaos.' ) . '</p>';
    echo '      </div>';
    echo '      <div class="ai-suite-cards">';
    echo '        <div class="ai-card"><div class="ai-card-title">' . esc_html( $is_ro ? 'Job Board modern' : 'Modern job board' ) . '</div><div class="ai-card-text">' . esc_html( $is_ro ? 'Listă, filtre, pagini job optimizate și SEO.' : 'Listing, filters, optimized job pages and SEO.' ) . '</div></div>';
    echo '        <div class="ai-card"><div class="ai-card-title">' . esc_html( $is_ro ? 'Aplicare cu CV' : 'CV application' ) . '</div><div class="ai-card-text">' . esc_html( $is_ro ? 'Upload securizat, validări și notificări email.' : 'Secure upload, validations and email notifications.' ) . '</div></div>';
    echo '        <div class="ai-card"><div class="ai-card-title">' . esc_html( $is_ro ? 'Pipeline & statusuri' : 'Pipeline & statuses' ) . '</div><div class="ai-card-text">' . esc_html( $is_ro ? 'De la aplicare la interviu, totul în ordine.' : 'From application to interview, everything in order.' ) . '</div></div>';
    echo '      </div>';
    echo '    </div>';
    echo '  </section>';

    echo '  <section class="ai-suite-section ai-suite-section-compact">';
    echo '    <div class="ai-container">';
    echo '      <div class="ai-suite-cta">';
    echo '        <div>';
    echo '          <h3 class="ai-suite-h3">' . esc_html( $is_ro ? 'Ești candidat sau companie?' : 'Candidate or company?' ) . '</h3>';
    echo '          <p class="ai-suite-lead">' . esc_html( $is_ro ? 'Intră în portal sau creează cont în câteva secunde.' : 'Access the portal or create an account in seconds.' ) . '</p>';
    echo '        </div>';
    echo '        <div class="ai-suite-cta-actions">';
    if ( $candidates_url ) {
        echo '          <a class="ai-btn ai-btn-ghost ai-btn-wide" href="' . esc_url( $candidates_url ) . '">' . esc_html( $is_ro ? 'Cont candidat' : 'Candidate account' ) . '</a>';
    }
    if ( $companies_url ) {
        echo '          <a class="ai-btn ai-btn-primary ai-btn-wide" href="' . esc_url( $companies_url ) . '">' . esc_html( $is_ro ? 'Cont companie' : 'Company account' ) . '</a>';
    }
    echo '        </div>';
    echo '      </div>';
    echo '    </div>';
    echo '  </section>';

    echo '</div>';
    return ob_get_clean();
}

public static function apply_form_shortcode( $atts = [] ) {
            // Determine job ID from attribute or current post.
            $atts   = shortcode_atts( array( 'job_id' => 0 ), $atts, 'ai_suite_apply_form' );
            $job_id = intval( $atts['job_id'] );
            if ( ! $job_id && get_post_type() === 'rmax_job' ) {
                $job_id = get_the_ID();
            }
            if ( ! $job_id ) {
                return '';
            }
            $action = esc_url( admin_url( 'admin-post.php' ) );
            ob_start();
            // Mesaj rezultat (redirect fără re-POST).
            if ( isset( $_GET['ai_suite_apply'], $_GET['ai_suite_msg'] ) ) {
                $flag = sanitize_key( wp_unslash( $_GET['ai_suite_apply'] ) );
                $msg  = rawurldecode( (string) wp_unslash( $_GET['ai_suite_msg'] ) );
                $cls  = ( 'ok' === $flag ) ? 'ai-apply-success' : 'ai-apply-error';
                echo '<div class="' . esc_attr( $cls ) . '">' . esc_html( $msg ) . '</div>';
            }
            echo '<form class="ai-apply-form" method="post" action="' . $action . '" enctype="multipart/form-data">';
            echo '<input type="hidden" name="action" value="ai_suite_submit_application" />';
            echo '<input type="hidden" name="job_id" value="' . esc_attr( $job_id ) . '" />';
            wp_nonce_field( 'ai_suite_submit_application', 'ai_suite_apply_nonce' );
            // Honeypot anti-spam.
            echo '<input type="text" name="website" value="" style="display:none !important" tabindex="-1" autocomplete="off" />';
            // Name field.
            echo '<p><label>' . esc_html__( 'Nume complet', 'ai-suite' ) . '</label><br />';
            echo '<input type="text" name="candidate_name" required /></p>';
            // Email field.
            echo '<p><label>' . esc_html__( 'Email', 'ai-suite' ) . '</label><br />';
            echo '<input type="email" name="candidate_email" required /></p>';
            // Phone field.
            echo '<p><label>' . esc_html__( 'Telefon', 'ai-suite' ) . '</label><br />';
            echo '<input type="text" name="candidate_phone" required /></p>';
            // CV upload.
            $limit_mb = function_exists( 'ai_suite_get_upload_limit_mb' ) ? (int) ai_suite_get_upload_limit_mb() : 8;
            echo '<p><label>' . esc_html__( 'Atașează CV (PDF/DOC/DOCX)', 'ai-suite' ) . '</label><br />';
            echo '<input type="file" name="candidate_cv" accept=".pdf,.doc,.docx" required />';
            echo '<br /><small>' . esc_html( sprintf( __( 'Limită: %d MB', 'ai-suite' ), $limit_mb ) ) . '</small></p>';
            // Message field.
            echo '<p><label>' . esc_html__( 'Mesaj', 'ai-suite' ) . '</label><br />';
            echo '<textarea name="candidate_message" rows="5"></textarea></p>';
            // Submit button.
            echo '<p><button type="submit">' . esc_html__( 'Trimite aplicația', 'ai-suite' ) . '</button></p>';
            echo '</form>';
            return ob_get_clean();
        }

        /**
         * Saved jobs (candidate) – AJAX endpoints.
         */
        public static function ajax_toggle_save_job() {
            check_ajax_referer( 'ai_suite_jobboard', 'nonce' );
            if ( ! is_user_logged_in() ) {
                wp_send_json_error( array( 'message' => __( 'Trebuie să fii autentificat.', 'ai-suite' ) ), 401 );
            }
            $user_id = function_exists( 'ai_suite_portal_effective_user_id' ) ? ai_suite_portal_effective_user_id() : get_current_user_id();
            if ( function_exists( 'ai_suite_portal_user_can' ) && ! ai_suite_portal_user_can( 'candidate', $user_id ) ) {
                if ( function_exists( 'ai_suite_portal_log_auth_failure' ) ) {
                    ai_suite_portal_log_auth_failure( 'capability', array(
                        'action' => isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '',
                    ) );
                }
                wp_send_json_error( array( 'message' => __( 'Neautorizat.', 'ai-suite' ) ), 403 );
            }

            $job_id = isset( $_POST['job_id'] ) ? absint( wp_unslash( $_POST['job_id'] ) ) : 0;
            if ( ! $job_id || get_post_type( $job_id ) !== 'rmax_job' ) {
                wp_send_json_error( array( 'message' => __( 'Job invalid.', 'ai-suite' ) ), 400 );
            }

            $saved = (array) get_user_meta( $user_id, '_ai_suite_saved_jobs', true );
            $saved = array_values( array_filter( array_map( 'absint', $saved ) ) );

            $idx = array_search( $job_id, $saved, true );
            if ( $idx !== false ) {
                unset( $saved[ $idx ] );
                $saved = array_values( $saved );
                update_user_meta( $user_id, '_ai_suite_saved_jobs', $saved );
                if ( function_exists( 'aisuite_log' ) ) {
                    aisuite_log( 'info', 'Saved job removed', array( 'user_id' => $user_id, 'job_id' => $job_id ) );
                }
                wp_send_json_success( array( 'saved' => false, 'count' => count( $saved ) ) );
            }

            $saved[] = $job_id;
            update_user_meta( $user_id, '_ai_suite_saved_jobs', $saved );
            if ( function_exists( 'aisuite_log' ) ) {
                aisuite_log( 'info', 'Saved job added', array( 'user_id' => $user_id, 'job_id' => $job_id ) );
            }
            wp_send_json_success( array( 'saved' => true, 'count' => count( $saved ) ) );
        }

        public static function ajax_get_saved_jobs() {
            check_ajax_referer( 'ai_suite_jobboard', 'nonce' );
            if ( ! is_user_logged_in() ) {
                wp_send_json_error( array( 'message' => __( 'Trebuie să fii autentificat.', 'ai-suite' ) ), 401 );
            }
            $user_id = function_exists( 'ai_suite_portal_effective_user_id' ) ? ai_suite_portal_effective_user_id() : get_current_user_id();
            if ( function_exists( 'ai_suite_portal_user_can' ) && ! ai_suite_portal_user_can( 'candidate', $user_id ) ) {
                if ( function_exists( 'ai_suite_portal_log_auth_failure' ) ) {
                    ai_suite_portal_log_auth_failure( 'capability', array(
                        'action' => isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '',
                    ) );
                }
                wp_send_json_error( array( 'message' => __( 'Neautorizat.', 'ai-suite' ) ), 403 );
            }

            $saved = (array) get_user_meta( $user_id, '_ai_suite_saved_jobs', true );
            $saved = array_values( array_filter( array_map( 'absint', $saved ) ) );

            if ( empty( $saved ) ) {
                wp_send_json_success( array( 'jobs' => array() ) );
            }

            $posts = get_posts( array(
                'post_type'      => 'rmax_job',
                'post_status'    => 'publish',
                'posts_per_page' => 50,
                'post__in'       => $saved,
                'orderby'        => 'post__in',
            ) );

            $jobs = array();
            foreach ( (array) $posts as $post ) {
                $jobs[] = array(
                    'id'    => absint( $post->ID ),
                    'title' => $post->post_title,
                    'url'   => get_permalink( $post->ID ),
                );
            }

            wp_send_json_success( array( 'jobs' => $jobs ) );
        }

        /**
         * Process the application form submission.
         */
        public static function handle_apply_form() {
            // Folosim stratul PRO dacă e disponibil.
            if ( function_exists( 'ai_suite_handle_application_submit' ) ) {
                $res = ai_suite_handle_application_submit();
                $job_id = isset( $_POST['job_id'] ) ? absint( wp_unslash( $_POST['job_id'] ) ) : 0;
                $redirect = $job_id ? get_permalink( $job_id ) : ( wp_get_referer() ? wp_get_referer() : home_url() );
                $redirect = add_query_arg(
                    array(
                        'ai_suite_apply' => $res['ok'] ? 'ok' : 'fail',
                        'ai_suite_msg'   => rawurlencode( (string) $res['message'] ),
                    ),
                    $redirect
                );
                wp_safe_redirect( $redirect );
                exit;
            }

            // Fallback minimal (ar trebui să nu se întâmple): redirect înapoi.
            wp_safe_redirect( wp_get_referer() ? wp_get_referer() : home_url() );
            exit;
        }
    }
    // Boot the frontend features on init.
    add_action( 'init', [ 'AI_Suite_Frontend', 'boot' ], 15 );
}
