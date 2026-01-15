<?php
/**
 * Tabs definitions for AI Suite admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'ai_suite_tabs' ) ) {
    /**
     * Get list of tabs.
     *
     * @return array
     */
    function ai_suite_tabs() {
        $tabs = array(
            // Use Romanian labels throughout the admin UI.
            // NOTE: extra keys (group/icon/desc) are optional and safe for older UI.
            'dashboard'    => array( 'label' => __( 'Panou principal', 'ai-suite' ), 'view' => 'tab-dashboard.php', 'group' => __( 'Panou', 'ai-suite' ), 'icon' => 'chart-area', 'desc' => __( 'KPI, trenduri și link-uri rapide.', 'ai-suite' ) ),
            'wizard'       => array( 'label' => __( 'Asistent configurare', 'ai-suite' ), 'view' => 'tab-wizard.php', 'group' => __( 'Configurare', 'ai-suite' ), 'icon' => 'admin-tools', 'desc' => __( 'Setup rapid: pagini, meniuri, OpenAI.', 'ai-suite' ) ),

            'healthcheck'  => array( 'label' => __( 'Verificare sistem', 'ai-suite' ), 'view' => 'tab-healthcheck.php', 'group' => __( 'Sistem', 'ai-suite' ), 'icon' => 'shield', 'desc' => __( 'Teste: OpenAI, cron, permisiuni, versiuni.', 'ai-suite' ) ),
            'autorepair'  => array( 'label' => __( 'Auto-Repair AI', 'ai-suite' ), 'view' => 'tab-autorepair.php', 'group' => __( 'Sistem', 'ai-suite' ), 'icon' => 'update', 'desc' => __( 'Diagnostic + remedieri automate (self-heal) cu notificări email.', 'ai-suite' ) ),
            'logs'         => array( 'label' => __( 'Jurnal activitate', 'ai-suite' ), 'view' => 'tab-logs.php', 'group' => __( 'Sistem', 'ai-suite' ), 'icon' => 'text-page', 'desc' => __( 'Evenimente, acțiuni, erori și audit.', 'ai-suite' ) ),
            'tools'        => array( 'label' => __( 'Unelte', 'ai-suite' ), 'view' => 'tab-tools.php', 'group' => __( 'Sistem', 'ai-suite' ), 'icon' => 'admin-tools', 'desc' => __( 'Reset, reparare pagini, flush, module.', 'ai-suite' ) ),

            'bots'         => array( 'label' => __( 'Boți AI', 'ai-suite' ), 'view' => 'tab-bots.php', 'group' => __( 'AI', 'ai-suite' ), 'icon' => 'robot', 'desc' => __( 'Rulează boți, monitorizează status, debug.', 'ai-suite' ) ),
            'runs'         => array( 'label' => __( 'Rulări automate', 'ai-suite' ), 'view' => 'tab-runs.php', 'group' => __( 'AI', 'ai-suite' ), 'icon' => 'controls-repeat', 'desc' => __( 'Cron jobs, automatizări și execuții.', 'ai-suite' ) ),
            'ai_queue'     => array( 'label' => __( 'Coadă AI', 'ai-suite' ), 'view' => 'tab-ai-queue.php', 'group' => __( 'AI', 'ai-suite' ), 'icon' => 'clock', 'desc' => __( 'Worker, retry, monitorizare task-uri.', 'ai-suite' ) ),
            'copilot'      => array( 'label' => __( 'Copilot AI', 'ai-suite' ), 'view' => 'tab-copilot.php', 'group' => __( 'AI', 'ai-suite' ), 'icon' => 'format-chat', 'desc' => __( 'Chat per companie/recruiter în dashboard.', 'ai-suite' ) ),

            'jobs'         => array( 'label' => __( 'Joburi', 'ai-suite' ), 'view' => 'tab-jobs.php', 'group' => __( 'Recrutare', 'ai-suite' ), 'icon' => 'megaphone', 'desc' => __( 'Publicare, promovare, management joburi.', 'ai-suite' ) ),
            'candidates'   => array( 'label' => __( 'Candidați', 'ai-suite' ), 'view' => 'tab-candidates.php', 'group' => __( 'Recrutare', 'ai-suite' ), 'icon' => 'groups', 'desc' => __( 'Bază candidați, fișe, filtre.', 'ai-suite' ) ),
            'applications' => array( 'label' => __( 'Aplicații', 'ai-suite' ), 'view' => 'tab-applications.php', 'group' => __( 'Recrutare', 'ai-suite' ), 
            'ats_templates' => array( 'label' => __( 'Șabloane email ATS', 'ai-suite' ), 'view' => 'tab-ats-templates.php', 'group' => __( 'Recrutare', 'ai-suite' ), 'icon' => 'email', 'desc' => __( 'Șabloane pentru Bulk Email în ATS Board.', 'ai-suite' ) ),
'icon' => 'feedback', 'desc' => __( 'Pipeline, status, ATS board.', 'ai-suite' ) ),
            'companies'    => array( 'label' => __( 'Companii', 'ai-suite' ), 'view' => 'tab-companies.php', 'group' => __( 'Recrutare', 'ai-suite' ), 'icon' => 'building', 'desc' => __( 'Clienți, detalii, setări per companie.', 'ai-suite' ) ),

            'team'         => array( 'label' => __( 'Echipă internă', 'ai-suite' ), 'view' => 'tab-team.php', 'group' => __( 'Echipă', 'ai-suite' ), 'icon' => 'networking', 'desc' => __( 'Roluri, alocări companii, acces.', 'ai-suite' ) ),
            'portal'       => array( 'label' => __( 'Portal', 'ai-suite' ), 'view' => 'tab-portal.php', 'group' => __( 'Portal', 'ai-suite' ), 'icon' => 'admin-site', 'desc' => __( 'Link-uri către portaluri și job board.', 'ai-suite' ) ),

            'billing'      => array( 'label' => __( 'Abonamente', 'ai-suite' ), 'view' => 'tab-billing.php', 'group' => __( 'Monetizare', 'ai-suite' ), 'icon' => 'cart', 'desc' => __( 'Planuri, facturare, acces per client.', 'ai-suite' ) ),
            'billing_history' => array( 'label' => __( 'Istoric facturare', 'ai-suite' ), 'view' => 'tab-billing-history.php', 'group' => __( 'Monetizare', 'ai-suite' ), 'icon' => 'media-document', 'desc' => __( 'Evenimente + facturi HTML (audit complet).', 'ai-suite' ) ),
            'facebook_leads' => array( 'label' => __( 'Facebook Leads', 'ai-suite' ), 'view' => 'tab-facebook-leads.php', 'group' => __( 'Integrări', 'ai-suite' ), 'icon' => 'facebook', 'desc' => __( 'Leads, import automat, mapping câmpuri.', 'ai-suite' ) ),
            'settings'     => array( 'label' => __( 'Setări', 'ai-suite' ), 'view' => 'tab-settings.php', 'group' => __( 'Setări', 'ai-suite' ), 'icon' => 'admin-generic', 'desc' => __( 'Chei API, preferințe, limbi, opțiuni.', 'ai-suite' ) ),
        );
        return apply_filters( 'ai_suite_tabs', $tabs );
    }
}