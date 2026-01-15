<?php
/**
 * Healthcheck bot for AI Suite.
 *
 * Runs system checks (OpenAI key/connectivity, cron, permissions, versions) and can alert by email
 * on scheduled failures (minimal involvement for admin).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AI_Suite_Bot_Healthcheck' ) ) {
    final class AI_Suite_Bot_Healthcheck implements AI_Suite_Bot_Interface {

        public function key() {
            return 'healthcheck';
        }

        public function label() {
            return __( 'Bot de verificare sistem', 'ai-suite' );
        }

        /**
         * Run healthcheck.
         *
         * Args:
         * - mode: 'openai_test' to run live OpenAI test only
         * - source: 'cron' when run from scheduler
         */
        public function run( array $args = array() ) {
            $mode   = ! empty( $args['mode'] ) ? (string) $args['mode'] : '';
            $source = ! empty( $args['source'] ) ? (string) $args['source'] : 'manual';

            // Button: "Testează OpenAI" – live connectivity check.
            if ( 'openai_test' === $mode ) {
                $check  = $this->check_openai_live();
                $ok     = ! empty( $check['ok'] );
                $result = array(
                    'ok'      => $ok,
                    'message' => $ok ? __( 'OpenAI: conexiune OK', 'ai-suite' ) : __( 'OpenAI: eroare la conectare', 'ai-suite' ),
                    'data'    => array( 'checks' => array( $check ) ),
                );

                if ( function_exists( 'aisuite_log' ) ) {
                    aisuite_log( $ok ? 'info' : 'warning', __( 'Test OpenAI executat', 'ai-suite' ), $result );
                }
                if ( function_exists( 'aisuite_record_run' ) ) {
                    aisuite_record_run( $this->key(), $result );
                }

                return $result;
            }

            $checks   = array();
            $checks[] = $this->check_openai_key();
            $checks[] = $this->check_cron();
            $checks[] = $this->check_permissions();
            $checks[] = $this->check_versions();

            $ok = true;
            foreach ( $checks as $c ) {
                if ( empty( $c['ok'] ) ) {
                    $ok = false;
                    break;
                }
            }

            $result = array(
                'ok'      => $ok,
                'message' => $ok ? __( 'Verificare sistem: OK', 'ai-suite' ) : __( 'Verificare sistem: Probleme detectate', 'ai-suite' ),
                'data'    => array( 'checks' => $checks ),
            );

            if ( function_exists( 'aisuite_log' ) ) {
                aisuite_log( $ok ? 'info' : 'warning', __( 'Verificare sistem executată', 'ai-suite' ), $result );
            }
            if ( function_exists( 'aisuite_record_run' ) ) {
                aisuite_record_run( $this->key(), $result );
            }

            // Minimal involvement: if cron run failed, email admin (throttled).
            if ( 'cron' === $source && ! $ok ) {
                $this->maybe_send_alert_email( $result );
            }

            return $result;
        }

        private function check_openai_key() {
            if ( ! function_exists( 'aisuite_get_settings' ) ) {
                return array(
                    'ok'      => false,
                    'title'   => __( 'Cheie API OpenAI', 'ai-suite' ),
                    'details' => __( 'Funcția aisuite_get_settings() lipsește. Reinstalează / repară pluginul.', 'ai-suite' ),
                );
            }
            $settings = (array) aisuite_get_settings();
            $has_key  = ! empty( $settings['openai_api_key'] );
            return array(
                'ok'      => $has_key,
                'title'   => __( 'Cheie API OpenAI', 'ai-suite' ),
                'details' => $has_key ? __( 'Cheia API este setată.', 'ai-suite' ) : __( 'Cheia API OpenAI lipsește în setări.', 'ai-suite' ),
            );
        }

        private function check_openai_live() {
            if ( ! function_exists( 'aisuite_get_settings' ) ) {
                return array(
                    'ok'      => false,
                    'title'   => __( 'OpenAI (Live Test)', 'ai-suite' ),
                    'details' => __( 'Funcția aisuite_get_settings() lipsește.', 'ai-suite' ),
                );
            }

            $settings = (array) aisuite_get_settings();
            $has_key  = ! empty( $settings['openai_api_key'] );
            if ( ! $has_key ) {
                return array(
                    'ok'      => false,
                    'title'   => __( 'OpenAI (Live Test)', 'ai-suite' ),
                    'details' => __( 'Cheia API OpenAI lipsește în setări.', 'ai-suite' ),
                );
            }

            if ( function_exists( 'ai_suite_openai_test_connection' ) ) {
                $t  = ai_suite_openai_test_connection();
                $ok = ! empty( $t['ok'] );
                $details = $ok
                    ? sprintf( __( 'Conexiune OK (model: %s).', 'ai-suite' ), esc_html( (string) ( $t['model'] ?? '' ) ) )
                    : ( ! empty( $t['error'] ) ? ( __( 'Eroare: ', 'ai-suite' ) . (string) $t['error'] ) : __( 'Eroare necunoscută.', 'ai-suite' ) );

                return array(
                    'ok'      => $ok,
                    'title'   => __( 'OpenAI (Live Test)', 'ai-suite' ),
                    'details' => $details,
                    'data'    => $t,
                );
            }

            // Fallback: key exists but test function missing.
            return array(
                'ok'      => true,
                'title'   => __( 'OpenAI (Live Test)', 'ai-suite' ),
                'details' => __( 'Cheia este setată, dar funcția de test nu este disponibilă.', 'ai-suite' ),
            );
        }

        private function check_cron() {
            if ( ! function_exists( 'wp_next_scheduled' ) ) {
                return array(
                    'ok'      => false,
                    'title'   => __( 'WP‑Cron', 'ai-suite' ),
                    'details' => __( 'wp_next_scheduled() lipsește.', 'ai-suite' ),
                );
            }
            $ts = wp_next_scheduled( 'ai_suite_cron_48h' );
            return array(
                'ok'      => ! empty( $ts ),
                'title'   => __( 'WP‑Cron', 'ai-suite' ),
                'details' => $ts ? __( 'Cronul este programat.', 'ai-suite' ) : __( 'Evenimentul cron nu este programat.', 'ai-suite' ),
            );
        }

        private function check_permissions() {
            if ( ! function_exists( 'current_user_can' ) ) {
                return array(
                    'ok'      => false,
                    'title'   => __( 'Permisiuni administrator', 'ai-suite' ),
                    'details' => __( 'current_user_can() indisponibil.', 'ai-suite' ),
                );
            }
            $ok = current_user_can( 'manage_ai_suite' );
            return array(
                'ok'      => $ok,
                'title'   => __( 'Permisiuni administrator', 'ai-suite' ),
                'details' => $ok ? __( 'Utilizatorul curent are permisiuni suficiente.', 'ai-suite' ) : __( 'Utilizatorul nu are capabilitatea manage_ai_suite.', 'ai-suite' ),
            );
        }

        private function check_versions() {
            global $wp_version;
            $plugin_version = defined( 'AI_SUITE_VER' ) ? AI_SUITE_VER : 'unknown';
            $wpv = isset( $wp_version ) ? $wp_version : get_bloginfo( 'version' );
            return array(
                'ok'      => true,
                'title'   => __( 'Versiuni', 'ai-suite' ),
                'details' => sprintf(
                    /* translators: 1: plugin version, 2: WordPress version */
                    __( 'Versiunea AI Suite: %1$s, versiunea WordPress: %2$s', 'ai-suite' ),
                    esc_html( (string) $plugin_version ),
                    esc_html( (string) $wpv )
                ),
            );
        }

        /**
         * Send a throttled email alert when scheduled healthcheck fails.
         */
        private function maybe_send_alert_email( array $result ) {
            if ( ! function_exists( 'wp_mail' ) ) {
                return;
            }

            // Throttle: at most one alert per 6 hours.
            $last = (int) get_option( 'ai_suite_last_health_alert', 0 );
            if ( $last && ( time() - $last ) < ( 6 * HOUR_IN_SECONDS ) ) {
                return;
            }

            $settings = function_exists( 'aisuite_get_settings' ) ? (array) aisuite_get_settings() : array();

            $emails = array();
            $admin_email = (string) get_option( 'admin_email' );
            if ( $admin_email ) {
                $emails[] = sanitize_email( $admin_email );
            }

            // Optional extra recipients (comma/space separated) in settings: alert_emails.
            if ( ! empty( $settings['alert_emails'] ) && is_string( $settings['alert_emails'] ) ) {
                $parts = preg_split( '/[;,\s]+/', (string) $settings['alert_emails'] );
                if ( is_array( $parts ) ) {
                    foreach ( $parts as $e ) {
                        $e = sanitize_email( trim( (string) $e ) );
                        if ( $e ) {
                            $emails[] = $e;
                        }
                    }
                }
            }

            $emails = array_values( array_unique( array_filter( $emails ) ) );
            if ( empty( $emails ) ) {
                return;
            }

            $failed = array();
            if ( ! empty( $result['data']['checks'] ) && is_array( $result['data']['checks'] ) ) {
                foreach ( $result['data']['checks'] as $c ) {
                    if ( empty( $c['ok'] ) ) {
                        $title   = ! empty( $c['title'] ) ? (string) $c['title'] : 'Check';
                        $details = ! empty( $c['details'] ) ? (string) $c['details'] : '';
                        $failed[] = $title . ( $details ? ( ' — ' . $details ) : '' );
                    }
                }
            }

            $host = parse_url( home_url(), PHP_URL_HOST );
            $subject = sprintf( '[AI Suite] Probleme detectate (Healthcheck) — %s', $host ? $host : 'site' );

            $body  = "S-au detectat probleme la verificarea automată (Healthcheck).\n\n";
            if ( ! empty( $failed ) ) {
                $body .= "Probleme:\n- " . implode( "\n- ", $failed ) . "\n\n";
            } else {
                $body .= "Detalii indisponibile.\n\n";
            }

            $body .= "Deschide Dashboard → AI Suite → Verificare sistem:\n" . admin_url( 'admin.php?page=ai-suite&tab=healthcheck' ) . "\n\n";
            $body .= "(Mesaj automat)";

            update_option( 'ai_suite_last_health_alert', time(), false );
            wp_mail( $emails, $subject, $body );
        }
    }
}
