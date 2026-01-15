<?php
/**
 * AI Suite – Modern Admin (stable).
 *
 * Clean, premium, ordered UI for admin (menu + tabs + settings + tools).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'ai_suite_admin_boot' ) ) {

function ai_suite_admin_boot() {
    add_action( 'admin_menu', 'ai_suite_register_admin_menu' );
    add_action( 'admin_enqueue_scripts', 'ai_suite_admin_assets' );

    add_action( 'admin_post_ai_suite_save_settings', 'ai_suite_handle_save_settings' );
    add_action( 'admin_post_ai_suite_tools_repair', 'ai_suite_handle_tools_repair' );
}

function ai_suite_register_admin_menu() {
    $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';

    add_menu_page(
        __( 'AI Suite', 'ai-suite' ),
        __( 'AI Suite', 'ai-suite' ),
        $cap,
        'ai-suite',
        'ai_suite_render_admin_page',
        'dashicons-robot',
        58
    );
}

function ai_suite_admin_assets( $hook ) {
    if ( $hook !== 'toplevel_page_ai-suite' ) {
        return;
    }
    $ver = defined('AI_SUITE_VER') ? AI_SUITE_VER : '1.0.0';
    wp_enqueue_style( 'ai-suite-admin', AI_SUITE_URL . 'assets/admin.css', array(), $ver );
    wp_enqueue_script( 'ai-suite-admin', AI_SUITE_URL . 'assets/admin.js', array('jquery'), $ver, true );
}

function ai_suite_get_tabs() {
    $tabs = array(
        'dashboard' => array( 'label' => __( 'Panou', 'ai-suite' ) ),
        'settings'  => array( 'label' => __( 'Setări', 'ai-suite' ) ),
        'tools'     => array( 'label' => __( 'Unelte', 'ai-suite' ) ),
        'ai_queue'  => array( 'label' => __( 'Coadă AI', 'ai-suite' ) ),
        'portal'    => array( 'label' => __( 'Portal (link-uri)', 'ai-suite' ) ),
    );
    if ( function_exists( 'ai_suite_ui_tabs' ) ) {
        $tabs = ai_suite_ui_tabs( $tabs );
    } else {
        $tabs = apply_filters( 'ai_suite_admin_tabs', $tabs );
    }
    return $tabs;
}

function ai_suite_render_admin_page() {
    $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
    if ( ! current_user_can( $cap ) ) {
        wp_die( esc_html__( 'Neautorizat.', 'ai-suite' ) );
    }

    $tabs   = ai_suite_get_tabs();
    $active = isset($_GET['tab']) ? sanitize_key( wp_unslash($_GET['tab']) ) : 'dashboard';
    if ( ! isset( $tabs[$active] ) ) { $active = 'dashboard'; }

    echo '<div class="wrap ai-suite">';
    echo '<div class="ais-header">';
    echo '<div class="ais-header__title">AI Suite</div>';
    echo '<div class="ais-header__sub">' . esc_html__( 'Premium Admin – ordonat, scalabil, ușor de extins.', 'ai-suite' ) . '</div>';
    echo '</div>';

    echo '<nav class="ais-tabs" aria-label="AI Suite Tabs">';
    foreach ( $tabs as $k => $t ) {
        $url = admin_url( 'admin.php?page=ai-suite&tab=' . $k );
        $cls = ($k === $active) ? 'ais-tab is-active' : 'ais-tab';
        echo '<a class="' . esc_attr($cls) . '" href="' . esc_url($url) . '">' . esc_html($t['label']) . '</a>';
    }
    echo '</nav>';

    echo '<div class="ais-shell">';

    if ( $active === 'settings' ) {
        ai_suite_render_tab_settings();
    } elseif ( $active === 'tools' ) {
        ai_suite_render_tab_tools();
    } elseif ( $active === 'ai_queue' ) {
        ai_suite_render_tab_ai_queue();
    } elseif ( $active === 'portal' ) {
        ai_suite_render_tab_portal_links();
    } else {
        ai_suite_render_tab_dashboard();
    }

    echo '</div></div>';
}

function ai_suite_render_tab_dashboard() {
    if ( function_exists('ai_suite_ui_card_open') ) {
        ai_suite_ui_card_open( __( 'Unde te uiți pe frontend', 'ai-suite' ), __( 'Link-uri oficiale create automat de plugin.', 'ai-suite' ) );
    }
    echo '<ul class="ais-list">';
    echo '<li><strong>/portal</strong> – Hub (butoane către tot)</li>';
    echo '<li><strong>/portal-login</strong> – Login</li>';
    echo '<li><strong>/inregistrare-candidat</strong> – Register candidat</li>';
    echo '<li><strong>/inregistrare-companie</strong> – Register companie</li>';
    echo '<li><strong>/portal-candidat</strong> – Dashboard candidat</li>';
    echo '<li><strong>/portal-companie</strong> – Dashboard companie</li>';
    echo '<li><strong>/joburi</strong> – Lista joburi publică</li>';
    echo '</ul>';
    echo '<div class="ais-spacer"></div>';
    echo '<p class="ais-muted">' . esc_html__( 'Dacă vezi 404: mergi în Unelte și apasă “Recreează paginile + Flush Permalinks”.', 'ai-suite' ) . '</p>';
    if ( function_exists('ai_suite_ui_card_close') ) { ai_suite_ui_card_close(); }
}

function ai_suite_render_tab_settings() {
    $settings = function_exists('aisuite_get_settings') ? aisuite_get_settings() : array();
    $openai_key = isset($settings['openai_api_key']) ? (string)$settings['openai_api_key'] : '';
    $model = isset($settings['openai_model']) ? (string)$settings['openai_model'] : 'gpt-4.1-mini';
    $queue_enabled = ! empty($settings['ai_queue_enabled']) ? 1 : 0;
    $ui_lang = isset($settings['ui_language']) ? (string)$settings['ui_language'] : 'ro';
    $enable_en_pages = ! empty($settings['enable_en_pages']) ? 1 : 0;

    echo '<h2 style="margin-top:0;">' . esc_html__( 'Setări', 'ai-suite' ) . '</h2>';
    echo '<form method="post" action="' . esc_url( admin_url('admin-post.php') ) . '">';
    wp_nonce_field( 'ai_suite_save_settings' );
    echo '<input type="hidden" name="action" value="ai_suite_save_settings" />';

    echo '<table class="form-table"><tbody>';

    echo '<tr><th><label for="openai_api_key">OpenAI API Key</label></th><td>';
    echo '<input type="password" id="openai_api_key" name="openai_api_key" class="regular-text" value="' . esc_attr($openai_key) . '" />';
    echo '</td></tr>';

    echo '<tr><th><label for="openai_model">' . esc_html__( 'Model', 'ai-suite' ) . '</label></th><td>';
    echo '<input type="text" id="openai_model" name="openai_model" class="regular-text" value="' . esc_attr($model) . '" />';
    echo '<p class="description">' . esc_html__( 'Ex: gpt-4.1-mini / gpt-4.1 / gpt-4o-mini', 'ai-suite' ) . '</p>';
    echo '</td></tr>';

    echo '<tr><th>' . esc_html__( 'AI Queue', 'ai-suite' ) . '</th><td>';
    echo '<label><input type="checkbox" name="ai_queue_enabled" value="1" ' . checked(1,$queue_enabled,false) . ' /> ' . esc_html__( 'Activează procesarea async (recomandat).', 'ai-suite' ) . '</label>';
    echo '</td></tr>';



    echo '<tr><th>' . esc_html__( 'Limbă UI (frontend)', 'ai-suite' ) . '</th><td>';
    echo '<select name="ui_language">';
    $opts = array(
        'ro'   => __( 'Română (default)', 'ai-suite' ),
        'en'   => __( 'English', 'ai-suite' ),
        'auto' => __( 'Auto (după limba WordPress)', 'ai-suite' ),
    );
    foreach ( $opts as $k => $label ) {
        echo '<option value="' . esc_attr( $k ) . '" ' . selected( $ui_lang, $k, false ) . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select>';
    echo '<p class="description">' . esc_html__( 'Controlează limba etichetelor din job board și portal. Recomandat: Română.', 'ai-suite' ) . '</p>';
    echo '</td></tr>';

    echo '<tr><th>' . esc_html__( 'Pagini EN (opțional)', 'ai-suite' ) . '</th><td>';
    echo '<label><input type="checkbox" name="enable_en_pages" value="1" ' . checked( 1, $enable_en_pages, false ) . ' /> ' . esc_html__( 'Creează și pagini în engleză (evită dacă vrei meniuri curate).', 'ai-suite' ) . '</label>';
    echo '</td></tr>';

    echo '</tbody></table>';

    submit_button( __( 'Salvează', 'ai-suite' ) );
    echo '</form>';
}

function ai_suite_handle_save_settings() {
    $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
    if ( ! current_user_can( $cap ) ) { wp_die( esc_html__( 'Neautorizat.', 'ai-suite' ) ); }
    check_admin_referer( 'ai_suite_save_settings' );

    $settings = function_exists('aisuite_get_settings') ? aisuite_get_settings() : array();
    $settings['openai_api_key'] = isset($_POST['openai_api_key']) ? sanitize_text_field( wp_unslash($_POST['openai_api_key']) ) : '';
    $settings['openai_model']   = isset($_POST['openai_model']) ? sanitize_text_field( wp_unslash($_POST['openai_model']) ) : 'gpt-4.1-mini';
    $settings['ai_queue_enabled'] = ! empty($_POST['ai_queue_enabled']) ? 1 : 0;
    $ui_language = isset($_POST['ui_language']) ? sanitize_key( wp_unslash($_POST['ui_language']) ) : 'ro';
    if ( ! in_array( $ui_language, array('ro','en','auto'), true ) ) { $ui_language = 'ro'; }
    $settings['ui_language'] = $ui_language;

    $settings['enable_en_pages'] = ! empty($_POST['enable_en_pages']) ? 1 : 0;
    // Backward compatibility with previous option name.
    update_option( 'ai_suite_enable_en_pages', (int) $settings['enable_en_pages'], false );


    if ( function_exists('aisuite_update_settings') ) {
        aisuite_update_settings( $settings );
    } else {
        update_option( 'ai_suite_settings', $settings, false );
    }

    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=settings&saved=1' ) );
    exit;
}

function ai_suite_render_tab_tools() {
    echo '<h2 style="margin-top:0;">' . esc_html__( 'Unelte / Reparare (404)', 'ai-suite' ) . '</h2>';
    echo '<p>' . esc_html__( 'Creează paginile frontend și face flush la permalinks.', 'ai-suite' ) . '</p>';

    echo '<form method="post" action="' . esc_url( admin_url('admin-post.php') ) . '">';
    wp_nonce_field( 'ai_suite_tools_repair' );
    echo '<input type="hidden" name="action" value="ai_suite_tools_repair" />';
    submit_button( __( 'Recreează paginile + Flush Permalinks', 'ai-suite' ), 'primary' );
    echo '</form>';

    $slugs = array('portal','portal-login','inregistrare-candidat','inregistrare-companie','portal-candidat','portal-companie','joburi');
    echo '<h3 style="margin-top:18px;">' . esc_html__( 'Status pagini', 'ai-suite' ) . '</h3>';
    echo '<table class="widefat striped"><thead><tr><th>Slug</th><th>Status</th></tr></thead><tbody>';
    foreach ( $slugs as $slug ) {
        $p = get_page_by_path( $slug );
        echo '<tr><td>' . esc_html($slug) . '</td><td>' . ( $p ? 'OK' : '<strong style="color:#b91c1c;">Lipsește</strong>' ) . '</td></tr>';
    }


    echo '</tbody></table>';
}

function ai_suite_handle_tools_repair() {
    $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
    if ( ! current_user_can( $cap ) ) { wp_die( esc_html__( 'Neautorizat.', 'ai-suite' ) ); }
    check_admin_referer( 'ai_suite_tools_repair' );

    if ( function_exists('aisuite_install_or_upgrade') ) {
        aisuite_install_or_upgrade( true );
    }
    if ( function_exists('flush_rewrite_rules') ) { flush_rewrite_rules(); }

    wp_safe_redirect( admin_url( 'admin.php?page=ai-suite&tab=tools&repaired=1' ) );
    exit;
}

function ai_suite_render_tab_ai_queue() {
    if ( file_exists( AI_SUITE_DIR . 'includes/admin/views/tab-ai-queue.php' ) ) {
        require AI_SUITE_DIR . 'includes/admin/views/tab-ai-queue.php';
    } else {
        echo '<p>' . esc_html__( 'AI Queue indisponibil.', 'ai-suite' ) . '</p>';
    }
}

function ai_suite_render_tab_portal_links() {
    echo '<h2 style="margin-top:0;">' . esc_html__( 'Link-uri portal', 'ai-suite' ) . '</h2>';
    $links = array(
        'Portal Hub' => home_url('/portal/'),
        'Login' => home_url('/portal-login/'),
        'Înregistrare candidat' => home_url('/inregistrare-candidat/'),
        'Înregistrare companie' => home_url('/inregistrare-companie/'),
        'Portal candidat' => home_url('/portal-candidat/'),
        'Portal companie' => home_url('/portal-companie/'),
        'Joburi' => home_url('/joburi/'),
    );
    echo '<ul style="line-height:1.9;">';
    foreach ($links as $label=>$url) {
        echo '<li><a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html($label) . '</a></li>';
    }
    echo '</ul>';
}
}
