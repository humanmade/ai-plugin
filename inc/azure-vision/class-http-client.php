<?php

namespace AI\Azure_Vision;

use Exception;

class HTTP_Client {
	protected static $instance;
	protected $api_key;
	protected $base_url;
	protected $api_version;

	public function __construct( string $api_key, string $base_url = null, string $api_version = '2023-02-01-preview' ) {
		$this->api_key = $api_key;
		$this->api_version = $api_version;
		if ( $base_url ) {
			$this->base_url = $base_url;
		}
	}

	public static function get_instance() : static {
		if ( isset( static::$instance ) ) {
			return static::$instance;
		}

		$azure_api_key = get_option( 'microsoft_azure_vision_api_key' );
		$azure_api_version = get_option( 'microsoft_azure_vision_api_version' );
		$azure_api_endpoint = get_option( 'microsoft_azure_vision_endpoint' );

		if ( ! $azure_api_key ) {
			throw new Exception( 'Azure API Key not set' );
		}

		if ( ! $azure_api_endpoint ) {
			throw new Exception( 'Azure API Endpoint not set' );
		}

		if ( ! $azure_api_version ) {
			throw new Exception( 'Azure API Version not set' );
		}

		static::$instance = new static( $azure_api_key, $azure_api_endpoint, $azure_api_version );
		return static::$instance;
	}

	public function analyze(
		string $features = null,
		string $model_name = null,
		string $language = null,
		string $smartcrops_aspect_ratios = null,
		bool $gender_neutral_caption = false,
		string $image_data = null,
		string $image_url = null,
	) {
		$query = http_build_query( array_filter( [
			'features' => $features,
			'model-name' => $model_name,
			'language' => $language,
			'smartcrops-aspect-ratios' => $smartcrops_aspect_ratios,
			'gender-neutral-caption' => $gender_neutral_caption,
		] ) );
		$response = $this->request( '/imageanalysis:analyze?' . $query, 'POST', $image_data ? $image_data : [ 'url' => $image_url ] );
		return json_decode( wp_remote_retrieve_body( $response ) );
	}

	public function request( string $endpoint, string $method, $data = null ) {
		$url = add_query_arg( 'api-version', $this->api_version, "{$this->base_url}{$endpoint}" );

		$args = [
			'method' => $method,
			'headers' => [
				'Ocp-Apim-Subscription-Key' => $this->api_key,
			],
			'timeout' => 60,
		];

		if ( $method === 'POST' && ! is_string( $data ) ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body'] = json_encode( $data );
		}

		if ( $method === 'POST' && is_string( $data ) ) {
			$args['headers']['Content-Type'] = 'application/octet-stream';
			$args['body'] = $data;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}

		if ( wp_remote_retrieve_response_code( $response ) > 299 ) {
			$data = json_decode( wp_remote_retrieve_body( $response ) );
			throw new Exception( is_array( $data->error->message ) ? implode( "\n", $data->error->message ) : $data->error->message );
		}

		return $response;
	}
}
