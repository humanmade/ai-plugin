<?php

namespace AI\DreamStudio;

use Exception;

use GuzzleHttp;

class Client {
	protected string $api_key;
	public string $base_url = 'https://api.stability.ai';
	protected static Client $instance;

	public static function get_instance() : static {
		if ( ! isset( static::$instance ) ) {
			$api_key = get_option( 'dreamstudio_api_key' );
			if ( ! $api_key ) {
				throw new Exception( 'DreamStudio API Key not set' );
			}
			static::$instance = new static( $api_key );
		}

		return static::$instance;
	}

	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	public function inpainting( string $image_data, string $mask_data, string $prompt ) : array {
		$response = $this->request( '/v1/generation/stable-diffusion-v1-6/image-to-image/masking', 'POST', [
			[
				'name' => 'text_prompts[0][text]',
				'contents' => $prompt,
			],
			[
				'name'     => 'init_image',
				'contents' => $image_data,
			],
			[
				'name' => 'mask_source',
				'contents' => 'MASK_IMAGE_WHITE',
			],
			[
				'name'     => 'mask_image',
				'contents' => $mask_data,
			],

		] );

		return json_decode( $response->getBody()->getContents(), true )['artifacts'];
	}

	public function request( string $endpoint, string $method, $data = null ) : GuzzleHttp\Psr7\Response {

		$client = new GuzzleHttp\Client([
			'base_uri' => $this->base_url,
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_key,
			],
		]);

		try {
			return $client->request( $method, $endpoint, [
				'multipart' => $data,
			] );
		} catch ( GuzzleHttp\Exception\ClientException $e ) {
			$response = $e->getResponse();
			$body = $response->getBody()->getContents();
			$body = json_decode( $body, true );
			throw new Exception( $body['message'] );
		}
	}
}
