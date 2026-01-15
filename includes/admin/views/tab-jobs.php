<?php
/**
 * Jobs management tab view.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Setup counts of job statuses.
$counts = array(
    'all'    => 0,
    'open'   => 0,
    'draft'  => 0,
    'closed' => 0,
);

// Retrieve counts.
global $wpdb;
$table_posts = $wpdb->posts;
$meta_table  = $wpdb->postmeta;
$sql         = $wpdb->prepare(
    "SELECT meta.meta_value AS status, COUNT(*) AS count
     FROM $table_posts AS posts
     INNER JOIN $meta_table AS meta ON posts.ID = meta.post_id
     WHERE posts.post_type = %s
       AND meta.meta_key = %s
     GROUP BY meta.meta_value",
    'rmax_job',
    '_job_status'
);
$results = $wpdb->get_results( $sql, ARRAY_A );
$total   = 0;
foreach ( $results as $row ) {
    $status = $row['status'];
    $count  = (int) $row['count'];
    $counts[ $status ] = $count;
    $total += $count;
}
$counts['all'] = $total;

// Selected status filter.
$status_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';

// Department/location filters (taxonomies).
$department_filter = isset( $_GET['department'] ) ? absint( wp_unslash( $_GET['department'] ) ) : 0;
$location_filter   = isset( $_GET['location'] ) ? absint( wp_unslash( $_GET['location'] ) ) : 0;

// Pagination.
$paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;

// Search keyword.
$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

echo '<div class="ai-card">';
echo '<h2>' . esc_html__( 'Joburi', 'ai-suite' ) . '</h2>';

// Status filters.
echo '<ul class="subsubsub">';
$statuses = array(
    'all'    => __( 'Toate', 'ai-suite' ),
    'open'   => __( 'Deschise', 'ai-suite' ),
    'draft'  => __( 'Ciornă', 'ai-suite' ),
    'closed' => __( 'Închise', 'ai-suite' ),
);
$i = 0;
foreach ( $statuses as $key => $label ) {
    $class = ( $status_filter === $key ) ? 'class="current"' : '';
    $url   = add_query_arg( array(
        'page'       => 'ai-suite',
        'tab'        => 'jobs',
        'status'     => $key,
        'department' => $department_filter,
        'location'   => $location_filter,
        's'          => $search,
        'paged'      => 1,
    ), admin_url( 'admin.php' ) );
    $count = isset( $counts[ $key ] ) ? $counts[ $key ] : 0;
    echo '<li>';
    echo '<a ' . $class . ' href="' . esc_url( $url ) . '">' . esc_html( $label ) . ' <span class="count">(' . intval( $count ) . ')</span></a>';
    echo ( ++$i < count( $statuses ) ? ' | ' : '' );
    echo '</li>';
}
echo '</ul>';

// Search form.
echo '<form method="get" style="margin-top:10px;">';
echo '<input type="hidden" name="page" value="ai-suite" />';
echo '<input type="hidden" name="tab" value="jobs" />';
echo '<input type="hidden" name="status" value="' . esc_attr( $status_filter ) . '" />';
echo '<input type="hidden" name="paged" value="1" />';
echo '<p class="search-box">';
echo '<input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Caută după titlu...', 'ai-suite' ) . '" />';

// Taxonomy filters.
$departments = get_terms( array( 'taxonomy' => 'job_department', 'hide_empty' => false ) );
$locations   = get_terms( array( 'taxonomy' => 'job_location', 'hide_empty' => false ) );

echo '<select name="department" style="margin-left:6px;max-width:220px;">';
echo '<option value="0">' . esc_html__( 'Toate departamentele', 'ai-suite' ) . '</option>';
if ( ! is_wp_error( $departments ) && ! empty( $departments ) ) {
    foreach ( $departments as $t ) {
        echo '<option value="' . esc_attr( $t->term_id ) . '" ' . selected( $department_filter, (int) $t->term_id, false ) . '>' . esc_html( $t->name ) . '</option>';
    }
}
echo '</select>';

echo '<select name="location" style="margin-left:6px;max-width:220px;">';
echo '<option value="0">' . esc_html__( 'Toate locațiile', 'ai-suite' ) . '</option>';
if ( ! is_wp_error( $locations ) && ! empty( $locations ) ) {
    foreach ( $locations as $t ) {
        echo '<option value="' . esc_attr( $t->term_id ) . '" ' . selected( $location_filter, (int) $t->term_id, false ) . '>' . esc_html( $t->name ) . '</option>';
    }
}
echo '</select>';

echo '<button class="button" style="margin-left:6px;">' . esc_html__( 'Filtrează', 'ai-suite' ) . '</button>';

// Export.
$export_jobs_url = wp_nonce_url(
    add_query_arg(
        array(
            'action'     => 'ai_suite_export_jobs_csv',
            'status'     => $status_filter,
            's'          => $search,
            'department' => $department_filter,
            'location'   => $location_filter,
        ),
        admin_url( 'admin-post.php' )
    ),
    'ai_suite_export_jobs_csv'
);
echo '<a class="button button-secondary" style="margin-left:6px;" href="' . esc_url( $export_jobs_url ) . '">' . esc_html__( 'Export CSV', 'ai-suite' ) . '</a>';
echo '</p>';
echo '</form>';

// Quick add job form.
if ( current_user_can( 'edit_posts' ) ) {
    echo '<h3>' . esc_html__( 'Adaugă job nou', 'ai-suite' ) . '</h3>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="ai_suite_add_job" />';
    wp_nonce_field( 'ai_suite_add_job' );
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th><label for="job_title">' . esc_html__( 'Titlu job', 'ai-suite' ) . '</label></th>';
    echo '<td><input type="text" id="job_title" name="job_title" class="regular-text" required></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th><label for="job_status">' . esc_html__( 'Status', 'ai-suite' ) . '</label></th>';
    echo '<td>';
    echo '<select id="job_status" name="job_status">';
    echo '<option value="open">' . esc_html__( 'Deschis', 'ai-suite' ) . '</option>';
    echo '<option value="draft">' . esc_html__( 'Ciornă', 'ai-suite' ) . '</option>';
    echo '<option value="closed">' . esc_html__( 'Închis', 'ai-suite' ) . '</option>';
    echo '</select>';
    echo '</td>';
    echo '</tr>';
    echo '</table>';
    echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html__( 'Adaugă job', 'ai-suite' ) . '</button></p>';
    echo '</form>';
}

// Query jobs.
$args = array(
    'post_type'      => 'rmax_job',
    'posts_per_page' => 20,
    'paged'          => $paged,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
    'meta_key'       => '_job_status',
);

// Status filter.
if ( 'all' !== $status_filter ) {
    $args['meta_query'] = array(
        array(
            'key'   => '_job_status',
            'value' => $status_filter,
        ),
    );
}

// Search filter.
if ( ! empty( $search ) ) {
    $args['s'] = $search;
}

// Tax filters.
$tax_query = array();
if ( $department_filter ) {
    $tax_query[] = array(
        'taxonomy' => 'job_department',
        'field'    => 'term_id',
        'terms'    => array( $department_filter ),
    );
}
if ( $location_filter ) {
    $tax_query[] = array(
        'taxonomy' => 'job_location',
        'field'    => 'term_id',
        'terms'    => array( $location_filter ),
    );
}
if ( ! empty( $tax_query ) ) {
    $args['tax_query'] = $tax_query;
}


// AISUITE_SCOPE_RECRUITER: restrict data to assigned companies for Recruiter/Manager.
if ( ! current_user_can( 'manage_ai_suite' ) && function_exists( 'aisuite_get_assigned_company_ids' ) && ( function_exists( 'aisuite_current_user_is_recruiter' ) && ( aisuite_current_user_is_recruiter() || aisuite_current_user_is_manager() ) ) ) {
    $assigned_company_ids = aisuite_get_assigned_company_ids( get_current_user_id() );
    if ( empty( $assigned_company_ids ) ) {
        // No access to any company: return empty.
        $args['post__in'] = array( 0 );
    } else {
        if ( ! isset( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
            $args['meta_query'] = array();
        }
        $args['meta_query'][] = array(
            'key'     => '_job_company_id',
            'value'   => $assigned_company_ids,
            'compare' => 'IN',
        );
    }
}

$jobs_query = new WP_Query( $args );

// Bulk update form.
echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
echo '<input type="hidden" name="action" value="ai_suite_jobs_bulk_status" />';
wp_nonce_field( 'ai_suite_jobs_bulk_status' );

if ( $jobs_query->have_posts() ) {
    echo '<table class="wp-list-table widefat striped">';
    echo '<thead><tr>';
    echo '<th class="manage-column check-column"><input type="checkbox" class="job-select-all"></th>';
    echo '<th>' . esc_html__( 'Titlu', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Status', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Promovat', 'ai-suite' ) . '</th>';
    echo '<th>' . esc_html__( 'Dată', 'ai-suite' ) . '</th>';
    echo '</tr></thead>';
    echo '<tbody>';
    while ( $jobs_query->have_posts() ) {
        $jobs_query->the_post();
        $job_id   = get_the_ID();
        $status   = get_post_meta( $job_id, '_job_status', true );
        $status   = $status ? esc_html( $status ) : 'open';
        echo '<tr>';
        echo '<th class="check-column"><input type="checkbox" name="job_ids[]" value="' . esc_attr( $job_id ) . '" class="job-select"></th>';
        echo '<td><a href="' . esc_url( get_edit_post_link( $job_id ) ) . '">' . get_the_title() . '</a></td>';
        echo '<td>' . $status . '</td>';
        $is_feat  = function_exists( 'aisuite_is_job_featured' ) ? aisuite_is_job_featured( $job_id ) : false;
$until_ts = function_exists( 'aisuite_get_job_featured_until' ) ? aisuite_get_job_featured_until( $job_id ) : 0;

$feat_label = $is_feat ? esc_html__( 'Da', 'ai-suite' ) : esc_html__( 'Nu', 'ai-suite' );
$feat_style = $is_feat ? 'font-weight:700;color:#0b5;' : 'color:#666;';
$feat_extra = '';

if ( $is_feat ) {
    if ( $until_ts > 0 ) {
        $feat_extra = ' <span style="color:#999;">(' . esc_html( date_i18n( 'Y-m-d', $until_ts ) ) . ')</span>';
    } else {
        $feat_extra = ' <span style="color:#999;">(' . esc_html__( 'Nelimitat', 'ai-suite' ) . ')</span>';
    }
}

echo '<td><span style="' . esc_attr( $feat_style ) . '">' . esc_html( $feat_label ) . '</span>' . $feat_extra . '</td>';
echo '<td>' . esc_html( get_the_date() ) . '</td>';
echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    // Bulk action.
    echo '<div style="margin-top:10px;">';
    echo '<select name="bulk_status">';
    echo '<option value="open">' . esc_html__( 'Marchează deschis', 'ai-suite' ) . '</option>';
    echo '<option value="draft">' . esc_html__( 'Marchează ciornă', 'ai-suite' ) . '</option>';
    echo '<option value="closed">' . esc_html__( 'Marchează închis', 'ai-suite' ) . '</option>';
    echo '</select> ';
    echo '<button type="submit" class="button">' . esc_html__( 'Aplică', 'ai-suite' ) . '</button>';
    echo '</div>';

    // Pagination.
    $max_pages = (int) $jobs_query->max_num_pages;
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
    echo '<p>' . esc_html__( 'Niciun job găsit.', 'ai-suite' ) . '</p>';
}
echo '</form>';

echo '</div>';