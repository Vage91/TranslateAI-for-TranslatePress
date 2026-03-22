<?php
/**
 * Main Plugin Class
 *
 * Registers hooks, admin menu, settings, AJAX handlers, and renders the
 * settings page for TranslateAI for TranslatePress.
 *
 * @package TranslateAI_For_TranslatePress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TAITP_Plugin {

	public function __construct() {
		add_action( 'admin_menu',            [ $this, 'add_menu' ] );
		add_action( 'admin_init',            [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_post_taitp_reset_db',   [ $this, 'handle_db_reset' ] );
		add_action( 'admin_post_taitp_deep_clean', [ $this, 'handle_deep_clean' ] );
		add_action( 'wp_ajax_taitp_translate_step',        [ $this, 'ajax_translate_step' ] );
		add_action( 'wp_ajax_taitp_test_connection',       [ $this, 'ajax_test_connection' ] );
		add_action( 'wp_ajax_taitp_test_judge_connection', [ $this, 'ajax_test_judge_connection' ] );
	}

	// -----------------------------------------------------------------------
	// Assets
	// -----------------------------------------------------------------------

	public function enqueue_assets( string $hook ): void {
		if ( 'settings_page_translateai-for-translatepress' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'taitp-admin',
			TAITP_PLUGIN_URL . 'assets/css/admin.css',
			[],
			TAITP_VERSION
		);

		wp_enqueue_script(
			'taitp-admin',
			TAITP_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			TAITP_VERSION,
			true
		);

		// Build table → language map for JS auto-fill
		$available_tables = $this->get_available_tables();
		$table_lang_map   = [];
		foreach ( $available_tables as $t ) {
			$table_lang_map[ $t ] = $this->parse_table_languages( $t );
		}

		// Compute total strings for the progress bar
		$table = $this->get_dict_table();
		$total = 0;
		if ( ! empty( $table ) ) {
			global $wpdb;
			$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( $table_exists ) {
				$safe_table = esc_sql( $table );
				$total      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$safe_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}

		wp_localize_script( 'taitp-admin', 'tailtpData', [
			'nonce'         => wp_create_nonce( TAITP_NONCE_ACTION ),
			'total'         => $total,
			'tableLangMap'  => $table_lang_map,
			'selectedTable' => get_option( 'taitp_selected_table', '' ),
			'sourceLang'    => get_option( 'taitp_source_lang', 'Italian' ),
			'targetLang'    => get_option( 'taitp_target_lang', 'English' ),
			'version'       => TAITP_VERSION,
			'strings'       => [
				'skipped'              => __( 'skipped', 'translateai-for-translatepress' ),
				'processingStarted'    => __( 'Processing started…', 'translateai-for-translatepress' ),
				'processingStopped'    => __( 'Processing stopped by user.', 'translateai-for-translatepress' ),
				'invalidResponse'      => __( 'Invalid response – retrying in 3s…', 'translateai-for-translatepress' ),
				'translationCompleted' => __( 'Translation completed.', 'translateai-for-translatepress' ),
				'networkError'         => __( 'Network error – retrying in 5s…', 'translateai-for-translatepress' ),
				'original'             => __( 'Original', 'translateai-for-translatepress' ),
				'translation'          => __( 'Translation', 'translateai-for-translatepress' ),
				'reason'               => __( 'Reason:', 'translateai-for-translatepress' ),
				'judge'                => __( 'Judge:', 'translateai-for-translatepress' ),
				'approved'             => __( 'Approved', 'translateai-for-translatepress' ),
				'rejected'             => __( 'Rejected', 'translateai-for-translatepress' ),
				'translatorOk'         => __( 'Translator OK', 'translateai-for-translatepress' ),
				'translatorConnError'  => __( 'Translator: connection error', 'translateai-for-translatepress' ),
				'translatorReqFailed'  => __( 'Translator: request failed', 'translateai-for-translatepress' ),
				'judgeOk'              => __( 'Judge OK', 'translateai-for-translatepress' ),
				'judgeConnError'       => __( 'Judge: connection error', 'translateai-for-translatepress' ),
				'judgeReqFailed'       => __( 'Judge: request failed', 'translateai-for-translatepress' ),
				'testingJudge'         => __( 'Testing Judge…', 'translateai-for-translatepress' ),
				'connectionOk'         => __( 'Connection OK', 'translateai-for-translatepress' ),
				'lastAttempted'        => __( 'Last attempted translation', 'translateai-for-translatepress' ),
				'skippedReport'        => __( 'Skipped Strings Report', 'translateai-for-translatepress' ),
				'generatedOn'          => __( 'Generated on', 'translateai-for-translatepress' ),
				'totalSkipped'         => __( 'Total skipped:', 'translateai-for-translatepress' ),
				'skippedLabel'         => __( 'Skipped', 'translateai-for-translatepress' ),
				'pluginName'           => __( 'TranslateAI for TranslatePress', 'translateai-for-translatepress' ),
				'modeDescTranslatorOnly' => __( '🤖 The translator model generates the translation and saves it directly after structural validation. Faster and ideal for most use cases.', 'translateai-for-translatepress' ),
				'modeDescFull'           => __( '🤖⚖️ The translator generates the translation, then a second judge model validates quality before saving. Slower but with an extra layer of quality control.', 'translateai-for-translatepress' ),
			],
		] );
	}

	// -----------------------------------------------------------------------
	// Settings
	// -----------------------------------------------------------------------

	public function register_settings(): void {
		$options = [
			'taitp_main_model',
			'taitp_judge_model',
			'taitp_api_url',
			'taitp_api_key',
			'taitp_api_service',
			'taitp_source_lang',
			'taitp_target_lang',
			'taitp_selected_table',
			'taitp_batch_delay_ms',
			'taitp_judge_api_url',
			'taitp_judge_api_key',
			'taitp_judge_api_service',
			'taitp_mode',
		];
		foreach ( $options as $option ) {
			register_setting( 'taitp_opts', $option, [ 'sanitize_callback' => 'sanitize_text_field' ] );
		}
		register_setting( 'taitp_opts', 'taitp_batch_enabled',          [ 'sanitize_callback' => 'absint' ] );
		register_setting( 'taitp_opts', 'taitp_batch_size',             [ 'sanitize_callback' => 'absint' ] );
		register_setting( 'taitp_opts', 'taitp_batch_delay_ms',         [ 'sanitize_callback' => 'absint' ] );
		register_setting( 'taitp_opts', 'taitp_judge_endpoint_enabled', [ 'sanitize_callback' => 'absint' ] );
		register_setting( 'taitp_opts', 'taitp_site_context', [
			'sanitize_callback' => function ( string $v ): string {
				return sanitize_textarea_field( wp_unslash( $v ) );
			},
		] );
	}

	public function add_menu(): void {
		add_options_page(
			__( 'TranslateAI for TranslatePress', 'translateai-for-translatepress' ),
			__( 'TranslateAI', 'translateai-for-translatepress' ),
			'manage_options',
			'translateai-for-translatepress',
			[ $this, 'settings_page' ]
		);
	}

	// -----------------------------------------------------------------------
	// Admin POST handlers
	// -----------------------------------------------------------------------

	public function handle_db_reset(): void {
		$this->verify_admin_action( 'taitp_reset_action' );
		global $wpdb;
		$table = $this->get_dict_table();
		if ( empty( $table ) ) {
			wp_die( esc_html__( 'Invalid table.', 'translateai-for-translatepress' ) );
		}
		$safe_table = esc_sql( $table );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "UPDATE {$safe_table} SET translated = original, status = 0" );
		wp_safe_redirect( admin_url( 'options-general.php?page=translateai-for-translatepress&reset=success' ) );
		exit;
	}

	public function handle_deep_clean(): void {
		$this->verify_admin_action( 'taitp_clean_action' );
		global $wpdb;
		$table = $this->get_dict_table();
		if ( empty( $table ) ) {
			wp_die( esc_html__( 'Invalid table.', 'translateai-for-translatepress' ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_taitp_retry_%'" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_taitp_retry_%'" );

		$safe_table = esc_sql( $table );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$affected = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$safe_table} SET status = 0 WHERE status = %d", TAITP_STATUS_SKIPPED ) );

		wp_safe_redirect( admin_url( 'options-general.php?page=translateai-for-translatepress&cleaned=' . $affected ) );
		exit;
	}

	// -----------------------------------------------------------------------
	// AJAX handlers
	// -----------------------------------------------------------------------

	public function ajax_test_connection(): void {
		check_ajax_referer( TAITP_NONCE_ACTION, TAITP_NONCE_KEY );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		$api_url = get_option( 'taitp_api_url', '' );
		$model   = sanitize_text_field( wp_unslash( $_POST['model'] ?? '' ) );

		if ( empty( $api_url ) || empty( $model ) ) {
			wp_send_json_error( [ 'message' => 'Missing parameters' ] );
		}

		$client = new TAITP_Ollama_Client( $api_url, get_option( 'taitp_api_key', '' ), get_option( 'taitp_api_service', 'ollama' ) );
		$client->test( $model ) ? wp_send_json_success() : wp_send_json_error();
	}

	public function ajax_test_judge_connection(): void {
		check_ajax_referer( TAITP_NONCE_ACTION, TAITP_NONCE_KEY );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		$api_url = get_option( 'taitp_judge_api_url', '' );
		$model   = sanitize_text_field( wp_unslash( $_POST['model'] ?? '' ) );

		if ( empty( $api_url ) || empty( $model ) ) {
			wp_send_json_error( [ 'message' => 'Missing parameters' ] );
		}

		$client = new TAITP_Ollama_Client( $api_url, get_option( 'taitp_judge_api_key', '' ), get_option( 'taitp_judge_api_service', 'ollama' ) );
		$client->test( $model ) ? wp_send_json_success() : wp_send_json_error();
	}

	public function ajax_translate_step(): void {
		check_ajax_referer( TAITP_NONCE_ACTION, TAITP_NONCE_KEY );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		global $wpdb;
		$table = $this->get_dict_table();

		if ( empty( $table ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid or unauthorized table.', 'translateai-for-translatepress' ) ] );
		}

		$is_batch   = (bool) get_option( 'taitp_batch_enabled' );
		$batch_size = max( 1, min( 15, (int) get_option( 'taitp_batch_size', 3 ) ) );
		$delay_ms   = max( 0, min( 5000, (int) get_option( 'taitp_batch_delay_ms', 500 ) ) );
		$limit      = $is_batch ? $batch_size : 1;

		$safe_table = esc_sql( $table );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id AS dict_row_id, original_id, original FROM {$safe_table} WHERE status NOT IN (%d, %d) ORDER BY id ASC LIMIT %d",
				TAITP_STATUS_DONE,
				TAITP_STATUS_SKIPPED,
				$limit
			)
		);

		if ( ! $rows ) {
			$counts = $this->get_counts();
			wp_send_json_success( [ 'finished' => true, 'done_count' => $counts['done'] + $counts['skipped'], 'counts' => $counts ] );
		}

		$engine       = $this->build_engine();
		$results      = [];
		$current_done = $this->get_done_count();
		$total_rows   = count( $rows );

		foreach ( $rows as $index => $row ) {
			$result       = $engine->process( $row, $table, $current_done );
			$current_done = $result['done_count'];
			$results[]    = $result;

			if ( $is_batch && $delay_ms > 0 && $index < $total_rows - 1 ) {
				usleep( $delay_ms * 1000 );
			}
		}

		$counts = $this->get_counts();
		if ( $is_batch ) {
			$results[ count( $results ) - 1 ]['counts'] = $counts;
			wp_send_json_success( $results );
		} else {
			$results[0]['counts'] = $counts;
			wp_send_json_success( $results[0] );
		}
	}

	// -----------------------------------------------------------------------
	// Settings page
	// -----------------------------------------------------------------------

	public function settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table            = $this->get_dict_table();
		$available_tables = $this->get_available_tables();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$table_exists     = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;

		$total   = 0;
		$done    = 0;
		$skipped = 0;
		$percent = 0;

		if ( $table_exists ) {
			$safe_table = esc_sql( $table );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$safe_table}" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$done    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$safe_table} WHERE status = %d", TAITP_STATUS_DONE ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$skipped = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$safe_table} WHERE status = %d", TAITP_STATUS_SKIPPED ) );
			$percent = $total > 0 ? round( ( ( $done + $skipped ) / $total ) * 100 ) : 0;
		}

		$batch_enabled    = (bool) get_option( 'taitp_batch_enabled' );
		$batch_delay_ms   = (int) get_option( 'taitp_batch_delay_ms', 500 );
		$judge_ep_enabled = (bool) get_option( 'taitp_judge_endpoint_enabled' );

		// Build table → language map (also used in the view's table selector)
		$table_lang_map = [];
		foreach ( $available_tables as $t ) {
			$table_lang_map[ $t ] = $this->parse_table_languages( $t );
		}

		include TAITP_PLUGIN_DIR . 'admin/views/settings-page.php';
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	private function get_dict_table(): string {
		global $wpdb;
		$selected = sanitize_key( get_option( 'taitp_selected_table', 'trp_dictionary_it_it_en_gb' ) );

		if ( strpos( $selected, 'trp_dictionary_' ) !== 0 || strpos( $selected, 'trp_gettext' ) !== false ) {
			return '';
		}

		return $wpdb->prefix . $selected;
	}

	private function get_available_tables(): array {
		global $wpdb;
		$prefix  = $wpdb->esc_like( $wpdb->prefix . 'trp_dictionary_' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLES LIKE %s', $prefix . '%' ), ARRAY_N );
		$tables  = [];
		foreach ( $results as $row ) {
			$name = str_replace( $wpdb->prefix, '', $row[0] );
			if ( strpos( $name, 'trp_gettext' ) !== false ) {
				continue;
			}
			$tables[] = $name;
		}
		return $tables;
	}

	private function get_done_count(): int {
		global $wpdb;
		$safe_table = esc_sql( $this->get_dict_table() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT COUNT(*) FROM {$safe_table} WHERE status IN (%d, %d)",
				TAITP_STATUS_DONE,
				TAITP_STATUS_SKIPPED
			)
		);
	}

	/**
	 * @return array{done: int, skipped: int, pending: int, total: int}
	 */
	private function get_counts(): array {
		global $wpdb;
		$safe_table = esc_sql( $this->get_dict_table() );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$safe_table}" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$done    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$safe_table} WHERE status = %d", TAITP_STATUS_DONE ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$skipped = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$safe_table} WHERE status = %d", TAITP_STATUS_SKIPPED ) );
		return [
			'done'    => $done,
			'skipped' => $skipped,
			'pending' => max( 0, $total - $done - $skipped ),
			'total'   => $total,
		];
	}

	private function build_engine(): TAITP_Translation_Engine {
		$translator_client = new TAITP_Ollama_Client(
			get_option( 'taitp_api_url', 'http://localhost:11434/api/generate' ),
			get_option( 'taitp_api_key', '' ),
			get_option( 'taitp_api_service', 'ollama' )
		);

		$judge_client = null;
		if ( (bool) get_option( 'taitp_judge_endpoint_enabled' ) ) {
			$judge_api_url = get_option( 'taitp_judge_api_url', '' );
			if ( ! empty( $judge_api_url ) ) {
				$judge_client = new TAITP_Ollama_Client(
					$judge_api_url,
					get_option( 'taitp_judge_api_key', '' ),
					get_option( 'taitp_judge_api_service', 'ollama' )
				);
			}
		}

		return new TAITP_Translation_Engine(
			$translator_client,
			get_option( 'taitp_main_model', 'mistral-small:22b' ),
			get_option( 'taitp_judge_model', 'mistral-small:22b' ),
			get_option( 'taitp_source_lang', 'Italian' ),
			get_option( 'taitp_target_lang', 'English' ),
			3,
			(string) get_option( 'taitp_site_context', '' ),
			$judge_client,
			get_option( 'taitp_mode', 'full' )
		);
	}

	private function verify_admin_action( string $nonce_action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'translateai-for-translatepress' ), 403 );
		}
		check_admin_referer( $nonce_action );
	}

	private function locale_to_language( string $locale ): string {
		$map = [
			'af'    => 'Afrikaans',
			'ar'    => 'Arabic',    'ar_ar' => 'Arabic',
			'az'    => 'Azerbaijani',
			'be'    => 'Belarusian',
			'bg'    => 'Bulgarian', 'bg_bg' => 'Bulgarian',
			'bn'    => 'Bengali',
			'bs'    => 'Bosnian',
			'ca'    => 'Catalan',
			'cs'    => 'Czech',     'cs_cz' => 'Czech',
			'cy'    => 'Welsh',
			'da'    => 'Danish',    'da_dk' => 'Danish',
			'de'    => 'German',    'de_at' => 'German (Austria)', 'de_ch' => 'German (Switzerland)', 'de_de' => 'German',
			'el'    => 'Greek',     'el_gr' => 'Greek',
			'en'    => 'English',   'en_au' => 'English (Australia)', 'en_ca' => 'English (Canada)',
			'en_gb' => 'English (UK)', 'en_nz' => 'English (New Zealand)', 'en_us' => 'English (US)',
			'eo'    => 'Esperanto',
			'es'    => 'Spanish',   'es_ar' => 'Spanish (Argentina)', 'es_es' => 'Spanish (Spain)', 'es_mx' => 'Spanish (Mexico)',
			'et'    => 'Estonian',  'et_ee' => 'Estonian',
			'eu'    => 'Basque',
			'fa'    => 'Persian',   'fa_ir' => 'Persian',
			'fi'    => 'Finnish',   'fi_fi' => 'Finnish',
			'fr'    => 'French',    'fr_be' => 'French (Belgium)', 'fr_ca' => 'French (Canada)', 'fr_fr' => 'French',
			'gl'    => 'Galician',
			'he'    => 'Hebrew',    'he_il' => 'Hebrew',
			'hi'    => 'Hindi',     'hi_in' => 'Hindi',
			'hr'    => 'Croatian',  'hr_hr' => 'Croatian',
			'hu'    => 'Hungarian', 'hu_hu' => 'Hungarian',
			'hy'    => 'Armenian',
			'id'    => 'Indonesian', 'id_id' => 'Indonesian',
			'is'    => 'Icelandic',
			'it'    => 'Italian',   'it_it' => 'Italian',
			'ja'    => 'Japanese',  'ja_jp' => 'Japanese',
			'ka'    => 'Georgian',
			'kk'    => 'Kazakh',
			'km'    => 'Khmer',
			'ko'    => 'Korean',    'ko_kr' => 'Korean',
			'lt'    => 'Lithuanian', 'lt_lt' => 'Lithuanian',
			'lv'    => 'Latvian',   'lv_lv' => 'Latvian',
			'mk'    => 'Macedonian',
			'ml'    => 'Malayalam',
			'mn'    => 'Mongolian',
			'ms'    => 'Malay',     'ms_my' => 'Malay',
			'mt'    => 'Maltese',
			'my'    => 'Burmese',
			'nb'    => 'Norwegian', 'nb_no' => 'Norwegian',
			'nl'    => 'Dutch',     'nl_be' => 'Dutch (Belgium)', 'nl_nl' => 'Dutch',
			'nn'    => 'Norwegian Nynorsk',
			'pl'    => 'Polish',    'pl_pl' => 'Polish',
			'pt'    => 'Portuguese', 'pt_br' => 'Portuguese (Brazil)', 'pt_pt' => 'Portuguese (Portugal)',
			'ro'    => 'Romanian',  'ro_ro' => 'Romanian',
			'ru'    => 'Russian',   'ru_ru' => 'Russian',
			'sk'    => 'Slovak',    'sk_sk' => 'Slovak',
			'sl'    => 'Slovenian', 'sl_si' => 'Slovenian',
			'sq'    => 'Albanian',
			'sr'    => 'Serbian',   'sr_rs' => 'Serbian',
			'sv'    => 'Swedish',   'sv_se' => 'Swedish',
			'sw'    => 'Swahili',
			'ta'    => 'Tamil',
			'te'    => 'Telugu',
			'th'    => 'Thai',      'th_th' => 'Thai',
			'tr'    => 'Turkish',   'tr_tr' => 'Turkish',
			'uk'    => 'Ukrainian', 'uk_ua' => 'Ukrainian',
			'ur'    => 'Urdu',
			'uz'    => 'Uzbek',
			'vi'    => 'Vietnamese', 'vi_vn' => 'Vietnamese',
			'zh'    => 'Chinese',   'zh_cn' => 'Chinese (Simplified)', 'zh_tw' => 'Chinese (Traditional)',
		];

		return $map[ strtolower( $locale ) ] ?? ucfirst( str_replace( '_', ' ', $locale ) );
	}

	public function parse_table_languages( string $table_key ): array {
		$stripped = preg_replace( '/^trp_dictionary_/i', '', $table_key );

		$patterns = [
			'/^([a-z]{2}_[a-z]{2})_([a-z]{2}_[a-z]{2})$/i',
			'/^([a-z]{2}_[a-z]{2})_([a-z]{2})$/i',
			'/^([a-z]{2})_([a-z]{2}_[a-z]{2})$/i',
			'/^([a-z]{2})_([a-z]{2})$/i',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $stripped, $m ) ) {
				return [
					'source' => $this->locale_to_language( strtolower( $m[1] ) ),
					'target' => $this->locale_to_language( strtolower( $m[2] ) ),
				];
			}
		}

		$parts = explode( '_', $stripped );
		$mid   = (int) floor( count( $parts ) / 2 );
		return [
			'source' => $this->locale_to_language( implode( '_', array_slice( $parts, 0, $mid ) ) ),
			'target' => $this->locale_to_language( implode( '_', array_slice( $parts, $mid ) ) ),
		];
	}
}
