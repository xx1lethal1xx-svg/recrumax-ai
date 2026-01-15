<?php
/**
 * AI Suite – Aplicații PRO (v1.5)
 *
 * Funcții:
 * - Validări stricte pentru formularul de aplicare
 * - Honeypot anti-spam + limitare viteză (lock)
 * - Upload CV securizat (PDF/DOC/DOCX) cu limită MB
 * - Salvare candidat + aplicație (CPT) cu meta compatibile v1.4.x
 * - Email notificare admin + confirmare candidat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'ai_suite_app_statuses' ) ) {
    /**
     * Statusuri aplicații (cheie => etichetă).
     */
    function ai_suite_app_statuses() {
        return array(
            'nou'        => __( 'Nou', 'ai-suite' ),
            'in_analiza' => __( 'În analiză', 'ai-suite' ),
            'interviu'   => __( 'Interviu', 'ai-suite' ),
            'respins'    => __( 'Respins', 'ai-suite' ),
            'acceptat'   => __( 'Acceptat', 'ai-suite' ),
        );
    }
}

if ( ! function_exists( 'ai_suite_get_admin_email_target' ) ) {
    function ai_suite_get_admin_email_target() {
        $email = '';
        if ( function_exists( 'aisuite_get_settings' ) ) {
            $settings = (array) aisuite_get_settings();
            if ( ! empty( $settings['notificari_admin_email'] ) ) {
                $email = sanitize_email( $settings['notificari_admin_email'] );
            }
        }
        if ( ! $email ) {
            $email = get_option( 'admin_email' );
        }
        return $email;
    }
}

if ( ! function_exists( 'ai_suite_get_upload_limit_mb' ) ) {
    function ai_suite_get_upload_limit_mb() {
        $mb = 8;
        if ( function_exists( 'aisuite_get_settings' ) ) {
            $settings = (array) aisuite_get_settings();
            if ( isset( $settings['limita_upload_mb'] ) ) {
                $mb = absint( $settings['limita_upload_mb'] );
            }
        }
        if ( $mb < 1 ) {
            $mb = 1;
        }
        if ( $mb > 25 ) {
            $mb = 25;
        }
        return $mb;
    }
}

if ( ! function_exists( 'ai_suite_get_setting_value' ) ) {
    /**
     * Citește o setare din AI Suite (cu fallback sigur).
     */
    function ai_suite_get_setting_value( $key, $default = '' ) {
        $settings = array();
        if ( function_exists( 'aisuite_get_settings' ) ) {
            $settings = (array) aisuite_get_settings();
        }
        if ( isset( $settings[ $key ] ) && $settings[ $key ] !== '' ) {
            return $settings[ $key ];
        }
        return $default;
    }
}

if ( ! function_exists( 'ai_suite_render_template' ) ) {
    /**
     * Template simplu cu token-uri: {CANDIDATE_NAME}, {JOB_TITLE}, {EMAIL}, {PHONE}, {MESSAGE}, {CV_URL}, {ADMIN_URL}
     */
    function ai_suite_render_template( $tpl, array $vars ) {
        $search = array();
        $replace = array();
        foreach ( $vars as $k => $v ) {
            $search[]  = '{' . strtoupper( $k ) . '}';
            $replace[] = (string) $v;
        }
        return str_replace( $search, $replace, (string) $tpl );
    }
}

if ( ! function_exists( 'ai_suite_application_add_timeline' ) ) {
    /**
     * Timeline internă aplicație.
     */
    function ai_suite_application_add_timeline( $application_id, $event, array $data = array() ) {
        $application_id = absint( $application_id );
        if ( ! $application_id ) {
            return;
        }
        $timeline = get_post_meta( $application_id, '_application_timeline', true );
        if ( ! is_array( $timeline ) ) {
            $timeline = array();
        }
        $timeline[] = array(
            'time'  => time(),
            'event' => sanitize_text_field( $event ),
            'data'  => $data,
            'user'  => is_user_logged_in() ? get_current_user_id() : 0,
        );
        update_post_meta( $application_id, '_application_timeline', $timeline );
    }
}

if ( ! function_exists( 'ai_suite_rate_limit_ok' ) ) {
    /**
     * Limitare simplă (lock) pe IP + cheie.
     */
    function ai_suite_rate_limit_ok( $key, $ttl = 8 ) {
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
        $lock_key = 'ai_suite_lock_' . md5( (string) $key . '|' . $ip );
        if ( get_transient( $lock_key ) ) {
            return false;
        }
        set_transient( $lock_key, 1, (int) $ttl );
        return true;
    }
}

if ( ! function_exists( 'ai_suite_handle_application_submit' ) ) {
    /**
     * Procesează aplicarea: validează, încarcă CV, creează candidat + aplicație, trimite emailuri.
     *
     * @return array{ok:bool,message:string}
     */
    function ai_suite_handle_application_submit() {
        // Nonce
        if ( empty( $_POST['ai_suite_apply_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ai_suite_apply_nonce'] ) ), 'ai_suite_submit_application' ) ) {
            return array( 'ok' => false, 'message' => __( 'Sesiune invalidă. Reîncarcă pagina și încearcă din nou.', 'ai-suite' ) );
        }

        // Honeypot anti-spam
        if ( ! empty( $_POST['website'] ) ) {
            return array( 'ok' => false, 'message' => __( 'Cerere respinsă.', 'ai-suite' ) );
        }

        // Rate limit
        if ( ! ai_suite_rate_limit_ok( 'apply_form', 8 ) ) {
            return array( 'ok' => false, 'message' => __( 'Prea multe încercări. Încearcă din nou în câteva secunde.', 'ai-suite' ) );
        }

        $job_id = isset( $_POST['job_id'] ) ? absint( wp_unslash( $_POST['job_id'] ) ) : 0;
        if ( ! $job_id || 'rmax_job' !== get_post_type( $job_id ) ) {
            return array( 'ok' => false, 'message' => __( 'Job invalid. Te rog reîncarcă pagina.', 'ai-suite' ) );
        }

        $name    = isset( $_POST['candidate_name'] ) ? sanitize_text_field( wp_unslash( $_POST['candidate_name'] ) ) : '';
        $email   = isset( $_POST['candidate_email'] ) ? sanitize_email( wp_unslash( $_POST['candidate_email'] ) ) : '';
        $phone   = isset( $_POST['candidate_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['candidate_phone'] ) ) : '';
        $message = isset( $_POST['candidate_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['candidate_message'] ) ) : '';

        if ( mb_strlen( $name ) < 3 ) {
            return array( 'ok' => false, 'message' => __( 'Te rog completează numele complet.', 'ai-suite' ) );
        }
        if ( ! is_email( $email ) ) {
            return array( 'ok' => false, 'message' => __( 'Te rog completează un email valid.', 'ai-suite' ) );
        }
        if ( mb_strlen( $phone ) < 6 ) {
            return array( 'ok' => false, 'message' => __( 'Te rog completează un număr de telefon valid.', 'ai-suite' ) );
        }

        // Upload CV
        if ( empty( $_FILES['candidate_cv'] ) || empty( $_FILES['candidate_cv']['name'] ) ) {
            return array( 'ok' => false, 'message' => __( 'Te rog atașează CV-ul.', 'ai-suite' ) );
        }

        $limit_mb  = ai_suite_get_upload_limit_mb();
        $max_bytes = $limit_mb * 1024 * 1024;
        $size      = isset( $_FILES['candidate_cv']['size'] ) ? (int) $_FILES['candidate_cv']['size'] : 0;
        if ( $size > $max_bytes ) {
            return array( 'ok' => false, 'message' => sprintf( __( 'CV-ul este prea mare. Limită: %d MB.', 'ai-suite' ), $limit_mb ) );
        }

        $allowed_ext = array( 'pdf', 'doc', 'docx' );
        $ext = strtolower( (string) pathinfo( (string) $_FILES['candidate_cv']['name'], PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, $allowed_ext, true ) ) {
            return array( 'ok' => false, 'message' => __( 'Format CV neacceptat. Acceptăm: PDF, DOC, DOCX.', 'ai-suite' ) );
        }

        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $upload = wp_handle_upload( $_FILES['candidate_cv'], array( 'test_form' => false ) );
        if ( isset( $upload['error'] ) ) {
            if ( function_exists( 'aisuite_log' ) ) {
                aisuite_log( 'warning', __( 'Eroare upload CV', 'ai-suite' ), array( 'error' => (string) $upload['error'] ) );
            }
            return array( 'ok' => false, 'message' => __( 'Nu am putut încărca CV-ul. Încearcă din nou.', 'ai-suite' ) );
        }

        $filename = isset( $upload['file'] ) ? (string) $upload['file'] : '';
        if ( ! $filename ) {
            return array( 'ok' => false, 'message' => __( 'Nu am putut încărca CV-ul. Încearcă din nou.', 'ai-suite' ) );
        }

        // Creează attachment
        $filetype = wp_check_filetype( basename( $filename ), null );
        $attachment = array(
            'post_mime_type' => $filetype['type'],
            'post_title'     => sanitize_file_name( basename( $filename ) ),
            'post_content'   => '',
            'post_status'    => 'inherit',
        );
        $cv_id = wp_insert_attachment( $attachment, $filename );
        if ( $cv_id && ! is_wp_error( $cv_id ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            wp_update_attachment_metadata( $cv_id, wp_generate_attachment_metadata( $cv_id, $filename ) );
        } else {
            $cv_id = 0;
        }

        // Deduplicare candidat după email
        $candidate_id = 0;
        $existing = get_posts( array(
            'post_type'      => 'rmax_candidate',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'   => '_candidate_email',
                    'value' => $email,
                ),
            ),
        ) );
        if ( ! empty( $existing ) && ! empty( $existing[0]->ID ) ) {
            $candidate_id = (int) $existing[0]->ID;
        } else {
            $candidate_id = wp_insert_post( array(
                'post_type'   => 'rmax_candidate',
                'post_title'  => $name,
                'post_status' => 'publish',
                'post_author' => 0,
            ), true );
            if ( is_wp_error( $candidate_id ) || ! $candidate_id ) {
                if ( function_exists( 'aisuite_log' ) ) {
                    aisuite_log( 'warning', __( 'Eroare creare candidat', 'ai-suite' ), array( 'error' => is_wp_error( $candidate_id ) ? $candidate_id->get_error_message() : 'unknown' ) );
                }
                return array( 'ok' => false, 'message' => __( 'Eroare internă. Te rog încearcă din nou.', 'ai-suite' ) );
            }
        }

        // Actualizează meta candidat
        update_post_meta( $candidate_id, '_candidate_email', $email );
        update_post_meta( $candidate_id, '_candidate_phone', $phone );
        if ( $cv_id ) {
            update_post_meta( $candidate_id, '_candidate_cv', $cv_id );
        }

        // Creează aplicație
        $job_title = get_the_title( $job_id );
        $application_id = wp_insert_post( array(
            'post_type'   => 'rmax_application',
            'post_title'  => $name . ' – ' . $job_title,
            'post_status' => 'publish',
            'post_author' => 0,
        ), true );

        if ( is_wp_error( $application_id ) || ! $application_id ) {
            if ( function_exists( 'aisuite_log' ) ) {
                aisuite_log( 'warning', __( 'Eroare creare aplicație', 'ai-suite' ), array( 'error' => is_wp_error( $application_id ) ? $application_id->get_error_message() : 'unknown' ) );
            }
            return array( 'ok' => false, 'message' => __( 'Eroare internă. Te rog încearcă din nou.', 'ai-suite' ) );
        }

        update_post_meta( $application_id, '_application_candidate_id', $candidate_id );
        update_post_meta( $application_id, '_application_job_id', $job_id );
        update_post_meta( $application_id, '_application_status', 'nou' );
        update_post_meta( $application_id, '_application_message', $message );
        if ( $cv_id ) {
            update_post_meta( $application_id, '_application_cv', $cv_id );
        }

        // v1.9.2: leagă aplicația de userul autentificat (dacă există) + compania jobului
        if ( is_user_logged_in() ) {
            update_post_meta( $application_id, '_application_user_id', get_current_user_id() );
        }
        $job_company_user = absint( get_post_meta( $job_id, '_job_company_user_id', true ) );
        $job_company_id   = absint( get_post_meta( $job_id, '_job_company_id', true ) );
        if ( $job_company_user ) {
            update_post_meta( $application_id, '_application_company_user_id', $job_company_user );
        }
        if ( $job_company_id ) {
            update_post_meta( $application_id, '_application_company_id', $job_company_id );
        }

        // v1.9.2: Auto-enqueue AI scoring + sumar (asincron)
        $settings = function_exists( 'aisuite_get_settings' ) ? (array) aisuite_get_settings() : array();
        $auto_score = isset( $settings['ai_auto_score_on_apply'] ) ? (int) $settings['ai_auto_score_on_apply'] === 1 : true;
        if ( $auto_score && function_exists( 'ai_suite_queue_enqueue' ) ) {
            ai_suite_queue_enqueue( 'score_application', array(
                'application_id' => (int) $application_id,
            ), 5 );

            $auto_sum = isset( $settings['ai_auto_summary_on_apply'] ) ? (int) $settings['ai_auto_summary_on_apply'] === 1 : true;
            if ( $auto_sum ) {
                ai_suite_queue_enqueue( 'summarize_candidate', array(
                    'candidate_id' => (int) $candidate_id,
                ), 9 );
            }
            update_post_meta( $application_id, '_ai_queue_enqueued', 1 );
        }

        if ( function_exists( 'aisuite_log' ) ) {
            aisuite_log( 'info', __( 'Aplicație nouă înregistrată', 'ai-suite' ), array(
                'application_id' => $application_id,
                'job_id'         => $job_id,
                'candidate_id'   => $candidate_id,
            ) );
        }

        // Timeline
        ai_suite_application_add_timeline( $application_id, 'created', array( 'status' => 'nou' ) );

        // Email admin (template editabil)
        $to_admin = ai_suite_get_admin_email_target();
        $subject_admin = sprintf( __( 'Aplicație nouă – %s', 'ai-suite' ), $job_title );
        $cv_url = $cv_id ? wp_get_attachment_url( $cv_id ) : '';
        $tpl_admin = (string) ai_suite_get_setting_value( 'email_tpl_admin_new_application', '' );
        if ( ! $tpl_admin ) {
            $tpl_admin = "A fost înregistrată o aplicație nouă.\n\n" .
                "Job: {JOB_TITLE}\n" .
                "Candidat: {CANDIDATE_NAME}\n" .
                "Email: {EMAIL}\n" .
                "Telefon: {PHONE}\n\n" .
                "Mesaj: {MESSAGE}\n\n" .
                "CV: {CV_URL}\n\n" .
                "Admin aplicații: {ADMIN_URL}\n";
        }

        $body_admin = ai_suite_render_template( $tpl_admin, array(
            'job_title'       => $job_title,
            'candidate_name'  => $name,
            'email'           => $email,
            'phone'           => $phone,
            'message'         => $message,
            'cv_url'          => $cv_url,
            'admin_url'       => admin_url( 'admin.php?page=ai-suite&tab=applications' ),
        ) );

        wp_mail( $to_admin, $subject_admin, $body_admin );

        // Email candidat (toggle + template)
        $send_cand = (int) ai_suite_get_setting_value( 'trimite_email_candidat', 1 );
        $subject_cand = sprintf( __( 'Confirmare aplicare – %s', 'ai-suite' ), $job_title );
        $body_cand = "Salut, {$name}!\n\n" .
            "Îți confirmăm că am primit aplicarea ta pentru jobul: {$job_title}.\n" .
            "Revenim către tine după analizare.\n\n" .
            "Mulțumim!\n";

        $tpl_cand = (string) ai_suite_get_setting_value( 'email_tpl_candidate_confirmation', '' );
        if ( $tpl_cand ) {
            $body_cand = ai_suite_render_template( $tpl_cand, array(
                'job_title'      => $job_title,
                'candidate_name' => $name,
                'email'          => $email,
                'phone'          => $phone,
                'message'        => $message,
                'cv_url'         => $cv_url,
            ) );
        }

        if ( $send_cand && is_email( $email ) ) {
            wp_mail( $email, $subject_cand, $body_cand );
        }

        return array( 'ok' => true, 'message' => __( 'Aplicarea ta a fost trimisă cu succes. Mulțumim!', 'ai-suite' ) );
    }
}

// === v1.7.6 – Aplicații PRO+: status flow + audit (ADD-ONLY) ===

if ( ! function_exists( 'ai_suite_application_status_flow' ) ) {
    /**
     * Definește tranzițiile permise între statusuri.
     *
     * @return array<string, string[]> from => [to1,to2]
     */
    function ai_suite_application_status_flow() {
        $flow = array(
            'nou'        => array( 'in_analiza', 'respins' ),
            'in_analiza' => array( 'interviu', 'respins', 'acceptat' ),
            'interviu'   => array( 'acceptat', 'respins' ),
            // Terminale
            'respins'    => array(),
            'acceptat'   => array(),
        );

        /**
         * Permite proiectului să extindă flow-ul.
         */
        return (array) apply_filters( 'ai_suite_application_status_flow', $flow );
    }
}

if ( ! function_exists( 'ai_suite_application_can_transition' ) ) {
    /**
     * Verifică dacă o tranziție de status este permisă.
     *
     * Fail-safe: dacă nu recunoaștem statusuri (custom), permitem tranziția.
     */
    function ai_suite_application_can_transition( $from, $to ) {
        $from = sanitize_key( (string) $from );
        $to   = sanitize_key( (string) $to );

        if ( $to === '' ) {
            return false;
        }
        if ( $from === $to ) {
            return true;
        }

        $flow = ai_suite_application_status_flow();

        // Dacă statusurile nu sunt în flow, nu blocăm (compatibilitate).
        if ( ! isset( $flow[ $from ] ) && ! isset( $flow[ $to ] ) ) {
            return true;
        }
        if ( ! isset( $flow[ $from ] ) ) {
            return true;
        }

        $allowed = (array) $flow[ $from ];
        return in_array( $to, $allowed, true );
    }
}

if ( ! function_exists( 'ai_suite_application_record_status_change' ) ) {
    /**
     * Înregistrează schimbarea de status ca audit log.
     */
    function ai_suite_application_record_status_change( $application_id, $from, $to, $context = 'manual', $meta = array() ) {
        $application_id = absint( $application_id );
        if ( ! $application_id ) {
            return;
        }

        $history = get_post_meta( $application_id, '_application_status_history', true );
        if ( ! is_array( $history ) ) {
            $history = array();
        }

        $entry = array(
            'id'      => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : (string) ( time() . '-' . wp_rand( 1000, 9999 ) ),
            'time'    => time(),
            'from'    => sanitize_key( (string) $from ),
            'to'      => sanitize_key( (string) $to ),
            'user'    => is_user_logged_in() ? get_current_user_id() : 0,
            'context' => sanitize_key( (string) $context ),
            'meta'    => is_array( $meta ) ? $meta : array(),
        );

        $history[] = $entry;
        update_post_meta( $application_id, '_application_status_history', $history );

        // Timeline: mesaj human-readable.
        if ( function_exists( 'ai_suite_app_statuses' ) ) {
            $labels = (array) ai_suite_app_statuses();
            $from_l = isset( $labels[ $from ] ) ? $labels[ $from ] : $from;
            $to_l   = isset( $labels[ $to ] ) ? $labels[ $to ] : $to;
            if ( function_exists( 'ai_suite_application_add_timeline' ) ) {
                ai_suite_application_add_timeline( $application_id, 'status_changed', array(
                    'from'    => $from,
                    'to'      => $to,
                    'context' => $context,
                    'label'   => sprintf( __( 'Status schimbat: %1$s → %2$s', 'ai-suite' ), $from_l, $to_l ),
                ) );
            }
        }

        /**
         * Hook pregătit pentru AI / notificări.
         */
        do_action( 'aisuite_application_status_changed', $application_id, $from, $to, $context, $entry );
    }
}

if ( ! function_exists( 'ai_suite_application_add_note' ) ) {
    /**
     * Adaugă notiță internă (cu audit).
     */
    function ai_suite_application_add_note( $application_id, $text, $meta = array() ) {
        $application_id = absint( $application_id );
        $text = trim( (string) $text );
        if ( ! $application_id || $text === '' ) {
            return false;
        }

        $notes = get_post_meta( $application_id, '_application_notes', true );
        if ( ! is_array( $notes ) ) {
            $notes = array();
        }

        $note = array(
            'id'   => function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : (string) ( time() . '-' . wp_rand( 1000, 9999 ) ),
            'time' => time(),
            'text' => $text,
            'user' => is_user_logged_in() ? get_current_user_id() : 0,
            'meta' => is_array( $meta ) ? $meta : array(),
        );

        $notes[] = $note;
        update_post_meta( $application_id, '_application_notes', $notes );

        if ( function_exists( 'ai_suite_application_add_timeline' ) ) {
            ai_suite_application_add_timeline( $application_id, 'note_added', array( 'note_id' => $note['id'] ) );
        }

        do_action( 'aisuite_application_note_added', $application_id, $note );

        return true;
    }
}

if ( ! function_exists( 'ai_suite_application_delete_note' ) ) {
    /**
     * Șterge notiță internă după ID (admin only).
     */
    function ai_suite_application_delete_note( $application_id, $note_id ) {
        $application_id = absint( $application_id );
        $note_id = (string) $note_id;
        if ( ! $application_id || $note_id === '' ) {
            return false;
        }

        $notes = get_post_meta( $application_id, '_application_notes', true );
        if ( ! is_array( $notes ) || empty( $notes ) ) {
            return false;
        }

        $new = array();
        $deleted = false;
        foreach ( $notes as $n ) {
            $nid = isset( $n['id'] ) ? (string) $n['id'] : '';
            if ( $nid && hash_equals( $nid, $note_id ) ) {
                $deleted = true;
                continue;
            }
            $new[] = $n;
        }

        if ( $deleted ) {
            update_post_meta( $application_id, '_application_notes', $new );
            if ( function_exists( 'ai_suite_application_add_timeline' ) ) {
                ai_suite_application_add_timeline( $application_id, 'note_deleted', array( 'note_id' => $note_id ) );
            }
        }

        return $deleted;
    }
}

// === v1.7.6 – Aplicații PRO+: status flow + audit (ADD-ONLY) ===

if ( ! function_exists( 'ai_suite_application_status_flow' ) ) {
    /**
     * Definește tranzițiile permise între statusuri.
     *
     * @return array<string, string[]> from => [to...]
     */
    function ai_suite_application_status_flow() {
        $flow = array(
            'nou'        => array( 'in_analiza', 'respins' ),
            'in_analiza' => array( 'interviu', 'respins' ),
            'interviu'   => array( 'acceptat', 'respins' ),
            'acceptat'   => array(),
            'respins'    => array(),
            // fallback for unknown/legacy statuses
            '*'          => array( 'nou', 'in_analiza', 'interviu', 'respins', 'acceptat' ),
        );

        /**
         * Filtru: ai_suite_application_status_flow
         */
        return (array) apply_filters( 'ai_suite_application_status_flow', $flow );
    }
}

if ( ! function_exists( 'ai_suite_application_can_transition' ) ) {
    /**
     * Verifică dacă tranziția de status este permisă.
     */
    function ai_suite_application_can_transition( $from, $to ) {
        $from = sanitize_key( (string) $from );
        $to   = sanitize_key( (string) $to );

        if ( $from === $to ) {
            return true;
        }

        $statuses = function_exists( 'ai_suite_app_statuses' ) ? (array) ai_suite_app_statuses() : array();
        if ( $to && ! empty( $statuses ) && ! isset( $statuses[ $to ] ) ) {
            // status necunoscut
            return false;
        }

        $flow = (array) ai_suite_application_status_flow();
        if ( isset( $flow[ $from ] ) ) {
            return in_array( $to, (array) $flow[ $from ], true );
        }

        // dacă statusul vechi nu este recunoscut, permitem doar dacă este în fallback.
        return isset( $flow['*'] ) ? in_array( $to, (array) $flow['*'], true ) : true;
    }
}

if ( ! function_exists( 'ai_suite_application_record_status_change' ) ) {
    /**
     * Scrie audit log pentru schimbarea statusului.
     */
    function ai_suite_application_record_status_change( $application_id, $from, $to, $context = 'manual' ) {
        $application_id = absint( $application_id );
        if ( ! $application_id ) {
            return;
        }

        $history = get_post_meta( $application_id, '_application_status_history', true );
        if ( ! is_array( $history ) ) {
            $history = array();
        }

        $history[] = array(
            'id'      => wp_generate_uuid4(),
            'time'    => time(),
            'from'    => sanitize_key( (string) $from ),
            'to'      => sanitize_key( (string) $to ),
            'user'    => is_user_logged_in() ? get_current_user_id() : 0,
            'context' => sanitize_text_field( (string) $context ),
        );

        update_post_meta( $application_id, '_application_status_history', $history );

        if ( function_exists( 'ai_suite_application_add_timeline' ) ) {
            ai_suite_application_add_timeline( $application_id, 'status_changed', array(
                'from'    => sanitize_key( (string) $from ),
                'to'      => sanitize_key( (string) $to ),
                'context' => sanitize_text_field( (string) $context ),
            ) );
        }

        do_action( 'aisuite_application_status_changed', $application_id, $from, $to, $context );
    }
}

if ( ! function_exists( 'ai_suite_application_add_note_pro' ) ) {
    /**
     * Adaugă notiță internă cu audit (user/time/id).
     */
    function ai_suite_application_add_note_pro( $application_id, $note_text, $context = 'manual' ) {
        $application_id = absint( $application_id );
        $note_text      = trim( (string) $note_text );
        if ( ! $application_id || $note_text === '' ) {
            return false;
        }

        $notes = get_post_meta( $application_id, '_application_notes', true );
        if ( ! is_array( $notes ) ) {
            $notes = array();
        }

        $notes[] = array(
            'id'      => wp_generate_uuid4(),
            'time'    => time(),
            'text'    => sanitize_textarea_field( $note_text ),
            'user'    => is_user_logged_in() ? get_current_user_id() : 0,
            'context' => sanitize_text_field( (string) $context ),
        );

        update_post_meta( $application_id, '_application_notes', $notes );

        if ( function_exists( 'ai_suite_application_add_timeline' ) ) {
            ai_suite_application_add_timeline( $application_id, 'note_added', array( 'context' => sanitize_text_field( (string) $context ) ) );
        }

        do_action( 'aisuite_application_note_added', $application_id, $note_text, $context );

        return true;
    }
}

if ( ! function_exists( 'ai_suite_application_delete_note_pro' ) ) {
    /**
     * Șterge o notiță după ID (uuid).
     */
    function ai_suite_application_delete_note_pro( $application_id, $note_id ) {
        $application_id = absint( $application_id );
        $note_id        = sanitize_text_field( (string) $note_id );
        if ( ! $application_id || ! $note_id ) {
            return false;
        }

        $notes = get_post_meta( $application_id, '_application_notes', true );
        if ( ! is_array( $notes ) || empty( $notes ) ) {
            return false;
        }

        $new = array();
        $deleted = false;
        foreach ( $notes as $n ) {
            $nid = isset( $n['id'] ) ? (string) $n['id'] : '';
            if ( $nid && hash_equals( $nid, $note_id ) ) {
                $deleted = true;
                continue;
            }
            $new[] = $n;
        }

        if ( $deleted ) {
            update_post_meta( $application_id, '_application_notes', $new );
            if ( function_exists( 'ai_suite_application_add_timeline' ) ) {
                ai_suite_application_add_timeline( $application_id, 'note_deleted' );
            }
            do_action( 'aisuite_application_note_deleted', $application_id, $note_id );
        }

        return $deleted;
    }
}
