<?php

namespace AI\OpenAI;

use Exception;
use IteratorAggregate;
use OpenAI;

class HTTP_Client implements Client {
	protected static $instance;
	protected $api_key;
	protected $base_url = 'https://api.openai.com/v1';
	protected $azure_api_version;

	public function __construct( string $api_key, string $base_url = null, string $azure_api_version = '2023-03-15-preview' ) {
		$this->api_key = $api_key;
		$this->azure_api_version = $azure_api_version;

		if ( $base_url ) {
			$this->base_url = $base_url;
		}
	}

	public static function get_instance() : static {
		if ( isset( static::$instance ) ) {
			return static::$instance;
		}

		$use_azure = get_option( 'microsoft_azure_openai_endpoint' );

		if ( $use_azure ) {
			$azure_api_key = get_option( 'microsoft_azure_openai_api_key' );
			$azure_api_version = get_option( 'microsoft_azure_openai_api_version' );
			$azure_api_endpoint = get_option( 'microsoft_azure_openai_endpoint' );
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
		} else {
			$openai_api_key = get_option( 'openai_api_key' );
			if ( ! $openai_api_key ) {
				throw new Exception( 'OpenAI API Key not set' );
			}

			static::$instance = new static( $openai_api_key );
		}

		return static::$instance;
	}

	public function edit(
		string $input,
		string $instruction,
		string $model = "text-davinci-edit-001",
		int $n = 1,
		float $temperature = null,
		float $top_p = null,
	) : Edit {
		$response = $this->request(
			'/edits',
			'POST',
			[
			'model' => $model,
			'input' => $input,
			'instruction' => $instruction,
			'n' => $n,
			'temperature' => $temperature,
			'top_p' => $top_p,
			]
		);

		return Edit::from_data(json_decode(wp_remote_retrieve_body($response)));
	}

	public function chat(
		array $messages,
		string $model = "gpt-3.5-turbo",
		int $n = 1,
		array $functions = null,
		array $function_call = null,
		float $temperature = null,
		float $top_p = null,
		string $stop = null,
		int $max_tokens = null,
		float $presence_penalty = null,
		float $frequency_penalty = null,
		array $logit_bias = null,
		string $user = null
	) : Chat {
		$response = $this->request(
			'/chat/completions',
			'POST',
			array_filter(
				[
				'messages' => $messages,
				'model' => $model,
				'n' => $n,
				'temperature' => $temperature,
				'top_p' => $top_p,
				'stop' => $stop,
				'max_tokens' => $max_tokens,
				'presence_penalty' => $presence_penalty,
				'frequency_penalty' => $frequency_penalty,
				'logit_bias' => $logit_bias,
				'user' => $user,
				'functions' => $functions,
				'function_call' => $function_call,
				]
			)
		);
		return Chat::from_data( json_decode( wp_remote_retrieve_body( $response ) ) );
	}

	public function chat_streamed(
		array $messages,
		string $model = "gpt-3.5-turbo",
		int $n = 1,
		float $temperature = null,
		float $top_p = null,
		string $stop = null,
		int $max_tokens = null,
		float $presence_penalty = null,
		float $frequency_penalty = null,
		array $logit_bias = null,
		string $user = null
	) : IteratorAggregate {
		$client = $this->get_openai_client();
		$stream = $client->chat()->createStreamed(
			array_filter(
				[
				'messages' => $messages,
				'model' => $model,
				'n' => $n,
				'temperature' => $temperature,
				'top_p' => $top_p,
				'stop' => $stop,
				'max_tokens' => $max_tokens,
				'presence_penalty' => $presence_penalty,
				'frequency_penalty' => $frequency_penalty,
				'logit_bias' => $logit_bias,
				'user' => $user
				]
			)
		);
		return new Chat_Stream( $stream );
	}

	protected function get_openai_client() : OpenAI\Client {
		$base_uri = str_replace( 'https://', '', $this->base_url );

		if ( strpos( $this->base_url, 'azure.com' ) ) {
			$client = OpenAI::factory()
				->withApiKey( $this->api_key )
				->withBaseUri( $base_uri )
				->withQueryParam( 'api-version', $this->azure_api_version )
				->withHttpHeader( 'api-key', $this->api_key )
				->make();
		} else {
			$client = OpenAI::client( $this->api_key );
		}
		return $client;
	}

	public function request( string $endpoint, string $method, array $data = [] ) {

		$url = "{$this->base_url}$endpoint";
		$args = [
			'method' => $method,
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'timeout' => 60, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- Intentionally longer as OpenAI can take a while to respond.
		];

		if ( strpos( $this->base_url, 'azure.com' ) ) {
			$args['headers']['api-key'] = $this->api_key;
			$url = "$url?api-version=$this->azure_api_version";
		} else {
			$args['headers']['Authorization'] = "Bearer $this->api_key";
		}

		if ($method === 'POST' ) {
			$args['body'] = wp_json_encode($data);
		}

		$response = wp_remote_request($url, $args);

		if ( is_wp_error( $response ) ) {
			throw new Exception( esc_html( $response->get_error_message() ) );
		}

		if ( wp_remote_retrieve_response_code( $response ) > 299 ) {
			$data = json_decode(wp_remote_retrieve_body($response));
			throw new Exception( esc_html( is_array( $data->error->message ) ? implode( "\n", $data->error->message ) : $data->error->message ) );
		}

		return $response;
	}
}
