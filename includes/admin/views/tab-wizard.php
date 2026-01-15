<?php
/**
 * Setup Wizard (admin tab) for AI Suite.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
if ( ! current_user_can( $cap ) ) {
    echo '<div class="notice notice-error"><p>' . esc_html__( 'Nu ai permisiuni pentru această pagină.', 'ai-suite' ) . '</p></div>';
    return;
}

$required_slugs = array(
    'joburi',
    'portal',
    'portal-login',
    'inregistrare-candidat',
    'inregistrare-companie',
    'portal-candidat',
    'portal-companie',
);

$missing_pages = array();
foreach ( $required_slugs as $slug ) {
    $p = get_page_by_path( $slug );
    if ( ! $p ) {
        $missing_pages[] = $slug;
    }
}

$permalink_ok = (bool) get_option( 'permalink_structure' );
$setup_done = (bool) get_option( 'ai_suite_setup_done', 0 );

$nonce_repair = wp_create_nonce( 'ai_suite_repair_frontend_pages' );
$nonce_menu   = wp_create_nonce( 'ai_suite_rebuild_frontend_menu' );
$nonce_done   = wp_create_nonce( 'ai_suite_mark_setup_done' );
$demo_nonce   = wp_create_nonce( 'ai_suite_demo_actions' );
?>

<div class="wrap ai-suite-wrap">
    <h1><?php echo esc_html__( 'Asistent configurare (Wizard)', 'ai-suite' ); ?></h1>

    <p><?php echo esc_html__( 'Rulează pașii de mai jos o singură dată, pentru ca platforma să fie stabilă și complet funcțională.', 'ai-suite' ); ?></p>

    <div class="ai-suite-cards">
        <div class="ai-suite-card">
            <h2><?php echo esc_html__( '1) Verifică permalinks', 'ai-suite' ); ?></h2>
            <p>
                <?php if ( $permalink_ok ) : ?>
                    <span class="ai-pill ok"><?php echo esc_html__( 'OK', 'ai-suite' ); ?></span>
                    <?php echo esc_html__( 'Permalinks sunt activate.', 'ai-suite' ); ?>
                <?php else : ?>
                    <span class="ai-pill bad"><?php echo esc_html__( 'Necesită', 'ai-suite' ); ?></span>
                    <?php echo esc_html__( 'Permalinks sunt pe "Plain". Setează orice structură (ex: Post name).', 'ai-suite' ); ?>
                    <br>
                    <a class="button" href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>"><?php echo esc_html__( 'Deschide Permalinks', 'ai-suite' ); ?></a>
                <?php endif; ?>
            </p>
        </div>

        <div class="ai-suite-card">
            <h2><?php echo esc_html__( '2) Creează pagini + meniuri', 'ai-suite' ); ?></h2>
            <p>
                <?php if ( empty( $missing_pages ) ) : ?>
                    <span class="ai-pill ok"><?php echo esc_html__( 'OK', 'ai-suite' ); ?></span>
                    <?php echo esc_html__( 'Paginile principale există.', 'ai-suite' ); ?>
                <?php else : ?>
                    <span class="ai-pill bad"><?php echo esc_html__( 'Lipsesc', 'ai-suite' ); ?></span>
                    <?php echo esc_html__( 'Lipsesc pagini:', 'ai-suite' ); ?> <code><?php echo esc_html( implode( ', ', $missing_pages ) ); ?></code>
                <?php endif; ?>
            </p>

            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="ai_suite_repair_frontend_pages" />
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce_repair ); ?>" />
                    <button class="button button-primary" type="submit"><?php echo esc_html__( 'Creează/Repair pagini', 'ai-suite' ); ?></button>
                </form>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="ai_suite_rebuild_frontend_menu" />
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce_menu ); ?>" />
                    <button class="button" type="submit"><?php echo esc_html__( 'Regenerează meniul', 'ai-suite' ); ?></button>
                </form>
            </div>
        </div>

        <div class="ai-suite-card">
            <h2><?php echo esc_html__( '3) Test OpenAI', 'ai-suite' ); ?></h2>
            <p><?php echo esc_html__( 'Verifică dacă cheia API și modelul sunt funcționale.', 'ai-suite' ); ?></p>
            <p>
                <button id="ai-test-openai" class="button button-primary" type="button"><?php echo esc_html__( 'Testează OpenAI', 'ai-suite' ); ?></button>
            </p>
            <div id="ai-healthcheck-result" class="ai-suite-result"></div>
        </div>

        <div class="ai-suite-card">
            <h2><?php echo esc_html__( '4) Seed demo (opțional)', 'ai-suite' ); ?></h2>
            <p><?php echo esc_html__( 'Populează joburi/candidați/aplicații demo ca să vezi fluxul complet.', 'ai-suite' ); ?></p>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="ai_suite_seed_demo" />
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $demo_nonce ); ?>" />
                    <label style="display:inline-flex; align-items:center; gap:6px;"><input type="checkbox" name="force" value="1" /><?php echo esc_html__( 'Force', 'ai-suite' ); ?></label>
                    <button class="button" type="submit"><?php echo esc_html__( 'Seed demo', 'ai-suite' ); ?></button>
                </form>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="ai_suite_clear_demo" />
                    <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $demo_nonce ); ?>" />
                    <button class="button" type="submit" onclick="return confirm('Ștergi datele demo?');"><?php echo esc_html__( 'Șterge demo', 'ai-suite' ); ?></button>
                </form>
            </div>
        </div>

        <div class="ai-suite-card">
            <h2><?php echo esc_html__( '5) Marchează setup ca finalizat', 'ai-suite' ); ?></h2>
            <p>
                <?php if ( $setup_done ) : ?>
                    <span class="ai-pill ok"><?php echo esc_html__( 'Finalizat', 'ai-suite' ); ?></span>
                <?php else : ?>
                    <span class="ai-pill bad"><?php echo esc_html__( 'Ne-finalizat', 'ai-suite' ); ?></span>
                <?php endif; ?>
            </p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="ai_suite_mark_setup_done" />
                <input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce_done ); ?>" />
                <button class="button button-primary" type="submit"><?php echo esc_html__( 'Marchează ca DONE', 'ai-suite' ); ?></button>
            </form>
        </div>

    </div>
</div>
