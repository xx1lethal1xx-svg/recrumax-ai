<?php
/**
 * AI Suite – Copilot AI (ChatGPT) – Admin + Portal Companie
 *
 * ADD-ONLY module. Safe-mode friendly: if OpenAI is unavailable, UI degrades gracefully.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! defined( 'AI_SUITE_COPILOT_USERMETA_PREFIX' ) ) {
    define( 'AI_SUITE_COPILOT_USERMETA_PREFIX', '_aisuite_copilot_chat_' ); // per user + company id
}
if ( ! defined( 'AI_SUITE_COPILOT_COMPANYMETA_KEY' ) ) {
    define( 'AI_SUITE_COPILOT_COMPANYMETA_KEY', '_aisuite_copilot_chat' ); // shared per company (portal)
}

if ( ! function_exists( 'aisuite_copilot_limit_messages' ) ) {
    function aisuite_copilot_limit_messages( $messages, $max = 20 ) {
        if ( ! is_array( $messages ) ) { return array(); }
        $max = max( 4, (int) $max );
        if ( count( $messages ) <= $max ) { return $messages; }
        return array_slice( $messages, -$max );
    }
}

if ( ! function_exists( 'aisuite_copilot_sanitize_messages' ) ) {
    function aisuite_copilot_sanitize_messages( $messages ) {
        if ( ! is_array( $messages ) ) { return array(); }
        $out = array();
        foreach ( $messages as $m ) {
            if ( ! is_array( $m ) ) { continue; }
            $role = isset( $m['role'] ) ? (string) $m['role'] : '';
            $content = isset( $m['content'] ) ? (string) $m['content'] : '';
            if ( ! in_array( $role, array( 'system', 'user', 'assistant' ), true ) ) { continue; }
            $content = wp_strip_all_tags( $content );
            $content = trim( $content );
            if ( $content === '' ) { continue; }
            $out[] = array( 'role' => $role, 'content' => $content );
        }
        return $out;
    }
}

if ( ! function_exists( 'aisuite_copilot_company_context' ) ) {
    function aisuite_copilot_company_context( $company_id, $include_pii = false ) {
        $company_id = absint( $company_id );
        if ( ! $company_id ) { return array(); }

        $title = get_the_title( $company_id );
        if ( ! $title ) { $title = '#' . $company_id; }

        $jobs_count = (int) wp_count_posts( 'ai_suite_job' )->publish;
        // Try to count company-scoped jobs/applications if meta exists.
        $company_jobs = 0;
        $company_apps = 0;

        // Jobs by company meta (best-effort).
        $q1 = new WP_Query( array(
            'post_type'      => 'ai_suite_job',
            'post_status'    => 'publish',
            'posts_per_page' => 5,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => '_job_company_id',
                    'value' => $company_id,
                    'compare' => '=',
                ),
            ),
        ) );
        $company_jobs = (int) $q1->found_posts;
        $job_ids = is_array( $q1->posts ) ? $q1->posts : array();

        // Applications by meta (best-effort).
        $q2 = new WP_Query( array(
            'post_type'      => 'ai_suite_application',
            'post_status'    => array( 'publish', 'private' ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => '_application_company_id',
                    'value' => $company_id,
                    'compare' => '=',
                ),
            ),
        ) );
        $company_apps = (int) $q2->found_posts;

        $recent_jobs = array();
        foreach ( $job_ids as $jid ) {
            $t = get_the_title( $jid );
            if ( $t ) { $recent_jobs[] = $t; }
        }

        $ctx = array(
            'company_id' => $company_id,
            'company'    => $title,
            'company_jobs' => $company_jobs,
            'company_applications' => $company_apps,
            'recent_jobs' => $recent_jobs,
        );

        // By default we do NOT include PII.
        if ( $include_pii ) {
            // Still best-effort minimal: never include emails/phones automatically.
            $ctx['pii_note'] = 'PII inclusion requested, but the system will avoid exposing emails/phones by default.';
        }

        return $ctx;
    }
}

if ( ! function_exists( 'aisuite_copilot_system_message' ) ) {
    function aisuite_copilot_system_message( $company_id = 0, $include_pii = false, $scope = 'admin' ) {
        $ctx = aisuite_copilot_company_context( $company_id, $include_pii );
        $rules = array(
            'Ești Copilot AI pentru o platformă de recrutare (RecruMax / AI Suite).',
            'Răspunde în limba română, concis, practic, orientat pe acțiune.',
            'Nu inventa date. Dacă lipsesc informații, pune întrebări scurte.',
            'Nu divulga date personale (email/telefon/CNP). Dacă utilizatorul cere PII, refuză politicos.',
            'Decizia finală aparține utilizatorului (human-in-the-loop).',
        );
        $meta = array();
        if ( ! empty( $ctx['company'] ) ) {
            $meta[] = 'Context companie: ' . $ctx['company'] . ' (ID ' . (int) $ctx['company_id'] . ').';
            $meta[] = 'Joburi (companie): ' . (int) $ctx['company_jobs'] . '; Aplicații (companie): ' . (int) $ctx['company_applications'] . '.';
            if ( ! empty( $ctx['recent_jobs'] ) ) {
                $meta[] = 'Joburi recente: ' . implode( ', ', array_slice( $ctx['recent_jobs'], 0, 5 ) ) . '.';
            }
        }
        $meta[] = 'Scope: ' . (string) $scope . '.';

        $content = implode( "\n", array_merge( $rules, array( '' ), $meta ) );
        return array( 'role' => 'system', 'content' => $content );
    }
}

if ( ! function_exists( 'aisuite_copilot_chat_completion' ) ) {
    function aisuite_copilot_chat_completion( array $messages, $max_tokens = 500, $temperature = 0.2 ) {
        $settings = function_exists( 'aisuite_get_settings' ) ? aisuite_get_settings() : array();
        $model = (string) ( $settings['openai_model'] ?? 'gpt-4.1-mini' );

        $payload = array(
            'model' => $model,
            'temperature' => (float) $temperature,
            'max_tokens' => max( 64, (int) $max_tokens ),
            'messages' => $messages,
        );

        $res = function_exists( 'ai_suite_openai_request' ) ? ai_suite_openai_request( $payload, 35 ) : array( 'ok' => false, 'status' => 0, 'body' => array(), 'error' => 'OpenAI helper missing.' );
        if ( empty( $res['ok'] ) ) {
            return array(
                'ok' => false,
                'text' => '',
                'status' => (int) ( $res['status'] ?? 0 ),
                'error' => (string) ( $res['error'] ?? 'Eroare necunoscută' ),
                'raw' => is_array( $res['body'] ) ? $res['body'] : array(),
            );
        }
        $data = (array) $res['body'];
        $text = '';
        if ( ! empty( $data['choices'][0]['message']['content'] ) ) {
            $text = (string) $data['choices'][0]['message']['content'];
        } elseif ( ! empty( $data['choices'][0]['text'] ) ) {
            $text = (string) $data['choices'][0]['text'];
        }
        $text = trim( $text );
        return array( 'ok' => true, 'text' => $text, 'status' => 200, 'error' => '', 'raw' => $data );
    }
}

if ( ! function_exists( 'aisuite_copilot_user_meta_key' ) ) {
    function aisuite_copilot_user_meta_key( $company_id ) {
        return AI_SUITE_COPILOT_USERMETA_PREFIX . absint( $company_id );
    }
}

if ( ! function_exists( 'aisuite_copilot_get_user_chat' ) ) {
    function aisuite_copilot_get_user_chat( $company_id, $user_id = 0 ) {
        $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
        $key = aisuite_copilot_user_meta_key( $company_id );
        $chat = get_user_meta( $user_id, $key, true );
        return is_array( $chat ) ? $chat : array();
    }
}
if ( ! function_exists( 'aisuite_copilot_set_user_chat' ) ) {
    function aisuite_copilot_set_user_chat( $company_id, $chat, $user_id = 0 ) {
        $user_id = $user_id ? absint( $user_id ) : get_current_user_id();
        $key = aisuite_copilot_user_meta_key( $company_id );
        update_user_meta( $user_id, $key, is_array( $chat ) ? $chat : array() );
        return true;
    }
}

if ( ! function_exists( 'aisuite_copilot_get_company_chat' ) ) {
    function aisuite_copilot_get_company_chat( $company_id ) {
        $company_id = absint( $company_id );
        $chat = get_post_meta( $company_id, AI_SUITE_COPILOT_COMPANYMETA_KEY, true );
        return is_array( $chat ) ? $chat : array();
    }
}
if ( ! function_exists( 'aisuite_copilot_set_company_chat' ) ) {
    function aisuite_copilot_set_company_chat( $company_id, $chat ) {
        $company_id = absint( $company_id );
        update_post_meta( $company_id, AI_SUITE_COPILOT_COMPANYMETA_KEY, is_array( $chat ) ? $chat : array() );
        return true;
    }
}

/**
 * Portal tab injection (company).
 */
if ( ! function_exists( 'aisuite_copilot_add_company_portal_tab' ) ) {
    function aisuite_copilot_add_company_portal_tab( $tabs, $company_id ) {
        if ( ! is_array( $tabs ) ) { $tabs = array(); }
        $label = __( 'Copilot AI', 'ai-suite' );
        if ( function_exists( 'ai_suite_company_has_feature' ) && ! ai_suite_company_has_feature( $company_id, 'copilot' ) ) {
            $label = __( 'Copilot AI 🔒', 'ai-suite' );
        }
        $tabs['copilot'] = $label;
        return $tabs;
    }
    add_filter( 'ai_suite_company_portal_tabs', 'aisuite_copilot_add_company_portal_tab', 50, 2 );
}

if ( ! function_exists( 'aisuite_copilot_render_company_portal_pane' ) ) {
    function aisuite_copilot_render_company_portal_pane( $company_id ) {
        $company_id = absint( $company_id );
        if ( ! $company_id ) { return; }

        $has_copilot = true;
        if ( function_exists( 'ai_suite_company_has_feature' ) ) {
            $has_copilot = (bool) ai_suite_company_has_feature( $company_id, 'copilot' );
        }
        if ( ! $has_copilot ) {
            echo '<section class="ais-pane" data-ais-pane="copilot">';
            echo '<div class="ais-card">';
            echo '<div class="ais-card-head">';
            echo '<h3 class="ais-card-title">' . esc_html__( 'Copilot AI (blocat)', 'ai-suite' ) . '</h3>';
            echo '<div class="ais-muted">' . esc_html__( 'Copilot AI este disponibil doar în planul PRO/Enterprise. Poți face upgrade din tabul Abonament.', 'ai-suite' ) . '</div>';
            echo '</div>';
            echo '<div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">';
            echo '<button type="button" class="ais-btn ais-btn-primary" onclick="try{document.querySelector(\'.ais-tab[data-ais-tab=\\\"billing\\\"]\')?.click();}catch(e){}">' . esc_html__( 'Mergi la Abonament', 'ai-suite' ) . '</button>';
            echo '</div>';
            echo '</div>';
            echo '</section>';
            return;
        }


        echo '<section class="ais-pane" data-ais-pane="copilot">';
        echo '<div class="ais-card">';
        echo '<div class="ais-flex" style="gap:12px;align-items:center;justify-content:space-between;">';
        echo '<div><div class="ais-pill">' . esc_html__( 'Copilot AI', 'ai-suite' ) . '</div>';
        echo '<h3 class="ais-card-title" style="margin-top:10px;">' . esc_html__( 'Asistent AI pentru compania ta', 'ai-suite' ) . '</h3>';
        echo '<div class="ais-muted" style="margin-top:6px;">' . esc_html__( 'Poți cere: anunțuri job, mesaje către candidați, shortlist, plan interviu.', 'ai-suite' ) . '</div></div>';
        echo '<label style="display:flex;gap:8px;align-items:center;">';
        echo '<input type="checkbox" id="ais-portal-copilot-include-pii" value="1" />';
        echo '<span class="ais-muted">' . esc_html__( 'Include date personale în context (NU recomandat)', 'ai-suite' ) . '</span>';
        echo '</label>';
        echo '</div>';

        echo '<hr style="margin:16px 0;border:0;border-top:1px solid rgba(255,255,255,0.08);" />';

        echo '<div id="ais-portal-copilot-chat" class="ais-copilot-chat" aria-live="polite"></div>';
        echo '<div class="ais-flex" style="gap:10px;margin-top:12px;align-items:flex-start;">';
        echo '<textarea id="ais-portal-copilot-input" class="ais-input" rows="3" placeholder="' . esc_attr__( 'Scrie o cerere…', 'ai-suite' ) . '"></textarea>';
        echo '<div style="display:flex;flex-direction:column;gap:8px;">';
        echo '<button type="button" class="ais-btn ais-btn-primary" id="ais-portal-copilot-send">' . esc_html__( 'Trimite', 'ai-suite' ) . '</button>';
        echo '<button type="button" class="ais-btn" id="ais-portal-copilot-clear">' . esc_html__( 'Șterge conversația', 'ai-suite' ) . '</button>';
        echo '</div></div>';
        echo '<div id="ais-portal-copilot-status" class="ais-muted" style="margin-top:10px;"></div>';
        echo '</div>';
        echo '</section>';
    }
    add_action( 'ai_suite_company_portal_render_panes', 'aisuite_copilot_render_company_portal_pane', 50, 1 );
}
