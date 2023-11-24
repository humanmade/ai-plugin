<?php

namespace AI\Clipdrop;

use Exception;

use GuzzleHttp;

class Client {
	protected string $api_key;
	public string $base_url = 'https://clipdrop-api.co';
	protected static Client $instance;

	public static function get_instance() : static {
		if ( ! isset( static::$instance ) ) {
			$api_key = get_option( 'clipdrop_api_key' );
			if ( ! $api_key ) {
				throw new Exception( 'Clipdrop API Key not set' );
			}
			static::$instance = new static( $api_key );
		}

		return static::$instance;
	}

	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	public function cleanup( string $image_data, string $mask_data ) : string {
		$response = $this->request( '/cleanup/v1', 'POST', [
			[
				'name'     => 'image_file',
				'contents' => $image_data,
				'filename' => 'image.jpg'
			],
			[
				'name'     => 'mask_file',
				'contents' => $mask_data,
				'filename' => 'mask.png'
			],

		] );

		return (string) $response->getBody();
	}

	public function remove_background( string $image_data ) : string {
		$response = $this->request( 'remove-background/v1', 'POST', [
			[
				'name'     => 'image_file',
				'contents' => $image_data,
				'filename' => 'image.jpg'
			],
		] );

		return (string) $response->getBody();
	}

	public function replace_background( string $image_data, string $prompt ) : string {
		$response = $this->request( 'replace-background/v1', 'POST', [
			[
				'name'     => 'image_file',
				'contents' => $image_data,
				'filename' => 'image.jpg'
			],
			[
				'name'     => 'prompt',
				'contents' => $prompt,
			],
		] );

		return (string) $response->getBody();
	}

	public function image_upscaling( string $image_data, int $width, int $height ) : string {
		$response = $this->request( 'image-upscaling/v1/upscale', 'POST', [
			[
				'name'     => 'image_file',
				'contents' => $image_data,
				'filename' => 'image.jpg'
			],
			[
				'name'     => 'target_width',
				'contents' => $width,
			],
			[
				'name'     => 'target_height',
				'contents' => $height,
			],
		] );

		return (string) $response->getBody();
	}

	public function request( string $endpoint, string $method, $data = null ) : GuzzleHttp\Psr7\Response {

		$client = new GuzzleHttp\Client([
			'base_uri' => $this->base_url,
			'headers' => [
				'x-api-key' => $this->api_key,
				'Accept' => 'image/png',
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
			throw new Exception( $body['error'] );
		}
	}
}
