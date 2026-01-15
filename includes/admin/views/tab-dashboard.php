<?php
/**
 * Dashboard (Panou principal) – Premium Bento UI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Optional helper pack (server-side widgets: status, top candidates, activity).
if ( defined( 'AI_SUITE_DIR' ) && file_exists( AI_SUITE_DIR . 'includes/admin/dashboard-pro.php' ) ) {
    require_once AI_SUITE_DIR . 'includes/admin/dashboard-pro.php';
}

global $wpdb;

// ---------------------------
// Counts (safe, defensive)
// ---------------------------
$counts = function_exists( 'aisuite_dashboard_pro_get_counts' ) ? (array) aisuite_dashboard_pro_get_counts() : array();

$jobs_total  = isset( $counts['jobs']['total'] ) ? (int) $counts['jobs']['total'] : 0;
$jobs_active = isset( $counts['jobs']['active'] ) ? (int) $counts['jobs']['active'] : 0;

$apps_total  = isset( $counts['applications_total'] ) ? (int) $counts['applications_total'] : 0;
$app_status  = isset( $counts['applications_statuses'] ) && is_array( $counts['applications_statuses'] ) ? $counts['applications_statuses'] : array();

$cands_total = isset( $counts['candidates_total'] ) ? (int) $counts['candidates_total'] : 0;

// Companies
$companies_total = 0;
if ( function_exists( 'wp_count_posts' ) ) {
    $cc = wp_count_posts( 'rmax_company' );
    if ( is_object( $cc ) ) {
        $companies_total = 0;
        foreach ( get_object_vars( $cc ) as $v ) {
            $companies_total += (int) $v;
        }
    }
}

// Pending applications = statuses that are not final (angajat/respins).
$pending_statuses = array( 'nou', 'in_analiza', 'interviu', 'oferta', 'in_proces' );
$apps_pending = 0;
foreach ( $pending_statuses as $st ) {
    if ( isset( $app_status[ $st ] ) ) {
        $apps_pending += (int) $app_status[ $st ];
    }
}

// Apps today
$apps_today = 0;
try {
    $today_gmt = gmdate( 'Y-m-d 00:00:00' );
    $apps_today = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type=%s AND post_status='publish' AND post_date_gmt >= %s",
        'rmax_application',
        $today_gmt
    ) );
} catch ( Exception $e ) {
    $apps_today = 0;
}

// AI Queue pending (if table exists)
$queue_pending = 0;
try {
    $qtable = $wpdb->prefix . 'ai_suite_ai_queue';
    $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $qtable ) );
    if ( $exists === $qtable ) {
        $queue_pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$qtable} WHERE status='queued'" );
    }
} catch ( Exception $e ) {
    $queue_pending = 0;
}

// Facebook leads last 7d (if table exists)
$leads_7d = 0;
try {
    $ltable = $wpdb->prefix . 'ai_suite_fb_leads';
    $exists2 = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ltable ) );
    if ( $exists2 === $ltable ) {
        $since = gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS );
        $leads_7d = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ltable} WHERE created_at >= %s", $since ) );
    }
} catch ( Exception $e ) {
    $leads_7d = 0;
}

// Safe Mode / last crash
$safe_mode = function_exists( 'aisuite_is_safe_mode' ) ? (bool) aisuite_is_safe_mode() : false;
$fatal = defined( 'AI_SUITE_SAFEBOOT_OPT_FATAL' ) ? get_option( AI_SUITE_SAFEBOOT_OPT_FATAL, array() ) : array();
$last_crash_file = ( is_array( $fatal ) && ! empty( $fatal['file'] ) ) ? basename( (string) $fatal['file'] ) : '';
$last_crash_time = ( is_array( $fatal ) && ! empty( $fatal['time'] ) ) ? (int) $fatal['time'] : 0;

// Recent activity (best-effort)
$recent_activity = function_exists( 'aisuite_dashboard_pro_recent_activity' ) ? (array) aisuite_dashboard_pro_recent_activity( 8 ) : array();

// Top candidates (best-effort)
$top_candidates = function_exists( 'aisuite_dashboard_pro_top_candidates' ) ? (array) aisuite_dashboard_pro_top_candidates( 6 ) : array();

$url_jobs         = admin_url( 'admin.php?page=ai-suite&tab=jobs' );
$url_apps         = admin_url( 'admin.php?page=ai-suite&tab=applications' );
$url_companies    = admin_url( 'admin.php?page=ai-suite&tab=companies' );
$url_candidates   = admin_url( 'admin.php?page=ai-suite&tab=candidates' );
$url_health       = admin_url( 'admin.php?page=ai-suite&tab=healthcheck' );
$url_copilot      = admin_url( 'admin.php?page=ai-suite&tab=copilot' );
$url_leads        = admin_url( 'admin.php?page=ai-suite&tab=facebook_leads' );
$url_tools        = admin_url( 'admin.php?page=ai-suite&tab=tools' );
$url_billing      = admin_url( 'admin.php?page=ai-suite&tab=billing' );

?>

<div class="ais-shell">
    <div class="ais-bento">

        <!-- KPI row -->
        <div class="ais-card ais-kpi ais-col-3">
            <div class="ais-kpi__icon"><span class="dashicons dashicons-megaphone"></span></div>
            <div class="ais-kpi__meta">
                <div class="ais-kpi__label"><?php echo esc_html__( 'Joburi active', 'ai-suite' ); ?></div>
                <div class="ais-kpi__value"><?php echo esc_html( $jobs_active ); ?></div>
                <div class="ais-kpi__hint"><?php echo esc_html( sprintf( __( 'Total: %d', 'ai-suite' ), $jobs_total ) ); ?></div>
            </div>
        </div>

        <div class="ais-card ais-kpi ais-col-3">
            <div class="ais-kpi__icon"><span class="dashicons dashicons-feedback"></span></div>
            <div class="ais-kpi__meta">
                <div class="ais-kpi__label"><?php echo esc_html__( 'Aplicații în așteptare', 'ai-suite' ); ?></div>
                <div class="ais-kpi__value"><?php echo esc_html( $apps_pending ); ?></div>
                <div class="ais-kpi__hint"><?php echo esc_html( sprintf( __( 'Astăzi: %d', 'ai-suite' ), $apps_today ) ); ?></div>
            </div>
        </div>

        <div class="ais-card ais-kpi ais-col-3">
            <div class="ais-kpi__icon"><span class="dashicons dashicons-groups"></span></div>
            <div class="ais-kpi__meta">
                <div class="ais-kpi__label"><?php echo esc_html__( 'Candidați', 'ai-suite' ); ?></div>
                <div class="ais-kpi__value"><?php echo esc_html( $cands_total ); ?></div>
                <div class="ais-kpi__hint"><?php echo esc_html( sprintf( __( 'Aplicații total: %d', 'ai-suite' ), $apps_total ) ); ?></div>
            </div>
        </div>

        <div class="ais-card ais-kpi ais-col-3">
            <div class="ais-kpi__icon"><span class="dashicons dashicons-building"></span></div>
            <div class="ais-kpi__meta">
                <div class="ais-kpi__label"><?php echo esc_html__( 'Companii', 'ai-suite' ); ?></div>
                <div class="ais-kpi__value"><?php echo esc_html( $companies_total ); ?></div>
                <div class="ais-kpi__hint"><?php echo esc_html( sprintf( __( 'Leads 7 zile: %d', 'ai-suite' ), $leads_7d ) ); ?></div>
            </div>
        </div>

        <!-- Big chart -->
        <div class="ais-card ais-col-8">
            <div class="ais-card__head">
                <h2 class="ais-h2"><?php echo esc_html__( 'Trend aplicații (ultimele 14 zile)', 'ai-suite' ); ?></h2>
                <div class="ais-muted"><?php echo esc_html__( 'Datele se actualizează automat.', 'ai-suite' ); ?></div>
            </div>
            <div class="ais-card__body">
                <canvas id="ai-kpi-chart" height="100"></canvas>
            </div>
        </div>

        <!-- System status -->
        <div class="ais-card ais-col-4">
            <div class="ais-card__head">
                <h2 class="ais-h2"><?php echo esc_html__( 'Status sistem', 'ai-suite' ); ?></h2>
                <div class="ais-muted"><?php echo esc_html__( 'Safe Mode, cozi, crash-uri.', 'ai-suite' ); ?></div>
            </div>
            <div class="ais-card__body">

                <?php if ( $safe_mode ) : ?>
                    <div class="ais-notice ais-notice--warn" style="margin-bottom:10px;">
                        <strong><?php echo esc_html__( 'Safe Mode activ', 'ai-suite' ); ?></strong>
                        <div class="ais-muted"><?php echo esc_html__( 'Unele module pot fi dezactivate automat până rezolvi eroarea.', 'ai-suite' ); ?></div>
                        <div style="margin-top:8px;"><a class="button" href="<?php echo esc_url( $url_tools ); ?>"><?php echo esc_html__( 'Deschide Unelte', 'ai-suite' ); ?></a></div>
                    </div>
                <?php endif; ?>

                <?php if ( $last_crash_file ) : ?>
                    <div class="ais-notice" style="margin-bottom:10px;">
                        <strong><?php echo esc_html__( 'Ultimul crash', 'ai-suite' ); ?>:</strong>
                        <code><?php echo esc_html( $last_crash_file ); ?></code>
                        <?php if ( $last_crash_time ) : ?>
                            <div class="ais-muted" style="margin-top:6px;">
                                <?php echo esc_html( sprintf( __( 'Data: %s', 'ai-suite' ), date_i18n( 'Y-m-d H:i', $last_crash_time ) ) ); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="ais-sysgrid">
                    <div class="ais-sys">
                        <div class="ais-sys__label"><?php echo esc_html__( 'Coadă AI', 'ai-suite' ); ?></div>
                        <div class="ais-sys__value"><?php echo esc_html( $queue_pending ); ?></div>
                        <div class="ais-sys__hint"><?php echo esc_html__( 'task-uri queued', 'ai-suite' ); ?></div>
                    </div>
                    <div class="ais-sys">
                        <div class="ais-sys__label"><?php echo esc_html__( 'Leads', 'ai-suite' ); ?></div>
                        <div class="ais-sys__value"><?php echo esc_html( $leads_7d ); ?></div>
                        <div class="ais-sys__hint"><?php echo esc_html__( 'ultimele 7 zile', 'ai-suite' ); ?></div>
                    </div>
                </div>

                <div class="ais-actions" style="margin-top:12px;">
                    <a class="button button-primary" href="<?php echo esc_url( $url_health ); ?>"><span class="dashicons dashicons-shield" style="margin-right:6px;"></span><?php echo esc_html__( 'Rulează Healthcheck', 'ai-suite' ); ?></a>
                    <a class="button" href="<?php echo esc_url( $url_copilot ); ?>"><span class="dashicons dashicons-format-chat" style="margin-right:6px;"></span><?php echo esc_html__( 'Deschide Copilot', 'ai-suite' ); ?></a>
                </div>
            </div>
        </div>

        <!-- Recent activity -->
        <div class="ais-card ais-col-6">
            <div class="ais-card__head">
                <h2 class="ais-h2"><?php echo esc_html__( 'Activitate recentă', 'ai-suite' ); ?></h2>
                <div class="ais-muted"><?php echo esc_html__( 'Ultimele acțiuni înregistrate.', 'ai-suite' ); ?></div>
            </div>
            <div class="ais-card__body">
                <?php if ( ! empty( $recent_activity ) ) : ?>
                    <ul class="ais-activity">
                        <?php foreach ( $recent_activity as $row ) :
                            $t = isset( $row['time'] ) ? (int) $row['time'] : 0;
                            $msg = isset( $row['message'] ) ? (string) $row['message'] : '';
                            $lvl = isset( $row['level'] ) ? (string) $row['level'] : 'info';
                            $cls = 'ais-activity__item';
                            if ( $lvl === 'error' ) $cls .= ' is-error';
                            if ( $lvl === 'warn' ) $cls .= ' is-warn';
                        ?>
                        <li class="<?php echo esc_attr( $cls ); ?>">
                            <div class="ais-activity__time"><?php echo esc_html( $t ? date_i18n( 'd.m H:i', $t ) : '' ); ?></div>
                            <div class="ais-activity__msg"><?php echo esc_html( $msg ); ?></div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <div style="margin-top:10px;"><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=ai-suite&tab=logs' ) ); ?>"><?php echo esc_html__( 'Vezi jurnal complet', 'ai-suite' ); ?></a></div>
                <?php else : ?>
                    <div class="ais-muted"><?php echo esc_html__( 'Nu există încă evenimente în jurnal.', 'ai-suite' ); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="ais-card ais-col-3">
            <div class="ais-card__head">
                <h2 class="ais-h2"><?php echo esc_html__( 'Acțiuni rapide', 'ai-suite' ); ?></h2>
                <div class="ais-muted"><?php echo esc_html__( 'Cele mai folosite.', 'ai-suite' ); ?></div>
            </div>
            <div class="ais-card__body">
                <div class="ais-quick">
                    <a class="ais-quick__btn" href="<?php echo esc_url( $url_jobs ); ?>"><span class="dashicons dashicons-megaphone"></span><?php echo esc_html__( 'Joburi', 'ai-suite' ); ?></a>
                    <a class="ais-quick__btn" href="<?php echo esc_url( $url_apps ); ?>"><span class="dashicons dashicons-feedback"></span><?php echo esc_html__( 'Aplicații', 'ai-suite' ); ?></a>
                    <a class="ais-quick__btn" href="<?php echo esc_url( $url_candidates ); ?>"><span class="dashicons dashicons-groups"></span><?php echo esc_html__( 'Candidați', 'ai-suite' ); ?></a>
                    <a class="ais-quick__btn" href="<?php echo esc_url( $url_companies ); ?>"><span class="dashicons dashicons-building"></span><?php echo esc_html__( 'Companii', 'ai-suite' ); ?></a>
                    <a class="ais-quick__btn" href="<?php echo esc_url( $url_billing ); ?>"><span class="dashicons dashicons-cart"></span><?php echo esc_html__( 'Abonamente', 'ai-suite' ); ?></a>
                    <a class="ais-quick__btn" href="<?php echo esc_url( $url_leads ); ?>"><span class="dashicons dashicons-facebook"></span><?php echo esc_html__( 'Facebook Leads', 'ai-suite' ); ?></a>
                </div>
            </div>
        </div>

        <!-- Top candidates -->
        <div class="ais-card ais-col-3">
            <div class="ais-card__head">
                <h2 class="ais-h2"><?php echo esc_html__( 'Top candidați', 'ai-suite' ); ?></h2>
                <div class="ais-muted"><?php echo esc_html__( 'Scor/activitate (rapid).', 'ai-suite' ); ?></div>
            </div>
            <div class="ais-card__body">
                <?php if ( ! empty( $top_candidates ) ) : ?>
                    <ol class="ais-top">
                        <?php foreach ( $top_candidates as $c ) :
                            $name = isset( $c['name'] ) ? (string) $c['name'] : '';
                            $score = isset( $c['score'] ) ? (int) $c['score'] : 0;
                            $link = isset( $c['link'] ) ? (string) $c['link'] : '';
                        ?>
                            <li class="ais-top__item">
                                <a href="<?php echo esc_url( $link ); ?>" class="ais-top__name"><?php echo esc_html( $name ); ?></a>
                                <span class="ais-top__score"><?php echo esc_html( $score ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php else : ?>
                    <div class="ais-muted"><?php echo esc_html__( 'Nu există încă date suficiente.', 'ai-suite' ); ?></div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
(function(){
    if (!window.fetch) { return; }

    // Lazy load Chart.js only on dashboard.
    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
    s.onload = function(){
        fetch('<?php echo esc_url( admin_url( 'admin-ajax.php?action=ai_suite_kpi_data&nonce=' . wp_create_nonce( 'ai_suite_nonce' ) ) ); ?>')
            .then(function(r){ return r.json(); })
            .then(function(data){
                if (!data || !data.success || !data.data) { return; }
                var ctx = document.getElementById('ai-kpi-chart');
                if (!ctx) { return; }
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.data.labels || [],
                        datasets: [{
                            label: '<?php echo esc_js( __( 'Aplicații', 'ai-suite' ) ); ?>',
                            data: data.data.values || [],
                            fill: true,
                            tension: 0.35
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true },
                            x: { grid: { display: false } }
                        }
                    }
                });
            })
            .catch(function(){});
    };
    document.head.appendChild(s);
})();
</script>
