<?php

namespace AI\Image_Editor\Admin;

function bootstrap() : void {
	add_action( 'wp_ajax_image-editor', __NAMESPACE__ . '\\change_attachment_mimetype_on_background_removal', 0 );
}

function enqueue_admin_script() : void {
	$upscale = include __DIR__ . '/build/upscale.asset.php';
	wp_enqueue_script( 'upscale-js', plugin_dir_url( __FILE__ ) . '/build/upscale.js', $upscale['dependencies'], $upscale['version'], true );
}

function change_attachment_mimetype_on_background_removal() {
	if ( ! isset( $_REQUEST['remove_background'] ) ) {
		return;
	}

	$post = get_post( (int) $_REQUEST['postid'] );
	$mime_types_with_transparency = [
		'image/png',
		'image/webp',
		'image/gif',
	];

	if ( in_array( $post->post_mime_type, $mime_types_with_transparency, true ) ) {
		return;
	}

	// Convert the image to PNG.
	$file = get_attached_file( $post->ID );
	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) ) {
		return;
	}

	$new_file = substr( $file, 0, - strlen( pathinfo( $file, PATHINFO_EXTENSION ) ) ) . 'png';
	$editor->save( $new_file );

	update_attached_file( $post->ID, $new_file );
	wp_update_post( [
		'ID' => $post->ID,
		'post_mime_type' => 'image/png',
	] );
}
