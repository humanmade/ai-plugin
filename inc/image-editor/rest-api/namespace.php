<?php

namespace AI\Image_Editor\REST_API;

use AI\Segmind;
use AI\Clipdrop;
use WP_Error;
use WP_REST_Response;
use WP_REST_Request;

function bootstrap() : void {
	add_action( 'rest_api_init', register_rest_routes(...) );
}

function register_rest_routes() : void {
	register_rest_route( 'ai/v1', 'image-editor/inpaint', [
		[
			'methods' => 'POST',
			'callback' => inpaint(...),
			'args' => [
				'image' => [
					'type' => 'string',
					'required' => true,
				],
				'mask' => [
					'type' => 'string',
					'required' => true,
				],
				'prompt' => [
					'type' => 'string',
					'required' => true,
				],
				'samples' => [
					'type' => 'integer',
					'default' => 1,
				]
			],
		]
	] );

	register_rest_route( 'ai/v1', 'image-editor/cleanup', [
		[
			'methods' => 'POST',
			'callback' => cleanup(...),
			'args' => [
				'image' => [
					'type' => 'string',
					'required' => true,
				],
				'mask' => [
					'type' => 'string',
					'required' => true,
				],
			],
		]
	] );

	register_rest_route( 'ai/v1', 'image-editor/remove-background', [
		[
			'methods' => 'POST',
			'callback' => remove_background(...),
			'args' => [
				'image' => [
					'type' => 'string',
					'required' => true,
				],
			],
		]
	] );

	register_rest_route( 'ai/v1', 'image-editor/replace-background', [
		[
			'methods' => 'POST',
			'callback' => replace_background(...),
			'args' => [
				'image' => [
					'type' => 'string',
					'required' => true,
				],
				'prompt' => [
					'type' => 'string',
					'required' => true,
				],
			],
		]
	] );

	register_rest_route( 'ai/v1', 'image-editor/upscale', [
		[
			'methods' => 'POST',
			'callback' => upscale(...),
			'args' => [
				'image' => [
					'type' => 'string',
					'required' => true,
				],
			],
		]
	] );
}

function inpaint( WP_REST_Request $request ) {
	$client = Segmind\Client::get_instance();
	file_put_contents( __DIR__ . '/test.jpg', base64_decode( $request['image'] ) );
	file_put_contents( __DIR__ . '/test-mask.jpg', base64_decode( $request['mask'] ) );
	$data = $client->sdxl_inpainting(
		image_data: base64_decode( $request['image'] ),
		mask_data: base64_decode( $request['mask'] ),
		prompt: $request['prompt'],
		samples: $request['samples'],
	);

	file_put_contents( __DIR__ . '/test-out.jpg', base64_decode( $data['image'][0] ) );
	return $data;
}

function cleanup( WP_REST_Request $request ) {
	$client = Clipdrop\Client::get_instance();
	file_put_contents( __DIR__ . '/test.jpg', base64_decode( $request['image'] ) );
	file_put_contents( __DIR__ . '/test-mask.png', base64_decode( $request['mask'] ) );
	$data = $client->cleanup(
		image_data: base64_decode( $request['image'] ),
		mask_data: base64_decode( $request['mask'] ),
	);
	return [
		'image' => base64_encode($data),
	];
}

function remove_background( WP_REST_Request $request ) {
	$client = Clipdrop\Client::get_instance();
	$data = $client->remove_background(
		image_data: base64_decode( $request['image'] ),
	);
	return [
		'image' => base64_encode($data),
	];
}

function replace_background( WP_REST_Request $request ) {
	$client = Clipdrop\Client::get_instance();
	$data = $client->replace_background(
		image_data: base64_decode( $request['image'] ),
		prompt: $request['prompt'],
	);
	return [
		'image' => base64_encode($data),
	];
}


function upscale( WP_REST_Request $request ) {
	$client = Segmind\Client::get_instance();
	$image = base64_decode( $request['image'] );

	$data = $client->esrgan(
		image_data: $image,
		scale: 2,
	);
	return [
		'image' => base64_encode($data),
	];
}
