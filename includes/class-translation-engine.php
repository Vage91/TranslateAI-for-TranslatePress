<?php
/**
 * Translation Engine
 *
 * Orchestrates the translator and judge agents to translate strings
 * from the TranslatePress dictionary table.
 *
 * @package TranslateAI_For_TranslatePress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TAITP_Translation_Engine {

	private int    $max_retries;
	private string $source_lang;
	private string $target_lang;
	private string $translator_model;
	private string $judge_model;
	private string $site_context;
	private string $mode;
	private TAITP_Ollama_Client $client;
	private TAITP_Ollama_Client $judge_client;

	public function __construct(
		TAITP_Ollama_Client $client,
		string $translator_model,
		string $judge_model,
		string $source_lang,
		string $target_lang,
		int    $max_retries = 3,
		string $site_context = '',
		?TAITP_Ollama_Client $judge_client = null,
		string $mode = 'full'
	) {
		$this->client           = $client;
		$this->judge_client     = $judge_client ?? $client;
		$this->translator_model = $translator_model;
		$this->judge_model      = $judge_model;
		$this->source_lang      = $source_lang;
		$this->target_lang      = $target_lang;
		$this->max_retries      = $max_retries;
		$this->site_context     = $site_context;
		$this->mode             = $mode;
	}

	/**
	 * Process a single string row from the dictionary table.
	 *
	 * @param object $row        Row with dict_row_id, original_id, original.
	 * @param string $table      Fully qualified table name.
	 * @param int    $done_count Current done count (passed in to avoid extra query).
	 * @return array<string, mixed> Result payload for the frontend.
	 */
	public function process( object $row, string $table, int $done_count ): array {
		global $wpdb;

		// --- Auto-pass technical strings (URLs, file extensions) ---
		if ( $this->is_technical_string( $row->original ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				[ 'translated' => $row->original, 'status' => TAITP_STATUS_DONE ],
				[ 'id' => $row->dict_row_id ],
				[ '%s', '%d' ],
				[ '%d' ]
			);

			return $this->build_result( $row->original_id, true, $row->original, 'N/A', 'Auto-pass (technical string)', $done_count + 1, false, 'AUTO-PASS', '#00a0d2', $row->original );
		}

		// --- Build translator prompt ---
		$retries        = (int) ( get_transient( 'taitp_retry_' . $row->dict_row_id ) ?: 0 );
		$previous_error = (string) ( get_transient( 'taitp_err_' . $row->dict_row_id ) ?: '' );
		$error_hint     = $previous_error ? "\nCritical: Avoid previous error: {$previous_error}." : '';
		$context_hint   = ! empty( $this->site_context )
			? 'Site context (use to pick domain-specific terminology): "' . $this->site_context . '"' . "\n"
			: '';

		$is_translator_only = ( 'translator_only' === $this->mode );
		$json_format        = $is_translator_only
			? '{"translation": "..."}'
			: '{"translation": "...", "rationale": "..."}';

		$translator_prompt = sprintf(
			'Task: Translate the following text from %s to %s.' . "\n" .
			'%s' .
			'Rules:' . "\n" .
			'- Return ONLY valid JSON on a single line, no markdown, no code blocks, no backticks.' . "\n" .
			'- The "translation" value must be plain text only — no JSON, no HTML, no escape sequences written literally.' . "\n" .
			'- Do NOT include the original text in the translation.' . "\n" .
			'- Format: %s' . "\n" .
			'%s' .
			'TEXT: "%s"' . "\n" .
			'JSON:',
			$this->source_lang,
			$this->target_lang,
			$context_hint,
			$json_format,
			$error_hint ? $error_hint . "\n" : '',
			$row->original
		);

		$raw_response = $this->client->generate( $this->translator_model, $translator_prompt, 0.3 );

		if ( 'API_ERROR' === $raw_response ) {
			return [ 'log' => "ID {$row->original_id}: <span style='color:orange;'>API Error – retrying later</span>", 'done_count' => $done_count, 'finished' => false ];
		}

		// --- Parse translator JSON response ---
		$parsed    = json_decode( $this->extract_json( $raw_response ), true );
		$raw_trans = $parsed['translation'] ?? $this->clean_llm_output( $raw_response );
		$rationale = $parsed['rationale']   ?? 'N/A';

		// --- Deep sanitize the translation BEFORE sending to judge ---
		$translation = $this->deep_sanitize( $row->original, trim( $raw_trans ) );

		// --- Structural validation: reject before even calling the judge ---
		$structural_error = $this->detect_structural_artifact( $translation, $row->original );
		if ( null !== $structural_error ) {
			$retries++;
			if ( $retries >= $this->max_retries ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update( $table, [ 'status' => TAITP_STATUS_SKIPPED ], [ 'id' => $row->dict_row_id ], [ '%d' ], [ '%d' ] );
				delete_transient( 'taitp_retry_' . $row->dict_row_id );
				delete_transient( 'taitp_err_' . $row->dict_row_id );
				return $this->build_result( $row->original_id, false, $translation, $rationale, "STRUCTURAL REJECT: {$structural_error}", $done_count + 1, false, 'SKIPPED', 'red', $row->original );
			}
			set_transient( 'taitp_retry_' . $row->dict_row_id, $retries, 3600 );
			set_transient( 'taitp_err_' . $row->dict_row_id, "Output contained structural artifacts: {$structural_error}. Return ONLY plain translated text inside the JSON, no markup.", 3600 );
			return $this->build_result( $row->original_id, false, $translation, $rationale, "STRUCTURAL REJECT: {$structural_error}", $done_count, false, "RETRY ({$retries})", 'darkorange', $row->original );
		}

		// --- Translator-only mode: skip judge ---
		if ( 'translator_only' === $this->mode ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				[ 'translated' => $translation, 'status' => TAITP_STATUS_DONE ],
				[ 'id' => $row->dict_row_id ],
				[ '%s', '%d' ],
				[ '%d' ]
			);
			delete_transient( 'taitp_retry_' . $row->dict_row_id );
			delete_transient( 'taitp_err_' . $row->dict_row_id );

			return $this->build_result( $row->original_id, true, $translation, $rationale, 'Judge disabled', $done_count + 1, false, 'OK', 'green', $row->original );
		}

		// --- Judge agent ---
		$context_block = '';
		if ( ! empty( $this->site_context ) ) {
			$context_block =
				'SITE CONTEXT (use ONLY to disambiguate domain-specific terms):' . "\n" .
				'"' . $this->site_context . '"' . "\n" .
				'IMPORTANT: This context helps you understand specialized terminology. ' .
				'Do NOT use it to reject generic UI strings (e.g. "Home", "Send", "Cancel", "Error", single words, ' .
				'navigation labels, button text, or any string that is not domain-specific). ' .
				'A short or generic string is valid as long as its translation is correct.' . "\n\n";
		}

		$judge_prompt =
			'You are a precise translation quality judge. Your only job is to verify correctness.' . "\n\n" .
			$context_block .
			'Source (' . $this->source_lang . '): "' . $row->original . '"' . "\n" .
			'Translation (' . $this->target_lang . '): "' . $translation . '"' . "\n\n" .
			'Answer VERDICT: NO only if the translation has one of these HARD defects:' . "\n" .
			'1. Contains JSON syntax, curly braces, or key-value pairs' . "\n" .
			'2. Contains markdown, backticks, or code blocks' . "\n" .
			'3. Contains literal unicode escape sequences (e.g. \u00fc as text)' . "\n" .
			'4. Contains HTML tags' . "\n" .
			'5. Is completely empty' . "\n" .
			'6. Clearly conveys the OPPOSITE meaning of the source' . "\n" .
			'7. Contains the full original source text verbatim (copy-paste, untranslated)' . "\n\n" .
			'Answer VERDICT: YES if:' . "\n" .
			'- The translation is a reasonable, natural rendering of the source' . "\n" .
			'- The translation is short because the source is short (single words, labels, UI text are fine)' . "\n" .
			'- The translation uses a domain-specific meaning consistent with the site context above' . "\n" .
			'- Minor stylistic differences are present but meaning is preserved' . "\n\n" .
			'DO NOT reject based on: personal style preference, word choice variations, ' .
			'brevity of generic strings, or domain terminology you are unsure about.' . "\n\n" .
			'Respond EXACTLY in this format:' . "\n" .
			'VERDICT: YES' . "\n" .
			'REASON: [one sentence]' . "\n\n" .
			'or:' . "\n\n" .
			'VERDICT: NO' . "\n" .
			'REASON: [one sentence explaining the specific defect]';

		$judge_raw = $this->judge_client->generate( $this->judge_model, $judge_prompt, 0.0 );
		$approved  = stripos( $judge_raw, 'VERDICT: YES' ) !== false;

		// Extra safety: even if judge says YES, run structural check on final string
		if ( $approved && null !== $this->detect_structural_artifact( $translation, $row->original ) ) {
			$approved  = false;
			$judge_raw = 'VERDICT: NO REASON: Structural artifact detected post-judge.';
		}

		$reason = trim( preg_replace( '/VERDICT:\s*(YES|NO)/i', '', $judge_raw ) );

		// --- Persist result ---
		if ( $approved ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				[ 'translated' => $translation, 'status' => TAITP_STATUS_DONE ],
				[ 'id' => $row->dict_row_id ],
				[ '%s', '%d' ],
				[ '%d' ]
			);
			delete_transient( 'taitp_retry_' . $row->dict_row_id );
			delete_transient( 'taitp_err_' . $row->dict_row_id );

			return $this->build_result( $row->original_id, true, $translation, $rationale, $reason, $done_count + 1, false, 'OK', 'green', $row->original );
		}

		// --- Handle rejection + retry logic ---
		$retries++;

		if ( $retries >= $this->max_retries ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				[ 'status' => TAITP_STATUS_SKIPPED ],
				[ 'id' => $row->dict_row_id ],
				[ '%d' ],
				[ '%d' ]
			);
			delete_transient( 'taitp_retry_' . $row->dict_row_id );
			delete_transient( 'taitp_err_' . $row->dict_row_id );

			return $this->build_result( $row->original_id, false, $translation, $rationale, $reason, $done_count + 1, false, 'SKIPPED', 'red', $row->original );
		}

		set_transient( 'taitp_retry_' . $row->dict_row_id, $retries, 3600 );
		set_transient( 'taitp_err_' . $row->dict_row_id,   $reason,   3600 );

		return $this->build_result( $row->original_id, false, $translation, $rationale, $reason, $done_count, false, "RETRY ({$retries})", 'darkorange', $row->original );
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Detect structural artifacts that should never appear in a clean translation.
	 * Returns a description of the problem, or null if clean.
	 */
	private function detect_structural_artifact( string $translation, string $original ): ?string {
		if ( mb_strlen( trim( $translation ) ) === 0 ) {
			return 'empty translation';
		}

		if ( ! $this->is_compact_language( $this->target_lang ) ) {
			if ( mb_strlen( $translation ) < mb_strlen( $original ) * 0.2 && mb_strlen( $original ) > 10 ) {
				return 'translation is suspiciously short';
			}
		}

		if ( preg_match( '/^\s*\{/', $translation ) && preg_match( '/\}\s*$/', $translation ) ) {
			return 'translation is wrapped in JSON braces';
		}
		if ( preg_match( '/"translation"\s*:/', $translation ) ) {
			return 'translation contains JSON key "translation"';
		}
		if ( preg_match( '/```/', $translation ) ) {
			return 'translation contains markdown code fences';
		}
		if ( preg_match( '/\\\\u[0-9a-fA-F]{4}/', $translation ) ) {
			return 'translation contains literal unicode escape sequences';
		}
		if ( preg_match( '/<[a-zA-Z\/][^>]*>/', $translation ) ) {
			return 'translation contains HTML tags';
		}
		return null;
	}

	/**
	 * Languages where translated text is naturally much shorter than the source
	 * due to morphological density (CJK, Thai, etc.).
	 */
	private function is_compact_language( string $lang ): bool {
		$compact = [
			'chinese', 'japanese', 'korean', 'thai', 'khmer', 'burmese',
			'tibetan', 'lao', 'myanmar',
		];
		$lang_lower = strtolower( $lang );
		foreach ( $compact as $c ) {
			if ( strpos( $lang_lower, $c ) !== false ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Comprehensive sanitization pipeline applied BEFORE the judge sees the text.
	 */
	private function deep_sanitize( string $original, string $text ): string {
		$text = preg_replace( '/```(?:json)?\s*([\s\S]*?)```/i', '$1', $text );

		$decoded = json_decode( $text, true );
		if ( is_array( $decoded ) && isset( $decoded['translation'] ) ) {
			$text = $decoded['translation'];
		}

		$text = preg_replace( '/^\s*\{.*?"translation"\s*:\s*"((?:[^"\\\\]|\\\\.)*)".*/s', '$1', $text );

		$json_decoded = json_decode( '"' . addcslashes( $text, '"' ) . '"' );
		if ( is_string( $json_decoded ) && mb_strlen( $json_decoded ) > 0 ) {
			$text = $json_decoded;
		}

		$text = $this->clean_llm_output( $text );
		$text = $this->sync_punctuation( $original, $text );

		return $text;
	}

	private function build_result(
		string $original_id,
		bool   $approved,
		string $translation,
		string $rationale,
		string $judge_feedback,
		int    $done_count,
		bool   $finished,
		string $log_label,
		string $log_color,
		string $original = ''
	): array {
		return [
			'id'             => $original_id,
			'approved'       => $approved,
			'original'       => esc_html( $original ),
			'translation'    => esc_html( $translation ),
			'rationale'      => esc_html( $rationale ),
			'judge_feedback' => esc_html( trim( $judge_feedback ) ),
			'log'            => "ID {$original_id}: <span style='color:{$log_color};'>{$log_label}</span>",
			'done_count'     => $done_count,
			'finished'       => $finished,
		];
	}

	private function extract_json( string $string ): string {
		$string = preg_replace( '/```(?:json)?\s*([\s\S]*?)```/i', '$1', $string );
		preg_match( '/\{.*\}/s', $string, $matches );
		return $matches[0] ?? $string;
	}

	private function is_technical_string( string $text ): bool {
		return (bool) filter_var( trim( $text ), FILTER_VALIDATE_URL )
			|| (bool) preg_match( '/\.(jpg|jpeg|png|gif|svg|webp|pdf|zip|mp4|mp3|css|js)$/i', trim( $text ) );
	}

	private function sync_punctuation( string $original, string $translated ): string {
		preg_match( '/([\s.!?]+)$/u', $original, $matches );
		return preg_replace( '/([\s.!?]+)$/u', '', trim( $translated ) ) . ( $matches[1] ?? '' );
	}

	private function clean_llm_output( string $text ): string {
		return trim(
			preg_replace( '/^(Translation|EN|English|Result|TEXT|Output|Traduzione|Übersetzung):/i', '', trim( $text ) ),
			" \n\r\t\v\0\"'"
		);
	}
}
