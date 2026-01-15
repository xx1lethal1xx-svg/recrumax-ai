<?php
/**
 * Bot registry for AI Suite.
 *
 * Maintains list of available bots, their status and class names.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AI_Suite_Registry' ) ) {
    final class AI_Suite_Registry {

        /**
         * Retrieve all bots from the registry.
         *
         * @return array
         */
        public static function get_all() {
            $data = get_option( AI_SUITE_OPTION_REGISTRY, array() );
            return is_array( $data ) ? $data : array();
        }

        /**
         * Persist bots to registry.
         *
         * @param array $data Registry data.
         */
        public static function set_all( array $data ) {
            update_option( AI_SUITE_OPTION_REGISTRY, $data, false );
        }

        /**
         * Ensure a bot exists in the registry.
         *
         * @param string $key   Bot identifier.
         * @param string $label Human-readable label.
         * @param string $class Class implementing the bot.
         */
        public static function ensure_bot( $key, $label, $class ) {
            $all = self::get_all();
            if ( ! isset( $all[ $key ] ) ) {
                $all[ $key ] = array(
                    'label'       => $label,
                    'class'       => $class,
                    'enabled'     => true,
                    'last_run'    => null,
                    'last_status' => null,
                );
                self::set_all( $all );
            }
        }

        /**
         * Check if a bot is enabled.
         *
         * @param string $key Bot key.
         *
         * @return bool
         */
        public static function is_enabled( $key ) {
            $all = self::get_all();
            return isset( $all[ $key ]['enabled'] ) ? (bool) $all[ $key ]['enabled'] : false;
        }

        /**
         * Toggle a bot's enabled status.
         *
         * @param string $key     Bot key.
         * @param bool   $enabled Desired status.
         *
         * @return bool
         */
        public static function toggle( $key, $enabled ) {
            $all = self::get_all();
            if ( ! isset( $all[ $key ] ) ) {
                return false;
            }
            $all[ $key ]['enabled'] = (bool) $enabled;
            self::set_all( $all );
            return true;
        }

        /**
         * Register default bots.
         */
        public static function register_defaults() {
            // Use Romanian labels when registering default bots.
            self::ensure_bot( 'healthcheck', __( 'Bot de verificare sistem', 'ai-suite' ), 'AI_Suite_Bot_Healthcheck' );
            self::ensure_bot( 'manager',    __( 'Manager boți AI', 'ai-suite' ),     'AI_Suite_Bot_Manager' );
            self::ensure_bot( 'content',    __( 'Bot de conținut AI', 'ai-suite' ),  'AI_Suite_Bot_Content' );
            self::ensure_bot( 'social',     __( 'Bot social AI', 'ai-suite' ),        'AI_Suite_Bot_Social' );
        }
    }
}