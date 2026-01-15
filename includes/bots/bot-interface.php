<?php
/**
 * Interface for AI Suite bots.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! interface_exists( 'AI_Suite_Bot_Interface' ) ) {
    interface AI_Suite_Bot_Interface {
        /**
         * Bot key.
         *
         * @return string
         */
        public function key();

        /**
         * Bot label.
         *
         * @return string
         */
        public function label();

        /**
         * Run the bot.
         *
         * @param array $args Optional arguments.
         *
         * @return array Associative array with keys: ok (bool), message (string), data (array)
         */
        public function run( array $args = array() );
    }
}