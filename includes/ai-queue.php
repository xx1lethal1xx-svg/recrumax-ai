<?php
/**
 * AI Suite – AI Queue (v1.8.1)
 *
 * Scop:
 * - Coada asincronă pentru task-uri AI (scoring, sumar, email feedback)
 * - Worker prin WP-Cron + buton "Rulează acum" în admin
 * - Retry controlat + loguri
 *
 * IMPORTANT: Add-only / compatibil cu v1.8.0.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'AI_SUITE_QUEUE_TABLE' ) ) {
    define( 'AI_SUITE_QUEUE_TABLE', 'ai_suite_ai_queue' );
}

if ( ! function_exists( 'ai_suite_queue_table_name' ) ) {
    function ai_suite_queue_table_name() {
        global $wpdb;
        return $wpdb->prefix . AI_SUITE_QUEUE_TABLE;
    }
}

if ( ! function_exists( 'ai_suite_queue_install' ) ) {
    /**
     * Creează / upgradează tabela cozii.
     */
    function ai_suite_queue_install() {
        global $wpdb;

        $table = ai_suite_queue_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        // dbDelta
        if ( ! function_exists( 'dbDelta' ) ) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            type VARCHAR(80) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            priority INT(11) NOT NULL DEFAULT 10,
            attempts INT(11) NOT NULL DEFAULT 0,
            max_attempts INT(11) NOT NULL DEFAULT 3,
            run_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            locked_at DATETIME NULL,
            locked_by VARCHAR(64) NULL,
            payload LONGTEXT NULL,
            result LONGTEXT NULL,
            last_error TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status_run_at (status, run_at),
            KEY type_status (type, status),
            KEY priority_run_at (priority, run_at)
        ) {$charset_collate};";

        dbDelta( $sql );
    }
}

if ( ! function_exists( 'ai_suite_queue_enabled' ) ) {
    function ai_suite_queue_enabled() {
        $settings = function_exists( 'aisuite_get_settings' ) ? (array) aisuite_get_settings() : array();
        // Default: enabled.
        return isset( $settings['ai_queue_enabled'] ) ? (int) $settings['ai_queue_enabled'] === 1 : true;
    }
}

if ( ! function_exists( 'ai_suite_queue_enqueue' ) ) {
    /**
     * Enqueue un task.
     *
     * @param string $type
     * @param array  $payload
     * @param int    $priority
     * @param int|null $run_at_ts Unix timestamp (optional)
     * @param int    $max_attempts
     * @return int|false
     */
    function ai_suite_queue_enqueue( $type, array $payload, $priority = 10, $run_at_ts = null, $max_attempts = 3 ) {
        if ( ! ai_suite_queue_enabled() ) {
            return false;
        }
        global $wpdb;
        $table = ai_suite_queue_table_name();

        $type = sanitize_key( (string) $type );
        if ( $type === '' ) {
            return false;
        }

        $priority = (int) $priority;
        if ( $priority < 0 ) {
            $priority = 0;
        }

        $run_at = $run_at_ts ? gmdate( 'Y-m-d H:i:s', (int) $run_at_ts ) : current_time( 'mysql', 1 );

        $insert = array(
            'type'         => $type,
            'status'       => 'pending',
            'priority'     => $priority,
            'attempts'     => 0,
            'max_attempts' => max( 1, (int) $max_attempts ),
            'run_at'       => $run_at,
            'payload'      => wp_json_encode( $payload ),
            'created_at'   => current_time( 'mysql', 1 ),
            'updated_at'   => current_time( 'mysql', 1 ),
        );

        $ok = $wpdb->insert( $table, $insert, array( '%s','%s','%d','%d','%d','%s','%s','%s','%s' ) );
        if ( ! $ok ) {
            if ( function_exists( 'aisuite_log' ) ) {
                aisuite_log( 'warning', 'AI Queue: insert failed', array( 'type' => $type, 'db_error' => $wpdb->last_error ) );
            }
            return false;
        }
        return (int) $wpdb->insert_id;
    }
}

if ( ! function_exists( 'ai_suite_queue_list' ) ) {
    /**
     * Listează task-uri pentru UI.
     */
    function ai_suite_queue_list( $args = array() ) {
        global $wpdb;
        $table = ai_suite_queue_table_name();

        $status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
        $type   = isset( $args['type'] ) ? sanitize_key( (string) $args['type'] ) : '';
        $limit  = isset( $args['limit'] ) ? min( 200, max( 1, (int) $args['limit'] ) ) : 50;

        $where = array();
        $params = array();

        if ( $status ) {
            $where[]  = 'status = %s';
            $params[] = $status;
        }
        if ( $type ) {
            $where[]  = 'type = %s';
            $params[] = $type;
        }

        $sql = "SELECT * FROM {$table}";
        if ( $where ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }
        $sql .= ' ORDER BY status ASC, priority ASC, run_at ASC, id DESC';
        $sql .= ' LIMIT ' . (int) $limit;

        if ( $params ) {
            $sql = $wpdb->prepare( $sql, $params );
        }

        return $wpdb->get_results( $sql, ARRAY_A );
    }
}

if ( ! function_exists( 'ai_suite_queue_claim_batch' ) ) {
    /**
     * Claimează un batch de task-uri "pending".
     */
    function ai_suite_queue_claim_batch( $limit = 3 ) {
        global $wpdb;
        $table = ai_suite_queue_table_name();
        $limit = min( 20, max( 1, (int) $limit ) );

        $now_gmt = current_time( 'mysql', 1 );
        $lock_id = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : (string) ( time() . '-' . wp_rand( 1000, 9999 ) );

        // Selectăm candidate
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id FROM {$table}
                 WHERE status = 'pending'
                   AND run_at <= %s
                   AND attempts < max_attempts
                 ORDER BY priority ASC, run_at ASC, id ASC
                 LIMIT %d",
                $now_gmt,
                $limit
            ),
            ARRAY_A
        );

        if ( empty( $rows ) ) {
            return array();
        }

        $ids = array_map( 'intval', wp_list_pluck( $rows, 'id' ) );
        $ids_in = implode( ',', array_map( 'intval', $ids ) );
        if ( $ids_in === '' ) {
            return array();
        }

        // Claim: update status running pentru ids.
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table}
                 SET status='running', locked_at=%s, locked_by=%s, updated_at=%s
                 WHERE id IN ({$ids_in}) AND status='pending'",
                $now_gmt,
                $lock_id,
                $now_gmt
            )
        );

        // Read back claimed items.
        $claimed = $wpdb->get_results( "SELECT * FROM {$table} WHERE locked_by = '" . esc_sql( $lock_id ) . "' AND status='running' ORDER BY priority ASC, run_at ASC, id ASC", ARRAY_A );
        return is_array( $claimed ) ? $claimed : array();
    }
}

if ( ! function_exists( 'ai_suite_queue_fail' ) ) {
    function ai_suite_queue_fail( $id, $error_message ) {
        global $wpdb;
        $table = ai_suite_queue_table_name();
        $id = (int) $id;
        $now = current_time( 'mysql', 1 );
        $wpdb->update(
            $table,
            array(
                'status'     => 'failed',
                'attempts'   => $wpdb->get_var( $wpdb->prepare( "SELECT attempts FROM {$table} WHERE id=%d", $id ) ) + 1,
                'last_error' => (string) $error_message,
                'updated_at' => $now,
            ),
            array( 'id' => $id ),
            array( '%s','%d','%s','%s' ),
            array( '%d' )
        );
    }
}

if ( ! function_exists( 'ai_suite_queue_complete' ) ) {
    function ai_suite_queue_complete( $id, $result = null ) {
        global $wpdb;
        $table = ai_suite_queue_table_name();
        $id = (int) $id;
        $now = current_time( 'mysql', 1 );
        $wpdb->update(
            $table,
            array(
                'status'     => 'done',
                'result'     => is_null( $result ) ? null : wp_json_encode( $result ),
                'updated_at' => $now,
            ),
            array( 'id' => $id ),
            array( '%s','%s','%s' ),
            array( '%d' )
        );
    }
}

if ( ! function_exists( 'ai_suite_queue_retry' ) ) {
    function ai_suite_queue_retry( $id ) {
        global $wpdb;
        $table = ai_suite_queue_table_name();
        $id = (int) $id;
        $now = current_time( 'mysql', 1 );
        return (bool) $wpdb->update(
            $table,
            array(
                'status'     => 'pending',
                'locked_at'  => null,
                'locked_by'  => null,
                'updated_at' => $now,
            ),
            array( 'id' => $id ),
            array( '%s','%s','%s','%s' ),
            array( '%d' )
        );
    }
}

if ( ! function_exists( 'ai_suite_queue_delete' ) ) {
    function ai_suite_queue_delete( $id ) {
        global $wpdb;
        $table = ai_suite_queue_table_name();
        return (bool) $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );
    }
}

if ( ! function_exists( 'ai_suite_queue_purge' ) ) {
    /**
     * Șterge task-uri "done" mai vechi de X zile.
     */
    function ai_suite_queue_purge( $days = 14 ) {
        global $wpdb;
        $table = ai_suite_queue_table_name();
        $days = max( 1, (int) $days );
        $cut = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
        return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE status='done' AND updated_at < %s", $cut ) );
    }
}

if ( ! function_exists( 'ai_suite_queue_dispatch' ) ) {
    /**
     * Dispatcher pentru task types.
     */
    function ai_suite_queue_dispatch( $type, array $payload ) {
        $type = sanitize_key( (string) $type );

        switch ( $type ) {
            case 'score_application': {
                $app_id = isset( $payload['application_id'] ) ? absint( $payload['application_id'] ) : 0;
                if ( ! $app_id || 'rmax_application' !== get_post_type( $app_id ) ) {
                    return array( 'ok' => false, 'message' => 'Aplicație invalidă' );
                }
                $candidate_id = absint( get_post_meta( $app_id, '_application_candidate_id', true ) );
                $job_id       = absint( get_post_meta( $app_id, '_application_job_id', true ) );
                if ( ! $candidate_id || 'rmax_candidate' !== get_post_type( $candidate_id ) ) {
                    return array( 'ok' => false, 'message' => 'Candidat invalid' );
                }
                if ( ! $job_id || 'rmax_job' !== get_post_type( $job_id ) ) {
                    return array( 'ok' => false, 'message' => 'Job invalid' );
                }

                // If OpenAI isn't configured, skip gracefully (do not retry endlessly).
                $settings = function_exists( 'aisuite_get_settings' ) ? (array) aisuite_get_settings() : array();
                $openai_key = ! empty( $settings['openai_api_key'] ) ? (string) $settings['openai_api_key'] : '';
                if ( ! $openai_key ) {
                    update_post_meta( $app_id, '_ai_score', '' );
                    update_post_meta( $app_id, '_ai_score_ts', time() );
                    return array( 'ok' => true, 'skipped' => true, 'message' => 'OpenAI key missing; skipped scoring.' );
                }
                if ( ! function_exists( 'ai_suite_ai_score_candidate_for_job' ) ) {
                    return array( 'ok' => false, 'message' => 'AI indisponibil (scoring)' );
                }
                $score = ai_suite_ai_score_candidate_for_job( $candidate_id, $job_id );
                update_post_meta( $app_id, '_ai_score', $score );
                update_post_meta( $app_id, '_ai_score_ts', time() );
                if ( function_exists( 'ai_suite_application_add_timeline' ) ) {
                    ai_suite_application_add_timeline( $app_id, 'ai_score', array( 'score' => $score ) );
                }
                return array( 'ok' => true, 'score' => $score );
            }

            case 'summarize_candidate': {
                $candidate_id = isset( $payload['candidate_id'] ) ? absint( $payload['candidate_id'] ) : 0;
                if ( ! $candidate_id || 'rmax_candidate' !== get_post_type( $candidate_id ) ) {
                    return array( 'ok' => false, 'message' => 'Candidat invalid' );
                }
                if ( ! function_exists( 'ai_suite_ai_summarize_candidate' ) ) {
                    return array( 'ok' => false, 'message' => 'AI indisponibil (sumar)' );
                }

                $settings = function_exists( 'aisuite_get_settings' ) ? (array) aisuite_get_settings() : array();
                $openai_key = ! empty( $settings['openai_api_key'] ) ? (string) $settings['openai_api_key'] : '';
                if ( ! $openai_key ) {
                    update_post_meta( $candidate_id, '_ai_summary', '' );
                    update_post_meta( $candidate_id, '_ai_summary_ts', time() );
                    return array( 'ok' => true, 'skipped' => true, 'message' => 'OpenAI key missing; skipped summary.' );
                }
                $bio = (string) get_post_meta( $candidate_id, '_candidate_bio', true );
                $skills = (string) get_post_meta( $candidate_id, '_candidate_skills', true );
                $res = ai_suite_ai_summarize_candidate( $candidate_id, array( 'bio' => $bio, 'skills' => $skills ) );
                update_post_meta( $candidate_id, '_ai_summary', $res );
                update_post_meta( $candidate_id, '_ai_summary_ts', time() );
                return array( 'ok' => true, 'summary' => $res );
            }

            case 'send_status_email': {
                $app_id = isset( $payload['application_id'] ) ? absint( $payload['application_id'] ) : 0;
                $to     = isset( $payload['to'] ) ? sanitize_key( (string) $payload['to'] ) : '';
                $from   = isset( $payload['from'] ) ? sanitize_key( (string) $payload['from'] ) : '';

                if ( ! $app_id || 'rmax_application' !== get_post_type( $app_id ) ) {
                    return array( 'ok' => false, 'message' => 'Aplicație invalidă' );
                }

                $settings = function_exists( 'aisuite_get_settings' ) ? (array) aisuite_get_settings() : array();
                $enabled  = isset( $settings['ai_email_status_enabled'] ) ? (int) $settings['ai_email_status_enabled'] === 1 : true;
                if ( ! $enabled ) {
                    return array( 'ok' => true, 'message' => 'Email status dezactivat (setări)' );
                }

                $candidate_id = absint( get_post_meta( $app_id, '_application_candidate_id', true ) );
                $job_id       = absint( get_post_meta( $app_id, '_application_job_id', true ) );
                $cand_email   = $candidate_id ? (string) get_post_meta( $candidate_id, '_candidate_email', true ) : '';
                $cand_name    = $candidate_id ? (string) get_the_title( $candidate_id ) : '';
                $job_title    = $job_id ? (string) get_the_title( $job_id ) : '';

                if ( ! $cand_email || ! is_email( $cand_email ) ) {
                    return array( 'ok' => false, 'message' => 'Email candidat lipsă/invalid' );
                }

                // Note (ultimul)
                $note = '';
                $notes = get_post_meta( $app_id, '_application_notes', true );
                if ( is_array( $notes ) && ! empty( $notes ) ) {
                    $last = end( $notes );
                    if ( is_array( $last ) && ! empty( $last['text'] ) ) {
                        $note = (string) $last['text'];
                    }
                }

                // Status label
                $status_label = $to;
                if ( function_exists( 'ai_suite_app_statuses' ) ) {
                    $labels = (array) ai_suite_app_statuses();
                    if ( isset( $labels[ $to ] ) ) {
                        $status_label = (string) $labels[ $to ];
                    }
                }

                $subject = sprintf( __( 'Actualizare aplicație – %s', 'ai-suite' ), $job_title ? $job_title : __( 'Job', 'ai-suite' ) );

                // Dacă avem OpenAI key și e activ, generăm feedback scurt.
                $use_ai = ! empty( $settings['openai_api_key'] ) && ( ! isset( $settings['ai_email_use_ai'] ) || (int) $settings['ai_email_use_ai'] === 1 );
                $body = '';
                if ( $use_ai && function_exists( 'ai_suite_ai_call' ) ) {
                    $prompt = "Scrie un email scurt și politicos în limba română către candidat.\n\n" .
                        "Context:\n" .
                        "- Nume candidat: {$cand_name}\n" .
                        "- Job: {$job_title}\n" .
                        "- Status nou: {$status_label}\n" .
                        ( $note ? "- Notiță internă/feedback: {$note}\n" : '' ) .
                        "\nCerințe:\n" .
                        "- Ton profesional, prietenos\n" .
                        "- 6–12 rânduri\n" .
                        "- Include next step generic (ex: te contactăm / programăm interviu)\n" .
                        "- Fără date sensibile\n";

                    $ai = ai_suite_ai_call( $prompt, 500 );
                    if ( is_array( $ai ) && ! empty( $ai['ok'] ) && ! empty( $ai['text'] ) ) {
                        $body = (string) $ai['text'];
                        update_post_meta( $app_id, '_ai_last_feedback_email', $body );
                    }
                }

                // Fallback: template
                if ( ! $body ) {
                    $tpl = ! empty( $settings['email_tpl_candidate_feedback'] ) ? (string) $settings['email_tpl_candidate_feedback'] : '';
                    if ( ! $tpl ) {
                        $tpl = "Salut {CANDIDATE_NAME},\n\n" .
                               "Aplicația ta pentru jobul {JOB_TITLE} a fost actualizată la statusul: {STATUS}.\n" .
                               "{NOTE}\n\n" .
                               "Îți mulțumim! Te vom contacta cu pașii următori.\n";
                    }
                    if ( function_exists( 'ai_suite_render_template' ) ) {
                        $body = ai_suite_render_template( $tpl, array(
                            'candidate_name' => $cand_name,
                            'job_title'      => $job_title,
                            'status'         => $status_label,
                            'note'           => $note ? ( "Notă: " . $note ) : '',
                        ) );
                    } else {
                        $body = $tpl;
                    }
                }

                $sent = wp_mail( $cand_email, $subject, $body );
                update_post_meta( $app_id, '_ai_status_email_sent', array(
                    'time' => time(),
                    'to'   => $to,
                    'from' => $from,
                    'ok'   => $sent ? 1 : 0,
                ) );

                if ( function_exists( 'ai_suite_application_add_timeline' ) ) {
                    ai_suite_application_add_timeline( $app_id, 'email_status_sent', array( 'ok' => (int) (bool) $sent, 'to' => $to ) );
                }

                return array( 'ok' => (bool) $sent, 'message' => $sent ? 'Email trimis' : 'Email eșuat' );
            }

            default:
                return array( 'ok' => false, 'message' => 'Task necunoscut: ' . $type );
        }
    }
}

if ( ! function_exists( 'ai_suite_queue_worker' ) ) {
    /**
     * Rulează worker-ul.
     */
    function ai_suite_queue_worker( $limit = 3 ) {
        if ( ! ai_suite_queue_enabled() ) {
            return array( 'ok' => true, 'message' => 'Queue disabled' );
        }

        $items = ai_suite_queue_claim_batch( $limit );
        if ( empty( $items ) ) {
            return array( 'ok' => true, 'message' => 'Nimic de procesat', 'count' => 0 );
        }

        $done = 0;
        $failed = 0;
        $out = array();

        foreach ( $items as $item ) {
            $id = (int) $item['id'];
            $type = (string) $item['type'];
            $payload = array();
            if ( ! empty( $item['payload'] ) ) {
                $decoded = json_decode( (string) $item['payload'], true );
                if ( is_array( $decoded ) ) {
                    $payload = $decoded;
                }
            }

            try {
                $res = ai_suite_queue_dispatch( $type, $payload );
                if ( is_array( $res ) && ! empty( $res['ok'] ) ) {
                    ai_suite_queue_complete( $id, $res );
                    $done++;
                } else {
                    $msg = is_array( $res ) && isset( $res['message'] ) ? (string) $res['message'] : 'Task failed';
                    ai_suite_queue_fail( $id, $msg );
                    $failed++;
                }
                $out[] = array( 'id' => $id, 'type' => $type, 'res' => $res );
            } catch ( Exception $e ) {
                ai_suite_queue_fail( $id, $e->getMessage() );
                $failed++;
                $out[] = array( 'id' => $id, 'type' => $type, 'res' => array( 'ok' => false, 'message' => $e->getMessage() ) );
            }
        }

        if ( function_exists( 'aisuite_log' ) ) {
            aisuite_log( 'info', 'AI Queue worker', array( 'done' => $done, 'failed' => $failed ) );
        }

        return array( 'ok' => true, 'done' => $done, 'failed' => $failed, 'count' => count( $items ), 'items' => $out );
    }
}

// === Hooks: enqueue pe evenimente ===

// 1) Status change → enqueue email feedback
add_action( 'aisuite_application_status_changed', function( $application_id, $from, $to ) {
    $settings = function_exists( 'aisuite_get_settings' ) ? (array) aisuite_get_settings() : array();
    $enabled  = isset( $settings['ai_email_status_enabled'] ) ? (int) $settings['ai_email_status_enabled'] === 1 : true;
    if ( ! $enabled || ! ai_suite_queue_enabled() ) {
        return;
    }

    // Avoid DB errors if table isn't installed yet.
    global $wpdb;
    $table = ai_suite_queue_table_name();
    $like = $wpdb->esc_like( $table );
    if ( ! $wpdb->get_var( "SHOW TABLES LIKE '{$like}'" ) ) {
        return;
    }
    // Evită dubluri: dacă există deja task pending/running pentru același app+to, nu mai adăuga.
    global $wpdb;
    $table = ai_suite_queue_table_name();
    $needle = '"application_id":' . (int) $application_id;
    $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(1) FROM {$table} WHERE type='send_status_email' AND status IN ('pending','running') AND payload LIKE %s",
        '%' . $wpdb->esc_like( $needle ) . '%'
    ) );
    if ( $exists ) {
        return;
    }

    ai_suite_queue_enqueue( 'send_status_email', array(
        'application_id' => (int) $application_id,
        'from'           => (string) $from,
        'to'             => (string) $to,
    ), 8 );
}, 20, 3 );
