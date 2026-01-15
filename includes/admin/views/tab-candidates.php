<?php
/**
 * Candidates management tab (PRO list).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$search = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
$paged  = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;

echo '<div class="ai-card">';
echo '<h2>' . esc_html__( 'Candidați', 'ai-suite' ) . '</h2>';

// Search + export.
echo '<form method="get" style="margin-top:10px;">';
echo '<input type="hidden" name="page" value="ai-suite" />';
echo '<input type="hidden" name="tab" value="candidates" />';
echo '<input type="hidden" name="paged" value="1" />';
echo '<p class="search-box">';
echo '<input type="search" name="q" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Caută nume / email / telefon...', 'ai-suite' ) . '" />';
echo '<button class="button" style="margin-left:6px;">' . esc_html__( 'Caută', 'ai-suite' ) . '</button>';

$export_cands_url = wp_nonce_url(
    add_query_arg(
        array(
            'action' => 'ai_suite_export_candidates_csv',
            'q'      => $search,
        ),
        admin_url( 'admin-post.php' )
    ),
    'ai_suite_export_candidates_csv'
);

echo '<a class="button button-secondary" style="margin-left:6px;" href="' . esc_url( $export_cands_url ) . '">' . esc_html__( 'Export CSV', 'ai-suite' ) . '</a>';
echo '</p>';
echo '</form>';

// Build candidate IDs for search across title + meta.
$post__in = array();
if ( $search !== '' ) {
    $ids_title = get_posts( array(
        'post_type'      => 'rmax_candidate',
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'posts_per_page' => 500,
        's'              => $search,
    ) );

    $ids_meta = get_posts( array(
        'post_type'      => 'rmax_candidate',
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'posts_per_page' => 500,
        'meta_query'     => array(
            'relation' => 'OR',
            array(
                'key'     => '_candidate_email',
                'value'   => $search,
                'compare' => 'LIKE',
            ),
            array(
                'key'     => '_candidate_phone',
                'value'   => $search,
                'compare' => 'LIKE',
            ),
        ),
    ) );

    $post__in = array_values( array_unique( array_merge( array_map( 'absint', (array) $ids_title ), array_map( 'absint', (array) $ids_meta ) ) ) );
    if ( empty( $post__in ) ) {
        // Force empty results.
        $post__in = array( 0 );
    }
}

$args = array(
    'post_type'      => 'rmax_candidate',
    'posts_per_page' => 20,
    'paged'          => $paged,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
);

if ( $search !== '' ) {
    $args['post__in'] = $post__in;
    $args['orderby']  = 'post__in';
}


// AISUITE_SCOPE_RECRUITER_CANDS: restrict candidates to those who applied to assigned companies.
if ( ! current_user_can( 'manage_ai_suite' ) && function_exists( 'aisuite_get_assigned_company_ids' ) && ( function_exists( 'aisuite_current_user_is_recruiter' ) && ( aisuite_current_user_is_recruiter() || aisuite_current_user_is_manager() ) ) ) {
    $assigned_company_ids = aisuite_get_assigned_company_ids( get_current_user_id() );
    if ( empty( $assigned_company_ids ) ) {
        $args['post__in'] = array( 0 );
    } else {
        $app_ids = get_posts( array(
            'post_type'      => 'rmax_application',
            'post_status'    => 'publish',
            'posts_per_page' => 5000,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => '_application_company_id',
                    'value'   => $assigned_company_ids,
                    'compare' => 'IN',
                ),
            ),
        ) );

        if ( empty( $app_ids ) ) {
            $args['post__in'] = array( 0 );
        } else {
            global $wpdb;
            $app_ids = array_values( array_unique( array_map( 'intval', $app_ids ) ) );
            $placeholders = implode( ',', array_fill( 0, count( $app_ids ), '%d' ) );
            $sql = "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_application_candidate_id' AND post_id IN ($placeholders)";
            $cand_ids = $wpdb->get_col( $wpdb->prepare( $sql, $app_ids ) );
            $cand_ids = array_values( array_filter( array_map( 'intval', (array) $cand_ids ) ) );

            if ( empty( $cand_ids ) ) {
                $args['post__in'] = array( 0 );
            } else {
                if ( isset( $args['post__in'] ) && is_array( $args['post__in'] ) && ! empty( $args['post__in'] ) ) {
                    $existing = array_values( array_filter( array_map( 'intval', $args['post__in'] ) ) );
                    if ( ! empty( $existing ) && ! in_array( 0, $existing, true ) ) {
                        $cand_ids = array_values( array_intersect( $cand_ids, $existing ) );
                    }
                }
                $args['post__in'] = ! empty( $cand_ids ) ? $cand_ids : array( 0 );
                $args['orderby']  = 'post__in';
            }
        }
    }
}

$query = new WP_Query( $args );

if ( $query->have_posts() ) {
    global $wpdb;
    $pm = $wpdb->postmeta;
    $p  = $wpdb->posts;

    echo '<table class="wp-list-table widefat striped">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__( 'Nume', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Email', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Telefon', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Aplicații', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Ultima aplicație', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Dată', 'ai-suite' ) . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    while ( $query->have_posts() ) {
        $query->the_post();
        $candidate_id = get_the_ID();
        $email        = (string) get_post_meta( $candidate_id, '_candidate_email', true );
        $phone        = (string) get_post_meta( $candidate_id, '_candidate_phone', true );
        $edit_link    = get_edit_post_link( $candidate_id );

        // Count applications + latest.
        $app_count = 0;
        $last_app_date = '';
        $sql = $wpdb->prepare(
            "SELECT COUNT(DISTINCT posts.ID) AS cnt, MAX(posts.post_date) AS last_date
             FROM $p AS posts
             INNER JOIN $pm AS meta ON posts.ID = meta.post_id
             WHERE posts.post_type = %s
               AND posts.post_status = %s
               AND meta.meta_key = %s
               AND meta.meta_value = %s",
            'rmax_application',
            'publish',
            '_application_candidate_id',
            (string) $candidate_id
        );
        $row = $wpdb->get_row( $sql, ARRAY_A );
        if ( is_array( $row ) ) {
            $app_count = isset( $row['cnt'] ) ? (int) $row['cnt'] : 0;
            if ( ! empty( $row['last_date'] ) ) {
                $last_app_date = mysql2date( 'Y-m-d H:i', $row['last_date'] );
            }
        }

        echo '<tr>';
        echo '<td>' . ( $edit_link ? '<a href="' . esc_url( $edit_link ) . '">' . esc_html( get_the_title() ) . '</a>' : esc_html( get_the_title() ) ) . '</td>';
        echo '<td>' . esc_html( $email ) . '</td>';
        echo '<td>' . esc_html( $phone ) . '</td>';
        echo '<td><span class="ai-badge">' . esc_html( (string) $app_count ) . '</span></td>';
        echo '<td>' . ( $last_app_date ? esc_html( $last_app_date ) : '—' ) . '</td>';
        echo '<td>' . esc_html( get_the_date( 'Y-m-d' ) ) . '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';

    // Pagination.
    $max_pages = (int) $query->max_num_pages;
    if ( $max_pages > 1 ) {
        $base_url = remove_query_arg( 'paged' );
        $page_links = paginate_links( array(
            'base'      => add_query_arg( 'paged', '%#%', $base_url ),
            'format'    => '',
            'current'   => $paged,
            'total'     => $max_pages,
            'prev_text' => '«',
            'next_text' => '»',
            'type'      => 'array',
        ) );
        if ( is_array( $page_links ) ) {
            echo '<div class="tablenav"><div class="tablenav-pages" style="margin:10px 0 0;">';
            echo '<span class="pagination-links">' . implode( ' ', array_map( 'wp_kses_post', $page_links ) ) . '</span>';
            echo '</div></div>';
        }
    }

    wp_reset_postdata();
} else {
    echo '<p>' . esc_html__( 'Nu au fost găsiți candidați.', 'ai-suite' ) . '</p>';
}

echo '</div>';
