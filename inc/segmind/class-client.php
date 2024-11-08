<?php

namespace AI\Segmind;

use Exception;

class Client {
	protected string $api_key;
	public string $base_url = 'https://api.segmind.com';
	protected static Client $instance;

	public static function get_instance() : static {
		if ( ! isset( static::$instance ) ) {
			$api_key = get_option( 'segmind_api_key' );
			if ( ! $api_key ) {
				throw new Exception( 'Segmind API Key not set' );
			}
			static::$instance = new static( $api_key );
		}

		return static::$instance;
	}

	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * ESRGAN: Enhanced Super-Resolution Generative Adversarial Networks
	 *
	 * ESRGAN, or Enhanced Super-Resolution Generative Adversarial Networks,
	 * is a cutting-edge model designed to reconstruct high-resolution (HR)
	 * images or sequences from lower-resolution (LR) observations. This technology
	 * is particularly useful in upscaling images, for example, transforming a 720p
	 * image into a 1080p one. ESRGAN employs deep convolutional neural networks to
	 * recover HR images from LR ones, with the generator network learning to
	 * create realistic images and the discriminator network learning to differentiate
	 * between real and generated images. Through a process of competition and feedback,
	 * the generator network improves its ability to create high-quality images.
	 *
	 * @param string $image_Data Raw data of the image.
	 * @param int $scale Scale of the output image. Min 2, max 8.
	 * @return string The raw image data of the up-scaled image.
	 */
	public function esrgan( string $image_data, int $scale = 2 ) : string {
		$response = $this->request( '/v1/esrgan', 'POST', [
			'image' => base64_encode( $image_data ),
			'scale' => $scale,
		] );

		return $response['body'];
	}

	public function sdxl_inpainting(
		string $image_data,
		string $mask_data,
		string $prompt,
		int $samples = 1
		) : array {
		$data = [
			"prompt" => $prompt,
			"negative_prompt" => 0,
			"samples" => 4,
			"scheduler" => 'Euler a',
			"num_inference_steps" => 20,
			"guidance_scale" => 25,
			"strength" => 0.9,
			"base64" => true,
			'image' => base64_encode( $image_data ),
			'mask' => base64_encode( $mask_data ),
		];
		$response = $this->request( '/v1/sdxl-inpaint', 'POST', $data );
		return json_decode( $response['body'], true );
	}

	/**
	 * Background Removal
	 *
	 * Background removal is a cutting edge model that is designed to instantly remove
	 * the background of an image or an object in an image using generative AI. Using
	 * Segmind’s free background removal model, you can automatically detect the subject
	 * from any image and remove background instantly without any hassle. Harnessing
	 * the power of our artificial intelligence tool, it's easy to handle hair, animal fur,
	 * or complex edges in just a few seconds.
	 *
	 * @param string $image_data
	 * @param string $method "object" | "image"
	 * @return string
	 */
	public function background_removal( string $image_data, string $method = 'object' ) : string {
		$response = $this->request( '/v1/bg-removal', 'POST', [
			'image' => base64_encode( $image_data ),
			'method' => $method,
		] );

		return $response['body'];
	}

	public function request( string $endpoint, string $method, $data = null ) {
		$url = "{$this->base_url}{$endpoint}";

		$args = [
			'method' => $method,
			'headers' => [
				'x-api-key' => $this->api_key,
			],
			'timeout' => 60,
		];

		if ( $method === 'POST' ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body'] = json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}

		if ( wp_remote_retrieve_response_code( $response ) > 299 ) {
			error_log( wp_remote_retrieve_body( $response ) );
			$data = json_decode( wp_remote_retrieve_body( $response ) );
			error_log( print_r( $data, true ) );
			throw new Exception( sprintf( "Error from Segmind API, code %d", wp_remote_retrieve_response_code( $response ) ) );
		}

		return $response;
	}
}
