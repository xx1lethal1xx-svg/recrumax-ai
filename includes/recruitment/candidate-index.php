<?php
/**
 * AI Suite – Candidate Index (Enterprise)
 *
 * Scop:
 * - Creează un index rapid pentru căutare candidați (nume/email/telefon/skills/locație)
 * - Menține indexul sincron la save/delete
 * - Oferă funcții de căutare SQL + reindexare (admin)
 *
 * Notă: indexul este opțional; dacă tabela nu există, pluginul revine la WP_Query.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'ai_suite_candidate_index_table' ) ) {
    function ai_suite_candidate_index_table() {
        global $wpdb;
        return $wpdb->prefix . 'ai_suite_candidate_index';
    }
}

if ( ! function_exists( 'ai_suite_candidate_index_exists' ) ) {
    function ai_suite_candidate_index_exists() {
        global $wpdb;
        static $cached = null;
        if ( null !== $cached ) return (bool) $cached;
        $table = ai_suite_candidate_index_table();
        $cached = ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table ) ) === $table );
        return (bool) $cached;
    }
}

if ( ! function_exists( 'ai_suite_candidate_index_install' ) ) {
    function ai_suite_candidate_index_install() {
        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        global $wpdb;
        $table = ai_suite_candidate_index_table();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            candidate_id BIGINT(20) UNSIGNED NOT NULL,
            name TEXT NULL,
            email VARCHAR(190) NULL,
            phone VARCHAR(64) NULL,
            skills TEXT NULL,
            location VARCHAR(190) NULL,
            has_cv TINYINT(1) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (candidate_id),
            KEY email (email),
            KEY phone (phone),
            KEY location (location),
            KEY has_cv (has_cv)
        ) {$charset};";

        dbDelta( $sql );

        return true;
    }
}

if ( ! function_exists( 'ai_suite_candidate_index_normalize' ) ) {
    function ai_suite_candidate_index_normalize( $s ) {
        $s = (string) $s;
        $s = wp_strip_all_tags( $s );
        $s = preg_replace( '/\s+/', ' ', $s );
        $s = trim( $s );
        return $s;
    }
}

if ( ! function_exists( 'ai_suite_candidate_index_upsert' ) ) {
    function ai_suite_candidate_index_upsert( $candidate_id ) {
        $candidate_id = absint( $candidate_id );
        if ( ! $candidate_id ) return false;
        if ( get_post_type( $candidate_id ) !== 'rmax_candidate' ) return false;
        if ( ! ai_suite_candidate_index_exists() ) return false;

        global $wpdb;
        $table = ai_suite_candidate_index_table();

        $name = ai_suite_candidate_index_normalize( get_the_title( $candidate_id ) );
        $email = ai_suite_candidate_index_normalize( get_post_meta( $candidate_id, '_candidate_email', true ) );
        $phone = ai_suite_candidate_index_normalize( get_post_meta( $candidate_id, '_candidate_phone', true ) );
        $skills = ai_suite_candidate_index_normalize( get_post_meta( $candidate_id, '_candidate_skills', true ) );
        $location = ai_suite_candidate_index_normalize( get_post_meta( $candidate_id, '_candidate_location', true ) );
        $cv_id = absint( get_post_meta( $candidate_id, '_candidate_cv', true ) );
        $has_cv = $cv_id ? 1 : 0;

        $data = array(
            'candidate_id' => $candidate_id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'skills' => $skills,
            'location' => $location,
            'has_cv' => $has_cv,
            'updated_at' => current_time( 'mysql' ),
        );

        $formats = array( '%d','%s','%s','%s','%s','%s','%d','%s' );

        // wpdb->replace does UPSERT by primary key.
        $ok = ( false !== $wpdb->replace( $table, $data, $formats ) );
        return (bool) $ok;
    }
}

if ( ! function_exists( 'ai_suite_candidate_index_delete' ) ) {
    function ai_suite_candidate_index_delete( $candidate_id ) {
        $candidate_id = absint( $candidate_id );
        if ( ! $candidate_id ) return false;
        if ( ! ai_suite_candidate_index_exists() ) return false;

        global $wpdb;
        $table = ai_suite_candidate_index_table();
        $wpdb->delete( $table, array( 'candidate_id' => $candidate_id ), array( '%d' ) );
        return true;
    }
}

if ( ! function_exists( 'ai_suite_candidate_index_search' ) ) {
    /**
     * Returnează o listă de candidate IDs.
     */
    function ai_suite_candidate_index_search( $q = '', $loc = '', $has_cv = 0, $limit = 40 ) {
        $limit = absint( $limit );
        if ( $limit < 1 ) $limit = 20;
        if ( $limit > 200 ) $limit = 200;

        if ( ! ai_suite_candidate_index_exists() ) {
            return array();
        }

        global $wpdb;
        $table = ai_suite_candidate_index_table();

        $where = array( '1=1' );
        $args  = array();

        $q = ai_suite_candidate_index_normalize( $q );
        if ( $q !== '' ) {
            // Search in multiple fields.
            $like = '%' . $wpdb->esc_like( $q ) . '%';
            $where[] = "(name LIKE %s OR email LIKE %s OR phone LIKE %s OR skills LIKE %s OR location LIKE %s)";
            $args[] = $like; $args[] = $like; $args[] = $like; $args[] = $like; $args[] = $like;
        }

        $loc = ai_suite_candidate_index_normalize( $loc );
        if ( $loc !== '' ) {
            $like = '%' . $wpdb->esc_like( $loc ) . '%';
            $where[] = "location LIKE %s";
            $args[] = $like;
        }

        if ( $has_cv ) {
            $where[] = "has_cv = 1";
        }

        $sql = "SELECT candidate_id FROM {$table} WHERE " . implode( ' AND ', $where ) . " ORDER BY updated_at DESC LIMIT {$limit}";
        if ( ! empty( $args ) ) {
            $sql = $wpdb->prepare( $sql, $args );
        }

        $ids = $wpdb->get_col( $sql );
        $ids = array_map( 'absint', is_array( $ids ) ? $ids : array() );
        return array_values( array_filter( $ids ) );
    }
}

if ( ! function_exists( 'ai_suite_candidate_index_reindex_all' ) ) {
    /**
     * Reindexează candidații în batch (admin).
     */
    function ai_suite_candidate_index_reindex_all( $limit = 5000 ) {
        $limit = absint( $limit );
        if ( $limit < 1 ) $limit = 1000;
        if ( $limit > 20000 ) $limit = 20000;

        if ( ! ai_suite_candidate_index_exists() ) {
            ai_suite_candidate_index_install();
        }

        $ids = get_posts( array(
            'post_type'      => 'rmax_candidate',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => $limit,
            'orderby'        => 'ID',
            'order'          => 'ASC',
        ) );

        $count = 0;
        foreach ( (array) $ids as $cid ) {
            if ( ai_suite_candidate_index_upsert( (int) $cid ) ) {
                $count++;
            }
        }
        return $count;
    }
}

// --- Hooks: keep index fresh
add_action( 'init', function() {
    // Lazy-create on live upgrades (no activation) – only if admin triggers later.
    // We do not auto-create here to avoid surprises on minimal setups.
}, 30 );

add_action( 'save_post_rmax_candidate', function( $post_id, $post, $update ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return;
    if ( isset( $post->post_status ) && $post->post_status !== 'publish' ) return;
    if ( function_exists( 'ai_suite_candidate_index_upsert' ) ) {
        ai_suite_candidate_index_upsert( $post_id );
    }
}, 10, 3 );

add_action( 'before_delete_post', function( $post_id ) {
    if ( get_post_type( $post_id ) !== 'rmax_candidate' ) return;
    if ( function_exists( 'ai_suite_candidate_index_delete' ) ) {
        ai_suite_candidate_index_delete( $post_id );
    }
}, 10, 1 );
