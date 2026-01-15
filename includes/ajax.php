<?php
/**
 * AJAX handlers for AI Suite.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Guard for AJAX requests.
 */
function ai_suite_ajax_guard() {
            $cap = function_exists('aisuite_capability') ? aisuite_capability() : 'manage_ai_suite';
    if ( ! current_user_can( $cap ) ) {
        // Return Romanian error message for unauthorized access.
        wp_send_json_error( array( 'message' => __( 'Neautorizat', 'ai-suite' ) ), 403 );
    }
    check_ajax_referer( 'ai_suite_nonce', 'nonce' );
}

/**
 * Run a bot via AJAX.
 */
add_action( 'wp_ajax_ai_suite_run_bot', function() {
    ai_suite_ajax_guard();

    $bot_key = isset( $_POST['bot'] ) ? sanitize_key( wp_unslash( $_POST['bot'] ) ) : '';
    $args    = isset( $_POST['args'] ) && is_array( $_POST['args'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['args'] ) ) : array();

    if ( empty( $bot_key ) ) {
        wp_send_json_error( array( 'message' => __( 'Cheie de bot lipsă', 'ai-suite' ) ), 400 );
    }
    if ( ! class_exists( 'AI_Suite_Registry' ) ) {
        wp_send_json_error( array( 'message' => __( 'Registrul lipsește', 'ai-suite' ) ), 500 );
    }
    $all = AI_Suite_Registry::get_all();
    if ( empty( $all[ $bot_key ]['class'] ) || ! class_exists( $all[ $bot_key ]['class'] ) ) {
        wp_send_json_error( array( 'message' => __( 'Clasa bot lipsește', 'ai-suite' ) ), 500 );
    }
    if ( ! AI_Suite_Registry::is_enabled( $bot_key ) ) {
        wp_send_json_error( array( 'message' => __( 'Bot dezactivat', 'ai-suite' ) ), 400 );
    }
    $class = $all[ $bot_key ]['class'];
    $bot   = new $class();
    if ( ! ( $bot instanceof AI_Suite_Bot_Interface ) ) {
        wp_send_json_error( array( 'message' => __( 'Bot invalid', 'ai-suite' ) ), 500 );
    }

    // Run the bot.
    $res = $bot->run( $args );

    // Update registry with last run info.
    $all[ $bot_key ]['last_run']    = current_time( 'mysql' );
    $all[ $bot_key ]['last_status'] = ! empty( $res['ok'] ) ? 'ok' : 'fail';
    AI_Suite_Registry::set_all( $all );

    wp_send_json_success( $res );
} );

/**
 * Kanban: update application status (drag & drop).
 */
add_action( 'wp_ajax_ai_suite_kanban_update_status', function() {
    ai_suite_ajax_guard();

    $app_id    = isset( $_POST['app_id'] ) ? absint( wp_unslash( $_POST['app_id'] ) ) : 0;
    $new_status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

    if ( ! $app_id || 'rmax_application' !== get_post_type( $app_id ) ) {
        wp_send_json_error( array( 'message' => __( 'Aplicație invalidă', 'ai-suite' ) ), 400 );
    }
    if ( ! function_exists( 'ai_suite_app_statuses' ) ) {
        wp_send_json_error( array( 'message' => __( 'Statusuri indisponibile', 'ai-suite' ) ), 500 );
    }
    $statuses = (array) ai_suite_app_statuses();
    if ( empty( $statuses[ $new_status ] ) ) {
        wp_send_json_error( array( 'message' => __( 'Status invalid', 'ai-suite' ) ), 400 );
    }

    $old_status = (string) get_post_meta( $app_id, '_application_status', true );

    // v1.7.6: validate status flow.
    if ( function_exists( 'ai_suite_application_can_transition' ) && ! ai_suite_application_can_transition( $old_status, $new_status ) ) {
        wp_send_json_error( array( 'message' => __( 'Tranziție invalidă de status', 'ai-suite' ) ), 400 );
    }

    update_post_meta( $app_id, '_application_status', $new_status );

    // v1.7.6: audit + timeline.
    if ( function_exists( 'ai_suite_application_record_status_change' ) ) {
        ai_suite_application_record_status_change( $app_id, $old_status, $new_status, 'kanban' );
    } elseif ( function_exists( 'ai_suite_application_add_timeline' ) ) {
        ai_suite_application_add_timeline( $app_id, 'status_changed', array( 'from' => $old_status, 'to' => $new_status, 'context' => 'kanban' ) );
    }

    if ( function_exists( 'aisuite_log' ) ) {
        aisuite_log( 'info', 'Kanban: status aplicație actualizat', array(
            'app_id' => $app_id,
            'old_status' => $old_status,
            'new_status' => $new_status,
        ) );
    }

    wp_send_json_success( array( 'message' => __( 'Actualizat', 'ai-suite' ) ) );
} );


/**
 * v1.8.1: AI Queue – run worker now
 */
add_action( 'wp_ajax_ai_suite_ai_queue_run', function() {
    ai_suite_ajax_guard();

    if ( ! function_exists( 'ai_suite_queue_worker' ) ) {
        wp_send_json_error( array( 'message' => __( 'Worker indisponibil', 'ai-suite' ) ), 500 );
    }

    $limit = isset( $_POST['limit'] ) ? absint( wp_unslash( $_POST['limit'] ) ) : 5;
    if ( $limit < 1 ) { $limit = 1; }
    if ( $limit > 20 ) { $limit = 20; }

    $res = ai_suite_queue_worker( $limit );
    wp_send_json_success( $res );
} );

/**
 * v1.8.1: AI Queue – retry item
 */
add_action( 'wp_ajax_ai_suite_ai_queue_retry', function() {
    ai_suite_ajax_guard();

    if ( ! function_exists( 'ai_suite_queue_retry' ) ) {
        wp_send_json_error( array( 'message' => __( 'Funcție indisponibilă', 'ai-suite' ) ), 500 );
    }
    $id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
    if ( ! $id ) {
        wp_send_json_error( array( 'message' => __( 'ID invalid', 'ai-suite' ) ), 400 );
    }
    $ok = ai_suite_queue_retry( $id );
    if ( $ok ) {
        wp_send_json_success( array( 'message' => __( 'Retry setat', 'ai-suite' ) ) );
    }
    wp_send_json_error( array( 'message' => __( 'Nu am putut face retry', 'ai-suite' ) ), 500 );
} );

/**
 * v1.8.1: AI Queue – delete item
 */
add_action( 'wp_ajax_ai_suite_ai_queue_delete', function() {
    ai_suite_ajax_guard();

    if ( ! function_exists( 'ai_suite_queue_delete' ) ) {
        wp_send_json_error( array( 'message' => __( 'Funcție indisponibilă', 'ai-suite' ) ), 500 );
    }
    $id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
    if ( ! $id ) {
        wp_send_json_error( array( 'message' => __( 'ID invalid', 'ai-suite' ) ), 400 );
    }
    $ok = ai_suite_queue_delete( $id );
    if ( $ok ) {
        wp_send_json_success( array( 'message' => __( 'Șters', 'ai-suite' ) ) );
    }
    wp_send_json_error( array( 'message' => __( 'Nu am putut șterge', 'ai-suite' ) ), 500 );
} );

/**
 * v1.8.1: AI Queue – purge done/failed older than N days (default 14)
 */
add_action( 'wp_ajax_ai_suite_ai_queue_purge', function() {
    ai_suite_ajax_guard();

    if ( ! function_exists( 'ai_suite_queue_purge' ) ) {
        wp_send_json_error( array( 'message' => __( 'Funcție indisponibilă', 'ai-suite' ) ), 500 );
    }
    $days = isset( $_POST['days'] ) ? absint( wp_unslash( $_POST['days'] ) ) : 14;
    if ( $days < 1 ) { $days = 1; }
    if ( $days > 365 ) { $days = 365; }
    $deleted = ai_suite_queue_purge( $days );
    wp_send_json_success( array( 'message' => __( 'Curățat', 'ai-suite' ), 'deleted' => (int) $deleted ) );
} );

/**
 * v1.8.1: AI Queue – install/repair table
 */
add_action( 'wp_ajax_ai_suite_ai_queue_install', function() {
    ai_suite_ajax_guard();
    if ( ! function_exists( 'ai_suite_queue_install' ) ) {
        wp_send_json_error( array( 'message' => __( 'Installer indisponibil', 'ai-suite' ) ), 500 );
    }
    $ok = ai_suite_queue_install();
    wp_send_json_success( array( 'message' => __( 'OK', 'ai-suite' ), 'ok' => (bool) $ok ) );
} );
// -----------------------------------------------------------------------------
// Portal (Company) AJAX – Candidate search, shortlist & pipeline
// -----------------------------------------------------------------------------

if ( ! function_exists( 'ai_suite_portal_ajax_guard' ) ) {
	function ai_suite_portal_ajax_guard( $role = '' ) {
		if ( function_exists( 'ai_suite_portal_require_nonce' ) ) {
			ai_suite_portal_require_nonce( 'ai_suite_portal_nonce' );
		} else {
			check_ajax_referer( 'ai_suite_portal_nonce', 'nonce' );
		}
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( array( 'message' => __( 'Trebuie să fii autentificat.', 'ai-suite' ) ), 401 );
		}
		$role = $role ? $role : 'portal';
		$allowed = function_exists( 'ai_suite_portal_user_can' ) ? ai_suite_portal_user_can( $role ) : current_user_can( 'manage_options' );
		if ( ! $allowed ) {
			if ( function_exists( 'ai_suite_portal_log_auth_failure' ) ) {
				ai_suite_portal_log_auth_failure( 'capability', array(
					'action'  => isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '',
					'referer' => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
				) );
			}
			wp_send_json_error( array( 'message' => __( 'Acces restricționat.', 'ai-suite' ) ), 403 );
		}
	}
}

if ( ! function_exists( 'ai_suite_portal_company_id' ) ) {
	function ai_suite_portal_company_id( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return 0;
		}
		if ( class_exists( 'AI_Suite_Portal_Frontend' ) && method_exists( 'AI_Suite_Portal_Frontend', 'get_company_id_for_user' ) ) {
			return absint( AI_Suite_Portal_Frontend::get_company_id_for_user( $user_id ) );
		}
		return absint( get_user_meta( $user_id, '_ai_suite_company_id', true ) );
	}
}

if ( ! function_exists( 'ai_suite_portal_candidate_payload' ) ) {
	function ai_suite_portal_candidate_payload( $candidate_id ) {
		$candidate_id = absint( $candidate_id );
		if ( ! $candidate_id || 'rmax_candidate' !== get_post_type( $candidate_id ) ) {
			return null;
		}
		$name     = get_the_title( $candidate_id );
		$email    = (string) get_post_meta( $candidate_id, '_candidate_email', true );
		$phone    = (string) get_post_meta( $candidate_id, '_candidate_phone', true );
		$location = (string) get_post_meta( $candidate_id, '_candidate_location', true );
		$skills   = (string) get_post_meta( $candidate_id, '_candidate_skills', true );
		$cv_id    = absint( get_post_meta( $candidate_id, '_candidate_cv', true ) );
		$cv_url   = $cv_id ? wp_get_attachment_url( $cv_id ) : '';
		return array(
			'id'       => $candidate_id,
			'name'     => $name,
			'email'    => $email,
			'phone'    => $phone,
			'location' => $location,
			'skills'   => $skills,
			'cvUrl'    => $cv_url,
		);
	}
}

// ----------------------------------------------------------
// Portal ATS AJAX (fallback)
// IMPORTANT: ATS PRO module defines these endpoints with {ok:true} responses.
// This fallback registers ONLY if no handler already exists (ex: module disabled in Safe Mode).
// ----------------------------------------------------------

if ( ! function_exists( 'aisuite_register_portal_ats_fallback_ajax' ) ) {
	function aisuite_register_portal_ats_fallback_ajax() {

		$fail = function( $msg, $code = 400 ) {
			wp_send_json( array( 'ok' => false, 'message' => (string) $msg ), (int) $code );
		};
		$ok = function( $data = array() ) {
			if ( ! is_array( $data ) ) { $data = array( 'data' => $data ); }
			$data['ok'] = true;
			wp_send_json( $data );
		};

			$require_company = function() use ( $fail ) {
				if ( ! is_user_logged_in() ) {
					$fail( __( 'Neautorizat.', 'ai-suite' ), 401 );
				}
				if ( function_exists( 'ai_suite_portal_require_nonce' ) ) {
					ai_suite_portal_require_nonce( 'ai_suite_portal_nonce' );
				} else {
					check_ajax_referer( 'ai_suite_portal_nonce', 'nonce' );
				}
				if ( function_exists( 'ai_suite_portal_user_can' ) && ! ai_suite_portal_user_can( 'company' ) ) {
					if ( function_exists( 'ai_suite_portal_log_auth_failure' ) ) {
						ai_suite_portal_log_auth_failure( 'capability', array(
							'action' => isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '',
						) );
					}
					$fail( __( 'Neautorizat.', 'ai-suite' ), 403 );
				}
				$uid = function_exists( 'ai_suite_portal_effective_user_id' ) ? ai_suite_portal_effective_user_id() : get_current_user_id();
				$is_company = false;
				if ( function_exists( 'aisuite_user_has_role' ) ) {
					$is_company = aisuite_user_has_role( $uid, 'aisuite_company' );
				} elseif ( function_exists( 'aisuite_current_user_is_company' ) && (int) $uid === (int) get_current_user_id() ) {
					$is_company = aisuite_current_user_is_company();
				}
				if ( ! $is_company && ! current_user_can( 'manage_options' ) ) {
					$fail( __( 'Acces restricționat (doar companii).', 'ai-suite' ), 403 );
				}
			$company_id = function_exists( 'ai_suite_portal_company_id' ) ? ai_suite_portal_company_id( $uid ) : 0;
			if ( ! $company_id || get_post_type( $company_id ) !== 'rmax_company' ) {
				$fail( __( 'Profil companie lipsă.', 'ai-suite' ), 404 );
			}
			return $company_id;
		};

		// Helper: company job ids
		$get_company_job_ids = function( $company_id ) {
			$company_id = absint( $company_id );
			if ( ! $company_id ) { return array(); }
			if ( function_exists( 'aisuite_company_get_job_ids' ) ) {
				return array_values( array_filter( array_map( 'absint', (array) aisuite_company_get_job_ids( $company_id ) ) ) );
			}
			$ids = get_posts( array(
				'post_type'      => 'rmax_job',
				'post_status'    => 'any',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_job_company_id',
						'value' => (string) $company_id,
					),
				),
			) );
			return array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
		};

		// Candidate search (Company portal)
		if ( ! has_action( 'wp_ajax_ai_suite_candidate_search' ) ) {
			add_action( 'wp_ajax_ai_suite_candidate_search', function() use ( $require_company, $ok ) {
				$company_id = $require_company();

				$q   = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
				$loc = isset( $_POST['loc'] ) ? sanitize_text_field( wp_unslash( $_POST['loc'] ) ) : '';
				$has = isset( $_POST['hasCv'] ) ? absint( wp_unslash( $_POST['hasCv'] ) ) : 0;

				$args = array(
					'post_type'      => 'rmax_candidate',
					'post_status'    => 'publish',
					'posts_per_page' => 50,
					'orderby'        => 'date',
					'order'          => 'DESC',
				);
				if ( $q ) { $args['s'] = $q; }

				$qq = new WP_Query( $args );
				$list = array();

				// shortlist map
				$short_raw = get_post_meta( $company_id, '_company_shortlist', true );
				$short_map = array();
				if ( is_array( $short_raw ) ) {
					foreach ( $short_raw as $k => $row ) {
						$cid = 0;
						if ( is_array( $row ) && isset( $row['id'] ) ) {
							$cid = absint( $row['id'] );
						} else {
							$cid = absint( $k );
						}
						if ( $cid ) { $short_map[ $cid ] = true; }
					}
				}

				foreach ( (array) $qq->posts as $p ) {
					$payload = function_exists( 'ai_suite_portal_candidate_payload' ) ? ai_suite_portal_candidate_payload( $p->ID ) : null;
					if ( ! $payload ) { continue; }

					if ( $loc && stripos( (string) $payload['location'], (string) $loc ) === false ) { continue; }
					if ( $has && empty( $payload['cvUrl'] ) ) { continue; }

					$payload['isShortlisted'] = isset( $short_map[ absint( $payload['id'] ) ] );
					$list[] = $payload;
				}

				$ok( array( 'candidates' => $list ) );
			} );
		}

		// Shortlist get
		if ( ! has_action( 'wp_ajax_ai_suite_shortlist_get' ) ) {
			add_action( 'wp_ajax_ai_suite_shortlist_get', function() use ( $require_company, $ok ) {
				$company_id = $require_company();

				$raw = get_post_meta( $company_id, '_company_shortlist', true );
				$raw = is_array( $raw ) ? $raw : array();

				$items = array();
				foreach ( $raw as $k => $row ) {
					$cid = 0;
					$tags = array();
					$note = '';
					if ( is_array( $row ) ) {
						$cid  = isset( $row['id'] ) ? absint( $row['id'] ) : absint( $k );
						$tags = ( isset( $row['tags'] ) && is_array( $row['tags'] ) ) ? array_values( array_filter( array_map( 'sanitize_text_field', $row['tags'] ) ) ) : array();
						$note = isset( $row['note'] ) ? sanitize_textarea_field( $row['note'] ) : '';
					} else {
						$cid = absint( $k );
					}

					$p = function_exists( 'ai_suite_portal_candidate_payload' ) ? ai_suite_portal_candidate_payload( $cid ) : null;
					if ( ! $p ) { continue; }

					$p['tags'] = $tags;
					$p['note'] = $note;
					$items[] = $p;
				}

				$ok( array( 'items' => $items ) );
			} );
		}

		// Shortlist add/remove/update
		$shortlist_update_cb = function( $mode = 'update' ) use ( $require_company, $ok, $fail ) {
			$company_id = $require_company();

			$candidate_id = isset( $_POST['candidateId'] ) ? absint( wp_unslash( $_POST['candidateId'] ) ) : 0;
			if ( ! $candidate_id || get_post_type( $candidate_id ) !== 'rmax_candidate' ) {
				$fail( __( 'Candidat invalid.', 'ai-suite' ), 400 );
			}

			$do = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : $mode;
			if ( ! in_array( $do, array( 'add', 'remove', 'update', 'toggle' ), true ) ) {
				$do = $mode;
			}

			$tags = array();
			if ( isset( $_POST['tags'] ) ) {
				$tags_raw = wp_unslash( $_POST['tags'] );
				if ( is_string( $tags_raw ) ) { $tags_raw = explode( ',', $tags_raw ); }
				if ( is_array( $tags_raw ) ) { $tags = array_values( array_filter( array_map( 'sanitize_text_field', $tags_raw ) ) ); }
			}
			$note = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';

			$list = get_post_meta( $company_id, '_company_shortlist', true );
			$list = is_array( $list ) ? $list : array();

			// normalize to array of rows with id
			$rows = array();
			foreach ( $list as $k => $row ) {
				if ( is_array( $row ) && isset( $row['id'] ) ) {
					$rows[] = $row;
				} else {
					$cid = absint( $k );
					if ( $cid ) { $rows[] = array( 'id' => $cid ); }
				}
			}

			$found = false;
			foreach ( $rows as $i => $row ) {
				if ( isset( $row['id'] ) && absint( $row['id'] ) === $candidate_id ) {
					$found = true;
					if ( 'remove' === $do ) {
						unset( $rows[ $i ] );
					} elseif ( 'toggle' === $do ) {
						unset( $rows[ $i ] );
					} else {
						$rows[ $i ]['tags'] = $tags;
						$rows[ $i ]['note'] = $note;
					}
					break;
				}
			}

			if ( ! $found && 'remove' !== $do ) {
				$rows[] = array(
					'id'       => $candidate_id,
					'tags'     => $tags,
					'note'     => $note,
					'added_at' => current_time( 'mysql' ),
				);
			}

			$rows = array_values( $rows );
			update_post_meta( $company_id, '_company_shortlist', $rows );

			$ok( array() );
		};

		if ( ! has_action( 'wp_ajax_ai_suite_shortlist_update' ) ) {
			add_action( 'wp_ajax_ai_suite_shortlist_update', function() use ( $shortlist_update_cb ) {
				$shortlist_update_cb( 'update' );
			} );
		}
		if ( ! has_action( 'wp_ajax_ai_suite_shortlist_add' ) ) {
			add_action( 'wp_ajax_ai_suite_shortlist_add', function() use ( $shortlist_update_cb ) {
				$shortlist_update_cb( 'add' );
			} );
		}
		if ( ! has_action( 'wp_ajax_ai_suite_shortlist_remove' ) ) {
			add_action( 'wp_ajax_ai_suite_shortlist_remove', function() use ( $shortlist_update_cb ) {
				$shortlist_update_cb( 'remove' );
			} );
		}

		// Pipeline list / move (Company portal)
		if ( ! has_action( 'wp_ajax_ai_suite_pipeline_list' ) ) {
			add_action( 'wp_ajax_ai_suite_pipeline_list', function() use ( $require_company, $ok, $get_company_job_ids, $fail ) {
				$company_id = $require_company();

				$job_ids = $get_company_job_ids( $company_id );
				$filter_job_id = isset( $_POST['jobId'] ) ? absint( wp_unslash( $_POST['jobId'] ) ) : 0;
				if ( $filter_job_id ) {
					if ( ! in_array( $filter_job_id, $job_ids, true ) ) {
						$fail( __( 'Job invalid sau fără acces.', 'ai-suite' ), 403 );
					}
					$job_ids = array( $filter_job_id );
				}

				if ( empty( $job_ids ) ) {
					$ok( array( 'columns' => array(), 'counts' => array() ) );
				}

				$statuses = function_exists( 'ai_suite_app_statuses' ) ? (array) ai_suite_app_statuses() : array(
					'nou' => 'Nou', 'in_analiza' => 'În analiză', 'interviu' => 'Interviu', 'respins' => 'Respins', 'acceptat' => 'Acceptat'
				);

				$cols = array();
				$counts = array();
				foreach ( $statuses as $key => $label ) {
					$key = sanitize_key( $key );
					$cols[ $key ] = array( 'key' => $key, 'label' => (string) $label, 'items' => array() );
					$counts[ $key ] = 0;
				}

				$app_ids = get_posts( array(
					'post_type'      => 'rmax_application',
					'post_status'    => 'publish',
					'posts_per_page' => 200,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'     => '_application_job_id',
							'value'   => array_map( 'strval', $job_ids ),
							'compare' => 'IN',
						),
					),
				) );

				foreach ( (array) $app_ids as $app_id ) {
					$app_id = absint( $app_id );
					$status = (string) get_post_meta( $app_id, '_application_status', true );
					$status = sanitize_key( $status );
					if ( ! isset( $cols[ $status ] ) ) {
						$cols[ $status ] = array( 'key' => $status, 'label' => $status, 'items' => array() );
						$counts[ $status ] = 0;
					}

					$cand_id = absint( get_post_meta( $app_id, '_application_candidate_id', true ) );
					$job_id  = absint( get_post_meta( $app_id, '_application_job_id', true ) );

					$cand = $cand_id && function_exists( 'ai_suite_portal_candidate_payload' ) ? ai_suite_portal_candidate_payload( $cand_id ) : null;

					$cols[ $status ]['items'][] = array(
						'id'        => $app_id,
						'title'     => get_the_title( $app_id ),
						'status'    => $status,
						'jobId'     => $job_id,
						'jobTitle'  => $job_id ? get_the_title( $job_id ) : '',
						'candidate' => $cand,
						'created'   => get_post_time( 'U', true, $app_id ),
					);
					$counts[ $status ]++;
				}

				$ok( array( 'columns' => array_values( $cols ), 'counts' => $counts ) );
			} );
		}

		if ( ! has_action( 'wp_ajax_ai_suite_pipeline_move' ) ) {
			add_action( 'wp_ajax_ai_suite_pipeline_move', function() use ( $require_company, $ok, $fail ) {
				$company_id = $require_company();

				$app_id = isset( $_POST['applicationId'] ) ? absint( wp_unslash( $_POST['applicationId'] ) ) : 0;
				$to     = isset( $_POST['toStatus'] ) ? sanitize_key( wp_unslash( $_POST['toStatus'] ) ) : '';
				if ( ! $app_id || get_post_type( $app_id ) !== 'rmax_application' ) {
					$fail( __( 'Aplicație invalidă.', 'ai-suite' ), 400 );
				}
				if ( $to === '' ) {
					$fail( __( 'Status invalid.', 'ai-suite' ), 400 );
				}

				$job_id = absint( get_post_meta( $app_id, '_application_job_id', true ) );
				$job_company = $job_id ? absint( get_post_meta( $job_id, '_job_company_id', true ) ) : 0;
				if ( ! $job_id || $job_company !== $company_id ) {
					$fail( __( 'Acces interzis pentru această aplicație.', 'ai-suite' ), 403 );
				}

				update_post_meta( $app_id, '_application_status', $to );
				$ok( array() );
			} );
		}
	}

	add_action( 'init', 'aisuite_register_portal_ats_fallback_ajax', 99 );
}

/**
 * KPI data for Dashboard (last 30 days).
 */
add_action( 'wp_ajax_ai_suite_kpi_data', function() {
    ai_suite_ajax_guard();

    global $wpdb;

    $days = 30;
    $since = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

    // Applications count
    $apps = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(1) FROM {$wpdb->posts} WHERE post_type=%s AND post_status IN ('publish','private') AND post_date_gmt >= %s",
        'rmax_application', $since
    ) );

    // Jobs count
    $jobs = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(1) FROM {$wpdb->posts} WHERE post_type=%s AND post_status='publish' AND post_date_gmt >= %s",
        'rmax_job', $since
    ) );

    // Queue pending
    $queue_table = $wpdb->prefix . 'ai_suite_tasks';
    $queue = 0;
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $queue_table ) ) === $queue_table ) {
        $queue = (int) $wpdb->get_var( "SELECT COUNT(1) FROM {$queue_table} WHERE status IN ('pending','retry')" );
    }

    // Daily trend for apps
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT DATE(post_date_gmt) as d, COUNT(1) as c
         FROM {$wpdb->posts}
         WHERE post_type=%s AND post_status IN ('publish','private') AND post_date_gmt >= %s
         GROUP BY DATE(post_date_gmt)
         ORDER BY d ASC",
        'rmax_application', $since
    ), ARRAY_A );

    $map = array();
    foreach ( $rows as $r ) {
        $map[ $r['d'] ] = (int) $r['c'];
    }
    $trend = array();
    for ( $i = $days-1; $i >= 0; $i-- ) {
        $d = gmdate( 'Y-m-d', time() - ( $i * DAY_IN_SECONDS ) );
        $trend[] = array( 'd' => $d, 'c' => isset( $map[ $d ] ) ? (int) $map[ $d ] : 0 );
    }

    wp_send_json_success( array(
        'apps'  => $apps,
        'jobs'  => $jobs,
        'queue' => $queue,
        'trend' => $trend,
    ) );
} );


/**
 * -------------------------
 * Copilot AI (Admin) – allow internal roles too
 * -------------------------
 */
add_action( 'wp_ajax_ai_suite_copilot_load', function() {
    check_ajax_referer( 'ai_suite_nonce', 'nonce' );
    $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
    if ( ! current_user_can( $cap ) ) {
        wp_send_json_error( array( 'message' => __( 'Neautorizat', 'ai-suite' ) ), 403 );
    }

    $company_id = isset( $_POST['company_id'] ) ? absint( wp_unslash( $_POST['company_id'] ) ) : 0;
    if ( ! $company_id ) {
        wp_send_json_error( array( 'message' => __( 'Companie invalidă.', 'ai-suite' ) ), 400 );
    }

    if ( ! current_user_can( 'manage_ai_suite' ) && function_exists( 'aisuite_get_assigned_company_ids' ) ) {
        $allowed = aisuite_get_assigned_company_ids( get_current_user_id() );
        if ( ! in_array( $company_id, (array) $allowed, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Acces restricționat (companie nealocată).', 'ai-suite' ) ), 403 );
        }
    }

    $chat = function_exists( 'aisuite_copilot_get_user_chat' ) ? aisuite_copilot_get_user_chat( $company_id, get_current_user_id() ) : array();
    wp_send_json_success( array( 'chat' => $chat ) );
} );

add_action( 'wp_ajax_ai_suite_copilot_clear', function() {
    check_ajax_referer( 'ai_suite_nonce', 'nonce' );
    $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
    if ( ! current_user_can( $cap ) ) {
        wp_send_json_error( array( 'message' => __( 'Neautorizat', 'ai-suite' ) ), 403 );
    }
    $company_id = isset( $_POST['company_id'] ) ? absint( wp_unslash( $_POST['company_id'] ) ) : 0;
    if ( ! $company_id ) {
        wp_send_json_error( array( 'message' => __( 'Companie invalidă.', 'ai-suite' ) ), 400 );
    }
    if ( function_exists( 'aisuite_copilot_set_user_chat' ) ) {
        aisuite_copilot_set_user_chat( $company_id, array(), get_current_user_id() );
    }
    wp_send_json_success( array( 'ok' => true ) );
} );

add_action( 'wp_ajax_ai_suite_copilot_send', function() {
    check_ajax_referer( 'ai_suite_nonce', 'nonce' );
    $cap = function_exists( 'aisuite_capability' ) ? aisuite_capability() : 'manage_options';
    if ( ! current_user_can( $cap ) ) {
        wp_send_json_error( array( 'message' => __( 'Neautorizat', 'ai-suite' ) ), 403 );
    }

    $company_id = isset( $_POST['company_id'] ) ? absint( wp_unslash( $_POST['company_id'] ) ) : 0;
    $msg = isset( $_POST['message'] ) ? (string) wp_unslash( $_POST['message'] ) : '';
    $include_pii = ! empty( $_POST['include_pii'] ) ? 1 : 0;

    $msg = trim( wp_strip_all_tags( $msg ) );
    if ( ! $company_id ) {
        wp_send_json_error( array( 'message' => __( 'Companie invalidă.', 'ai-suite' ) ), 400 );
    }
    if ( $msg === '' ) {
        wp_send_json_error( array( 'message' => __( 'Mesaj gol.', 'ai-suite' ) ), 400 );
    }

    if ( ! current_user_can( 'manage_ai_suite' ) && function_exists( 'aisuite_get_assigned_company_ids' ) ) {
        $allowed = aisuite_get_assigned_company_ids( get_current_user_id() );
        if ( ! in_array( $company_id, (array) $allowed, true ) ) {
            wp_send_json_error( array( 'message' => __( 'Acces restricționat (companie nealocată).', 'ai-suite' ) ), 403 );
        }
    }

    $chat = function_exists( 'aisuite_copilot_get_user_chat' ) ? aisuite_copilot_get_user_chat( $company_id, get_current_user_id() ) : array();
    $chat = is_array( $chat ) ? $chat : array();

    // Build messages: system + history + new user message.
    $messages = array();
    if ( function_exists( 'aisuite_copilot_system_message' ) ) {
        $messages[] = aisuite_copilot_system_message( $company_id, (bool) $include_pii, 'admin' );
    }
    $history = function_exists( 'aisuite_copilot_sanitize_messages' ) ? aisuite_copilot_sanitize_messages( $chat ) : array();
    if ( function_exists( 'aisuite_copilot_limit_messages' ) ) {
        $history = aisuite_copilot_limit_messages( $history, 18 );
    }
    $messages = array_merge( $messages, $history, array( array( 'role' => 'user', 'content' => $msg ) ) );

    $res = function_exists( 'aisuite_copilot_chat_completion' ) ? aisuite_copilot_chat_completion( $messages, 650, 0.2 ) : array( 'ok' => false, 'error' => 'Copilot helper missing.' );
    if ( empty( $res['ok'] ) ) {
        wp_send_json_error( array( 'message' => __( 'Eroare OpenAI: ', 'ai-suite' ) . (string) ( $res['error'] ?? 'Unknown' ) ) );
    }

    $answer = trim( (string) $res['text'] );
    $chat[] = array( 'role' => 'user', 'content' => $msg );
    $chat[] = array( 'role' => 'assistant', 'content' => $answer );

    if ( function_exists( 'aisuite_copilot_set_user_chat' ) ) {
        aisuite_copilot_set_user_chat( $company_id, $chat, get_current_user_id() );
    }

    wp_send_json_success( array(
        'answer' => $answer,
        'chat'   => $chat,
    ) );
} );

/**
 * -------------------------
 * Copilot AI (Portal Companie) – shared per company
 * -------------------------
 */
add_action( 'wp_ajax_ai_suite_portal_copilot_load', function() {
    if ( function_exists( 'ai_suite_portal_ajax_guard' ) ) {
        ai_suite_portal_ajax_guard( 'company' );
    }
    $company_id = function_exists( 'ai_suite_portal_company_id' ) ? ai_suite_portal_company_id( get_current_user_id() ) : 0;
    if ( ! $company_id ) {
        wp_send_json_error( array( 'message' => __( 'Companie invalidă.', 'ai-suite' ) ), 400 );
    }
    
    if ( function_exists( 'ai_suite_company_has_feature' ) && ! ai_suite_company_has_feature( $company_id, 'copilot' ) ) {
        wp_send_json_error( array( 'message' => __( 'Copilot AI nu este disponibil pe planul tău. Upgrade din tabul Abonament.', 'ai-suite' ) ), 402 );
    }
$chat = function_exists( 'aisuite_copilot_get_company_chat' ) ? aisuite_copilot_get_company_chat( $company_id ) : array();
    wp_send_json_success( array( 'chat' => $chat ) );
} );

add_action( 'wp_ajax_ai_suite_portal_copilot_clear', function() {
    if ( function_exists( 'ai_suite_portal_ajax_guard' ) ) {
        ai_suite_portal_ajax_guard( 'company' );
    }
    $company_id = function_exists( 'ai_suite_portal_company_id' ) ? ai_suite_portal_company_id( get_current_user_id() ) : 0;
    if ( ! $company_id ) {
        wp_send_json_error( array( 'message' => __( 'Companie invalidă.', 'ai-suite' ) ), 400 );
    }
    
    if ( function_exists( 'ai_suite_company_has_feature' ) && ! ai_suite_company_has_feature( $company_id, 'copilot' ) ) {
        wp_send_json_error( array( 'message' => __( 'Copilot AI nu este disponibil pe planul tău. Upgrade din tabul Abonament.', 'ai-suite' ) ), 402 );
    }
if ( function_exists( 'aisuite_copilot_set_company_chat' ) ) {
        aisuite_copilot_set_company_chat( $company_id, array() );
    }
    wp_send_json_success( array( 'ok' => true ) );
} );

add_action( 'wp_ajax_ai_suite_portal_copilot_send', function() {
    if ( function_exists( 'ai_suite_portal_ajax_guard' ) ) {
        ai_suite_portal_ajax_guard( 'company' );
    }
    $company_id = function_exists( 'ai_suite_portal_company_id' ) ? ai_suite_portal_company_id( get_current_user_id() ) : 0;
    $msg = isset( $_POST['message'] ) ? (string) wp_unslash( $_POST['message'] ) : '';
    $include_pii = ! empty( $_POST['include_pii'] ) ? 1 : 0;

    $msg = trim( wp_strip_all_tags( $msg ) );
    if ( ! $company_id ) {
        wp_send_json_error( array( 'message' => __( 'Companie invalidă.', 'ai-suite' ) ), 400 );
    }
    
    if ( function_exists( 'ai_suite_company_has_feature' ) && ! ai_suite_company_has_feature( $company_id, 'copilot' ) ) {
        wp_send_json_error( array( 'message' => __( 'Copilot AI nu este disponibil pe planul tău. Upgrade din tabul Abonament.', 'ai-suite' ) ), 402 );
    }
if ( $msg === '' ) {
        wp_send_json_error( array( 'message' => __( 'Mesaj gol.', 'ai-suite' ) ), 400 );
    }

    $chat = function_exists( 'aisuite_copilot_get_company_chat' ) ? aisuite_copilot_get_company_chat( $company_id ) : array();
    $chat = is_array( $chat ) ? $chat : array();

    $messages = array();
    if ( function_exists( 'aisuite_copilot_system_message' ) ) {
        $messages[] = aisuite_copilot_system_message( $company_id, (bool) $include_pii, 'portal_company' );
    }
    $history = function_exists( 'aisuite_copilot_sanitize_messages' ) ? aisuite_copilot_sanitize_messages( $chat ) : array();
    if ( function_exists( 'aisuite_copilot_limit_messages' ) ) {
        $history = aisuite_copilot_limit_messages( $history, 18 );
    }
    $messages = array_merge( $messages, $history, array( array( 'role' => 'user', 'content' => $msg ) ) );

    $res = function_exists( 'aisuite_copilot_chat_completion' ) ? aisuite_copilot_chat_completion( $messages, 650, 0.2 ) : array( 'ok' => false, 'error' => 'Copilot helper missing.' );
    if ( empty( $res['ok'] ) ) {
        wp_send_json_error( array( 'message' => __( 'Eroare OpenAI: ', 'ai-suite' ) . (string) ( $res['error'] ?? 'Unknown' ) ) );
    }

    $answer = trim( (string) $res['text'] );
    $chat[] = array( 'role' => 'user', 'content' => $msg );
    $chat[] = array( 'role' => 'assistant', 'content' => $answer );

    if ( function_exists( 'aisuite_copilot_set_company_chat' ) ) {
        aisuite_copilot_set_company_chat( $company_id, $chat );
    }

    wp_send_json_success( array(
        'answer' => $answer,
        'chat'   => $chat,
    ) );
} );

/**
 * Portal JS diagnostics logger (AJAX).
 * Stores last issues in logs for admins to inspect later.
 */
add_action( 'wp_ajax_ai_suite_portal_js_log', function() {
    ai_suite_portal_ajax_guard( 'company' );

    $payload = isset( $_POST['payload'] ) && is_array( $_POST['payload'] ) ? wp_unslash( $_POST['payload'] ) : array();
    // Sanitize shallow payload.
    $clean = array();
    foreach ( $payload as $k => $v ) {
        $key = sanitize_key( (string) $k );
        if ( is_scalar( $v ) ) {
            $clean[ $key ] = sanitize_text_field( (string) $v );
        } else {
            $clean[ $key ] = wp_json_encode( $v );
        }
    }

    if ( function_exists( 'aisuite_log' ) ) {
        aisuite_log( 'warning', 'Portal JS issue', array(
            'source'    => 'portal_js',
            'user_id'   => get_current_user_id(),
            'companyId' => isset( $clean['companyid'] ) ? absint( $clean['companyid'] ) : 0,
            'payload'   => $clean,
        ) );
    }

    wp_send_json_success( array( 'ok' => 1 ) );
} );



// === Global Search (Admin Toolbar) ===
add_action( 'wp_ajax_ai_suite_global_search', function() {
    if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ai_suite_nonce' ) ) {
        wp_send_json_error( array( 'message' => 'bad_nonce' ), 403 );
    }

    // Allow admin OR internal team roles.
    $allowed = current_user_can( 'manage_ai_suite' );
    if ( ! $allowed && function_exists( 'aisuite_current_user_is_manager' ) && aisuite_current_user_is_manager() ) {
        $allowed = true;
    }
    if ( ! $allowed && function_exists( 'aisuite_current_user_is_recruiter' ) && aisuite_current_user_is_recruiter() ) {
        $allowed = true;
    }
    if ( ! $allowed ) {
        wp_send_json_error( array( 'message' => 'forbidden' ), 403 );
    }

    $q = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
    $q = trim( $q );
    if ( strlen( $q ) < 2 ) {
        wp_send_json_success( array( 'html' => '' ) );
    }

    $limit = 5;

    $sections = array();

    $map = array(
        'rmax_job'         => __( 'Joburi', 'ai-suite' ),
        'rmax_candidate'   => __( 'Candidați', 'ai-suite' ),
        'rmax_application' => __( 'Aplicații', 'ai-suite' ),
        'rmax_company'     => __( 'Companii', 'ai-suite' ),
    );

    foreach ( $map as $post_type => $label ) {
        $args = array(
            'post_type'      => $post_type,
            'posts_per_page' => $limit,
            'post_status'    => array( 'publish', 'private', 'draft' ),
            's'              => $q,
            'fields'         => 'ids',
        );

        // Some extra meta search (email/phone) for candidate/company/application.
        if ( in_array( $post_type, array( 'rmax_candidate', 'rmax_company', 'rmax_application' ), true ) ) {
            $meta_keys = array();
            if ( $post_type === 'rmax_candidate' ) $meta_keys = array( '_candidate_email', '_candidate_phone', '_candidate_location' );
            if ( $post_type === 'rmax_company' )   $meta_keys = array( '_company_contact_email', '_company_team_emails' );
            if ( $post_type === 'rmax_application' ) $meta_keys = array( '_application_email', '_application_phone' );

            $or = array( 'relation' => 'OR' );
            foreach ( $meta_keys as $mk ) {
                $or[] = array(
                    'key'     => $mk,
                    'value'   => $q,
                    'compare' => 'LIKE',
                );
            }
            $args['meta_query'] = $or;
            // Keep 's' too (WP will AND them; ok for small limits). If it returns empty, we try meta-only below.
        }

        $ids = get_posts( $args );

        // Fallback: meta-only
        if ( empty( $ids ) && isset( $args['meta_query'] ) ) {
            unset( $args['s'] );
            $ids = get_posts( $args );
        }

        if ( empty( $ids ) ) continue;

        $items = array();
        foreach ( $ids as $id ) {
            $t = get_the_title( $id );
            if ( $t === '' ) $t = '#' . $id;
            $edit = admin_url( 'post.php?post=' . absint( $id ) . '&action=edit' );
            $items[] = array(
                'title' => $t,
                'meta'  => '#' . $id,
                'url'   => $edit,
            );
        }

        $sections[] = array(
            'label' => $label,
            'items' => $items,
        );
    }

    if ( empty( $sections ) ) {
        wp_send_json_success( array(
            'html' => '<div class="ais-searchpop__sec"><h4>' . esc_html__( 'Rezultate', 'ai-suite' ) . '</h4><div style="padding:6px 0;color:#64748b;">' . esc_html__( 'Nimic găsit.', 'ai-suite' ) . '</div></div>'
        ) );
    }

    ob_start();
    foreach ( $sections as $sec ) {
        echo '<div class="ais-searchpop__sec">';
        echo '<h4>' . esc_html( $sec['label'] ) . '</h4>';
        foreach ( $sec['items'] as $it ) {
            echo '<a href="' . esc_url( $it['url'] ) . '">';
              echo '<span>' . esc_html( $it['title'] ) . '</span>';
              echo '<small>' . esc_html( $it['meta'] ) . '</small>';
            echo '</a>';
        }
        echo '</div>';
    }
    $html = ob_get_clean();

    wp_send_json_success( array( 'html' => $html ) );
} );

// ---------------------------
// Portal: Company billing (buyer) details save (Patch48)
// ---------------------------
if ( ! function_exists( 'ai_suite_ajax_company_billing_save' ) ) {
    function ai_suite_ajax_company_billing_save() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'Neautorizat.', 'ai-suite' ) ), 401 );
        }

        // Portal nonce
        if ( function_exists( 'ai_suite_portal_require_nonce' ) ) {
            ai_suite_portal_require_nonce( 'ai_suite_portal_nonce' );
        } else {
            check_ajax_referer( 'ai_suite_portal_nonce', 'nonce' );
        }

        // Support admin preview/impersonation (server will validate if helpers exist)
        $uid = get_current_user_id();
        if ( function_exists( 'ai_suite_portal_effective_user_id' ) ) {
            $uid = (int) ai_suite_portal_effective_user_id();
        }

        // Resolve company id for this portal user
        $company_id = 0;
        if ( function_exists( 'ai_suite_portal_company_id' ) ) {
            $company_id = (int) ai_suite_portal_company_id( $uid );
        }
        if ( ! $company_id ) {
            // fallback: some older builds store on user meta
            $company_id = (int) get_user_meta( $uid, '_ai_suite_company_id', true );
        }

        if ( ! $company_id ) {
            wp_send_json_error( array( 'message' => __( 'Companie lipsă.', 'ai-suite' ) ), 403 );
        }

        // Capability check: company user OR admin/recruitment manager
        $is_company_user = false;
        if ( function_exists( 'aisuite_current_user_is_company' ) ) {
            $is_company_user = aisuite_current_user_is_company();
        } elseif ( function_exists( 'aisuite_user_has_role' ) ) {
            $is_company_user = aisuite_user_has_role( $uid, 'company' );
        }

        if ( ! $is_company_user && ! current_user_can( 'manage_ai_suite' ) && ! ( function_exists( 'aisuite_current_user_can_manage_recruitment' ) && aisuite_current_user_can_manage_recruitment() ) ) {
            wp_send_json_error( array( 'message' => __( 'Neautorizat.', 'ai-suite' ) ), 403 );
        }

        $billing_name    = isset( $_POST['billing_name'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_name'] ) ) : '';
        $billing_cui     = isset( $_POST['billing_cui'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_cui'] ) ) : '';
        $billing_reg     = isset( $_POST['billing_reg'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_reg'] ) ) : '';
        $billing_address = isset( $_POST['billing_address'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_address'] ) ) : '';
        $billing_city    = isset( $_POST['billing_city'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_city'] ) ) : '';
        $billing_country = isset( $_POST['billing_country'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_country'] ) ) : '';
        $billing_email   = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';
        $billing_phone   = isset( $_POST['billing_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) ) : '';
        $billing_contact = isset( $_POST['billing_contact'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_contact'] ) ) : '';
        $billing_vat     = ! empty( $_POST['billing_vat'] ) ? 1 : 0;

        if ( $billing_name === '' ) {
            wp_send_json_error( array( 'message' => __( 'Denumirea pentru facturare este obligatorie.', 'ai-suite' ) ), 400 );
        }

        update_post_meta( $company_id, '_company_billing_name', $billing_name );
        update_post_meta( $company_id, '_company_billing_cui', $billing_cui );
        update_post_meta( $company_id, '_company_billing_reg', $billing_reg );
        update_post_meta( $company_id, '_company_billing_address', $billing_address );
        update_post_meta( $company_id, '_company_billing_city', $billing_city );
        update_post_meta( $company_id, '_company_billing_country', $billing_country );
        update_post_meta( $company_id, '_company_billing_email', $billing_email );
        update_post_meta( $company_id, '_company_billing_phone', $billing_phone );
        update_post_meta( $company_id, '_company_billing_contact', $billing_contact );
        update_post_meta( $company_id, '_company_billing_vat', $billing_vat );

        wp_send_json_success( array( 'message' => __( 'Datele de facturare au fost salvate.', 'ai-suite' ) ) );
    }
}

if ( ! has_action( 'wp_ajax_ai_suite_company_billing_save', 'ai_suite_ajax_company_billing_save' ) ) {
    add_action( 'wp_ajax_ai_suite_company_billing_save', 'ai_suite_ajax_company_billing_save' );
}


// ============================
// ATS Email Templates (Admin + Portal)
// ============================
if ( ! function_exists( 'ai_suite_ats_templates_default' ) ) {
    function ai_suite_ats_templates_default() {
        return array(
            array(
                'key'     => 'acceptat',
                'label'   => __( 'Acceptat – următorii pași', 'ai-suite' ),
                'subject' => 'Felicitări, {candidate_name}! Următorii pași pentru {job_title}',
                'body'    => "Salut {candidate_name},\n\nMulțumim pentru aplicare. Dorim să mergem mai departe cu tine pentru poziția „{job_title}”.\n\nTe rugăm să răspunzi la acest email cu disponibilitatea ta pentru un scurt interviu.\n\nMulțumim,\n{company_name}",
            ),
            array(
                'key'     => 'respins',
                'label'   => __( 'Respins – feedback politicos', 'ai-suite' ),
                'subject' => 'Update aplicație – {job_title}',
                'body'    => "Salut {candidate_name},\n\nÎți mulțumim pentru interesul acordat poziției „{job_title}”.\nÎn acest moment am decis să continuăm cu alți candidați.\n\nÎți dorim mult succes în continuare!\n{company_name}",
            ),
            array(
                'key'     => 'interviu',
                'label'   => __( 'Invitație interviu', 'ai-suite' ),
                'subject' => 'Invitație interviu – {job_title} ({interview_date})',
                'body'    => "Salut {candidate_name},\n\nTe invităm la un interviu pentru poziția „{job_title}”.\nData/ora propusă: {interview_date}\n\nConfirmă, te rog, dacă este OK pentru tine.\n\nMulțumim,\n{company_name}",
            ),
        );
    }
}

if ( ! function_exists( 'ai_suite_get_ats_templates' ) ) {
    function ai_suite_get_ats_templates() {
        $tpls = get_option( 'ai_suite_ats_email_templates', array() );
        if ( ! is_array( $tpls ) || empty( $tpls ) ) {
            $tpls = ai_suite_ats_templates_default();
        }
        // Normalize
        $out = array();
        foreach ( $tpls as $t ) {
            if ( ! is_array( $t ) ) continue;
            $key = isset( $t['key'] ) ? sanitize_key( $t['key'] ) : '';
            if ( ! $key ) continue;
            $out[] = array(
                'key'     => $key,
                'label'   => isset( $t['label'] ) ? sanitize_text_field( $t['label'] ) : $key,
                'subject' => isset( $t['subject'] ) ? sanitize_text_field( $t['subject'] ) : '',
                'body'    => isset( $t['body'] ) ? wp_kses_post( $t['body'] ) : '',
            );
        }
        if ( empty( $out ) ) {
            $out = ai_suite_ats_templates_default();
        }
        return $out;
    }
}

if ( ! function_exists( 'ai_suite_save_ats_templates' ) ) {
    function ai_suite_save_ats_templates( $tpls ) {
        if ( ! is_array( $tpls ) ) return false;
        $out = array();
        foreach ( $tpls as $t ) {
            if ( ! is_array( $t ) ) continue;
            $key = isset( $t['key'] ) ? sanitize_key( $t['key'] ) : '';
            if ( ! $key ) continue;
            $out[] = array(
                'key'     => $key,
                'label'   => isset( $t['label'] ) ? sanitize_text_field( $t['label'] ) : $key,
                'subject' => isset( $t['subject'] ) ? sanitize_text_field( $t['subject'] ) : '',
                'body'    => isset( $t['body'] ) ? wp_kses_post( $t['body'] ) : '',
            );
        }
        update_option( 'ai_suite_ats_email_templates', $out, false );
        return true;
    }
}

add_action( 'wp_ajax_ai_suite_ats_templates_get', function() {
    ai_suite_ajax_guard();
    wp_send_json_success( array( 'templates' => ai_suite_get_ats_templates() ) );
} );

add_action( 'wp_ajax_ai_suite_ats_templates_save', function() {
    ai_suite_ajax_guard();
    $raw = isset($_POST['templates']) ? wp_unslash( $_POST['templates'] ) : '';
    $tpls = json_decode( $raw, true );
    if ( ! is_array( $tpls ) ) {
        wp_send_json_error( array( 'message' => __( 'Format invalid', 'ai-suite' ) ) );
    }
    ai_suite_save_ats_templates( $tpls );
    wp_send_json_success( array( 'ok' => true ) );
} );

add_action( 'wp_ajax_ai_suite_ats_templates_portal', function() {
    // Portal guard
    $nonce = isset($_POST['nonce']) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( ! $nonce || ! wp_verify_nonce( $nonce, 'ai_suite_portal_nonce' ) ) {
        wp_send_json_error( array( 'message' => __( 'Sesiune expirată', 'ai-suite' ) ), 403 );
    }
    wp_send_json_success( array( 'ok' => true, 'templates' => ai_suite_get_ats_templates() ) );
} );


// -----------------------------------------------------------------------------
// PATCH54: Portal user preferences (filters, UI toggles) – per user meta
// -----------------------------------------------------------------------------
if ( ! function_exists( 'ai_suite_portal_pref_sanitize_value' ) ) {
	function ai_suite_portal_pref_sanitize_value( $v ) {
		if ( is_array( $v ) ) {
			$out = array();
			foreach ( $v as $k => $vv ) {
				$kk = is_string( $k ) ? sanitize_key( $k ) : $k;
				$out[ $kk ] = ai_suite_portal_pref_sanitize_value( $vv );
			}
			return $out;
		}
		if ( is_bool( $v ) ) return (bool) $v;
		if ( is_numeric( $v ) ) return (string) $v;
		return sanitize_text_field( (string) $v );
	}
}

add_action( 'wp_ajax_ai_suite_portal_pref_get', function() {
	ai_suite_portal_ajax_guard( 'company' );

	$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
	if ( ! $key ) {
		wp_send_json_error( array( 'message' => __( 'Cheie lipsă.', 'ai-suite' ) ), 400 );
	}

	// Allowlist – avoid storing arbitrary keys
	$allowed = array( 'ats_filters_v1', 'pipe_filters_v1', 'ui_prefs_v1' );
	if ( ! in_array( $key, $allowed, true ) ) {
		wp_send_json_error( array( 'message' => __( 'Cheie invalidă.', 'ai-suite' ) ), 400 );
	}

	$uid = function_exists( 'ai_suite_portal_effective_user_id' ) ? absint( ai_suite_portal_effective_user_id() ) : get_current_user_id();
	$meta_key = '_aisuite_portal_pref_' . $key;
	$value = get_user_meta( $uid, $meta_key, true );
	if ( ! is_array( $value ) ) {
		$value = array();
	}

	wp_send_json_success( array( 'ok' => true, 'value' => $value ) );
} );

add_action( 'wp_ajax_ai_suite_portal_pref_set', function() {
	ai_suite_portal_ajax_guard( 'company' );

	$key = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
	if ( ! $key ) {
		wp_send_json_error( array( 'message' => __( 'Cheie lipsă.', 'ai-suite' ) ), 400 );
	}

	$allowed = array( 'ats_filters_v1', 'pipe_filters_v1', 'ui_prefs_v1' );
	if ( ! in_array( $key, $allowed, true ) ) {
		wp_send_json_error( array( 'message' => __( 'Cheie invalidă.', 'ai-suite' ) ), 400 );
	}

	$uid = function_exists( 'ai_suite_portal_effective_user_id' ) ? absint( ai_suite_portal_effective_user_id() ) : get_current_user_id();
	$meta_key = '_aisuite_portal_pref_' . $key;

	$value = array();
	if ( isset( $_POST['value'] ) ) {
		$value = wp_unslash( $_POST['value'] ); // may be nested
	}
	$value = ai_suite_portal_pref_sanitize_value( $value );

	update_user_meta( $uid, $meta_key, $value );

	wp_send_json_success( array( 'ok' => true ) );
} );
