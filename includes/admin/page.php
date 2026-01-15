<?php
/**
 * Admin page renderer for AI Suite.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'ai_suite_render_admin_page' ) ) {
    /**
     * Render the main plugin admin page.
     */
    function ai_suite_render_admin_page() {
        $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
        if ( ! current_user_can( $cap ) ) {
            return;
        }

        $tabs   = ai_suite_tabs();
        $active = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard';
        if ( ! isset( $tabs[ $active ] ) ) {
            $active = 'dashboard';
        }

        // Build grouped navigation (sidebar + mobile dropdown).
        $groups = array();
        foreach ( $tabs as $k => $t ) {
            $g = isset( $t['group'] ) ? (string) $t['group'] : __( 'General', 'ai-suite' );
            if ( ! isset( $groups[ $g ] ) ) { $groups[ $g ] = array(); }
            $groups[ $g ][ $k ] = $t;
        }

        $active_label = isset( $tabs[ $active ]['label'] ) ? (string) $tabs[ $active ]['label'] : 'AI Suite';
        $active_desc  = isset( $tabs[ $active ]['desc'] ) ? (string) $tabs[ $active ]['desc'] : '';

        echo '<div class="wrap ai-suite">';
        echo '<div class="ais-layout">';

        // Sidebar
        echo '<aside class="ais-side" id="ais-side" aria-label="AI Suite Meniu">';
        echo '  <div class="ais-side__brand">';
        echo '    <div class="ais-brand">';
        echo '      <span class="dashicons dashicons-robot"></span>';
        echo '      <span class="ais-brand__text">AI Suite</span>';
        echo '    </div>';
        echo '    <button type="button" class="ais-side__toggle" id="ais-side-toggle" aria-label="' . esc_attr__( 'Ascunde/Afișează meniu', 'ai-suite' ) . '"><span class="dashicons dashicons-menu"></span></button>';
        echo '  </div>';

        echo '  <div class="ais-side__search">';
        echo '    <span class="dashicons dashicons-search"></span>';
        echo '    <input type="text" id="ais-menu-search" placeholder="' . esc_attr__( 'Caută în meniu…', 'ai-suite' ) . '" autocomplete="off" />';
        echo '  </div>';

        echo '  <nav class="ais-menu" aria-label="AI Suite navigare">';
        foreach ( $groups as $gname => $items ) {
            $gid = 'ais-group-' . sanitize_title( $gname );
            echo '    <div class="ais-group" data-group="' . esc_attr( $gid ) . '">';
            echo '      <button type="button" class="ais-group__head" data-toggle="' . esc_attr( $gid ) . '">';
            echo '        <span class="ais-group__title">' . esc_html( $gname ) . '</span>';
            echo '        <span class="dashicons dashicons-arrow-down-alt2 ais-group__chev"></span>';
            echo '      </button>';
            echo '      <div class="ais-group__items" id="' . esc_attr( $gid ) . '">';
            foreach ( $items as $key => $tab ) {
                $url = admin_url( 'admin.php?page=ai-suite&tab=' . $key );
                $is_active = ( $key === $active );
                $cls = $is_active ? 'ais-navitem is-active' : 'ais-navitem';
                $icon = isset( $tab['icon'] ) ? sanitize_html_class( (string) $tab['icon'] ) : 'admin-generic';
                echo '        <a class="' . esc_attr( $cls ) . '" href="' . esc_url( $url ) . '" data-label="' . esc_attr( strtolower( (string) $tab['label'] ) ) . '" title="' . esc_attr( (string) $tab['label'] ) . '">';
                echo '          <span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span>';
                echo '          <span class="ais-navitem__text">' . esc_html( $tab['label'] ) . '</span>';
                echo '        </a>';
            }
            echo '      </div>';
            echo '    </div>';
        }
        echo '  </nav>';

        echo '  <div class="ais-side__footer">';
        echo '    <span class="ais-chip"><strong>' . esc_html__( 'Versiune', 'ai-suite' ) . '</strong> ' . esc_html( defined('AI_SUITE_VER') ? AI_SUITE_VER : '' ) . '</span>';
        echo '  </div>';
        echo '</aside>';

        // Main
        echo '<main class="ais-main">';
        echo '  <header class="ais-top">';
        echo '    <div class="ais-top__left">';
        echo '      <div class="ais-top__title">' . esc_html( $active_label ) . '</div>';
        if ( $active_desc ) {
            echo '      <div class="ais-top__desc">' . esc_html( $active_desc ) . '</div>';
        }
        echo '    </div>';
        echo '    <div class="ais-top__right">';
        // Mobile dropdown (nice on phone)
        echo '      <select class="ais-navselect" id="ais-navselect" aria-label="' . esc_attr__( 'Navigare rapidă', 'ai-suite' ) . '">';
        foreach ( $groups as $gname => $items ) {
            echo '        <optgroup label="' . esc_attr( $gname ) . '">';
            foreach ( $items as $key => $tab ) {
                $url = admin_url( 'admin.php?page=ai-suite&tab=' . $key );
                $sel = selected( $key, $active, false );
                echo '          <option value="' . esc_url( $url ) . '" ' . $sel . '>' . esc_html( $tab['label'] ) . '</option>';
            }
            echo '        </optgroup>';
        }
        echo '      </select>';
        echo '    </div>';
        echo '  </header>';

        echo '  <div class="ais-content">';
        // Include view file.
        $view = AI_SUITE_DIR . 'includes/admin/views/' . $tabs[ $active ]['view'];
        if ( file_exists( $view ) ) {
            include $view;
        }
        echo '  </div>';
        echo '</main>';

        echo '</div>'; // layout
        echo '</div>'; // wrap
    }
}

if ( ! function_exists( 'ai_suite_admin_enqueue' ) ) {
    /**
     * Enqueue admin scripts and styles.
     *
     * @param string $hook Hook name.
     */
    function ai_suite_admin_enqueue( $hook ) {
        if ( $hook !== 'toplevel_page_ai-suite' ) {
            return;
        }

        $active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

        wp_enqueue_style(
            'ai-suite-admin',
            AI_SUITE_URL . 'assets/admin.css',
            array(),
            AI_SUITE_VER
        );
        wp_enqueue_script(
            'ai-suite-admin',
            AI_SUITE_URL . 'assets/admin.js',
            array( 'jquery' ),
            AI_SUITE_VER,
            true
        );

        // Kanban view (Aplicații) – drag & drop.
        if ( 'applications' === $active_tab ) {
            wp_enqueue_script( 'jquery-ui-sortable' );
            wp_enqueue_script(
                'ai-suite-kanban',
                AI_SUITE_URL . 'assets/kanban.js',
                array( 'jquery', 'jquery-ui-sortable' ),
                AI_SUITE_VER,
                true
            );
        }
        wp_localize_script(
            'ai-suite-admin',
            'AI_SUITE',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'ai_suite_nonce' ),
                'i18n'     => array(
                    'se_salveaza' => __( 'Se salvează...', 'ai-suite' ),
                    'ok'          => __( 'Actualizat', 'ai-suite' ),
                    'eroare'      => __( 'Eroare', 'ai-suite' ),
                ),
            )
        );
    }
    add_action( 'admin_enqueue_scripts', 'ai_suite_admin_enqueue' );
}