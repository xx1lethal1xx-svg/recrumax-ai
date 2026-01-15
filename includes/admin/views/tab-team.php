<?php
/**
 * Internal Team management tab view.
 *
 * Roles: aisuite_recruiter, aisuite_manager
 * Assignments: recruiters/managers -> companies (rmax_company CPT)
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'aisuite_current_user_can_manage_team' ) || ! aisuite_current_user_can_manage_team() ) {
    echo '<div class="notice notice-error"><p>' . esc_html__( 'Neautorizat.', 'ai-suite' ) . '</p></div>';
    return;
}

$msg = isset( $_GET['msg'] ) ? sanitize_text_field( wp_unslash( $_GET['msg'] ) ) : '';
if ( $msg ) {
    echo '<div class="notice notice-success"><p>' . esc_html( $msg ) . '</p></div>';
}

// Companies list for assignments.
$companies = get_posts( array(
    'post_type'      => 'rmax_company',
    'post_status'    => 'publish',
    'posts_per_page' => 500,
    'orderby'        => 'title',
    'order'          => 'ASC',
    'fields'         => 'ids',
) );

// Team users (recruiter + manager).
$users = get_users( array(
    'role__in' => array( 'aisuite_recruiter', 'aisuite_manager' ),
    'number'   => 200,
    'orderby'  => 'registered',
    'order'    => 'DESC',
) );

echo '<div class="ai-suite-box">';
echo '<h2>' . esc_html__( 'Echipă internă', 'ai-suite' ) . '</h2>';
echo '<p class="description">' . esc_html__( 'Creează conturi pentru Recruiter/Manager și alocă companiile pe care le gestionează. Adminul are acces complet.', 'ai-suite' ) . '</p>';
echo '</div>';

// Invite/Create user
echo '<div class="ai-suite-box">';
echo '<h3>' . esc_html__( 'Invită coleg', 'ai-suite' ) . '</h3>';
echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
echo '<input type="hidden" name="action" value="ai_suite_team_invite" />';
wp_nonce_field( 'ai_suite_team_invite' );

echo '<div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">';
echo '<div><label><strong>' . esc_html__( 'Email', 'ai-suite' ) . '</strong><br><input type="email" name="email" required style="min-width:280px" /></label></div>';
echo '<div><label><strong>' . esc_html__( 'Nume', 'ai-suite' ) . '</strong><br><input type="text" name="name" style="min-width:240px" /></label></div>';
echo '<div><label><strong>' . esc_html__( 'Rol', 'ai-suite' ) . '</strong><br>';
echo '<select name="role">';
echo '<option value="aisuite_recruiter">' . esc_html__( 'Recruiter', 'ai-suite' ) . '</option>';
echo '<option value="aisuite_manager">' . esc_html__( 'Manager', 'ai-suite' ) . '</option>';
echo '</select></label></div>';
echo '<div><button class="button button-primary">' . esc_html__( 'Creează / Trimite invitație', 'ai-suite' ) . '</button></div>';
echo '</div>';

echo '<p class="description">' . esc_html__( 'Se creează un user WordPress și se trimite email de setare parolă (standard WordPress). Dacă userul există deja, îi setăm rolul selectat.', 'ai-suite' ) . '</p>';
echo '</form>';
echo '</div>';

// Users table
echo '<div class="ai-suite-box">';
echo '<h3>' . esc_html__( 'Membrii echipei', 'ai-suite' ) . '</h3>';

if ( empty( $users ) ) {
    echo '<p>' . esc_html__( 'Nu există încă membri în echipă.', 'ai-suite' ) . '</p>';
    echo '</div>';
    return;
}

echo '<table class="widefat striped">';
echo '<thead><tr>';
echo '<th>' . esc_html__( 'Utilizator', 'ai-suite' ) . '</th>';
echo '<th>' . esc_html__( 'Rol', 'ai-suite' ) . '</th>';
echo '<th>' . esc_html__( 'Companii alocate', 'ai-suite' ) . '</th>';
echo '<th>' . esc_html__( 'Acțiuni', 'ai-suite' ) . '</th>';
echo '</tr></thead><tbody>';

foreach ( $users as $u ) {
    $uid = (int) $u->ID;
    $role_label = in_array( 'aisuite_manager', (array) $u->roles, true ) ? __( 'Manager', 'ai-suite' ) : __( 'Recruiter', 'ai-suite' );
    $assigned = function_exists( 'aisuite_get_assigned_company_ids' ) ? aisuite_get_assigned_company_ids( $uid ) : array();

    echo '<tr>';
    echo '<td><strong>' . esc_html( $u->display_name ? $u->display_name : $u->user_login ) . '</strong><br><span class="description">' . esc_html( $u->user_email ) . '</span></td>';
    echo '<td>' . esc_html( $role_label ) . '</td>';

    echo '<td>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="ai_suite_team_save_assignments" />';
    echo '<input type="hidden" name="user_id" value="' . esc_attr( $uid ) . '" />';
    wp_nonce_field( 'ai_suite_team_save_assignments' );

    echo '<select name="company_ids[]" multiple size="6" style="min-width:360px;max-width:520px;">';
    if ( ! empty( $companies ) ) {
        foreach ( $companies as $cid ) {
            $title = get_the_title( $cid );
            $sel = in_array( (int) $cid, $assigned, true ) ? ' selected' : '';
            echo '<option value="' . esc_attr( (int) $cid ) . '"' . $sel . '>' . esc_html( $title ? $title : ('#' . (int)$cid) ) . '</option>';
        }
    }
    echo '</select>';
    echo '<div class="description">' . esc_html__( 'Ține Ctrl/Cmd pentru selecție multiplă.', 'ai-suite' ) . '</div>';
    echo '</td>';

    echo '<td style="vertical-align:top">';
    echo '<button class="button button-secondary">' . esc_html__( 'Salvează alocări', 'ai-suite' ) . '</button>';
    echo '</form>';
    echo '</td>';

    echo '</tr>';
}

echo '</tbody></table>';
echo '</div>';
