<?php
/**
 * Custom post types for the recruitment module.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'aisuite_register_recruitment_cpts' ) ) {
    /**
     * Register custom post types and taxonomies.
     *
     * Kept as a dedicated function so we can safely call it on activation
     * before flush_rewrite_rules().
     */
    function aisuite_register_recruitment_cpts() {
    // Job CPT.
    $labels = array(
        'name'               => _x( 'Joburi', 'post type general name', 'ai-suite' ),
        'singular_name'      => _x( 'Job', 'post type singular name', 'ai-suite' ),
        'menu_name'          => _x( 'Joburi', 'admin menu', 'ai-suite' ),
        'name_admin_bar'     => _x( 'Job', 'add new on admin bar', 'ai-suite' ),
        'add_new'            => _x( 'Adaugă', 'job', 'ai-suite' ),
        'add_new_item'       => __( 'Adaugă job nou', 'ai-suite' ),
        'new_item'           => __( 'Job nou', 'ai-suite' ),
        'edit_item'          => __( 'Editează job', 'ai-suite' ),
        'view_item'          => __( 'Vezi job', 'ai-suite' ),
        'all_items'          => __( 'Toate joburile', 'ai-suite' ),
        'search_items'       => __( 'Caută joburi', 'ai-suite' ),
        'parent_item_colon'  => __( 'Job părinte:', 'ai-suite' ),
        'not_found'          => __( 'Nu există joburi.', 'ai-suite' ),
        'not_found_in_trash' => __( 'Nu există joburi în coș.', 'ai-suite' ),
    );
    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => false, // we handle menu ourselves.
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'jobs' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
    );
    register_post_type( 'rmax_job', $args );

    // Candidate CPT.
    $labels_c = array(
        'name'               => _x( 'Candidați', 'post type general name', 'ai-suite' ),
        'singular_name'      => _x( 'Candidat', 'post type singular name', 'ai-suite' ),
        'menu_name'          => _x( 'Candidați', 'admin menu', 'ai-suite' ),
        'name_admin_bar'     => _x( 'Candidat', 'add new on admin bar', 'ai-suite' ),
        'add_new'            => _x( 'Adaugă', 'candidate', 'ai-suite' ),
        'add_new_item'       => __( 'Adaugă candidat nou', 'ai-suite' ),
        'new_item'           => __( 'Candidat nou', 'ai-suite' ),
        'edit_item'          => __( 'Editează candidat', 'ai-suite' ),
        'view_item'          => __( 'Vezi candidat', 'ai-suite' ),
        'all_items'          => __( 'Toți candidații', 'ai-suite' ),
        'search_items'       => __( 'Caută candidați', 'ai-suite' ),
        'parent_item_colon'  => __( 'Candidat părinte:', 'ai-suite' ),
        'not_found'          => __( 'Nu există candidați.', 'ai-suite' ),
        'not_found_in_trash' => __( 'Nu există candidați în coș.', 'ai-suite' ),
    );
    $args_c = array(
        'labels'             => $labels_c,
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => false,
        'capability_type'    => 'post',
        'hierarchical'       => false,
        'supports'           => array( 'title', 'custom-fields' ),
    );
    register_post_type( 'rmax_candidate', $args_c );

    // Application CPT.
    $labels_a = array(
        'name'               => _x( 'Aplicații', 'post type general name', 'ai-suite' ),
        'singular_name'      => _x( 'Aplicație', 'post type singular name', 'ai-suite' ),
        'menu_name'          => _x( 'Aplicații', 'admin menu', 'ai-suite' ),
        'name_admin_bar'     => _x( 'Aplicație', 'add new on admin bar', 'ai-suite' ),
        'add_new'            => _x( 'Adaugă', 'application', 'ai-suite' ),
        'add_new_item'       => __( 'Adaugă aplicație nouă', 'ai-suite' ),
        'new_item'           => __( 'Aplicație nouă', 'ai-suite' ),
        'edit_item'          => __( 'Editează aplicație', 'ai-suite' ),
        'view_item'          => __( 'Vezi aplicație', 'ai-suite' ),
        'all_items'          => __( 'Toate aplicațiile', 'ai-suite' ),
        'search_items'       => __( 'Caută aplicații', 'ai-suite' ),
        'parent_item_colon'  => __( 'Aplicație părinte:', 'ai-suite' ),
        'not_found'          => __( 'Nu există aplicații.', 'ai-suite' ),
        'not_found_in_trash' => __( 'Nu există aplicații în coș.', 'ai-suite' ),
    );
    $args_a = array(
        'labels'             => $labels_a,
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => false,
        'capability_type'    => 'post',
        'hierarchical'       => false,
        'supports'           => array( 'title', 'custom-fields' ),
    );
    register_post_type( 'rmax_application', $args_a );

    // Taxonomies for job: department and location.
    register_taxonomy(
        'job_department',
        'rmax_job',
        array(
            'hierarchical' => true,
            'labels'       => array(
                'name'              => __( 'Departamente', 'ai-suite' ),
                'singular_name'     => __( 'Departament', 'ai-suite' ),
                'search_items'      => __( 'Caută departamente', 'ai-suite' ),
                'all_items'         => __( 'Toate departamentele', 'ai-suite' ),
                'parent_item'       => __( 'Departament părinte', 'ai-suite' ),
                'parent_item_colon' => __( 'Departament părinte:', 'ai-suite' ),
                'edit_item'         => __( 'Editează departament', 'ai-suite' ),
                'update_item'       => __( 'Actualizează departament', 'ai-suite' ),
                'add_new_item'      => __( 'Adaugă departament', 'ai-suite' ),
                'new_item_name'     => __( 'Nume departament', 'ai-suite' ),
                'menu_name'         => __( 'Departamente', 'ai-suite' ),
            ),
            'show_ui'      => true,
            'show_admin_column' => true,
            'query_var'    => true,
            'rewrite'      => array( 'slug' => 'department' ),
        )
    );
    register_taxonomy(
        'job_location',
        'rmax_job',
        array(
            'hierarchical' => true,
            'labels'       => array(
                'name'              => __( 'Locații', 'ai-suite' ),
                'singular_name'     => __( 'Locație', 'ai-suite' ),
                'search_items'      => __( 'Caută locații', 'ai-suite' ),
                'all_items'         => __( 'Toate locațiile', 'ai-suite' ),
                'parent_item'       => __( 'Locație părinte', 'ai-suite' ),
                'parent_item_colon' => __( 'Locație părinte:', 'ai-suite' ),
                'edit_item'         => __( 'Editează locație', 'ai-suite' ),
                'update_item'       => __( 'Actualizează locație', 'ai-suite' ),
                'add_new_item'      => __( 'Adaugă locație', 'ai-suite' ),
                'new_item_name'     => __( 'Nume locație', 'ai-suite' ),
                'menu_name'         => __( 'Locații', 'ai-suite' ),
            ),
            'show_ui'      => true,
            'show_admin_column' => true,
            'query_var'    => true,
            'rewrite'      => array( 'slug' => 'location' ),
        )
    );
    }
}

add_action( 'init', 'aisuite_register_recruitment_cpts' );