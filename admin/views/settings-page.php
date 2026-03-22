<?php
/**
 * Settings page view.
 *
 * Available variables (set by TranslateAI_For_TranslatePress::settings_page()):
 *
 * @var string  $table
 * @var array   $available_tables
 * @var bool    $table_exists
 * @var int     $total
 * @var int     $done
 * @var int     $skipped
 * @var int     $percent
 * @var bool    $batch_enabled
 * @var int     $batch_delay_ms
 * @var bool    $judge_ep_enabled
 * @var array   $table_lang_map
 *
 * @package TranslateAI_For_TranslatePress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1 class="wp-heading-inline">
		<span style="font-size:20px;vertical-align:middle;margin-right:6px;">🌐</span>
		<?php esc_html_e( 'TranslateAI for TranslatePress', 'translateai-for-translatepress' ); ?>
	</h1>
	<span class="taitp-badge taitp-badge-blue" style="margin-left:10px;vertical-align:middle;">v<?php echo esc_html( TAITP_VERSION ); ?></span>
	<a href="https://buymeacoffee.com/vage91"
	   target="_blank"
	   rel="noopener noreferrer"
	   style="display:inline-flex;align-items:center;gap:7px;margin-left:14px;vertical-align:middle;padding:5px 14px;background:#FFDD00;color:#000000;font-weight:700;font-size:13px;border-radius:6px;text-decoration:none;line-height:1.4;"
	>
		<span style="font-size:16px;">☕</span> <?php esc_html_e( 'Buy me a coffee', 'translateai-for-translatepress' ); ?>
	</a>
	<hr class="wp-header-end">

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if ( isset( $_GET['cleaned'] ) ) : ?>
		<div class="notice notice-info is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Deep Clean completed:', 'translateai-for-translatepress' ); ?></strong>
				<?php echo absint( $_GET['cleaned'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?> <?php esc_html_e( 'strings restored.', 'translateai-for-translatepress' ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if ( isset( $_GET['reset'] ) && 'success' === sanitize_key( $_GET['reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Table reset.', 'translateai-for-translatepress' ); ?></strong>
				<?php
				printf(
					/* translators: %s: table name */
					esc_html__( 'All translations in "%s" have been restored to their initial state.', 'translateai-for-translatepress' ),
					'<code>' . esc_html( get_option( 'taitp_selected_table', '' ) ) . '</code>'
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<div class="taitp-wrap">

		<!-- Tab navigation -->
		<nav class="taitp-nav-tabs" role="tablist">
			<a class="taitp-nav-tab taitp-tab-active" id="taitp-tab-config" href="#taitp-panel-config" role="tab">
				⚙️ <?php esc_html_e( 'Configuration', 'translateai-for-translatepress' ); ?>
			</a>
			<a class="taitp-nav-tab" id="taitp-tab-translate" href="#taitp-panel-translate" role="tab">
				▶ <?php esc_html_e( 'Translation', 'translateai-for-translatepress' ); ?>
			</a>
		</nav>

		<!-- ── Panel: Configuration ─────────────────────────────── -->
		<div class="taitp-tab-panel taitp-tab-active" id="taitp-panel-config">
		<div class="taitp-grid-2">

			<!-- Config form -->
			<div class="postbox">
				<div class="postbox-header">
					<h2 class="hndle"><?php esc_html_e( 'Configuration', 'translateai-for-translatepress' ); ?></h2>
				</div>
				<div class="inside">
					<form method="post" action="options.php" id="taitp-config-form">
						<?php settings_fields( 'taitp_opts' ); ?>

						<p class="taitp-section-title"><?php esc_html_e( 'Dictionary Table', 'translateai-for-translatepress' ); ?></p>
						<select name="taitp_selected_table" id="taitp_selected_table" style="width:100%;max-width:440px;">
							<?php foreach ( $available_tables as $taitp_t ) : ?>
								<option value="<?php echo esc_attr( $taitp_t ); ?>" <?php selected( get_option( 'taitp_selected_table' ), $taitp_t ); ?>>
									<?php
									$taitp_langs = $table_lang_map[ $taitp_t ] ?? [ 'source' => $taitp_t, 'target' => '' ];
									echo esc_html( $taitp_t . '  —  ' . $taitp_langs['source'] . ' → ' . $taitp_langs['target'] );
									?>
								</option>
							<?php endforeach; ?>
						</select>

						<p class="taitp-section-title"><?php esc_html_e( 'Languages', 'translateai-for-translatepress' ); ?></p>
						<div id="taitp-lang-notice" style="display:none;align-items:center;gap:8px;padding:8px 12px;background:#eef6fd;border:1px solid #bee0f7;border-radius:4px;margin-bottom:10px;font-size:12px;color:#0c4a8a;">
							<span>🔄</span>
							<span id="taitp-lang-notice-text"></span>
							<span style="margin-left:auto;font-style:italic;opacity:.8;"><?php esc_html_e( 'Auto-updated from table', 'translateai-for-translatepress' ); ?></span>
						</div>
						<div class="taitp-field-row">
							<div class="taitp-field">
								<label class="taitp-label-block" for="taitp_source_lang"><?php esc_html_e( 'Source', 'translateai-for-translatepress' ); ?></label>
								<input name="taitp_source_lang" type="text" id="taitp_source_lang"
									value="<?php echo esc_attr( get_option( 'taitp_source_lang', 'Italian' ) ); ?>"
									placeholder="e.g. Italian" class="regular-text" style="width:100%;">
							</div>
							<div class="taitp-arrow">→</div>
							<div class="taitp-field">
								<label class="taitp-label-block" for="taitp_target_lang"><?php esc_html_e( 'Target', 'translateai-for-translatepress' ); ?></label>
								<input name="taitp_target_lang" type="text" id="taitp_target_lang"
									value="<?php echo esc_attr( get_option( 'taitp_target_lang', 'English' ) ); ?>"
									placeholder="e.g. English" class="regular-text" style="width:100%;">
							</div>
						</div>

						<p class="taitp-section-title"><?php esc_html_e( 'Mode', 'translateai-for-translatepress' ); ?></p>
						<select name="taitp_mode" id="taitp_mode" style="width:100%;max-width:440px;">
							<option value="translator_only" <?php selected( get_option( 'taitp_mode', 'translator_only' ), 'translator_only' ); ?>>
								🤖 <?php esc_html_e( 'Translator Only', 'translateai-for-translatepress' ); ?>
							</option>
							<option value="full" <?php selected( get_option( 'taitp_mode', 'translator_only' ), 'full' ); ?>>
								🤖⚖️ <?php esc_html_e( 'Translator + Judge', 'translateai-for-translatepress' ); ?>
							</option>
						</select>
						<p class="description" id="taitp-mode-description" style="margin-top:6px;"></p>

						<p class="taitp-section-title"><?php esc_html_e( 'Models', 'translateai-for-translatepress' ); ?></p>
						<table class="form-table" role="presentation" style="margin-top:0;">
							<tr>
								<th scope="row" style="width:160px;"><label for="taitp_api_url"><?php esc_html_e( 'API Endpoint', 'translateai-for-translatepress' ); ?></label></th>
								<td>
									<input name="taitp_api_url" type="url" id="taitp_api_url"
										value="<?php echo esc_attr( get_option( 'taitp_api_url', 'http://192.168.178.100:30068/api/generate' ) ); ?>"
										class="large-text code" style="margin-bottom:6px;">
									<select name="taitp_api_service" id="taitp_api_service">
										<option value="ollama" <?php selected( get_option( 'taitp_api_service', 'ollama' ), 'ollama' ); ?>><?php esc_html_e( 'Ollama', 'translateai-for-translatepress' ); ?></option>
										<option value="openwebui_ollama" <?php selected( get_option( 'taitp_api_service', 'ollama' ), 'openwebui_ollama' ); ?>><?php esc_html_e( 'Open WebUI (Ollama native)', 'translateai-for-translatepress' ); ?></option>
										<option value="openwebui_openai" <?php selected( get_option( 'taitp_api_service', 'ollama' ), 'openwebui_openai' ); ?>><?php esc_html_e( 'Open WebUI (OpenAI compatible)', 'translateai-for-translatepress' ); ?></option>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="taitp_api_key"><?php esc_html_e( 'API Key', 'translateai-for-translatepress' ); ?></label></th>
								<td>
									<input name="taitp_api_key" type="password" id="taitp_api_key"
										value="<?php echo esc_attr( get_option( 'taitp_api_key', '' ) ); ?>"
										class="regular-text" autocomplete="new-password">
									<p class="description"><?php esc_html_e( 'Optional — leave empty if not required.', 'translateai-for-translatepress' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="taitp_main_model"><?php esc_html_e( 'Translator Model', 'translateai-for-translatepress' ); ?></label></th>
								<td><input name="taitp_main_model" type="text" id="taitp_main_model" value="<?php echo esc_attr( get_option( 'taitp_main_model', 'mistral-small:22b' ) ); ?>" class="regular-text code"></td>
							</tr>
							<tr id="taitp-judge-model-row">
								<th scope="row"><label for="taitp_judge_model"><?php esc_html_e( 'Judge Model', 'translateai-for-translatepress' ); ?></label></th>
								<td><input name="taitp_judge_model" type="text" id="taitp_judge_model" value="<?php echo esc_attr( get_option( 'taitp_judge_model', 'mistral-small:22b' ) ); ?>" class="regular-text code"></td>
							</tr>
						</table>

						<div id="taitp-judge-section">
							<p class="taitp-section-title">
								<?php esc_html_e( 'Separate Judge Endpoint', 'translateai-for-translatepress' ); ?>
								<span style="font-weight:400;color:#646970;">(<?php esc_html_e( 'optional', 'translateai-for-translatepress' ); ?>)</span>
							</p>
							<div class="taitp-batch-row" style="flex-direction:column;align-items:flex-start;gap:12px;">
								<label style="display:flex;align-items:center;gap:8px;font-weight:600;font-size:13px;cursor:pointer;">
									<input type="checkbox" id="taitp-judge-endpoint-toggle" name="taitp_judge_endpoint_enabled" value="1" <?php checked( $judge_ep_enabled ); ?>>
									<?php esc_html_e( 'Use separate endpoint for Judge model', 'translateai-for-translatepress' ); ?>
								</label>
								<div id="taitp-judge-endpoint-wrap" style="width:100%;<?php echo $judge_ep_enabled ? '' : 'display:none;'; ?>">
									<table class="form-table" role="presentation" style="margin:0;">
										<tr>
											<th scope="row" style="width:160px;padding-left:0;"><label for="taitp_judge_api_url"><?php esc_html_e( 'Judge API Endpoint', 'translateai-for-translatepress' ); ?></label></th>
											<td>
												<input name="taitp_judge_api_url" type="url" id="taitp_judge_api_url"
													value="<?php echo esc_attr( get_option( 'taitp_judge_api_url', '' ) ); ?>"
													class="large-text code" placeholder="e.g. http://192.168.1.101:11434/api/generate" style="margin-bottom:6px;">
												<select name="taitp_judge_api_service" id="taitp_judge_api_service">
													<option value="ollama" <?php selected( get_option( 'taitp_judge_api_service', 'ollama' ), 'ollama' ); ?>><?php esc_html_e( 'Ollama', 'translateai-for-translatepress' ); ?></option>
													<option value="openwebui_ollama" <?php selected( get_option( 'taitp_judge_api_service', 'ollama' ), 'openwebui_ollama' ); ?>><?php esc_html_e( 'Open WebUI (Ollama native)', 'translateai-for-translatepress' ); ?></option>
													<option value="openwebui_openai" <?php selected( get_option( 'taitp_judge_api_service', 'ollama' ), 'openwebui_openai' ); ?>><?php esc_html_e( 'Open WebUI (OpenAI compatible)', 'translateai-for-translatepress' ); ?></option>
												</select>
											</td>
										</tr>
										<tr>
											<th scope="row" style="padding-left:0;"><label for="taitp_judge_api_key"><?php esc_html_e( 'Judge API Key', 'translateai-for-translatepress' ); ?></label></th>
											<td>
												<input name="taitp_judge_api_key" type="password" id="taitp_judge_api_key"
													value="<?php echo esc_attr( get_option( 'taitp_judge_api_key', '' ) ); ?>"
													class="regular-text" autocomplete="new-password">
												<p class="description"><?php esc_html_e( 'Optional — leave empty if not required.', 'translateai-for-translatepress' ); ?></p>
											</td>
										</tr>
									</table>
								</div>
							</div>
						</div><!-- #taitp-judge-section -->

						<p class="taitp-section-title">
							<?php esc_html_e( 'Site Context', 'translateai-for-translatepress' ); ?>
							<span style="font-weight:400;color:#646970;">(<?php esc_html_e( 'optional', 'translateai-for-translatepress' ); ?>)</span>
						</p>
						<textarea
							name="taitp_site_context"
							id="taitp_site_context"
							rows="3"
							class="large-text"
							placeholder="<?php esc_attr_e( 'e.g. Private investigation agency specializing in spyware detection, surveillance and digital security.', 'translateai-for-translatepress' ); ?>"
							style="resize:vertical;"
						><?php echo esc_textarea( get_option( 'taitp_site_context', '' ) ); ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Briefly describe the site topic or sector. The judge will use this context to correctly evaluate specialized terms without penalizing generic strings (e.g. "Home", "Submit", "Cancel").', 'translateai-for-translatepress' ); ?>
						</p>

						<p class="taitp-section-title"><?php esc_html_e( 'Batch Processing', 'translateai-for-translatepress' ); ?></p>
						<div class="taitp-batch-row">
							<label style="display:flex;align-items:center;gap:8px;font-weight:600;font-size:13px;cursor:pointer;">
								<input type="checkbox" id="taitp-batch-toggle" name="taitp_batch_enabled" value="1" <?php checked( $batch_enabled ); ?>>
								<?php esc_html_e( 'Enable Batch Processing', 'translateai-for-translatepress' ); ?>
							</label>
							<div id="taitp-batch-size-wrap" style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;<?php echo $batch_enabled ? '' : 'opacity:.45;'; ?>">
								<label for="taitp_batch_size"><?php esc_html_e( 'Items:', 'translateai-for-translatepress' ); ?></label>
								<input name="taitp_batch_size" id="taitp_batch_size" type="number" min="1" max="15"
									value="<?php echo esc_attr( get_option( 'taitp_batch_size', '3' ) ); ?>"
									style="width:56px;" class="small-text">
							</div>
							<div id="taitp-batch-delay-wrap" style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;<?php echo $batch_enabled ? '' : 'opacity:.45;'; ?>">
								<label for="taitp_batch_delay_ms"><?php esc_html_e( 'Delay (ms):', 'translateai-for-translatepress' ); ?></label>
								<input name="taitp_batch_delay_ms" id="taitp_batch_delay_ms" type="number" min="0" max="5000" step="100"
									value="<?php echo esc_attr( $batch_delay_ms ); ?>"
									style="width:72px;" class="small-text">
							</div>
						</div>
						<p class="description" style="margin-top:6px;">
							<?php esc_html_e( 'The delay (recommended: 300–500 ms) prevents context bleeding between consecutive requests to the same model.', 'translateai-for-translatepress' ); ?>
						</p>

						<div style="margin-top:20px;display:flex;align-items:center;gap:10px;">
							<?php submit_button( __( 'Save Configuration', 'translateai-for-translatepress' ), 'primary', 'submit', false ); ?>
							<button type="button" id="taitp-test-btn" class="button button-secondary">🔌 <?php esc_html_e( 'Test Connection', 'translateai-for-translatepress' ); ?></button>
							<span id="taitp-test-result" style="font-size:13px;"></span>
						</div>
					</form>
				</div>
			</div>

			<!-- Database status -->
			<div class="postbox">
				<div class="postbox-header">
					<h2 class="hndle"><?php esc_html_e( 'Database Status', 'translateai-for-translatepress' ); ?></h2>
				</div>
				<div class="inside">
					<?php if ( ! $table_exists ) : ?>
						<div class="notice notice-error inline" style="margin:0;">
							<p>⚠️ <?php esc_html_e( 'Table not found. Save the configuration selecting the correct table.', 'translateai-for-translatepress' ); ?></p>
						</div>
					<?php else : ?>
						<div class="taitp-stat-row" style="margin-top:4px;">
							<span class="taitp-stat-label"><?php esc_html_e( 'Completion', 'translateai-for-translatepress' ); ?></span>
							<span class="taitp-stat-value" id="taitp-percent"><?php echo esc_html( $percent ); ?>%</span>
						</div>
						<div class="taitp-progress-wrap">
							<div class="taitp-progress-bar" id="taitp-progress-bar" style="width:<?php echo esc_attr( $percent ); ?>%;"></div>
						</div>
						<p class="taitp-counter-sub">
							<span id="taitp-counter"><?php echo esc_html( $done + $skipped ); ?></span> / <?php echo esc_html( $total ); ?> <?php esc_html_e( 'strings processed', 'translateai-for-translatepress' ); ?>
						</p>

						<table class="widefat striped" style="margin-bottom:16px;">
							<tbody>
								<tr>
									<td><?php esc_html_e( 'Total strings', 'translateai-for-translatepress' ); ?></td>
									<td style="text-align:right;font-weight:600;"><?php echo esc_html( $total ); ?></td>
								</tr>
								<tr>
									<td>✅ <?php esc_html_e( 'Translated', 'translateai-for-translatepress' ); ?></td>
									<td style="text-align:right;font-weight:600;color:#1a7a2e;" id="taitp-stat-done"><?php echo esc_html( $done ); ?></td>
								</tr>
								<tr>
									<td>⚠️ <?php esc_html_e( 'Skipped', 'translateai-for-translatepress' ); ?></td>
									<td style="text-align:right;font-weight:600;color:#8a5c00;" id="taitp-stat-skipped"><?php echo esc_html( $skipped ); ?></td>
								</tr>
								<tr>
									<td>🕐 <?php esc_html_e( 'Pending', 'translateai-for-translatepress' ); ?></td>
									<td style="text-align:right;font-weight:600;color:#646970;" id="taitp-stat-pending"><?php echo esc_html( max( 0, $total - $done - $skipped ) ); ?></td>
								</tr>
							</tbody>
						</table>

						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=taitp_deep_clean' ), 'taitp_clean_action' ) ); ?>"
						   class="button button-secondary"
						   style="width:100%;text-align:center;justify-content:center;"
						   onclick="return confirm('<?php esc_attr_e( 'Restore skipped strings and clean transients?', 'translateai-for-translatepress' ); ?>');">
							🔍 <?php esc_html_e( 'Deep Clean & Restore Skipped', 'translateai-for-translatepress' ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

		</div><!-- .taitp-grid-2 -->
		</div><!-- #taitp-panel-config -->

		<!-- ── Panel: Translation ───────────────────────────────── -->
		<div class="taitp-tab-panel" id="taitp-panel-translate">
		<div class="taitp-grid-2">

			<!-- Activity log -->
			<div class="postbox taitp-postbox-flex" style="margin-bottom:0;">
				<div class="postbox-header">
					<h2 class="hndle"><?php esc_html_e( 'Activity Log', 'translateai-for-translatepress' ); ?></h2>
				</div>
				<div class="taitp-console" id="taitp-console">
					<span class="log-info"><?php echo esc_html( sprintf(
						/* translators: 1: source language 2: target language 3: table name */
						__( 'Table: %3$s — %1$s → %2$s', 'translateai-for-translatepress' ),
						get_option( 'taitp_source_lang', 'Italian' ),
						get_option( 'taitp_target_lang', 'English' ),
						get_option( 'taitp_selected_table', '' )
					) ); ?></span><br>
					<span class="log-info"><?php esc_html_e( 'Ready. Press START to begin.', 'translateai-for-translatepress' ); ?></span>
				</div>
				<div class="taitp-action-bar">
					<button id="taitp-start-btn" class="button button-primary" <?php echo ! $table_exists ? 'disabled' : ''; ?>>
						▶ <?php esc_html_e( 'Start', 'translateai-for-translatepress' ); ?>
					</button>
					<button id="taitp-stop-btn" class="button button-secondary" disabled>
						■ <?php esc_html_e( 'Stop', 'translateai-for-translatepress' ); ?>
					</button>
					<span class="spacer"></span>
					<?php
					/* translators: %s: dictionary table name */
					$taitp_reset_confirm = sprintf( __( 'Reset the table "%s"? All translations will be lost. This operation is irreversible.', 'translateai-for-translatepress' ), get_option( 'taitp_selected_table', '' ) );
					?>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=taitp_reset_db' ), 'taitp_reset_action' ) ); ?>"
					   class="button button-link-delete"
					   onclick="return confirm('<?php echo esc_js( $taitp_reset_confirm ); ?>');">
						🗑 <?php esc_html_e( 'Reset Translations', 'translateai-for-translatepress' ); ?>
					</a>
				</div>
			</div>

			<!-- Agent reviews -->
			<div class="postbox" style="margin-bottom:0;">
				<div class="postbox-header">
					<h2 class="hndle"><?php esc_html_e( 'Agent Reviews', 'translateai-for-translatepress' ); ?></h2>
				</div>
				<div class="taitp-reject-scroll" id="taitp-reject-box">
					<p style="color:#646970;font-size:13px;margin:0;"><?php esc_html_e( 'Waiting for processing…', 'translateai-for-translatepress' ); ?></p>
				</div>
				<div class="taitp-action-bar">
					<button id="taitp-download-failed-btn" class="button button-secondary" disabled>
						⬇ <?php esc_html_e( 'Download Skipped Report', 'translateai-for-translatepress' ); ?>
					</button>
					<span id="taitp-failed-count" style="font-size:12px;color:#646970;"></span>
				</div>
			</div>

		</div><!-- .taitp-grid-2 -->
		</div><!-- #taitp-panel-translate -->

	</div><!-- .taitp-wrap -->
</div><!-- .wrap -->
