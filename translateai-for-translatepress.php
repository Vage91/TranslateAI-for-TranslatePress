<?php
/**
 * Plugin Name:       TranslateAI for TranslatePress
 * Plugin URI:        https://github.com/your-username/translateai-for-translatepress
 * Description:       AI-powered automatic translation for TranslatePress using local Ollama models. Supports dual-agent mode (translator + judge) and translator-only mode, with batch processing, site context, and multi-endpoint support.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Your Name
 * Author URI:        https://yourwebsite.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       translateai-for-translatepress
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------
define( 'TAITP_VERSION',        '1.0.0' );
define( 'TAITP_NONCE_ACTION',   'taitp_secure_ajax' );
define( 'TAITP_NONCE_KEY',      'security' );
define( 'TAITP_STATUS_DONE',    2 );
define( 'TAITP_STATUS_SKIPPED', 9 );
define( 'TAITP_API_TIMEOUT',    120 );
define( 'TAITP_TEST_TIMEOUT',   10 );
define( 'TAITP_PLUGIN_DIR',     plugin_dir_path( __FILE__ ) );
define( 'TAITP_PLUGIN_URL',     plugin_dir_url( __FILE__ ) );

// ---------------------------------------------------------------------------
// Activation: check TranslatePress dependency
// ---------------------------------------------------------------------------
register_activation_hook( __FILE__, 'taitp_activation_check' );

function taitp_activation_check(): void {
	if ( ! is_plugin_active( 'translatepress-multilingual/index.php' ) &&
		 ! is_plugin_active( 'translatepress-business/index.php' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die(
			esc_html__( 'TranslateAI for TranslatePress requires the TranslatePress plugin to be installed and active.', 'translateai-for-translatepress' ),
			esc_html__( 'Plugin Activation Error', 'translateai-for-translatepress' ),
			[ 'back_link' => true ]
		);
	}
}

// Admin notice if TranslatePress is deactivated after plugin is active
add_action( 'admin_notices', 'taitp_dependency_notice' );

function taitp_dependency_notice(): void {
	if ( is_plugin_active( 'translatepress-multilingual/index.php' ) ||
		 is_plugin_active( 'translatepress-business/index.php' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>';
	printf(
		/* translators: %s: TranslatePress plugin name */
		esc_html__( 'TranslateAI for TranslatePress requires %s to be installed and active.', 'translateai-for-translatepress' ),
		'<strong>TranslatePress</strong>'
	);
	echo '</p></div>';
}

// ---------------------------------------------------------------------------
// Load plugin files
// ---------------------------------------------------------------------------
require_once TAITP_PLUGIN_DIR . 'includes/class-ollama-client.php';
require_once TAITP_PLUGIN_DIR . 'includes/class-translation-engine.php';
require_once TAITP_PLUGIN_DIR . 'includes/class-plugin.php';

new TAITP_Plugin();
