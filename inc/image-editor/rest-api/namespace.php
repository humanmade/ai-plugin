<?php

namespace AI\Image_Editor\REST_API;

use AI\Segmind;
use AI\Clipdrop;
use Exception;
use Imagick;
use WP_Error;
use WP_Image_Editor_Imagick;
use WP_Post;
use WP_REST_Response;
use WP_REST_Request;

use function HM\AWS_Rekognition\update_attachment_data;

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

	register_rest_field( 'attachment', 'image_blob', [
		'get_callback' => '__return_null',
		'update_callback' => update_attachment_image_blob(...),
		'schema' => [
			'description' => 'Image blob, write only. A base64 image block to update the attachment to.',
			'type' => 'string',
			'context' => [ 'edit' ],
		],
	] );
}

function update_attachment_image_blob( string $blob, WP_Post $post ) {
	$image_data = base64_decode( $blob );
	// Update the attachment with the image

	$file = tempnam( sys_get_temp_dir(), 'wp-image-editor' );
	file_put_contents( $file, $image_data );
	$mime = wp_get_image_mime( $file );

	wp_update_post( [
		'ID' => $post->ID,
		'post_mime_type' => $mime,
	] );

	$extension = wp_get_default_extension_for_mime_type( $mime );
	$current_file = get_attached_file( $post->ID );
	$filename = pathinfo( $current_file, PATHINFO_DIRNAME ) . '/' . pathinfo( $current_file, PATHINFO_FILENAME ) . '-' . time() . '.' . $extension;
	copy( $file, $filename );

	require_once ABSPATH . 'wp-admin/includes/image.php';
	update_attached_file( $post->ID, $filename );
	$metadata = wp_generate_attachment_metadata( $post->ID, $filename );
	update_attachment_data( $post->ID, $metadata );
}

function inpaint( WP_REST_Request $request ) {
	try {
		$client = Segmind\Client::get_instance();
		// Image must be a JPEG, so convert it.
		$imagick = new Imagick;
		$imagick->readImageBlob( base64_decode( $request['image'] ) );
		$imagick->setFormat( 'jpeg' );
		$imagick->setImageCompressionQuality( 100 );
		$image_data = $imagick->getImageBlob();

		$data = $client->sdxl_inpainting(
			image_data: $image_data,
			mask_data: base64_decode( $request['mask'] ),
			prompt: $request['prompt'],
			samples: $request['samples'],
		);
	} catch ( Exception $e ) {
		return new WP_Error( 'clipdrop_error', $e->getMessage() );
	}
	return $data;
}

function cleanup( WP_REST_Request $request ) {
	try {
		$client = Clipdrop\Client::get_instance();
		$data = $client->cleanup(
			image_data: base64_decode( $request['image'] ),
			mask_data: base64_decode( $request['mask'] ),
		);
	} catch ( Exception $e ) {
		return new WP_Error( 'clipdrop_error', $e->getMessage() );
	}
	return [
		'image' => base64_encode($data),
	];
}

function remove_background( WP_REST_Request $request ) {
	try {
		$client = Clipdrop\Client::get_instance();
		$data = $client->remove_background(
			image_data: base64_decode( $request['image'] ),
		);
	} catch ( Exception $e ) {
		return new WP_Error( 'clipdrop_error', $e->getMessage() );
	}
	return [
		'image' => base64_encode($data),
	];
}

function replace_background( WP_REST_Request $request ) {
	try {
		$client = Clipdrop\Client::get_instance();
		$data = $client->replace_background(
			image_data: base64_decode( $request['image'] ),
			prompt: $request['prompt'],
		);
	} catch ( Exception $e ) {
		return new WP_Error( 'clipdrop_error', $e->getMessage() );
	}
	return [
		'image' => base64_encode($data),
	];
}

function upscale( WP_REST_Request $request ) {
	try {
		$client = Segmind\Client::get_instance();
		$image = base64_decode( $request['image'] );

		$data = $client->esrgan(
			image_data: $image,
			scale: 2,
		);
	} catch ( Exception $e ) {
		return new WP_Error( 'clipdrop_error', $e->getMessage() );
	}
	return [
		'image' => base64_encode($data),
	];
}
