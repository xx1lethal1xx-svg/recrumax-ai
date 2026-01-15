<?php
/**
 * Manager bot for AI Suite.
 *
 * Allows orchestrating and summarising other bots.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AI_Suite_Bot_Manager' ) ) {
    final class AI_Suite_Bot_Manager implements AI_Suite_Bot_Interface {

        public function key() {
            return 'manager';
        }

        public function label() {
            return __( 'Manager boți AI', 'ai-suite' );
        }

        public function run( array $args = array() ) {
            // If 'run_all' flag is passed, run all enabled bots sequentially.
            $all  = AI_Suite_Registry::get_all();
            $runs = array();
            if ( ! empty( $args['run_all'] ) ) {
                foreach ( $all as $key => $bot ) {
                    if ( $key === $this->key() ) {
                        continue; // Don't run itself.
                    }
                    if ( ! empty( $bot['enabled'] ) && class_exists( $bot['class'] ) ) {
                        $inst = new $bot['class']();
                        if ( $inst instanceof AI_Suite_Bot_Interface ) {
                            try {
                                $runs[ $key ] = $inst->run();
                            } catch ( \Throwable $e ) {
                                $runs[ $key ] = array(
                                    'ok'      => false,
                                    'message' => 'Exception: ' . $e->getMessage(),
                                    'data'    => array(),
                                );
                            }
                        }
                    }
                }
            }

            // Summarise available bots.
            $summary = array();
            foreach ( $all as $key => $bot ) {
                $summary[] = array(
                    'key'        => $key,
                    'label'      => $bot['label'],
                    'enabled'    => ! empty( $bot['enabled'] ),
                    'last_run'   => isset( $bot['last_run'] ) ? $bot['last_run'] : null,
                    'last_status'=> isset( $bot['last_status'] ) ? $bot['last_status'] : null,
                );
            }

            $result = array(
                'ok'      => true,
                'message' => __( 'Rezumat manager pregătit.', 'ai-suite' ),
                'data'    => array(
                    'summary' => $summary,
                    'runs'    => $runs,
                ),
            );

            // Record run in logs.
            if ( function_exists( 'aisuite_log' ) ) {
                // Log message in Romanian.
                aisuite_log( 'info', __( 'Botul manager a fost executat', 'ai-suite' ), $result );
            }
            if ( function_exists( 'aisuite_record_run' ) ) {
                aisuite_record_run( $this->key(), $result );
            }

            return $result;
        }
    }
}