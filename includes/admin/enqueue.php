<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Enqueue admin assets for AI Suite.
 *
 * Scop: să avem un singur loc care încarcă CSS/JS premium pentru toate taburile.
 */

if ( ! function_exists( 'ai_suite_admin_enqueue_assets' ) ) {
	function ai_suite_admin_enqueue_assets( $hook ) {
		// Doar pe pagina pluginului.
		if ( 'toplevel_page_ai-suite' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'ai-suite-admin', AI_SUITE_URL . 'assets/admin.css', array(), AI_SUITE_VER );
		wp_enqueue_style( 'ai-suite-premium', AI_SUITE_URL . 'assets/premium/aisuite-premium.css', array( 'ai-suite-admin' ), AI_SUITE_VER );
		wp_enqueue_script( 'ai-suite-admin', AI_SUITE_URL . 'assets/admin.js', array( 'jquery' ), AI_SUITE_VER, true );

		// Kanban (pipeline) – folosit atât în admin cât și în alte view-uri.
		wp_enqueue_script( 'ai-suite-kanban', AI_SUITE_URL . 'assets/kanban.js', array( 'jquery' ), AI_SUITE_VER, true );
		wp_enqueue_script( 'ai-suite-premium-ui', AI_SUITE_URL . 'assets/premium/aisuite-ui.js', array(), AI_SUITE_VER, true );
		wp_enqueue_script( 'ai-suite-chart', AI_SUITE_URL . 'assets/premium/vendor/chart.min.js', array(), AI_SUITE_VER, true );

		wp_localize_script(
			'ai-suite-admin',
			'AI_Suite_Admin',
			array(
				'ajax'  => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'ai_suite_nonce' ),
			)
		);
	}
	add_action( 'admin_enqueue_scripts', 'ai_suite_admin_enqueue_assets' );
}
