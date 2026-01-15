<?php
/**
 * Tools tab – diagnostics + quick repair actions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
if ( ! current_user_can( $cap ) ) {
    echo '<p>' . esc_html__( 'Neautorizat.', 'ai-suite' ) . '</p>';
    return;
}

$expected = array(
    array( 'slug' => 'portal', 'title' => __( 'Portal (Hub)', 'ai-suite' ), 'content' => '[ai_suite_portal_hub]' ),
    array( 'slug' => 'portal-login', 'title' => __( 'Portal – Login', 'ai-suite' ), 'content' => '[ai_suite_portal_login]' ),
    array( 'slug' => 'inregistrare-candidat', 'title' => __( 'Înregistrare Candidat', 'ai-suite' ), 'content' => '[ai_suite_candidate_register]' ),
    array( 'slug' => 'inregistrare-companie', 'title' => __( 'Înregistrare Companie', 'ai-suite' ), 'content' => '[ai_suite_company_register]' ),
    array( 'slug' => 'portal-candidat', 'title' => __( 'Portal Candidat', 'ai-suite' ), 'content' => '[ai_suite_candidate_portal]' ),
    array( 'slug' => 'portal-companie', 'title' => __( 'Portal Companie', 'ai-suite' ), 'content' => '[ai_suite_company_portal]' ),
    array( 'slug' => 'joburi', 'title' => __( 'Joburi', 'ai-suite' ), 'content' => '[ai_suite_jobs]' ),
);

echo '<div class="ais-card" style="max-width: 1100px;">';
echo '<h2 style="margin-top:0;">' . esc_html__( 'Unelte / Diagnostic', 'ai-suite' ) . '</h2>';
echo '<p>' . esc_html__( 'Aici vezi dacă paginile de frontend există și poți repara automat (create + flush permalinks).', 'ai-suite' ) . '</p>';

// Safe Mode / Hardening status
if ( function_exists( 'aisuite_is_safe_mode' ) || ( defined( 'AI_SUITE_SAFEBOOT_OPT_FATAL' ) && defined( 'AI_SUITE_SAFEBOOT_OPT_DISABLED' ) ) ) {
    $is_safe = function_exists( 'aisuite_is_safe_mode' ) ? (bool) aisuite_is_safe_mode() : false;
    $fatal = defined( 'AI_SUITE_SAFEBOOT_OPT_FATAL' ) ? get_option( AI_SUITE_SAFEBOOT_OPT_FATAL, array() ) : array();
    $disabled = function_exists( 'aisuite_safe_boot_get_disabled_modules' ) ? aisuite_safe_boot_get_disabled_modules() : ( defined( 'AI_SUITE_SAFEBOOT_OPT_DISABLED' ) ? (array) get_option( AI_SUITE_SAFEBOOT_OPT_DISABLED, array() ) : array() );

    echo '<div class="ais-card" style="margin: 14px 0 18px; padding: 14px 16px; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 10px;">';
    echo '<div style="display:flex; gap:12px; align-items:center; justify-content:space-between; flex-wrap:wrap;">';
    echo '<div>';
    echo '<div style="font-weight:700;">' . esc_html__( 'Safe Mode / Hardening', 'ai-suite' ) . '</div>';
    echo '<div style="color:#7c2d12; font-size:13px; margin-top:4px;">';
    if ( $is_safe ) {
        $until = defined( 'AI_SUITE_SAFEBOOT_OPT_SAFEMODE_UNTIL' ) ? (int) get_option( AI_SUITE_SAFEBOOT_OPT_SAFEMODE_UNTIL, 0 ) : 0;
        $mins  = $until ? max( 1, (int) ceil( ( $until - time() ) / 60 ) ) : 0;
        echo esc_html__( 'Activ: pluginul rulează doar modulele esențiale ca să nu se blocheze wp-admin.', 'ai-suite' ) . ( $mins ? ' ' . sprintf( esc_html__( 'Expiră în ~%d minute.', 'ai-suite' ), (int) $mins ) : '' );
    } else {
        echo esc_html__( 'Inactiv: toate modulele sunt permise.', 'ai-suite' );
    }
    if ( is_array( $fatal ) && ! empty( $fatal['file'] ) ) {
        echo '<br/><strong>' . esc_html__( 'Ultimul crash:', 'ai-suite' ) . '</strong> <code>' . esc_html( basename( (string) $fatal['file'] ) ) . '</code>';
        if ( ! empty( $fatal['line'] ) ) {
            echo ' <span style="color:#9a3412;">' . esc_html__( 'linia', 'ai-suite' ) . ' ' . (int) $fatal['line'] . '</span>';
        }
    }
    echo '</div>';
    echo '</div>';

    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:0;">';
    echo '<input type="hidden" name="action" value="ai_suite_clear_safe_mode" />';
    wp_nonce_field( 'ai_suite_clear_safe_mode' );
    echo '<button class="button">' . esc_html__( 'Reset Safe Mode + Re-Enable modules', 'ai-suite' ) . '</button>';
    echo '</form>';
    echo '</div>';

    // Module toggles
    $modules = array(
        'bots'     => array( 'label' => __( 'Boți AI (Manager/Content/Social)', 'ai-suite' ), 'hint' => __( 'Dezactivează temporar dacă îți blochează wp-admin.', 'ai-suite' ) ),
        'facebook' => array( 'label' => __( 'Facebook Leads', 'ai-suite' ), 'hint' => __( 'Integrare leads + webhook/polling.', 'ai-suite' ) ),
        'billing'  => array( 'label' => __( 'Subscriptions / Billing', 'ai-suite' ), 'hint' => __( 'Abonamente, joburi promovate, plăți.', 'ai-suite' ) ),
    );
    echo '<div style="margin-top:12px;">';
    echo '<table class="widefat striped" style="max-width: 980px;">';
    echo '<thead><tr><th>' . esc_html__( 'Modul', 'ai-suite' ) . '</th><th>' . esc_html__( 'Status', 'ai-suite' ) . '</th><th>' . esc_html__( 'Acțiune', 'ai-suite' ) . '</th></tr></thead><tbody>';
    foreach ( $modules as $key => $info ) {
        $is_on = ! in_array( $key, (array) $disabled, true );
        echo '<tr>';
        echo '<td><strong>' . esc_html( $info['label'] ) . '</strong><div style="color:#666; font-size:12px; margin-top:3px;">' . esc_html( $info['hint'] ) . '</div></td>';
        echo '<td>' . ( $is_on ? '<span style="color:#0a7d0a;font-weight:700;">ON</span>' : '<span style="color:#b42318;font-weight:700;">OFF</span>' ) . '</td>';
        echo '<td>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="ai_suite_toggle_module" />';
        wp_nonce_field( 'ai_suite_toggle_module' );
        echo '<input type="hidden" name="module" value="' . esc_attr( $key ) . '" />';
        echo '<input type="hidden" name="enabled" value="' . esc_attr( $is_on ? 0 : 1 ) . '" />';
        echo '<button class="button">' . esc_html( $is_on ? __( 'Dezactivează', 'ai-suite' ) : __( 'Activează', 'ai-suite' ) ) . '</button>';
        echo '</form>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
    echo '</div>';
}

// Repair button.
echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin: 14px 0 18px;">';
echo '<input type="hidden" name="action" value="ai_suite_repair_frontend_pages" />';
wp_nonce_field( 'ai_suite_repair_frontend_pages' );
echo '<button class="button button-primary">' . esc_html__( 'Recreează paginile + repară permalinks', 'ai-suite' ) . '</button>';
echo '</form>';

// Rebuild menu button.
echo '<div class="ais-card" style="margin: 0 0 18px; padding: 14px 16px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 10px;">';
echo '<div style="font-weight:600; margin-bottom:6px;">' . esc_html__( 'Navigație (meniul din header)', 'ai-suite' ) . '</div>';
echo '<div style="color:#555; font-size:13px; margin-bottom:10px;">' . esc_html__( 'Dacă tema afișează o listă lungă cu pagini (duplicat RO/EN), apasă butonul de mai jos. Pluginul va crea un meniu ordonat (Joburi → Portal Login → Înregistrări → Portale) și va încerca să îl seteze ca meniu principal.', 'ai-suite' ) . '</div>';
echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
echo '<input type="hidden" name="action" value="ai_suite_rebuild_frontend_menu" />';
wp_nonce_field( 'ai_suite_rebuild_frontend_menu' );
echo '<button class="button">' . esc_html__( 'Rebuild meniu frontend (ordonare + dedupe)', 'ai-suite' ) . '</button>';
echo '</form>';
echo '<div style="color:#666; font-size:12px; margin-top:8px;">' . esc_html__( 'Dacă nu se poate seta automat (unele teme au locații custom), intră în Appearance → Menus și atribuie manual meniul „AI Suite – Recruitment” la locația „Primary”.', 'ai-suite' ) . '</div>';
echo '</div>';

// Status table.
echo '<table class="widefat striped">';
echo '<thead><tr>';
echo '<th>' . esc_html__( 'Pagină', 'ai-suite' ) . '</th>';
echo '<th>' . esc_html__( 'Slug', 'ai-suite' ) . '</th>';
echo '<th>' . esc_html__( 'Status', 'ai-suite' ) . '</th>';
echo '<th>' . esc_html__( 'Link', 'ai-suite' ) . '</th>';
echo '</tr></thead><tbody>';

foreach ( $expected as $p ) {
    $page = get_page_by_path( $p['slug'] );
    $ok   = ( $page && isset( $page->ID ) && $page->post_status === 'publish' );
    $url  = $ok ? get_permalink( $page->ID ) : '';

    echo '<tr>';
    echo '<td><strong>' . esc_html( $p['title'] ) . '</strong></td>';
    echo '<td><code>' . esc_html( $p['slug'] ) . '</code></td>';
    echo '<td>' . ( $ok ? '<span style="color:#1a7f37;font-weight:600;">OK</span>' : '<span style="color:#b42318;font-weight:600;">Lipsește</span>' ) . '</td>';
    echo '<td>';
    if ( $ok ) {
        echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Deschide', 'ai-suite' ) . '</a>';
    } else {
        echo '—';
    }
    echo '</td>';
    echo '</tr>';
}

echo '</tbody></table>';

echo '<div style="margin-top:14px; font-size: 13px; color:#555;">';
echo '<p style="margin:0;">' . esc_html__( 'Notă: Dacă folosești permalinks „Plain”, linkurile /portal-companie vor da 404. Setează Permalinks pe „Post name” și apasă Save.', 'ai-suite' ) . '</p>';
echo '</div>';

echo '</div>';


?>

<hr style="margin:24px 0" />

<h2><?php echo esc_html__( 'Enterprise – Candidate Index', 'ai-suite' ); ?></h2>
<p class="description">
    <?php echo esc_html__( 'Index SQL pentru căutare rapidă (nume/email/telefon/skills/locație). Recomandat când ai multe CV-uri.', 'ai-suite' ); ?>
</p>

<?php
$index_exists = function_exists('ai_suite_candidate_index_exists') ? ai_suite_candidate_index_exists() : false;
?>
<table class="widefat striped" style="max-width: 860px;">
    <tbody>
        <tr>
            <th><?php echo esc_html__( 'Index tabelă', 'ai-suite' ); ?></th>
            <td>
                <?php echo $index_exists ? '<span style="color:#0a7d0a;font-weight:600">OK</span>' : '<span style="color:#b00;font-weight:600">Nu există încă</span>'; ?>
            </td>
        </tr>
        <tr>
            <th><?php echo esc_html__( 'Acțiuni', 'ai-suite' ); ?></th>
            <td>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'ai_suite_reindex_candidates', 'ai_suite_nonce' ); ?>
                    <input type="hidden" name="action" value="ai_suite_reindex_candidates" />
                    <button type="submit" class="button button-primary"><?php echo esc_html__( 'Creează/Rebuild Index (Reindex)', 'ai-suite' ); ?></button>
                </form>
                <p class="description" style="margin-top:8px">
                    <?php echo esc_html__( 'Rulează manual când ai importat candidați sau ai modificat meta. În timp, indexul se actualizează automat la editare.', 'ai-suite' ); ?>
                </p>
            </td>
        </tr>
    </tbody>
</table>
