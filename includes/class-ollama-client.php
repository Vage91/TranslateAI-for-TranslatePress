<?php
/**
 * Ollama API Client
 *
 * Handles communication with Ollama (native), Open WebUI (Ollama native),
 * and Open WebUI (OpenAI-compatible) endpoints.
 *
 * @package TranslateAI_For_TranslatePress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TAITP_Ollama_Client {

	private string $api_url;
	private string $api_key;
	private string $service; // 'ollama' | 'openwebui_ollama' | 'openwebui_openai'

	public function __construct( string $api_url, string $api_key = '', string $service = 'ollama' ) {
		$this->api_url = rtrim( $api_url, '/' );
		$this->api_key = $api_key;
		$this->service = $service;
	}

	/**
	 * Resolve the correct endpoint URL based on the selected service.
	 */
	private function get_endpoint(): string {
		switch ( $this->service ) {
			case 'openwebui_openai':
				// Strip any /api/generate suffix if user pasted it, use OpenAI-compatible endpoint
				$base = preg_replace( '#/api/generate$#', '', $this->api_url );
				$base = preg_replace( '#/ollama/api/generate$#', '', $base );
				return rtrim( $base, '/' ) . '/api/chat/completions';
			case 'openwebui_ollama':
				$base = preg_replace( '#/ollama/api/generate$#', '', $this->api_url );
				$base = preg_replace( '#/api/generate$#', '', $base );
				return rtrim( $base, '/' ) . '/ollama/api/generate';
			default: // 'ollama'
				return $this->api_url;
		}
	}

	/**
	 * Build the request body based on the selected service.
	 */
	private function build_body( string $model, string $prompt, float $temperature ): string {
		if ( 'openwebui_openai' === $this->service ) {
			return wp_json_encode( [
				'model'       => $model,
				'messages'    => [ [ 'role' => 'user', 'content' => $prompt ] ],
				'stream'      => false,
				'temperature' => $temperature,
			] );
		}
		// Ollama native format (ollama + openwebui_ollama)
		return wp_json_encode( [
			'model'   => $model,
			'prompt'  => $prompt,
			'stream'  => false,
			'options' => [ 'temperature' => $temperature ],
		] );
	}

	/**
	 * Extract the response text from the API response body.
	 */
	private function extract_response( string $body ): string {
		$data = json_decode( $body, true );
		if ( 'openwebui_openai' === $this->service ) {
			return $data['choices'][0]['message']['content'] ?? 'API_ERROR';
		}
		return $data['response'] ?? 'API_ERROR';
	}

	/**
	 * Send a prompt to the API.
	 *
	 * @return string Raw response text, or 'API_ERROR'.
	 */
	public function generate( string $model, string $prompt, float $temperature = 0.3 ): string {
		$headers = [
			'Content-Type'               => 'application/json',
			'ngrok-skip-browser-warning' => 'true',
		];

		if ( ! empty( $this->api_key ) ) {
			$headers['Authorization'] = 'Bearer ' . $this->api_key;
		}

		$response = wp_remote_post( $this->get_endpoint(), [
			'body'    => $this->build_body( $model, $prompt, $temperature ),
			'timeout' => TAITP_API_TIMEOUT,
			'headers' => $headers,
		] );

		if ( is_wp_error( $response ) ) {
			return 'API_ERROR';
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return 'API_ERROR';
		}

		return $this->extract_response( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Quick ping to verify the API is reachable.
	 */
	public function test( string $model ): bool {
		$headers = [
			'Content-Type'               => 'application/json',
			'ngrok-skip-browser-warning' => 'true',
		];

		if ( ! empty( $this->api_key ) ) {
			$headers['Authorization'] = 'Bearer ' . $this->api_key;
		}

		$response = wp_remote_post( $this->get_endpoint(), [
			'body'    => $this->build_body( $model, 'hi', 0.1 ),
			'timeout' => TAITP_TEST_TIMEOUT,
			'headers' => $headers,
		] );

		return ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response );
	}
}
